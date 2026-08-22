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
 * Message providers for the block_nvq_matrix plugin.
 *
 * Declares 'coursecomplete', sent to a student when an assessor confirms
 * they want to notify the student of their final Pass/Fail status
 * (matrix_data::send_completion_notification()). Sent via Moodle's own
 * messaging API (message_send()) rather than a raw email, so it shows up
 * in the message drawer, respects the student's own notification
 * preferences (Moodle Notification settings -> block_nvq_matrix), and can
 * still reach them by email if they have that enabled.
 *
 * On by default for both the popup (message drawer) and email channels,
 * but the student can turn either off in their own notification
 * preferences - this default is deliberately generous since a "you've
 * completed your course" message is exactly the kind of thing a student
 * would want to actually see, but it's their preference to change.
 *
 * Also declares 'assessorsubmission', sent to a course's designated
 * Assessor (block_nvq_matrix_assessor) when a student submits evidence -
 * detected by classes/task/notify_assessors_task.php, a scheduled task
 * that polls block_exacompcompuser_mm (not an event - confirmed none
 * fires for this plugin's actual evidence flow, see that task's own
 * docblock for the full trail), see
 * matrix_data::notify_assessor_of_submission(). Popup-only by default,
 * not email - unlike a Pass/Fail result this is a routine, frequent event
 * (every evidence submission), so an always-on email default would be
 * noisy; the assessor can still turn email on themselves.
 *
 * MESSAGE_DEFAULT_ENABLED (not MESSAGE_DEFAULT_LOGGEDIN +
 * MESSAGE_DEFAULT_LOGGEDOFF): those two constants were removed in Moodle's
 * MDL-73372 (the online/offline distinction for notification defaults was
 * dropped) and no longer exist as of Moodle 4.4+ - defining a provider with
 * them throws "Undefined constant" and aborts the whole upgrade. This
 * plugin only needs to declare "on by default", which is what
 * MESSAGE_DEFAULT_ENABLED alone now means.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'coursecomplete' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'assessorsubmission' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED,
        ],
    ],
];
