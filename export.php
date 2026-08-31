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
 * already use, rather than a single CONTEXT_SYSTEM check.
 *
 * RESTRICTED TO A SINGLE COURSE (v26.6.5, client decision - see
 * classes/portfolio_export.php's build_matrix_tree() docblock): the
 * export itself now only ever covers the one course passed in, not
 * every course the student has ever had data on. The permission check
 * below was tightened to match - previously "the capability held on
 * ANY one enrolled course is enough" (matching this plugin's general
 * dashboard-wide picker model at the time), which would now let someone
 * with :exportportfolio on an unrelated course they teach export THIS
 * student's data from a course they hold no role on at all, once the
 * export itself became course-specific. Now requires the capability on
 * the SPECIFIC course being exported, not just any course.
 *
 * @package   block_nvq_matrix
 * @copyright 2026 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\portfolio_export;

$studentid = required_param('studentid', PARAM_INT);
$courseid  = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$coursecontext = context_course::instance($courseid);
$PAGE->set_context($coursecontext);

require_capability('block/nvq_matrix:exportportfolio', $coursecontext);

$student = $DB->get_record('user', ['id' => $studentid], 'id', IGNORE_MISSING);
if (!$student) {
    throw new \moodle_exception('invaliduserid');
}

require_sesskey();

portfolio_export::send_zip($studentid, $courseid);
