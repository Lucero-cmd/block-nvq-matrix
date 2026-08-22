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
 * Per-student portfolio export. Originally teacher-only per client
 * decision, then found (live testing, v26.4.10) that CONTEXT_SYSTEM
 * structurally can never be satisfied by a course-level role assignment
 * - capabilities cascade downward, system -> course -> activity, never
 * upward - so it ended up admin-only in practice regardless of the
 * archetypes listed. Client accepted that as "for now" at the time;
 * v26.4.18 widens it back to teachers by switching to the same
 * per-enrolled-course CONTEXT_COURSE check view.php's $canviewall etc.
 * already use, rather than a single CONTEXT_SYSTEM check. Not scoped to
 * a specific course/studentid pairing beyond that - matches this
 * plugin's general permission model (a capability held on any one
 * course is enough to reach this dashboard-wide action), same as how
 * the picker itself already works.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\portfolio_export;

$studentid = required_param('studentid', PARAM_INT);

require_login();

$PAGE->set_context(context_system::instance());

$canexport = false;
foreach (enrol_get_users_courses($USER->id, true, ['id']) as $course) {
    if (has_capability('block/nvq_matrix:exportportfolio', context_course::instance($course->id))) {
        $canexport = true;
        break;
    }
}
if (!$canexport) {
    // Reuses Moodle's own exception machinery for the correct
    // message/formatting rather than hand-constructing one - also
    // still correctly lets a true site admin through even with zero
    // enrolled courses, since admins bypass capability checks entirely.
    require_capability('block/nvq_matrix:exportportfolio', context_system::instance());
}

$student = $DB->get_record('user', ['id' => $studentid], 'id', IGNORE_MISSING);
if (!$student) {
    throw new \moodle_exception('invaliduserid');
}

require_sesskey();

portfolio_export::send_zip($studentid);
