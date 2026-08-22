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
 * AJAX endpoint for saving the optional unit-level IQA comment in the NVQ matrix.
 *
 * Accepts a POST with topicid, studentid, courseid, comment. Independent of
 * the grade/sampling verdicts and of the existing grade comment — either
 * may be set, cleared, or left blank on its own.
 *
 * Gated on block/nvq_matrix:iqacomment — deliberately granted to the
 * 'teacher' archetype on this site, since 'teacher' is the role used for
 * IQA reviewers and this is their write path for this one field only (they
 * still can't grade or sample).
 *
 * Note: this endpoint originally also supported an independent "assessor
 * comment" (type=assessor), removed per client feedback — the existing
 * grade comment (part of the grading controls) already covers that.
 *
 * Same two-check pattern as grade.php/sample.php: (1) courseid genuinely
 * belongs to the topic, (2) capability scoped to that exact course context,
 * with the target student verified as a real student (not a peer
 * assessor/manager/IQA who happens to be enrolled).
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
    echo json_encode(['success' => false, 'error' => get_string('unitcommentnopermission', 'block_nvq_matrix')]);
    die();
}

$topicid   = required_param('topicid', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);
$courseid  = required_param('courseid', PARAM_INT);
$comment   = optional_param('comment', '', PARAM_TEXT);
$commentdatestr = optional_param('commentdate', '', PARAM_TEXT);

$response = ['success' => false];

if ($topicid <= 0 || $studentid <= 0 || $courseid <= 0) {
    $response['error'] = get_string('unitcommenterror', 'block_nvq_matrix');
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
    $response['error'] = get_string('unitcommenterror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// ----------------------------------------------------------------
// Check 2 — capability, scoped to this exact course context.
// ----------------------------------------------------------------
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

if (!$coursecontext
    || !has_capability('block/nvq_matrix:iqacomment', $coursecontext)
    || !is_enrolled($coursecontext, $studentid)
    || has_capability('block/nvq_matrix:viewall', $coursecontext, $studentid)
) {
    http_response_code(403);
    $response['error'] = get_string('unitcommentnopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// ----------------------------------------------------------------
// Save.
// ----------------------------------------------------------------
try {
    // Same fix as evidence_comment.php/grade.php: the byline shown right
    // after saving must reflect the actual saved timestamp (respecting a
    // backdated comment date), not always "now".
    $commentdate = matrix_data::parse_comment_date($commentdatestr);
    $savedtime = $commentdate > 0 ? $commentdate : time();
    matrix_data::save_unit_comment($topicid, $studentid, $courseid, $comment, $commentdate);
    $response['success'] = true;
    $response['message'] = get_string('unitcommentsaved', 'block_nvq_matrix');
    $response['comment'] = $comment;
    $response['commentbyline'] = trim($comment) === ''
        ? ''
        : matrix_data::format_comment_byline((int) $USER->id, $savedtime, [(int) $USER->id => fullname($USER)]);
} catch (\Throwable $e) {
    $response['error'] = get_string('unitcommenterror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
