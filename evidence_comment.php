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
 * AJAX endpoint for saving a comment on a single evidence item *occurrence*
 * in the NVQ matrix.
 *
 * Accepts a POST with mmid, itemid, studentid, courseid, comment. mmid is
 * the block_exacompcompuser_mm row id — the specific link between this
 * evidence item and this criterion. The same evidence file can be linked
 * to more than one criterion (a separate mm row per link), so comments are
 * keyed on mmid rather than itemid: keying on itemid alone would make one
 * comment appear identically under every criterion that file happens to
 * also be attached to.
 *
 * Gated on block/nvq_matrix:grade (same restriction as grading) scoped to
 * the submitted course, plus a check that the mm link row genuinely
 * belongs to the target student and to a topic in the submitted course, so
 * a crafted mmid can't attach a comment to someone else's evidence.
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

$mmid      = required_param('mmid', PARAM_INT);
$itemid    = required_param('itemid', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);
$courseid  = required_param('courseid', PARAM_INT);
$comment   = optional_param('comment', '', PARAM_TEXT);
$commentdatestr = optional_param('commentdate', '', PARAM_TEXT);

$response = ['success' => false];

if ($mmid <= 0 || $itemid <= 0 || $studentid <= 0 || $courseid <= 0) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

global $DB;

// Check 1 — the mm link row genuinely is: this exact item, linked under a
// criterion, belonging to this student, in a topic that maps to the
// submitted courseid. Checking mm.id directly (rather than itemid+userid,
// as before) ties the comment to the one specific criterion occurrence the
// person was looking at, not to every occurrence of that file.
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

// Check 2 — capability, scoped to the submitted course context.
// Uses block/nvq_matrix:grade specifically, same restriction as grading —
// the 'teacher' archetype (non-editing teacher / EQA) can read comments
// but never write them.
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

try {
    // The immediate byline echoed back to the browser must reflect the
    // same timestamp that was actually written to the database - not
    // always "now" - otherwise a backdated comment date correctly saves
    // to the DB but the on-screen byline still shows today until the
    // page is reloaded (client-reported bug).
    $commentdate = matrix_data::parse_comment_date($commentdatestr);
    $savedtime = $commentdate > 0 ? $commentdate : time();
    matrix_data::save_evidence_comment($mmid, $itemid, $studentid, $comment, $commentdate);
    $response['success'] = true;
    $response['message'] = get_string('evidencecommentsaved', 'block_nvq_matrix');
    $response['comment'] = $comment;
    $response['commentbyline'] = trim($comment) === ''
        ? ''
        : matrix_data::format_comment_byline((int) $USER->id, $savedtime, [(int) $USER->id => fullname($USER)]);
} catch (\Throwable $e) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
