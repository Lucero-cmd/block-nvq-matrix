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
 * AJAX endpoint for saving a unit-level grade verdict in the NVQ matrix.
 *
 * Accepts a POST with topicid, studentid, courseid, value (0/1) and an
 * optional comment. Every submission is validated server-side against three
 * independent checks before anything is written:
 *
 * 1. courseid genuinely belongs to the topic (block_exacompcoutopi_mm).
 *    Stops a crafted courseid attributing a grade to the wrong course.
 * 2. The calling user holds block/nvq_matrix:grade in that exact course
 *    context, and the target student is enrolled there without also holding
 *    block/nvq_matrix:viewall (i.e. is genuinely a student, not a peer
 *    assessor/manager/EQA). Deliberately uses :grade, not :viewall — the
 *    'teacher' archetype (non-editing teacher / EQA) has viewall but not
 *    grade, so it can see verdicts but never set them.
 * 3. At least one criterion under this topic has evidence linked for this
 *    student — grading is not permitted before evidence exists.
 *
 * Writes to block_nvq_matrix_grades — this plugin's own dedicated table,
 * never to exacomp's tables. This keeps grading fully isolated from
 * exacomp's own evidence-upload auto-seeding behaviour.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\matrix_data;

require_login();

header('Content-Type: application/json');

// require_sesskey() throws an HTML exception page on failure, which the
// fetch() caller can't parse as JSON — wrap it so timeouts always return JSON.
try {
    require_sesskey();
} catch (\Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => get_string('gradenopermission', 'block_nvq_matrix')]);
    die();
}

// ----------------------------------------------------------------
// Read and validate parameters.
// action=clear removes the grade entirely (distinct from value=0, which is
// itself a real "Not Yet Competent" verdict); value is ignored when clearing.
// ----------------------------------------------------------------
$topicid   = required_param('topicid', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);
$courseid  = required_param('courseid', PARAM_INT);
$action    = optional_param('action', 'set', PARAM_ALPHA);
$value     = optional_param('value', 0, PARAM_INT);
$comment   = optional_param('comment', '', PARAM_TEXT);
$commentdatestr = optional_param('commentdate', '', PARAM_TEXT);
// Per-entry checkbox (v26.6.20) - "Also backdate the audit trail to
// this date too", alongside settings.php's site-wide Migration mode.
// Either can trigger matrix_data::resolve_archivedtime()'s backdating -
// see that method's own docblock for the full explanation of why both
// exist. Ignored entirely if no commentdate was actually submitted.
$backdateaudit = optional_param('backdateaudit', 0, PARAM_BOOL);

$isclear  = ($action === 'clear');
$iscommentonly = ($action === 'comment');
$response = ['success' => false];

if (!$isclear && !$iscommentonly && $value !== 0 && $value !== 1) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

if ($topicid <= 0 || $studentid <= 0 || $courseid <= 0) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

global $DB;

// ----------------------------------------------------------------
// Check 1 — courseid genuinely belongs to this topic.
// ----------------------------------------------------------------
$validcourseforthistopic = $DB->record_exists('block_exacompcoutopi_mm', [
    'topicid'  => $topicid,
    'courseid' => $courseid,
]);

if (!$validcourseforthistopic) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// ----------------------------------------------------------------
// Check 2 — capability, scoped to this exact course context.
// Uses block/nvq_matrix:grade specifically, not :viewall — the 'teacher'
// archetype (non-editing teacher / EQA) holds viewall but not grade, so
// it can see verdicts but never set them.
// ----------------------------------------------------------------
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

if (!$coursecontext
    || !has_capability('block/nvq_matrix:grade', $coursecontext)
    || !is_enrolled($coursecontext, $studentid)
    || has_capability('block/nvq_matrix:viewall', $coursecontext, $studentid)
) {
    http_response_code(403);
    $response['error'] = get_string('gradenopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// ----------------------------------------------------------------
// Check 3 — evidence must exist for this descriptor before it can be graded.
// Skipped when clearing or comment-only: removing/editing the comment or
// verdict independently should always be possible, even if evidence was
// later removed.
// ----------------------------------------------------------------
if (!$isclear && !$iscommentonly) {
    $hasevidence = $DB->record_exists_sql("
        SELECT 1
          FROM {block_exacompcompuser_mm} mm
          JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
         WHERE mm.userid         = :userid
           AND mm.eportfolioitem = 1
           AND dtm.topicid       = :topicid
    ", ['userid' => $studentid, 'topicid' => $topicid]);

    if (!$hasevidence) {
        $response['error'] = get_string('gradenoevidence', 'block_nvq_matrix');
        echo json_encode($response);
        die();
    }
}

// ----------------------------------------------------------------
// Save or clear.
// ----------------------------------------------------------------
try {
    // As with the evidence-comment and unit-comment endpoints: the byline
    // echoed back immediately after saving must reflect the timestamp
    // actually written to the DB, not always "now" - otherwise a
    // backdated comment date saves correctly but the on-screen byline
    // still shows today until the page is reloaded (client-reported bug).
    $commentdate = matrix_data::parse_comment_date($commentdatestr);
    $savedtime = $commentdate > 0 ? $commentdate : time();
    if ($isclear) {
        matrix_data::clear_grade($topicid, $studentid, $courseid);
        $response['success'] = true;
        $response['message'] = get_string('gradecleared', 'block_nvq_matrix');
        $response['cleared'] = true;
    } else if ($iscommentonly) {
        matrix_data::save_grade_comment($topicid, $studentid, $courseid, $comment, $commentdate, $backdateaudit);
        $response['success'] = true;
        $response['message'] = get_string('gradesaved', 'block_nvq_matrix');
        $response['commentbyline'] = trim($comment) === ''
            ? ''
            : matrix_data::format_comment_byline((int) $USER->id, $savedtime, [(int) $USER->id => fullname($USER)]);
    } else {
        matrix_data::save_grade($topicid, $studentid, $courseid, $value, $comment, $commentdate, $backdateaudit);
        $response['success'] = true;
        $response['message'] = get_string('gradesaved', 'block_nvq_matrix');
        $response['commentbyline'] = trim($comment) === ''
            ? ''
            : matrix_data::format_comment_byline((int) $USER->id, $savedtime, [(int) $USER->id => fullname($USER)]);
    }
} catch (\Throwable $e) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
