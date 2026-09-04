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
 * Scheduled task that notifies each course's designated Assessor about
 * new student evidence submissions.
 *
 * Replaces the v26.4.21 event-observer approach, which turned out to be
 * built on a wrong assumption. This plugin's matrix reads evidence from
 * block_exacompcompuser_mm (joined to block_exaportitem) - a student
 * links an eportfolio item to one or more competencies via exaport's
 * own item.php (block_exaport_do_add()/block_exaport_do_edit()),
 * which writes directly to that table with NO Moodle event fired at
 * all - there's even a commented-out \block_exaport\event\item_created
 * block sitting right above one of the two insert points, never
 * finished/enabled. \block_exacomp\event\example_submitted (the
 * original hook) is a real event, but it belongs to exacomp's separate
 * "Examples" sub-feature (block_exacompexamples) and isn't fired by
 * this flow at all.
 *
 * Polling avoids touching exaport's or exacomp's own code (both are
 * third-party plugins on this site, upgraded independently of this
 * one) at the cost of near-real-time rather than instant delivery -
 * confirmed acceptable trade-off, client's choice over patching
 * exaport's item.php directly.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_nvq_matrix\task;

defined('MOODLE_INTERNAL') || die();

class notify_assessors_task extends \core\task\scheduled_task {

    /**
     * Config key (block_nvq_matrix) holding the highest
     * block_exacompcompuser_mm.id already processed by this task. Using
     * the row's own auto-increment id as the watermark rather than a
     * timestamp - checked exaport's insert_record() calls directly and
     * confirmed the 'timestamp' column on that table is never actually
     * populated by exaport's write path (NULL for every row this task
     * cares about), so a time-based watermark would never advance.
     */
    const CONFIG_LASTID = 'assessor_notify_lastid';

    public function get_name() {
        return get_string('tasknotifyassessors', 'block_nvq_matrix');
    }

    public function execute() {
        global $DB;

        // block_exacompcompuser_mm may not exist if exacomp isn't
        // installed on this site at all - this plugin depends on it,
        // but a scheduled task failing loudly every run on a
        // mis-provisioned site is worse than it just quietly no-op'ing.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_exacompcompuser_mm') || !$dbman->table_exists('block_exaportitem')) {
            return;
        }

        $lastid = (int) get_config('block_nvq_matrix', self::CONFIG_LASTID);

        // Current max id, fetched once up front and used both as the
        // scan's upper bound and the new watermark - rows inserted
        // *during* this run (after this SELECT) are deliberately left
        // for the next run rather than risking a race where a row gets
        // included in the scan but its watermark update is skipped.
        $maxid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {block_exacompcompuser_mm}');

        if ($maxid <= $lastid) {
            // Nothing new since last run - including the very first
            // run on a fresh install, where $lastid defaults to 0: see
            // the $lastid === 0 handling below, which only applies when
            // there ARE new rows to consider.
            return;
        }

        if ($lastid === 0) {
            // First-ever run (config not set yet). Recording the
            // current max id as the baseline WITHOUT notifying for
            // anything - otherwise every historical evidence link ever
            // made on this site would fire an assessor notification the
            // moment this task first runs, which would be a startling
            // flood rather than the "new submission" signal this
            // feature is meant to provide.
            set_config(self::CONFIG_LASTID, $maxid, 'block_nvq_matrix');
            return;
        }

        // eportfolioitem=1: an eportfolio item link, not a course-module
        // competency link (comptype-agnostic - matrix_data's own read
        // query doesn't filter on comptype either, see get_evidence()).
        // role=0: exaport's item.php hardcodes role=>0 on every insert
        // it makes to this table (both do_add() and do_edit()) - this
        // is exaport's own convention for "the item owner (student)
        // made this link themselves", as opposed to role=1 used
        // elsewhere in exacomp for teacher-made assignments. Filtering
        // on it here is what keeps this task specifically to
        // student-driven submissions, matching the original "when a
        // student makes a submission" requirement, rather than firing
        // for every write to this shared table regardless of origin.
        $sql = "SELECT mm.id, mm.activityid, mm.userid
                  FROM {block_exacompcompuser_mm} mm
                 WHERE mm.id > :lastid
                   AND mm.id <= :maxid
                   AND mm.eportfolioitem = 1
                   AND mm.role = 0
              ORDER BY mm.id ASC";
        $rows = $DB->get_records_sql($sql, ['lastid' => $lastid, 'maxid' => $maxid]);

