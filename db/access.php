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
 * Plugin capabilities for the block_nvq_matrix plugin.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/nvq_matrix:addinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],
    'block/nvq_matrix:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],
    // Capability to view all students' matrices (assessors, IQA, EQA, managers).
    'block/nvq_matrix:viewall' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    // Capability to set the per-unit sampling status (Sampled / Not Yet Sampled).
    // Deliberately excludes the 'teacher' archetype (non-editing teacher) —
    // this role is used for EQA/IQA reviewers who must be able to view the
    // sampling status but never set it themselves.
    'block/nvq_matrix:sample' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    // Capability to set the unit-level Competent / Not Yet Competent grade
    // (and per-evidence-item comments). Deliberately excludes the 'teacher'
    // archetype (non-editing teacher) for the same reason as :sample above —
    // that role is used for EQA/IQA reviewers, who must see grades but never
    // set them. Assessors are editingteacher; :viewall remains separate and
    // broader (it still covers read access for teacher/editingteacher/manager).
    'block/nvq_matrix:grade' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    // Capability to set the optional unit-level IQA comment. Unlike :grade
    // and :sample, this is deliberately granted to the 'teacher' archetype —
    // on this site 'teacher' (non-editing teacher) is the role used for IQA
    // reviewers, and this is their first write capability in this plugin.
    // editingteacher/manager also hold it in case an assessor/manager needs
    // to record an IQA comment directly.
    // Note: there is no separate "assessor comment" capability — the
    // existing grade comment (part of block/nvq_matrix:grade) already
    // covers that, per client feedback.
    'block/nvq_matrix:iqacomment' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    // Capability to set the final Pass/Fail status for a student on a
    // course, and to trigger the "you've completed this course" completion
    // notification. Deliberately scoped like :grade (assessor/manager
    // only, NOT the 'teacher'/IQA archetype) - this is the assessor's
    // final sign-off, not a review action.
    'block/nvq_matrix:finalstatus' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    // Capability to export a single student's full portfolio (matrix
    // overview + evidence files + Assessment Plan/Sampling summary) as a
    // zip. Deliberately teacher-only, per client decision - unlike every
    // other capability in this file this is CONTEXT_SYSTEM, matching
    // export.php's page context (the matrix dashboard is not scoped to a
    // single course, so this action isn't either). Students never hold
    // this - there is no "export my own portfolio" path in this plugin,
    // that already exists separately via local_nvqportfolio.
    // Capability to export a single student's full portfolio (matrix
    // overview + evidence files + Assessment Plan/Sampling summary) as a
    // zip. CONTEXT_SYSTEM (not CONTEXT_COURSE like the rest of this
    // file) - this action isn't scoped to a single course, matching
    // export.php's page context. Deliberately admin/site-manager-only
    // for now: a role assigned only at course context (the normal way
    // an editingteacher/teacher gets their permissions on this site)
    // never satisfies a CONTEXT_SYSTEM check - capabilities cascade
    // downward (system -> course -> activity), never upward. So in
    // practice only a true site admin (who bypasses capability checks
    // entirely) or someone explicitly assigned Manager at system level
    // can export. Students never hold this either way - there is no
    // "export my own portfolio" path in this plugin, that already
    // exists separately via local_nvqportfolio.
    // Capability to export a single student's full portfolio (matrix
    // overview + evidence files + Assessment Plan/Sampling summary) as a
    // zip. CONTEXT_COURSE, same as every other capability in this file -
    // changed from CONTEXT_SYSTEM in v26.4.18 after client asked for
    // teachers to have access too. CONTEXT_SYSTEM structurally could
    // never satisfy a course-level role assignment (capabilities cascade
    // downward, system -> course -> activity, never upward) - that was
    // discovered the hard way in v26.4.10 when live testing showed a
    // normal editingteacher couldn't see the export button at all, and
    // the client's decision at the time was to accept that as
    // admin-only "for now". Now that "for now" is over: this needs the
    // same per-enrolled-course CONTEXT_COURSE check every other
    // capability here already uses, not a narrower archetype list under
    // the wrong context level. Students never hold this - there is no
    // "export my own portfolio" path in this plugin, that already
    // exists separately via local_nvqportfolio.
    // Capability to set the designated Assessor for a course (the
    // dropdown at the top of the matrix), used to route the
    // student-submission notification. Deliberately excludes the
    // 'teacher' archetype (IQA reviewers on this site) for the same
    // reason as :grade/:sample/:finalstatus - this is a
    // management/config action, not a review action, and the assessor
    // being designated must themselves already hold :grade in the
    // course (enforced in matrix_data::save_assessor(), not here).
    'block/nvq_matrix:manageassessor' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    'block/nvq_matrix:exportportfolio' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    // Capability to permanently delete an ARCHIVED (no longer actively
    // enrolled) student's NVQ matrix data for a specific course — grades,
    // sampling records, unit comments, final status, and their evidence
    // comments/types. This is genuinely destructive and irreversible, so
    // deliberately excludes the 'teacher' archetype (IQA/EQA reviewers on
    // this site) for the same reason as :grade/:sample/:finalstatus —
    // this is a management action, not a review action. The endpoint
    // this gates (delete_archived.php) independently re-verifies the
    // target is genuinely archived before deleting anything, regardless
    // of who holds this capability.
    'block/nvq_matrix:deletearchived' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
