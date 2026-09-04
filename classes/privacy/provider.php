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
 * Privacy API implementation for block_nvq_matrix.
 *
 * Replaces the null_provider that was in place since v17 (and was wrong from
 * that point on — see version.php v25 changelog). This block stores, across
 * its own dedicated tables, unit-level grades, sampling status, three kinds
 * of attributed comments, a final Pass/Fail status per student per course,
 * the designated assessor per student per course, and a record of when an
 * archived student's data was permanently deleted - all tied to a specific
 * student and/or to the staff member who wrote or actioned each entry. Every
 * table is scoped to CONTEXT_COURSE (most via a direct courseid column, one —
 * evidence comments — via a join chain through exacomp's own tables, since
 * it has no courseid column of its own).
 *
 * block_nvq_matrix_assessor and block_nvq_matrix_cleared_archive were added
 * to this provider in v26.6.3 - a real gap, not a deliberate exclusion; both
 * store direct user references and were previously missing entirely (see
 * their own get_metadata() entries below for why each is treated as
 * export-only rather than subject-erasable, same reasoning as this file's
 * existing author-attribution columns).
 *
 * Design decision on deletion — read before changing:
 * A row in these tables has two people attached to it: the STUDENT it is
 * about (studentid) and the STAFF MEMBER who wrote it (gradedby / sampledby /
 * commentedby / iqacommentby / assessorcommentby). When a *student* requests
 * erasure, their own rows are deleted outright — that data exists solely to
 * describe them. When a *staff member* requests erasure, their rows are
 * NOT deleted, because doing so would remove another student's grading/
 * sampling/comment record — the authorship stays in place. This mirrors the
 * standard Moodle pattern for academic records (e.g. grade history, forum
 * posts), where a legitimate interest / legal-obligation basis to retain the
 * record for the person it's about can outweigh an author's erasure request
 * for their own attribution on someone else's record. If the client's DPO
 * wants a stricter policy (e.g. anonymising author names entirely), the
 * remaining NOTNULL author columns (sampledby, iqacommentby/
 * assessorcommentby) would need relaxing via a schema upgrade first — same
 * treatment grades.gradedby/commentedby already got in v26.2 for an
 * unrelated reason (decoupling the grade verdict from its comment), which
 * happens to leave those two already nullable. Flagged in the handover, not
 * done here since it wasn't asked for and changes the schema.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_nvq_matrix\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for block_nvq_matrix.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    /**
     * Describes what personal data this plugin stores and why.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_nvq_matrix_grades',
            [
                'studentid'    => 'privacy:metadata:block_nvq_matrix_grades:studentid',
                'topicid'      => 'privacy:metadata:block_nvq_matrix_grades:topicid',
                'courseid'     => 'privacy:metadata:block_nvq_matrix_grades:courseid',
                'value'        => 'privacy:metadata:block_nvq_matrix_grades:value',
                'comment'      => 'privacy:metadata:block_nvq_matrix_grades:comment',
                'gradedby'     => 'privacy:metadata:block_nvq_matrix_grades:gradedby',
                'timemodified' => 'privacy:metadata:block_nvq_matrix_grades:timemodified',
                'commentedby'  => 'privacy:metadata:block_nvq_matrix_grades:commentedby',
                'commenttime'  => 'privacy:metadata:block_nvq_matrix_grades:commenttime',
            ],
            'privacy:metadata:block_nvq_matrix_grades'
        );

        $collection->add_database_table(
            'block_nvq_matrix_sampling',
            [
                'studentid'    => 'privacy:metadata:block_nvq_matrix_sampling:studentid',
                'topicid'      => 'privacy:metadata:block_nvq_matrix_sampling:topicid',
                'courseid'     => 'privacy:metadata:block_nvq_matrix_sampling:courseid',
                'status'       => 'privacy:metadata:block_nvq_matrix_sampling:status',
                'sampledby'    => 'privacy:metadata:block_nvq_matrix_sampling:sampledby',
                'timemodified' => 'privacy:metadata:block_nvq_matrix_sampling:timemodified',
            ],
            'privacy:metadata:block_nvq_matrix_sampling'
        );

        $collection->add_database_table(
            'block_nvq_matrix_evidence_comments',
            [
                'studentid'    => 'privacy:metadata:block_nvq_matrix_evidence_comments:studentid',
                'comment'      => 'privacy:metadata:block_nvq_matrix_evidence_comments:comment',
                'evidencetype' => 'privacy:metadata:block_nvq_matrix_evidence_comments:evidencetype',
                'commentedby'  => 'privacy:metadata:block_nvq_matrix_evidence_comments:commentedby',
                'timemodified' => 'privacy:metadata:block_nvq_matrix_evidence_comments:timemodified',
            ],
            'privacy:metadata:block_nvq_matrix_evidence_comments'
        );

        $collection->add_database_table(
            'block_nvq_matrix_evidence_types',
            [
                'evidencecommentid' => 'privacy:metadata:block_nvq_matrix_evidence_types:evidencecommentid',
                'code'               => 'privacy:metadata:block_nvq_matrix_evidence_types:code',
            ],
            'privacy:metadata:block_nvq_matrix_evidence_types'
        );

        $collection->add_database_table(
            'block_nvq_matrix_status',
            [
                'studentid'    => 'privacy:metadata:block_nvq_matrix_status:studentid',
                'courseid'     => 'privacy:metadata:block_nvq_matrix_status:courseid',
                'status'       => 'privacy:metadata:block_nvq_matrix_status:status',
                'setby'        => 'privacy:metadata:block_nvq_matrix_status:setby',
                'timemodified' => 'privacy:metadata:block_nvq_matrix_status:timemodified',
                'notifiedtime' => 'privacy:metadata:block_nvq_matrix_status:notifiedtime',
                'notifiedby'   => 'privacy:metadata:block_nvq_matrix_status:notifiedby',
            ],
            'privacy:metadata:block_nvq_matrix_status'
        );

        // Real gap fixed here (v26.6.3): these two tables store direct
        // user references (assessor.userid/setby,
        // cleared_archive.studentid/clearedby) but were missing from
        // this provider entirely - a data export or right-to-be-
        // forgotten request would have silently missed them.
        $collection->add_database_table(
            'block_nvq_matrix_assessor',
            [
                'courseid'     => 'privacy:metadata:block_nvq_matrix_assessor:courseid',
                'userid'       => 'privacy:metadata:block_nvq_matrix_assessor:userid',
                'setby'        => 'privacy:metadata:block_nvq_matrix_assessor:setby',
                'timemodified' => 'privacy:metadata:block_nvq_matrix_assessor:timemodified',
            ],
            'privacy:metadata:block_nvq_matrix_assessor'
        );

        $collection->add_database_table(
            'block_nvq_matrix_cleared_archive',
            [
                'studentid'   => 'privacy:metadata:block_nvq_matrix_cleared_archive:studentid',
                'courseid'    => 'privacy:metadata:block_nvq_matrix_cleared_archive:courseid',
                'timecleared' => 'privacy:metadata:block_nvq_matrix_cleared_archive:timecleared',
                'clearedby'   => 'privacy:metadata:block_nvq_matrix_cleared_archive:clearedby',
            ],
            'privacy:metadata:block_nvq_matrix_cleared_archive'
        );

        $collection->add_subsystem_link(
            'core_message',
            [],
            'privacy:metadata:block_nvq_matrix:core_message'
        );

        $collection->add_database_table(
            'block_nvq_matrix_unit_comments',
            [
                'studentid'           => 'privacy:metadata:block_nvq_matrix_unit_comments:studentid',
                'topicid'             => 'privacy:metadata:block_nvq_matrix_unit_comments:topicid',
                'courseid'            => 'privacy:metadata:block_nvq_matrix_unit_comments:courseid',
                'iqacomment'          => 'privacy:metadata:block_nvq_matrix_unit_comments:iqacomment',
                'iqacommentby'        => 'privacy:metadata:block_nvq_matrix_unit_comments:iqacommentby',
                'iqacommenttime'      => 'privacy:metadata:block_nvq_matrix_unit_comments:iqacommenttime',
                'assessorcomment'     => 'privacy:metadata:block_nvq_matrix_unit_comments:assessorcomment',
                'assessorcommentby'   => 'privacy:metadata:block_nvq_matrix_unit_comments:assessorcommentby',
                'assessorcommenttime' => 'privacy:metadata:block_nvq_matrix_unit_comments:assessorcommenttime',
            ],
            'privacy:metadata:block_nvq_matrix_unit_comments'
        );

        // Real gap fixed here (v26.6.9 audit): this table has neither a
        // studentid nor a courseid column of its own - just itemid
        // (block_exaportitem.id) and timenotified. That made it easy to
        // miss when block_nvq_matrix_assessor/cleared_archive were added
        // to this provider in v26.6.3, since neither of THIS table's two
        // columns looks like a personal-data field in isolation. It is
        // one, though: itemid resolves (via block_exaportitem, which has
        // its own direct userid + courseid columns - no join chain needed,
        // unlike evidence_comments above) to the specific student whose
        // evidence submission triggered a notification, and timenotified
        // records exactly when that happened - i.e. this table is a
        // record of a specific student's submission activity, same in
        // kind as the "who/when" already covered for every other table
        // here. No staff-author column exists on this table at all (it's
        // system-generated bookkeeping for the notify_assessors_task
        // scheduled task, see its own docblock) - only ever exported/
        // deleted as the item-owning student's own data, never as
        // "authored about someone else" the way grades/sampling/comments
        // are.
        $collection->add_database_table(
            'block_nvq_matrix_notified_items',
            [
                'itemid'       => 'privacy:metadata:block_nvq_matrix_notified_items:itemid',
                'timenotified' => 'privacy:metadata:block_nvq_matrix_notified_items:timenotified',
            ],
            'privacy:metadata:block_nvq_matrix_notified_items'
        );

        return $collection;
    }

    /**
     * Every course context this user's data appears in — either as the
     * student the row is about, or as the staff member who wrote it.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_nvq_matrix_grades} g ON g.courseid = ctx.instanceid
             WHERE ctx.contextlevel = :contextlevel1
               AND (g.studentid = :userid1 OR g.gradedby = :userid2 OR g.commentedby = :userid10)
        ", [
            'contextlevel1' => CONTEXT_COURSE,
            'userid1'       => $userid,
            'userid2'       => $userid,
            'userid10'      => $userid,
        ]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_nvq_matrix_sampling} s ON s.courseid = ctx.instanceid
             WHERE ctx.contextlevel = :contextlevel2
               AND (s.studentid = :userid3 OR s.sampledby = :userid4)
        ", [
            'contextlevel2' => CONTEXT_COURSE,
            'userid3'       => $userid,
            'userid4'       => $userid,
        ]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_nvq_matrix_unit_comments} uc ON uc.courseid = ctx.instanceid
             WHERE ctx.contextlevel = :contextlevel3
               AND (uc.studentid = :userid5 OR uc.iqacommentby = :userid6 OR uc.assessorcommentby = :userid7)
        ", [
            'contextlevel3' => CONTEXT_COURSE,
            'userid5'       => $userid,
            'userid6'       => $userid,
            'userid7'       => $userid,
        ]);

        // Evidence comments have no courseid column of their own — resolve
        // the course(s) via the same item→criterion→topic→course chain used
        // everywhere else in this plugin (see matrix_data.php / evidence_comment.php).
        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_exacompcoutopi_mm} ct ON ct.courseid = ctx.instanceid
              JOIN {block_exacompdescrtopic_mm} dtm ON dtm.topicid = ct.topicid
              JOIN {block_exacompcompuser_mm} mm ON mm.compid = dtm.descrid
              JOIN {block_nvq_matrix_evidence_comments} ec ON ec.mmid = mm.id
             WHERE ctx.contextlevel = :contextlevel4
               AND (ec.studentid = :userid8 OR ec.commentedby = :userid9)
        ", [
            'contextlevel4' => CONTEXT_COURSE,
            'userid8'       => $userid,
            'userid9'       => $userid,
        ]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_nvq_matrix_status} st ON st.courseid = ctx.instanceid
             WHERE ctx.contextlevel = :contextlevel5
               AND (st.studentid = :userid11 OR st.setby = :userid12 OR st.notifiedby = :userid13)
        ", [
            'contextlevel5' => CONTEXT_COURSE,
            'userid11'      => $userid,
            'userid12'      => $userid,
            'userid13'      => $userid,
        ]);

        // Real gap fixed here (v26.6.3) - see get_metadata() above.
        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_nvq_matrix_assessor} a ON a.courseid = ctx.instanceid
             WHERE ctx.contextlevel = :contextlevel6
               AND (a.userid = :userid14 OR a.setby = :userid15)
        ", [
            'contextlevel6' => CONTEXT_COURSE,
            'userid14'      => $userid,
            'userid15'      => $userid,
        ]);

        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_nvq_matrix_cleared_archive} ca ON ca.courseid = ctx.instanceid
             WHERE ctx.contextlevel = :contextlevel7
               AND (ca.studentid = :userid16 OR ca.clearedby = :userid17)
        ", [
            'contextlevel7' => CONTEXT_COURSE,
            'userid16'      => $userid,
            'userid17'      => $userid,
        ]);

        // Real gap fixed here (v26.6.9 audit) - see get_metadata() above.
        // block_exaportitem has its own direct courseid + userid columns,
        // so this needs only a single join, unlike evidence_comments'
        // longer chain above.
        $contextlist->add_from_sql("
            SELECT ctx.id
              FROM {context} ctx
              JOIN {block_exaportitem} i ON i.courseid = ctx.instanceid
              JOIN {block_nvq_matrix_notified_items} ni ON ni.itemid = i.id
             WHERE ctx.contextlevel = :contextlevel8
               AND i.userid = :userid18
        ", [
            'contextlevel8' => CONTEXT_COURSE,
            'userid18'      => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Every user (student or staff author) whose data appears in this
     * course context, for a bulk data-deletion request scoped to that course.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $courseid = $context->instanceid;

        $userlist->add_from_sql('studentid', "
            SELECT studentid FROM {block_nvq_matrix_grades} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('gradedby', "
            SELECT gradedby FROM {block_nvq_matrix_grades} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('commentedby', "
            SELECT commentedby FROM {block_nvq_matrix_grades}
             WHERE courseid = :courseid AND commentedby IS NOT NULL
        ", ['courseid' => $courseid]);

        $userlist->add_from_sql('studentid', "
            SELECT studentid FROM {block_nvq_matrix_sampling} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('sampledby', "
            SELECT sampledby FROM {block_nvq_matrix_sampling} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);

        $userlist->add_from_sql('studentid', "
            SELECT studentid FROM {block_nvq_matrix_unit_comments} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('iqacommentby', "
            SELECT iqacommentby FROM {block_nvq_matrix_unit_comments}
             WHERE courseid = :courseid AND iqacommentby IS NOT NULL
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('assessorcommentby', "
            SELECT assessorcommentby FROM {block_nvq_matrix_unit_comments}
             WHERE courseid = :courseid AND assessorcommentby IS NOT NULL
        ", ['courseid' => $courseid]);

        $userlist->add_from_sql('studentid', "
            SELECT ec.studentid
              FROM {block_nvq_matrix_evidence_comments} ec
              JOIN {block_exacompcompuser_mm} mm ON mm.id = ec.mmid
              JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
              JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
             WHERE ct.courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('commentedby', "
            SELECT ec.commentedby
              FROM {block_nvq_matrix_evidence_comments} ec
              JOIN {block_exacompcompuser_mm} mm ON mm.id = ec.mmid
              JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
              JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
             WHERE ct.courseid = :courseid AND ec.commentedby IS NOT NULL
        ", ['courseid' => $courseid]);

        $userlist->add_from_sql('studentid', "
            SELECT studentid FROM {block_nvq_matrix_status} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('setby', "
            SELECT setby FROM {block_nvq_matrix_status}
             WHERE courseid = :courseid AND setby IS NOT NULL
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('notifiedby', "
            SELECT notifiedby FROM {block_nvq_matrix_status}
             WHERE courseid = :courseid AND notifiedby IS NOT NULL
        ", ['courseid' => $courseid]);

        // Real gap fixed here (v26.6.3) - see get_metadata() above.
        $userlist->add_from_sql('userid', "
            SELECT userid FROM {block_nvq_matrix_assessor} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('setby', "
            SELECT setby FROM {block_nvq_matrix_assessor}
             WHERE courseid = :courseid AND setby IS NOT NULL
        ", ['courseid' => $courseid]);

        $userlist->add_from_sql('studentid', "
            SELECT studentid FROM {block_nvq_matrix_cleared_archive} WHERE courseid = :courseid
        ", ['courseid' => $courseid]);
        $userlist->add_from_sql('clearedby', "
            SELECT clearedby FROM {block_nvq_matrix_cleared_archive}
             WHERE courseid = :courseid AND clearedby IS NOT NULL
        ", ['courseid' => $courseid]);

        // Real gap fixed here (v26.6.9 audit) - see get_metadata() above.
        $userlist->add_from_sql('studentid', "
            SELECT i.userid
              FROM {block_nvq_matrix_notified_items} ni
              JOIN {block_exaportitem} i ON i.id = ni.itemid
             WHERE i.courseid = :courseid
        ", ['courseid' => $courseid]);
    }

    /**
     * Exports this user's data for every approved course context: their own
     * rows as the student the data is about, plus a separate section for
     * entries they personally authored about other students (grades set,
     * sampling status set, comments written) in that course.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $courseid = $context->instanceid;
            $subcontext = [get_string('pluginname', 'block_nvq_matrix')];

            // ---- Data about this user (they are the student) ----
            $owngrades = $DB->get_records('block_nvq_matrix_grades', [
                'studentid' => $userid, 'courseid' => $courseid,
            ]);
            if (!empty($owngrades)) {
                $data = array_values(array_map(function ($g) {
                    if ($g->value === null) {
                        $valuetext = get_string('notgraded', 'block_nvq_matrix');
                    } else {
                        $valuetext = $g->value
                            ? get_string('competent', 'block_nvq_matrix')
                            : get_string('notyet', 'block_nvq_matrix');
                    }
                    return (object) [
                        'topicid'      => $g->topicid,
                        'value'        => $valuetext,
                        'comment'      => $g->comment,
                        'timemodified' => $g->timemodified ? transform::datetime($g->timemodified) : null,
                        'commenttime'  => $g->commenttime ? transform::datetime($g->commenttime) : null,
                    ];
                }, $owngrades));
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('col_grade', 'block_nvq_matrix')]),
                    (object) ['grades' => $data]
                );
            }

            $ownsampling = $DB->get_records('block_nvq_matrix_sampling', [
                'studentid' => $userid, 'courseid' => $courseid,
            ]);
            if (!empty($ownsampling)) {
                $data = array_values(array_map(function ($s) {
                    return (object) [
                        'topicid'      => $s->topicid,
                        'status'       => $s->status,
                        'timemodified' => transform::datetime($s->timemodified),
                    ];
                }, $ownsampling));
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('samplingstatus', 'block_nvq_matrix')]),
                    (object) ['sampling' => $data]
                );
            }

            $ownunitcomments = $DB->get_records('block_nvq_matrix_unit_comments', [
                'studentid' => $userid, 'courseid' => $courseid,
            ]);
            if (!empty($ownunitcomments)) {
                $data = array_values(array_map(function ($c) {
                    return (object) [
                        'topicid'    => $c->topicid,
                        'iqacomment' => $c->iqacomment,
                        // Legacy/unused column (see version.php v22 note) — still exported,
                        // since a site that briefly ran v22 may have real data in it.
                        'assessorcomment' => $c->assessorcomment,
                    ];
                }, $ownunitcomments));
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('iqacommentlabel', 'block_nvq_matrix')]),
                    (object) ['unitcomments' => $data]
                );
            }

            $ownevidence = $DB->get_records_sql("
                SELECT ec.*
                  FROM {block_nvq_matrix_evidence_comments} ec
                  JOIN {block_exacompcompuser_mm} mm ON mm.id = ec.mmid
                  JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                  JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                 WHERE ec.studentid = :userid AND ct.courseid = :courseid
            ", ['userid' => $userid, 'courseid' => $courseid]);
            if (!empty($ownevidence)) {
                // Evidence types fetched as a separate keyed lookup (not
                // joined into the query above) so a row with several types
                // can't multiply/misalign the evidence_comments row it
                // belongs to — same reasoning as matrix_data.php's own
                // evidence-type map build.
                $ownevidencetypes = [];
                if (!empty($ownevidence)) {
                    [$einsql, $eparams] = $DB->get_in_or_equal(array_keys($ownevidence));
                    $typerows = $DB->get_records_select(
                        'block_nvq_matrix_evidence_types',
                        "evidencecommentid $einsql",
                        $eparams
                    );
                    foreach ($typerows as $typerow) {
                        $ownevidencetypes[(int) $typerow->evidencecommentid][] = $typerow->code;
                    }
                }
                $data = array_values(array_map(function ($e) use ($ownevidencetypes) {
                    return (object) [
                        'comment'       => $e->comment,
                        'evidencetypes' => implode(', ', $ownevidencetypes[(int) $e->id] ?? []),
                        'timemodified'  => transform::datetime($e->timemodified),
                    ];
                }, $ownevidence));
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('evidencecommentplaceholder', 'block_nvq_matrix')]),
                    (object) ['evidencecomments' => $data]
                );
            }

            $ownstatus = $DB->get_record('block_nvq_matrix_status', [
                'studentid' => $userid, 'courseid' => $courseid,
            ]);
            if ($ownstatus && $ownstatus->status !== null) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('finalstatusheading', 'block_nvq_matrix')]),
                    (object) [
                        'status'       => $ownstatus->status
                            ? get_string('statuspass', 'block_nvq_matrix')
                            : get_string('statusfail', 'block_nvq_matrix'),
                        'timemodified' => $ownstatus->timemodified ? transform::datetime($ownstatus->timemodified) : null,
                        'notifiedtime' => $ownstatus->notifiedtime ? transform::datetime($ownstatus->notifiedtime) : null,
                    ]
                );
            }

            // ---- Data this user authored about other students (staff role) ----
            $authoredgrades = $DB->get_records_select(
                'block_nvq_matrix_grades',
                '(gradedby = :u1 OR commentedby = :u2) AND courseid = :courseid',
                ['u1' => $userid, 'u2' => $userid, 'courseid' => $courseid]
            );
            $authoredsampling = $DB->get_records('block_nvq_matrix_sampling', [
                'sampledby' => $userid, 'courseid' => $courseid,
            ]);
            $authoredunitcomments = $DB->get_records_select(
                'block_nvq_matrix_unit_comments',
                '(iqacommentby = :u1 OR assessorcommentby = :u2) AND courseid = :courseid',
                ['u1' => $userid, 'u2' => $userid, 'courseid' => $courseid]
            );
            $authoredstatus = $DB->get_records_select(
                'block_nvq_matrix_status',
                '(setby = :u1 OR notifiedby = :u2) AND courseid = :courseid',
                ['u1' => $userid, 'u2' => $userid, 'courseid' => $courseid]
            );

            // Real gap fixed here (v26.6.3) - see get_metadata() above.
            // A user's OWN assessor-assignment record (they are the
            // designated assessor) is exported here as "authored" data
            // rather than under "own data" above - unlike a student's
            // grade/sampling/status rows, being assigned as an assessor
            // isn't personal information ABOUT them in the same sense;
            // it's a course-administration fact they're one of two
            // parties to (alongside setby, who assigned them), so both
            // sides are shown together in this section.
            $authoredassessor = $DB->get_records_select(
                'block_nvq_matrix_assessor',
                '(userid = :u1 OR setby = :u2) AND courseid = :courseid',
                ['u1' => $userid, 'u2' => $userid, 'courseid' => $courseid]
            );
            $authoredclearedarchive = $DB->get_records_select(
                'block_nvq_matrix_cleared_archive',
                'clearedby = :u1 AND courseid = :courseid',
                ['u1' => $userid, 'courseid' => $courseid]
            );

            if (!empty($authoredassessor)) {
                $data = (object) ['assessorassignments' => array_values(array_map(function ($a) use ($userid) {
                    return (object) [
                        'role'         => ($a->userid == $userid)
                            ? get_string('privacy:assessorrole:assignee', 'block_nvq_matrix')
                            : get_string('privacy:assessorrole:assignedby', 'block_nvq_matrix'),
                        'userid'       => $a->userid,
                        'setby'        => $a->setby,
                        'timemodified' => $a->timemodified ? transform::datetime($a->timemodified) : null,
                    ];
                }, $authoredassessor))];
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:authoredentries', 'block_nvq_matrix')]),
                    $data
                );
            }

            // A student's own cleared-archive rows (they are the
            // studentid) are intentionally NOT shown under "own data"
            // above - this table only ever holds bookkeeping about a
            // long-past teacher action, not something the student
            // experiences directly, so it's exported here alongside its
            // author for context rather than duplicated in both places.
            $ownclearedarchive = $DB->get_records('block_nvq_matrix_cleared_archive', [
                'studentid' => $userid, 'courseid' => $courseid,
            ]);
            $clearedarchivetoexport = $authoredclearedarchive + $ownclearedarchive;
            if (!empty($clearedarchivetoexport)) {
                $data = (object) ['archiveclearances' => array_values(array_map(function ($ca) {
                    return (object) [
                        'studentid'   => $ca->studentid,
                        'clearedby'   => $ca->clearedby,
                        'timecleared' => $ca->timecleared ? transform::datetime($ca->timecleared) : null,
                    ];
                }, $clearedarchivetoexport))];
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('deletearchived', 'block_nvq_matrix')]),
                    $data
                );
            }

            // Real gap fixed here (v26.6.9 audit) - see get_metadata()
            // above. No staff-author side to this one (system-generated
            // bookkeeping, not authored content) - only ever exported as
            // this user's own data, resolved via their own eportfolio
            // items in this course.
            $ownnotifieditems = $DB->get_records_sql("
                SELECT ni.*
                  FROM {block_nvq_matrix_notified_items} ni
                  JOIN {block_exaportitem} i ON i.id = ni.itemid
                 WHERE i.userid = :userid AND i.courseid = :courseid
            ", ['userid' => $userid, 'courseid' => $courseid]);
            if (!empty($ownnotifieditems)) {
                $data = (object) ['notifieditems' => array_values(array_map(function ($ni) {
                    return (object) [
                        'itemid'       => $ni->itemid,
                        'timenotified' => transform::datetime($ni->timenotified),
                    ];
                }, $ownnotifieditems))];
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('tasknotifyassessors', 'block_nvq_matrix')]),
                    $data
                );
            }

            if (!empty($authoredgrades) || !empty($authoredsampling) || !empty($authoredunitcomments) || !empty($authoredstatus)) {
                $data = (object) [
                    'grades'   => array_values(array_map(function ($g) {
                        return (object) [
                            'studentid'    => $g->studentid,
                            'topicid'      => $g->topicid,
                            'comment'      => $g->comment,
                            'timemodified' => $g->timemodified ? transform::datetime($g->timemodified) : null,
                            'commenttime'  => $g->commenttime ? transform::datetime($g->commenttime) : null,
                        ];
                    }, $authoredgrades)),
                    'sampling' => array_values(array_map(function ($s) {
                        return (object) [
                            'studentid'    => $s->studentid,
                            'topicid'      => $s->topicid,
                            'timemodified' => transform::datetime($s->timemodified),
                        ];
                    }, $authoredsampling)),
                    'unitcomments' => array_values(array_map(function ($c) {
                        return (object) [
                            'studentid'  => $c->studentid,
                            'topicid'    => $c->topicid,
                            'iqacomment' => $c->iqacomment,
                        ];
                    }, $authoredunitcomments)),
                    'status' => array_values(array_map(function ($st) {
                        return (object) [
                            'studentid'    => $st->studentid,
                            'status'       => ($st->status !== null)
                                ? ($st->status ? get_string('statuspass', 'block_nvq_matrix') : get_string('statusfail', 'block_nvq_matrix'))
                                : null,
                            'timemodified' => $st->timemodified ? transform::datetime($st->timemodified) : null,
                            'notifiedtime' => $st->notifiedtime ? transform::datetime($st->notifiedtime) : null,
                        ];
                    }, $authoredstatus)),
                ];
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:authoredentries', 'block_nvq_matrix')]),
                    $data
                );
            }

            $authoredevidence = $DB->get_records_sql("
                SELECT ec.*
                  FROM {block_nvq_matrix_evidence_comments} ec
                  JOIN {block_exacompcompuser_mm} mm ON mm.id = ec.mmid
                  JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                  JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                 WHERE ec.commentedby = :userid AND ct.courseid = :courseid
            ", ['userid' => $userid, 'courseid' => $courseid]);
            if (!empty($authoredevidence)) {
                $data = array_values(array_map(function ($e) {
                    return (object) [
                        'studentid'    => $e->studentid,
                        'comment'      => $e->comment,
                        'evidencetype' => $e->evidencetype,
                        'timemodified' => transform::datetime($e->timemodified),
                    ];
                }, $authoredevidence));
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [
                        get_string('privacy:authoredentries', 'block_nvq_matrix'),
                        get_string('evidencecommentplaceholder', 'block_nvq_matrix'),
                    ]),
                    (object) ['evidencecomments' => $data]
                );
            }
        }
    }

    /**
     * Deletes ALL data in a context — used when an entire course is deleted,
     * not tied to any specific user.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $courseid = $context->instanceid;

        $DB->delete_records('block_nvq_matrix_grades', ['courseid' => $courseid]);
        $DB->delete_records('block_nvq_matrix_sampling', ['courseid' => $courseid]);
        $DB->delete_records('block_nvq_matrix_unit_comments', ['courseid' => $courseid]);
        $DB->delete_records('block_nvq_matrix_status', ['courseid' => $courseid]);
        // Real gap fixed here (v26.6.3) - see get_metadata() above.
        $DB->delete_records('block_nvq_matrix_assessor', ['courseid' => $courseid]);
        $DB->delete_records('block_nvq_matrix_cleared_archive', ['courseid' => $courseid]);

        // Real gap fixed here (v26.6.9 audit) - see get_metadata() above.
        $DB->execute("
            DELETE FROM {block_nvq_matrix_notified_items}
             WHERE itemid IN (
                SELECT i.id FROM {block_exaportitem} i WHERE i.courseid = :courseid
             )
        ", ['courseid' => $courseid]);

        $DB->execute("
            DELETE FROM {block_nvq_matrix_evidence_types}
             WHERE evidencecommentid IN (
                SELECT ec.id
                  FROM {block_nvq_matrix_evidence_comments} ec
                  JOIN {block_exacompcompuser_mm} mm ON mm.id = ec.mmid
                  JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                  JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                 WHERE ct.courseid = :courseid
             )
        ", ['courseid' => $courseid]);

        $DB->execute("
            DELETE FROM {block_nvq_matrix_evidence_comments}
             WHERE mmid IN (
                SELECT mm.id
                  FROM {block_exacompcompuser_mm} mm
                  JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                  JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                 WHERE ct.courseid = :courseid
             )
        ", ['courseid' => $courseid]);
    }

    /**
     * Deletes this user's own data (as the student/subject) for each
     * approved course context. Does NOT delete rows where this user is only
     * the staff author of an entry about a different student — see the
     * design note at the top of this class for why.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $courseid = $context->instanceid;

            $DB->delete_records('block_nvq_matrix_grades', ['studentid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_nvq_matrix_sampling', ['studentid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_nvq_matrix_unit_comments', ['studentid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_nvq_matrix_status', ['studentid' => $userid, 'courseid' => $courseid]);

            $DB->execute("
                DELETE FROM {block_nvq_matrix_evidence_types}
                 WHERE evidencecommentid IN (
                    SELECT ec.id
                      FROM {block_nvq_matrix_evidence_comments} ec
                     WHERE ec.studentid = :userid
                       AND ec.mmid IN (
                        SELECT mm.id
                          FROM {block_exacompcompuser_mm} mm
                          JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                          JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                         WHERE ct.courseid = :courseid
                     )
                 )
            ", ['userid' => $userid, 'courseid' => $courseid]);

            $DB->execute("
                DELETE FROM {block_nvq_matrix_evidence_comments}
                 WHERE studentid = :userid
                   AND mmid IN (
                    SELECT mm.id
                      FROM {block_exacompcompuser_mm} mm
                      JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                      JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                     WHERE ct.courseid = :courseid
                 )
            ", ['userid' => $userid, 'courseid' => $courseid]);

            // Real gap fixed here (v26.6.9 audit) - see get_metadata() above.
            $DB->execute("
                DELETE FROM {block_nvq_matrix_notified_items}
                 WHERE itemid IN (
                    SELECT i.id
                      FROM {block_exaportitem} i
                     WHERE i.userid = :userid AND i.courseid = :courseid
                 )
            ", ['userid' => $userid, 'courseid' => $courseid]);
        }
    }

    /**
     * Bulk-deletes an approved set of users' data within one course context.
     * Same subject-only deletion rule as delete_data_for_user() — see the
     * design note at the top of this class.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $courseid = $context->instanceid;

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('block_nvq_matrix_grades', ['studentid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_nvq_matrix_sampling', ['studentid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_nvq_matrix_unit_comments', ['studentid' => $userid, 'courseid' => $courseid]);
            $DB->delete_records('block_nvq_matrix_status', ['studentid' => $userid, 'courseid' => $courseid]);

            $DB->execute("
                DELETE FROM {block_nvq_matrix_evidence_types}
                 WHERE evidencecommentid IN (
                    SELECT ec.id
                      FROM {block_nvq_matrix_evidence_comments} ec
                     WHERE ec.studentid = :userid
                       AND ec.mmid IN (
                        SELECT mm.id
                          FROM {block_exacompcompuser_mm} mm
                          JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                          JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                         WHERE ct.courseid = :courseid
                     )
                 )
            ", ['userid' => $userid, 'courseid' => $courseid]);

            $DB->execute("
                DELETE FROM {block_nvq_matrix_evidence_comments}
                 WHERE studentid = :userid
                   AND mmid IN (
                    SELECT mm.id
                      FROM {block_exacompcompuser_mm} mm
                      JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                      JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
                     WHERE ct.courseid = :courseid
                 )
            ", ['userid' => $userid, 'courseid' => $courseid]);

            // Real gap fixed here (v26.6.9 audit) - see get_metadata() above.
            $DB->execute("
                DELETE FROM {block_nvq_matrix_notified_items}
                 WHERE itemid IN (
                    SELECT i.id
                      FROM {block_exaportitem} i
                     WHERE i.userid = :userid AND i.courseid = :courseid
                 )
            ", ['userid' => $userid, 'courseid' => $courseid]);
        }
    }
}
