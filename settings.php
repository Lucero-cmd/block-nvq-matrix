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
 * Admin settings for the block_nvq_matrix plugin.
 *
 * Deliberately small. Two candidate settings were ruled out as
 * redundant with existing Moodle admin UI rather than built here:
 * the notification task's polling interval (already editable per-task
 * via Site administration -> Server -> Scheduled tasks - a second
 * setting here would just be two places to change the same thing) and
 * the popup/email channel split (already user-configurable via each
 * person's own Notification preferences once a message provider is
 * registered, which 'assessorsubmission' is). The two settings here
 * (renotifyonedit, migrationmode) both have no existing Moodle
 * equivalent.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Default 1 (on) - deliberately matches the plugin's original,
    // already-shipped behaviour (v26.4.22: every write to
    // block_exacompcompuser_mm for an item, including a same-competency
    // re-edit via exaport's block_exaport_do_edit(), triggers a fresh
    // notification). Existing sites upgrading to this version see no
    // behaviour change unless an admin deliberately turns this off.
    $settings->add(new admin_setting_configcheckbox(
        'block_nvq_matrix/renotifyonedit',
        get_string('renotifyonedit', 'block_nvq_matrix'),
        get_string('renotifyonedit_desc', 'block_nvq_matrix'),
        1
    ));

    // Default 0 (off) - deliberately the opposite default from
    // renotifyonedit above. This setting relaxes something this
    // plugin's audit trail otherwise guarantees permanently
    // (archivedtime is normally never backdatable, for anyone, by
    // design - see matrix_data::resolve_archivedtime()'s own docblock),
    // so it must be a deliberate, temporary, admin-visible choice, not
    // something quietly on by default on every site. Added v26.6.17 for
    // historical data migration from a previous platform: entering
    // genuinely old grades (correctly backdated via the existing
    // comment-date fields) was still stamping every resulting audit
    // trail entry with today's real date, making migrated data
    // indistinguishable from a grade actually changed today. Intended
    // to be switched back off once migration is complete.
    $settings->add(new admin_setting_configcheckbox(
        'block_nvq_matrix/migrationmode',
        get_string('migrationmode', 'block_nvq_matrix'),
        get_string('migrationmode_desc', 'block_nvq_matrix'),
        0
    ));
}
