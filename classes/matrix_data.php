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
 * Shared data-fetching helper for the block_nvq_matrix plugin.
 *
 * Called by both block_nvq_matrix::get_content() and view.php so that
 * all database queries and template-array construction live in one place.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_nvq_matrix;

defined('MOODLE_INTERNAL') || die();

class matrix_data {

    /**
     * Allowed evidence type codes and their display labels, in the order
     * they should appear in the dropdown. Code is what's stored in
     * block_nvq_matrix_evidence_comments.evidencetype; label is what's
     * shown. Used both to build the dropdown and to validate submissions
     * in save_evidence_type().
     */
    const EVIDENCE_TYPES = [
        'APL' => 'Accredited Prior Learning',
        'EoE' => 'Examination of Evidence',
        'NA'  => 'Not Applicable',
        'O'   => 'Observation',
        'P'   => 'Product',
        'PD'  => 'Professional Discussion',
        'Q'   => 'Questioning',
        'RA'  => 'Reflective Account',
        'S'   => 'Simulation',
        'WT'  => 'Witness Testimony',
    ];

    /**
     * Builds the full template data array for the matrix Mustache template.
     *
     * @param int    $studentid        The student whose matrix is being displayed (0 = none selected yet).
     * @param bool   $canviewall       Whether the current user has block/nvq_matrix:viewall.
     * @param string $selectorhtml     Pre-built HTML for the student selector (pass '' for student view).
     * @param bool   $oncoursepage     True when rendering inside a course context.
     * @param int    $courseid         Course ID when on a course page; 0 on the dashboard.
     * @param bool   $cangrade         Whether the current user may submit grades (block/nvq_matrix:grade).
     * @param bool   $cansample        Whether the current user may set sampling status (block/nvq_matrix:sample).
     * @param bool   $caniqacomment      Whether the current user may set the unit-level IQA comment
     *                                   (block/nvq_matrix:iqacomment). There is no separate assessor-comment
     *                                   capability — the existing grade comment (part of :grade) already
     *                                   covers that, per client feedback.
     * @param bool   $canfinalstatus     Whether the current user may set the final Pass/Fail status and
     *                                   trigger the completion notification (block/nvq_matrix:finalstatus).
     * @return array                   Template data ready to pass to render_from_template().
     */
    public static function build(
        int    $studentid,
        bool   $canviewall,
        string $selectorhtml,
        bool   $oncoursepage = false,
        int    $courseid     = 0,
        bool   $cangrade     = false,
        bool   $cansample    = false,
        bool   $caniqacomment      = false,
        bool   $canfinalstatus     = false
    ): array {
        global $DB, $USER;

        // Evidence type is deliberately editable by the student themselves,
        // not just assessor/IQA — client's call: it's the student who knows
        // what a given piece of evidence actually is, and getting it wrong
        // doesn't affect their grade. Everything else in this plugin stays
        // assessor/IQA-only.
        $isownmatrix = ($studentid === (int) $USER->id);

        // Idle state — assessor has not yet chosen a student.
        if ($canviewall && $studentid === 0) {
            return [
                'canviewall'    => $canviewall,
                'issiteadmin'   => is_siteadmin() ? 1 : 0,
                'cangrade'      => $cangrade,
                'cansample'     => $cansample,
                'canfinalstatus' => $canfinalstatus,
                'finalstatusboxes' => [],
                'gradeurl'      => (new \moodle_url('/blocks/nvq_matrix/grade.php'))->out(false),
                'sampleurl'     => (new \moodle_url('/blocks/nvq_matrix/sample.php'))->out(false),
                'unitcommenturl' => (new \moodle_url('/blocks/nvq_matrix/unit_comment.php'))->out(false),
                'historyurl'    => (new \moodle_url('/blocks/nvq_matrix/history.php'))->out(false),
                'evidencetypeurl' => (new \moodle_url('/blocks/nvq_matrix/evidence_type.php'))->out(false),
                'finalstatusurl' => (new \moodle_url('/blocks/nvq_matrix/final_status.php'))->out(false),
                'sesskey'       => sesskey(),
                'selectorhtml'  => $selectorhtml,
                'units'         => [],
                'portfoliolinks' => [],
                'overalltext'   => '',
                'overallpct'    => 0,
                'totalmet'      => 0,
                'totaltotal'    => 0,
                'totalgaps'     => 0,
                'hasanygaps'    => false,
                'assessorprogresstext' => '',
                'assessorprogresspct'  => 0,
                'hasunits'      => false,
                'nostudentdata' => false,
                'nodatastring'  => '',
                'idlestate'     => true,
            ];
        }

        // ----------------------------------------------------------------
        // Resolve topics (Learning Outcomes).
        // ----------------------------------------------------------------
        $topicids = [];

        if ($oncoursepage && $courseid) {
            $topicids = $DB->get_fieldset_select(
                'block_exacompcoutopi_mm',
                'topicid',
                'courseid = :courseid',
                ['courseid' => $courseid]
            );
        } elseif (empty($topicids)) {
            // Only the "no specific course requested" path (a student
            // viewing their own full matrix, or a legacy/idle call with
            // no courseid) falls back to the unscoped, all-courses query.
            // When a specific course WAS requested but genuinely has zero
            // linked topics (competencies not yet mapped for that course,
            // for instance), that must resolve to "no data for this
            // course" below, never silently widen to every course the
            // student is on - the whole point of passing $courseid here.
            $topicids = $DB->get_fieldset_sql("
                SELECT DISTINCT dtm.topicid
                  FROM {block_exacompcompuser_mm} mm
                  JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
                 WHERE mm.userid         = :userid
                   AND mm.eportfolioitem = 1
            ", ['userid' => $studentid]);
        }

        if (empty($topicids)) {
            return [
                'canviewall'    => $canviewall,
                'issiteadmin'   => is_siteadmin() ? 1 : 0,
                'cangrade'      => $cangrade,
                'cansample'     => $cansample,
                'canfinalstatus' => $canfinalstatus,
                'finalstatusboxes' => [],
                'gradeurl'      => (new \moodle_url('/blocks/nvq_matrix/grade.php'))->out(false),
                'sampleurl'     => (new \moodle_url('/blocks/nvq_matrix/sample.php'))->out(false),
                'unitcommenturl' => (new \moodle_url('/blocks/nvq_matrix/unit_comment.php'))->out(false),
                'historyurl'    => (new \moodle_url('/blocks/nvq_matrix/history.php'))->out(false),
                'evidencetypeurl' => (new \moodle_url('/blocks/nvq_matrix/evidence_type.php'))->out(false),
                'finalstatusurl' => (new \moodle_url('/blocks/nvq_matrix/final_status.php'))->out(false),
                'sesskey'       => sesskey(),
                'selectorhtml'  => $selectorhtml,
                'units'         => [],
                'portfoliolinks' => [],
                'overalltext'   => '',
                'overallpct'    => 0,
                'totalmet'      => 0,
                'totaltotal'    => 0,
                'totalgaps'     => 0,
                'hasanygaps'    => false,
                'assessorprogresstext' => '',
                'assessorprogresspct'  => 0,
                'hasunits'      => false,
                'nostudentdata' => true,
                'nodatastring'  => get_string('nodata', 'block_nvq_matrix'),
                'idlestate'     => false,
            ];
        }

        // ----------------------------------------------------------------
        // Fetch topics, course map, descriptors, and evidence.
        // ----------------------------------------------------------------
        list($topicinsql, $topicparams) = $DB->get_in_or_equal($topicids, SQL_PARAMS_NAMED, 'topic');

        // Order topics (units) the same way they were entered into Exabis,
        // not alphabetically by title. Exabis doesn't populate a per-topic
        // "sorting" column on block_exacomptopics for this site's data (it
        // was NULL across every row checked), so there's no dedicated order
        // field to key off — but topic.id is assigned at creation time, so
        // ascending id reproduces entry order. Verified against two live
        // courses before this change: both matched the client's intended
        // unit sequence exactly, with no gaps or out-of-place units.
        $topics = $DB->get_records_select(
            'block_exacomptopics',
            "id $topicinsql",
            $topicparams,
            'id ASC'
        );

        // Build topicid → course fullname map.
        $topiccoursemap = [];
        // Build topicid → courseid map (the specific course this topic is
        // taught in). Used to scope every grade write to the exact course
        // context, so a grade never leaks across courses/competencies even
        // if a topic is technically linked to more than one course.
        // Where a topic maps to multiple courses, the lowest courseid wins
        // deterministically (matches "first assigned course" in practice).
        $topiccourseidmap = [];
        // courseid -> course fullname, deduped, for the final-status boxes
        // at the bottom of the matrix (one box per distinct course this
        // student has units in) — a simple by-product of the same query.
        $courseidnamemap = [];
        $coursemaprs    = $DB->get_recordset_sql("
            SELECT ct.id, ct.topicid, ct.courseid, c.fullname
              FROM {block_exacompcoutopi_mm} ct
              JOIN {course} c ON c.id = ct.courseid
             WHERE ct.topicid $topicinsql
          ORDER BY c.fullname ASC
        ", $topicparams);
        foreach ($coursemaprs as $row) {
            if (!isset($topiccoursemap[$row->topicid])) {
                $topiccoursemap[$row->topicid] = format_string($row->fullname);
            }
            if (!isset($topiccourseidmap[$row->topicid])
                || (int) $row->courseid < $topiccourseidmap[$row->topicid]) {
                $topiccourseidmap[$row->topicid] = (int) $row->courseid;
            }
            if (!isset($courseidnamemap[(int) $row->courseid])) {
                $courseidnamemap[(int) $row->courseid] = format_string($row->fullname);
            }
        }
        $coursemaprs->close();

        // $courseidnamemap above is derived purely from the topic<->course
        // m:m link table, so a unit that is shared across more than one
        // course/pathway (e.g. an optional unit common to two NVQ route
        // variants) would otherwise surface a final-status box for every
        // course that unit is linked to — including ones the student was
        // never actually registered/enrolled on. Scope it down to courses
        // the student has a genuine presence on before it's used to build
        // the final-status boxes below.
        //
        // REAL BUG FIXED HERE: this used to check ACTIVE enrollment only,
        // so the moment a student was archived (unenrolled) from a
        // course, their Pass/Fail status box for it silently disappeared
        // entirely — reported as "pass status in archive does not
        // display but displays when enrolled". An archived student's
        // status row is untouched by unenrollment (confirmed, v26.4.13)
        // exactly like their grade/sampling/comment rows are, so this now
        // also keeps a course if the student has genuine historical
        // presence there — the same four-table signal already trusted
        // for the archived-students feature itself (view.php) — not just
        // current enrollment. This still excludes a course the student
        // was truly never on (the original protective purpose), since
        // presence requires an actual row in one of these tables, not
        // just the unit happening to be linked to that course too.
        if (!empty($courseidnamemap)) {
            $candidatecourseids = array_keys($courseidnamemap);
            $keepcourseids = array_flip(array_keys(enrol_get_users_courses($studentid, true, ['id'])));

            list($fscidsql1, $fscparams1) = $DB->get_in_or_equal($candidatecourseids, SQL_PARAMS_NAMED, 'fscg');
            list($fscidsql2, $fscparams2) = $DB->get_in_or_equal($candidatecourseids, SQL_PARAMS_NAMED, 'fscs');
            list($fscidsql3, $fscparams3) = $DB->get_in_or_equal($candidatecourseids, SQL_PARAMS_NAMED, 'fscc');
            list($fscidsql4, $fscparams4) = $DB->get_in_or_equal($candidatecourseids, SQL_PARAMS_NAMED, 'fscf');
            $presencecourseids = $DB->get_fieldset_sql("
                SELECT DISTINCT courseid FROM (
                    SELECT courseid FROM {block_nvq_matrix_grades} WHERE studentid = :fscsid1 AND courseid $fscidsql1
                    UNION
                    SELECT courseid FROM {block_nvq_matrix_sampling} WHERE studentid = :fscsid2 AND courseid $fscidsql2
                    UNION
                    SELECT courseid FROM {block_nvq_matrix_unit_comments} WHERE studentid = :fscsid3 AND courseid $fscidsql3
                    UNION
                    SELECT courseid FROM {block_nvq_matrix_status} WHERE studentid = :fscsid4 AND courseid $fscidsql4
                ) presence
            ",
                $fscparams1 + ['fscsid1' => $studentid]
                + $fscparams2 + ['fscsid2' => $studentid]
                + $fscparams3 + ['fscsid3' => $studentid]
                + $fscparams4 + ['fscsid4' => $studentid]
            );
            foreach ($presencecourseids as $pcid) {
                $keepcourseids[(int) $pcid] = true;
            }

            $courseidnamemap = array_intersect_key($courseidnamemap, $keepcourseids);
        }

        // ----------------------------------------------------------------
        // Portfolio links (local_nvqportfolio integration — shallow,
        // links-only version; see version.php changelog). local_nvqportfolio
        // is an optional soft dependency: this block never assumes it's
        // installed, and every step is guarded so a site running
        // block_nvq_matrix alone is unaffected.
        //
        // Deliberately NOT built from $topiccourseidmap above: that map's
        // "lowest courseid wins" tiebreak exists to give grading a single
        // deterministic course when a topic is linked to more than one
        // (e.g. a course cloned into a variant, both sharing the same
        // topic bank) — it says nothing about which of those courses this
        // particular student is actually enrolled in. Client-reported bug:
        // students on "ProQual Level 3 Diploma in Engineering Surveying"
        // were shown "Engineering Surveying (Experienced Route)" instead,
        // because that variant happened to have the lower courseid. Portfolio
        // links are resolved fresh here against real enrolment instead.
        // ----------------------------------------------------------------
        $portfoliolinks = self::get_portfolio_links($studentid, $topicids);

        // Fetch all descriptors for these topics.
        $alldescriptors = [];
        $descriptorrs   = $DB->get_recordset_sql("
            SELECT dtm.id, d.id AS descriptorid, d.title, d.sorting, d.parentid, dtm.topicid
              FROM {block_exacompdescrtopic_mm} dtm
              JOIN {block_exacompdescriptors} d ON d.id = dtm.descrid
             WHERE dtm.topicid $topicinsql
        ", $topicparams);
        foreach ($descriptorrs as $row) {
            $rec           = new \stdClass();
            $rec->id       = $row->descriptorid;
            $rec->title    = $row->title;
            $rec->sorting  = $row->sorting;
            $rec->parentid = $row->parentid;
            $rec->topicid  = $row->topicid;
            $alldescriptors[$row->id] = $rec;
        }
        $descriptorrs->close();

        // Sort: by topic, then LO group, then header-before-criteria, then decimal number.
        uasort($alldescriptors, function ($a, $b) {
            if ((int) $a->topicid !== (int) $b->topicid) {
                return (int) $a->topicid - (int) $b->topicid;
            }
            $alo = self::extract_lo_sort($a->title);
            $blo = self::extract_lo_sort($b->title);
            if ($alo['lo'] !== $blo['lo']) {
                return $alo['lo'] <=> $blo['lo'];
            }
            if ($alo['isheader'] !== $blo['isheader']) {
                return $alo['isheader'] ? -1 : 1;
            }
            return $alo['sub'] <=> $blo['sub'];
        });

        // Evidence, indexed by descriptor ID.
        // itemtype and url are fetched so we can build the correct click-through URL
        // per item type: file/note → shared_item.php; link → the item's own url field.
        //
        // mm.id ("mmid" below) is the specific link row between this evidence
        // item and this criterion (descriptor). The same evidence item can be
        // linked to several criteria — each link is its own mm row. Comments
        // are keyed on mmid, not itemid, so a comment written against one
        // occurrence of a file doesn't leak onto every other criterion that
        // same file happens to also be attached to.
        $evidencerows = $DB->get_records_sql("
            SELECT mm.id AS mmid, mm.compid AS descriptorid,
                   i.id AS itemid, i.name AS itemname, i.type AS itemtype, i.url AS itemurl
              FROM {block_exacompcompuser_mm} mm
              JOIN {block_exaportitem} i ON i.id = mm.activityid
             WHERE mm.userid         = :userid
               AND mm.eportfolioitem = 1
          ORDER BY i.name ASC
        ", ['userid' => $studentid]);

        $evidencemap = [];
        foreach ($evidencerows as $row) {
            $did = $row->descriptorid;
            if (!isset($evidencemap[$did])) {
                $evidencemap[$did] = [];
            }
            $evidencemap[$did][$row->mmid] = (object) [
                'itemname' => format_string($row->itemname),
                'itemtype' => $row->itemtype,
                'itemurl'  => $row->itemurl,
                'itemid'   => (int) $row->itemid,
                'mmid'     => (int) $row->mmid,
            ];
        }

        // Userids of everyone who has written any attributed comment
        // anywhere in this matrix (evidence-item, grade, or IQA comments) —
        // collected up front so a single batched fullname() query can
        // resolve all of them at once, below.
        $commentuserids = [];

        // Evidence-item comments — this plugin's own table, keyed by mmid
        // (the specific item↔criterion link), not itemid. See note above.
        // Editable by anyone with :viewall (cangrade); visible read-only to students.
        // Stores the full row (not just the comment text) so the byline
        // (who/when) can be rendered next to the comment, same as the IQA
        // comment already does.
        $commentrows = $DB->get_records('block_nvq_matrix_evidence_comments', ['studentid' => $studentid]);

        // Evidence types, per evidence-comment row - fetched as its own
        // separate query, never SQL-JOINed into the evidence-comment/item
        // query above. A JOIN against a one-to-many child table like this
        // one duplicates the parent row once per matching child row, which
        // desynced the evidence item and its type in an earlier version of
        // this feature; a plain keyed lookup merged in PHP (same pattern
        // already used for block_nvq_matrix_sampling below) can't do that,
        // since the main query is untouched either way.
        $evidencetypesbycommentid = [];
        if (!empty($commentrows)) {
            [$ecinsql, $ecparams] = $DB->get_in_or_equal(array_keys($commentrows));
            $typerows = $DB->get_records_select(
                'block_nvq_matrix_evidence_types',
                "evidencecommentid $ecinsql",
                $ecparams
            );
            foreach ($typerows as $typerow) {
                $evidencetypesbycommentid[(int) $typerow->evidencecommentid][] = $typerow->code;
            }
        }

        $commentmap  = [];
        foreach ($commentrows as $crow) {
            if (empty($crow->mmid)) {
                // Pre-migration row that couldn't be matched to a specific
                // link during upgrade — skip rather than guess which
                // criterion it belonged to.
                continue;
            }
            $commentmap[(int) $crow->mmid] = (object) [
                'comment'       => trim((string) $crow->comment),
                'evidencetypes' => $evidencetypesbycommentid[(int) $crow->id] ?? [],
                'commentedby'   => (int) ($crow->commentedby ?? 0),
                'timemodified'  => (int) ($crow->timemodified ?? 0),
            ];
            if (!empty($crow->commentedby)) {
                $commentuserids[(int) $crow->commentedby] = true;
            }
        }
        $commenturl = (new \moodle_url('/blocks/nvq_matrix/evidence_comment.php'))->out(false);
        $evidencetypeurl = (new \moodle_url('/blocks/nvq_matrix/evidence_type.php'))->out(false);
        $finalstatusurl  = (new \moodle_url('/blocks/nvq_matrix/final_status.php'))->out(false);

        // Unit-level grade map — fetched from this plugin's own table
        // (block_nvq_matrix_grades), never from exacomp's block_exacompcompuser.
        // Keeping this fully separate avoids any collision with exacomp's own
        // evidence-linking behaviour (which auto-seeds a "Competent" row in its
        // own table the moment evidence is uploaded — unrelated to this grade).
        // Keyed by "topicid:courseid" so a grade given in one course never
        // surfaces against the same unit in a different course.
        $graderows = $DB->get_records('block_nvq_matrix_grades', ['studentid' => $studentid]);

        $grademap = [];
        foreach ($graderows as $grow) {
            $key = (int) $grow->topicid . ':' . (int) $grow->courseid;
            $grademap[$key] = $grow;
            if (!empty($grow->gradedby)) {
                $commentuserids[(int) $grow->gradedby] = true;
            }
            // commentedby is tracked separately from gradedby (see v26.2) —
            // in particular it can be the *only* one set (grade cleared,
            // comment left behind) or set to a different user than gradedby
            // (someone else edited just the comment later). Either way it
            // must be resolved too, or the comment's byline silently goes
            // blank even though the comment itself is still showing.
            if (!empty($grow->commentedby)) {
                $commentuserids[(int) $grow->commentedby] = true;
            }
        }

        // Unit-level sampling status map — same isolation principle as grades.
        $samplerows = $DB->get_records('block_nvq_matrix_sampling', ['studentid' => $studentid]);
        $samplemap  = [];
        foreach ($samplerows as $srow) {
            $key = (int) $srow->topicid . ':' . (int) $srow->courseid;
            $samplemap[$key] = $srow;
        }
        $sampleurl = (new \moodle_url('/blocks/nvq_matrix/sample.php'))->out(false);

        // Grade URL base — used by the template for AJAX POST target.
        $gradeurl = (new \moodle_url('/blocks/nvq_matrix/grade.php'))->out(false);

        // Unit-level comment map (assessor comment + IQA comment) — this
        // plugin's own table, decoupled from the grade/sampling verdicts
        // themselves so either comment can be left independently and
        // optionally. Keyed by "topicid:courseid", same isolation principle
        // as grades/sampling.
        $unitcommentrows = $DB->get_records('block_nvq_matrix_unit_comments', ['studentid' => $studentid]);
        $unitcommentmap  = [];
        foreach ($unitcommentrows as $ucrow) {
            $key = (int) $ucrow->topicid . ':' . (int) $ucrow->courseid;
            $unitcommentmap[$key] = $ucrow;
            if (!empty($ucrow->iqacommentby)) {
                $commentuserids[(int) $ucrow->iqacommentby] = true;
            }
        }

        // Resolve commenter names in one batch query — covers evidence-item
        // commenters, grade commenters, and IQA commenters together.
        $commenternames = [];
        if (!empty($commentuserids)) {
            $commenterrs = $DB->get_records_list(
                'user',
                'id',
                array_keys($commentuserids),
                '',
                'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
            );
            foreach ($commenterrs as $u) {
                $commenternames[(int) $u->id] = fullname($u);
            }
        }

        $unitcommenturl = (new \moodle_url('/blocks/nvq_matrix/unit_comment.php'))->out(false);

        // History URL base — used by the on-demand History toggle's AJAX
        // fetch (v26.6.16). Built here alongside the other endpoint URLs
        // for consistency, even though, unlike them, nothing in this
        // build() method writes to history.php - it's purely a read
        // endpoint the client calls independently.
        $historyurl = (new \moodle_url('/blocks/nvq_matrix/history.php'))->out(false);

        // ----------------------------------------------------------------
        // Build template data.
        // ----------------------------------------------------------------
        $totalcriteria = 0;
        $totalmet      = 0;
        $unitsdata     = [];

        foreach ($topics as $topic) {
            $unitcriteria = 0;
            $unitmet      = 0;
            $logroups     = [];
            $loheaders    = [];
            $children     = [];

            foreach ($alldescriptors as $descriptor) {
                if ((int) $descriptor->topicid !== (int) $topic->id) {
                    continue;
                }
                if ((int) $descriptor->parentid === 0) {
                    $loheaders[$descriptor->id] = $descriptor;
                } else {
                    $children[$descriptor->parentid][] = $descriptor;
                }
            }

            foreach ($loheaders as $loid => $loheader) {
                $criteriarows = [];
                foreach ($children[$loid] ?? [] as $criterion) {
                    $unitcriteria++;
                    $totalcriteria++;
                    $items        = $evidencemap[$criterion->id] ?? [];
                    $haseevidence = !empty($items);
                    if ($haseevidence) {
                        $unitmet++;
                        $totalmet++;
                    }
                    $formatteditem = [];
                    foreach ($items as $itemdata) {
                        $url         = self::build_item_url($itemdata, $studentid, $courseid);
                        $commentrow  = $commentmap[$itemdata->mmid] ?? null;
                        $comment     = $commentrow ? $commentrow->comment : '';
                        $formatteditem[] = [
                            'itemname'         => $itemdata->itemname,
                            'itemurl'          => $url,
                            'hasurl'           => $url !== '',
                            'itemid'           => $itemdata->itemid,
                            'mmid'             => $itemdata->mmid,
                            'itemcomment'      => $comment,
                            'hasitemcomment'   => $comment !== '',
                            'itemcommentbyname' => $commentrow
                                ? self::format_comment_byline($commentrow->commentedby, $commentrow->timemodified, $commenternames)
                                : '',
                            'itemcommentdateiso' => self::format_comment_date_iso($commentrow ? $commentrow->timemodified : 0),
                            'cancomment'       => $cangrade,
                            'commenturl'       => $commenturl,
                            'evidencetypes'       => $commentrow ? $commentrow->evidencetypes : [],
                            'hasevidencetype'     => $commentrow ? !empty($commentrow->evidencetypes) : false,
                            'evidencetypelabel'   => self::format_evidence_type_label($commentrow ? $commentrow->evidencetypes : []),
                            'evidencetypeoptions' => self::build_evidence_type_options($commentrow ? $commentrow->evidencetypes : []),
                            'caneditevidencetype' => $cangrade || $caniqacomment || $isownmatrix,
                            'evidencetypeurl'     => $evidencetypeurl,
                            'studentid'        => $studentid,
                        ];
                    }
                    $criteriarows[] = [
                        'criterion'    => format_string($criterion->title),
                        'haseevidence' => $haseevidence,
                        'items'        => $formatteditem,
                        'noevidence'   => get_string('noevidence', 'block_nvq_matrix'),
                    ];
                }
                if (!empty($criteriarows)) {
                    $logroups[] = [
                        'lotitle'  => format_string($loheader->title),
                        'criteria' => $criteriarows,
                    ];
                }
            }

            // Orphaned criteria — LO header not found for this student.
            $accountedchildren = [];
            foreach ($loheaders as $loid => $loheader) {
                foreach ($children[$loid] ?? [] as $c) {
                    $accountedchildren[$c->id] = true;
                }
            }
            $orphanrows = [];
            foreach ($children as $parentid => $childlist) {
                if (isset($loheaders[$parentid])) {
                    continue;
                }
                foreach ($childlist as $criterion) {
                    if (isset($accountedchildren[$criterion->id])) {
                        continue;
                    }
                    $unitcriteria++;
                    $totalcriteria++;
                    $items        = $evidencemap[$criterion->id] ?? [];
                    $haseevidence = !empty($items);
                    if ($haseevidence) {
                        $unitmet++;
                        $totalmet++;
                    }
                    $formatteditem = [];
                    foreach ($items as $itemdata) {
                        $url         = self::build_item_url($itemdata, $studentid, $courseid);
                        $commentrow  = $commentmap[$itemdata->mmid] ?? null;
                        $comment     = $commentrow ? $commentrow->comment : '';
                        $formatteditem[] = [
                            'itemname'         => $itemdata->itemname,
                            'itemurl'          => $url,
                            'hasurl'           => $url !== '',
                            'itemid'           => $itemdata->itemid,
                            'mmid'             => $itemdata->mmid,
                            'itemcomment'      => $comment,
                            'hasitemcomment'   => $comment !== '',
                            'itemcommentbyname' => $commentrow
                                ? self::format_comment_byline($commentrow->commentedby, $commentrow->timemodified, $commenternames)
                                : '',
                            'itemcommentdateiso' => self::format_comment_date_iso($commentrow ? $commentrow->timemodified : 0),
                            'cancomment'       => $cangrade,
                            'commenturl'       => $commenturl,
                            'evidencetypes'       => $commentrow ? $commentrow->evidencetypes : [],
                            'hasevidencetype'     => $commentrow ? !empty($commentrow->evidencetypes) : false,
                            'evidencetypelabel'   => self::format_evidence_type_label($commentrow ? $commentrow->evidencetypes : []),
                            'evidencetypeoptions' => self::build_evidence_type_options($commentrow ? $commentrow->evidencetypes : []),
                            'caneditevidencetype' => $cangrade || $caniqacomment || $isownmatrix,
                            'evidencetypeurl'     => $evidencetypeurl,
                            'studentid'        => $studentid,
                        ];
                    }
                    $orphanrows[] = [
                        'criterion'    => format_string($criterion->title),
                        'haseevidence' => $haseevidence,
                        'items'        => $formatteditem,
                        'noevidence'   => get_string('noevidence', 'block_nvq_matrix'),
                    ];
                }
            }
            if (!empty($orphanrows)) {
                $logroups[] = [
                    'lotitle'  => '',
                    'criteria' => $orphanrows,
                ];
            }

            $progresspct = $unitcriteria > 0 ? round(($unitmet / $unitcriteria) * 100) : 0;

            // Gap analysis: a "gap" is simply a criterion in this unit with
            // no evidence linked yet — the exact inverse of $unitmet, which
            // is already computed correctly above (including the orphan-row
            // pass below). No new query needed; this is pure arithmetic on
            // data already fetched, which keeps gap analysis immune to the
            // list-vs-content scoping bugs this plugin has hit before (see
            // handover §9) since it can never diverge from what the matrix
            // itself already shows as evidenced/not.
            $unitgaps = $unitcriteria - $unitmet;

            if (!empty($logroups)) {
                $unitsdata[] = [
                    'topicid'      => $topic->id,
                    'unittitle'    => format_string($topic->title),
                    // Real bug fixed here (v26.6.4): same root cause as
                    // the v26.6.3 courseid fix just above/below, but a
                    // SEPARATE map ($topiccoursemap, singular "course" -
                    // easy to miss alongside $topiccourseidmap) missed
                    // in that fix. This one feeds the course name label
                    // actually shown on each unit, not a grading action's
                    // courseid - reported live after v26.6.3: a unit
                    // shared between two pathways of the same qualification
                    // (client's "shared competence library, per-pathway
                    // unit selection" design) always displayed the
                    // alphabetically-first course's name (the query above
                    // orders by c.fullname ASC and keeps the first match),
                    // regardless of which course/pathway was actually
                    // being viewed. Use the current course's own name when
                    // on a specific course page - $courseidnamemap is
                    // guaranteed to have an entry for $courseid here, since
                    // it's built from the same recordset and this topic is
                    // only in $unitsdata because it's genuinely linked to
                    // the course being viewed. Only fall back to the
                    // shared/ambiguous map on the unscoped all-courses view,
                    // same condition as the courseid fix.
                    'coursename'   => $oncoursepage
                        ? ($courseidnamemap[$courseid] ?? ($topiccoursemap[$topic->id] ?? ''))
                        : ($topiccoursemap[$topic->id] ?? ''),
                    'logroups'     => $logroups,
                    'progresstext' => get_string('unitprogress', 'block_nvq_matrix', [
                        'met'   => $unitmet,
                        'total' => $unitcriteria,
                    ]),
                    'progresspct'  => $progresspct,
                    'unitmet'      => $unitmet,
                    'unittotal'    => $unitcriteria,
                    'unitgaps'     => $unitgaps,
                    'hasgaps'      => $unitgaps > 0,
                ] + self::build_unit_grade_row(
                    $topic->id,
                    $studentid,
                    // Real bug fixed here (v26.6.3): $topiccourseidmap
                    // deliberately resolves to the LOWEST courseid a
                    // topic is linked to (see its declaration above) -
                    // correct for the final-status dedup it was built
                    // for, but wrong here. When a unit is shared across
                    // more than one course, using that map meant every
                    // grade/sample/comment button silently submitted the
                    // WRONG course's id whenever it differed from the
                    // one actually being viewed - the viewing teacher's
                    // capabilities were then checked against a course
                    // they may have no role in at all, surfacing as a
                    // baffling "no permission to grade" error scoped to
                    // shared units only. When on a specific course page,
                    // use that course's own id directly instead - it's
                    // already known and correct; only fall back to the
                    // map for the unscoped/all-courses view where no
                    // single $courseid exists.
                    $oncoursepage ? $courseid : ($topiccourseidmap[$topic->id] ?? 0),
                    $grademap,
                    $cangrade,
                    $gradeurl,
                    $unitmet > 0, // at least one criterion in this unit has evidence
                    $commenternames
                ) + self::build_unit_sampling_row(
                    $topic->id,
                    $studentid,
                    $oncoursepage ? $courseid : ($topiccourseidmap[$topic->id] ?? 0),
                    $samplemap,
                    $cansample,
                    $sampleurl
                ) + self::build_unit_comment_row(
                    $topic->id,
                    $studentid,
                    $oncoursepage ? $courseid : ($topiccourseidmap[$topic->id] ?? 0),
                    $unitcommentmap,
                    $commenternames,
                    $caniqacomment,
                    $unitcommenturl
                );
            }
        }

        $overallpct = $totalcriteria > 0 ? round(($totalmet / $totalcriteria) * 100) : 0;
        $totalgaps  = $totalcriteria - $totalmet;

        // ----------------------------------------------------------------
        // Assessor progress: what share of this student's units have
        // actually been GRADED (a Competent or Not Yet Competent verdict
        // saved via build_unit_grade_row() above), independent of how much
        // evidence has been submitted. Deliberately unit-level, matching
        // this plugin's existing unit-level grading model (v17+) rather
        // than counting individual criteria — "5 units, 4 graded = 80%",
        // not a criterion-count. Loops over $unitsdata (already built
        // above, one entry per unit that actually has content to show) so
        // this can never disagree with what's rendered on the page, and
        // needs no extra DB query since 'gradeisset' is already merged
        // into every row by build_unit_grade_row().
        // ----------------------------------------------------------------
        $totalgradableunits = count($unitsdata);
        $gradedunits         = 0;
        foreach ($unitsdata as $unitrow) {
            if (!empty($unitrow['gradeisset'])) {
                $gradedunits++;
            }
        }
        $assessorprogresspct = $totalgradableunits > 0
            ? round(($gradedunits / $totalgradableunits) * 100)
            : 0;

        // ----------------------------------------------------------------
        // Final Pass/Fail status boxes — one per distinct course this
        // student has units in ($courseidnamemap, built alongside the
        // topic/course maps above). Self-contained name lookup (setby/
        // notifiedby) rather than folding into $commentuserids/
        // $commenternames above, since this runs after those are already
        // resolved and the set of users involved is small and unrelated.
        // ----------------------------------------------------------------
        $finalstatusboxes = [];
        if (!empty($courseidnamemap)) {
            $statusrows = $DB->get_records('block_nvq_matrix_status', ['studentid' => $studentid]);
            $statusmap  = [];
            $statususerids = [];
            foreach ($statusrows as $srow) {
                $statusmap[(int) $srow->courseid] = $srow;
                if (!empty($srow->setby)) {
                    $statususerids[(int) $srow->setby] = true;
                }
                if (!empty($srow->notifiedby)) {
                    $statususerids[(int) $srow->notifiedby] = true;
                }
            }
            $statusnames = [];
            if (!empty($statususerids)) {
                $statususerrs = $DB->get_records_list(
                    'user',
                    'id',
                    array_keys($statususerids),
                    '',
                    'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
                );
                foreach ($statususerrs as $u) {
                    $statusnames[(int) $u->id] = fullname($u);
                }
            }

            // Resolved once here (not per-course-box) purely for the
            // client-side "Send to {name} for {course}?" confirmation text.
            $studentrec  = $DB->get_record('user', ['id' => $studentid], 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename');
            $studentname = $studentrec ? fullname($studentrec) : '';

            foreach ($courseidnamemap as $cid => $cname) {
                $srow = $statusmap[$cid] ?? null;
                $finalstatusboxes[] = self::build_final_status_box(
                    $cid,
                    $cname,
                    $studentid,
                    $studentname,
                    $srow,
                    $statusnames,
                    $canfinalstatus,
                    $finalstatusurl
                );
            }
        }

        return [
            'canviewall'    => $canviewall,
                'issiteadmin'   => is_siteadmin() ? 1 : 0,
            'cangrade'      => $cangrade,
            'cansample'     => $cansample,
            'caniqacomment'      => $caniqacomment,
            'canfinalstatus' => $canfinalstatus,
            // Governs whether the single global "Edit matrix" toggle
            // renders at all — no point showing it to someone who can't
            // edit any comment or evidence type on this matrix (e.g. an
            // :viewall user looking at someone else's record). Mirrors the
            // same three flags each individual comment/evidence-type field
            // already gates on ({{#cancomment}}, {{#caniqacomment}}, and
            // isownmatrix for evidence type only).
            'caneditanything' => ($cangrade || $caniqacomment || $isownmatrix),
            'finalstatusboxes' => $finalstatusboxes,
            'gradeurl'      => $gradeurl,
            'sampleurl'     => $sampleurl,
            'unitcommenturl' => $unitcommenturl,
            'historyurl'    => $historyurl,
            'evidencetypeurl' => $evidencetypeurl,
            'finalstatusurl' => $finalstatusurl,
            'sesskey'       => sesskey(),
            'selectorhtml'  => $selectorhtml,
            'units'         => $unitsdata,
            'portfoliolinks' => $portfoliolinks,
            'overalltext'   => get_string('overallprogress', 'block_nvq_matrix', [
                'met'   => $totalmet,
                'total' => $totalcriteria,
            ]),
            'overallpct'    => $overallpct,
            'totalmet'      => $totalmet,
            'totaltotal'    => $totalcriteria,
            'totalgaps'     => $totalgaps,
            'hasanygaps'    => $totalgaps > 0,
            'assessorprogresstext' => get_string('assessorprogress', 'block_nvq_matrix', [
                'graded' => $gradedunits,
                'total'  => $totalgradableunits,
            ]),
            'assessorprogresspct'  => $assessorprogresspct,
            'hasunits'      => !empty($unitsdata),
            'nostudentdata' => false,
            'nodatastring'  => '',
            'idlestate'     => false,
        ];
    }

    /**
     * Builds the template data for a single course's final-status box.
     *
     * @param int         $courseid       The course this box is for.
     * @param string      $coursename     Already-format_string()'d course name.
     * @param int         $studentid      The student this box is for.
     * @param string      $studentname    Student's fullname, for the notify-confirmation text.
     * @param object|null $statusrow      Row from block_nvq_matrix_status, or null if never set.
     * @param array       $statusnames    Map of userid → fullname for setby/notifiedby.
     * @param bool        $canfinalstatus Whether the current user may set status / notify.
     * @param string      $finalstatusurl URL of final_status.php.
     * @return array
     */
    private static function build_final_status_box(
        int     $courseid,
        string  $coursename,
        int     $studentid,
        string  $studentname,
        ?object $statusrow,
        array   $statusnames,
        bool    $canfinalstatus,
        string  $finalstatusurl
    ): array {
        $statusisset = ($statusrow !== null && $statusrow->status !== null && $statusrow->status !== '');
        $ispass      = $statusisset && (int) $statusrow->status === 1;
        $isfail      = $statusisset && (int) $statusrow->status === 0;

        $statustext = get_string('statusnotset', 'block_nvq_matrix');
        if ($ispass) {
            $statustext = get_string('statuspass', 'block_nvq_matrix');
        } else if ($isfail) {
            $statustext = get_string('statusfail', 'block_nvq_matrix');
        }

        $setinfo = '';
        if ($statusisset && !empty($statusrow->setby)) {
            $setbyname = $statusnames[(int) $statusrow->setby] ?? '';
            $setdate   = $statusrow->timemodified ? userdate((int) $statusrow->timemodified, '%d/%m/%Y') : '';
            if ($setbyname !== '' && $setdate !== '') {
                $setinfo = get_string('statussetby', 'block_nvq_matrix', ['name' => $setbyname, 'date' => $setdate]);
            }
        }

        $hasbeennotified = ($statusrow !== null && !empty($statusrow->notifiedtime));
        $notifiedinfo    = '';
        if ($hasbeennotified) {
            $notifiedbyname = $statusnames[(int) ($statusrow->notifiedby ?? 0)] ?? '';
            $notifieddate   = userdate((int) $statusrow->notifiedtime, '%d/%m/%Y');
            $notifiedinfo   = ($notifiedbyname !== '')
                ? get_string('notifiedbyondate', 'block_nvq_matrix', ['name' => $notifiedbyname, 'date' => $notifieddate])
                : get_string('notifiedondate', 'block_nvq_matrix', ['date' => $notifieddate]);
        }

        // Prefills the backdate input: the date the status currently
        // carries if one is set, otherwise today — same "if it's set, show
        // what it's set to, otherwise default to today" behaviour as the
        // grade/IQA/evidence comment date inputs.
        $setdateiso = self::format_comment_date_iso($statusisset ? (int) $statusrow->timemodified : 0);

        return [
            'courseid'        => $courseid,
            'coursename'      => $coursename,
            'studentid'       => $studentid,
            'canfinalstatus'  => $canfinalstatus,
            'statusisset'     => $statusisset,
            'ispass'          => $ispass,
            'isfail'          => $isfail,
            'statustext'      => $statustext,
            'setinfo'         => $setinfo,
            'hassetinfo'      => ($setinfo !== ''),
            'hasbeennotified' => $hasbeennotified,
            'notifiedinfo'    => $notifiedinfo,
            'setdateiso'      => $setdateiso,
            'canclear'        => ($canfinalstatus && $statusisset),
            'clearconfirm'    => get_string('clearstatusconfirm', 'block_nvq_matrix', [
                'name'    => $studentname,
                'course'  => $coursename,
            ]),
            'notifyconfirm'   => get_string('notifyconfirm', 'block_nvq_matrix', [
                'status'  => $ispass ? get_string('statuspass', 'block_nvq_matrix') : get_string('statusfail', 'block_nvq_matrix'),
                'name'    => $studentname,
                'course'  => $coursename,
            ]),
            'finalstatusurl'  => $finalstatusurl,
            'sesskey'         => sesskey(),
        ];
    }

    /**
     * Sets the final Pass/Fail status for a student on a course. Purely a
     * record of the assessor's decision — does not itself notify the
     * student; see send_completion_notification() for that, triggered
     * separately (and repeatably) once the assessor chooses to.
     *
     * Capability and course-enrolment checks must be performed by the
     * caller (final_status.php) before invoking this method.
     *
     * @param int $studentid
     * @param int $courseid
     * @param int $status 1 = Pass, 0 = Fail.
     * @param int $setdate Manual timestamp for "set by/on" (0 = use now()).
     *                      Same backdating pattern as the grade/IQA/evidence
     *                      comment dates (see parse_comment_date()) — for
     *                      recording a result for a student who genuinely
     *                      completed before this feature existed, without
     *                      the byline showing the day it was retroactively
     *                      entered.
     * @return void
     * @throws \dml_exception
     */
    /**
     * Snapshots a live row into its companion history table, immediately
     * before it's about to be overwritten. Called as the very first thing
     * inside every save_*() method's "if ($existing)" branch, before any
     * field on $oldrecord is mutated - the whole point is capturing the
     * state as it stood BEFORE this change.
     *
     * Added v26.6.13 - see version.php's changelog entry for the full
     * rationale. Never updates a history row, only ever inserts - a
     * genuine append-only audit log. $fields lists which properties of
     * $oldrecord to copy across (liverowid and archivedtime are always
     * added automatically, not listed by the caller).
     *
     * @param string $historytable
     * @param object $oldrecord The live row as it stood before this change.
     * @param array $fields Property names to copy from $oldrecord.
     * @return void
     */
    /**
     * Decides what archivedtime a history snapshot should use: the SAME
     * date already being submitted for this save's own timemodified/
     * commenttime, if one was submitted - otherwise the real current
     * time.
     *
     * Simplified here (v26.6.21) - a previous version of this method
     * (v26.6.17-20) gated backdating behind a settings-page "Migration
     * mode" toggle and/or a per-save checkbox, both restricted to
     * Assessor/Manager/admin. Client decision (2026-09-08): that was
     * more complexity than needed and wasn't behaving as expected in
     * practice - simplified to a flat rule with no gating at all: a
     * backdated date always backdates the audit trail, for anyone who
     * can save this field in the first place. The separate
     * "archivedtime shown alongside a real edit timestamp" concept was
     * removed from the History display at the same time (see
     * matrix.mustache) - this plugin no longer tries to distinguish
     * "what the assessor typed" from "when the edit really happened";
     * the audit trail now simply shows the former.
     *
     * @param int $submitteddate A parsed date (from
     *                            parse_comment_date()), or 0 if none was
     *                            submitted.
     * @return int
     */
    private static function resolve_archivedtime(int $submitteddate): int {
        return $submitteddate > 0 ? $submitteddate : time();
    }

    private static function snapshot_history(string $historytable, object $oldrecord, array $fields, int $archivedtime): void {
        global $DB;

        $data = (object) [
            'liverowid'    => $oldrecord->id,
            'archivedtime' => $archivedtime,
        ];
        foreach ($fields as $field) {
            $data->$field = $oldrecord->$field ?? null;
        }
        $DB->insert_record($historytable, $data);
    }

    /**
     * Snapshots EVERY grade/sampling/unit_comment/status row for a
     * student+course into their respective history tables, without
     * touching the live rows at all - called by delete_archived.php
     * immediately before it permanently deletes this exact data, inside
     * the same transaction, so the snapshot inserts roll back too if the
     * delete itself fails partway through.
     *
     * Added v26.6.13 alongside the rest of the audit trail feature -
     * arguably the single most important place for it: delete_archived.php
     * is explicitly documented elsewhere in this plugin as irreversible,
     * so without this, the moment of permanent deletion would be the one
     * place the whole audit trail goes silent right when it matters most.
     * Unlike the save_*() methods, which snapshot exactly one row each
     * because only one can ever be "current" per student/topic/course,
     * this snapshots EVERY topic's row at once (a student can have many
     * graded/sampled/commented units in one course), which is why this is
     * its own method rather than reusing snapshot_history() directly in a
     * loop from the caller.
     *
     * @param int $studentid
     * @param int $courseid
     * @return void
     */
    public static function snapshot_all_before_permanent_delete(int $studentid, int $courseid): void {
        global $DB;

        // Always real-time here, regardless of Migration mode - a
        // permanent deletion has no "submitted date" concept to backdate
        // against (unlike a save/edit), so resolve_archivedtime() is
        // never called for this method.
        $now = time();
        $params = ['studentid' => $studentid, 'courseid' => $courseid];

        foreach ($DB->get_records('block_nvq_matrix_grades', $params) as $row) {
            self::snapshot_history('block_nvq_matrix_grades_history', $row, [
                'studentid', 'topicid', 'courseid', 'value', 'comment',
                'gradedby', 'timemodified', 'commentedby', 'commenttime',
            ], $now);
        }

        foreach ($DB->get_records('block_nvq_matrix_sampling', $params) as $row) {
            self::snapshot_history('block_nvq_matrix_sampling_history', $row, [
                'studentid', 'topicid', 'courseid', 'status', 'sampledby', 'timemodified',
            ], $now);
        }

        foreach ($DB->get_records('block_nvq_matrix_unit_comments', $params) as $row) {
            self::snapshot_history('block_nvq_matrix_unit_comments_history', $row, [
                'studentid', 'topicid', 'courseid',
                'assessorcomment', 'assessorcommentby', 'assessorcommenttime',
                'iqacomment', 'iqacommentby', 'iqacommenttime',
            ], $now);
        }

        foreach ($DB->get_records('block_nvq_matrix_status', $params) as $row) {
            self::snapshot_history('block_nvq_matrix_status_history', $row, [
                'studentid', 'courseid', 'status', 'setby', 'timemodified', 'notifiedtime', 'notifiedby',
            ], $now);
        }
    }

    public static function save_final_status(int $studentid, int $courseid, int $status, int $setdate = 0): void {
        global $DB, $USER;

        $now         = time();
        $timemodified = $setdate > 0 ? $setdate : $now;

        $existing = $DB->get_record('block_nvq_matrix_status', [
            'studentid' => $studentid,
            'courseid'  => $courseid,
        ]);

        if ($existing) {
            self::snapshot_history('block_nvq_matrix_status_history', $existing, [
                'studentid', 'courseid', 'status', 'setby', 'timemodified', 'notifiedtime', 'notifiedby',
            ], self::resolve_archivedtime($setdate));
            $existing->status       = $status;
            $existing->setby        = $USER->id;
            $existing->timemodified = $timemodified;
            $DB->update_record('block_nvq_matrix_status', $existing);
        } else {
            $DB->insert_record('block_nvq_matrix_status', (object) [
                'studentid'    => $studentid,
                'courseid'     => $courseid,
                'status'       => $status,
                'setby'        => $USER->id,
                'timemodified' => $timemodified,
            ]);
        }
    }

    /**
     * Clears a previously-set final Pass/Fail status, deleting the row
     * entirely (unlike clear_grade(), there's no separate "comment" on this
     * record worth preserving, so a clear here is a full reset back to
     * "Not yet set" — including wiping any notifiedby/notifiedtime, since a
     * cleared status is presumed to have been set in error and any
     * notification sent alongside it is stale too).
     *
     * Capability and course-enrolment checks must be performed by the
     * caller (final_status.php) before invoking this method.
     *
     * @param int $studentid
     * @param int $courseid
     * @return void
     * @throws \dml_exception
     */
    public static function clear_final_status(int $studentid, int $courseid): void {
        global $DB;

        $existing = $DB->get_record('block_nvq_matrix_status', [
            'studentid' => $studentid,
            'courseid'  => $courseid,
        ]);

        if ($existing) {
            self::snapshot_history('block_nvq_matrix_status_history', $existing, [
                'studentid', 'courseid', 'status', 'setby', 'timemodified', 'notifiedtime', 'notifiedby',
            ], time());
        }

        $DB->delete_records('block_nvq_matrix_status', [
            'studentid' => $studentid,
            'courseid'  => $courseid,
        ]);
    }

    /**
     * Sends the completion notification to the student via Moodle's own
     * messaging API (see db/messages.php — 'coursecomplete' provider), and
     * records who sent it and when.
     *
     * Deliberately unconstrained: does not check whether every unit is
     * graded/sampled/IQA'd, and can be called any number of times (each
     * call just updates notifiedtime/notifiedby to the latest) — the
     * client asked for no workflow gating here, the confirmation prompt
     * client-side is the only safeguard. The one thing this DOES require
     * is that a status has actually been set — there's nothing sensible to
     * tell the student otherwise.
     *
     * Capability and course-enrolment checks must be performed by the
     * caller (final_status.php) before invoking this method.
     *
     * @param int $studentid
     * @param int $courseid
     * @return void
     * @throws \moodle_exception If no status has been set for this student/course yet.
     * @throws \dml_exception
     */
    public static function send_completion_notification(int $studentid, int $courseid): void {
        global $DB, $USER;

        $statusrow = $DB->get_record('block_nvq_matrix_status', [
            'studentid' => $studentid,
            'courseid'  => $courseid,
        ]);

        if (!$statusrow || $statusrow->status === null || $statusrow->status === '') {
            throw new \moodle_exception('nostatusset', 'block_nvq_matrix');
        }

        $course  = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

        $coursename = format_string($course->fullname);
        $ispass     = ((int) $statusrow->status === 1);

        if ($ispass) {
            $subject = get_string('completionsubjectpass', 'block_nvq_matrix', $coursename);
            $body    = get_string('completionbodypass', 'block_nvq_matrix', [
                'name'   => $student->firstname,
                'course' => $coursename,
            ]);
        } else {
            $subject = get_string('completionsubjectfail', 'block_nvq_matrix', $coursename);
            $body    = get_string('completionbodyfail', 'block_nvq_matrix', [
                'name'   => $student->firstname,
                'course' => $coursename,
            ]);
        }

        $contexturl = new \moodle_url('/blocks/nvq_matrix/view.php');

        $message = new \core\message\message();
        $message->component        = 'block_nvq_matrix';
        $message->name              = 'coursecomplete';
        $message->userfrom           = $USER;
        $message->userto             = $student;
        $message->subject            = $subject;
        $message->fullmessage        = html_to_text($body);
        $message->fullmessageformat  = FORMAT_HTML;
        $message->fullmessagehtml    = $body;
        $message->smallmessage       = $subject;
        $message->notification       = 1;
        $message->contexturl         = $contexturl->out(false);
        $message->contexturlname     = get_string('viewyourmatrix', 'block_nvq_matrix');

        message_send($message);

        self::snapshot_history('block_nvq_matrix_status_history', $statusrow, [
            'studentid', 'courseid', 'status', 'setby', 'timemodified', 'notifiedtime', 'notifiedby',
        ], time());
        $statusrow->notifiedtime = time();
        $statusrow->notifiedby   = $USER->id;
        $DB->update_record('block_nvq_matrix_status', $statusrow);
    }

    /**
     * Users eligible to be designated Assessor for a course: anyone
     * currently enrolled who holds block/nvq_matrix:grade there
     * (editingteacher/manager on this site - see db/access.php). The
     * dropdown is deliberately restricted to this list rather than any
     * enrolled user or any site user, per client confirmation that the
     * assessor must actually be one of the course's teachers.
     *
     * @param int $courseid
     * @return array userid => user record (id, fullname fields), ordered by name.
     */
    public static function get_candidate_assessors(int $courseid): array {
        $context = \context_course::instance($courseid);
        $namefields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic,
                       u.lastnamephonetic, u.middlename, u.alternatename';
        return get_enrolled_users(
            $context,
            'block/nvq_matrix:grade',
            0,
            $namefields,
            'u.lastname ASC, u.firstname ASC'
        );
    }

    /**
     * The currently designated Assessor for a course, if any.
     *
     * @param int $courseid
     * @return \stdClass|null The block_nvq_matrix_assessor row, or null if never set.
     */
    public static function get_assessor(int $courseid): ?\stdClass {
        global $DB;
        $row = $DB->get_record('block_nvq_matrix_assessor', ['courseid' => $courseid]);
        return $row ?: null;
    }

    /**
     * Clears the designated Assessor for a course (dropdown "Not set"
     * option). Separate from save_assessor() rather than overloading
     * userid=0 through it - deleting a row and upserting one are
     * different operations, and save_assessor()'s own capability check
     * (has_capability(':grade', ..., $userid)) would need a special-case
     * carve-out for userid 0 otherwise.
     *
     * Capability check (block/nvq_matrix:manageassessor) must be
     * performed by the caller (assessor.php), same as save_assessor().
     *
     * @param int $courseid
     * @return void
     */
    public static function clear_assessor(int $courseid): void {
        global $DB;
        $DB->delete_records('block_nvq_matrix_assessor', ['courseid' => $courseid]);
    }

    /**
     * Sets (or changes) the designated Assessor for a course. Upserts on
     * the unique courseid index - one assessor per course.
     *
     * Capability check (block/nvq_matrix:manageassessor) must be
     * performed by the caller (assessor.php) before invoking this, same
     * pattern as save_final_status().
     *
     * @param int $courseid
     * @param int $userid Must currently hold block/nvq_matrix:grade in this course.
     * @return void
     * @throws \moodle_exception if $userid isn't a valid candidate.
     */
    public static function save_assessor(int $courseid, int $userid): void {
        global $DB, $USER;

        $context = \context_course::instance($courseid);
        if (!has_capability('block/nvq_matrix:grade', $context, $userid)) {
            // Guards against a stale dropdown submission (e.g. the chosen
            // user was unenrolled or lost the grade capability between
            // page load and form submit) reaching the DB - the assessor
            // must actually be a valid candidate at save time, not just
            // at render time.
            throw new \moodle_exception('invalidassessor', 'block_nvq_matrix');
        }

        $existing = $DB->get_record('block_nvq_matrix_assessor', ['courseid' => $courseid]);
        $now = time();
        if ($existing) {
            $existing->userid       = $userid;
            $existing->setby        = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('block_nvq_matrix_assessor', $existing);
        } else {
            $DB->insert_record('block_nvq_matrix_assessor', (object) [
                'courseid'     => $courseid,
                'userid'       => $userid,
                'setby'        => $USER->id,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Resolves the correct course for a specific eportfolio item submission,
     * for use by notify_assessors_task before it calls
     * notify_assessor_of_submission() below.
     *
     * REAL BUG FIXED HERE (v26.6.11): block_exaportitem.courseid - exaport's
     * own denormalized column, set at item-link time, never written by this
     * plugin - was found wrong for 62% of items site-wide in a live audit
     * (187 of 303), stamped with courseid=1 (SITEID / the Front Page
     * "course", which structurally can never have an NVQ assessor or
     * competence topics) instead of the student's real course. Best
     * explanation found: exaport records whatever $COURSE->id is active at
     * link time, which defaults to SITEID when an item is linked from
     * outside a specific course's own context (e.g. a generic "My
     * ePortfolio" area rather than the course's own Competence Grid page) -
     * not confirmed against exaport's own source, but consistent with every
     * case checked live. notify_assessors_task previously trusted this
     * column outright, so every submission linked this way silently failed
     * to notify anyone: no error, just the normal "no assessor row for this
     * course" no-op get_assessor() already returns for a genuinely
     * unconfigured course - SITEID always hits that same no-op path,
     * indistinguishable from the ordinary case.
     *
     * Mirrors the same enrolment-filtered topic-chain resolution
     * get_portfolio_links() already uses, for the same underlying reason: a
     * topic/competency CAN legitimately be linked to more than one course
     * (confirmed live - though the client is actively giving each course
     * its own independent competence set via a separate import/export tool,
     * so this particular ambiguity should shrink over time rather than
     * grow), so a topic link alone doesn't say which course a given
     * student's submission actually belongs to - only which course(s) the
     * student is actually enrolled in, intersected with the topic's real
     * links, can answer that.
     *
     * Falls back to $fallbackcourseid (the raw, often-wrong item.courseid)
     * only when resolution finds nothing better - this can genuinely
     * happen (an item not yet linked to any competency, or linked to a
     * topic not mapped to any course the student is enrolled in), and in
     * that case the original value is still the best information
     * available, wrong as it may often be.
     *
     * @param int $itemid The block_exaportitem.id being submitted.
     * @param int $studentid The item's owner (block_exaportitem.userid).
     * @param int $fallbackcourseid The raw item.courseid, used only if resolution finds nothing.
     * @return int
     */
    public static function resolve_submission_courseid(int $itemid, int $studentid, int $fallbackcourseid): int {
        global $DB;

        $candidates = $DB->get_fieldset_sql("
            SELECT DISTINCT ct.courseid
              FROM {block_exacompcompuser_mm} mm
              JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
              JOIN {block_exacompcoutopi_mm} ct ON ct.topicid = dtm.topicid
             WHERE mm.activityid = :itemid
               AND mm.userid = :userid
               AND mm.eportfolioitem = 1
        ", ['itemid' => $itemid, 'userid' => $studentid]);

        if (empty($candidates)) {
            return $fallbackcourseid;
        }

        // Filter to courses this student is genuinely enrolled in - same
        // protective filter get_portfolio_links() already applies, for the
        // same reason: a topic being linked to a course says nothing about
        // whether THIS student is actually on it.
        $enrolledids = array_keys(enrol_get_users_courses($studentid, true, ['id']));
        $validcandidates = array_values(array_intersect($candidates, $enrolledids));

        if (empty($validcandidates)) {
            return $fallbackcourseid;
        }

        if (count($validcandidates) === 1) {
            return (int) $validcandidates[0];
        }

        // Still genuinely ambiguous (student enrolled in more than one
        // course sharing this exact topic) - same deterministic "lowest
        // courseid wins" tiebreak already used elsewhere in this plugin
        // (matrix_data::build()'s $topiccourseidmap) purely for
        // consistency with that established convention, even though it's
        // already flagged elsewhere in this file as an imperfect
        // tiebreak. Still a better bet than trusting item.courseid, which
        // this whole method exists because of.
        //
        // Cast to int before sorting - $validcandidates holds raw DB
        // fieldset values (strings), and while PHP's default sort()
        // usually numeric-sorts numeric-looking strings correctly, that's
        // not guaranteed behaviour to lean on for a tiebreak that needs to
        // be deterministic.
        $validcandidates = array_map('intval', $validcandidates);
        sort($validcandidates);
        return (int) $validcandidates[0];
    }

    /**
     * Notifies a course's designated Assessor that a student has
     * submitted evidence. Called from
     * classes/task/notify_assessors_task.php, which polls
     * block_exacompcompuser_mm for new student-linked eportfolio items
     * (see that class's own docblock for the full trail: the original
     * event-observer design was wrong - neither exacomp's
     * example_submitted nor competence_assigned fire for this plugin's
     * actual evidence flow, and exaport's own item.php writes to that
     * table with no Moodle event at all).
     *
     * Deliberately mirrors exacomp's own
     * block_exacomp_notify_all_teachers_about_submission() pattern (same
     * core\message\message construction, message_send()) but stays
     * entirely within this plugin's own component/provider
     * ('block_nvq_matrix' / 'assessorsubmission') rather than reusing
     * exacomp's 'submission' provider - keeps this plugin self-contained
     * and not dependent on exacomp's message provider surviving an
     * exacomp upgrade unchanged.
     *
     * Silently does nothing if no assessor has been designated for this
     * course yet - this is a routine "not configured" state (client
     * hasn't set the dropdown), not an error.
     *
     * @param int $courseid
     * @param int $studentid The student who submitted.
     * @param int $itemid block_exaportitem.id for the submitted eportfolio item. Currently unused in the message itself, kept for future use (e.g. a direct link to the item).
     * @return void
     */
    public static function notify_assessor_of_submission(int $courseid, int $studentid, int $itemid): void {
        global $DB;

        $assessorrow = self::get_assessor($courseid);
        if (!$assessorrow) {
            return;
        }

        // Guard against the assessor having since lost :grade (unenrolled,
        // role changed) without the dropdown being explicitly re-saved -
        // same defensive spirit as the check in save_assessor(), just
        // read-side here rather than rejecting the save.
        $context = \context_course::instance($courseid);
        if (!has_capability('block/nvq_matrix:grade', $context, $assessorrow->userid)) {
            return;
        }

        $assessor = $DB->get_record('user', ['id' => $assessorrow->userid], '*', IGNORE_MISSING);
        $student  = $DB->get_record('user', ['id' => $studentid], '*', IGNORE_MISSING);
        $course   = $DB->get_record('course', ['id' => $courseid], 'id, fullname', IGNORE_MISSING);
        if (!$assessor || !$student || !$course) {
            return;
        }

        $coursename = format_string($course->fullname);
        $studentname = fullname($student);

        $subject = get_string('assessorsubmissionsubject', 'block_nvq_matrix', $coursename);
        $body    = get_string('assessorsubmissionbody', 'block_nvq_matrix', [
            'student' => $studentname,
            'course'  => $coursename,
        ]);

        // Real bug fixed here (v26.6.3): view.php explicitly treats a
        // bare nvq_matrix_student param with no matching
        // nvq_matrix_course as unresolved and falls back to the idle
        // "no student selected" state (see its own comments on the
        // combined-view leak this enforces) - so this link was landing
        // assessors on an empty picker screen instead of deep-linking to
        // the student's matrix. Both params are required together.
        $contexturl = new \moodle_url('/blocks/nvq_matrix/view.php', [
            'nvq_matrix_student' => $studentid,
            'nvq_matrix_course'  => $courseid,
        ]);

        $message = new \core\message\message();
        $message->component       = 'block_nvq_matrix';
        $message->name             = 'assessorsubmission';
        $message->userfrom          = $student;
        $message->userto            = $assessor;
        $message->subject           = $subject;
        $message->fullmessage       = html_to_text($body);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml   = $body;
        $message->smallmessage      = $subject;
        $message->notification      = 1;
        $message->contexturl        = $contexturl->out(false);
        $message->contexturlname    = get_string('viewyourmatrix', 'block_nvq_matrix');

        message_send($message);
    }

    /**
     * Builds the grade data keys for a single unit (topic) row.
     *
     * @param int    $topicid       Exacomp topic ID (the "unit" in NVQ terms).
     * @param int    $studentid     Student being graded.
     * @param int    $courseid      The specific course this topic belongs to (0 if unresolved).
     * @param array  $grademap      Map of "topicid:courseid" → grade row from block_nvq_matrix_grades.
     * @param bool   $cangrade      Whether current user may submit grades at all.
     * @param string $gradeurl      URL of grade.php.
     * @param bool   $hasanyevidence Whether at least one criterion in this unit has evidence linked.
     * @param array  $commenternames Map of userid → fullname, pre-resolved for all commenters in this matrix.
     * @return array
     */
    private static function build_unit_grade_row(
        int    $topicid,
        int    $studentid,
        int    $courseid,
        array  $grademap,
        bool   $cangrade,
        string $gradeurl,
        bool   $hasanyevidence,
        array  $commenternames = []
    ): array {
        $key         = $topicid . ':' . $courseid;
        $graderow    = $grademap[$key] ?? null;
        $gradeisset  = ($graderow !== null && $graderow->value !== null && $graderow->value !== '');
        $gradevalue  = $gradeisset ? (int) $graderow->value : 0;

        // Comment is intentionally independent of the verdict: a grade can
        // be cleared (value -> NULL) without touching the comment, and a
        // comment can exist on a row that currently has no verdict at all
        // (e.g. comment left, verdict later cleared for a re-grade). See
        // clear_grade()/save_grade().
        $comment       = $graderow !== null ? trim((string) ($graderow->comment ?? '')) : '';
        $commentedby   = $graderow !== null ? (int) ($graderow->commentedby ?? 0) : 0;
        $commenttime   = $graderow !== null ? (int) ($graderow->commenttime ?? 0) : 0;

        if (!$gradeisset) {
            $gradetext = get_string('notgraded', 'block_nvq_matrix');
        } else if ($gradevalue === 1) {
            $gradetext = get_string('competent', 'block_nvq_matrix');
        } else {
            $gradetext = get_string('notyet', 'block_nvq_matrix');
        }

        return [
            'cangrade'        => $cangrade && $hasanyevidence,
            'gradedisabled'   => $cangrade && !$hasanyevidence,
            'gradevalue'      => $gradevalue,
            'gradeisset'      => $gradeisset,
            'gradecompetent'  => $gradeisset && $gradevalue === 1,
            'gradenotyet'     => $gradeisset && $gradevalue !== 1,
            'gradetext'       => $gradetext,
            'gradecomment'    => $comment,
            'hasgradecomment' => $comment !== '',
            'gradecommentbyname' => ($comment !== '')
                ? self::format_comment_byline($commentedby, $commenttime, $commenternames)
                : '',
            'gradecommentdateiso' => self::format_comment_date_iso($commenttime),
            'topicid'         => $topicid,
            'studentid'       => $studentid,
            'courseid'        => $courseid,
            'gradeurl'        => $gradeurl,
            'sesskey'         => sesskey(),
        ];
    }

    /**
     * Saves a unit-level (topic-level) grade verdict and optional comment,
     * scoped to a specific course. Writes to this plugin's own dedicated
     * table (block_nvq_matrix_grades) — never to exacomp's own tables — so
     * this can never collide with exacomp's evidence-upload auto-seeding or
     * with exacomp's own ECG grid data. Only the NVQ matrix reads this data.
     *
     * Capability, course-ownership, and evidence-presence checks must be
     * performed by the caller (grade.php) before invoking this method.
     *
     * @param int    $topicid   Exacomp topic ID (the unit being graded).
     * @param int    $studentid Student being graded.
     * @param int    $courseid  The specific course this grade belongs to.
     * @param int    $value     1 = Competent, 0 = Not Yet Competent.
     * @param string $comment   Optional assessor comment.
     * @param int    $commentdate Manual timestamp for the comment byline (0 = use now()).
     *                            Ignored when $comment is blank, same "blank clears
     *                            attribution" rule used by save_unit_comment().
     * @return void
     * @throws \dml_exception
     */
    public static function save_grade(
        int    $topicid,
        int    $studentid,
        int    $courseid,
        int    $value,
        string $comment = '',
        int    $commentdate = 0
    ): void {
        global $DB, $USER;

        // REAL BUG FIXED HERE (v26.6.22): timemodified used to be
        // hardcoded to $now unconditionally, in both branches below,
        // regardless of $commentdate - the only backdatable field on
        // this table was commenttime (the comment's own attribution),
        // never timemodified (the verdict's own timestamp). This was
        // invisible before the History feature existed, since nothing
        // displayed timemodified directly - but get_unit_history()
        // reads gradedby/timemodified for its "set by" line, so a
        // backdated grade's history entry always showed the real save
        // time instead of the entered date. Confirmed live on staging
        // (2026-09-08). Now follows $commentdate exactly like
        // commenttime already does - one entered date governs the
        // whole row, not two independently-tracked timestamps that can
        // silently diverge.
        $now     = time();
        $trimmed = trim($comment);

        $existing = $DB->get_record('block_nvq_matrix_grades', [
            'studentid' => $studentid,
            'topicid'   => $topicid,
            'courseid'  => $courseid,
        ]);

        // Comment attribution is tracked independently of the verdict
        // (commentedby/commenttime vs gradedby/timemodified) so clearing
        // the verdict later never touches the comment. A blank comment
        // clears its own attribution, same rule as the unit IQA comment.
        $commentfields = ($trimmed === '')
            ? ['comment' => '', 'commentedby' => null, 'commenttime' => null]
            : [
                'comment'     => $comment,
                'commentedby' => $USER->id,
                'commenttime' => $commentdate > 0 ? $commentdate : $now,
            ];

        if ($existing) {
            self::snapshot_history('block_nvq_matrix_grades_history', $existing, [
                'studentid', 'topicid', 'courseid', 'value', 'comment',
                'gradedby', 'timemodified', 'commentedby', 'commenttime',
            ], self::resolve_archivedtime($commentdate));
            $existing->value        = $value;
            $existing->gradedby     = $USER->id;
            $existing->timemodified = $commentdate > 0 ? $commentdate : $now;
            foreach ($commentfields as $field => $fieldvalue) {
                $existing->$field = $fieldvalue;
            }
            $DB->update_record('block_nvq_matrix_grades', $existing);
        } else {
            $DB->insert_record('block_nvq_matrix_grades', (object) array_merge([
                'studentid'    => $studentid,
                'topicid'      => $topicid,
                'courseid'     => $courseid,
                'value'        => $value,
                'gradedby'     => $USER->id,
                'timemodified' => $commentdate > 0 ? $commentdate : $now,
            ], $commentfields));
        }
    }

    /**
     * Saves only the grade comment, leaving the verdict (value/gradedby/
     * timemodified) completely untouched — used when the comment box is
     * edited with no verdict currently active (e.g. after the grade was
     * cleared), so an assessor can update or backdate a comment without
     * being forced to also pick Competent/Not Yet Competent.
     *
     * If no row exists yet for this student/topic/course, one is created
     * with a NULL verdict — a row can legitimately hold a comment with no
     * grade attached to it at all.
     *
     * Capability and course-ownership checks must be performed by the
     * caller (grade.php) before invoking this method.
     *
     * @param int    $topicid   Exacomp topic ID.
     * @param int    $studentid Student the comment belongs to.
     * @param int    $courseid  The specific course this belongs to.
     * @param string $comment   Comment text (may be empty to clear it).
     * @param int    $commentdate Manual timestamp for the comment byline (0 = use now()).
     * @return void
     * @throws \dml_exception
     */
    public static function save_grade_comment(
        int    $topicid,
        int    $studentid,
        int    $courseid,
        string $comment,
        int    $commentdate = 0
    ): void {
        global $DB, $USER;

        $now     = time();
        $trimmed = trim($comment);

        $commentfields = ($trimmed === '')
            ? ['comment' => '', 'commentedby' => null, 'commenttime' => null]
            : [
                'comment'     => $comment,
                'commentedby' => $USER->id,
                'commenttime' => $commentdate > 0 ? $commentdate : $now,
            ];

        $existing = $DB->get_record('block_nvq_matrix_grades', [
            'studentid' => $studentid,
            'topicid'   => $topicid,
            'courseid'  => $courseid,
        ]);

        if ($existing) {
            self::snapshot_history('block_nvq_matrix_grades_history', $existing, [
                'studentid', 'topicid', 'courseid', 'value', 'comment',
                'gradedby', 'timemodified', 'commentedby', 'commenttime',
            ], self::resolve_archivedtime($commentdate));
            foreach ($commentfields as $field => $fieldvalue) {
                $existing->$field = $fieldvalue;
            }
            $DB->update_record('block_nvq_matrix_grades', $existing);
        } else {
            if ($trimmed === '') {
                // Nothing to save and nothing already there — don't create
                // an empty row just because the comment box was blurred.
                return;
            }
            $DB->insert_record('block_nvq_matrix_grades', (object) array_merge([
                'studentid' => $studentid,
                'topicid'   => $topicid,
                'courseid'  => $courseid,
                'value'     => null,
            ], $commentfields));
        }
    }

    /**
     * Builds the sampling-status data keys for a single unit (topic) row.
     *
     * @param int    $topicid     Exacomp topic ID.
     * @param int    $studentid   Student whose unit is being sampled.
     * @param int    $courseid    The specific course this topic belongs to.
     * @param array  $samplemap   Map of "topicid:courseid" → row from block_nvq_matrix_sampling.
     * @param bool   $cansample   Whether current user may set sampling status (block/nvq_matrix:sample).
     * @param string $sampleurl   URL of sample.php.
     * @return array
     */
    private static function build_unit_sampling_row(
        int    $topicid,
        int    $studentid,
        int    $courseid,
        array  $samplemap,
        bool   $cansample,
        string $sampleurl
    ): array {
        $key       = $topicid . ':' . $courseid;
        $samplerow = $samplemap[$key] ?? null;
        $status    = ($samplerow !== null) ? (int) $samplerow->status : 0;

        // status: 0 = blank, 1 = Sampled, 2 = Not Yet Sampled.
        $statustext = match ($status) {
            1       => get_string('sampled', 'block_nvq_matrix'),
            2       => get_string('notyetsampled', 'block_nvq_matrix'),
            default => '',
        };

        return [
            'cansample'        => $cansample,
            'samplestatus'     => $status,
            'issampled'        => $status === 1,
            'isnotyetsampled'  => $status === 2,
            'isblanksample'    => $status === 0,
            'samplestatustext' => $statustext,
            'sampleurl'        => $sampleurl,
            'sampledateiso'    => self::format_comment_date_iso($samplerow->timemodified ?? 0),
        ];
    }

    /**
     * Saves a unit-level sampling status. Writes to this plugin's own
     * dedicated table (block_nvq_matrix_sampling) — separate from grading,
     * since sampling has its own capability (block/nvq_matrix:sample).
     *
     * Capability and course-ownership checks must be performed by the
     * caller (sample.php) before invoking this method.
     *
     * @param int $topicid   Exacomp topic ID.
     * @param int $studentid Student whose unit is being sampled.
     * @param int $courseid  The specific course this status belongs to.
     * @param int $status    0 = blank, 1 = Sampled, 2 = Not Yet Sampled.
     * @return void
     * @throws \dml_exception
     */
    public static function save_sampling(
        int $topicid,
        int $studentid,
        int $courseid,
        int $status,
        int $sampledate = 0
    ): void {
        global $DB, $USER;

        $now = time();
        $timemodified = $sampledate > 0 ? $sampledate : $now;

        $existing = $DB->get_record('block_nvq_matrix_sampling', [
            'studentid' => $studentid,
            'topicid'   => $topicid,
            'courseid'  => $courseid,
        ]);

        if ($existing) {
            self::snapshot_history('block_nvq_matrix_sampling_history', $existing, [
                'studentid', 'topicid', 'courseid', 'status', 'sampledby', 'timemodified',
            ], self::resolve_archivedtime($sampledate));
            $existing->status       = $status;
            $existing->timemodified = $timemodified;
            $existing->sampledby    = $USER->id;
            $DB->update_record('block_nvq_matrix_sampling', $existing);
        } else {
            $DB->insert_record('block_nvq_matrix_sampling', (object) [
                'studentid'    => $studentid,
                'topicid'      => $topicid,
                'courseid'     => $courseid,
                'status'       => $status,
                'sampledby'    => $USER->id,
                'timemodified' => $timemodified,
            ]);
        }
    }

    /**
     * Builds the unit-level comment data keys (assessor comment + IQA
     * comment) for a single unit (topic) row. Independent of the grade/
     * sampling verdicts — can be set, cleared, or left blank on its own.
     * There is no separate "assessor comment" here — the existing grade
     * comment (part of the grading controls, block/nvq_matrix:grade)
     * already covers that, per client feedback.
     *
     * @param int    $topicid        Exacomp topic ID.
     * @param int    $studentid      Student the unit belongs to.
     * @param int    $courseid       The specific course this topic belongs to.
     * @param array  $commentmap     Map of "topicid:courseid" → row from block_nvq_matrix_unit_comments.
     * @param array  $commenternames Map of userid → fullname, pre-resolved for all commenters in this matrix.
     * @param bool   $caniqacomment      Whether current user may set the IQA comment.
     * @param string $unitcommenturl     URL of unit_comment.php.
     * @return array
     */
    private static function build_unit_comment_row(
        int    $topicid,
        int    $studentid,
        int    $courseid,
        array  $commentmap,
        array  $commenternames,
        bool   $caniqacomment,
        string $unitcommenturl
    ): array {
        $key = $topicid . ':' . $courseid;
        $row = $commentmap[$key] ?? null;

        $iqacomment    = $row ? trim((string) ($row->iqacomment ?? '')) : '';
        $iqacommentby  = $row && !empty($row->iqacommentby) ? (int) $row->iqacommentby : 0;
        $iqacommenttime = $row ? (int) ($row->iqacommenttime ?? 0) : 0;

        return [
            'caniqacomment'         => $caniqacomment,
            'iqacomment'            => $iqacomment,
            'hasiqacomment'         => $iqacomment !== '',
            'iqacommentbyname'      => ($iqacomment !== '')
                ? self::format_comment_byline($iqacommentby, $iqacommenttime, $commenternames)
                : '',
            'iqacommentdateiso'     => self::format_comment_date_iso($iqacommenttime),
            'unitcommenturl'        => $unitcommenturl,
        ];
    }

    /**
     * Saves the optional unit-level IQA comment. Lives in
     * block_nvq_matrix_unit_comments (a table that originally also had an
     * "assessor comment" column pair, removed from the UI per client
     * feedback — the existing grade comment already covers that — but the
     * now-unused assessorcomment/assessorcommentby/assessorcommenttime
     * columns are left in place rather than dropped, since a prior release
     * may already be live with data in them).
     *
     * Capability and course-ownership checks must be performed by the
     * caller (unit_comment.php) before invoking this method.
     *
     * @param int    $topicid   Exacomp topic ID (the unit being commented on).
     * @param int    $studentid Student the unit belongs to.
     * @param int    $courseid  The specific course this unit belongs to.
     * @param string $comment   Comment text (may be empty to clear it).
     * @param int    $commentdate Manual timestamp for the comment byline (0 = use now()).
     *                            Ignored when $comment is blank.
     * @return void
     * @throws \dml_exception
     */
    public static function save_unit_comment(
        int    $topicid,
        int    $studentid,
        int    $courseid,
        string $comment,
        int    $commentdate = 0
    ): void {
        global $DB, $USER;

        $now = time();

        $existing = $DB->get_record('block_nvq_matrix_unit_comments', [
            'studentid' => $studentid,
            'topicid'   => $topicid,
            'courseid'  => $courseid,
        ]);

        $trimmed = trim($comment);

        // When clearing a comment back to blank, also clear who/when — an
        // empty comment shouldn't carry a stale "commented by" attribution
        // forward from whoever last wrote (or cleared) it. This is what
        // caused the empty-box-with-a-name-attached display bug.
        $fields = ($trimmed === '')
            ? [
                'iqacomment'     => '',
                'iqacommentby'   => null,
                'iqacommenttime' => null,
            ]
            : [
                'iqacomment'     => $comment,
                'iqacommentby'   => $USER->id,
                'iqacommenttime' => $commentdate > 0 ? $commentdate : $now,
            ];

        if ($existing) {
            self::snapshot_history('block_nvq_matrix_unit_comments_history', $existing, [
                'studentid', 'topicid', 'courseid',
                'assessorcomment', 'assessorcommentby', 'assessorcommenttime',
                'iqacomment', 'iqacommentby', 'iqacommenttime',
            ], self::resolve_archivedtime($commentdate));
            foreach ($fields as $field => $value) {
                $existing->$field = $value;
            }
            $DB->update_record('block_nvq_matrix_unit_comments', $existing);
        } else {
            $DB->insert_record('block_nvq_matrix_unit_comments', (object) array_merge([
                'studentid' => $studentid,
                'topicid'   => $topicid,
                'courseid'  => $courseid,
            ], $fields));
        }
    }

    /**
     * Saves an optional comment attached to a single evidence item
     * *occurrence* — i.e. one specific item↔criterion link, not the item
     * as a whole. Writes to this plugin's own dedicated table
     * (block_nvq_matrix_evidence_comments) — never to exaport/exacomp tables.
     *
     * Keyed on $mmid (the block_exacompcompuser_mm link row id) rather than
     * the exaport item id, because the same evidence file can be linked to
     * more than one criterion — keying on the item id alone would make one
     * comment appear identically under every criterion that file happens to
     * be attached to.
     *
     * Capability and ownership checks must be performed by the caller
     * (evidence_comment.php) before invoking this method.
     *
     * @param int    $mmid      block_exacompcompuser_mm row id — the specific item↔criterion link.
     * @param int    $itemid    Exaport item ID the comment is attached to (stored for reference only).
     * @param int    $studentid Owner of the evidence item.
     * @param string $comment   Comment text (may be empty to clear it).
     * @param int    $commentdate Manual timestamp for the comment byline (0 = use now()).
     *                            Ignored when $comment is blank.
     * @return void
     * @throws \dml_exception
     */
    public static function save_evidence_comment(
        int    $mmid,
        int    $itemid,
        int    $studentid,
        string $comment,
        int    $commentdate = 0
    ): void {
        global $DB, $USER;

        $now = $commentdate > 0 ? $commentdate : time();

        $existing = $DB->get_record('block_nvq_matrix_evidence_comments', [
            'studentid' => $studentid,
            'mmid'      => $mmid,
        ]);

        if ($existing) {
            $existing->comment      = $comment;
            $existing->itemid       = $itemid;
            $existing->timemodified = $now;
            $existing->commentedby  = $USER->id;
            $DB->update_record('block_nvq_matrix_evidence_comments', $existing);
        } else {
            $DB->insert_record('block_nvq_matrix_evidence_comments', (object) [
                'studentid'    => $studentid,
                'mmid'         => $mmid,
                'itemid'       => $itemid,
                'comment'      => $comment,
                'commentedby'  => $USER->id,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Clears a unit-level grade *verdict*, restoring the unit to
     * "Not graded" — distinct from setting value=0 (Not Yet Competent),
     * which is itself a real verdict. Deliberately does NOT touch the
     * comment or its attribution (commentedby/commenttime): grade and
     * comment are independent fields on the same row, and clearing one
     * must never silently clear the other (client-reported bug, fixed in
     * v26.2 — previously this deleted the whole row, comment included).
     *
     * If the row has no comment either once the verdict is cleared, the
     * row is deleted outright (nothing left worth keeping) — same
     * end-effect as before for the common case, just no longer at the cost
     * of an existing comment.
     *
     * Capability and course-ownership checks must be performed by the
     * caller (grade.php) before invoking this method.
     *
     * @param int $topicid   Exacomp topic ID (the unit being ungraded).
     * @param int $studentid Student whose grade is being cleared.
     * @param int $courseid  The specific course this grade belongs to.
     * @return void
     * @throws \dml_exception
     */
    public static function clear_grade(
        int $topicid,
        int $studentid,
        int $courseid
    ): void {
        global $DB;

        $existing = $DB->get_record('block_nvq_matrix_grades', [
            'studentid' => $studentid,
            'topicid'   => $topicid,
            'courseid'  => $courseid,
        ]);

        if (!$existing) {
            return;
        }

        $hascomment = trim((string) ($existing->comment ?? '')) !== '';

        if (!$hascomment) {
            self::snapshot_history('block_nvq_matrix_grades_history', $existing, [
                'studentid', 'topicid', 'courseid', 'value', 'comment',
                'gradedby', 'timemodified', 'commentedby', 'commenttime',
            ], time());
            $DB->delete_records('block_nvq_matrix_grades', ['id' => $existing->id]);
            return;
        }

        self::snapshot_history('block_nvq_matrix_grades_history', $existing, [
            'studentid', 'topicid', 'courseid', 'value', 'comment',
            'gradedby', 'timemodified', 'commentedby', 'commenttime',
        ], time());
        $existing->value        = null;
        $existing->gradedby     = null;
        $existing->timemodified = null;
        $DB->update_record('block_nvq_matrix_grades', $existing);
    }

    /**
     * Builds the "Name, date" text shown next to an attributed comment
     * (evidence-item comment, grade comment, or IQA comment). Returns ''
     * when there's no resolvable name, so callers can gate display on the
     * result being non-empty rather than duplicating null-checks.
     *
     * Centralised here (rather than formatted separately per comment type)
     * so all three comment types display attribution consistently and so a
     * future field only needs to call this once, rather than re-deriving
     * its own byline logic and risking the editable/read-only asymmetry
     * that caused the v24 IQA-comment bug.
     *
     * Date only, no time-of-day, always shown as DD/MM/YYYY regardless of
     * the site/user's locale (client request) - see parse_comment_date()
     * for why the stored timestamp still carries a time component even
     * though it's never displayed.
     *
     * @param int   $userid         The commenter's userid (0 = none).
     * @param int   $timestamp      Unix timestamp the comment was saved (0 = none).
     * @param array $commenternames Map of userid → fullname, pre-resolved for the whole matrix.
     * @return string               e.g. "Jane Smith, 08/07/2026", or '' if unresolvable.
     */
    /**
     * Returns one unit's full change history for one student - every past
     * grade verdict/comment and IQA comment, most recent first. Sampling
     * history is deliberately excluded here and returned separately by
     * get_sampling_history() below - kept as two methods rather than one
     * combined call so history.php's two actions (grade/comment vs
     * sampling) can be requested independently, matching how the live
     * page itself already treats grading and sampling as separate
     * concerns with separate capability gates (:grade vs :sample).
     *
     * Deliberately does NOT include the current live value - that's
     * already visible on the page itself right above the History
     * toggle; this only ever shows what it USED TO BE. Added v26.6.16
     * alongside history.php, the first user-facing surface for the
     * audit trail built in v26.6.13.
     *
     * @param int $topicid
     * @param int $studentid
     * @param int $courseid
     * @return array{grade: array, unitcomment: array}
     */
    /**
     * REAL BUG FIXED HERE (v26.6.23): resolves which liverowid a history
     * query should scope to, given a live table + matching conditions.
     * If a live row currently exists, scopes to ITS id specifically -
     * never mixes in history from a previously-deleted live row for the
     * same student/topic/course, even though that history correctly
     * remains in the table forever. Confirmed live on staging
     * (2026-09-08): deleting a student's record (delete_archived.php)
     * and later re-creating it (a fresh restore) left two logically
     * separate "generations" of history both matching the same
     * student+topic+course - the History display then showed BOTH
     * generations mixed together with no indication they belonged to
     * different live rows, looking exactly like duplicate or
     * inconsistent entries (two "Competent, 17/07/2026" entries that
     * were genuinely two different rows, from two different
     * generations, that happened to share the same value/date because
     * both originated from the same original data).
     *
     * If NO live row currently exists (e.g. checking an archived
     * student's history - a real, supported use case this endpoint was
     * specifically built for), falls back to the MOST RECENT liverowid
     * that has any history for this student/topic/course - the last
     * generation that ever existed - rather than showing every past
     * generation's history all mixed together.
     *
     * @param string $livetable
     * @param string $historytable
     * @param array $conditions studentid/courseid, plus topicid if applicable.
     * @return int|null The liverowid to scope to, or null if there's no
     *                    history at all for this student/topic/course.
     */
    private static function resolve_current_liverowid(string $livetable, string $historytable, array $conditions): ?int {
        global $DB;

        $live = $DB->get_record($livetable, $conditions);
        if ($live) {
            return (int) $live->id;
        }

        $mostrecent = $DB->get_records($historytable, $conditions, 'archivedtime DESC', 'liverowid', 0, 1);
        if (empty($mostrecent)) {
            return null;
        }
        return (int) reset($mostrecent)->liverowid;
    }

    public static function get_unit_history(int $topicid, int $studentid, int $courseid): array {
        global $DB;

        $params = ['studentid' => $studentid, 'topicid' => $topicid, 'courseid' => $courseid];

        $gradeliverowid = self::resolve_current_liverowid('block_nvq_matrix_grades', 'block_nvq_matrix_grades_history', $params);
        $graderows = $gradeliverowid === null
            ? []
            : $DB->get_records('block_nvq_matrix_grades_history', ['liverowid' => $gradeliverowid], 'archivedtime DESC');

        $unitcommentliverowid = self::resolve_current_liverowid('block_nvq_matrix_unit_comments', 'block_nvq_matrix_unit_comments_history', $params);
        $unitcommentrows = $unitcommentliverowid === null
            ? []
            : $DB->get_records('block_nvq_matrix_unit_comments_history', ['liverowid' => $unitcommentliverowid], 'archivedtime DESC');

        // Batch-fetch every referenced user once, matching this file's
        // own established "avoid N+1" convention used throughout.
        $userids = [];
        foreach ($graderows as $r) {
            $userids[(int) $r->gradedby] = true;
            $userids[(int) $r->commentedby] = true;
        }
        foreach ($unitcommentrows as $r) {
            $userids[(int) $r->iqacommentby] = true;
        }
        unset($userids[0]);
        $usernames = self::get_fullnames_for_ids(array_keys($userids));

        $grade = [];
        foreach ($graderows as $r) {
            $grade[] = (object) [
                'id'           => (int) $r->id,
                'valuetext'    => self::format_grade_value_text($r->value),
                'comment'      => (string) $r->comment,
                'setby'        => $usernames[(int) $r->gradedby] ?? '',
                'settime'      => $r->timemodified ? userdate($r->timemodified, '%d/%m/%Y') : '',
                'commentedby'  => $usernames[(int) $r->commentedby] ?? '',
                'commenttime'  => $r->commenttime ? userdate($r->commenttime, '%d/%m/%Y') : '',
                'archivedtime' => userdate($r->archivedtime, '%d/%m/%Y %H:%M'),
            ];
        }

        $unitcomment = [];
        foreach ($unitcommentrows as $r) {
            // IQA comment only - assessorcomment is legacy/unused (see
            // unit_comment.php's own docblock: the separate
            // assessor-comment path was removed per client feedback),
            // still snapshotted for completeness but not worth surfacing
            // in a history view nobody writes to anymore.
            if (trim((string) $r->iqacomment) === '') {
                continue;
            }
            $unitcomment[] = (object) [
                'id'           => (int) $r->id,
                'comment'      => (string) $r->iqacomment,
                'setby'        => $usernames[(int) $r->iqacommentby] ?? '',
                'settime'      => $r->iqacommenttime ? userdate($r->iqacommenttime, '%d/%m/%Y') : '',
                'archivedtime' => userdate($r->archivedtime, '%d/%m/%Y %H:%M'),
            ];
        }

        return ['grade' => $grade, 'unitcomment' => $unitcomment];
    }

    /**
     * Returns one unit's sampling history for one student, most recent
     * first. Deliberately separate from get_unit_history() - see that
     * method's own docblock for why.
     *
     * @param int $topicid
     * @param int $studentid
     * @param int $courseid
     * @return array
     */
    public static function get_sampling_history(int $topicid, int $studentid, int $courseid): array {
        global $DB;

        $params = ['studentid' => $studentid, 'topicid' => $topicid, 'courseid' => $courseid];
        $liverowid = self::resolve_current_liverowid('block_nvq_matrix_sampling', 'block_nvq_matrix_sampling_history', $params);
        $rows = $liverowid === null
            ? []
            : $DB->get_records('block_nvq_matrix_sampling_history', ['liverowid' => $liverowid], 'archivedtime DESC');

        $userids = [];
        foreach ($rows as $r) {
            $userids[(int) $r->sampledby] = true;
        }
        unset($userids[0]);
        $usernames = self::get_fullnames_for_ids(array_keys($userids));

        $sampling = [];
        foreach ($rows as $r) {
            $sampling[] = (object) [
                'id'           => (int) $r->id,
                'statustext'   => self::format_sampling_status_text($r->status),
                'setby'        => $usernames[(int) $r->sampledby] ?? '',
                'settime'      => $r->timemodified ? userdate($r->timemodified, '%d/%m/%Y') : '',
                'archivedtime' => userdate($r->archivedtime, '%d/%m/%Y %H:%M'),
            ];
        }

        return $sampling;
    }

    /**
     * Returns a course's final Pass/Fail status history for one student,
     * most recent first.
     *
     * @param int $studentid
     * @param int $courseid
     * @return array
     */
    public static function get_status_history(int $studentid, int $courseid): array {
        global $DB;

        $params = ['studentid' => $studentid, 'courseid' => $courseid];
        $liverowid = self::resolve_current_liverowid('block_nvq_matrix_status', 'block_nvq_matrix_status_history', $params);
        $rows = $liverowid === null
            ? []
            : $DB->get_records('block_nvq_matrix_status_history', ['liverowid' => $liverowid], 'archivedtime DESC');

        $userids = [];
        foreach ($rows as $r) {
            $userids[(int) $r->setby] = true;
            $userids[(int) $r->notifiedby] = true;
        }
        unset($userids[0]);
        $usernames = self::get_fullnames_for_ids(array_keys($userids));

        $status = [];
        foreach ($rows as $r) {
            $status[] = (object) [
                'id'           => (int) $r->id,
                'statustext'   => self::format_final_status_text($r->status),
                'setby'        => $usernames[(int) $r->setby] ?? '',
                'settime'      => $r->timemodified ? userdate($r->timemodified, '%d/%m/%Y') : '',
                'notifiedtext' => $r->notifiedtime
                    ? get_string('historynotifiedon', 'block_nvq_matrix', [
                        'name' => $usernames[(int) $r->notifiedby] ?? '',
                        'date' => userdate($r->notifiedtime, '%d/%m/%Y'),
                    ])
                    : '',
                'archivedtime' => userdate($r->archivedtime, '%d/%m/%Y %H:%M'),
            ];
        }

        return $status;
    }

    /**
     * Permanently deletes ONE specific history row. Called only from
     * history.php's action=delete, which itself requires genuine site
     * admin status (is_siteadmin()) - not :viewall, not manager, not
     * any plugin capability - client decision (2026-09-08): editing the
     * audit trail itself is sensitive enough that even a Manager
     * shouldn't be able to do it, only a true site admin. This method
     * itself re-verifies the row genuinely belongs to the claimed
     * student+course before deleting anything, matching this plugin's
     * established "never trust a client-supplied id without
     * re-checking server-side" rule (see delete_archived.php's own
     * docblock for the precedent).
     *
     * $type is a short internal label, not a raw table name - never
     * pass user input directly as a table name.
     *
     * @param string $type 'grade' | 'sampling' | 'unitcomment' | 'status'
     * @param int $historyid The specific history row's own id.
     * @param int $studentid
     * @param int $courseid
     * @return bool True if a row was genuinely found and deleted.
     */
    public static function delete_history_entry(string $type, int $historyid, int $studentid, int $courseid): bool {
        global $DB;

        $tables = [
            'grade'       => 'block_nvq_matrix_grades_history',
            'sampling'    => 'block_nvq_matrix_sampling_history',
            'unitcomment' => 'block_nvq_matrix_unit_comments_history',
            'status'      => 'block_nvq_matrix_status_history',
        ];

        if (!isset($tables[$type])) {
            return false;
        }

        $conditions = ['id' => $historyid, 'studentid' => $studentid, 'courseid' => $courseid];
        if (!$DB->record_exists($tables[$type], $conditions)) {
            return false;
        }

        $DB->delete_records($tables[$type], $conditions);
        return true;
    }

    /**
     * Small shared formatting helpers for the three history methods
     * above - kept private and tiny rather than pulling in whatever
     * client-side label logic the live badges use, since this endpoint
     * returns plain JSON text, not markup with data-attributes.
     */
    private static function format_grade_value_text($value): string {
        if ($value === null || $value === '') {
            return get_string('notgraded', 'block_nvq_matrix');
        }
        return ((int) $value === 1)
            ? get_string('competent', 'block_nvq_matrix')
            : get_string('notyet', 'block_nvq_matrix');
    }

    private static function format_sampling_status_text($status): string {
        $status = (int) $status;
        if ($status === 1) {
            return get_string('sampled', 'block_nvq_matrix');
        }
        if ($status === 2) {
            return get_string('notyetsampled', 'block_nvq_matrix');
        }
        return get_string('historysamplingblank', 'block_nvq_matrix');
    }

    private static function format_final_status_text($status): string {
        if ($status === null || $status === '') {
            return get_string('historystatusnotset', 'block_nvq_matrix');
        }
        return ((int) $status === 1)
            ? get_string('finalstatuspass', 'block_nvq_matrix')
            : get_string('finalstatusfail', 'block_nvq_matrix');
    }

    /**
     * Batch fullname lookup for a list of user ids, matching this file's
     * own "avoid N+1" convention. Returns userid => fullname.
     *
     * @param array $userids
     * @return array
     */
    private static function get_fullnames_for_ids(array $userids): array {
        global $DB;

        $usernames = [];
        if (empty($userids)) {
            return $usernames;
        }
        list($uinsql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'hu');
        $users = $DB->get_records_select(
            'user',
            "id $uinsql",
            $uparams,
            '',
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
        foreach ($users as $u) {
            $usernames[$u->id] = fullname($u);
        }
        return $usernames;
    }

    public static function format_comment_byline(int $userid, int $timestamp, array $commenternames): string {
        if (!$userid || empty($commenternames[$userid])) {
            return '';
        }

        $text = $commenternames[$userid];

        if (!empty($timestamp)) {
            $text .= ', ' . userdate($timestamp, '%d/%m/%Y');
        }

        return $text;
    }

    /**
     * Parses a "YYYY-MM-DD" date string submitted from a comment box's
     * optional date field into a Unix timestamp, so an assessor can
     * backdate a comment to when the work was actually done (e.g.
     * re-grading a portfolio that was genuinely completed weeks ago,
     * without every re-grade comment showing today's date).
     *
     * The submitted date is combined with the *current* time-of-day (in
     * the acting user's own timezone) rather than midnight, purely so the
     * byline still reads as a real timestamp rather than always "00:00" —
     * the date is what the assessor is choosing; the time-of-day is
     * incidental.
     *
     * @param string $datestr Date string, expected "YYYY-MM-DD". Any other
     *                        format (including blank) is treated as "not
     *                        supplied" and returns 0.
     * @return int Unix timestamp, or 0 if $datestr is blank/unparseable —
     *             callers should fall back to time() in that case.
     */
    public static function parse_comment_date(string $datestr): int {
        $datestr = trim($datestr);
        if ($datestr === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datestr, $m)) {
            return 0;
        }

        [, $year, $month, $day] = $m;
        $year  = (int) $year;
        $month = (int) $month;
        $day   = (int) $day;

        if (!checkdate($month, $day, $year)) {
            return 0;
        }

        // Keep the current time-of-day (in the user's own timezone) so the
        // resulting timestamp isn't always midnight — only the date itself
        // is what the assessor is choosing to override.
        $now = usergetdate(time());

        return make_timestamp($year, $month, $day, $now['hours'], $now['minutes'], $now['seconds']);
    }

    /**
     * Formats a timestamp (or "now" if none given) as a "YYYY-MM-DD" string
     * in the current user's own timezone, for prefilling a comment box's
     * <input type="date"> field — showing the date the comment currently
     * carries (or today's date, for a not-yet-saved comment), matching the
     * "if it's today, take today's date, otherwise let me set it" behaviour.
     *
     * @param int $timestamp Unix timestamp, or 0 to use the current time.
     * @return string "YYYY-MM-DD"
     */
    private static function format_comment_date_iso(int $timestamp): string {
        $ts = $timestamp > 0 ? $timestamp : time();
        $tz = \core_date::get_user_timezone_object();
        $dt = new \DateTime('@' . $ts);
        $dt->setTimezone($tz);
        return $dt->format('Y-m-d');
    }

    /**
     * Builds the {{#evidencetypeoptions}} list for the evidence type
     * checkbox popover: one entry per code in EVIDENCE_TYPES, each with
     * 'checked' set for whichever ones match the item's current selections
     * (an item can have zero, one, or several checked at once).
     *
     * @param string[] $current Currently-selected evidence type codes.
     * @return array<int, array{code: string, label: string, checked: bool}>
     */
    private static function build_evidence_type_options(array $current): array {
        $options = [];
        foreach (self::EVIDENCE_TYPES as $code => $label) {
            $options[] = [
                'code'    => $code,
                'label'   => $code . ' - ' . $label,
                'checked' => in_array($code, $current, true),
            ];
        }
        return $options;
    }

    /**
     * Formats a set of evidence type codes as a display label for
     * read-only viewers — short codes only (e.g. "O, PD"), not the full
     * type names (client request: full names like "Professional
     * Discussion" took up too much room next to each evidence item).
     * The full names are still shown in the checkbox popover when
     * editing, where there's room and the reader actually needs them to
     * pick correctly. Unrecognised codes are silently dropped rather than
     * guessing. Returns '' when nothing is set - the template hides the
     * whole "Evidence type: ..." line when this is blank.
     *
     * @param string[] $codes
     * @return string
     */
    private static function format_evidence_type_label(array $codes): string {
        $validcodes = array_values(array_filter($codes, function ($code) {
            return isset(self::EVIDENCE_TYPES[$code]);
        }));
        return implode(', ', $validcodes);
    }

    /**
     * Saves the evidence type(s) for a single item-to-criterion link,
     * completely independent of the comment on that same row: only
     * block_nvq_matrix_evidence_types rows are touched, so setting the
     * type(s) never overwrites (or re-attributes) an existing comment, and
     * vice versa — same independence principle as the grade/comment split
     * (see save_grade_comment()).
     *
     * An evidence item can genuinely fit more than one type (e.g. both
     * Observation and Professional Discussion), so this replaces the whole
     * selected set for the item: existing rows in block_nvq_matrix_evidence_types
     * for this evidencecommentid are deleted and one row per code in
     * $evidencetypes is inserted. Deliberately delete-then-insert rather
     * than diffing — the set is small (max 10 codes) and this keeps the
     * logic simple and idempotent regardless of what was previously saved.
     *
     * The legacy single-value evidencetype column on
     * block_nvq_matrix_evidence_comments is no longer written to (left in
     * place, unused, as a one-release rollback safety net — see
     * db/upgrade.php's 2026072101 step). Reads never fall back to it
     * either; once this version is deployed, block_nvq_matrix_evidence_types
     * is the sole source of truth.
     *
     * If no row exists yet in block_nvq_matrix_evidence_comments, one is
     * created with a blank comment (a row can legitimately hold only
     * evidence type(s) and no comment at all). commentedby/timemodified on
     * a freshly-created row in that case simply record who/when the row
     * was first touched, not "who wrote a comment" — the template only
     * ever shows a comment byline when hasitemcomment is true, so this
     * never produces a misleading byline.
     *
     * Capability and course-ownership checks must be performed by the
     * caller (evidence_type.php) before invoking this method.
     *
     * @param int      $mmid          block_exacompcompuser_mm row id.
     * @param int      $itemid        Exaport item ID (stored for reference only).
     * @param int      $studentid     Owner of the evidence item.
     * @param string[] $evidencetypes Zero or more EVIDENCE_TYPES codes. Empty array clears all types.
     * @return void
     * @throws \invalid_parameter_exception If any code isn't a known EVIDENCE_TYPES code.
     * @throws \dml_exception
     */
    public static function save_evidence_type(
        int   $mmid,
        int   $itemid,
        int   $studentid,
        array $evidencetypes
    ): void {
        global $DB, $USER;

        // Trim, drop blanks, de-duplicate, and validate every code up
        // front — before touching the database at all — so a request with
        // even one bad code fails cleanly rather than partially applying.
        $codes = [];
        foreach ($evidencetypes as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            if (!isset(self::EVIDENCE_TYPES[$code])) {
                throw new \invalid_parameter_exception('Unknown evidence type: ' . $code);
            }
            $codes[$code] = true;
        }
        $codes = array_keys($codes);

        $existing = $DB->get_record('block_nvq_matrix_evidence_comments', [
            'studentid' => $studentid,
            'mmid'      => $mmid,
        ]);

        if (!$existing) {
            if (empty($codes)) {
                // Nothing to store and nothing already there.
                return;
            }
            $newid = $DB->insert_record('block_nvq_matrix_evidence_comments', (object) [
                'studentid'    => $studentid,
                'mmid'         => $mmid,
                'itemid'       => $itemid,
                'comment'      => '',
                'commentedby'  => $USER->id,
                'timemodified' => time(),
            ]);
        } else {
            $newid = $existing->id;
        }

        $DB->delete_records('block_nvq_matrix_evidence_types', ['evidencecommentid' => $newid]);
        foreach ($codes as $code) {
            $DB->insert_record('block_nvq_matrix_evidence_types', (object) [
                'evidencecommentid' => $newid,
                'code'              => $code,
            ]);
        }
    }

    /**
     * Resolves the click-through URL for an evidence item in the matrix.
     *
     * - file / note → exaport shared_item.php (opens the item page visible to
     *   both students and assessors using the portfolio/id/{studentid} access key)
     * - link        → the item's own stored URL (opens the external resource directly)
     *
     * @param object $item       Evidence row with itemid, itemtype, itemurl properties.
     * @param int    $studentid  Owner of the portfolio item.
     * @param int    $courseid   Course context; 0 when called from the dashboard view.
     * @return string            Absolute URL string, or '' if no URL can be determined.
     */
    private static function build_item_url(object $item, int $studentid, int $courseid): string {
        if ($item->itemtype === 'link') {
            // Link items: open the stored external URL directly.
            $url = trim((string) $item->itemurl);
            return ($url !== '' && $url !== 'http://' && $url !== 'https://') ? $url : '';
        }

        // file and note: open the exaport shared item viewer.
        // courseid=0 (dashboard) still works — exaport accepts courseid=0 gracefully.
        return (new \moodle_url('/blocks/exaport/shared_item.php', [
            'courseid' => $courseid,
            'access'   => 'portfolio/id/' . $studentid,
            'itemid'   => $item->itemid,
        ]))->out(false);
    }

    /**
     * Duplicated from block_nvq_matrix::extract_lo_sort() so that
     * matrix_data has no dependency on the block class, allowing view.php
     * to call matrix_data::build() without requiring block_nvq_matrix.php.
     *
     * @param string $title Descriptor title from the database.
     * @return array{lo: int, isheader: bool, sub: float}
     */
    private static function extract_lo_sort(string $title): array {
        $title = trim($title);

        // LO header pattern: "LO1", "LO 2", "LO1:", "LO1 —" etc.
        if (preg_match('/^LO\s*(\d+)/i', $title, $m)) {
            return [
                'lo'       => (int) $m[1],
                'isheader' => true,
                'sub'      => 0.0,
            ];
        }

        // Criterion pattern: "1.1", "1.2 Describe...", "2.10 ..." etc.
        if (preg_match('/^(\d+)\.(\d+)/', $title, $m)) {
            return [
                'lo'       => (int) $m[1],
                'isheader' => false,
                'sub'      => (float) ($m[1] . '.' . $m[2]),
            ];
        }

        // Fallback: unrecognised format — sort to the end.
        return [
            'lo'       => PHP_INT_MAX,
            'isheader' => false,
            'sub'      => 0.0,
        ];
    }

    /**
     * Resolves the (optional) Assessment Plan / Sampling Plan / Sampling
     * Record links for each course this student's matrix touches.
     *
     * local_nvqportfolio is a soft dependency — it may not be installed
     * on every site running block_nvq_matrix, so every step here is
     * defensive: check the plugin directory exists, check its tables
     * exist, check the course actually has a qualification configured
     * (local_nvqport_qualification is one row per course — no row means
     * that course was never set up in the portfolio, so there's nothing
     * to link to), and re-use local_nvqportfolio's own
     * local_nvqportfolio_can_view_student() rather than reimplementing
     * its capability logic here. Any failure at any step for a given
     * course just quietly omits that course's panel — never fatal, since
     * this is a supplementary link block, not core matrix functionality.
     *
     * Candidate courses are looked up fresh from the topics themselves
     * (not from $topiccourseidmap in build() above) and filtered to
     * courses the student is genuinely enrolled in via is_enrolled().
     * This is deliberate: $topiccourseidmap's "lowest courseid wins"
     * tiebreak is for grading, where a topic linked to more than one
     * course (e.g. a course cloned into a variant, sharing a topic bank)
     * needs some single deterministic course to write against — it has
     * no idea which of those courses this student is actually on. Reusing
     * it here caused a real bug: students on "ProQual Level 3 Diploma in
     * Engineering Surveying" got linked to "Engineering Surveying
     * (Experienced Route)" instead, because that variant happened to have
     * the lower courseid. Resolving independently against real enrolment
     * fixes that, and naturally handles a student who's genuinely on more
     * than one such course too (they'd get a panel for each).
     *
     * @param int   $studentid The student whose matrix is being viewed.
     * @param array $topicids  The exacomp topic IDs shown on this matrix.
     * @return array           One entry per enrolled course that also has
     *                         a configured qualification AND is visible
     *                         to the current user, each with coursename +
     *                         the three portfolio page URLs. Empty if
     *                         local_nvqportfolio isn't installed.
     */
    private static function get_portfolio_links(int $studentid, array $topicids): array {
        global $CFG, $DB;

        if (empty($topicids) || empty($studentid)) {
            return [];
        }

        if (!is_dir($CFG->dirroot . '/local/nvqportfolio')) {
            return [];
        }

        require_once($CFG->dirroot . '/local/nvqportfolio/lib.php');

        if (!function_exists('local_nvqportfolio_can_view_student')) {
            return [];
        }

        $dbman = $DB->get_manager();
        $qualtable = new \xmldb_table('local_nvqport_qualification');
        if (!$dbman->table_exists($qualtable)) {
            return [];
        }

        list($topicinsql, $topicparams) = $DB->get_in_or_equal($topicids, SQL_PARAMS_NAMED, 'pftopic');
        $candidates = $DB->get_records_sql("
            SELECT DISTINCT ct.courseid, c.fullname
              FROM {block_exacompcoutopi_mm} ct
              JOIN {course} c ON c.id = ct.courseid
             WHERE ct.topicid $topicinsql
          ORDER BY c.fullname ASC
        ", $topicparams);

        $links = [];
        foreach ($candidates as $row) {
            $courseid = (int) $row->courseid;
            if (!$courseid) {
                continue;
            }

            // Only a course this student has a genuine presence on is
            // eligible, regardless of how many other courses happen to
            // share the same topics.
            //
            // REAL BUG FIXED HERE: this used to require ACTIVE enrollment
            // only (is_enrolled(..., true)), so an archived student's
            // Assessment Plan/Sampling Plan/Sampling Record links
            // disappeared entirely the moment they were unenrolled - even
            // though nothing about their actual NVQ matrix data changed.
            // Now also allows a course they have historical presence on
            // (a row in this plugin's own grades/sampling/unit_comments/
            // status tables), the same signal already trusted for the
            // archived-students feature itself and for the matching
            // final-status-box fix (v26.5.6) - not just current
            // enrollment.
            try {
                $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
                if (!$coursecontext) {
                    continue;
                }
                $haspresence = is_enrolled($coursecontext, $studentid, '', true);
                if (!$haspresence) {
                    $haspresence = $DB->record_exists('block_nvq_matrix_grades', [
                        'studentid' => $studentid, 'courseid' => $courseid,
                    ]) || $DB->record_exists('block_nvq_matrix_sampling', [
                        'studentid' => $studentid, 'courseid' => $courseid,
                    ]) || $DB->record_exists('block_nvq_matrix_unit_comments', [
                        'studentid' => $studentid, 'courseid' => $courseid,
                    ]) || $DB->record_exists('block_nvq_matrix_status', [
                        'studentid' => $studentid, 'courseid' => $courseid,
                    ]);
                }
                if (!$haspresence) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            if (!$DB->record_exists('local_nvqport_qualification', ['courseid' => $courseid])) {
                continue;
            }

            try {
                if (!local_nvqportfolio_can_view_student($courseid, $studentid)) {
                    continue;
                }
            } catch (\Throwable $e) {
                // A missing course context or similar shouldn't take the
                // whole matrix page down — just skip this course's panel.
                continue;
            }

            $links[] = [
                'coursename' => format_string($row->fullname),
                'assessmentplanurl' => (new \moodle_url('/local/nvqportfolio/assessment_plan_view.php', [
                    'courseid' => $courseid,
                    'studentid' => $studentid,
                ]))->out(false),
                'samplingplanurl' => (new \moodle_url('/local/nvqportfolio/sampling_plan_view.php', [
                    'courseid' => $courseid,
                    'studentid' => $studentid,
                ]))->out(false),
                'samplingrecordurl' => (new \moodle_url('/local/nvqportfolio/sampling_record_view.php', [
                    'courseid' => $courseid,
                    'studentid' => $studentid,
                ]))->out(false),
            ];
        }

        return $links;
    }
}
