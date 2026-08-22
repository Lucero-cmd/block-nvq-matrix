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

namespace block_nvq_matrix;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds a single "portfolio export" zip for one student, admin/
 * site-manager-only (see db/access.php's exportportfolio capability -
 * narrowed from an original teacher-facing intent in v26.4.10 after
 * live testing showed CONTEXT_SYSTEM never cascades from a course-level
 * role assignment).
 *
 * Zip layout (see handover / chat decision log for the reasoning):
 *   Portfolio_Summary.pdf   - Assessment Plan + all Sampling Plans + all
 *                              Sampling Records, reused as-is from
 *                              local_nvqportfolio's existing PDF renderer
 *                              (soft dependency - skipped if not installed).
 *                              One per distinct course, suffixed with the
 *                              courseid, if the student has more than one.
 *   Matrix_Overview/index.html
 *                            - "Final status" summary (Pass/Fail per
 *                              course, with who set it and when), then
 *                              one row per unit (grade/sampling at a
 *                              glance, each with its own attribution)
 *                              linking out to that unit's own page.
 *                              Split into one page per unit
 *                              deliberately (rather than one long page)
 *                              - client feedback was that every unit's
 *                              full descriptor/evidence tree on a single
 *                              page got bulky and rough to navigate.
 *   Matrix_Overview/Unit_<id>_<title>.html
 *                            - one unit's own descriptor tree: grade,
 *                              sampling status, unit/IQA comments,
 *                              evidence-item comments + types, and a link
 *                              to each linked evidence file - every
 *                              grade/sampling/comment line shows who set
 *                              it and when ("— Name, date"), sourced
 *                              from each table's own existing by/time-
 *                              suffixed attribution columns.
 *                              A file linked to more than one descriptor
 *                              (possibly across different units) only
 *                              gets a real link on its first occurrence;
 *                              later occurrences show a text pointer
 *                              back to wherever it first appeared.
 *   Evidence/<itemid>_<filename>.ext
 *                            - every referenced eportfolio file linked
 *                              to a competence, written once regardless
 *                              of how many descriptors reference it.
 *                              Eportfolio items never tagged to any
 *                              competence are deliberately excluded from
 *                              the export entirely, per client decision
 *                              (reversing the earlier "include unlinked
 *                              evidence too" choice) - only evidence
 *                              actually linked to a competence should
 *                              leave the platform in this zip.
 *
 * Access control is the caller's responsibility (export.php) - this class
 * assumes the caller has already established the current user may export
 * this specific student's portfolio (block/nvq_matrix:exportportfolio).
 * It never re-derives or re-checks capability itself.
 *
 * Evidence source is Exabis e-Portfolio (block_exaport, third-party,
 * unmodified) - files live in the student's own user context under
 * component 'block_exaport', filearea 'item_file', itemid = the
 * block_exaportitem row id. See block_exaport's lib.php pluginfile
 * handler for the canonical version of this lookup this class mirrors.
 *
 * NOTE ON FIELD VERIFICATION: like the rest of this plugin, this was
 * built without a live Moodle install to execute against (see
 * handover's Architecture section) - table/field names here are taken
 * directly from block_exaport's and this plugin's own db/install.xml,
 * cross-checked against matrix_data::build()'s existing queries against
 * the same tables. Recommend a staging run before trusting this on prod,
 * same as every other change in this codebase.
 */
class portfolio_export {

    /**
     * Entry point: builds the zip for one student and sends it to the
     * browser as a forced download, then exits. Caller must already have
     * verified capability before calling this.
     *
     * @param int $studentid The student whose portfolio is being exported.
     */
    public static function send_zip(int $studentid): void {
        global $DB;

        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

        $tree = self::build_matrix_tree($studentid);

        // itemid => stored_file, deduplicated across every descriptor
        // that references the same evidence item.
        $files = self::collect_evidence_files($tree, $studentid);

        $overviewpages = self::render_overview_pages($tree, $student, $files);

        $zippath = self::assemble_zip($studentid, $student, $tree, $overviewpages, $files);

        $filename = clean_filename(
            'portfolio_' . fullname($student) . '_' . userdate(time(), '%Y-%m-%d') . '.zip'
        );

        send_temp_file($zippath, $filename);
        // send_temp_file() does not return.
    }

    /**
     * Mirrors matrix_data::build()'s data-gathering exactly (same tables,
     * same joins - see that method for the reasoning behind each query),
     * but returns a plain nested array rather than Mustache template
     * data, and deliberately covers ALL of the student's units across
     * ALL their courses in one call (matrix_data::build() does the same
     * when $oncoursepage is false) - a portfolio export should never be
     * scoped to a single course.
     *
     * @param int $studentid
     * @return array{topics: array, statusbycourse: array, coursenames: array}
     */
    private static function build_matrix_tree(int $studentid): array {
        global $DB;

        // block_nvq_matrix_status is deliberately independent of
        // topics/grades - an assessor can set final status for a course
        // even if this student currently has zero linked evidence/topics
        // there (status set before evidence upload, or evidence later
        // removed). Fetched unconditionally, before the topics query
        // below, specifically so the no-topics-at-all early return a few
        // lines down still carries it - final status must never
        // silently disappear from the export just because this student
        // happens to have no eportfolio-linked topics right now.
        $statusinfo = self::fetch_final_status($studentid);

        // Same topic-resolution fallback matrix_data::build() uses when
        // not on a course page: every topic this student has eportfolio
        // evidence linked against.
        $topicids = $DB->get_fieldset_sql("
            SELECT DISTINCT dtm.topicid
              FROM {block_exacompcompuser_mm} mm
              JOIN {block_exacompdescrtopic_mm} dtm ON dtm.descrid = mm.compid
             WHERE mm.userid         = :userid
               AND mm.eportfolioitem = 1
        ", ['userid' => $studentid]);

        if (empty($topicids)) {
            return [
                'topics' => [],
                'statusbycourse' => $statusinfo['statusbycourse'],
                'coursenames' => $statusinfo['coursenames'],
            ];
        }

        [$topicinsql, $topicparams] = $DB->get_in_or_equal($topicids, SQL_PARAMS_NAMED, 'topic');

        $topics = $DB->get_records_select('block_exacomptopics', "id $topicinsql", $topicparams, 'id ASC');

        $topiccourseidmap = [];
        $coursemaprs = $DB->get_recordset_sql("
            SELECT ct.id, ct.topicid, ct.courseid, c.fullname
              FROM {block_exacompcoutopi_mm} ct
              JOIN {course} c ON c.id = ct.courseid
             WHERE ct.topicid $topicinsql
        ", $topicparams);
        $topiccoursenames = [];
        foreach ($coursemaprs as $row) {
            if (!isset($topiccourseidmap[$row->topicid])
                    || (int) $row->courseid < $topiccourseidmap[$row->topicid]) {
                $topiccourseidmap[$row->topicid] = (int) $row->courseid;
            }
            $topiccoursenames[(int) $row->courseid] = format_string($row->fullname);
        }
        $coursemaprs->close();

        $descriptorrows = $DB->get_records_sql("
            SELECT dtm.id, d.id AS descriptorid, d.title, d.sorting, d.parentid, dtm.topicid
              FROM {block_exacompdescrtopic_mm} dtm
              JOIN {block_exacompdescriptors} d ON d.id = dtm.descrid
             WHERE dtm.topicid $topicinsql
        ", $topicparams);

        // Evidence, keyed by descriptor id - same query matrix_data uses,
        // mmid is the specific item<->criterion link (a file can be
        // linked to several descriptors, each its own mm row).
        $evidencerows = $DB->get_records_sql("
            SELECT mm.id AS mmid, mm.compid AS descriptorid,
                   i.id AS itemid, i.name AS itemname, i.type AS itemtype,
                   i.url AS itemurl, i.userid AS itemuserid, i.intro AS itemintro
              FROM {block_exacompcompuser_mm} mm
              JOIN {block_exaportitem} i ON i.id = mm.activityid
             WHERE mm.userid         = :userid
               AND mm.eportfolioitem = 1
        ", ['userid' => $studentid]);

        $evidencebydescriptor = [];
        $linkeditemids = [];
        foreach ($evidencerows as $row) {
            $evidencebydescriptor[(int) $row->descriptorid][] = $row;
            $linkeditemids[(int) $row->itemid] = true;
        }

        $commentrows = $DB->get_records('block_nvq_matrix_evidence_comments', ['studentid' => $studentid]);
        $commentmap = [];
        $commentids = [];
        foreach ($commentrows as $crow) {
            if (empty($crow->mmid)) {
                continue;
            }
            $commentmap[(int) $crow->mmid] = $crow;
            $commentids[] = $crow->id;
        }
        $typesbycommentid = [];
        if (!empty($commentids)) {
            [$cinsql, $cparams] = $DB->get_in_or_equal($commentids);
            $typerows = $DB->get_records_select('block_nvq_matrix_evidence_types', "evidencecommentid $cinsql", $cparams);
            foreach ($typerows as $trow) {
                $typesbycommentid[(int) $trow->evidencecommentid][] = $trow->code;
            }
        }

        $graderows   = $DB->get_records('block_nvq_matrix_grades', ['studentid' => $studentid]);
        $samplerows  = $DB->get_records('block_nvq_matrix_sampling', ['studentid' => $studentid]);
        $commentrows2 = $DB->get_records('block_nvq_matrix_unit_comments', ['studentid' => $studentid]);

        // Every user id anywhere in these rows that needs a display name -
        // resolved once, in a single query, rather than per-row. (Final
        // status attribution is resolved separately, inside
        // fetch_final_status() - it's fetched before this point and
        // doesn't depend on anything gathered here.)
        $useridstoresolve = [];
        foreach ($graderows as $g) {
            $useridstoresolve[] = $g->gradedby;
            $useridstoresolve[] = $g->commentedby;
        }
        foreach ($samplerows as $s) {
            $useridstoresolve[] = $s->sampledby;
        }
        foreach ($commentrows2 as $c) {
            $useridstoresolve[] = $c->assessorcommentby;
            $useridstoresolve[] = $c->iqacommentby;
        }
        foreach ($commentrows as $crow) {
            $useridstoresolve[] = $crow->commentedby;
        }
        $usernames = self::resolve_user_names($useridstoresolve);

        $grademap = [];
        foreach ($graderows as $g) {
            $grademap[(int) $g->topicid . ':' . (int) $g->courseid] = $g;
        }
        $samplemap = [];
        foreach ($samplerows as $s) {
            $samplemap[(int) $s->topicid . ':' . (int) $s->courseid] = $s;
        }
        $unitcommentmap = [];
        foreach ($commentrows2 as $c) {
            $unitcommentmap[(int) $c->topicid . ':' . (int) $c->courseid] = $c;
        }

        // fetch_final_status() already resolved names for every course
        // that has a status row (regardless of whether it also has
        // topics) - merge those into $topiccoursenames rather than
        // re-querying, so a status-only course (no topics for this
        // student) still gets a display name on the index page.
        foreach ($statusinfo['coursenames'] as $cid => $name) {
            if (!isset($topiccoursenames[$cid])) {
                $topiccoursenames[$cid] = $name;
            }
        }

        // Build descriptor tree per topic, sorted the same way
        // matrix_data::extract_lo_sort() orders them (LO header before its
        // own criteria, then decimal order) - reusing that exact method
        // rather than re-implementing the sort keeps ordering identical
        // to what the teacher sees on-screen.
        $descriptorsbytopic = [];
        foreach ($descriptorrows as $d) {
            $descriptorsbytopic[(int) $d->topicid][] = $d;
        }
        // Order each topic's descriptors the same way matrix_data::build()
        // does (LO header before its own criteria, then decimal order) -
        // duplicated rather than called directly since extract_lo_sort()
        // is private to matrix_data; same duplication pattern that class
        // already uses for its own build_item_url() (see its docblock).
        foreach ($descriptorsbytopic as $tid => $rows) {
            usort($rows, function ($a, $b) {
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
            $descriptorsbytopic[$tid] = $rows;
        }

        $result = ['topics' => []];

        foreach ($topics as $topic) {
            $tid = (int) $topic->id;
            $courseid = $topiccourseidmap[$tid] ?? 0;
            $key = $tid . ':' . $courseid;

            $descriptors = [];
            foreach (($descriptorsbytopic[$tid] ?? []) as $d) {
                $did = (int) $d->descriptorid;
                $evidence = [];
                foreach (($evidencebydescriptor[$did] ?? []) as $erow) {
                    $mmid = (int) $erow->mmid;
                    $comment = $commentmap[$mmid] ?? null;
                    $evidence[] = [
                        'mmid'     => $mmid,
                        'itemid'   => (int) $erow->itemid,
                        'itemname' => format_string($erow->itemname),
                        'itemtype' => $erow->itemtype,
                        'itemurl'  => $erow->itemurl,
                        'itemintro' => $erow->itemintro ?? '',
                        'itemuserid' => (int) $erow->itemuserid,
                        'comment'  => $comment ? trim((string) $comment->comment) : '',
                        'commentbyname' => $comment ? ($usernames[(int) $comment->commentedby] ?? '') : '',
                        'commentdate'   => ($comment && $comment->timemodified)
                            ? userdate((int) $comment->timemodified, get_string('strftimedatetimeshort', 'langconfig')) : '',
                        'evidencetypes' => $comment ? ($typesbycommentid[(int) $comment->id] ?? []) : [],
                    ];
                }
                $descriptors[] = [
                    'id'    => $did,
                    'title' => format_string($d->title),
                    'evidence' => $evidence,
                ];
            }

            $grade  = $grademap[$key] ?? null;
            $sample = $samplemap[$key] ?? null;
            $ucomm  = $unitcommentmap[$key] ?? null;

            $fmtdate = fn($ts) => $ts ? userdate((int) $ts, get_string('strftimedatetimeshort', 'langconfig')) : '';

            $result['topics'][] = [
                'id'          => $tid,
                'title'       => format_string($topic->title ?? ''),
                'courseid'    => $courseid,
                'coursename'  => $topiccoursenames[$courseid] ?? '',
                'gradevalue'  => $grade->value ?? null,
                'gradebyname' => ($grade && $grade->value !== null) ? ($usernames[(int) $grade->gradedby] ?? '') : '',
                'gradedate'   => ($grade && $grade->value !== null) ? $fmtdate($grade->timemodified) : '',
                'gradecomment' => $grade->comment ?? '',
                'gradecommentbyname' => ($grade && $grade->comment) ? ($usernames[(int) $grade->commentedby] ?? '') : '',
                'gradecommentdate'   => ($grade && $grade->comment) ? $fmtdate($grade->commenttime) : '',
                'samplestatus' => $sample->status ?? 0,
                'samplebyname' => ($sample && $sample->status) ? ($usernames[(int) $sample->sampledby] ?? '') : '',
                'sampledate'   => ($sample && $sample->status) ? $fmtdate($sample->timemodified) : '',
                'assessorcomment' => $ucomm->assessorcomment ?? '',
                'assessorcommentbyname' => ($ucomm && $ucomm->assessorcomment) ? ($usernames[(int) $ucomm->assessorcommentby] ?? '') : '',
                'assessorcommentdate'   => ($ucomm && $ucomm->assessorcomment) ? $fmtdate($ucomm->assessorcommenttime) : '',
                'iqacomment'   => $ucomm->iqacomment ?? '',
                'iqacommentbyname' => ($ucomm && $ucomm->iqacomment) ? ($usernames[(int) $ucomm->iqacommentby] ?? '') : '',
                'iqacommentdate'   => ($ucomm && $ucomm->iqacomment) ? $fmtdate($ucomm->iqacommenttime) : '',
                'descriptors' => $descriptors,
            ];
        }

        $result['statusbycourse'] = $statusinfo['statusbycourse'];
        // Superset of every course name needed anywhere in the export -
        // both courses reached via a topic AND status-only courses
        // (merged in above, from fetch_final_status()).
        $result['coursenames'] = $topiccoursenames;

        return $result;
    }

    /**
     * Fetches this student's final Pass/Fail status per course, with
     * attribution, fully independent of whether they have any topics/
     * evidence at all. Deliberately self-contained (resolves its own
     * user names AND its own course names) rather than depending on
     * anything gathered elsewhere in build_matrix_tree() - status is a
     * genuinely separate table with no dependency on topics existing,
     * so this has to work correctly even when called from the
     * no-topics-at-all early-return path.
     *
     * @param int $studentid
     * @return array{statusbycourse: array, coursenames: array} courseid
     *              keyed in both.
     */
    private static function fetch_final_status(int $studentid): array {
        global $DB;

        $statusrows = $DB->get_records('block_nvq_matrix_status', ['studentid' => $studentid]);
        if (empty($statusrows)) {
            return ['statusbycourse' => [], 'coursenames' => []];
        }

        $setbyids = array_map(fn($st) => $st->setby, $statusrows);
        $usernames = self::resolve_user_names($setbyids);

        $statusbycourse = [];
        foreach ($statusrows as $st) {
            $statusbycourse[(int) $st->courseid] = [
                'status' => $st->status === null ? null : (int) $st->status,
                'byname' => $usernames[(int) $st->setby] ?? '',
                'date'   => $st->timemodified
                    ? userdate((int) $st->timemodified, get_string('strftimedatetimeshort', 'langconfig')) : '',
            ];
        }

        $courseids = array_keys($statusbycourse);
        $courserows = $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname');
        $coursenames = [];
        foreach ($courserows as $c) {
            $coursenames[(int) $c->id] = format_string($c->fullname);
        }

        return ['statusbycourse' => $statusbycourse, 'coursenames' => $coursenames];
    }

    /**
     * Resolves a list of user ids (may contain 0/null/duplicates - all
     * safely ignored) to display names in a single query, rather than
     * one lookup per row. Used for grade/sampling/comment/status
     * attribution throughout the export.
     *
     * @param array $userids
     * @return array userid => fullname
     */
    private static function resolve_user_names(array $userids): array {
        global $DB;

        $userids = array_values(array_unique(array_filter($userids, fn($id) => !empty($id))));
        if (empty($userids)) {
            return [];
        }

        // fullname() needs whatever fields this site's fullnamedisplay
        // format string actually references - which ones those are
        // isn't knowable from this code alone, so select the full set
        // Moodle's own user-fields helpers consider "name fields" rather
        // than guessing a subset. An incomplete list doesn't just log a
        // debugging() notice: on a site where output buffering isn't
        // already active, that notice prints as HTML *before* this
        // script's file-download headers get sent, corrupting the zip
        // output with a "headers already sent" error - confirmed live,
        // not theoretical. Matches the field list view.php's
        // archived-student lookup already uses correctly.
        $users = $DB->get_records_list(
            'user',
            'id',
            $userids,
            '',
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
        $names = [];
        foreach ($users as $user) {
            $names[(int) $user->id] = fullname($user);
        }
        return $names;
    }

    /**
     * Resolves every evidence "file"/"note" type item referenced anywhere
     * in the tree to its actual stored_file, deduped
     * by itemid so a file linked to five descriptors is still only fetched
     * (and later zipped) once. "link" type items have no file - they're
     * rendered as an external URL in the HTML instead.
     *
     * @param array $tree
     * @param int $studentid
     * @return array itemid => \stored_file
     */
    private static function collect_evidence_files(array $tree, int $studentid): array {
        $fs = get_file_storage();
        $files = [];

        $additem = function (array $item) use (&$files, $fs) {
            $itemid = $item['itemid'];
            if (isset($files[$itemid]) || $item['itemtype'] === 'link') {
                return;
            }
            $userid = $item['itemuserid'] ?: null;
            if (!$userid) {
                return;
            }
            $context = \context_user::instance($userid, IGNORE_MISSING);
            if (!$context) {
                return;
            }
            // A file/note item normally has exactly one 'item_file'
            // attachment; get_area_files() with itemid set returns just
            // that item's files (directory entries filtered out below).
            $areafiles = $fs->get_area_files($context->id, 'block_exaport', 'item_file', $itemid, 'filename', false);
            foreach ($areafiles as $f) {
                if (!$f->is_directory()) {
                    $files[$itemid] = $f;
                    break; // One file per evidence item is the expected shape here.
                }
            }
        };

        foreach ($tree['topics'] as $topic) {
            foreach ($topic['descriptors'] as $descriptor) {
                foreach ($descriptor['evidence'] as $item) {
                    $additem($item);
                }
            }
        }

        return $files;
    }

    /**
     * Renders Matrix_Overview.html - a static, self-contained mirror of
     * the live matrix's unit -> descriptor structure. Evidence links
     * point at the local Evidence/ copy rather than pluginfile.php, since
     * the zip has to work standalone once downloaded.
     *
     * Renders as a small SET of files under Matrix_Overview/ rather than
     * one long page - client feedback was that a single page with every
     * unit's full descriptor/evidence tree on it got bulky and rough to
     * navigate. index.html lists every unit (with its grade/sampling at
     * a glance) linking out to that unit's own page; each unit's
     * descriptors/evidence live only on that unit's own page.
     *
     * First occurrence of a given itemid anywhere across these pages gets
     * the real link to Evidence/; every later occurrence (same file
     * linked to more than one descriptor, possibly on a different unit's
     * page) instead shows a short back-pointer to wherever it first
     * appeared. All these pages sit in the same folder, so a
     * cross-page pointer is just "otherfile.html#anchor" - no relative
     * path juggling needed.
     *
     * @param array $tree
     * @param \stdClass $student
     * @param array $files itemid => \stored_file, from collect_evidence_files()
     *              - used to confirm a link actually has something to
     *              point at before rendering it, and to link using the
     *              file's real name rather than the item's display name.
     * @return array relative-path-within-Matrix_Overview => html string,
     *               e.g. 'index.html' => '...', 'Unit_12_xyz.html' => '...'.
     */
    private static function render_overview_pages(array $tree, \stdClass $student, array $files): array {
        // itemid => ['file' => filename, 'anchor' => anchor id] of first occurrence.
        $seen = [];

        $esc = fn($s) => s((string) $s);

        // "— Name, date" attribution line, only rendered when there's
        // actually a name to show (an empty/never-set field has nothing
        // to attribute). Defined early so every closure below that needs
        // it (comment/grade/sampling/status rendering) can capture it via
        // use() - closures capture by value at definition time, so this
        // has to exist before anything that references it in its own
        // use() clause, not just before it's called.
        $byline = function (string $name, string $date) use ($esc): string {
            if ($name === '') {
                return '';
            }
            $bit = $esc($name);
            if ($date !== '') {
                $bit .= ', ' . $esc($date);
            }
            return ' <span class="byline">— ' . $bit . '</span>';
        };

        $css = '
            body{font-family:sans-serif;max-width:900px;margin:2em auto;line-height:1.4;}
            h1{margin-bottom:0;} .meta{color:#555;margin-top:.2em;}
            h2{margin-top:1.6em;border-bottom:2px solid #ccc;padding-bottom:.2em;}
            h3{margin-top:1.2em;}
            .unitmeta{background:#f5f5f5;padding:.6em 1em;border-radius:6px;margin:.4em 0 1em;}
            .unitmeta div{margin:.15em 0;}
            .comment{margin:.3em 0;padding:.4em .6em;background:#fafafa;border-left:3px solid #ccc;}
            .etype{font-size:.85em;color:#555;font-style:italic;}
            .byline{font-size:.85em;color:#777;font-weight:normal;font-style:italic;}
            ul{margin:.2em 0;}
            table{border-collapse:collapse;width:100%;margin-top:1em;}
            th,td{text-align:left;padding:.5em .6em;border-bottom:1px solid #ddd;}
            th{background:#f5f5f5;}
            .back{display:inline-block;margin-bottom:1em;}
        ';

        $evidencetypelabel = function (array $codes): string {
            if (empty($codes)) {
                return '';
            }
            $labels = array_map(function ($code) {
                return matrix_data::EVIDENCE_TYPES[$code] ?? $code;
            }, $codes);
            return implode(', ', $labels);
        };

        $rendercomment = function (array $item) use ($esc, $evidencetypelabel, $byline): string {
            $out = '';
            if (!empty($item['evidencetypes'])) {
                $out .= '<div class="etype">' . $esc($evidencetypelabel($item['evidencetypes'])) . '</div>';
            }
            if (!empty($item['comment'])) {
                $out .= '<div class="comment">' . nl2br($esc($item['comment']))
                    . $byline($item['commentbyname'] ?? '', $item['commentdate'] ?? '') . '</div>';
            }
            return $out;
        };

        $renderevidenceitem = function (array $item, string $currentfile) use (&$seen, $esc, $files): string {
            $itemid = $item['itemid'];
            $name = $esc($item['itemname']);

            if ($item['itemtype'] === 'link') {
                $url = trim((string) $item['itemurl']);
                $link = ($url !== '') ? '<a href="' . $esc($url) . '" target="_blank" rel="noopener">' . $name . ' (external link)</a>' : $name;
                return '<li>' . $link . '</li>';
            }

            if ($item['itemtype'] === 'note') {
                // A 'note' item is text by design, not a file - it never
                // had anything to upload, so "no file available" would be
                // a misleading thing to say about it. Show its own text
                // instead. intro is a rich-text editor field, so strip
                // markup rather than trust/render it as-is.
                $text = trim(strip_tags((string) ($item['itemintro'] ?? '')));
                $body = $text !== '' ? nl2br($esc($text)) : '<em>(empty note)</em>';
                return '<li>' . $name . '<div class="comment">' . $body . '</div></li>';
            }

            if (isset($seen[$itemid])) {
                $prev = $seen[$itemid];
                $href = ($prev['file'] === $currentfile) ? '#' . $prev['anchor'] : $esc($prev['file']) . '#' . $prev['anchor'];
                return '<li>' . $name . ' — <em>same file, see <a href="' . $href . '">evidence #' . $itemid . '</a></em></li>';
            }

            $anchor = 'ev' . $itemid;
            $seen[$itemid] = ['file' => $currentfile, 'anchor' => $anchor];

            if (!isset($files[$itemid])) {
                // The item exists but no actual file could be resolved for
                // it (e.g. a 'note' type item with no item_file
                // attachment, or an orphaned/deleted upload) - link to a
                // path that was never written into the zip would be a
                // dead link, so say so plainly instead.
                return '<li id="' . $anchor . '">' . $name . ' <em>(no file available)</em></li>';
            }

            // Build the link from the actually resolved stored_file's real
            // filename, matching exactly what assemble_zip() wrote into
            // Evidence/ - NOT the evidence item's display name, which is
            // very often different from the uploaded file's own name.
            $realfilename = self::safe_filename($files[$itemid]->get_filename());
            $relpath = '../Evidence/' . $itemid . '_' . $realfilename;
            return '<li id="' . $anchor . '"><a href="' . $esc($relpath) . '">' . $name . '</a></li>';
        };

        $gradelabel = function ($value): string {
            if ($value === null) {
                return 'Not yet graded';
            }
            return $value ? 'Competent' : 'Not Yet Competent';
        };
        $samplelabel = function ($status): string {
            return match ((int) $status) {
                1 => 'Sampled',
                2 => 'Not Yet Sampled',
                default => 'Not set',
            };
        };
        $finalstatuslabel = function ($status): string {
            if ($status === null) {
                return 'Not yet set';
            }
            return $status ? 'Pass' : 'Fail';
        };

        // First pass: work out each unit's filename up front, so index.html
        // can link to them before the per-unit pages are rendered (and so
        // a "same file" pointer from an earlier unit to a later one - a
        // valid ordering, since evidence isn't guaranteed to appear in
        // topic order - already knows the target filename).
        $unitfiles = [];
        foreach ($tree['topics'] as $topic) {
            $unitfiles[$topic['id']] = self::safe_filename('Unit_' . $topic['id'] . '_' . $topic['title']) . '.html';
        }

        $pages = [];

        // --- Per-unit pages -------------------------------------------------
        foreach ($tree['topics'] as $topic) {
            $file = $unitfiles[$topic['id']];

            $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
            $html .= '<title>' . $esc($topic['title']) . ' — ' . $esc(fullname($student)) . '</title>';
            $html .= '<style>' . $css . '</style></head><body>';
            $html .= '<a class="back" href="index.html">&#8592; Back to overview</a>';
            $html .= '<h1>' . $esc($topic['title']) . '</h1>';
            if ($topic['coursename']) {
                $html .= '<p class="meta">' . $esc($topic['coursename']) . '</p>';
            }

            $html .= '<div class="unitmeta">';
            $html .= '<div><strong>Grade:</strong> ' . $esc($gradelabel($topic['gradevalue']))
                . $byline($topic['gradebyname'] ?? '', $topic['gradedate'] ?? '') . '</div>';
            if (!empty($topic['gradecomment'])) {
                $html .= '<div><strong>Assessor comment:</strong> ' . nl2br($esc($topic['gradecomment']))
                    . $byline($topic['gradecommentbyname'] ?? '', $topic['gradecommentdate'] ?? '') . '</div>';
            }
            $html .= '<div><strong>Sampling:</strong> ' . $esc($samplelabel($topic['samplestatus']))
                . $byline($topic['samplebyname'] ?? '', $topic['sampledate'] ?? '') . '</div>';
            if (!empty($topic['assessorcomment'])) {
                $html .= '<div><strong>Unit assessor comment:</strong> ' . nl2br($esc($topic['assessorcomment']))
                    . $byline($topic['assessorcommentbyname'] ?? '', $topic['assessorcommentdate'] ?? '') . '</div>';
            }
            if (!empty($topic['iqacomment'])) {
                $html .= '<div><strong>IQA comment:</strong> ' . nl2br($esc($topic['iqacomment']))
                    . $byline($topic['iqacommentbyname'] ?? '', $topic['iqacommentdate'] ?? '') . '</div>';
            }
            $html .= '</div>';

            foreach ($topic['descriptors'] as $descriptor) {
                $html .= '<h3>' . $esc($descriptor['title']) . '</h3>';
                if (empty($descriptor['evidence'])) {
                    $html .= '<p><em>No evidence linked yet.</em></p>';
                    continue;
                }
                $html .= '<ul>';
                foreach ($descriptor['evidence'] as $item) {
                    $html .= $renderevidenceitem($item, $file);
                    $html .= $rendercomment($item);
                }
                $html .= '</ul>';
            }

            $html .= '</body></html>';
            $pages[$file] = $html;
        }

        // --- Index page -------------------------------------------------
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>Matrix Overview — ' . $esc(fullname($student)) . '</title>';
        $html .= '<style>' . $css . '</style></head><body>';
        $html .= '<h1>Matrix Overview</h1>';
        $html .= '<p class="meta">' . $esc(fullname($student)) . ' &middot; exported ' . $esc(userdate(time())) . '</p>';

        // Final status is per-course, not per-unit, so it gets its own
        // small table above the units table rather than a repeated
        // column on every unit row. Course list is the UNION of courses
        // reached via a topic AND any course with a status row but no
        // current topics for this student (block_nvq_matrix_status is
        // independent of topics existing) - scanning topics alone would
        // silently drop a status-only course even though the data is
        // right there in $tree['statusbycourse'].
        if (!empty($tree['statusbycourse'])) {
            $courseidsseen = [];
            foreach ($tree['topics'] as $topic) {
                $courseidsseen[$topic['courseid']] = true;
            }
            foreach (array_keys($tree['statusbycourse']) as $cid) {
                $courseidsseen[$cid] = true;
            }
            $html .= '<h2>Final status</h2><table><tr><th>Course</th><th>Status</th></tr>';
            foreach (array_keys($courseidsseen) as $cid) {
                $st = $tree['statusbycourse'][$cid] ?? null;
                $coursename = $tree['coursenames'][$cid] ?? '';
                $statustext = $st ? $finalstatuslabel($st['status']) : $finalstatuslabel(null);
                $statusbyline = $st ? $byline($st['byname'], $st['date']) : '';
                $html .= '<tr><td>' . $esc($coursename) . '</td><td>' . $esc($statustext) . $statusbyline . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= '<h2>Units</h2>';
        $html .= '<table><tr><th>Unit</th><th>Course</th><th>Grade</th><th>Sampling</th></tr>';
        foreach ($tree['topics'] as $topic) {
            $file = $unitfiles[$topic['id']];
            $html .= '<tr>';
            $html .= '<td><a href="' . $esc($file) . '">' . $esc($topic['title']) . '</a></td>';
            $html .= '<td>' . $esc($topic['coursename']) . '</td>';
            $html .= '<td>' . $esc($gradelabel($topic['gradevalue'])) . $byline($topic['gradebyname'] ?? '', $topic['gradedate'] ?? '') . '</td>';
            $html .= '<td>' . $esc($samplelabel($topic['samplestatus'])) . $byline($topic['samplebyname'] ?? '', $topic['sampledate'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '</body></html>';
        $pages['index.html'] = $html;

        return $pages;
    }

    /**
     * Assembles the final zip on disk (Moodle's zip_packer, not raw
     * ZipArchive, so it goes through the same file-handling path as
     * every other Moodle-generated zip) and returns its temp path.
     *
     * @param int $studentid
     * @param \stdClass $student
     * @param array $tree
     * @param array $overviewpages relative filename => html, from
     *              render_overview_pages().
     * @param array $files itemid => \stored_file
     * @return string absolute path to the assembled zip in $CFG->tempdir.
     */
    private static function assemble_zip(
        int $studentid,
        \stdClass $student,
        array $tree,
        array $overviewpages,
        array $files
    ): string {
        global $CFG;

        $packer = get_file_packer('application/zip');

        $filestozip = [];

        // zip_packer::archive_to_pathname() requires raw string content to
        // be wrapped as [content] - a bare string is instead read as an
        // OS pathname to an existing file, which this isn't. (stored_file
        // objects, used below for evidence, are passed as-is - only raw
        // strings need the wrapper.)
        foreach ($overviewpages as $filename => $html) {
            $filestozip['Matrix_Overview/' . $filename] = [$html];
        }

        $summarypdfs = self::build_portfolio_summary_pdfs($studentid);
        foreach ($summarypdfs as $courseid => $pdfbytes) {
            // One student can have Assessment Plan/Sampling data on more
            // than one course (distinct course ids, per client) - each
            // gets its own summary rather than only exporting the first.
            // A single course keeps the plain filename for the common
            // case; only courseid-suffix once there's more than one.
            $name = count($summarypdfs) > 1
                ? 'Portfolio_Summary_' . $courseid . '.pdf'
                : 'Portfolio_Summary.pdf';
            $filestozip[$name] = [$pdfbytes];
        }

        foreach ($files as $itemid => $storedfile) {
            $relname = 'Evidence/' . $itemid . '_' . self::safe_filename($storedfile->get_filename());
            $filestozip[$relname] = $storedfile;
        }

        $tmpname = tempnam($CFG->tempdir, 'nvqexport');
        // archive_to_pathname accepts a mix of raw string content and
        // stored_file objects as values - both are supported directly by
        // Moodle's zip_packer, no need to write string content to a temp
        // file first.
        $packer->archive_to_pathname($filestozip, $tmpname);

        return $tmpname;
    }

    /**
     * Soft-dependency call into local_nvqportfolio, reusing its existing
     * portfolio PDF renderer (AP + all Sampling Plans + all Sampling
     * Records) but capturing the TCPDF output as a string instead of
     * forcing a browser download, so it can be embedded in this zip.
     * Guarded the same way matrix_data::get_portfolio_links() already
     * guards its own call into this plugin - never assumes it's
     * installed.
     *
     * Builds one summary PDF per distinct course id the student has an
     * Assessment Plan on, rather than only the first - a student with
     * more than one course each has its own courseid, so each gets its
     * own summary (chat: "a student with multiple courses have varying
     * course ID so i think that can make it easier").
     *
     * @param int $studentid
     * @return array courseid => raw PDF bytes. Empty if local_nvqportfolio
     *                isn't installed or has no data for this student.
     */
    private static function build_portfolio_summary_pdfs(int $studentid): array {
        global $CFG, $DB;

        if (!is_dir($CFG->dirroot . '/local/nvqportfolio')) {
            return [];
        }
        require_once($CFG->dirroot . '/local/nvqportfolio/lib.php');
        if (!function_exists('local_nvqportfolio_render_portfolio_pdf_html')) {
            return [];
        }

        $courseids = $DB->get_fieldset_select(
            'local_nvqport_ap',
            'DISTINCT courseid',
            'studentid = :studentid',
            ['studentid' => $studentid]
        );
        if (empty($courseids)) {
            return [];
        }

        require_once($CFG->libdir . '/pdflib.php');

        $pdfs = [];
        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            $body = local_nvqportfolio_render_portfolio_pdf_html($courseid, $studentid);
            if (trim((string) $body) === '') {
                // Defensive: skip a course that turned out to have no
                // renderable content rather than zipping a blank PDF.
                continue;
            }

            $pdf = new \pdf();
            $pdf->SetCreator('Moodle');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();
            $pdf->writeHTML($body, true, false, true, false, '');

            // 'S' = return as string rather than 'D' (force download) -
            // see local_nvqportfolio's own export.php for the download
            // variant this mirrors.
            $pdfs[$courseid] = $pdf->Output('portfolio_summary.pdf', 'S');
        }

        return $pdfs;
    }

    /**
     * Duplicated from matrix_data::extract_lo_sort() (itself duplicated
     * from block_nvq_matrix::extract_lo_sort() for the same reason -
     * private methods can't be called cross-class). Keeps descriptor
     * ordering in this export identical to what the teacher sees
     * on-screen. If the sort logic in matrix_data ever changes, this
     * copy needs to be updated to match.
     *
     * @param string $title Descriptor title from the database.
     * @return array{lo: int, isheader: bool, sub: float}
     */
    private static function extract_lo_sort(string $title): array {
        $title = trim($title);

        if (preg_match('/^LO\s*(\d+)/i', $title, $m)) {
            return [
                'lo'       => (int) $m[1],
                'isheader' => true,
                'sub'      => 0.0,
            ];
        }

        if (preg_match('/^(\d+)\.(\d+)/', $title, $m)) {
            return [
                'lo'       => (int) $m[1],
                'isheader' => false,
                'sub'      => (float) ($m[1] . '.' . $m[2]),
            ];
        }

        return [
            'lo'       => PHP_INT_MAX,
            'isheader' => false,
            'sub'      => 0.0,
        ];
    }

    /**
     * Filesystem-safe version of an evidence item's display name, for use
     * inside the zip. Deliberately not Moodle's clean_filename() alone -
     * that still allows characters that read oddly inside a zip browser
     * (spaces are fine, kept as-is); this just strips path separators
     * and control characters.
     *
     * @param string $name
     * @return string
     */
    private static function safe_filename(string $name): string {
        $name = clean_filename($name);
        return $name === '' ? 'file' : $name;
    }
}
