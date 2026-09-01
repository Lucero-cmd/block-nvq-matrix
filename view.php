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
 * Full-page view for the NVQ Competence Matrix.
 *
 * Renders the same matrix as the dashboard block but in a full-width
 * Moodle page layout. Accessible from the block header link.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_nvq_matrix\matrix_data;

/**
 * Inline magnifying-glass icon for the "toggle search" button in the
 * student picker bar. Kept as a tiny helper (rather than inline in the
 * middle of the picker-building code below) purely so that block of PHP
 * reads as picker logic, not SVG markup.
 *
 * @return string raw SVG markup (currentColor fill - inherits button colour).
 */
function block_nvq_matrix_search_icon_svg(): string {
    return '<svg viewBox="0 0 16 16" width="14" height="14" focusable="false" aria-hidden="true">'
        . '<path fill="currentColor" d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 '
        . '3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>'
        . '</svg>';
}

/**
 * Inline funnel icon for the "toggle filter" button in the student
 * picker bar. See block_nvq_matrix_search_icon_svg() for why this is a
 * helper rather than inline markup.
 *
 * @return string raw SVG markup (currentColor fill - inherits button colour).
 */
function block_nvq_matrix_funnel_icon_svg(): string {
    return '<svg viewBox="0 0 16 16" width="14" height="14" focusable="false" aria-hidden="true">'
        . '<path fill="currentColor" d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 '
        . '8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>'
        . '</svg>';
}

require_login();

// ----------------------------------------------------------------
// Page context — system context is correct for a dashboard-wide view.
// ----------------------------------------------------------------
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/nvq_matrix/view.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('defaulttitle', 'block_nvq_matrix'));
$PAGE->set_heading(get_string('defaulttitle', 'block_nvq_matrix'));

// Breadcrumb: Home → NVQ Competence Matrix.
$PAGE->navbar->add(
    get_string('defaulttitle', 'block_nvq_matrix'),
    new moodle_url('/blocks/nvq_matrix/view.php')
);

// ----------------------------------------------------------------
// Capability check.
// ----------------------------------------------------------------
$namefields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic,
               u.lastnamephonetic, u.middlename, u.alternatename';

$canviewall         = false;
$cangrade           = false;
$cansample          = false;
$caniqacomment      = false;
$canfinalstatus     = false;
$canexportportfolio = false;
// Just the flag for showing/hiding the "Assessor" button in the
// topbar - the actual per-course assessor data (current designee,
// candidate list) moved to assessor_manage.php along with the widget
// itself, so it's no longer gathered here on every matrix page load.
$canmanageassessor  = false;
$enrolledcourses = enrol_get_users_courses($USER->id, true, ['id', 'fullname']);
foreach ($enrolledcourses as $c) {
    $ctx = context_course::instance($c->id);
    if (has_capability('block/nvq_matrix:viewall', $ctx)) {
        $canviewall = true;
    }
    if (has_capability('block/nvq_matrix:manageassessor', $ctx)) {
        $canmanageassessor = true;
    }
    if (has_capability('block/nvq_matrix:grade', $ctx)) {
        $cangrade = true;
    }
    if (has_capability('block/nvq_matrix:sample', $ctx)) {
        $cansample = true;
    }
    if (has_capability('block/nvq_matrix:iqacomment', $ctx)) {
        $caniqacomment = true;
    }
    if (has_capability('block/nvq_matrix:finalstatus', $ctx)) {
        $canfinalstatus = true;
    }
    // block/nvq_matrix:exportportfolio was CONTEXT_SYSTEM through
    // v26.4.17 (a role assigned only at course context could never
    // satisfy it, so it ended up admin-only regardless of the
    // archetypes listed - confirmed by live testing, v26.4.10). Widened
    // to CONTEXT_COURSE in v26.4.18 per client request to include
    // teachers - now folds into this same loop like everything else
    // on this page, rather than a separate single system-wide check.
    if (has_capability('block/nvq_matrix:exportportfolio', $ctx)) {
        $canexportportfolio = true;
    }
}
// A site-wide manager who holds this capability at CONTEXT_SYSTEM
// directly (common for a site-level Manager role, not assigned via
// course enrolment at all) would never be caught by the loop above if
// they have zero course enrolments of their own - enrol_get_users_
// courses() only returns courses they're actually enrolled in.
// export.php has an equivalent fallback for exactly this case (see its
// own comment) - without the same check here, that fallback would be
// unreachable in practice: the export button would never render for
// them at all, even though export.php itself would let them through.
if (!$canexportportfolio && has_capability('block/nvq_matrix:exportportfolio', context_system::instance())) {
    $canexportportfolio = true;
}

// ----------------------------------------------------------------
// Determine which student to display.
// ----------------------------------------------------------------
$selectedstudentid = optional_param('nvq_matrix_student', 0, PARAM_INT);
// Which specific course's row was clicked - see the resolution logic
// further down for why this is required alongside the studentid, not
// just an optional hint.
$selectedcourseid = optional_param('nvq_matrix_course', 0, PARAM_INT);

