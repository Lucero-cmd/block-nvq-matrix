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
 * AJAX endpoint for the on-demand "History" panel - the first
 * user-facing surface for the audit trail built in v26.6.13
 * (classes/privacy/provider.php's own tables were the backend; this is
 * where an assessor/IQA/EQA/manager actually reads it).
 *
 * Three actions:
 *   - action=unit    : one unit's grade-verdict/comment history AND
 *                       sampling history together (topicid required).
 *   - action=status   : one course's final Pass/Fail status history
 *                        (no topicid - status is course-level, not
 *                        per-unit).
 *   - action=delete   : permanently deletes ONE specific history row.
 *                        Gated on genuine site admin status
 *                        (is_siteadmin()), not :viewall, not manager -
 *                        client decision (2026-09-08): editing the
 *                        audit trail itself is sensitive enough that
 *                        even a Manager shouldn't be able to do it.
 *
 * unit/status are gated on block/nvq_matrix:viewall - deliberately the
 * same capability that already governs staff-side visibility of the
 * live matrix, not a narrower per-field capability (:grade/:sample/
 * :iqacomment) - history is read-only, and anyone who can already see a
 * student's current verdicts should be able to see how they got there.
 * Deliberately does NOT require is_enrolled() on the target student the
 * way grade.php/sample.php do for their write actions - unlike those,
 * this is read-only, and one of the most useful cases for checking
 * history is exactly an already-archived (unenrolled) student, so
 * requiring active enrolment here would defeat the point.
 *
 * Not exposed to the student viewing their own matrix - this endpoint
 * has no "is this the student's own data" branch at all, unlike
 * evidence_type.php's isownevidence path. Deliberate: an audit trail of
 * internal assessor/IQA deliberation (a verdict changed, then changed
 * back) is staff-facing QA information, not something this plugin
 * currently surfaces to the learner it's about.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\matrix_data;

require_login();

header('Content-Type: application/json');

try {
    require_sesskey();
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => get_string('historynopermission', 'block_nvq_matrix')]);
    die();
}

$action    = required_param('action', PARAM_ALPHA); // 'unit' | 'status' | 'delete'
$studentid = required_param('studentid', PARAM_INT);
$courseid  = required_param('courseid', PARAM_INT);
$topicid   = optional_param('topicid', 0, PARAM_INT);

$response = ['success' => false];

if (!in_array($action, ['unit', 'status', 'delete'], true) || $studentid <= 0 || $courseid <= 0) {
    $response['error'] = get_string('historyerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

if ($action === 'unit' && $topicid <= 0) {
    $response['error'] = get_string('historyerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

global $DB;

// action=delete has its own, stricter gate (genuine site admin only) -
// handled entirely separately from the unit/status read actions below,
// since it doesn't need the topic/courseid validation those two do.
if ($action === 'delete') {
    if (!is_siteadmin()) {
        http_response_code(403);
        $response['error'] = get_string('historynopermission', 'block_nvq_matrix');
        echo json_encode($response);
        die();
    }

    $historytype = required_param('historytype', PARAM_ALPHA); // 'grade' | 'sampling' | 'unitcomment' | 'status'
    $historyid   = required_param('historyid', PARAM_INT);

    if ($historyid <= 0 || !in_array($historytype, ['grade', 'sampling', 'unitcomment', 'status'], true)) {
        $response['error'] = get_string('historyerror', 'block_nvq_matrix');
        echo json_encode($response);
        die();
    }

    try {
        $deleted = matrix_data::delete_history_entry($historytype, $historyid, $studentid, $courseid);
        $response['success'] = $deleted;
        if (!$deleted) {
            $response['error'] = get_string('historyerror', 'block_nvq_matrix');
        }
    } catch (\Throwable $e) {
        $response['error'] = get_string('historyerror', 'block_nvq_matrix');
    }

    echo json_encode($response);
    die();
}

// Check 1 — for action=unit, courseid genuinely belongs to this topic,
// same pattern grade.php/sample.php already use.
if ($action === 'unit') {
    $validcourseforthistopic = $DB->record_exists('block_exacompcoutopi_mm', [
        'topicid'  => $topicid,
        'courseid' => $courseid,
    ]);
    if (!$validcourseforthistopic) {
        $response['error'] = get_string('historyerror', 'block_nvq_matrix');
        echo json_encode($response);
        die();
    }
}

// Check 2 — capability, scoped to this exact course context. See file
// docblock above for why this is :viewall and why enrolment is
// deliberately not required.
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

if (!$coursecontext || !has_capability('block/nvq_matrix:viewall', $coursecontext)) {
    http_response_code(403);
    $response['error'] = get_string('historynopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

try {
    if ($action === 'unit') {
        $unithistory = matrix_data::get_unit_history($topicid, $studentid, $courseid);
        $samplinghistory = matrix_data::get_sampling_history($topicid, $studentid, $courseid);
        $response['success']     = true;
        $response['grade']       = $unithistory['grade'];
        $response['unitcomment'] = $unithistory['unitcomment'];
        $response['sampling']    = $samplinghistory;
    } else {
        $response['success'] = true;
        $response['status']  = matrix_data::get_status_history($studentid, $courseid);
    }
} catch (\Throwable $e) {
    $response['error'] = get_string('historyerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
