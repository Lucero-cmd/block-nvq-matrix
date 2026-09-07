<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX endpoint for PERMANENTLY deleting an archived student's NVQ matrix
 * data for a specific course - grades, sampling records, unit comments,
 * final status, and their evidence comments/types.
 *
 * Accepts a POST with studentid, courseid. Gated on the new
 * block/nvq_matrix:deletearchived capability, deliberately separate from
 * :viewall - being able to SEE the archived list doesn't mean being
 * allowed to permanently destroy data from it. Independently re-verifies
 * the target is genuinely archived (not actively enrolled) regardless of
 * who holds the capability, since this action is irreversible and this
 * plugin has already been burned once by scoping bugs in this exact
 * area (v26.5.3-v26.5.6) - never trust a client-supplied studentid/
 * courseid pair without re-checking server-side.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

header('Content-Type: application/json');

try {
    require_sesskey();
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => get_string('deletearchivednopermission', 'block_nvq_matrix')]);
    die();
}

$studentid = required_param('studentid', PARAM_INT);
$courseid  = required_param('courseid', PARAM_INT);

$response = ['success' => false];

if ($studentid <= 0 || $courseid <= 0) {
    $response['error'] = get_string('deletearchivederror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

// Check 1 — capability, scoped to this exact course context. Separate
// from :viewall by design (see file docblock above).
if (!$coursecontext || !has_capability('block/nvq_matrix:deletearchived', $coursecontext)) {
    http_response_code(403);
    $response['error'] = get_string('deletearchivednopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// Check 2 — the safety guard this endpoint exists to enforce: only a
// GENUINELY archived (not actively enrolled) student+course pair is
// ever eligible for deletion, no matter what capability the caller
// holds or what the request claims. Mirrors the exact archived
// determination already used in view.php.
if (is_enrolled($coursecontext, $studentid, '', true)) {
    http_response_code(409);
    $response['error'] = get_string('deletearchivedstillenrolled', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

global $DB, $USER;

// Check 3 — real bug fixed here (v26.6.3): a group-restricted Company
// Manager's visibility into the archived list is enforced entirely by
// view.php's display logic (see its own extensive comments on this
// exact leak, v26.4.24-26). This endpoint never re-verified that
// server-side - a Company Manager holding :deletearchived could submit
// ANY studentid/courseid pair directly, bypassing the UI entirely, and
// delete another company's archived data they were never shown.
// Fail-closed, matching this plugin's already-established "no group =
// sees nobody" rule for company managers: only applies when the caller
// actually holds the companymanager role on this course, so a full
// teacher/manager/admin's ability to delete is completely unaffected.
$callerroles = get_user_roles($coursecontext, $USER->id, true);
$callerroleshortnames = array_map(function ($r) {
    return $r->shortname;
}, $callerroles);
if (in_array('companymanager', $callerroleshortnames, true)) {
    $callergroups = groups_get_user_groups($courseid, $USER->id);
    $callergroupids = $callergroups[0] ?? [];
    $targetsharesgroup = false;
    if (!empty($callergroupids)) {
        list($ginsql, $ginparams) = $DB->get_in_or_equal($callergroupids, SQL_PARAMS_NAMED, 'delgrp');
        $ginparams['targetuser'] = $studentid;
        $targetsharesgroup = $DB->record_exists_select(
            'groups_members',
            "userid = :targetuser AND groupid $ginsql",
            $ginparams
        );
    }
    if (!$targetsharesgroup) {
        http_response_code(403);
        $response['error'] = get_string('deletearchivednopermission', 'block_nvq_matrix');
        echo json_encode($response);
        die();
    }
}

try {
    $transaction = $DB->start_delegated_transaction();

    // Snapshots every grade/sampling/unit_comment/status row for this
    // student+course into its history table BEFORE the deletes below -
    // see matrix_data::snapshot_all_before_permanent_delete()'s own
    // docblock for why this matters here specifically. Inside the same
    // transaction, so a failure partway through the deletes below rolls
    // this back too rather than leaving orphaned history for data that
    // (thanks to the rollback) still exists live.
    \block_nvq_matrix\matrix_data::snapshot_all_before_permanent_delete($studentid, $courseid);

    $DB->delete_records('block_nvq_matrix_grades', ['studentid' => $studentid, 'courseid' => $courseid]);
    $DB->delete_records('block_nvq_matrix_sampling', ['studentid' => $studentid, 'courseid' => $courseid]);
    $DB->delete_records('block_nvq_matrix_unit_comments', ['studentid' => $studentid, 'courseid' => $courseid]);
    $DB->delete_records('block_nvq_matrix_status', ['studentid' => $studentid, 'courseid' => $courseid]);

    // block_nvq_matrix_evidence_comments has no courseid (or even
    // topicid) column of its own - only mmid, a foreign key into
    // exacomp's own block_exacompcompuser_mm table. Resolve which mmid
    // values actually belong to THIS student+course via the same
    // three-table join chain used elsewhere in this plugin (matrix_data
    // .php's own topicid resolution, get_portfolio_links()) before
    // deleting - never delete by studentid alone, that would remove
    // this student's evidence comments across EVERY course, not just
    // the archived one being deleted.
    $mmids = $DB->get_fieldset_sql("
        SELECT DISTINCT mm.id
          FROM {block_exacompcompuser_mm} mm
          JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
          JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
         WHERE mm.userid   = :studentid
           AND ct.courseid = :courseid
    ", ['studentid' => $studentid, 'courseid' => $courseid]);

    if (!empty($mmids)) {
        list($mmidinsql, $mmidparams) = $DB->get_in_or_equal($mmids, SQL_PARAMS_NAMED, 'delmm');
        $mmidparams['studentid'] = $studentid;
        $commentids = $DB->get_fieldset_select(
            'block_nvq_matrix_evidence_comments',
            'id',
            "studentid = :studentid AND mmid $mmidinsql",
            $mmidparams
        );

        if (!empty($commentids)) {
            list($commentinsql, $commentparams) = $DB->get_in_or_equal($commentids, SQL_PARAMS_NAMED, 'delcm');
            // Child table first - evidence_types has no ON DELETE
            // CASCADE of its own in install.xml, so leaving this out
            // would strand orphaned type rows pointing at a deleted
            // comment.
            $DB->delete_records_select('block_nvq_matrix_evidence_types', "evidencecommentid $commentinsql", $commentparams);
            $DB->delete_records_select('block_nvq_matrix_evidence_comments', "id $commentinsql", $commentparams);
        }
    }

    // Record this clearing so the student's OWN archived-course switcher
    // (view.php) also stops showing this course - without this, the
    // student would still see it as archived, since that detection is
    // based purely on exacomp/exaport evidence presence, which is
    // deliberately untouched above (this plugin never modifies
    // third-party evidence data). A repeat delete on the same pair (e.g.
    // if new grade/sampling rows were somehow created again before being
    // re-archived) just updates the existing marker rather than erroring
    // on the unique studentid+courseid index.
    $existingclear = $DB->get_record('block_nvq_matrix_cleared_archive', [
        'studentid' => $studentid, 'courseid' => $courseid,
    ]);
    if ($existingclear) {
        $existingclear->timecleared = time();
        $existingclear->clearedby   = $USER->id;
        $DB->update_record('block_nvq_matrix_cleared_archive', $existingclear);
    } else {
        $DB->insert_record('block_nvq_matrix_cleared_archive', (object) [
            'studentid'   => $studentid,
            'courseid'    => $courseid,
            'timecleared' => time(),
            'clearedby'   => $USER->id,
        ]);
    }

    $transaction->allow_commit();

    $response['success'] = true;
    $response['message'] = get_string('deletearchivedsuccess', 'block_nvq_matrix');
} catch (\Throwable $e) {
    // Real bug fixed here (v26.6.3): moodle_transaction::rollback($e)
    // does not just roll back and return - by Moodle's own design it
    // re-throws the exception it's given, immediately, as part of
    // force_transaction_rollback(). Every line that used to sit after
    // it in this catch block (debugging(), $response['error'],
    // $response['debugmessage']) was therefore dead code - it never
    // ran, because rollback() had already re-thrown before reaching it.
    // The re-thrown exception then propagated all the way up uncaught,
    // so the browser got Moodle's default HTML error page instead of
    // this endpoint's JSON - which is exactly why every diagnostic
    // added here in v26.6.1/v26.6.2 could never actually surface.
    //
    // Fix: build the full JSON response FIRST, then roll back inside
    // its own nested try/catch so its re-throw can't swallow the
    // response we already built.
    debugging('block_nvq_matrix delete_archived.php failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $response['error'] = get_string('deletearchivederror', 'block_nvq_matrix');
    // Included directly in the JSON too, not just the log - this
    // endpoint is only ever reachable past the capability check above
    // (editingteacher/manager), so there's no meaningful disclosure risk
    // in showing them the real cause instead of sending them back to
    // guess from a generic message a second time.
    $response['debugmessage'] = $e->getMessage();

    if (!empty($transaction)) {
        try {
            $transaction->rollback($e);
        } catch (\Throwable $ignored) {
            // Expected - rollback() re-throws $e by design. The actual
            // rollback has already happened by this point; the response
            // above is already built, so there's nothing left to do
            // with the re-thrown exception here.
            unset($ignored);
        }
    }
}

echo json_encode($response);