if ($canviewall) {
    // Build the student list across all courses where the current user has viewall.
    // Also collect the course ids each student is enrolled on, for the
    // per-student+course picker rows built further down.
    $courses        = $enrolledcourses;
    $students       = [];
    $studentcourseids = []; // userid => [courseid, ...] - GROUP-FILTERED
                             // for a company manager viewer (drives the
                             // per-course rows below - what they're
                             // actually allowed to see).
    $trueenrolledcourseids = []; // userid => [courseid, ...] - UNFILTERED
                                  // by any group restriction, unlike
                                  // $studentcourseids above. Used below
                                  // for the archived-detection "is this
                                  // student currently enrolled at all"
                                  // check - using the group-filtered
                                  // version there was a real bug: a
                                  // student enrolled but simply outside a
                                  // company manager's group was wrongly
                                  // treated as "not currently enrolled"
                                  // and shown to that manager as archived
                                  // (unenrolled), when they're actually
                                  // just enrolled under a different group
                                  // this manager doesn't oversee.
    $viewallcourseids = []; // courseid => true, for scoping the status lookup query.
    $coursenamesbyid = []; // courseid => fullname, for building one row per student+course pair.
    $contextbycourseid = []; // courseid => context - reused below for the archived-detection viewall check.
    $viewergroupidsbycourse = []; // courseid => [groupid, ...] - the
                                   // CURRENT viewer's own groups on that
                                   // course, only set when the viewer is
                                   // group-restricted there (company
                                   // manager). Hoisted out of this loop so
                                   // archived-detection further down can
                                   // ALSO group-scope who's allowed to
                                   // see an archived row - previously
                                   // completely unscoped, a second, more
                                   // serious leak: even a genuinely
                                   // unenrolled student from an unrelated
                                   // company could surface in any company
                                   // manager's archived list.
    foreach ($courses as $course) {
        $ctx = context_course::instance($course->id);
        if (!has_capability('block/nvq_matrix:viewall', $ctx)) {
            continue;
        }
        $viewallcourseids[$course->id] = true;
        $coursenamesbyid[(int) $course->id] = format_string($course->fullname);
        $contextbycourseid[(int) $course->id] = $ctx;
        $enrolled = get_enrolled_users($ctx, '', 0, $namefields, 'u.lastname ASC, u.firstname ASC');

        // Captured from the RAW enrolled list, before any group
        // filtering below - this is "true enrollment", independent of
        // which of them THIS viewer happens to be allowed to see.
        foreach ($enrolled as $u) {
            $trueenrolledcourseids[$u->id][] = (int) $course->id;
        }

        // Company Manager role (hardcoded by shortname, this is a fully
        // custom site plugin) is restricted to only the students who
        // share a group with them on this course - core Moodle's forced
        // separate-groups mechanism handles the Participants page, but
        // this plugin builds its own student list independently via
        // get_enrolled_users() above, so it needs its own group check.
        // Teachers/Assessor/IQA/EQA are untouched - they still see every
        // enrolled student regardless of groups. A company manager with
        // no group on this course sees nobody here (deliberately strict,
        // not a fallback to full visibility).
        $viewerroles = get_user_roles($ctx, $USER->id, true);
        $viewerroleshortnames = array_map(function ($r) {
            return $r->shortname;
        }, $viewerroles);
        if (in_array('companymanager', $viewerroleshortnames, true)) {
            $usergroups = groups_get_user_groups($course->id, $USER->id);
            $groupids = $usergroups[0] ?? [];
            $viewergroupidsbycourse[(int) $course->id] = $groupids;
            if (empty($groupids)) {
                $enrolled = [];
            } else {
                list($ginsql, $ginparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');
                $memberids = $DB->get_fieldset_select('groups_members', 'userid', "groupid $ginsql", $ginparams);
                $memberids = array_flip($memberids);
                $enrolled = array_intersect_key($enrolled, $memberids);
            }
        }

        foreach ($enrolled as $u) {
            if (!has_capability('block/nvq_matrix:viewall', $ctx, $u->id)) {
                if (!isset($students[$u->id])) {
                    $students[$u->id] = $u;
                }
                // Accumulate all course ids this student belongs to.
                $studentcourseids[$u->id][] = (int) $course->id;
            }
        }
    }

    // For every course where the CURRENT viewer is group-restricted,
    // pre-fetch the full current membership of their OWN group(s) once
    // per course (not once per archived student below - avoids an N+1
    // query pattern over what could be a long archived list).
    $viewergroupmembersbycourse = []; // courseid => [userid => true]
    foreach ($viewergroupidsbycourse as $vcid => $vgroupids) {
        if (empty($vgroupids)) {
            $viewergroupmembersbycourse[$vcid] = [];
            continue;
        }
        list($vginsql, $vgparams) = $DB->get_in_or_equal($vgroupids, SQL_PARAMS_NAMED, 'vgrp');
        $vmemberids = $DB->get_fieldset_select('groups_members', 'userid', "groupid $vginsql", $vgparams);
        $viewergroupmembersbycourse[$vcid] = array_flip($vmemberids);
    }

    // ------------------------------------------------------------
    // Archived students - anyone with plugin data for a viewall course
    // who ISN'T in that course's current enrolled list. Confirmed (chat,
    // checked directly against the real installed block_exacomp and
    // block_exaport plugins) that unenrolling a student never deletes or
    // touches this plugin's own data, or exacomp's own competence links -
    // it only breaks *discovery*: get_enrolled_users() (above) and
    // exacomp's own is_enrolled()-gated course listing both stop
    // surfacing the student, even though every row they ever had is
    // still sitting in the database untouched. This is what actually
    // fixes that - not a data restore, since nothing was ever lost.
    //
    // Scoped to grades/sampling/unit_comments/status only, not
    // evidence_comments - that table has no courseid column of its own
    // (it's linked via mmid into exacomp's tables instead), and the
    // other four are already a reliable enough "this student had a
    // presence in this course" signal without needing that join.
    //
    // Deliberately computed here, before $studentid is resolved below,
    // and independent of whether $students ended up empty - an archived
    // student's id is never in $students, so the isset() check on the
    // next line has to also check $archivedusers, or clicking an
    // archived row would silently fail to select them and fall back to
    // the idle "no student selected" state instead. And if EVERY
    // student in a teacher's courses has been unenrolled (exactly the
    // scenario this feature exists for), $students would be entirely
    // empty - this can't be gated behind "!empty($students)" the way
    // the row-building below is, or that edge case would show nothing
    // at all instead of the archived list.
    // ------------------------------------------------------------
    $archivedrows = [];
    $archivedusers = [];
    $archivedpairs = []; // studentid => [courseid, ...] - not currently
                          // enrolled there. Hoisted above the conditional
                          // below (same reasoning as statusmap/timemap) -
                          // the studentid+courseid resolution further down
                          // reads this unconditionally too.
    $statusmap = []; // studentid => [courseid => status] - always defined,
    $timemap   = []; // studentid => [courseid => timemodified] - even if
                      // $viewallcourseids or $archivedstudentids end up
                      // empty, since the enrolled-rows loop further down
                      // unconditionally reads from both.
    if (!empty($viewallcourseids)) {
        // Moodle's DB parameter binding does NOT support reusing the same
        // named placeholder across multiple textual occurrences in one
        // query the way raw PDO does - it expects one distinct bound
        // value per occurrence in the SQL text, not per unique name.
        // Reusing a single get_in_or_equal() result across all four
        // UNION branches below (confirmed live: "Expected 52, got 13" -
        // 13 courseids x 4 occurrences) needs four separately-generated
        // IN-clauses, each with its own uniquely-prefixed placeholders
        // and its own params array, even though they all filter on the
        // exact same course id list.
        $courseidlist = array_keys($viewallcourseids);
        list($vcidinsql1, $vcidparams1) = $DB->get_in_or_equal($courseidlist, SQL_PARAMS_NAMED, 'vcida');
        list($vcidinsql2, $vcidparams2) = $DB->get_in_or_equal($courseidlist, SQL_PARAMS_NAMED, 'vcidb');
        list($vcidinsql3, $vcidparams3) = $DB->get_in_or_equal($courseidlist, SQL_PARAMS_NAMED, 'vcidc');
        list($vcidinsql4, $vcidparams4) = $DB->get_in_or_equal($courseidlist, SQL_PARAMS_NAMED, 'vcidd');
        $presencerows = $DB->get_records_sql("
            SELECT DISTINCT studentid, courseid FROM (
                SELECT studentid, courseid FROM {block_nvq_matrix_grades} WHERE courseid $vcidinsql1
                UNION
                SELECT studentid, courseid FROM {block_nvq_matrix_sampling} WHERE courseid $vcidinsql2
                UNION
                SELECT studentid, courseid FROM {block_nvq_matrix_unit_comments} WHERE courseid $vcidinsql3
                UNION
                SELECT studentid, courseid FROM {block_nvq_matrix_status} WHERE courseid $vcidinsql4
            ) presence
        ", $vcidparams1 + $vcidparams2 + $vcidparams3 + $vcidparams4);

        $archivedstudentids = [];
        foreach ($presencerows as $row) {
            $sid = (int) $row->studentid;
            $cid = (int) $row->courseid;
            // Uses the UNFILTERED true-enrollment list, not the
            // group-filtered $studentcourseids - see the declaration
            // comment above for why using the filtered version here was
            // a real misclassification bug (an enrolled-but-different-
            // group student wrongly showing as archived to a company
            // manager who isn't over their group).
            $currentlyenrolled = in_array($cid, $trueenrolledcourseids[$sid] ?? [], true);
            if ($currentlyenrolled) {
                continue;
            }
            // Same exclusion the enrolled-students loop above already
            // applies: someone who now holds :viewall on this course
            // (e.g. a student who was later promoted to teacher, still
            // carrying old grade/sampling rows from before) isn't
            // "archived" just because they're no longer counted as a
            // student - without this check they'd incorrectly show up
            // as unenrolled even though get_enrolled_users() still
            // finds them fine, just filtered out of $studentcourseids
            // for the same reason a co-teacher never appears there.
            if (isset($contextbycourseid[$cid]) && has_capability('block/nvq_matrix:viewall', $contextbycourseid[$cid], $sid)) {
                continue;
            }
            // Company-manager group scoping for ARCHIVED rows - the
            // actual leak this fix exists for. Only reached when the
            // current viewer is group-restricted on this specific course
            // (isset() is false for a full teacher/admin, who sees every
            // archived student exactly as before). If this now-
            // unenrolled student isn't in $viewergroupmembersbycourse
            // (either they never were in the viewer's group, or their
            // groups_members row was itself cleaned up on unenrollment),
            // exclude them - fail closed, matching this plugin's already
            // -established "no group = sees nobody" rule for company
            // managers (v26.4.24), rather than fail open and leak an
            // unrelated company's unenrolled student into this list.
            if (isset($viewergroupmembersbycourse[$cid]) && !isset($viewergroupmembersbycourse[$cid][$sid])) {
                continue;
            }
            $archivedpairs[$sid][] = $cid;
            $archivedstudentids[$sid] = true;
        }

        // ------------------------------------------------------------
        // Final Pass/Fail status per student+course pair, driven
        // entirely by block_nvq_matrix_status - the only reliable
        // "done" signal this plugin has. Runs here unconditionally
        // (not nested inside "if there are archived students") since
        // every enrolled student's row below depends on it too - only
        // WHICH student ids it covers changes based on whether there
        // are any archived ones to add. An archived student's status
        // row is untouched by unenrollment (confirmed, v26.4.13)
        // exactly like their grade/sampling/comment rows are, so
        // leaving them out here would silently show "no status" for an
        // archived student who actually has one.
        // ------------------------------------------------------------
        $allstatusstudentids = array_unique(array_merge(array_keys($students), array_keys($archivedstudentids)));
        if (!empty($allstatusstudentids)) {
            list($sidinsql, $sidparams) = $DB->get_in_or_equal($allstatusstudentids, SQL_PARAMS_NAMED, 'sid');
            list($cidinsql, $cidparams) = $DB->get_in_or_equal($courseidlist, SQL_PARAMS_NAMED, 'cid');
            $statusrows = $DB->get_records_select(
                'block_nvq_matrix_status',
                "studentid $sidinsql AND courseid $cidinsql",
                $sidparams + $cidparams,
                '',
                'id, studentid, courseid, status, timemodified'
            );
            foreach ($statusrows as $row) {
                $statusmap[(int) $row->studentid][(int) $row->courseid] = $row->status === null ? null : (int) $row->status;
                $timemap[(int) $row->studentid][(int) $row->courseid]   = $row->timemodified === null ? null : (int) $row->timemodified;
            }
        }

        if (!empty($archivedstudentids)) {
            // Names for archived students not already loaded above (a
            // student can be enrolled in one viewall course and archived
            // in another, so may already be in $students).
            $needlookup = array_diff(array_keys($archivedstudentids), array_keys($students));
            $archivedusers = $students; // start from what's already loaded.
            if (!empty($needlookup)) {
                $extra = $DB->get_records_list('user', 'id', $needlookup, '', 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, deleted');
                foreach ($extra as $u) {
                    $archivedusers[$u->id] = $u;
                }
            }

            foreach ($archivedpairs as $sid => $courseids) {
                $user = $archivedusers[$sid] ?? null;
                // A studentid can outlive its own user account (full
                // deletion, separate from unenrollment) - skip rather
                // than show a broken/blank row for it. Full account
                // deletion is a different, harder problem than this
                // view is meant to solve (see chat: disaster recovery,
                // deferred).
                if (!$user || !empty($user->deleted)) {
                    continue;
                }
                $courseids = array_unique($courseids);
                usort($courseids, function ($a, $b) use ($coursenamesbyid) {
                    return strcasecmp($coursenamesbyid[$a] ?? '', $coursenamesbyid[$b] ?? '');
                });
                foreach ($courseids as $cid) {
                    // Same status value an enrolled row would show, just
                    // kept separate from the completed/needsgrading
                    // bucket - "archived" describes enrolment state, not
                    // grading state, so a Fail-and-archived student
                    // shouldn't look identical to a Pass-and-archived one.
                    $status = $statusmap[$sid][$cid] ?? null;
                    $completeddate = '';
                    if ($status === 1) {
                        $passtime = $timemap[$sid][$cid] ?? null;
                        $completeddate = $passtime !== null ? userdate($passtime, '%Y-%m-%d') : '';
                    }
                    $archivedrows[] = [
                        'userid'        => $sid,
                        'name'          => fullname($user),
                        'coursename'    => $coursenamesbyid[$cid] ?? '',
                        'courseid'      => $cid,
                        'bucket'        => 'archived',
                        'completeddate' => $completeddate,
                        'finalstatus'   => $status,
                    ];
                }
            }
        }
    }

    // Both studentid AND courseid must resolve together now - a bare
    // ?nvq_matrix_student=id with no (or a mismatched) course param is
    // treated as unresolved (idle state) rather than falling back to
    // "show everything", which would silently reopen the old combined-
    // view leak via direct URL editing. $selectedcourseid must be one
    // this SPECIFIC student is actually enrolled in (or archived on)
    // within the viewer's own permitted course set - $studentcourseids/
    // $archivedpairs are both already scoped to that upstream.
    $resolvedcourseid = 0;
    if ($selectedstudentid && $selectedcourseid
        && (isset($students[$selectedstudentid]) || isset($archivedusers[$selectedstudentid]))
        && (in_array($selectedcourseid, $studentcourseids[$selectedstudentid] ?? [], true)
            || in_array($selectedcourseid, $archivedpairs[$selectedstudentid] ?? [], true))) {
        $studentid = $selectedstudentid;
        $resolvedcourseid = $selectedcourseid;
    } else {
        $studentid = 0;
    }

} else {
    // Students see only their own matrix, but scoped to ONE course at a
    // time - previously $resolvedcourseid was hardcoded to 0 here, which
    // makes matrix_data::build() take its unscoped "all courses this
    // student has eportfolio data on" query path (see build()'s topicid
    // resolution: oncoursepage is (bool) $resolvedcourseid, so 0 always
    // meant unscoped). A student enrolled in more than one NVQ-mapped
    // course got every course's units silently blended into one matrix,
    // AND Overall/Assessor progress computed across both combined -
    // reported as "mixing up units" between courses. Fix: resolve to a
    // single real course, same as the assessor/IQA path above already
    // does per student+course row.
    $studentid = $USER->id;
    $students  = [];

    // Two separate sources, combined - this is the actual fix for
    // "other courses aren't showing": the previous version only found a
    // course via existing EVIDENCE (eportfolioitem=1 rows), so a course
    // the student is freshly enrolled in with zero evidence submitted
    // yet never appeared at all - it looked identical to not being
    // enrolled there.
    //
    // Source 1 - ACTIVE: every course this student is currently actively
    // enrolled in (enrol_get_users_courses(..., true) - Moodle's own
    // enrolment-status/date-aware API, not a hand-rolled query) that
    // also has an NVQ competence structure mapped at all
    // (block_exacompcoutopi_mm). Deliberately NOT gated on having any
    // evidence yet - a brand new student with a blank matrix still needs
    // to see and be able to start it.
    $activeenrolledcourses = enrol_get_users_courses($studentid, true);
    $activecourseids = array_keys($activeenrolledcourses);
    $mappedactivecourseids = [];
    if (!empty($activecourseids)) {
        list($acidinsql, $acidparams) = $DB->get_in_or_equal($activecourseids, SQL_PARAMS_NAMED, 'acid');
        $mappedactivecourseids = $DB->get_fieldset_select(
            'block_exacompcoutopi_mm',
            'DISTINCT courseid',
            "courseid $acidinsql",
            $acidparams
        );
    }
    $mappedactivecourseids = array_map('intval', $mappedactivecourseids);

    // Source 2 - the student's OWN archived courses: courses with actual
    // eportfolio evidence linked for this student, even if they're no
    // longer actively enrolled there. Without this, unenrolling a
    // student from a course would silently hide their own historical
    // portfolio from themselves the moment Source 1 above went live -
    // mirrors the teacher-side archived-students feature (v26.4.13),
    // just from the student's own point of view instead of a viewall
    // assessor's.
    $evidencecourseids = $DB->get_fieldset_sql("
        SELECT DISTINCT ct.courseid
          FROM {block_exacompcompuser_mm} mm
          JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
          JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
         WHERE mm.userid         = :userid
           AND mm.eportfolioitem = 1
    ", ['userid' => $studentid]);
    $evidencecourseids = array_map('intval', $evidencecourseids);

    // A teacher/manager permanently deleting this student's matrix data
    // for an archived course (delete_archived.php,
    // block/nvq_matrix:deletearchived) deliberately never touches the
    // underlying exacomp/exaport evidence queried above - this plugin
    // never modifies third-party data. Without this exclusion, a
    // "deleted" archived course would still show up here forever, since
    // the evidence that makes it detectable was never actually removed.
    $clearedcourseids = $DB->get_fieldset_select(
        'block_nvq_matrix_cleared_archive',
        'courseid',
        'studentid = :studentid',
        ['studentid' => $studentid]
    );
    $evidencecourseids = array_diff($evidencecourseids, array_map('intval', $clearedcourseids));

    // BUGFIX: the query above finds a courseid purely via shared TOPIC
    // linkage (block_exacompcoutopi_mm), not via any real connection to
    // this specific student and course. Two courses can legitimately
    // share the same topic/unit structure (e.g. two NVQ route variants
    // built on identical learning criteria, different durations) - in
    // that case a student with real evidence under course A gets course
    // B pulled in too, purely because the topic happens to be linked to
    // both, even though the student was never on course B at all.
    // Confirmed live: courseid 2 and 16 share topicid 10, causing course
    // 16 to wrongly show as "(Archived)" for students only ever on
    // course 2.
    //
    // Fix: only trust an evidence-derived courseid if the student also
    // has a genuine row in one of this plugin's own courseid-scoped
    // tables for that exact course - the same "historical presence"
    // signal already trusted elsewhere (matrix_data.php's final-status
    // fix, v26.5.6, and get_portfolio_links()). A student never truly
    // present on that course (no grade/sampling/comment/status ever
    // recorded there) is excluded, even if the topic is shared.
    $realcourseids = [];
    foreach ($evidencecourseids as $ecid) {
        $haspresence = $DB->record_exists('block_nvq_matrix_grades', [
            'studentid' => $studentid, 'courseid' => $ecid,
        ]) || $DB->record_exists('block_nvq_matrix_sampling', [
            'studentid' => $studentid, 'courseid' => $ecid,
        ]) || $DB->record_exists('block_nvq_matrix_unit_comments', [
            'studentid' => $studentid, 'courseid' => $ecid,
        ]) || $DB->record_exists('block_nvq_matrix_status', [
            'studentid' => $studentid, 'courseid' => $ecid,
        ]);
        if ($haspresence) {
            $realcourseids[] = $ecid;
        }
    }
    $evidencecourseids = $realcourseids;

    // Archived = has evidence, but not in the active list above.
    $archivedcourseidsforstudent = array_diff($evidencecourseids, $mappedactivecourseids);
    $studentcourseids = array_unique(array_merge($mappedactivecourseids, $evidencecourseids));

    $coursenamesbyid = [];
    if (!empty($studentcourseids)) {
        $courserecords = $DB->get_records_list('course', 'id', $studentcourseids, '', 'id, fullname');
        foreach ($courserecords as $c) {
            $coursenamesbyid[(int) $c->id] = format_string($c->fullname);
        }
        // Alphabetical, so both the default course picked below and the
        // switcher's link order are stable/predictable rather than
        // whatever order the DB happened to return them in.
        asort($coursenamesbyid, SORT_STRING | SORT_FLAG_CASE);
        $studentcourseids = array_keys($coursenamesbyid);
    }

    if (empty($studentcourseids)) {
        // No NVQ-mapped course at all - unchanged from before, falls
        // through to build()'s unscoped query, which will correctly
        // resolve to "no data" since there is none.
        $resolvedcourseid = 0;
    } elseif ($selectedcourseid && in_array($selectedcourseid, $studentcourseids, true)) {
        $resolvedcourseid = $selectedcourseid;
    } else {
        // No course chosen (or an invalid/stale one) - default to the
        // first ACTIVE course alphabetically (falling back to the first
        // archived one only if every course is archived), rather than
        // leaving this at 0, since 0 is exactly what re-triggers the
        // unscoped "blend everything" query this fix exists to avoid. A
        // one-course student always lands here too, so their experience
        // is unchanged.
        $defaultpool = array_values(array_intersect($studentcourseids, $mappedactivecourseids));
        $resolvedcourseid = $defaultpool[0] ?? $studentcourseids[0];
    }

    // Course switcher - only rendered when there's actually more than
    // one course to switch between, so a typical single-course student
    // sees no change in the page at all. Each entry is flagged
    // 'isarchived' when it's only reachable via Source 2 above (no
    // longer actively enrolled) - answers "what happens to the student's
    // own archived course" directly: it still appears here, clearly
    // labelled, rather than either disappearing or looking identical to
    // an active one.
    $templatedata_courseswitcher = [];
    if (count($studentcourseids) > 1) {
        foreach ($studentcourseids as $cid) {
            $switchurl = new moodle_url('/blocks/nvq_matrix/view.php', ['nvq_matrix_course' => $cid]);
            $templatedata_courseswitcher[] = [
                'courseid'   => $cid,
                'coursename' => $coursenamesbyid[$cid] ?? '',
                'courseurl'  => $switchurl->out(false),
                'iscurrent'  => ($cid === $resolvedcourseid),
                'isarchived' => in_array($cid, $archivedcourseidsforstudent, true),
            ];
        }
    }
}

// ----------------------------------------------------------------
// Build student selector HTML.
// ----------------------------------------------------------------
$pageurl      = new moodle_url('/blocks/nvq_matrix/view.php');
$selectorhtml = '';

if ($canviewall && (!empty($students) || !empty($archivedrows))) {
    // One row per student+course pair - a student on two courses gets two
    // rows, each with its own independent status/completion date, so an
    // assessor can see (and filter) exactly which course still needs
    // grading rather than a single blended status hiding which one it is.
    // Each row now links to that SPECIFIC course's matrix only (see the
    // studentid+courseid resolution below and matrix_data::build()'s
    // $oncoursepage/$courseid scoping) - previously every row for the
    // same student opened one combined page showing all of their courses
    // together, which both defeated the point of splitting rows here and
    // could show a viewer course data they had no permission on, if that
    // student happened to also be on another of the viewer's courses.
    $rows = [];
    foreach ($students as $student) {
        $courseids = array_unique($studentcourseids[$student->id] ?? []);
        if (empty($courseids)) {
            continue;
        }
        usort($courseids, function ($a, $b) use ($coursenamesbyid) {
            return strcasecmp($coursenamesbyid[$a] ?? '', $coursenamesbyid[$b] ?? '');
        });
        foreach ($courseids as $cid) {
            $status = $statusmap[$student->id][$cid] ?? null;
            $bucket = ($status === 1) ? 'completed' : 'needsgrading';
            $completeddate = '';
            if ($bucket === 'completed') {
                $passtime = $timemap[$student->id][$cid] ?? null;
                $completeddate = $passtime !== null ? userdate($passtime, '%Y-%m-%d') : '';
            }
            $rows[] = [
                'userid'        => $student->id,
                'name'          => fullname($student),
                'coursename'    => $coursenamesbyid[$cid] ?? '',
                'courseid'      => $cid,
                'bucket'        => $bucket,
                'completeddate' => $completeddate,
            ];
        }
    }

    // $archivedrows was already fully computed above (before $studentid
    // resolution) - just append it here, no re-querying.
    $rows = array_merge($rows, $archivedrows);

    $html = html_writer::start_tag('div', ['class' => 'nvq-student-selector-form', 'id' => 'nvq-student-picker']);

    // Compact dropdown-style bar - collapsed to just the selected
    // student's name (or a prompt, if none picked yet) plus a search
    // icon and a funnel/filter icon, rather than always showing the
    // full list, search box, and filter chips at once. Clicking the
    // name/caret toggles the student list open; the two icon buttons
    // reveal their own controls (search input / filter chips) and also
    // open the list, since there'd be nothing to see results in otherwise.
    $selectedname = ($studentid && isset($students[$studentid])) ? fullname($students[$studentid]) : '';

    // Always starts collapsed - even with no student picked yet, the
    // picker should read as a closed dropdown the assessor opens
    // deliberately, not a list that's already sprawled open on page load.
    $liststartsopen = false;

    $html .= html_writer::start_div('nvq-selector-bar');
    $html .= html_writer::start_tag('button', [
        'type'          => 'button',
        'class'         => 'nvq-selector-trigger',
        'id'            => 'nvq-selector-trigger',
        'aria-expanded' => $liststartsopen ? 'true' : 'false',
        'aria-controls' => 'nvq-selector-panel',
    ]);
    $html .= html_writer::span(
        s($selectedname !== '' ? $selectedname : get_string('selectstudentprompt', 'block_nvq_matrix')),
        'nvq-selector-trigger-label'
    );
    $html .= html_writer::span(
        '<svg viewBox="0 0 16 16" width="11" height="11" focusable="false" aria-hidden="true">'
        . '<path fill="currentColor" d="M3.204 5h9.592L8 10.481 3.204 5z"/></svg>',
        'nvq-selector-trigger-icon'
    );
    $html .= html_writer::end_tag('button');

    $html .= html_writer::tag('button', block_nvq_matrix_search_icon_svg(), [
        'type'          => 'button',
        'class'         => 'nvq-icon-btn nvq-search-toggle',
        'id'            => 'nvq-search-toggle',
        'aria-label'    => get_string('togglesearch', 'block_nvq_matrix'),
        'aria-expanded' => 'false',
        'aria-controls' => 'nvq_student_search',
    ]);
    $html .= html_writer::tag('button', block_nvq_matrix_funnel_icon_svg(), [
        'type'          => 'button',
        'class'         => 'nvq-icon-btn nvq-filter-toggle',
        'id'            => 'nvq-filter-toggle',
        'aria-label'    => get_string('togglefilter', 'block_nvq_matrix'),
        'aria-expanded' => 'false',
        'aria-controls' => 'nvq-filter-dropdown',
    ]);
    $html .= html_writer::end_div(); // .nvq-selector-bar

    $html .= html_writer::label(
        get_string('selectstudent', 'block_nvq_matrix'),
        'nvq_student_search',
        true,
        ['class' => 'nvq-sr-only']
    );
    $html .= html_writer::empty_tag('input', [
        'type'         => 'search',
        'id'           => 'nvq_student_search',
        'class'        => 'nvq-student-search',
        'placeholder'  => get_string('searchstudentplaceholder', 'block_nvq_matrix'),
        'autocomplete' => 'off',
        'hidden'       => 'hidden',
    ]);

    // Filter chips - "All" is the default active bucket per client
    // request; "Needs grading" and "Completed" are one click away behind
    // the funnel icon above.
    $html .= html_writer::start_div('nvq-student-filters', [
        'id'         => 'nvq-filter-dropdown',
        'role'       => 'group',
        'aria-label' => get_string('togglefilter', 'block_nvq_matrix'),
        'hidden'     => 'hidden',
    ]);
    $chips = [
        'needsgrading' => get_string('filterneedsgrading', 'block_nvq_matrix'),
        'completed'    => get_string('filtercompleted', 'block_nvq_matrix'),
        'all'          => get_string('filterall', 'block_nvq_matrix'),
    ];
    foreach ($chips as $key => $label) {
        $chipattrs = [
            'type'         => 'button',
            'class'        => 'nvq-filter-chip' . ($key === 'all' ? ' nvq-filter-chip--active' : ''),
            'data-filter'  => $key,
            'aria-pressed' => $key === 'all' ? 'true' : 'false',
        ];
        $html .= html_writer::tag('button', $label, $chipattrs);
    }

    // "Show archived" toggle - off by default per client decision, so
    // archived rows never clutter the normal list unless deliberately
    // asked for. Only rendered at all when there's something for it to
    // reveal - no point showing a toggle for zero archived students.
    if (!empty($archivedrows)) {
        // If the student currently being viewed is only reachable via an
        // archived row, default the toggle to checked - otherwise their
        // own row would be invisible in the list while their matrix is
        // open on screen, which would look broken rather than just "off
        // by default".
        $activeisarchived = false;
        foreach ($archivedrows as $arow) {
            if ((int) $arow['userid'] === (int) $studentid) {
                $activeisarchived = true;
                break;
            }
        }

        $html .= html_writer::start_div('nvq-archived-toggle-wrap');
        $toggleattrs = [
            'type'  => 'checkbox',
            'id'    => 'nvq-show-archived',
            'class' => 'nvq-archived-toggle',
        ];
        if ($activeisarchived) {
            $toggleattrs['checked'] = 'checked';
        }
        $html .= html_writer::empty_tag('input', $toggleattrs);
        $html .= html_writer::label(
            get_string('showarchived', 'block_nvq_matrix'),
            'nvq-show-archived',
            true,
            ['class' => 'nvq-archived-toggle-label']
        );
        $html .= html_writer::end_div();
    }

    $html .= html_writer::end_div();

    // Date range - only meaningful once "Completed" is the active filter
    // (JS shows/hides this), since "when completed" has no meaning for
    // a student who hasn't completed yet. Blank from/to = no restriction,
    // so the Completed chip on its own still shows every completed
    // student, same as before this was added.
    $html .= html_writer::start_div('nvq-completed-daterange', ['hidden' => 'hidden']);
    $html .= html_writer::label(
        get_string('completedfrom', 'block_nvq_matrix'),
        'nvq_completed_from',
        true,
        ['class' => 'nvq-daterange-label']
    );
    $html .= html_writer::empty_tag('input', [
        'type'  => 'date',
        'id'    => 'nvq_completed_from',
        'class' => 'nvq-daterange-input',
    ]);
    $html .= html_writer::label(
        get_string('completedto', 'block_nvq_matrix'),
        'nvq_completed_to',
        true,
        ['class' => 'nvq-daterange-label']
    );
    $html .= html_writer::empty_tag('input', [
        'type'  => 'date',
        'id'    => 'nvq_completed_to',
        'class' => 'nvq-daterange-input',
    ]);
    $html .= html_writer::end_div();

    $listattrs = ['id' => 'nvq-selector-panel', 'role' => 'listbox'];
    if (!$liststartsopen) {
        $listattrs['hidden'] = 'hidden';
    }
    $html .= html_writer::start_div('nvq-student-list', $listattrs);
    foreach ($rows as $data) {
        $rowurl = new moodle_url($pageurl, [
            'nvq_matrix_student' => $data['userid'],
            'nvq_matrix_course'  => $data['courseid'],
        ]);
        // Only this exact student+course row is "active" now - each row
        // opens its own specific course's matrix (see where $rows is
        // built above), not a shared combined view, so matching on
        // userid alone would incorrectly mark every one of a student's
        // rows active at once whenever any one of them was open.
        $isactive = ((int) $data['userid'] === (int) $studentid)
            && ((int) $data['courseid'] === (int) $resolvedcourseid);
        $rowclass = 'nvq-student-row';
        if ($isactive) {
            $rowclass .= ' nvq-student-row--active';
        }
        $searchtext = mb_strtolower($data['name'] . ' ' . $data['coursename']);

        $rowcontent = html_writer::span(s($data['name']), 'nvq-student-row-name');
        if (!empty($data['coursename'])) {
            $rowcontent .= html_writer::span(s($data['coursename']), 'nvq-student-row-course');
        }
        $badgestring = match ($data['bucket']) {
            'completed' => get_string('statuscompleted', 'block_nvq_matrix'),
            'archived'  => get_string('statusarchived', 'block_nvq_matrix'),
            default     => get_string('statusneedsgrading', 'block_nvq_matrix'),
        };
        $rowcontent .= html_writer::span(s($badgestring), 'nvq-student-row-badge nvq-badge--' . $data['bucket']);
        if ($data['bucket'] === 'completed' && !empty($data['completeddate'])) {
            $rowcontent .= html_writer::span(
                get_string('completedondate', 'block_nvq_matrix', $data['completeddate']),
                'nvq-student-row-completeddate'
            );
        }
        // Archived describes enrolment state, not grading state - the
        // "Archived" badge alone says nothing about whether this
        // student's course was ever actually graded, so show final
        // status as a separate, second badge rather than overloading
        // one badge to mean two different things.
        if ($data['bucket'] === 'archived' && array_key_exists('finalstatus', $data)) {
            $finalstatustext = match ($data['finalstatus']) {
                1 => get_string('finalstatuspass', 'block_nvq_matrix'),
                0 => get_string('finalstatusfail', 'block_nvq_matrix'),
                default => get_string('finalstatusnotset', 'block_nvq_matrix'),
            };
            $finalstatusclass = match ($data['finalstatus']) {
                1 => 'nvq-badge--completed',
                0 => 'nvq-badge--fail',
                default => 'nvq-badge--needsgrading',
            };
            $rowcontent .= html_writer::span(s($finalstatustext), 'nvq-student-row-badge ' . $finalstatusclass);
            if ($data['finalstatus'] === 1 && !empty($data['completeddate'])) {
                $rowcontent .= html_writer::span(
                    get_string('completedondate', 'block_nvq_matrix', $data['completeddate']),
                    'nvq-student-row-completeddate'
                );
            }
        }

        $rowlink = html_writer::link($rowurl, $rowcontent, [
            'class'              => $rowclass,
            'data-status'        => $data['bucket'],
            'data-name'          => $searchtext,
            'data-completeddate' => $data['completeddate'],
            'role'               => 'option',
            'aria-selected'      => $isactive ? 'true' : 'false',
        ]);

        // Delete (archived only) - a real capability check, separate
        // from :viewall (seeing the archived list doesn't mean being
        // allowed to permanently destroy it), so this button only
        // renders for a viewer who genuinely holds
        // block/nvq_matrix:deletearchived on THIS row's specific
        // course. The confirm dialog + actual delete call are wired in
        // matrix.mustache's script, mirroring the export tile's
        // data-confirm pattern.
        $showdelete = $data['bucket'] === 'archived'
            && isset($contextbycourseid[$data['courseid']])
            && has_capability('block/nvq_matrix:deletearchived', $contextbycourseid[$data['courseid']]);

        if ($showdelete) {
            $deletebtn = html_writer::tag('button', get_string('deletearchived', 'block_nvq_matrix'), [
                'type'  => 'button',
                'class' => 'nvq-archived-delete-btn',
                'data-userid'   => $data['userid'],
                'data-courseid' => $data['courseid'],
                'data-confirm'  => get_string('deletearchivedconfirm', 'block_nvq_matrix', [
                    'name'       => $data['name'],
                    'coursename' => $data['coursename'],
                ]),
            ]);
            // Wrapped only when there's actually a delete button to sit
            // beside — a plain row stays a bare <a>, unchanged, so this
            // never risks the row-filtering JS above (which already
            // matches .nvq-student-row regardless of nesting depth, but
            // there's no reason to add a wrapper div for every row when
            // only archived-with-permission ones need one).
            $html .= html_writer::div($rowlink . $deletebtn, 'nvq-student-row-wrap');
        } else {
            $html .= $rowlink;
        }
    }
    $html .= html_writer::div(
        get_string('nostudentsmatch', 'block_nvq_matrix'),
        'nvq-student-row-empty',
        ['hidden' => 'hidden']
    );
    $html .= html_writer::end_div();

    $html .= html_writer::end_tag('div');
    $selectorhtml = $html;
}

// ----------------------------------------------------------------
// Redirect students with no viewall capability back to the dashboard
// if they somehow reach this page without being logged in as a student.
// (require_login() above already gates guests.)
// ----------------------------------------------------------------

// ----------------------------------------------------------------
// Build template data using the shared helper.
// ----------------------------------------------------------------
$templatedata = matrix_data::build(
    $studentid,
    $canviewall,
    $selectorhtml,
    (bool) $resolvedcourseid, // oncoursepage: true whenever a specific
                               // course resolved above, so build() scopes
                               // topicids (and everything keyed off them -
                               // grades/sampling/comments/portfolio links/
                               // final-status boxes) to just that course,
                               // instead of the student's entire history
                               // across every course. A plain student
                               // viewing their own matrix now also
                               // resolves to a real course id (see the
                               // else branch above) rather than always
                               // 0 - fixing units/progress from more than
                               // one NVQ-mapped course silently blending
                               // together on their own matrix page.
    $resolvedcourseid,
    $cangrade,           // cangrade: its own capability (block/nvq_matrix:grade) — editingteacher/manager only.
    $cansample,          // cansample: its own capability (block/nvq_matrix:sample) — editingteacher/manager only.
    $caniqacomment,      // caniqacomment: block/nvq_matrix:iqacomment — teacher (IQA), editingteacher, manager.
    $canfinalstatus      // canfinalstatus: block/nvq_matrix:finalstatus — editingteacher, manager.
);

// Course switcher only exists for the plain-student branch above (a
// canviewall assessor/IQA already switches course via the main student+
// course selector), so this is empty/false for every other viewer.
$templatedata['courseswitcher']     = $templatedata_courseswitcher ?? [];
$templatedata['showcourseswitcher'] = !empty($templatedata['courseswitcher']);

// ----------------------------------------------------------------
// Output.
// ----------------------------------------------------------------
echo $OUTPUT->header();

// Print page-level "back to dashboard" link. The export-portfolio and
// assessor-manage links used to live here too; export moved into the
// matrix template's dashboard row (see below), assessor-manage stays
// here since it's not part of that row's design.
$topbar = html_writer::link(
    new moodle_url('/my/'),
    '&#8592; ' . get_string('backtodashboard', 'block_nvq_matrix'),
    ['class' => 'nvq-back-link']
);

// Export Portfolio is now rendered inside the matrix template's compact
// dashboard row (alongside the Gap Analysis tile) rather than as a
// page-level topbar link — same capability/student gating and the same
// export.php destination + confirm-dialog message as before, just
// handed to the template instead of built as an html_writer::link here.
//
// courseid added to the export URL (v26.6.5, client decision - see
// classes/portfolio_export.php's build_matrix_tree() docblock): the
// export itself is now restricted to the single course being viewed,
// not every course the student has ever had data on - $resolvedcourseid
// is exactly that course, already validated above. Also now gated on
// $resolvedcourseid being genuinely resolved (not the idle/unresolved
// 0 state), since export.php requires courseid and there is nothing
// meaningful to export without one.
$templatedata['showexportbutton'] = false;
if ($canexportportfolio && $studentid && $resolvedcourseid) {
    $exporturl = new moodle_url('/blocks/nvq_matrix/export.php', [
        'studentid' => $studentid,
        'courseid'  => $resolvedcourseid,
        'sesskey'   => sesskey(),
    ]);
    $exportstudent = $students[$studentid] ?? $DB->get_record('user', ['id' => $studentid]);
    $templatedata['showexportbutton'] = true;
    $templatedata['exporturl']        = $exporturl->out(false);
    $templatedata['exportconfirmmsg'] = get_string(
        'exportportfolioconfirm',
        'block_nvq_matrix',
        fullname($exportstudent)
    );
}

// The dashboard row (Gap Analysis tile + Export Portfolio tile) should
// render whenever either has something to show — export could be
// available even when hasunits is false (e.g. it was previously shown
// regardless of unit content, gated only on capability + a selected
// student), so this deliberately isn't just $templatedata['hasunits'].
$templatedata['showdashboardrow'] = $templatedata['hasunits'] || $templatedata['showexportbutton'];

if ($canmanageassessor) {
    $topbar .= html_writer::link(
        new moodle_url('/blocks/nvq_matrix/assessor_manage.php'),
        get_string('assessorheading', 'block_nvq_matrix'),
        ['class' => 'nvq-assessor-link btn btn-secondary']
    );
}

echo html_writer::div($topbar, 'nvq-view-topbar');

echo $OUTPUT->render_from_template('block_nvq_matrix/matrix', $templatedata);

echo $OUTPUT->footer();
