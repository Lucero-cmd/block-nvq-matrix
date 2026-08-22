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
 * AJAX endpoint for saving the evidence type (APL/EoE/NA/O/P/PD/Q/RA/S/WT)
 * on a single evidence item *occurrence* in the NVQ matrix.
 *
 * Deliberately a separate endpoint from evidence_comment.php even though
 * both write to block_nvq_matrix_evidence_comments — evidence type has a
 * broader capability check (assessor OR IQA, not assessor-only) and must
 * never touch the comment or its attribution. See
 * matrix_data::save_evidence_type().
 *
 * Same mmid-ownership check as evidence_comment.php: mmid is the
 * block_exacompcompuser_mm row id — the specific link between this
 * evidence item and this criterion — verified to genuinely belong to the
 * target student and to a topic in the submitted course, so a crafted
 * mmid can't tag someone else's evidence.
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
    echo json_encode(['success' => false, 'error' => get_string('gradenopermission', 'block_nvq_matrix')]);
    die();
}

$mmid         = required_param('mmid', PARAM_INT);
$itemid       = required_param('itemid', PARAM_INT);
$studentid    = required_param('studentid', PARAM_INT);
$courseid     = required_param('courseid', PARAM_INT);
$evidencetypes = optional_param_array('evidencetype', [], PARAM_ALPHA);

$response = ['success' => false];

if ($mmid <= 0 || $itemid <= 0 || $studentid <= 0 || $courseid <= 0) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

global $DB;

// Check 1 — the mm link row genuinely is: this exact item, linked under a
// criterion, belonging to this student, in a topic that maps to the
// submitted courseid.
$linkbelongstostudentandcourse = $DB->record_exists_sql("
    SELECT 1
      FROM {block_exacompcompuser_mm} mm
      JOIN {block_exaportitem} i ON i.id = mm.activityid
      JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
      JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
     WHERE mm.id             = :mmid
       AND mm.userid         = :userid
       AND mm.eportfolioitem = 1
       AND i.id              = :itemid
       AND ct.courseid       = :courseid
", ['mmid' => $mmid, 'userid' => $studentid, 'itemid' => $itemid, 'courseid' => $courseid]);

if (!$linkbelongstostudentandcourse) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// Check 2 — capability, scoped to the submitted course context. Broader
// than evidence_comment.php: the assessor (:grade), the IQA
// (:iqacomment), OR the student themselves on their own evidence — evidence
// type is a factual classification of the evidence, not an assessor-only
// judgement call, and the student is often best placed to know what a
// given piece of evidence actually is.
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

$cangrade      = $coursecontext && has_capability('block/nvq_matrix:grade', $coursecontext);
$caniqacomment = $coursecontext && has_capability('block/nvq_matrix:iqacomment', $coursecontext);
$isownevidence = ((int) $USER->id === $studentid);

if (!$coursecontext
    || (!$cangrade && !$caniqacomment && !$isownevidence)
    || !is_enrolled($coursecontext, $studentid)
    || (!$isownevidence && has_capability('block/nvq_matrix:viewall', $coursecontext, $studentid))
) {
    http_response_code(403);
    $response['error'] = get_string('gradenopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

try {
    matrix_data::save_evidence_type($mmid, $itemid, $studentid, $evidencetypes);
    $response['success'] = true;
    $response['message'] = get_string('evidencetypesaved', 'block_nvq_matrix');
} catch (\invalid_parameter_exception $e) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
} catch (\Throwable $e) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