        // block_nvq_matrix/renotifyonedit (settings.php): default 1
        // (on) preserves the plugin's original behaviour - notify on
        // every write, including a same-competency re-edit. When off,
        // block_nvq_matrix_notified_items tracks which items have
        // already fired a notification at least once, so a later
        // re-edit of the SAME item (new mm row ids and all, since
        // exaport's do_edit() deletes+reinserts on every save
        // regardless of whether competencies actually changed) doesn't
        // refire it. That table is only ever touched in this 'off'
        // branch - leaving the setting on its default costs nothing
        // extra.
        //
        // get_config() returns false (not the admin_setting's declared
        // default) if the config row doesn't exist yet - true on every
        // fresh install (install.xml runs no upgrade.php steps, so
        // nothing ever writes this row) and on any upgrading site until
        // an admin actually opens and saves the settings page. Without
        // the explicit false-check below, (bool) false is false, which
        // the task would read as 'once per item' mode - the OPPOSITE of
        // the documented default, silently, for exactly the sites this
        // was meant to leave unchanged. Caught in review before deploy.
        $configvalue = get_config('block_nvq_matrix', 'renotifyonedit');
        $renotifyonedit = ($configvalue === false) ? true : (bool) $configvalue;

        // A single item-save action typically links several
        // competencies at once (one block_exacompcompuser_mm row per
        // competency chosen), which would otherwise fire one
        // notification per competency for what the student experienced
        // as a single submission action. Deduping to one notification
        // per distinct activityid (eportfolio item) seen in this batch.
        $seenactivityids = [];
        foreach ($rows as $row) {
            $activityid = (int) $row->activityid;
            if (isset($seenactivityids[$activityid])) {
                continue;
            }
            $seenactivityids[$activityid] = true;

            if (!$renotifyonedit && $DB->record_exists('block_nvq_matrix_notified_items', ['itemid' => $activityid])) {
                // 'Once per item' mode, and this item already triggered
                // a notification on a previous run - skip it silently,
                // this is the whole point of the setting, not an error.
                continue;
            }

            $item = $DB->get_record('block_exaportitem', ['id' => $activityid], 'id, courseid, userid', IGNORE_MISSING);
            if (!$item) {
                // Item since deleted - nothing sensible to notify about.
                continue;
            }

            // REAL BUG FIXED HERE (v26.6.11): item.courseid - exaport's own
            // denormalized column - was found wrong for 62% of items
            // site-wide in a live audit (187 of 303), stamped with
            // courseid=1 (SITEID/Front Page) instead of the student's real
            // course. Every submission linked this way previously failed
            // to notify anyone, silently - the resulting "no assessor for
            // SITEID" no-op is indistinguishable from a genuinely
            // unconfigured course, so this went unnoticed until directly
            // audited. See matrix_data::resolve_submission_courseid()'s own
            // docblock for the full explanation and the enrolment-filtered
            // topic-chain resolution used to correct it, mirroring the same
            // pattern get_portfolio_links() already uses for the identical
            // underlying reason.
            $courseid = \block_nvq_matrix\matrix_data::resolve_submission_courseid(
                $activityid,
                (int) $item->userid,
                (int) $item->courseid
            );
            if (!$courseid) {
                // Resolution found nothing better AND the raw item.courseid
                // was itself 0 (a pre-course-linking legacy item) - nothing
                // sensible to notify about, same as the original guard here.
                continue;
            }

            // $item->userid (the item's actual owner) is used over
            // $row->userid (the mm row's own userid) as the student -
            // both are set to the same value by exaport's current
            // item.php, but the item's owner is the more semantically
            // correct source of truth for "who submitted this".
            \block_nvq_matrix\matrix_data::notify_assessor_of_submission(
                $courseid,
                (int) $item->userid,
                $activityid
            );

            if (!$renotifyonedit) {
                // Record so this item is skipped on every future run,
                // not just the next one - insert_record() rather than
                // an upsert since the unique itemid index above already
                // guarantees this can't be reached twice for the same
                // item in a way that would collide (the record_exists()
                // check above already skipped it if a row existed).
                $DB->insert_record('block_nvq_matrix_notified_items', (object) [
                    'itemid'       => $activityid,
                    'timenotified' => time(),
                ]);
            }
        }

        set_config(self::CONFIG_LASTID, $maxid, 'block_nvq_matrix');
    }

}
