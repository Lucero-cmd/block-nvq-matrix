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
 * Upgrade steps for block_nvq_matrix.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_nvq_matrix_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070200) {

        // Define table block_nvq_matrix_grades to be created.
        $table = new xmldb_table('block_nvq_matrix_grades');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('topicid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('gradedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table->add_key('gradedby', XMLDB_KEY_FOREIGN, ['gradedby'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        $table->add_index('studentid-topicid-courseid', XMLDB_INDEX_UNIQUE, ['studentid', 'topicid', 'courseid']);

        // Conditionally launch create table for block_nvq_matrix_grades.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026070200, 'nvq_matrix');
    }

    if ($oldversion < 2026070300) {

        // Define table block_nvq_matrix_sampling to be created.
        $table = new xmldb_table('block_nvq_matrix_sampling');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('topicid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sampledby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table->add_key('sampledby', XMLDB_KEY_FOREIGN, ['sampledby'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        $table->add_index('studentid-topicid-courseid', XMLDB_INDEX_UNIQUE, ['studentid', 'topicid', 'courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_nvq_matrix_evidence_comments to be created.
        $table2 = new xmldb_table('block_nvq_matrix_evidence_comments');

        $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table2->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table2->add_field('commentedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table2->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table2->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table2->add_key('commentedby', XMLDB_KEY_FOREIGN, ['commentedby'], 'user', ['id']);

        $table2->add_index('studentid-itemid', XMLDB_INDEX_UNIQUE, ['studentid', 'itemid']);

        if (!$dbman->table_exists($table2)) {
            $dbman->create_table($table2);
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026070300, 'nvq_matrix');
    }

    if ($oldversion < 2026070600) {

        // Define table block_nvq_matrix_unit_comments to be created.
        $table3 = new xmldb_table('block_nvq_matrix_unit_comments');

        $table3->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table3->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table3->add_field('topicid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table3->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table3->add_field('assessorcomment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table3->add_field('assessorcommentby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table3->add_field('assessorcommenttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table3->add_field('iqacomment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table3->add_field('iqacommentby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table3->add_field('iqacommenttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table3->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table3->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
        $table3->add_key('assessorcommentby', XMLDB_KEY_FOREIGN, ['assessorcommentby'], 'user', ['id']);
        $table3->add_key('iqacommentby', XMLDB_KEY_FOREIGN, ['iqacommentby'], 'user', ['id']);
        $table3->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        $table3->add_index('studentid-topicid-courseid', XMLDB_INDEX_UNIQUE, ['studentid', 'topicid', 'courseid']);

        if (!$dbman->table_exists($table3)) {
            $dbman->create_table($table3);
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026070600, 'nvq_matrix');
    }

    if ($oldversion < 2026070700) {

        // block_nvq_matrix_evidence_comments was originally keyed on
        // (studentid, itemid). Bug: the same evidence item can be linked to
        // more than one criterion (a separate block_exacompcompuser_mm row
        // per link), so a comment written under one criterion was showing
        // identically under every other criterion that file happened to
        // also be attached to. Re-key on (studentid, mmid) instead, where
        // mmid is the specific block_exacompcompuser_mm link row.
        $table4 = new xmldb_table('block_nvq_matrix_evidence_comments');
        $field4 = new xmldb_field('mmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'studentid');

        if (!$dbman->field_exists($table4, $field4)) {
            $dbman->add_field($table4, $field4);
        }

        // Best-effort backfill for any existing rows: match each
        // (studentid, itemid) comment to one of its current mm link rows.
        // If a file is linked to several criteria, this arbitrarily
        // attaches the pre-existing comment to the lowest-id link — there
        // is no way to know which criterion the original comment was
        // actually about, since that information was never stored. Any
        // site with more than a handful of such comments at upgrade time
        // should sanity-check them afterwards.
        $existingcomments = $DB->get_records('block_nvq_matrix_evidence_comments', ['mmid' => null]);
        foreach ($existingcomments as $ec) {
            $mm = $DB->get_records('block_exacompcompuser_mm', [
                'userid'         => $ec->studentid,
                'activityid'     => $ec->itemid,
                'eportfolioitem' => 1,
            ], 'id ASC', 'id', 0, 1);
            $mm = reset($mm);
            if ($mm) {
                $ec->mmid = $mm->id;
                $DB->update_record('block_nvq_matrix_evidence_comments', $ec);
            }
        }

        // Rows that still have no mmid (the evidence link no longer exists,
        // e.g. it was removed from exaport since the comment was written)
        // can't be attached to anything current — remove them rather than
        // leave unreachable orphan rows.
        $DB->delete_records('block_nvq_matrix_evidence_comments', ['mmid' => null]);

        // Now that every remaining row has an mmid, tighten the column and
        // swap the unique index from (studentid, itemid) to (studentid, mmid).
        // NOTE: must use a fresh xmldb_field definition with NOTNULL set —
        // change_field_notnull() reads the target state off the field
        // object passed to it, so reusing $field4 (defined nullable, for
        // add_field above) here would silently no-op instead of tightening
        // the column.
        $field4notnull = new xmldb_field('mmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'studentid');
        $dbman->change_field_notnull($table4, $field4notnull);

        $oldindex = new xmldb_index('studentid-itemid', XMLDB_INDEX_UNIQUE, ['studentid', 'itemid']);
        if ($dbman->index_exists($table4, $oldindex)) {
            $dbman->drop_index($table4, $oldindex);
        }

        $newindex = new xmldb_index('studentid-mmid', XMLDB_INDEX_UNIQUE, ['studentid', 'mmid']);
        if (!$dbman->index_exists($table4, $newindex)) {
            $dbman->add_index($table4, $newindex);
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026070700, 'nvq_matrix');
    }

    if ($oldversion < 2026070800) {

        // BUG FIX: the unit-level IQA comment template showed the
        // "commented by <name>" byline whenever iqacommentby was set, even
        // if iqacomment itself had since been cleared back to blank —
        // producing an empty comment box that still displayed a name,
        // which read as "someone commented" when nobody currently had.
        // save_unit_comment() now clears iqacommentby/iqacommenttime
        // whenever the comment is cleared, so this can't recur going
        // forward. This one-off cleans up any rows already left in that
        // state by earlier testing before the fix landed.
        $DB->execute("
            UPDATE {block_nvq_matrix_unit_comments}
               SET iqacommentby = NULL, iqacommenttime = NULL
             WHERE (iqacomment IS NULL OR " . $DB->sql_compare_text('iqacomment') . " = '')
               AND iqacommentby IS NOT NULL
        ");

        // Same cleanup for the dormant assessorcomment columns, for
        // consistency, even though nothing currently reads them.
        $DB->execute("
            UPDATE {block_nvq_matrix_unit_comments}
               SET assessorcommentby = NULL, assessorcommenttime = NULL
             WHERE (assessorcomment IS NULL OR " . $DB->sql_compare_text('assessorcomment') . " = '')
               AND assessorcommentby IS NOT NULL
        ");

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026070800, 'nvq_matrix');
    }

    if ($oldversion < 2026071600) {

        // FEATURE: decouple the grade verdict from the grade comment.
        // Previously "Clear grade" deleted the whole block_nvq_matrix_grades
        // row, which also deleted the comment sitting in the same row -
        // client-reported: clearing a Not Yet Competent verdict to re-grade
        // a student was wiping out the assessor's comment along with it.
        // value/gradedby/timemodified now describe the verdict only and can
        // be NULL (verdict cleared); comment attribution moves to its own
        // commentedby/commenttime columns so the two can be cleared
        // independently. See matrix_data::clear_grade()/save_grade().
        $table = new xmldb_table('block_nvq_matrix_grades');

        $valuefield = new xmldb_field('value', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'courseid');
        if ($dbman->field_exists($table, $valuefield)) {
            $dbman->change_field_notnull($table, $valuefield);
            $dbman->change_field_default($table, $valuefield);
        }

        // gradedby has a foreign-key-backed index (soft_..._gra_ix) from the
        // original install.xml. Most DB drivers refuse to alter a column an
        // index depends on (ddl_dependency_exception), so the key has to be
        // dropped first and re-added afterwards.
        $gradedbykey = new xmldb_key('gradedby', XMLDB_KEY_FOREIGN, ['gradedby'], 'user', ['id']);
        if ($dbman->find_key_name($table, $gradedbykey)) {
            $dbman->drop_key($table, $gradedbykey);
        }

        $gradedbyfield = new xmldb_field('gradedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'comment');
        if ($dbman->field_exists($table, $gradedbyfield)) {
            $dbman->change_field_notnull($table, $gradedbyfield);
        }

        if (!$dbman->find_key_name($table, $gradedbykey)) {
            $dbman->add_key($table, $gradedbykey);
        }

        $timemodifiedfield = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'gradedby');
        if ($dbman->field_exists($table, $timemodifiedfield)) {
            $dbman->change_field_notnull($table, $timemodifiedfield);
        }

        $commentedbyfield = new xmldb_field('commentedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timemodified');
        if (!$dbman->field_exists($table, $commentedbyfield)) {
            $dbman->add_field($table, $commentedbyfield);
        }

        $commenttimefield = new xmldb_field('commenttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'commentedby');
        if (!$dbman->field_exists($table, $commenttimefield)) {
            $dbman->add_field($table, $commenttimefield);
        }

        $commentedbykey = new xmldb_key('commentedby', XMLDB_KEY_FOREIGN, ['commentedby'], 'user', ['id']);
        if (!$dbman->find_key_name($table, $commentedbykey)) {
            $dbman->add_key($table, $commentedbykey);
        }

        // Best-effort backfill: any existing row with a non-blank comment
        // gets that comment's attribution copied from gradedby/timemodified
        // (the only place it was recorded before this upgrade) into the new
        // dedicated columns, so existing comments keep showing a byline
        // after this upgrade instead of suddenly losing it.
        $DB->execute("
            UPDATE {block_nvq_matrix_grades}
               SET commentedby = gradedby, commenttime = timemodified
             WHERE comment IS NOT NULL AND " . $DB->sql_compare_text('comment') . " <> ''
               AND commentedby IS NULL
        ");

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026071600, 'nvq_matrix');
    }

    if ($oldversion < 2026071700) {

        // FEATURE: evidence type dropdown on each evidence item (APL, EoE,
        // NA, O, P, PD, Q, RA, S, WT). Stored on the same row as the
        // per-item comment (block_nvq_matrix_evidence_comments) since both
        // are keyed on mmid, but deliberately a separate column with its
        // own save path (matrix_data::save_evidence_type()) so setting the
        // evidence type never touches the comment or its attribution, and
        // vice versa - same independence principle as the grade/comment
        // split in 2026071600.
        $table = new xmldb_table('block_nvq_matrix_evidence_comments');

        $evidencetypefield = new xmldb_field('evidencetype', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'comment');
        if (!$dbman->field_exists($table, $evidencetypefield)) {
            $dbman->add_field($table, $evidencetypefield);
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026071700, 'nvq_matrix');
    }

    if ($oldversion < 2026071800) {

        // FEATURE: final Pass/Fail status per student per course, set by
        // the assessor, with an optional completion notification sent to
        // the student. See matrix_data::save_final_status() /
        // send_completion_notification() and final_status.php.
        $table = new xmldb_table('block_nvq_matrix_status');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('setby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('notifiedtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('notifiedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
            $table->add_key('setby', XMLDB_KEY_FOREIGN, ['setby'], 'user', ['id']);
            $table->add_key('notifiedby', XMLDB_KEY_FOREIGN, ['notifiedby'], 'user', ['id']);
            $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

            $table->add_index('studentid-courseid', XMLDB_INDEX_UNIQUE, ['studentid', 'courseid']);

            $dbman->create_table($table);
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026071800, 'nvq_matrix');
    }

    if ($oldversion < 2026072101) {

        // FEATURE: multiple evidence types per evidence item. Previously
        // evidencetype was a single column on block_nvq_matrix_evidence_comments
        // (one code max), but the same evidence item can genuinely fit more
        // than one type (e.g. both Observation and Professional Discussion).
        // New child table, one row per (evidence item, type) pair — see the
        // table comment in install.xml for why this is a separate lookup
        // table rather than a widened comma-separated column or a column
        // joined directly into the main matrix query.
        $table = new xmldb_table('block_nvq_matrix_evidence_types');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('evidencecommentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key(
                'evidencecommentid',
                XMLDB_KEY_FOREIGN,
                ['evidencecommentid'],
                'block_nvq_matrix_evidence_comments',
                ['id']
            );

            $table->add_index('evidencecommentid-code', XMLDB_INDEX_UNIQUE, ['evidencecommentid', 'code']);

            $dbman->create_table($table);
        }

        // Migrate: every existing row that already has a single evidencetype
        // code becomes one row in the new table. The old evidencetype
        // column is left in place (untouched, unused going forward) as a
        // one-release rollback safety net rather than dropped immediately —
        // see matrix_data::save_evidence_type()'s docblock.
        $existingtypes = $DB->get_records_select(
            'block_nvq_matrix_evidence_comments',
            'evidencetype IS NOT NULL AND ' . $DB->sql_compare_text('evidencetype') . " <> ''",
            [],
            '',
            'id, evidencetype'
        );
        foreach ($existingtypes as $ec) {
            if (!$DB->record_exists('block_nvq_matrix_evidence_types', ['evidencecommentid' => $ec->id, 'code' => $ec->evidencetype])) {
                $DB->insert_record('block_nvq_matrix_evidence_types', (object) [
                    'evidencecommentid' => $ec->id,
                    'code'              => $ec->evidencetype,
                ]);
            }
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026072101, 'nvq_matrix');
    }

    if ($oldversion < 2026080701) {
        // block/nvq_matrix:exportportfolio widened from CONTEXT_SYSTEM
        // (manager archetype only) to CONTEXT_COURSE (teacher/
        // editingteacher/manager) in this version, per client request to
        // include teachers. update_capabilities() only auto-applies
        // archetype defaults to a BRAND NEW capability being added for
        // the first time - for an EXISTING capability whose archetypes
        // list changes on upgrade, already-assigned roles are NOT
        // automatically re-granted the widened access. Without this
        // explicit step, a site upgrading from an earlier version would
        // see db/access.php now listing teacher/editingteacher but no
        // actual change in who can export - the Teacher/Editing teacher
        // roles would still lack the capability until someone manually
        // visited Site administration -> Users -> Permissions -> Define
        // roles and granted it by hand. A fresh install doesn't need
        // this - update_capabilities() already grants archetype defaults
        // correctly the first time any capability is created.
        $capability = 'block/nvq_matrix:exportportfolio';
        $systemcontext = context_system::instance();
        foreach (['teacher', 'editingteacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                // assign_capability() already resets that role's own
                // cache internally (accesslib_clear_role_cache()) at the
                // end of every call - no separate manual cache-reset
                // needed after this loop.
                assign_capability($capability, CAP_ALLOW, $role->id, $systemcontext->id, true);
            }
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026080701, 'nvq_matrix');
    }

    if ($oldversion < 2026081801) {
        // New table for the Assessor-designation feature: one row per
        // course, recording which course teacher (must hold
        // block/nvq_matrix:grade there) is designated as the Assessor.
        $table = new xmldb_table('block_nvq_matrix_assessor');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('setby', XMLDB_KEY_FOREIGN, ['setby'], 'user', ['id']);

            // Deliberately no separate courseid foreign key above.
            // Confirmed live (twice): Moodle's xmldb_table rejects a KEY
            // and an INDEX on the identical field set as a collision
            // regardless of what either is named - renaming the index
            // to 'courseid-unique' alone did NOT fix it, the second
            // live attempt hit the same rejection, just naming the
            // index instead of the key in the error message. Moodle
            // doesn't enforce FK referential integrity at the DB engine
            // level anyway (advisory metadata only), so dropping the
            // declared key costs nothing functionally - courseid
            // validity is already guaranteed by
            // context_course::instance($courseid) being called before
            // any write in matrix_data::save_assessor().
            $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);

            $dbman->create_table($table);
        }

        // block/nvq_matrix:manageassessor is a brand new capability, so
        // update_capabilities() (called automatically after this
        // upgrade step returns) already grants the archetype defaults
        // declared in db/access.php (editingteacher, manager) to every
        // role using those archetypes - no explicit assign_capability()
        // loop needed here, unlike the exportportfolio widening above.
        // That loop was only necessary because exportportfolio already
        // existed with narrower archetypes; update_capabilities() only
        // auto-applies defaults the first time a capability is created.

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026081801, 'nvq_matrix');
    }

    if ($oldversion < 2026082001) {
        // New table for the settings-page notification-behaviour toggle
        // (block_nvq_matrix/renotifyonedit) - tracks which eportfolio
        // items have already triggered an assessor notification, so
        // 'once per item' mode has something to check against. See that
        // table's own COMMENT in install.xml for why it's only ever
        // touched when the setting is OFF.
        $table = new xmldb_table('block_nvq_matrix_notified_items');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timenotified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // No itemid foreign key - same reasoning as
            // block_nvq_matrix_assessor's courseid index: a KEY and an
            // INDEX on the identical single field collide in Moodle's
            // xmldb_table regardless of naming (confirmed live on that
            // table already), and itemid references a different
            // plugin's table (block_exaport) besides.
            $table->add_index('itemid', XMLDB_INDEX_UNIQUE, ['itemid']);

            $dbman->create_table($table);
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026082001, 'nvq_matrix');
    }

    if ($oldversion < 2026082101) {
        // Company Manager (site-custom role, shortname 'companymanager')
        // is meant to be view-only in this plugin, but its archetype
        // (teacher) means it silently inherited :iqacomment and
        // :exportportfolio from db/access.php's archetype defaults -
        // both granted to 'teacher' for this site's actual IQA/EQA
        // reviewer role, not intended for Company Manager. Explicitly
        // Prevent every write/export capability on this role, leaving
        // only :viewall untouched. Looked up by shortname (hardcoded,
        // this is a fully custom site plugin, not a portable one) -
        // skipped harmlessly if the role doesn't exist on a given site
        // (e.g. a fresh install with no company-manager role set up
        // yet). Same context_system pattern as the :exportportfolio
        // widening step above (v26.4.18/2026080701) - a role's default
        // capability set is defined via context_system regardless of
        // the capability's own declared contextlevel.
        $companymanagerrole = $DB->get_record('role', ['shortname' => 'companymanager']);
        if ($companymanagerrole) {
            $systemcontext = context_system::instance();
            $preventcapabilities = [
                'block/nvq_matrix:sample',
                'block/nvq_matrix:grade',
                'block/nvq_matrix:iqacomment',
                'block/nvq_matrix:finalstatus',
                'block/nvq_matrix:manageassessor',
                'block/nvq_matrix:exportportfolio',
            ];
            foreach ($preventcapabilities as $capability) {
                assign_capability($capability, CAP_PREVENT, $companymanagerrole->id, $systemcontext->id, true);
            }
        }

        // Nvq_matrix savepoint reached.
        upgrade_block_savepoint(true, 2026082101, 'nvq_matrix');
    }

    return true;
}
