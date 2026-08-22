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
 * Standalone page for designating each course's Assessor.
 *
 * Split out of view.php (v26.4.21) - the dropdown widget was rendered
 * inline above the matrix on every visit, which client testing found
 * visually rough (an admin-only config control competing for space
 * with the student matrix on every single page load, including for
 * users who'd never touch it that session). Moved to its own page,
 * reached via a small button in view.php's topbar, shown only when the
 * current user holds block/nvq_matrix:manageassessor on at least one
 * course - same capability gate as before, just relocated.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\matrix_data;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/nvq_matrix/assessor_manage.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('assessorheading', 'block_nvq_matrix'));
$PAGE->set_heading(get_string('assessorheading', 'block_nvq_matrix'));

$PAGE->navbar->add(
    get_string('defaulttitle', 'block_nvq_matrix'),
    new moodle_url('/blocks/nvq_matrix/view.php')
);
$PAGE->navbar->add(
    get_string('assessorheading', 'block_nvq_matrix'),
    new moodle_url('/blocks/nvq_matrix/assessor_manage.php')
);

// ----------------------------------------------------------------
// Capability check + gather every course the current user can manage
// the assessor for — same logic view.php used to run inline, just
// living here now instead.
// ----------------------------------------------------------------
// Two field lists deliberately kept separate: get_enrolled_users()
// builds its own query with the user table aliased as 'u', so its
// userfields param needs the 'u.' prefix - but $DB->get_record('user',
// ...) below builds a plain unaliased "SELECT ... FROM {user}" query,
// where a 'u.' prefix is a real SQL error ("Unknown column 'u.id'"),
// not just unnecessary. Confirmed live - this exact mismatch broke the
// page. Matches the plain field list already used everywhere else in
// this plugin for direct user lookups (e.g. matrix_data.php).
$namefields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic,
               u.lastnamephonetic, u.middlename, u.alternatename';
$plainnamefields = 'id, firstname, lastname, firstnamephonetic,
                     lastnamephonetic, middlename, alternatename';

$canmanageassessor = false;
$assessormanagecourses = [];
$enrolledcourses = enrol_get_users_courses($USER->id, true, ['id', 'fullname']);
foreach ($enrolledcourses as $c) {
    $ctx = context_course::instance($c->id);
    if (!has_capability('block/nvq_matrix:manageassessor', $ctx)) {
        continue;
    }
    $canmanageassessor = true;
    $currentassessorrow = matrix_data::get_assessor((int) $c->id);
    $currentassessor = null;
    if ($currentassessorrow) {
        $currentassessor = $DB->get_record('user', ['id' => $currentassessorrow->userid], $plainnamefields, IGNORE_MISSING) ?: null;
    }
    $assessormanagecourses[(int) $c->id] = [
        'coursename' => format_string($c->fullname),
        'current'    => $currentassessor,
        'candidates' => matrix_data::get_candidate_assessors((int) $c->id),
    ];
}

if (!$canmanageassessor) {
    // Mirrors the capability-denied pattern used elsewhere in this
    // plugin (e.g. export.php) rather than a raw print_error() with no
    // page chrome.
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('assessornopermission', 'block_nvq_matrix'), 'notifyproblem');
    echo html_writer::link(
        new moodle_url('/blocks/nvq_matrix/view.php'),
        '&#8592; ' . get_string('backtodashboard', 'block_nvq_matrix')
    );
    echo $OUTPUT->footer();
    die();
}

// ----------------------------------------------------------------
// Output.
// ----------------------------------------------------------------
echo $OUTPUT->header();

echo html_writer::link(
    new moodle_url('/blocks/nvq_matrix/view.php'),
    '&#8592; ' . get_string('backtomatrix', 'block_nvq_matrix'),
    ['class' => 'nvq-back-link']
);

echo html_writer::tag('p', get_string('assessorpageintro', 'block_nvq_matrix'), ['class' => 'nvq-assessor-intro']);

echo html_writer::start_div('nvq-assessor-widget nvq-assessor-widget-standalone');
foreach ($assessormanagecourses as $acourseid => $adata) {
    $selectid = 'nvq-assessor-select-' . $acourseid;
    echo html_writer::start_div('nvq-assessor-row', ['data-courseid' => $acourseid]);
    echo html_writer::tag('label', s($adata['coursename']), ['for' => $selectid, 'class' => 'nvq-assessor-label']);

    $options = ['0' => get_string('assessornotset', 'block_nvq_matrix')];
    foreach ($adata['candidates'] as $candidateid => $candidate) {
        $options[(string) $candidateid] = fullname($candidate);
    }
    // Stale-assessor surfacing - see view.php's original comment
    // (v26.4.21) for the full reasoning; unchanged by this move.
    if ($adata['current'] && !isset($options[(string) $adata['current']->id])) {
        $options[(string) $adata['current']->id] = fullname($adata['current'])
            . ' ' . get_string('assessorstale', 'block_nvq_matrix');
    }
    $selected = $adata['current'] ? (string) $adata['current']->id : '0';

    echo html_writer::select(
        $options,
        'nvq_assessor_select',
        $selected,
        null,
        ['id' => $selectid, 'class' => 'nvq-assessor-select custom-select']
    );
    echo html_writer::tag('span', '', ['class' => 'nvq-assessor-status', 'id' => $selectid . '-status']);
    echo html_writer::end_div();
}
echo html_writer::end_div();
?>
<script>
(function() {
    document.querySelectorAll('.nvq-assessor-select').forEach(function(select) {
        select.addEventListener('change', function() {
            var row = select.closest('.nvq-assessor-row');
            var courseid = row.getAttribute('data-courseid');
            var status = document.getElementById(select.id + '-status');
            status.textContent = '<?php echo addslashes(get_string('assessorsaving', 'block_nvq_matrix')); ?>';
            fetch('<?php echo (new moodle_url('/blocks/nvq_matrix/assessor.php'))->out(false); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'courseid=' + encodeURIComponent(courseid)
                    + '&assessorid=' + encodeURIComponent(select.value)
                    + '&sesskey=<?php echo sesskey(); ?>'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                status.textContent = data.success
                    ? (data.message || '')
                    : (data.error || '<?php echo addslashes(get_string('assessorerror', 'block_nvq_matrix')); ?>');
            })
            .catch(function() {
                status.textContent = '<?php echo addslashes(get_string('assessorerror', 'block_nvq_matrix')); ?>';
            });
        });
    });
})();
</script>
<?php

echo $OUTPUT->footer();
