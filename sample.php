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
 * AJAX endpoint for saving a unit-level sampling status in the NVQ matrix.
 *
 * Accepts a POST with topicid, studentid, courseid, status (0/1/2), and an
 * optional sampledate (YYYY-MM-DD) to backdate the "sampled on" date - same
 * pattern as the grade/IQA/evidence comment date fields, and previously
 * missing here (v26.6.18): sampling had no backdating support at all,
 * unlike every other timestamped field in this plugin, which became a real
 * gap once Migration mode (v26.6.17) needed a submitted date to backdate
 * against for historical sampling records from a previous platform.
 *
 * Gated on block/nvq_matrix:sample specifically — NOT the same as grading's
 * block/nvq_matrix:viewall. This capability is deliberately withheld from
 * the 'teacher' archetype (used for non-editing EQA/IQA reviewers), who may
 * view the sampling status but must never be able to set it.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
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
    echo json_encode(['success' => false, 'error' => get_string('samplingnopermission', 'block_nvq_matrix')]);
    die();
}

$topicid       = required_param('topicid', PARAM_INT);
$studentid     = required_param('studentid', PARAM_INT);
$courseid      = required_param('courseid', PARAM_INT);
$status        = required_param('status', PARAM_INT);
$sampledatestr = optional_param('sampledate', '', PARAM_TEXT);

$response = ['success' => false];

if (!in_array($status, [0, 1, 2], true)) {
    $response['error'] = get_string('samplingerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

if ($topicid <= 0 || $studentid <= 0 || $courseid <= 0) {
    $response['error'] = get_string('samplingerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

global $DB;

// Check 1 — courseid genuinely belongs to this topic.
$validcourseforthistopic = $DB->record_exists('block_exacompcoutopi_mm', [
    'topicid'  => $topicid,
    'courseid' => $courseid,
]);

if (!$validcourseforthistopic) {
    $response['error'] = get_string('samplingerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// Check 2 — capability, scoped to this exact course context.
// Uses block/nvq_matrix:sample specifically, not :viewall.
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

if (!$coursecontext
    || !has_capability('block/nvq_matrix:sample', $coursecontext)
    || !is_enrolled($coursecontext, $studentid)
    || has_capability('block/nvq_matrix:viewall', $coursecontext, $studentid)
) {
    http_response_code(403);
    $response['error'] = get_string('samplingnopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

try {
    $sampledate = matrix_data::parse_comment_date($sampledatestr);
    matrix_data::save_sampling($topicid, $studentid, $courseid, $status, $sampledate);
    $response['success'] = true;
    $response['message'] = get_string('samplingsaved', 'block_nvq_matrix');
} catch (\Throwable $e) {
    $response['error'] = get_string('samplingerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
