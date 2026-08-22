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
 * AJAX endpoint for the final Pass/Fail status box at the bottom of the
 * NVQ matrix.
 *
 * Three actions, all gated on block/nvq_matrix:finalstatus scoped to the
 * submitted course:
 *   - action=set    : records the Pass/Fail verdict (does NOT notify).
 *                     Accepts an optional 'setdate' (YYYY-MM-DD) to backdate
 *                     the "set by/on" byline, same pattern as the grade/IQA/
 *                     evidence comment date fields — for recording a result
 *                     for a student who genuinely completed before this
 *                     feature existed.
 *   - action=clear  : deletes the status row entirely, resetting the box
 *                     back to "Not yet set" (e.g. to undo a status set on
 *                     the wrong student, or set while testing). No
 *                     server-side confirmation beyond the capability check
 *                     — see templates/matrix.mustache for the client-side
 *                     confirm() prompt.
 *   - action=notify : sends the completion notification to the student via
 *                     Moodle's own messaging API. Deliberately unconstrained
 *                     — no check that grading/IQA is "finished" — and can be
 *                     called repeatedly; the confirmation prompt is
 *                     client-side (see templates/matrix.mustache), this
 *                     endpoint itself places no limit on when or how often
 *                     it's called. It does require that a status has
 *                     already been set (matrix_data throws otherwise).
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

$studentid   = required_param('studentid', PARAM_INT);
$courseid    = required_param('courseid', PARAM_INT);
$action      = required_param('action', PARAM_ALPHA); // 'set' | 'clear' | 'notify'
$status      = optional_param('status', -1, PARAM_INT);
$setdatestr  = optional_param('setdate', '', PARAM_TEXT);

$response = ['success' => false];

if ($studentid <= 0 || $courseid <= 0 || !in_array($action, ['set', 'clear', 'notify'], true)) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

if ($action === 'set' && $status !== 0 && $status !== 1) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// ----------------------------------------------------------------
// Capability + enrolment check, scoped to the submitted course.
// Deliberately its own capability (:finalstatus), not reusing :grade —
// see db/access.php for the reasoning.
// ----------------------------------------------------------------
$coursecontext = context_course::instance($courseid, IGNORE_MISSING);

if (!$coursecontext
    || !has_capability('block/nvq_matrix:finalstatus', $coursecontext)
    || !is_enrolled($coursecontext, $studentid)
    || has_capability('block/nvq_matrix:viewall', $coursecontext, $studentid)
) {
    http_response_code(403);
    $response['error'] = get_string('gradenopermission', 'block_nvq_matrix');
    echo json_encode($response);
    die();
}

// This is an AJAX-only endpoint with no page render to infer context from,
// so $PAGE->context must be set explicitly. Only this endpoint needs it -
// it's the only one of this plugin's AJAX endpoints that calls
// format_string() (via matrix_data::send_completion_notification()), which
// throws a debugging() notice without a context set. With debug message
// display on, that notice gets printed as HTML ahead of the JSON body and
// breaks the client's response parsing.
$PAGE->set_context($coursecontext);

try {
    if ($action === 'set') {
        matrix_data::save_final_status($studentid, $courseid, $status, matrix_data::parse_comment_date($setdatestr));
        $response['success'] = true;
        $response['message'] = get_string('statussaved', 'block_nvq_matrix');
    } else if ($action === 'clear') {
        matrix_data::clear_final_status($studentid, $courseid);
        $response['success'] = true;
        $response['message'] = get_string('statuscleared', 'block_nvq_matrix');
    } else {
        matrix_data::send_completion_notification($studentid, $courseid);
        $response['success'] = true;
        $response['message'] = get_string('notificationsent', 'block_nvq_matrix');
    }
} catch (\moodle_exception $e) {
    $response['error'] = ($e->errorcode === 'nostatusset')
        ? get_string('nostatusset', 'block_nvq_matrix')
        : get_string('gradeerror', 'block_nvq_matrix');
} catch (\Throwable $e) {
    $response['error'] = get_string('gradeerror', 'block_nvq_matrix');
}

echo json_encode($response);
die();
