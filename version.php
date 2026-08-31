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
 * Version metadata for the block_nvq_matrix plugin.
 *
 * v26.6.6 (1.20.6) — Reverses part of v26.6.5, client decision: that
 *   version made build_portfolio_summary_pdfs() skip the summary PDF
 *   entirely when the student had no genuine Assessment
 *   Plan/Sampling Plan/Sampling Record for the exported course.
 *   Reversed - this zip gets submitted to whoever is in charge of the
 *   student, and the client wants a "nothing entered yet" state to
 *   actively appear in that paperwork, not be silently omitted.
 *   local_nvqportfolio's own renderer already produces that page
 *   correctly on its own (headers plus "No assessment plan" text etc.)
 *   - the v26.6.5 skip-check just needed to stop skipping it. The
 *   single-course restriction itself (the actual bug fix in v26.6.5 -
 *   no longer bundling an unrelated second course's plan) is
 *   unchanged and stays in place.
 *
 *   No schema or capability change - release-string/version bump only.
 *
 * v26.6.5 (1.20.5) — Export Portfolio restricted to a single course,
 *   client decision after live testing: previously (deliberately, per
 *   client's own earlier request quoted in the code) bundled a
 *   Portfolio_Summary.pdf for EVERY course the student had ever had an
 *   Assessment Plan on, courseid-suffixed once there was more than one.
 *   Reversed - a student with an unrelated second course's plan on file
 *   had that bundled into an export for a course it had nothing to do
 *   with. classes/portfolio_export.php's send_zip()/
 *   build_matrix_tree()/fetch_final_status()/
 *   build_portfolio_summary_pdfs() all now take an explicit $courseid
 *   and scope every query to it - grades/sampling/unit_comments/status
 *   by their own courseid column, topics/evidence via an added join to
 *   block_exacompcoutopi_mm filtered by courseid (same shared-topic
 *   concern as the courseid/coursename fixes in v26.6.3/v26.6.4, just
 *   inside the export path instead of the on-screen matrix), and the
 *   local_nvqportfolio summary PDF now checks that ONE course's
 *   Assessment Plan directly instead of every distinct course the
 *   student has ever had one on. export.php now requires courseid as a
 *   parameter and checks :exportportfolio against that specific course
 *   context rather than "any one enrolled course" - tightened to match,
 *   since the old "any course is enough" permission model would
 *   otherwise let someone with the capability on an unrelated course
 *   export a student's data from a course they hold no role on at all,
 *   once the export itself became course-specific. view.php's export
 *   button now only renders once a specific course has actually
 *   resolved, and passes it through.
 *
 *   Fixed alongside this: build_portfolio_summary_pdfs()'s skip-if-
 *   empty check tested the final RENDERED HTML STRING
 *   (`trim($body) === ''`), which local_nvqportfolio's renderer never
 *   actually returns empty - it always emits headers and "No assessment
 *   plan" / "No sampling plans" / "No sampling records" text even with
 *   zero real data. A student with nothing recorded for a course still
 *   got a downloadable (near-blank) PDF bundled into their export. Now
 *   checks for a genuine Assessment Plan row, Sampling Plan, or
 *   Sampling Record directly before rendering anything at all.
 *
 *   No schema or capability change - release-string/version bump only.
 *
 * v26.6.4 (1.20.4) — Follow-up to v26.6.3's shared-unit courseid fix,
 *   reported live immediately after that deploy: the grade/sample/
 *   comment courseid was fixed, but a SEPARATE map feeding the course
 *   NAME shown inside each unit's expanded body -
 *   $topiccoursemap (singular "course", easy to miss alongside
 *   $topiccourseidmap right next to it) - was missed. Same root cause:
 *   it deliberately keeps the alphabetically-first course name for a
 *   topic shared across more than one course/pathway (the query orders
 *   by c.fullname ASC and keeps the first match), so a unit shared
 *   between two pathways of the same qualification (client's shared
 *   competence-library design, one topic satisfying multiple pathways)
 *   always displayed the wrong pathway's name inside the unit body,
 *   regardless of which course was actually being viewed. Confirmed
 *   live on two separate course pairs and both an archived and a
 *   normally-enrolled student, ruling out an archived-student-specific
 *   cause. Fixed the same way as v26.6.3's courseid fix: use the
 *   current course's own name ($courseidnamemap[$courseid], keyed
 *   directly by courseid so it carries no cross-course ambiguity)
 *   whenever $oncoursepage is true; $topiccoursemap now only used on
 *   the unscoped all-courses view, same condition as the courseid fix.
 *
 *   No schema or capability change - release-string/version bump only,
 *   bumped anyway (not left at v26.6.3's release string) so the
 *   deployed version is unambiguous in Site Administration after the
 *   v26.6.3 deploy/redeploy confusion this fix follows.
 *
 * v26.6.3 (1.20.3) — Full plugin audit (permissions/grading, delete
 *   archived, upgrade path, assign-assessor, notifications, styles,
 *   privacy, backup/restore, group-scoping) requested by Lucero after
 *   the grading permission error on a shared-unit course couldn't be
 *   traced by hand any further. Seven real, independently-confirmed
 *   findings fixed or documented here:
 *
 *   (1) delete_archived.php — the actual root cause of "Delete
 *       Archived still failing" going back to v26.6.1: the catch
 *       block called $transaction->rollback($e), which by Moodle's own
 *       design re-throws $e immediately as part of
 *       force_transaction_rollback(). Every line after it in that
 *       catch block - debugging(), $response['error'],
 *       $response['debugmessage'] added in v26.6.2 specifically to
 *       diagnose this - was therefore dead code, unreachable. The
 *       re-thrown exception propagated uncaught, so the browser got
 *       Moodle's default HTML error page instead of this endpoint's
 *       JSON, which is exactly why the v26.6.2 diagnostic could never
 *       surface anything. Fixed by building the full response first,
 *       then rolling back inside its own nested try/catch so its
 *       re-throw can't swallow the response already built.
 *
 *   (2) matrix_data.php build() — grade/sample/comment buttons for a
 *       unit shared across more than one course silently submitted the
 *       WRONG course id. $topiccourseidmap (built for an unrelated
 *       final-status dedup purpose, see its own declaration comment)
 *       deliberately resolves to the LOWEST course id a topic is
 *       linked to, but that same value was reused as the grading
 *       action's course context - so a teacher with a role in the
 *       course actually being viewed, grading a shared unit, had their
 *       capability checked against a DIFFERENT course entirely, most
 *       often one they hold no role in. Root cause of the "no
 *       permission to grade this student" error reported live during
 *       this audit (course 12, topic 65, shared with course 10). Fixed
 *       by using the actual $courseid parameter directly whenever
 *       $oncoursepage is true; $topiccourseidmap is now only used for
 *       its original unscoped/all-courses purpose.
 *
 *   (3) matrix_data.php notify_assessor_of_submission() — the deep
 *       link sent to an assessor when a student submits evidence only
 *       included nvq_matrix_student, not nvq_matrix_course. view.php
 *       explicitly treats a bare student param with no matching course
 *       param as unresolved and falls back to the idle "no student
 *       selected" screen (the same enforcement that closed the
 *       combined-view leak in v26.4.24-26) - so every submission
 *       notification was landing assessors on an empty picker instead
 *       of deep-linking to the student's matrix. Both params are now
 *       included.
 *
 *   (4) classes/privacy/provider.php — block_nvq_matrix_assessor
 *       (userid, setby) and block_nvq_matrix_cleared_archive
 *       (studentid, clearedby) both store direct user references but
 *       were completely absent from this provider; a GDPR export or
 *       right-to-be-forgotten request would have silently missed them.
 *       Added to get_metadata(), get_contexts_for_userid(),
 *       get_users_in_context(), export_user_data() (exported
 *       alongside their author, not subject-deleted - neither table
 *       holds free-text content "about" a person the way grades/
 *       comments do, they're purely administrative/audit records,
 *       same treatment this provider already gives every other
 *       author-attribution column), and
 *       delete_data_for_all_users_in_context() (full course deletion
 *       still removes both tables' rows for that course, as before -
 *       only the personal subject-erasure methods leave them in place).
 *
 *   (5) delete_archived.php — a group-restricted Company Manager's
 *       visibility into the archived list was enforced ENTIRELY by
 *       view.php's display logic (the "actual leak this fix exists
 *       for" comments from v26.4.24-26). This AJAX endpoint never
 *       independently re-verified group membership server-side, even
 *       though its own docblock states the opposite philosophy
 *       ("never trust a client-supplied studentid/courseid pair
 *       without re-checking server-side") for the archived-status
 *       check right next to it. A Company Manager holding
 *       :deletearchived could submit ANY studentid/courseid pair
 *       directly, bypassing the UI, and delete another company's
 *       archived data. Out of the box this was latent rather than
 *       live - :deletearchived's archetype list only grants
 *       editingteacher/manager, not teacher (Company Manager's
 *       archetype) - but it's exactly the scenario the deferred
 *       upgrade step from v26.6.0 was meant to close. Fixed two ways:
 *       a defensive group-membership re-check added directly to this
 *       endpoint (belt-and-braces, matches the endpoint's own stated
 *       philosophy), plus the deferred Company Manager Prevent
 *       override for :deletearchived finally added below.
 *
 *   (6) Backup/restore — DOCUMENTED, not code-fixed (see
 *       block_nvq_matrix.php's class docblock for the full
 *       explanation): applicable_formats() only allows 'my' (the
 *       Dashboard), so this block can never actually be added to a
 *       course. Moodle's course backup only serializes block instances
 *       present in that course's own context, so a standard
 *       backup_block_task/restore_block_task implementation would
 *       never actually be invoked - it would be dead code, not a real
 *       fix. This plugin's data (grades, sampling, comments, status,
 *       assessor assignments) does NOT survive a course backup/
 *       restore, silently and without warning. Client decision:
 *       document this clearly rather than change the block's
 *       architecture or build a separate export/import tool.
 *
 *   (7) Two capability description strings were missing from the lang
 *       file entirely - block/nvq_matrix:finalstatus and
 *       block/nvq_matrix:manageassessor - both introduced in v26.6.0
 *       but never given a matching lang string, causing a
 *       "Invalid get_string() identifier" debugging warning on every
 *       role-definition/check-permissions page load. Added.
 *
 *   REAL $plugin->version bump (new capability-prevent upgrade step for
 *   :deletearchived) - not a release-string-only release like most
 *   entries in this file.
 *
 * v26.6.2 (1.20.2) — Delete Archived still failing after v26.6.1's
 *   rollback-logic fix and confirmed DB upgrade - the wrapper-hiding
 *   fix DID work (button correctly hidden until "Show Archived" is
 *   ticked, per Lucero), but the deletion itself still errors with no
 *   visible cause. Rather than guess a third time, the real exception
 *   message is now surfaced directly: added to the JSON response as
 *   'debugmessage' (safe here - this endpoint is only ever reachable
 *   past the block/nvq_matrix:deletearchived capability check, so
 *   there's no meaningful disclosure risk in showing a privileged user
 *   the real cause instead of sending them back to guess from a generic
 *   message again), and shown in the browser alert() alongside the
 *   normal error text. No functional change to the delete logic itself
 *   - this version exists purely to get a real, actionable error
 *   message on the next test instead of another blind guess.
 *   No schema/capability change - release-string/version bump only.
 *   Verified: PHP brace/paren/bracket balance, extracted <script> block
 *   re-validated with node --check.
 *
 * v26.6.1 (1.20.1) — REAL BUG FIXES, reported by Lucero after testing
 *   v26.6.0's Delete Archived feature live:
 *   (1) Delete always returned "Error deleting this data" (generic
 *       catch-all). Most likely cause: the delete DB transaction reads
 *       the NEW block_nvq_matrix_cleared_archive table (v26.6.0), which
 *       only actually exists after the real Moodle DB upgrade runs
 *       (Site administration -> Notifications), not just from pulling
 *       the git files - a real schema change needs that step, unlike
 *       most of this branch's earlier release-string-only bumps.
 *       Separately, also found and fixed a REAL bug in the error
 *       handling itself while investigating: the catch block checked
 *       $transaction->is_disposed() before rolling back - not an actual
 *       moodle_transaction method - risking a second, undefined-method
 *       error masking whatever the original failure was. Simplified to
 *       the standard rollback() call, and added debugging() logging of
 *       the real exception message server-side (visible in Moodle's
 *       error log / on-screen if debugging is enabled) instead of only
 *       ever showing the same generic string - needed to actually
 *       diagnose a failure like this rather than guessing blind.
 *   (2) The Delete button was visible on archived rows even while the
 *       "Show Archived" toggle was OFF. Root cause: the picker's
 *       show/hide filter (initStudentPicker() in matrix.mustache) sets
 *       `hidden` on the row's <a> element directly - but v26.6.0 wraps
 *       an archived-with-permission row's <a> together with its Delete
 *       button in a new .nvq-student-row-wrap div, so the button (a
 *       SIBLING inside that wrapper) was never actually covered by
 *       hiding just the <a> next to it. Fixed by hiding the wrapper
 *       (row.closest('.nvq-student-row-wrap') || row) instead of
 *       always the row itself.
 *       While fixing this, proactively also added
 *       .nvq-student-row-wrap[hidden] to this file's own existing
 *       [hidden]-override list (see the "BUG FIX" comment block near
 *       .nvq-student-list[hidden] etc.) - this new wrapper sets
 *       `display: flex` via a plain class selector, which is exactly
 *       the documented failure mode already caught twice before in
 *       this same file (v26.5.0's gap-mode fix, and the original
 *       student-list fix this pattern is named after) - fixed
 *       preemptively here rather than waiting to hit it live a third
 *       time.
 *   No schema/capability change - release-string/version bump only.
 *   Verified: PHP brace/paren/bracket balance, and the extracted
 *   <script> block re-validated with node --check (real JS syntax
 *   parsing, not brace-counting) after the filter-logic edit.
 *
 * v26.6.0 (1.20.0) — REAL capability + schema bump (not just release-
 *   string) - two new pieces requested together:
 *   (1) Delete Archived: a new "Delete" button on each archived-student
 *       row (gated on a NEW capability, block/nvq_matrix:deletearchived
 *       - editingteacher/manager only, deliberately separate from
 *       :viewall since seeing the archived list doesn't mean being
 *       allowed to permanently destroy it) permanently removes that
 *       student's block_nvq_matrix data for that course: grades,
 *       sampling, unit comments, final status, and their evidence
 *       comments/types (resolved via the same three-table exacomp join
 *       chain used elsewhere in this plugin, since evidence_comments has
 *       no courseid/topicid column of its own - only mmid). New
 *       standalone endpoint delete_archived.php independently
 *       re-verifies the target is genuinely archived (not actively
 *       enrolled) server-side before deleting anything, regardless of
 *       what the request claims - this plugin has already been burned
 *       by scoping bugs in this exact area (v26.5.3-v26.5.6) and this
 *       action is irreversible.
 *   (2) A student's own archived-course switcher (v26.5.5) is based
 *       purely on exacomp/exaport evidence presence, which the delete
 *       action above deliberately never touches (this plugin never
 *       modifies third-party data) - so a "deleted" archived course
 *       would otherwise keep showing on the student's own switcher
 *       forever. New table block_nvq_matrix_cleared_archive
 *       (studentid+courseid unique) records that a course was cleared;
 *       written by delete_archived.php, read by view.php's student-side
 *       archived detection to exclude it. If the student is later
 *       actively re-enrolled, this marker has no effect on the ACTIVE-
 *       enrolment detection path - it only ever suppresses the archived
 *       one.
 *   KNOWN GAP CAUGHT AND FIXED DURING THIS BUILD: initially wrote an
 *   upgrade.php step assigning CAP_PREVENT for the new
 *   :deletearchived capability on the companymanager role, in the SAME
 *   version bump that introduces the capability itself - would have
 *   silently failed, since update_capabilities() (which actually
 *   registers a new capability into mdl_capabilities) only runs AFTER
 *   the whole upgrade function returns, so the capability doesn't exist
 *   in the database yet at the point that code would run. Removed;
 *   companymanager-prevention for this specific capability is deferred
 *   to the next version bump, mirroring this file's own established
 *   precedent (the 2026082101 step is itself a dedicated LATER step for
 *   capabilities introduced in earlier versions, never the same version
 *   that introduced them).
 *   SEPARATE BUG CAUGHT AND FIXED DURING THIS BUILD: a str_replace edit
 *   to install.xml accidentally consumed part of the
 *   block_nvq_matrix_evidence_types table's own opening tag while
 *   inserting the new table ahead of it, leaving invalid XML (a
 *   <FIELDS> block with no enclosing <TABLE> opener). Caught by
 *   actually parsing the file with Python's xml.etree (not just brace-
 *   counting, which can't detect XML tag-level corruption) - fixed, and
 *   confirmed all 9 tables now parse correctly.
 *   Also fixed in the same round (client-owned local_nvqportfolio, NOT
 *   this plugin, delivered as a separate file since it's a different
 *   git repo): local_nvqportfolio_can_view_student()'s :viewown check
 *   returns false once a student is fully unenrolled (unenrolment
 *   typically removes the role assignment it was granted through, not
 *   just the enrolment record), so an archived student's own Assessment
 *   Plan/Sampling Plan/Sampling Record links disappeared entirely -
 *   fixed with a fallback presence check against that plugin's own
 *   local_nvqport_ap/sp/sr tables, keeping the dependency direction
 *   one-way (this plugin already calls into that one, never the other
 *   way round).
 *   Also fixed in matrix_data.php's get_portfolio_links(), same root
 *   cause: is_enrolled(..., true) (active-only) gated the links, now
 *   also allows a course with historical presence in this plugin's own
 *   four tables - same signal as the v26.5.6 final-status-box fix.
 *   Verified: PHP brace/paren/bracket balance on every touched file;
 *   install.xml re-parsed with a real XML parser (Python xml.etree),
 *   not just brace-counting, after the corruption above was caught and
 *   fixed; confirmed $USER is available in delete_archived.php's
 *   top-level script scope; confirmed the cleared-archive exclusion in
 *   view.php only affects the archived-detection path, never the
 *   active-enrolment one.
 *
 * v26.5.6 (1.19.6) — REAL BUG FIX, reported by Lucero after testing
 *   v26.5.5 live: a student's Pass/Fail final-status box displayed fine
 *   for an active/enrolled course but disappeared entirely for an
 *   archived one. Genuinely pre-existing (not introduced by v26.5.0-
 *   v26.5.5), only now exposed because the archived-students feature
 *   only recently started actually getting exercised this directly.
 *   Root cause: matrix_data::build()'s final-status-box course list was
 *   scoped with an ACTIVE-enrollment-only filter (added originally to
 *   stop a status box appearing for a course a unit merely happens to
 *   be linked to, that the student was never really on at all) - so the
 *   moment a student is archived (unenrolled) from a course, this
 *   filter silently emptied their status box for it too, even though
 *   their actual block_nvq_matrix_status row is completely untouched by
 *   unenrollment (confirmed, v26.4.13) exactly like grade/sampling/
 *   comment rows are.
 *   Fixed by keeping the original protective intent but widening what
 *   counts as "genuinely this student's course": now kept if EITHER
 *   actively enrolled OR the student has real historical presence there
 *   (a row in block_nvq_matrix_grades/sampling/unit_comments/status) -
 *   the exact same four-table signal already trusted for the archived-
 *   students feature itself (view.php). A course the student was truly
 *   never on (the original bug this filter existed to prevent) still
 *   correctly has no box, since presence requires an actual row, not
 *   just the unit happening to be linked to that course too.
 *   No schema/capability change - release-string/version bump only.
 *   Verified: PHP brace/paren/bracket balance; confirmed all four table
 *   names/columns match the exact shape already used successfully
 *   elsewhere in this file and in view.php's own archived-detection
 *   query; confirmed each of the four get_in_or_equal() calls uses its
 *   own distinct placeholder prefix (fscg/fscs/fscc/fscf) and each
 *   studentid parameter its own distinct name (fscsid1-4), per this
 *   plugin's own established v26.4.16 rule about never reusing one
 *   get_in_or_equal() result's placeholders within a single query.
 *
 * v26.5.5 (1.19.5) — REAL BUG FIX, reported by Lucero after testing
 *   v26.5.3's course-scoping fix live: a student's OTHER course had
 *   stopped appearing on their own matrix page at all. Root cause: the
 *   query added in v26.5.3 to detect "which courses does this student
 *   have a matrix for" only matched courses where the student already
 *   had EVIDENCE linked (eportfolioitem=1 rows) - so a course they were
 *   freshly enrolled in, with a matrix set up but zero evidence
 *   submitted yet, looked indistinguishable from not being enrolled
 *   there at all, and silently never made it into the course switcher.
 *   Fix: course detection is now enrolment-based first
 *   (enrol_get_users_courses($studentid, true) - Moodle's own active-
 *   enrolment API, not hand-rolled SQL) intersected with which courses
 *   actually have an NVQ structure mapped (block_exacompcoutopi_mm),
 *   deliberately NOT gated on having any evidence yet.
 *   This raised a second, related question during review (asked by
 *   Lucero directly: "what happens to the student view under
 *   archive?") - an enrolment-only fix would have made a course the
 *   student was LATER unenrolled from disappear from their own switcher
 *   entirely, hiding their own historical portfolio from themselves.
 *   Fixed by keeping the v26.5.3 evidence-based query too, as a second
 *   source: any course with evidence but no longer in the active list
 *   is marked archived (isarchived) and still shown in the switcher,
 *   visually muted with an "(Archived)" label (reusing the existing
 *   statusarchived string) rather than either disappearing or looking
 *   identical to an active course - this is the student's own
 *   equivalent of the teacher-side archived-students feature (v26.4.13),
 *   just seen from the student's own point of view instead of a
 *   viewall assessor's. Default course selection prefers an active
 *   course over an archived one whenever the student has both.
 *   Deliberately scoped to the plain-student branch only, per explicit
 *   instruction not to alter other role views - the canviewall/company-
 *   manager branch (and its own separate archived-detection fixed in
 *   v26.5.4) is untouched by this change.
 *   No schema/capability change - release-string/version bump only.
 *   Verified: PHP brace/paren/bracket balance, mustache section balance,
 *   confirmed enrol_get_users_courses() is a standard Moodle core
 *   function (lib/enrollib.php) with no capability check needed for a
 *   user viewing their own enrolments, and traced that this entire
 *   change sits inside the `else` (non-canviewall) branch only.
 *
 * v26.5.4 (1.19.4) — REAL BUG FIX, reported by Lucero after testing
 *   v26.5.3: company managers could see archived (unenrolled) students
 *   who were never in their own group, and separately, some students
 *   who WERE still enrolled - just in a different group - were wrongly
 *   showing up as archived at all. Two distinct bugs in the same
 *   archived-detection code, both matching the "list correctly scoped,
 *   detail view underneath not" leak pattern already seen once before
 *   in this plugin (v26.4.25).
 *   Bug 1 (misclassification): the "is this student currently enrolled"
 *   check reused $studentcourseids, which is built from the enrolled
 *   list AFTER company-manager group filtering already removed anyone
 *   outside the viewer's own group. So a student enrolled but simply in
 *   a DIFFERENT group looked "not currently enrolled" from that check's
 *   point of view, and got shown as archived (unenrolled) - even though
 *   they were never unenrolled at all, just invisible to this
 *   particular manager. Fixed with a new $trueenrolledcourseids, built
 *   from the RAW enrolled list before any group filtering, used only
 *   for this specific "genuinely not enrolled" check.
 *   Bug 2 (the actual cross-company leak): the archived-detection query
 *   itself had NO group scoping at all - it scanned every student with
 *   leftover grade/sampling/comment/status rows across every course the
 *   viewer holds :viewall on, so a genuinely unenrolled student from an
 *   UNRELATED company could still surface in any company manager's
 *   archived list. Fixed by pre-fetching each group-restricted viewer's
 *   own current group membership per course ($viewergroupidsbycourse ->
 *   $viewergroupmembersbycourse, one query per course, not per archived
 *   student - avoids an N+1 pattern), then excluding any archived
 *   candidate not found in it.
 *   Deliberately fails CLOSED, not open: if an archived student's group
 *   membership can no longer be verified at all (their groups_members
 *   row may itself have been cleaned up on unenrollment), they're
 *   EXCLUDED from the company manager's archived list rather than
 *   shown - matching this plugin's already-established v26.4.24 rule
 *   ("no group on a course = sees nobody") rather than inventing a new,
 *   looser exception for the archived case specifically. A full
 *   teacher/admin viewer (not group-restricted) sees archived students
 *   exactly as before - both fixes only take effect when the current
 *   viewer is a company manager on that specific course.
 *   No schema/capability change - release-string/version bump only.
 *   Verified: PHP brace/paren/bracket balance; traced every remaining
 *   read of $studentcourseids after the fix to confirm the two
 *   deliberately-still-group-filtered uses (picker-row resolution,
 *   active-student-row building) were left untouched and only the
 *   archived-detection "currently enrolled" check was switched to the
 *   new unfiltered variable; confirmed $viewergroupmembersbycourse is
 *   fully built before the conditional block that reads it, so no
 *   undefined-variable risk.
 *
 * v26.5.3 (1.19.3) — REAL BUG FIX (not in original scope, reported by
 *   Lucero after testing v26.5.2): a student enrolled in more than one
 *   NVQ-mapped course had every course's units blended into a single
 *   matrix on their own "My Matrix" page, and Overall/Assessor progress
 *   computed across both courses combined instead of per-course. Root
 *   cause: view.php's plain-student branch always passed
 *   $resolvedcourseid = 0 into matrix_data::build(), and build()'s own
 *   topicid resolution treats oncoursepage=false (0 is falsy) as "no
 *   specific course requested" — the same unscoped, all-courses query
 *   path that's CORRECTLY used for the idle/legacy case, but wrong for
 *   a student who actually has more than one course's data. Assessors/
 *   IQAs never hit this - their per-student+course selector rows always
 *   resolved a real courseid already.
 *   Fix: the plain-student branch now looks up which courses this
 *   student actually has NVQ competence data on (same 2-table exacomp
 *   join as build()'s existing unscoped fallback query, plus a third
 *   join to pull courseid out of it), and always resolves to ONE real
 *   course - defaulting to the alphabetically-first when none is
 *   explicitly chosen, exactly like a one-course student already had
 *   implicitly. When there's more than one, a small course-switcher
 *   (plain pill links, ?nvq_matrix_course=X) now appears above the
 *   portfolio panel so the student can move between them - each course
 *   now renders its own separate matrix and its own separate Overall/
 *   Assessor progress, never blended.
 *   Verified NOT a new information-leak risk: the scoped topicid query
 *   this now routes students through is the SAME one already shipped
 *   and trusted for the assessor/IQA per-student+course view - it scopes
 *   by course structure only, with actual evidence/grade data always
 *   separately filtered by $studentid everywhere downstream, unchanged.
 *   Also verified: $studentcourseids/$coursenamesbyid are re-declared
 *   inside the plain-student branch with a genuinely different shape
 *   than the canviewall branch's version of the same variable names -
 *   grepped every read of both across the whole file to confirm the two
 *   branches never cross-read each other's shape (they're mutually
 *   exclusive per request, but worth checking given the name reuse).
 *   No schema/capability change - release-string/version bump only.
 *   Verified: PHP brace/paren/bracket balance (view.php), mustache
 *   section balance, div/anchor/span tag balance in the template, CSS
 *   brace balance.
 *
 * v26.5.2 (1.19.2) — Gap Analysis panel shrunk further: from the
 *   full-width dashboard card (v26.5.1) down to a small circular tile
 *   (count inside the circle, label beside it) grouped into a new
 *   compact .nvq-dashboard-row alongside a new Export Portfolio tile.
 *   Export Portfolio moved from view.php's page-level topbar link (a
 *   plain html_writer::link with an inline onclick=confirm(...)) into
 *   this same dashboard row inside the matrix template — same
 *   export.php destination, same capability/student gating, same
 *   confirm-dialog message, just relocated and re-rendered as an icon+
 *   label tile. The confirm dialog itself moved from an inline onclick
 *   attribute to a data-confirm attribute read by a JS click listener,
 *   since a compiled mustache template can't cleanly embed a PHP-built
 *   inline handler string.
 *   $templatedata gains three new keys, set in view.php AFTER
 *   matrix_data::build() returns (not inside build() itself, since
 *   export capability/URL building is page-level, not matrix-data):
 *   showexportbutton, exporturl, exportconfirmmsg. Also
 *   showdashboardrow = hasunits || showexportbutton, so the row (and
 *   therefore the export tile) still renders even when hasunits is
 *   false — matching the OLD topbar link's gating, which only checked
 *   capability + a selected student, never hasunits. Getting this
 *   wrong would have been a real regression: nesting the whole row
 *   under {{#hasunits}} would silently hide Export Portfolio for any
 *   student with zero unit content, something the old topbar link
 *   never did.
 *   No schema/capability change — release-string/version bump only.
 *   Verified: mustache section balance, div/button/anchor tag balance,
 *   CSS brace balance, PHP brace/paren/bracket balance on view.php too
 *   (not just the template/CSS/lang files touched in v26.5.0/26.5.1),
 *   and a repo-wide grep confirming zero remaining references to every
 *   class name this round retired (.nvq-gapmode-dashboard and its
 *   .nvq-gapmode-dash-* children, .nvq-export-link) in any .php,
 *   .mustache, or .css file.
 *
 * v26.5.1 (1.19.1) — Gap Analysis panel redesigned from a small pill
 *   toggle-button into a full-width dashboard stat card (icon + count +
 *   label), moved to sit as the very first element inside the matrix
 *   content, above Overall/Assessor progress (same general area as
 *   before, just restyled and made the leading element). Still a real
 *   <button> when there are gaps to filter (nvqToggleGapMode() unchanged
 *   in behaviour — only its DOM structure/CSS classes moved from
 *   .nvq-gapmode-toggle-label/.nvq-gapmode-bar/.nvq-gapmode-badge to
 *   .nvq-gapmode-dash-label/.nvq-gapmode-dashboard/.nvq-gapmode-dash-*),
 *   kept as a button rather than a div specifically so it stays
 *   keyboard/screen-reader operable despite the "not a button" visual
 *   request — it still performs an action. Zero-gaps state now renders
 *   as a separate static (non-interactive) success-styled panel instead
 *   of a disabled/empty badge, since there's nothing left to filter.
 *   Assessor progress bar colour changed from info (blue) to success
 *   (green) per request — one-line CSS var swap on .nvq-assessor-bar.
 *   No schema/capability change — release-string/version bump only.
 *   Verified: mustache section balance, div/button tag balance (same
 *   pre-existing 1-div file-wide imbalance as v26.4.26/v26.5.0,
 *   unrelated to this change), CSS brace balance, and grep confirmed no
 *   leftover references anywhere to the old class names the JS/CSS
 *   could still be pointing at.
 *
 * v26.5.0 (1.19.0) — Two new read-only display features, both built as
 *   pure derivations of data this plugin already fetches and already
 *   renders elsewhere on the page — neither adds a query, a table, or a
 *   capability, so this is a release-string/version bump with no schema
 *   or capability change.
 *
 *   (1) Gap Analysis toggle: a button above the Overall progress bar that
 *       filters the matrix down to only what's missing evidence. Units
 *       with zero gaps are hidden entirely; units with gaps are
 *       force-expanded and, within them, only the ungraded/unevidenced
 *       criterion rows stay visible (any LO group left empty by that
 *       filtering is hidden too). Implemented as a loop-based JS toggle
 *       (nvqToggleGapMode()) over DOM state that's already correct —
 *       every criterion row already carries the .nvq-row-gap class
 *       server-side (matrix.mustache, pre-existing), and each unit card
 *       now also carries data-gaps="{{unitgaps}}" (matrix_data.php::build(),
 *       unitcriteria - unitmet, arithmetic only, no new query). This
 *       deliberately avoids CSS :has()-based filtering in favour of an
 *       explicit loop, matching how nvqToggleUnit()/toggleEditMode()
 *       already work in this file. Visible to every viewer (assessor,
 *       IQA, and the student themselves) since it's read-only and
 *       doesn't depend on any edit capability.
 *       BUG AVOIDED, not just fixed: styles.css already documents (see
 *       the "BUG FIX" comment near .nvq-student-list[hidden] etc.) that
 *       the `hidden` attribute alone doesn't hide an element once any
 *       author rule gives it an explicit `display` — and the existing
 *       "Responsive — stack columns" media query does exactly that to
 *       .nvq-criteria-table and its rows at <=500px. Added explicit
 *       [hidden] overrides for .nvq-criteria-table, .nvq-criterion-row,
 *       .nvq-unit-card, and .nvq-lo-header up front, before this ever
 *       shipped, rather than waiting to hit it on a phone in the field.
 *   (2) Assessor progress bar: a second bar directly under the existing
 *       Overall (evidence) progress bar, showing what share of this
 *       student's units have actually been GRADED (a Competent/Not Yet
 *       Competent verdict saved), independent of how much evidence has
 *       been submitted. Deliberately unit-level, matching this plugin's
 *       existing unit-level grading model (v17+): "5 units, 4 graded =
 *       80%", not a criterion count. Computed by looping the already-built
 *       $unitsdata array in matrix_data.php::build() and counting
 *       'gradeisset' (already merged into every row by the existing
 *       build_unit_grade_row()) — no new query, and it can never disagree
 *       with what each unit's own grade badge already shows.
 *
 * v26.4.26 (1.18.3) — CHANGE: portfolio export now includes ONLY evidence
 *   linked to a competence - the "unlinked evidence" section (assessment
 *   intros, untagged uploads, etc.) is removed entirely, per client
 *   decision. This directly reverses the earlier choice from the export
 *   feature's original build (v26.4.6-.7 era, see the "Matrix_Overview/
 *   Unlinked_Evidence.html" entries further down this changelog) - that
 *   choice is now superseded, not still in effect.
 *   Removed: fetch_unlinked_items() entirely (was dead once its only
 *   caller was cut), the Unlinked_Evidence.html page generation block,
 *   the index page's link to it, and the unlinkeditems loop in
 *   collect_evidence_files(). 'unlinkeditems' dropped from
 *   build_matrix_tree()'s return shape and docblock - nothing else in
 *   the plugin read that key, confirmed via a full-codebase grep before
 *   removing it, so this is a clean cut with no dangling references.
 *   Zip contents are now exactly: Portfolio_Summary.pdf, Matrix_Overview/
 *   (index + one page per unit, no separate unlinked page), Evidence/
 *   (only files actually linked to a competence).
 *   No schema/capability change - release-string bump only, but the
 *   plugin version still bumps per this file's convention for any real
 *   behavioural fix, not just capability/table changes.
 *
 * v26.4.25 (1.18.2) — FIX: student picker rows now open the SPECIFIC
 *   course they belong to, not a combined view of every course that
 *   student is on. Root cause: view.php already split a multi-course
 *   student into one row per course (v26.3.14), but every row still
 *   called matrix_data::build() with oncoursepage=false/courseid=0 -
 *   the split was list-only, purely cosmetic, and clicking any row for
 *   that student always opened the exact same all-courses-combined page
 *   regardless of which row was clicked. Beyond the UX complaint, this
 *   was a real data-exposure gap: a viewer (teacher, assessor, IQA/EQA,
 *   or a Company Manager under the new group-scoped picker from
 *   2026082101) could open a student's matrix via a course row they
 *   legitimately have access to, and see that same student's data from
 *   OTHER courses they have no visibility into, if the student happened
 *   to also be enrolled there.
 *   Fixed: each row's URL now carries its own courseid
 *   (?nvq_matrix_course=id alongside the existing ?nvq_matrix_student=id).
 *   Resolution now requires BOTH params to validate together - courseid
 *   must be one this specific student is actually enrolled in (or
 *   archived on) within the viewer's own permitted course set - else the
 *   page returns to idle state rather than falling back to showing
 *   everything, closing the same gap against direct URL editing too.
 *   $isactive (which row shows as selected) updated to match on both
 *   userid and courseid together, since a student's rows are no longer
 *   interchangeable once opened.
 *   A student viewing their own matrix is unaffected - oncoursepage/
 *   courseid stay false/0 for that path exactly as before, still
 *   showing all of their own courses together, since it's their own
 *   data and there was never a scoping concern there.
 *   Second, deeper fix in matrix_data::build() itself: even once a
 *   specific course was requested, the existing topicid-resolution logic
 *   had a silent fallback - if that course's scoped query returned zero
 *   linked topics (e.g. competencies not yet mapped for that course),
 *   it fell through to the same unscoped, all-courses query as the
 *   idle/student-own-matrix path, quietly reopening the exact leak this
 *   fix exists to close. Now that fallback only ever runs when no
 *   specific course was requested at all - a genuinely empty scoped
 *   course resolves to the existing "no data for this course" state,
 *   never widens to every course.
 *   No schema/capability change - release-string bump only, but the
 *   plugin version still bumps per this file's convention for any real
 *   behavioural fix, not just capability/table changes.
 *
 * v26.4.24 (1.18.1) — FIX: multi-tenant Company Manager isolation, per
 *   client request (alexddelearning.com company onboarding). Two parts,
 *   both hardcoded to the 'companymanager' role shortname since this is
 *   a fully custom site plugin:
 *   (1) view.php's own student-listing loop (get_enrolled_users() per
 *   viewall course) had no group-awareness at all - a Company Manager
 *   saw every enrolled student on a shared course, not just their own
 *   company's group, even though core Moodle's forced-separate-groups
 *   mode already restricts the Participants page correctly. This plugin
 *   builds its own list independently, so it needed its own check.
 *   Fixed by detecting the companymanager role via get_user_roles() and,
 *   when present, intersecting $enrolled against groups_members for the
 *   viewer's own group(s) on that course. Deliberately strict: a Company
 *   Manager with no group assigned on a given course sees nobody there,
 *   not a fallback to full visibility - confirmed with client. This also
 *   closes a direct-URL bypass for free: $studentid only ever resolves
 *   from $students/$archivedusers, both fed by this same filtered loop,
 *   so guessing another company's studentid in ?nvq_matrix_student=id
 *   simply fails to resolve rather than needing a separate check.
 *   (2) Company Manager's archetype (teacher) meant it silently inherited
 *   more than intended from db/access.php's archetype defaults -
 *   specifically :iqacomment and :exportportfolio, both granted to the
 *   'teacher' archetype for this site's actual IQA/EQA reviewer role.
 *   Client confirmed Company Manager should be view-only. New upgrade
 *   step explicitly Prevents :sample, :grade, :iqacomment, :finalstatus,
 *   :manageassessor, and :exportportfolio on the companymanager role
 *   specifically (looked up by shortname, skipped harmlessly if that
 *   role doesn't exist on a given site) - :viewall is untouched, so
 *   read access is unaffected. Real $plugin->version bump (capability
 *   assignment step), same reasoning as v26.4.10/v26.4.21.
 *
 * v26.4.23 (1.18.0) — NEW FEATURE: settings page (long-deferred from the
 *   original assessor-notification ask). Reviewed every parameter that
 *   feature actually has and cut most as redundant with existing Moodle
 *   admin UI rather than building duplicate settings: the notification
 *   task's polling interval is already editable per-task via Site
 *   administration -> Server -> Scheduled tasks, and the popup/email
 *   channel split is already user-configurable via each person's own
 *   Notification preferences (message provider 'assessorsubmission' is
 *   already registered for that, since v26.4.21). The one setting that
 *   genuinely had no existing Moodle equivalent: whether resubmitting
 *   (re-editing) an already-submitted item should re-notify the
 *   Assessor every time (default, matches all prior shipped behaviour -
 *   an upgrading site sees no change unless an admin opts in) or only
 *   once per item. New setting block_nvq_matrix/renotifyonedit
 *   (settings.php). 'Once per item' mode needed somewhere to record
 *   which items have already fired - new table
 *   block_nvq_matrix_notified_items, only ever read/written when the
 *   setting is off. Real $plugin->version bump (new table), same
 *   reasoning as v26.3.10/v26.4.6/v26.4.10/v26.4.21.
 *
 * v26.4.22 — FIX: assessor-submission notification never fired, and the
 *   dropdown UI was rough. Two changes:
 *   (1) Assessor dropdown moved off view.php entirely, onto its own new
 *   page assessor_manage.php, reached via a small button in the
 *   topbar - client feedback was that the inline widget looked rough
 *   competing for space with the matrix on every page load.
 *   (2) The v26.4.21 event-observer approach never had a working
 *   trigger. This plugin's matrix actually reads evidence from
 *   block_exacompcompuser_mm (joined to block_exaportitem), written
 *   directly by exaport's own item.php
 *   (block_exaport_do_add()/block_exaport_do_edit()) with NO Moodle
 *   event fired at all - confirmed by reading exaport's source
 *   directly; there's even a commented-out, never-finished
 *   \block_exaport\event\item_created block sitting right above one of
 *   the two insert points. \block_exacomp\event\example_submitted (the
 *   v26.4.21 hook) is a real event, but belongs to exacomp's separate
 *   "Examples" sub-feature and was never going to fire for this site's
 *   actual workflow. Replaced classes/observer.php + db/events.php
 *   with a scheduled task (classes/task/notify_assessors_task.php,
 *   db/tasks.php, every 5 minutes) that polls
 *   block_exacompcompuser_mm using its own auto-increment id as a
 *   watermark (config block_nvq_matrix/assessor_notify_lastid) - not a
 *   timestamp, since exaport's insert never populates that table's
 *   timestamp column. Deliberate trade-off, client's choice: touching
 *   neither exaport's nor exacomp's code (both third-party, upgraded
 *   independently) in exchange for near-real-time rather than instant
 *   delivery.
 *   No schema change - $plugin->version bumped anyway per this file's
 *   own stated policy of bumping on any real functional change, same
 *   reasoning as v26.4.6/v26.4.10 for non-schema bumps.
 *
 * v26.4.21 — NEW FEATURE: Assessor designation + submission notification.
 *   IQA and Assessor both map to the same underlying teacher role on
 *   this site, so there was no way to identify "the" Assessor for a
 *   course. Adds a dropdown at the top of the matrix (editingteacher/
 *   manager only, new capability block/nvq_matrix:manageassessor) to
 *   designate one course teacher as Assessor, stored in a new table
 *   block_nvq_matrix_assessor (one row per course). Candidates are
 *   restricted to users who hold block/nvq_matrix:grade in that course
 *   - confirmed requirement, not any enrolled user.
 *   When a student submits evidence, the designated Assessor now gets a
 *   Moodle notification (popup by default, not email - new message
 *   provider 'assessorsubmission'). Hooked via a new observer
 *   (classes/observer.php, db/events.php) on exacomp's own
 *   \block_exacomp\event\example_submitted, fired from exacomp's
 *   example_submission.php right after a student's evidence is saved
 *   and linked to a competence - confirmed by reading that file
 *   directly. \block_exacomp\event\competence_assigned was initially
 *   assumed to be the right hook but was ruled out: it fires from the
 *   teacher-side assign_competencies.php (edulevel LEVEL_TEACHING), not
 *   from a student submission at all.
 *   New table - real $plugin->version bump, same reasoning as
 *   v26.3.10/v26.4.6/v26.4.10.
 *
 * v26.4.20 — CRITICAL FIX: portfolio export was actually corrupting its
 *   own zip output on this site, caught only via a real export attempt
 *   on staging. resolve_user_names() (added v26.4.11 for grade/
 *   sampling/comment/status attribution) selected an incomplete set of
 *   user name fields (id, firstname, lastname, alternatename) -
 *   missing firstnamephonetic, lastnamephonetic, middlename. Moodle's
 *   fullname() needs whatever fields this site's fullnamedisplay
 *   format string actually references, and logs a debugging() notice
 *   for any missing one. That notice isn't just noise: it prints as
 *   HTML output *before* send_zip()'s file-download headers get sent,
 *   which broke with "Cannot modify header information - headers
 *   already sent" and visibly corrupted the download with raw zip
 *   binary bleeding into the page.
 *   Fixed by selecting the same complete field set view.php's
 *   archived-student lookup (v26.4.13) already correctly uses, rather
 *   than a guessed subset. Swept the entire plugin for the same
 *   pattern - every other user-table query either already selects the
 *   full set, uses '*', or omits the fields parameter entirely (which
 *   defaults to '*' in Moodle's get_record()) - confirmed nothing else
 *   has this gap.
 *   Third execution-only bug this line has hit in a row (v26.4.15
 *   parse error, v26.4.16 DB param binding, this one) - none were
 *   remotely visible to manual review or brace/paren/bracket balance
 *   checking; all three needed an actual attempt against a real site to
 *   surface.
 *   No schema/capability change - release string only.
 *
 * v26.4.19 — Bug review pass on v26.4.18. Found a real inconsistency
 *   between view.php and export.php: export.php has a fallback check
 *   against CONTEXT_SYSTEM specifically to still let through a
 *   site-wide Manager who holds this capability at system level
 *   directly (not via course enrolment at all) and therefore has zero
 *   courses for the per-enrolled-course loop to find. view.php's
 *   $canexportportfolio computation had no equivalent fallback - that
 *   user's export button would never render at all, even though
 *   export.php itself would have let them through if they somehow
 *   reached the URL directly. A capability granted but practically
 *   unreachable through the UI. Fixed by adding the same
 *   CONTEXT_SYSTEM fallback check to view.php, so the button is
 *   visible to exactly the same set of users who can actually use it.
 *   Also re-verified: the upgrade step's assign_capability() calls
 *   don't depend on the capability's stored contextlevel metadata (a
 *   UI hint, not an enforced constraint) so running before
 *   update_capabilities() has synced that value is safe; overwrite=true
 *   makes the step idempotent if ever re-run.
 *   No schema/capability change - release string only.
 *
 * v26.4.18 — FEATURE: portfolio export widened to teachers, per client
 *   request following the v26.4.10 "admin-only for now" decision.
 *   block/nvq_matrix:exportportfolio moved from CONTEXT_SYSTEM (which
 *   structurally can never be satisfied by a course-level role
 *   assignment - capabilities cascade downward, system to course to
 *   activity, never upward, confirmed the hard way in v26.4.10) to
 *   CONTEXT_COURSE, the same context level and same per-enrolled-
 *   course loop every other capability on this page already uses.
 *   Archetypes widened from manager-only to teacher, editingteacher,
 *   and manager together.
 *   export.php and view.php both updated to check the capability
 *   across the current user's enrolled courses rather than a single
 *   system-wide check - export.php now mirrors view.php's own
 *   $canviewall-style loop rather than a bare require_capability()
 *   call, since the export endpoint has no single course context of
 *   its own to check against (the export itself always spans all of a
 *   student's courses, not one).
 *   REAL DEPLOYMENT SUBTLETY, addressed with an explicit upgrade step:
 *   update_capabilities() only auto-applies archetype defaults to a
 *   brand-new capability being added for the first time - for an
 *   EXISTING capability whose archetypes list changes on upgrade (this
 *   case), already-assigned roles are not automatically re-granted the
 *   widened access. Without db/upgrade.php's new 2026080701 step
 *   (assign_capability() for every role matching the teacher and
 *   editingteacher archetypes - assign_capability() already resets
 *   each role's own cache internally, no separate call needed), an
 *   upgrading site would see db/access.php now listing teacher/
 *   editingteacher with no actual change in who could export until
 *   someone manually visited Define Roles and granted it by hand. A
 *   fresh install doesn't need this - it already gets archetype
 *   defaults correctly the first time.
 *   SCHEMA/CAPABILITY CHANGE: contextlevel and archetype grants both
 *   changed on an existing capability, plus a genuinely new upgrade
 *   step - real $plugin->version bump, same reasoning as v26.3.10/
 *   v26.4.6/v26.4.10. No table changes.
 *
 * v26.4.17 — Client-reported after live testing v26.4.16 on staging:
 *   an archived student showed correctly, but their final Pass/Fail
 *   status wasn't showing at all. Root cause: the status query driving
 *   the Completed/On-going distinction was scoped only to currently-
 *   enrolled students, and archived rows were built with
 *   'completeddate' hardcoded to an empty string and no status lookup
 *   at all - archived students never had their status fetched, full
 *   stop. Fixed by widening the status query's student id scope to
 *   also cover archived student ids, and moving it to run
 *   unconditionally whenever there is at least one viewall course
 *   (not nested inside "if there are archived students", which would
 *   have broken the existing Completed/On-going distinction for every
 *   ordinary enrolled student on the common case where nobody happens
 *   to be archived - caught and fixed before shipping, not after).
 *   Archived rows now carry their own 'finalstatus' field and render a
 *   second badge (Pass/Fail/Not set) alongside the existing "Archived"
 *   badge, since "archived" describes enrolment state and says nothing
 *   about grading state on its own - a Fail-and-archived student
 *   looked identical to a Pass-and-archived one before this. New
 *   nvq-badge--fail colour (reuses the existing danger token, no new
 *   design tokens needed) and flex-wrap on .nvq-student-row so the
 *   extra badge doesn't overflow narrow viewports.
 *   No schema/capability change - release string only.
 *
 * v26.4.16 — CRITICAL FIX: fatal DB error on the archived-students
 *   picker (v26.4.13), caught only once actually attempted on staging
 *   after v26.4.15's parse-error fix cleared the way: "Incorrect
 *   number of query parameters. Expected 52, got 13." at view.php line
 *   194. Root cause: Moodle's DB parameter binding does NOT support
 *   reusing the same named placeholder across multiple textual
 *   occurrences within one query the way raw PDO does - it expects a
 *   distinct bound value per occurrence in the SQL text, not per
 *   unique name. The archived-detection query reused one
 *   get_in_or_equal() result (13 course ids) across all four UNION
 *   branches - one occurrence per branch, four branches, but only one
 *   set of 13 params supplied - hence 13 x 4 = 52 expected vs. 13 got.
 *   Fixed by generating four separately-prefixed IN-clauses (one per
 *   UNION branch, each with its own params array), combined with +
 *   rather than reused. Swept the rest of the plugin for the same
 *   reuse-within-one-query pattern - matrix_data.php's $topicinsql
 *   also gets reused across three call sites, but each is confirmed to
 *   be its own separate query (one occurrence per query), which is the
 *   safe, standard, idiomatic pattern - only a placeholder repeated
 *   inside a single query string is unsafe. Nothing else in the plugin
 *   does that.
 *   Two fatal errors caught back to back (v26.4.15, this one) only
 *   once real execution became possible on staging - a reminder that
 *   manual review, however careful, cannot substitute for actually
 *   running the code, and that this specific class of DB-layer
 *   behaviour (Moodle's own parameter-binding quirk, not obvious from
 *   PHP syntax alone) is exactly the kind of thing execution catches
 *   and review does not.
 *   No schema/capability change - release string only.
 *
 * v26.4.15 — CRITICAL FIX: fatal PHP parse error, caught only once
 *   actually installed on a live Moodle site (staging) - "syntax
 *   error, unexpected token '*', expecting end of file" at
 *   version.php line 143, breaking moodle_needs_upgrading() for the
 *   ENTIRE SITE, not just this plugin (component::get_all_versions()
 *   parses every plugin's version.php up front). Root cause: this
 *   file's v26.4.11 changelog entry used a star-slash sequence as
 *   shorthand for "by-suffixed and time-suffixed column names" (star,
 *   "by", slash, star, "time") - but a star immediately followed by a
 *   slash is literally PHP's block-comment terminator, so that
 *   shorthand prematurely closed the file-spanning docblock ~1186
 *   lines early. Everything after that point until the next genuine
 *   terminator (the real one at the true end of this docblock) got
 *   parsed as raw PHP instead of comment text, which is nonsense
 *   starting with a bare asterisk - hence the exact error message.
 *   This is exactly the class of bug manual brace/paren/bracket
 *   balance checking (this plugin's standing practice, given no live
 *   PHP install has been available during development) CANNOT catch -
 *   it's a lexical/comment-token issue, not a structural one. Braces,
 *   parens, and brackets all remained perfectly balanced throughout;
 *   nothing short of an actual PHP parser (or install) would have
 *   caught it, and none was available until now.
 *   NOTE FOR FUTURE CHANGELOG ENTRIES: never type a star immediately
 *   followed by a slash anywhere in this file's prose, even inside
 *   quotes describing the problem - PHP's lexer does not care about
 *   surrounding quote marks, only the literal two-character sequence.
 *   (This got caught and fixed three more times while drafting THIS
 *   very entry, describing the bug by literally reproducing it.)
 *   Fixed the one instance, then swept the ENTIRE plugin (every .php
 *   file, not just this one) for the same pattern: the exact typo
 *   signature (a terminator immediately followed by another asterisk)
 *   doesn't appear anywhere else; a broader check confirmed every
 *   comment-closing sequence in every file now sits alone on its own
 *   line (the normal, safe docblock-closing shape) with nothing
 *   embedded mid-line anywhere. One look-alike but actually-harmless
 *   instance in classes/portfolio_export.php (a comment-OPENING-shaped
 *   substring sitting inside an already-open docblock, which PHP's
 *   tokenizer ignores since comments don't nest) was cleaned up
 *   anyway, purely to avoid leaving the same risky prose habit lying
 *   around for later.
 *   No schema/capability change - release string only. This should
 *   have been version-string-bumped as urgently as any capability
 *   change, given it broke the whole site's upgrade check, not just
 *   this plugin - flagging for future reference that a fatal parse
 *   error is its own category deserving the same urgency as a schema
 *   change, even though this specific fix touches no schema at all.
 *
 * v26.4.14 — Bug review pass on v26.4.13's archived-students picker.
 *   Found and fixed:
 *   (1) The enrolled-students loop excludes anyone who themselves holds
 *   :viewall on the course (so a co-teacher enrolled there is never
 *   mistaken for "a student") - the archived-detection query didn't
 *   apply that same exclusion. A student who was later promoted to
 *   teacher on that course, but still has old grade/sampling rows from
 *   before the promotion, would have been incorrectly shown as
 *   "archived"/unenrolled - they're filtered out of $studentcourseids
 *   for the same reason a co-teacher is, which the archived query was
 *   reading as "not currently enrolled" rather than "not a student
 *   anymore". Fixed by caching each viewall course's context
 *   (new $contextbycourseid) and applying the identical
 *   has_capability(':viewall', ..., $sid) check archived detection
 *   already should have mirrored from the enrolled loop.
 *   (2) CSS: .nvq-archived-toggle-wrap's border-top/margin-top divider
 *   styling wouldn't have reliably rendered as a separator - its parent
 *   (.nvq-student-filters) is display:flex;flex-wrap:wrap, so without
 *   an explicit flex-basis:100%, the toggle would just sit beside the
 *   filter chips as another flex item rather than wrapping to its own
 *   line. Added flex-basis:100%.
 *   Confirmed via re-inspection: toggle correctly nests inside
 *   #nvq-filter-dropdown (revealed by the funnel icon, same as the
 *   existing chips) - not a bug, just worth knowing the toggle isn't
 *   visible until that panel is opened, same as the chips it sits next
 *   to.
 *   No new capability/table - release string only.
 *
 * v26.4.13 — FEATURE: archived-students picker view (§0.4's fix #1,
 *   scoped months ago, finally built). Client's real complaint: an
 *   unenrolled student shows "No competence data found" in the picker.
 *   Root-caused properly before building anything, not assumed:
 *   confirmed directly against the real installed block_exacomp AND
 *   block_exaport plugins (client uploaded both) that unenrollment
 *   deletes/touches NOTHING in either plugin's own data - no observer
 *   in either plugin's db/events.php listens for any enrolment event at
 *   all. The actual cause is discovery, not data loss: this plugin's
 *   own picker (get_enrolled_users()) AND exacomp's own dashboard
 *   (block_exacomp_get_exacomp_courses(), is_enrolled()-gated) both
 *   independently stop surfacing an unenrolled student, even though
 *   every row they ever had is untouched. Confirmed this in our own
 *   code too: portfolio_export.php's topic query is userid-only, no
 *   enrolment check, and it already finds data for unenrolled students
 *   fine - the picker was always the only actual blocker.
 *   Client explicitly distinguished this from "completed" (Pass/Fail,
 *   already built, v26.3) - archived means specifically "unenrolled
 *   from a course", nothing to do with grading state.
 *   New "archived" bucket: for each course a teacher has :viewall on,
 *   finds every studentid with any row in grades/sampling/
 *   unit_comments/status for that course who ISN'T in that course's
 *   current get_enrolled_users() result. (evidence_comments
 *   deliberately excluded from detection - no courseid column of its
 *   own, linked via mmid into exacomp's tables instead; the other four
 *   tables are a reliable enough presence signal without that join.)
 *   Client decision: hidden behind a "Show archived" toggle, off by
 *   default, so the normal list doesn't get cluttered. Toggle
 *   auto-checks itself if the currently-open student is only reachable
 *   via an archived row, so their own row isn't invisible while their
 *   matrix is on screen.
 *   THREE BUGS CAUGHT DURING BUILD, all fixed before shipping:
 *   (1) Archived-student detection originally ran AFTER $studentid was
 *   resolved from $students - since an archived student is never in
 *   $students, clicking their row would silently fail to select them
 *   and fall back to the idle "no student selected" state. Moved
 *   detection earlier and widened the isset() check to also cover
 *   $archivedusers.
 *   (2) The whole archived block, and the row-rendering section that
 *   used it, were both gated behind "!empty($students)" - if EVERY
 *   student in a teacher's courses had been unenrolled (the exact
 *   scenario this feature exists for), $students would be entirely
 *   empty and the archived section would never run at all. Render
 *   condition widened to "!empty($students) || !empty($archivedrows)".
 *   (3) The badge label was a binary ternary (completed vs. everything
 *   else labelled "On-going") - an archived row would have shown an
 *   incorrect "On-going" badge. Switched to a match() covering all
 *   three buckets. Also had to make archived rows fully independent of
 *   the existing bucket chips/date-range filter in the JS, not just
 *   add a toggle alongside them - activefilter === 'all' would already
 *   have matched an archived row's data-status via the existing OR
 *   condition, making them appear by default despite the toggle being
 *   off, which is exactly what the toggle was supposed to prevent.
 *   No new capability or table - governed by the existing :viewall,
 *   same scope boundary the rest of the picker already uses. Release
 *   string only.
 *
 * v26.4.12 — Bug review pass on v26.4.11's final-status addition. Found
 *   a real gap: block_nvq_matrix_status is deliberately independent of
 *   topics/grades (an assessor can set final status for a course even
 *   with zero linked evidence/topics there - e.g. status set before
 *   evidence upload, or evidence later removed) - but the export's
 *   "Final status" table was built by scanning $tree['topics'] for
 *   distinct course ids, so a status-only course would silently never
 *   appear at all, even though the data was right there in
 *   $tree['statusbycourse']. Worse, build_matrix_tree()'s early-return
 *   path (student has ZERO eportfolio-linked topics at all) skipped
 *   final-status fetching entirely, so that student's status would be
 *   completely missing regardless of the index-page fix.
 *   Fixed by extracting status fetching into a new, fully self-
 *   contained fetch_final_status() helper - resolves its own user
 *   names AND its own course names, has no dependency on topics
 *   existing - called unconditionally near the top of
 *   build_matrix_tree(), before the topics query, so both the
 *   no-topics early-return and the normal path carry it. The index
 *   page's course list is now the union of topic-linked courses and
 *   statusbycourse's own course ids, using a new $tree['coursenames']
 *   map (superset of every course name needed anywhere in the export)
 *   instead of scanning topics for a name that might not exist there.
 *   Confirmed only classes/portfolio_export.php and version.php
 *   touched - no other file's functionality affected.
 *   No schema/capability change - release string only.
 *
 * v26.4.11 — Client follow-up after the bug review: final Pass/Fail
 *   status wasn't in the export at all, and no comment/grade/sampling
 *   line said who made it or when - just "Assessor comment" with no
 *   assessor. Both fixed:
 *   - Final status (block_nvq_matrix_status) is now queried per course
 *     and shown on Matrix_Overview/index.html as its own "Final status"
 *     summary table above the units table (client's explicit choice of
 *     placement), one row per distinct course, with who set it and when.
 *   - Every grade verdict, grade comment, sampling status, unit
 *     assessor comment, unit IQA comment, and evidence-item comment now
 *     shows "— Name, date" attribution (client's explicit choice: name
 *     + date on everything), sourced from each table's own existing
 *     by/time-suffixed attribution columns (gradedby/timemodified,
 *     commentedby/commenttime, sampledby/timemodified, assessorcommentby/
 *     assessorcommenttime, iqacommentby/iqacommenttime, setby/
 *     timemodified) - no schema change needed, the data was always
 *     there, the export just never read or rendered it.
 *   New resolve_user_names() helper batch-resolves every user id
 *   needed across all these rows in one query rather than one lookup
 *   per row.
 *   Also confirmed classes/privacy/provider.php already fully covers
 *   all five tables including status and every attribution column -
 *   flagged as "unconfirmed" in earlier handover notes, but on review
 *   it's complete and predates this export feature. What it doesn't
 *   cover, because Privacy API isn't the right tool for it, is an
 *   audit trail of who exported which student's portfolio and when -
 *   client decision: not needed for now.
 *   BUG CAUGHT DURING BUILD: an early draft had a new $byline closure
 *   referenced in $rendercomment's use() clause, but defined further
 *   down the function, after $rendercomment - PHP closures capture
 *   use() variables by value at definition time, not lazily, so
 *   $byline would have been undefined at that point. Fixed by moving
 *   $byline's definition to immediately after $esc, before anything
 *   that references it.
 *   No schema/capability change - release string only.
 *
 * v26.4.10 — Testing v26.4.6+ live surfaced that a normal
 *   editingteacher couldn't see the "Export portfolio" button at all.
 *   Root cause: block/nvq_matrix:exportportfolio was declared
 *   CONTEXT_SYSTEM, but a role assigned only at course context (how
 *   teachers get their permissions on this site, same as every other
 *   capability in this plugin) never satisfies a CONTEXT_SYSTEM check -
 *   capabilities cascade downward (system -> course -> activity), never
 *   upward, so only a true site admin or someone explicitly assigned
 *   Manager at system level could ever pass it. Client decision on
 *   seeing this: keep it this way deliberately, admin/site-manager-only
 *   for now, rather than widening to teachers. Removed the now-dead
 *   teacher/editingteacher archetype entries from db/access.php (they
 *   were never actually reachable under CONTEXT_SYSTEM regardless of
 *   being listed), updated the capability's own lang string and
 *   view.php's inline comment to state this is intentional rather than
 *   read as an unfixed bug later. CONTEXT_SYSTEM itself is unchanged -
 *   if a wider audience is wanted later, that requires switching to a
 *   per-enrolled-course CONTEXT_COURSE loop instead (the same pattern
 *   $canviewall/$cangrade/etc. already use), not just editing the
 *   archetypes list under CONTEXT_SYSTEM.
 *   SCHEMA/CAPABILITY CHANGE: archetype grants changed on an existing
 *   capability - real $plugin->version bump, same reasoning as
 *   v26.3.10/v26.4.6: update_capabilities() only re-syncs archetype
 *   defaults on a version increase, not a release-string-only change.
 *   No table changes.
 *
 * v26.4.9 — Client feedback: "Export portfolio" button was sitting right
 *   next to "Back to dashboard" on the left, wanted it at the extreme
 *   right instead. CSS-only fix - .nvq-view-topbar is now a flex row
 *   with justify-content: space-between (back link stays left, export
 *   button pushes to the far right), no view.php markup change needed.
 *   No schema/capability change - release string only.
 *
 * v26.4.8 — Bug review pass on the portfolio export (v26.4.6/.7), before
 *   any further feature work. Found and fixed:
 *   (1) A 'file'-type evidence link was generated unconditionally,
 *   regardless of whether collect_evidence_files() actually managed to
 *   resolve a real stored_file for that item - an orphaned/deleted
 *   upload would produce a link to a path that was never written into
 *   the zip. render_overview_pages() now takes the resolved files map
 *   and only renders a real link when the file was actually found,
 *   otherwise says "(no file available)" plainly instead of a dead link.
 *   (2) Even when a file WAS found, the link was built from the evidence
 *   item's display name (e.g. "Portfolio Evidence 1") rather than the
 *   actual uploaded file's own filename (e.g. "IMG_2034.jpg") - the two
 *   are very often different, and the zip entry itself was always named
 *   using the real filename, so the link and the actual zip entry could
 *   silently mismatch. Now built from the same resolved stored_file's
 *   real filename in both places.
 *   (3) 'note'-type eportfolio items (text-only by design, no file ever
 *   expected) were falling into the same "(no file available)" message
 *   as a genuinely broken file item - misleading, since a note was never
 *   supposed to have a file. Added a dedicated branch that surfaces the
 *   note's own text content (its intro field, tags stripped) instead.
 *   No schema/capability change - release string only.
 *
 * v26.4.7 — Client feedback on v26.4.6's portfolio export: a single
 *   Matrix_Overview.html with every unit's full descriptor/evidence tree
 *   on one page got bulky and rough to go through. Restructured into
 *   Matrix_Overview/ as a small set of pages instead of one long page:
 *   index.html (one row per unit - grade/sampling at a glance - linking
 *   out to that unit's own page), one Unit_<id>_<title>.html page per
 *   unit holding just that unit's own descriptor tree, and
 *   Unlinked_Evidence.html (only present if there's any). The Evidence/
 *   folder itself is unchanged - client confirmed that part was already
 *   working well. The "same file linked to more than one descriptor"
 *   back-pointer logic now has to work across separate HTML files rather
 *   than anchors on one page - handled by tracking each evidence item's
 *   first-occurrence file+anchor together, so a later occurrence on a
 *   different unit's page links to "otherfile.html#anchor" while a later
 *   occurrence on the SAME unit's page still just links to "#anchor".
 *   No database schema changes; no version bump beyond the release
 *   string (portfolio_export.php only, no capability/table changes).
 *
 * v26.4.6 — FEATURE: per-student portfolio export, teacher-only, requested
 *   after the matrix reached feature-parity on grading/sampling/comments.
 *   A new "Export portfolio" button (view.php topbar, shown only once a
 *   student is selected) downloads a single zip containing:
 *     - Matrix_Overview.html — a static unit -> descriptor tree mirroring
 *       the live matrix (grade, sampling status, unit/assessor/IQA
 *       comments, evidence-type tags and comments), with a link to each
 *       linked evidence file. An evidence item linked to more than one
 *       descriptor only gets a real download link on its first occurrence
 *       - later occurrences show a text pointer back to it, to avoid
 *       duplicating the file in the zip. A trailing "Unlinked evidence"
 *       section covers eportfolio items never tagged to any competence
 *       (e.g. an assessment introduction upload).
 *     - Portfolio_Summary_<courseid>.pdf — one per distinct course the
 *       student has an Assessment Plan on (plain Portfolio_Summary.pdf
 *       when there's only one), reusing local_nvqportfolio's existing PDF
 *       renderer as a soft dependency (skipped entirely if that plugin
 *       isn't installed, or has no data for this student).
 *     - Evidence/<itemid>_<filename> — every referenced eportfolio file,
 *       written once regardless of how many descriptors reference it.
 *   New class classes/portfolio_export.php re-derives the same unit/
 *   descriptor/evidence tree matrix_data::build() uses (same tables, same
 *   joins, including a duplicated copy of extract_lo_sort() for identical
 *   descriptor ordering - matrix_data's own copy is private, same
 *   cross-class duplication pattern that method's own docblock already
 *   documents), rather than depending on matrix_data's Mustache-shaped
 *   return array, so this stays decoupled from that method's internal
 *   structure.
 *   Evidence source confirmed against the real block_exaport plugin
 *   (third-party, unmodified) during scoping - files live in the
 *   student's own user context, component block_exaport, filearea
 *   item_file, itemid = the block_exaportitem row id.
 *   SCHEMA/CAPABILITY CHANGE: new capability block/nvq_matrix:exportportfolio
 *   (CONTEXT_SYSTEM - this action isn't scoped to a single course, same as
 *   the rest of this page - teacher/editingteacher/manager). No new
 *   database tables. $plugin->version bumped despite no table change,
 *   same reasoning as v26.3.10's message-provider fix: a capability
 *   declared in db/access.php is only actually registered by
 *   update_capabilities(), which only runs when upgrade_plugins() sees a
 *   version increase - without this bump the new capability would sit in
 *   code but never appear in Site administration -> Users -> Permissions
 *   for any role to be granted.
 *   BUGS FOUND & FIXED DURING BUILD (own review, before shipping):
 *   (1) zip_packer::archive_to_pathname() requires raw string content
 *   wrapped as array('content_as_string') - a bare string is instead read
 *   as an OS pathname to an existing file. Matrix_Overview.html and each
 *   summary PDF were initially passed as bare strings, which would have
 *   been silently misread rather than zipped.
 *   (2) Descriptor ordering inside each unit was never actually applied -
 *   an early draft's docblock claimed reuse of matrix_data's LO-header-
 *   before-criteria/decimal sort, but the usort() call itself was missing
 *   entirely, so descriptors would have come out in raw query order.
 *   Fixed by duplicating extract_lo_sort() and applying it per topic.
 *   (3) The summary PDF originally only exported the first course
 *   (IGNORE_MULTIPLE) a student had an Assessment Plan on - per client
 *   confirmation that a student's courses have distinct course ids, this
 *   now loops every distinct courseid and produces one summary PDF each.
 *   No live Moodle install available to execute this against during
 *   development, same standing limitation as every other addendum in
 *   this file - table/field names were verified directly against
 *   block_exaport's own db/install.xml and cross-checked line-for-line
 *   against matrix_data.php's existing queries against the same tables,
 *   and brace/paren/bracket balance was checked across every touched
 *   file, but recommend a staging run before trusting this on prod.
 *
 * v26.4.5 — Client-reported: the read-only per-evidence-item comment
 *   still showed a visible left border, despite v26.4.3 documenting
 *   that .nvq-item-comment-readonly had been pared down to match
 *   .nvq-unit-comment-text exactly. ROOT CAUSE: that pass wasn't fully
 *   applied to the shipped CSS — padding, border-radius, and
 *   border-left were all still present on the rule; only the
 *   background-color removal from v26.4.3 actually went out. FIX:
 *   removed the three leftover properties. Rule is now font-size,
 *   color, font-style, line-height, and margin-left only — the exact
 *   same minimal set as .nvq-unit-comment-text, margin-left kept
 *   deliberately for the row-indent (same reasoning as v26.4.3).
 *   Checked both CSS rules and both template/JS references to this
 *   class before editing — the only other rule touching it just
 *   toggles display:none for edit mode, and neither the Mustache
 *   className nor the JS className-reset depend on the removed
 *   properties, so this is a pure style-only change with nothing else
 *   to update. Brace balance confirmed on styles.css (217/217,
 *   unchanged from v26.4.3's count) after the edit. No schema change;
 *   no version bump beyond the release string.
 *
 * v26.4.4 — Client-reported: units (topics) on the matrix were displayed
 *   scattered rather than in the order they were actually entered into
 *   Exabis — e.g. a course entered as L/615/5308, R/615/5309, J/615/5310,
 *   L/615/5311, R/615/5312 was instead shown J, L, L, R, R. ROOT CAUSE:
 *   matrix_data::build()'s topic query hard-coded 'title ASC' — since
 *   these topic titles start with the unit reference code (e.g.
 *   "L/615/5308: Introduction to..."), sorting alphabetically on that
 *   string sorts by the reference-code letter/digits instead of entry
 *   order. FIX: sort param changed to 'id ASC'. block_exacomptopics.id is
 *   assigned at creation time, so ascending id reproduces the order units
 *   were entered in — confirmed this is the right proxy (topic.sorting
 *   itself is NULL across every row on this site's data, so there's no
 *   dedicated Exabis order column to key off instead). Verified directly
 *   against live production data on two separate courses before
 *   shipping: a 5-unit surveying course (ids 4,5,6,7,10) and a 20-unit
 *   course (ids 65–84, covering Units 1–14, 19–22, 24, 28) — both matched
 *   the client's confirmed Exabis entry order exactly, with no gaps or
 *   out-of-place units. Scoped deliberately to topic (unit) ordering
 *   only — the existing descriptor-within-topic sort (LO headers/
 *   criteria, keyed on parentid/sorting further down in the same method)
 *   was checked against the same production data and was already correct,
 *   so it's untouched by this change. One-line change, single query;
 *   no database schema changes; no version bump beyond the release
 *   string.
 *
 * v26.4.3 — Client-requested display change: the read-only per-evidence-
 *   item comment box no longer shows a grey background — now plain text,
 *   matching the unit-level assessor/IQA comment's look (.nvq-unit-
 *   comment-text), per client request to make the two consistent. Only
 *   `.nvq-item-comment-readonly`'s `background-color` was removed;
 *   everything else on that rule (padding, left indent, border-radius,
 *   the left border-strip) is untouched, so the comment still lines up
 *   under its evidence row the same as before. Deliberately scoped to
 *   only this one rule:
 *   - The active edit-mode textarea (`.nvq-item-comment`) was left alone
 *     — it's a live input, not a display, and the client's comparison was
 *     specifically against the assessor comment's read-only look, not its
 *     textarea.
 *   - The generic `.nvq-item-comment[readonly]` rule (background:
 *     transparent, further down in styles.css) was also left alone — it
 *     only ever applies to the textarea itself when JS marks it readonly
 *     mid-edit-mode, a separate element from the `-readonly` <div> this
 *     change targets, and was already transparent regardless.
 *   Checked for knock-on effects before shipping: `.nvq-item-comment-
 *   readonly` is referenced by exactly one CSS rule (this one) and one
 *   JS line that only sets its className (matrix.mustache, for the
 *   locked/edit-mode toggle) — nothing else reads or depends on its
 *   background. Brace/paren balance on styles.css confirmed unchanged
 *   apart from the one deleted declaration. No template or JS changes.
 *   No database schema changes; no version bump beyond the release
 *   string.
 *
 * v26.4.2 — Client-requested display change: the evidence type badge
 *   (both the read-only view and the edit-mode display next to each
 *   evidence item) now shows short codes only, e.g. "O, PD" instead of
 *   "O - Observation, PD - Professional Discussion" — full names took up
 *   too much room repeated next to every evidence item. Only
 *   format_evidence_type_label() changed; the checkbox popover used to
 *   pick types still shows the full name next to each code (there's room
 *   there, and it's what stops someone ticking the wrong box). No
 *   database schema changes; no version bump beyond the release string.
 *
 * v26.4.1 — Requested bug sweep on v26.4 (multi-select evidence types),
 *   specifically checking evidence/evidence-type stayed on the same
 *   line. That part checked out (see below) - but the sweep turned up a
 *   real, separate rendering bug affecting FIVE elements, including two
 *   features from earlier addenda, not just the new one:
 *   BUG FOUND & FIXED: `el.hidden = true` in JS does NOT actually hide an
 *   element once any author stylesheet gives that element its own
 *   explicit `display` value (e.g. `display: flex`) - the browser's own
 *   `[hidden] { display: none }` rule and an author rule targeting the
 *   same element have equal CSS specificity, and author-origin CSS always
 *   wins that tie over the user-agent stylesheet regardless of selector
 *   order. Affected: .nvq-student-list (the picker's row list -
 *   v26.3.17's "always starts collapsed" fix), .nvq-student-row (the
 *   search/status filter's per-row hide - v26.3.16's filter-bug fix),
 *   .nvq-student-filters and .nvq-completed-daterange (the funnel-icon
 *   dropdown), and .nvq-evidencetype-popover (new in v26.4). All five
 *   had an explicit `display` declared elsewhere in styles.css with
 *   nothing accounting for `[hidden]`, so all five would have kept
 *   rendering open/visible regardless of what JS correctly set the
 *   `hidden` attribute to. Fixed with one consolidated block of
 *   `[hidden]` overrides (class+attribute, higher specificity than the
 *   plain class rules) rather than five scattered fixes.
 *   SEPARATE RENDERING RISK CHECKED & FIXED: the new evidence-type
 *   popover was positioned with `position: absolute`, anchored to a
 *   nearby ancestor - but .nvq-unit-card (an ancestor of every evidence
 *   row) sets `overflow: hidden` to round its corners, which would have
 *   clipped the popover for any evidence item near the card's edge.
 *   Switched to `position: fixed`, positioned with JS-computed viewport
 *   coordinates (openEvidenceTypePopover()), which escapes that ancestor
 *   clipping entirely; closes on scroll/resize instead of trying to
 *   track the button live across a scroll.
 *   EVIDENCE-AND-TYPE-ALIGNMENT CHECK (the specific thing asked about):
 *   confirmed structurally safe. Both evidence and its type badge/popover
 *   are rendered from the SAME per-item Mustache context inside the SAME
 *   `<li>` (they're not two separately-rendered lists that could drift),
 *   and the underlying data is matched up via a plain PHP array keyed by
 *   mmid (matrix_data.php's $commentmap), not a SQL JOIN - so there's no
 *   mechanism left that could duplicate or misalign an evidence row
 *   relative to its type. Verified the two near-identical code blocks
 *   that build this data (LO-grouped vs orphaned criteria) are still
 *   byte-for-byte identical after the v26.4 edit.
 *   No database schema changes; no version bump beyond the release
 *   string (built on top of 2026072101's schema - no new migration
 *   needed for this fix).
 *
 * v26.4 — FEATURE: multiple evidence types per evidence item, replacing
 *   the old single-select dropdown (one code max). The same piece of
 *   evidence can genuinely fit more than one type (e.g. both Observation
 *   and Professional Discussion), which the single-select couldn't
 *   represent at all.
 *   SCHEMA CHANGE: new table block_nvq_matrix_evidence_types (one row per
 *   evidence item per selected type), replacing the single evidencetype
 *   column on block_nvq_matrix_evidence_comments. The old column is left
 *   in place, unused, as a one-release rollback safety net rather than
 *   dropped immediately — see db/upgrade.php's 2026072101 step, which
 *   creates the new table and migrates any existing single-code data
 *   into it.
 *   DELIBERATELY NOT joined into the main matrix query: a one-to-many
 *   child table joined directly into the evidence-item query would
 *   duplicate/misalign the parent evidence row once per matching type
 *   row - which is exactly what went wrong in an earlier attempt at this
 *   (per client report: evidence and its type ended up "not on the same
 *   line"). Evidence types are instead fetched as their own separate
 *   keyed lookup query and merged in PHP afterwards - the same pattern
 *   already used for block_nvq_matrix_sampling, proven not to have this
 *   problem.
 *   UI: the single-select `<select>` per evidence item is replaced with
 *   a small "Edit types" toggle button that opens a checkbox popover (one
 *   checkbox per EVIDENCE_TYPES code); checking/unchecking a box saves
 *   the complete current selection immediately, same as the old
 *   dropdown's on-change save. The read-only display badge now shows a
 *   comma-joined list of all selected types instead of just one.
 *   Also updated: classes/privacy/provider.php - new metadata entry for
 *   the child table, export includes the full set of selected types (not
 *   just one), and all three delete_data_for_*() functions now
 *   cascade-delete the child table's rows before deleting the parent
 *   evidence-comment rows they belong to (previously they'd have been
 *   silently orphaned - harmless since the child table holds no personal
 *   data itself, but untidy).
 *   $plugin->version bumped (real schema change, unlike most of the
 *   v26.3.x line which were code-only).
 *
 * v26.3.20 — Client-requested date format change: all dates in the
 *   matrix (comment bylines, final-status set date, notified date) now
 *   show as DD/MM/YYYY (e.g. 02/06/2026) instead of "2 June 2026".
 *   Switched from core_langconfig's strftimedateshort - which follows
 *   the site/user's locale and could format differently elsewhere - to
 *   an explicit '%d/%m/%Y' format, so it reads the same everywhere
 *   regardless of locale. No database schema changes; no version bump
 *   beyond the release string.
 *
 * v26.3.19 — Two client-reported issues:
 *   (1) BUG FIXED: setting a comment/final-status date in the past (e.g.
 *   2 June) kept showing today's date instead. The date WAS being saved
 *   correctly to the DB all along - the bug was only in the immediate
 *   on-screen byline shown right after saving a comment (evidence
 *   comment, grade comment, unit comment): evidence_comment.php,
 *   grade.php, and unit_comment.php each built that byline from
 *   $savedtime = time() (always "now") instead of the actual timestamp
 *   just written to the DB. A page reload always showed the correct
 *   date (server-rendered from the DB row), which is presumably why this
 *   went unnoticed until now - only the instant post-save feedback was
 *   wrong. Fixed by deriving $savedtime from the same parsed comment date
 *   used for the save itself, computed once and reused for both. (The
 *   final-status date field was unaffected - that box does a full
 *   re-render from the DB after saving rather than composing its own
 *   byline client-side.)
 *   (2) Display change: every date shown anywhere in the matrix (comment
 *   bylines, final-status "set by" date, notified date) now shows date
 *   only, no time-of-day - changed from core_langconfig's
 *   strftimedatetimeshort to strftimedateshort in the three places that
 *   used the former. The underlying saved timestamps still carry a
 *   time-of-day internally (see parse_comment_date()'s docblock for why),
 *   it's simply never displayed anymore.
 *   No database schema changes; no version bump beyond the release
 *   string.
 *
 * v26.3.18 — Client-requested wording change: "Needs grading" renamed
 *   to "On-going" (better optics), on both the filter chip label and
 *   the matching status badge. Lang strings only (filterneedsgrading,
 *   statusneedsgrading) — the internal 'needsgrading' key/data-status/
 *   data-filter value is untouched, so filtering logic is unaffected.
 *   No database schema changes; no version bump beyond the release
 *   string.
 *
 * v26.3.17 — Client-reported issues with the v26.3.16 dropdown picker,
 *   fixed together:
 *   (1) The row list was showing open on page load whenever no student
 *   was yet selected (intentional in v26.3.16, to prompt a pick — but
 *   the client wants the picker collapsed by default full stop). Removed
 *   that auto-open case; the trigger now always starts collapsed
 *   (aria-expanded="false", list hidden) regardless of whether a student
 *   is already selected.
 *   (2) Default filter chip changed from "Needs grading" to "All", per
 *   client request — changed in both the server-rendered chip markup
 *   (view.php) and the JS's initial activefilter value (matrix.mustache),
 *   which must stay in sync since the JS re-derives filtering from
 *   scratch on every interaction rather than reading the chip's rendered
 *   state.
 *   (3) Client reported the search/funnel icon buttons weren't showing
 *   an icon. The server-rendered markup already contained correct SVGs
 *   in both a fresh render and a saved copy of the live page, so this
 *   wasn't a missing-markup bug — but .nvq-icon-btn had no explicit
 *   height (only width) and relied on the browser's default button
 *   padding/line-height to size itself around the icon, which some
 *   theme/browser combinations reset unpredictably. Hardened
 *   defensively: explicit height to match width, padding:0, line-height:1,
 *   appearance:none, and a `display:block` rule on the svg itself (both
 *   icon buttons and the trigger's caret) so nothing in the surrounding
 *   button's inline-content sizing can collapse or clip it. If icons
 *   still don't appear after this update, purge all caches (Site
 *   administration → Development → Purge caches) — Moodle's CSS
 *   aggregation is revisioned separately from PHP/template changes and
 *   can otherwise keep serving a stale copy of styles.css.
 *   No database schema changes; no version bump beyond the release
 *   string.
 *
 * v26.3.16 — Two client-reported issues with the v26.3.12-14 student
 *   picker, fixed together:
 *   (1) BUG FIXED: under the "Completed" filter, a student enrolled on
 *   two courses (one completed, one not) showed BOTH course rows
 *   instead of just the completed one. Root cause: initStudentPicker()'s
 *   status/date filter exempted any row belonging to the currently-open
 *   student (.nvq-student-row--active) so the page you're viewing never
 *   seems to vanish from the list — but since v26.3.14 gives one row per
 *   student+course pair, BOTH of that student's rows carry the --active
 *   class together (see view.php's $isactive), so the exemption
 *   blanket-covered both regardless of their individual status. Fixed by
 *   removing the exemption entirely: every row is now filtered purely on
 *   its own data-status/data-completeddate, active or not.
 *   (2) UI redesign: the picker was an always-open search box + filter
 *   chips + full row list, all visible at once even after a student was
 *   selected. Replaced with a collapsed dropdown-style control: a
 *   trigger button showing just the selected student's name (or a
 *   prompt, if none picked) toggles the row list open/closed; a
 *   magnifying-glass icon and a funnel icon sit beside it and reveal the
 *   search input / filter chips (and open the list) only when clicked.
 *   New markup in view.php (nvq-selector-bar/-trigger, nvq-icon-btn
 *   search/filter toggles), new JS wiring in matrix.mustache
 *   (initStudentPicker()), new CSS (styles.css), two new lang strings
 *   (togglesearch, togglefilter). No database schema changes; no version
 *   bump beyond the release string.
 *
 * v26.3.15 — Requested bug sweep across everything touched this
 *   session (v26.3.9-14). No functional bugs found this round beyond
 *   one already caught and fixed while building v26.3.13 (the split
 *   JS docblock comment from v26.3.12). What WAS checked and cleared:
 *   real JS syntax validation via `node --check` against the extracted
 *   <script> block (not just brace counting - this is the check that
 *   would have caught the v26.3.12 comment bug directly, had it been
 *   available at the time); every get_string()/{{#str}} call
 *   cross-referenced against lang/en/block_nvq_matrix.php in both
 *   directions (no missing definitions, no duplicate keys); every
 *   var(--nvq-*) in styles.css confirmed against its :root definition;
 *   and specifically verified that enrol_get_users_courses($USER->id,
 *   true, ['id']) still leaves $course->fullname populated (Moodle
 *   core merges any $fields argument into a base set that already
 *   always includes fullname/shortname/etc - it's additive, not
 *   restrictive - so this was never actually at risk, but worth
 *   confirming against core source rather than assuming).
 *   One harmless cleanup: removed $studentcourses in view.php, dead
 *   weight left over from v26.3.14's student+course-pair refactor
 *   (superseded by $coursenamesbyid/$studentcourseids) - still being
 *   populated with a wasted format_string() call per enrolment despite
 *   nothing reading it anymore.
 *
 * v26.3.14 — Client-requested: the student picker now shows one row
 *   per student+course pair instead of one blended row per student.
 *   A student on two courses previously got a single row whose status
 *   was "Completed" only if BOTH courses were a Pass, with course names
 *   comma-joined - correct information, but it didn't say which course
 *   was the outstanding one without opening their matrix. Now each
 *   course gets its own row with its own independent status/completion
 *   date, so e.g. "needs grading" on Course B is visible directly in
 *   the list even if Course A already shows Completed for the same
 *   student. Clicking ANY row for a student still opens the same
 *   combined matrix page as before (matrix_data::build() always shows
 *   all of a student's courses together, regardless of which course
 *   row was clicked) - this change is purely about what the LIST shows
 *   and how it filters, not about scoping which course you land on.
 *   Both rows for the same student are still treated as "the currently
 *   viewed row" together (exempt from the status/date filters, per
 *   v26.3.12/13) whenever that student's matrix is open, since one page
 *   load covers both.
 *
 * v26.3.13 — Client-requested: a "when completed" date range on the
 *   student picker's Completed filter, so an assessor can narrow the
 *   completed list down to a specific window (e.g. this month's
 *   completions for an awarding-body return) instead of scrolling the
 *   full list. "Completion date" for a student is defined as the
 *   timemodified of the LAST of their courses to be marked Pass - i.e.
 *   the moment they actually became fully complete, not the first
 *   course they passed if they're on more than one. Two <input
 *   type="date"> fields (From/To), shown only while "Completed" is the
 *   active filter chip (hidden otherwise, since "when completed" has no
 *   meaning for a non-completed student) and both blank by default -
 *   which imposes no restriction at all, so the Completed chip on its
 *   own still shows every completed student, same as before this was
 *   added. Filtering is a plain string comparison client-side (both the
 *   row's data-completeddate and the date input's value are YYYY-MM-DD,
 *   which sorts correctly as a string without parsing). Also fixed a
 *   copy-paste error from v26.3.12's own edit: a JS docblock comment
 *   got split across two str_replace calls, leaving several lines of
 *   comment text sitting outside any comment block at all - would have
 *   been a hard JS syntax error on the very next page load. Caught
 *   before shipping by the routine brace/paren balance check, not by
 *   testing in a browser - worth remembering that check earns its keep.
 *
 * v26.3.12 — Two client-requested additions.
 *   (1) The four external links that navigate away from the matrix
 *   (the dashboard block's "Open NVQ Matrix" launcher, and the
 *   Assessment Plan / Sampling Plan / Sampling Record portfolio links)
 *   now open in a new tab (target="_blank" rel="noopener noreferrer"),
 *   matching the pattern already used for evidence-item links, so
 *   clicking them doesn't lose the assessor's place on the page they
 *   came from.
 *   (2) The student picker on view.php - previously a plain <select>
 *   dropdown - is now a searchable, filterable list. A new "completion
 *   bucket" is computed per student from block_nvq_matrix_status: a
 *   student only counts as "Completed" if EVERY course they're
 *   enrolled on (within the current viewer's viewall scope) has a Pass;
 *   anything else - no status set, or a Fail on any course - is "Needs
 *   grading" (Fail deliberately isn't its own bucket, since a Fail
 *   still means outstanding work). Filter chips (Needs grading /
 *   Completed / All) default to "Needs grading" - the actual working
 *   list - with a live name-search box alongside. All filtering is
 *   client-side (the full list is already rendered; no new requests),
 *   and the currently-selected student's row is always shown regardless
 *   of the active filter so switching filters never makes the page
 *   you're already viewing seem to vanish from the list above it.
 *
 * v26.3.11 — Client-reported: clicking Pass/Fail on the final-status box
 *   saved correctly but silently dropped the assessor out of edit mode,
 *   forcing an extra click on "Edit matrix" just to reach the Notify
 *   button afterwards. Root cause: saveFinalStatus(), clearFinalStatus()
 *   and sendCompletionNotification() all reload the page on success to
 *   re-render the box - simplest reliable way to reflect the new status
 *   everywhere - but edit mode is pure client-side state (a class on
 *   .block-nvq-matrix), so any reload always lands back in the default
 *   locked view regardless of what it was before. Fixed with a
 *   sessionStorage flag set immediately before reloading (only if edit
 *   mode was actually on) and consumed once on the next page load to
 *   re-enter edit mode automatically - same pattern for all three
 *   actions via a new reloadPreservingEditMode() helper. No DB/schema
 *   change; no version bump beyond the release string.
 *
 * v26.3.10 — Client-reported: "Notify student" always failed client-side
 *   ("Error sending notification"), even though the send itself succeeded
 *   server-side. Root cause was two independent bugs surfaced together:
 *   (1) final_status.php never sets $PAGE->context before
 *   send_completion_notification() calls format_string() on the course
 *   name, so on this AJAX-only endpoint (no page load to infer context
 *   from) Moodle's format_string() throws a debugging() notice; with site
 *   debug-message display on, that notice is printed as HTML before the
 *   JSON body, corrupting the response and making the client's r.json()
 *   throw — hence the generic client-side error text despite
 *   {"success":true} actually being present later in the same body.
 *   Fixed by setting $PAGE->context = $coursecontext in final_status.php
 *   right after the capability check, same as every other AJAX endpoint
 *   in this plugin already does.
 *   (2) The block/nvq_matrix:coursecomplete message provider (declared in
 *   db/messages.php since v26.3) was never actually written to
 *   {message_providers} — message_update_providers() only runs when
 *   upgrade_plugins() detects a version increase, and no addendum since
 *   v26.3 has bumped $plugin->version (correctly, since none needed a
 *   real DB/schema change) — so the provider was declared in code but
 *   never installed, meaning message_send() silently no-ops for every
 *   student regardless of their notification preferences. This addendum
 *   bumps $plugin->version specifically to force that one-time
 *   registration; no schema change accompanies it. After upgrading,
 *   confirm block_nvq_matrix appears under Site administration →
 *   Messaging → Notification settings.
 *
 * v26.3.9 — Locked-by-default UI overhaul, requested after v26.3.8's
 *   redesign: every writable control (grade verdict, sampling status,
 *   final-status Pass/Fail/Notify, all three comment boxes) is now
 *   completely inert and text-only until "Edit matrix" is clicked — for
 *   every role that can edit anything, not just the ones v26.3.6 already
 *   covered (evidence type, comment date fields). Previously grade
 *   buttons, the sampling dropdown, and final-status controls were always
 *   live regardless of edit mode, and locked comment textareas still
 *   showed their placeholder text ("Add a comment...") since `readonly`
 *   doesn't hide that. Grade and sampling now always render a plain
 *   read-only badge (nvq-grade-readonly / nvq-sample-readonly) by default;
 *   the interactive controls only mount into view via the .nvq-editmode
 *   class, same mechanism v26.3.6 used for evidence type. A CSS :has()
 *   guard on each wrapper (.nvq-grade-controls-wrap /.nvq-sample-wrap)
 *   ensures this never leaves a blank gap for a viewer who can edit
 *   something elsewhere on the page but not grades/sampling specifically.
 *   Final status didn't need new markup — its badge was already
 *   unconditional — so only nvq-finalstatus-controls/-date-row needed
 *   gating.
 *
 *   BUG FOUND & FIXED #1 (own QA, before shipping): the gradedisabled
 *   block (unit has cangrade but no evidence anywhere yet) had its whole
 *   contents — disabled buttons AND the hint text explaining *why*
 *   grading is disabled — wrapped in the same hidden-until-edit class,
 *   so a locked-view assessor would see nothing at all instead of the
 *   hint. Fixed: only the inert buttons wait for edit mode now; the hint
 *   stays always visible, matching its original behaviour.
 *
 *   BUG FOUND & FIXED #2 (own QA, more serious): wrapping the grade
 *   comment textarea inside a container that now gets display:none on
 *   the same class toggle that also controls its readOnly state created a
 *   race. Hiding a focused element auto-fires blur() on it per spec —
 *   that could fire *before* toggleEditMode()'s own deliberate blur()
 *   call and before readOnly gets set, meaning depending on browser
 *   timing the auto-blur could land *after* readOnly=true, hit the
 *   readOnly guard in the blur handler, and silently drop the save when
 *   clicking "Done editing" — exactly the class of bug v26.3.7 had
 *   already fixed once, reintroduced by this session's own restructure.
 *   Fixed by reordering toggleEditMode(): blur the active element first,
 *   *then* toggle the class that causes anything to hide.
 *
 *   BUG FOUND & FIXED #3 (own QA): the new read-only badge sync functions
 *   were written using M.util.get_string() for translated label text,
 *   copying the pattern already used (since before this session) in
 *   syncHeaderBadge()/syncHeaderSampleBadge(). This plugin never calls
 *   strings_for_js() anywhere, so M.str was never guaranteed to have
 *   those strings preloaded — an existing latent risk in the two older
 *   functions, now also newly present in the functions copied from them.
 *   Fixed all four functions to read pre-translated text from data-*text
 *   attributes rendered server-side instead, matching the safe pattern
 *   saveEvidenceType() already used via its data-notsettext attribute.
 *
 *   CLIENT-REPORTED BUG (after the above shipped): even after fixing the
 *   locked-by-default look, a locked comment box for the evidence-item and
 *   IQA unit comment fields still showed a visible grey box ("like a
 *   greyed-out shadow"). Root cause: Bootstrap's own
 *   .form-control[readonly] rule has identical CSS specificity to this
 *   plugin's override and loads after block CSS in the cascade, so it won
 *   regardless of what styles.css tried to set — the same class of
 *   problem the v26.3.3 addendum already hit with a <select>'s native
 *   chrome. (The grade comment box was already unaffected — it's fully
 *   display:none when locked, not just readonly-styled.) Fix: applied the
 *   same structural pattern used for grade/sample here too — a completely
 *   separate plain read-only <div>, always rendered, with the interactive
 *   <textarea> (+ date field + status) wrapped as its own hidden-until-
 *   edit-mode block. No more relying on CSS to fight Bootstrap.
 *
 *   CLIENT-REPORTED BUG (after the above shipped): a freshly-typed
 *   comment saved correctly but never appeared in the locked view.
 *   Root cause: the structural fix above made the read-only <div> and the
 *   editable <textarea> two separate elements for the first time —
 *   previously the same textarea served both roles, so nothing needed
 *   syncing. Fix, for all three comment types (evidence-item, IQA unit,
 *   grade): evidence_comment.php / unit_comment.php / grade.php now
 *   return the saved comment text plus a server-formatted commentbyline
 *   ("Name, date") in their JSON response, built from
 *   matrix_data::format_comment_byline() (widened from private to public
 *   so the endpoints can call it — no other change to that method, no
 *   existing internal `self::` caller affected). JS now creates the
 *   read-only div (and byline) if it didn't already exist, updates it if
 *   it did, or removes it if the comment was cleared. Caught and fixed a
 *   related bug in this same fix before shipping it: clearGrade() never
 *   touches the comment, so it must never pass a byline value either —
 *   the first draft would have wrongly stripped an existing byline just
 *   because clear-grade didn't supply one. Fixed by treating "byline
 *   parameter omitted" as "leave it untouched", distinct from "byline
 *   explicitly empty string" (comment genuinely cleared).
 *
 *   CLIENT-REPORTED BUG (after the above shipped): the Clear-grade button
 *   was missing for the assessor. Root cause: pre-existing since v26.3.2,
 *   not introduced this session, just newly exposed by more in-place
 *   grading during this round of testing — the button is only ever
 *   server-rendered when a grade was already set at page load; nothing
 *   ever created it dynamically after a first-time grade save via AJAX on
 *   a previously-ungraded unit, so it silently never appeared without a
 *   full page reload. Fixed: new ensureClearButton() creates it on the
 *   fly after a successful saveGrade() if not already present, using
 *   data-cleartext/data-confirmcleartext attributes on .nvq-grade-buttons
 *   (same safe-text-attribute pattern as everywhere else this round) — no
 *   extra event wiring needed since the click handler is already
 *   delegated on the whole matrix container.
 *
 *   Verified throughout: full Mustache section-tag balance (open/close
 *   including inverted {{^}} sections), HTML tag balance, JS brace/paren
 *   balance, and — for the two PHP endpoint changes and the matrix_data.php
 *   visibility change — brace/paren balance on every touched PHP file (no
 *   php -l available in this environment, same standing limitation noted
 *   in every previous addendum). No database schema changes.
 *
 * v26.3.8 — Visual redesign + structural fix, requested together:
 *   (1) Full CSS redesign to a more modern, cohesive look. Introduced a
 *   design-token system (--nvq-* custom properties scoped to
 *   .block-nvq-matrix, never leaking into the surrounding Moodle theme) —
 *   one colour palette, one border-radius scale, one shadow scale, one
 *   easing curve — replacing ~15 different ad-hoc hex values and
 *   inconsistent radii/shadows across the file. Flattened the heavy
 *   diagonal blue gradients (unit headers, launcher button) to a single
 *   flatter slate-blue. Unified all badges/pills (grade, sampling,
 *   final-status, unit header, evidence type) onto one consistent shape,
 *   weight, and tint/solid colour pairing. Pure CSS — no class names, JS
 *   hooks, or markup structure touched, no schema/version bump beyond the
 *   release string.
 *   (2) BUG FOUND & FIXED (surfaced during the redesign, not client-
 *   reported): evidence type and its evidence were rendered as two
 *   independent <ul> lists in separate <td> columns (nvq-evidencetype-list
 *   / nvq-evidence-list), each <li> sized purely by its own content. Since
 *   only the evidence side carried a comment textarea, any criterion with
 *   more than one evidence item drifted the two columns out of row-sync
 *   after the first item — no CSS fix is possible for two independently-
 *   sized parallel lists. Structural fix: the separate evidence-type
 *   column is removed; evidence type now renders as a small pill inline
 *   inside .nvq-evidence-item-main, the same flex row as that evidence's
 *   icon and name, inside the same <li> — the two can no longer drift
 *   apart regardless of comment length. nvq-col-evidencetype/
 *   nvq-evidencetype-list/nvq-evidencetype-item removed (orphaned);
 *   nvq-col-evidence widened to fill the freed space.
 *   No JS changes were needed — the edit-mode display/select toggle
 *   (.nvq-evidencetype-display / .nvq-evidencetype-editrow, driven by the
 *   .nvq-editmode class from v26.3.6) targets the same elements regardless
 *   of where their parent <li> lives, and every JS DOM-traversal call that
 *   touches this markup (textarea.parentElement, .closest('.nvq-evidence-
 *   item'), select.closest('.nvq-evidencetype-wrap')) still resolves
 *   correctly since those relationships were preserved, only relocated.
 *   Verified with a full Mustache section-tag balance check (open/close
 *   counts including inverted {{^}} sections, not just {{#}}/{{/}} pairs)
 *   and an HTML tag-balance check across the touched template region, both
 *   clean. Pure template/CSS change — no PHP, no database changes.
 *
 * v26.3.7 (bug audit, no client-reported issue) — two issues found while
 *   re-checking v26.3.6's locked/readonly comment fields:
 *   (1) Locked comment textareas are still focusable (readonly doesn't
 *   prevent that — e.g. clicking to select/copy the text), and the
 *   existing blur-triggered save handlers had no dirty-check, so that
 *   alone would silently resave unchanged content and re-stamp
 *   commentedby/timemodified as "now". Fixed with a readOnly guard at the
 *   top of the delegated blur listener — skips entirely for any field
 *   currently marked readOnly. Verified this doesn't block the legitimate
 *   "Done editing" save: toggleEditMode() calls blur() on the active
 *   element before setting readOnly=true, so that save still goes through.
 *   (2) Locked fields could still show Bootstrap's blue :focus glow on
 *   click (readonly doesn't suppress :focus styling), making a locked
 *   comment look "active" right when someone clicks it. Fixed with a
 *   higher-specificity [readonly]:focus rule that wins regardless of
 *   stylesheet order. Pure JS/CSS, no PHP or database changes.
 *
 * v26.3.6 — Client feedback: rather than a separate edit affordance on
 *   every comment/evidence-type field (v26.3.5's per-item pencil icon),
 *   replaced with a single global "Edit matrix" toggle button at the top
 *   of the page. Locked (default) state: every comment textarea renders
 *   read-only and borderless (looks like plain text, not an editable box),
 *   backdate fields are hidden, and evidence type shows as text only.
 *   Clicking the button unlocks everything on the page at once; clicking
 *   it again ("Done editing") locks it back down, blurring whatever field
 *   is currently focused first so its comment still saves via the
 *   existing blur-triggered save logic before locking. Individual fields
 *   are entirely unchanged in how they save — this only ever controls
 *   visibility/interactivity, never the save mechanism.
 *   New: matrix_data::build() computes a top-level caneditanything flag
 *   (cangrade || caniqacomment || isownmatrix) so the button doesn't
 *   render for someone with nothing to edit (e.g. a :viewall-only user).
 *   The per-item pencil icon and its enterEvidenceTypeEdit()/
 *   exitEvidenceTypeEdit() JS from v26.3.5 are removed — evidence type
 *   display/edit visibility is now purely CSS, driven off one
 *   .nvq-editmode class on the container, same mechanism as comments.
 *   templates/matrix.mustache, styles.css, lang strings (editmatrix,
 *   doneediting), classes/matrix_data.php (caneditanything only — no
 *   schema/version change).
 *
 * v26.3.5 — Client feedback: the v26.3.3 CSS-only "fade the select into
 *   plain text" fix had no visible effect on the live site (border-color
 *   transparent + appearance:none on a <select> doesn't reliably strip all
 *   native OS chrome in every browser/theme combination). Replaced with a
 *   structural fix instead of chasing more CSS: evidence type now renders
 *   as plain text (or an italic "Not set") plus a small pencil-icon button.
 *   Clicking the icon swaps in the real <select> in place; choosing a value
 *   saves via the existing evidence_type.php endpoint (unchanged) and, on
 *   success, updates the display text and collapses back to read-only
 *   without a page reload. Clicking away without changing anything also
 *   collapses back (via the existing capture-phase blur delegate already
 *   used for comment fields) with nothing saved. Same edit-affordance
 *   pattern the client separately suggested for comments — this covers
 *   evidence type only for now; comments (grade/IQA/evidence-item) still
 *   render as always-visible textareas, unchanged, and are a candidate for
 *   the same treatment as a follow-up if wanted. Pure template/CSS/lang
 *   change — no PHP logic, no database changes, no version bump beyond the
 *   release string.
 *
 * v26.3.4 (bug audit, no client-reported issue) — sendCompletionNotification()
 *   disabled .nvq-finalstatus-btn/.nvq-finalstatus-notify while its request
 *   was in flight but not .nvq-finalstatus-clear (added in v26.3.2), so the
 *   Clear button didn't visually grey out while a notification send was in
 *   progress. Not exploitable — the click delegate's existing
 *   dataset.saving guard already blocked an actual double-submit — but
 *   inconsistent with saveFinalStatus()/clearFinalStatus(), which both
 *   correctly disable all three controls. Now all three finally() blocks
 *   disable/re-enable the same set. Pure JS, no other changes.
 *
 * v26.3.3 — Client feedback: once an evidence type is chosen, the dropdown
 *   still looked like an empty box waiting to be filled in. The <select>
 *   itself is unchanged (still a real dropdown, still saves instantly on
 *   change via evidence_type.php) — only its appearance changes once it
 *   holds a value: border/background fade away so it reads as plain text,
 *   and the box styling reappears on hover/focus so it's still obviously
 *   editable. Applied both on initial page load (for items typed earlier)
 *   and immediately on change (doesn't wait on the AJAX save to resolve).
 *   Pure CSS/JS — no PHP, template markup, or database changes.
 *
 * v26.3.2 — Client request, prompted directly by testing the v26.3.1 fix on
 *   a real student account:
 *   (1) "Clear status" — a new button next to Pass/Fail (shown only once a
 *   status is set) that deletes the block_nvq_matrix_status row entirely,
 *   resetting the box back to "Not yet set". Unlike clear_grade(), there's
 *   no separate comment worth preserving on this record, so this is a full
 *   reset — including wiping any notifiedby/notifiedtime, since a
 *   notification sent alongside a status set in error is stale too. Gated
 *   on the same :finalstatus capability as setting status; client-side
 *   confirm() before sending, matching the notify button's pattern. See
 *   matrix_data::clear_final_status(), final_status.php action=clear.
 *   (2) Backdating — a date input next to the Pass/Fail buttons (defaults
 *   to today, or the status's current date if already set), same pattern
 *   as the existing grade/IQA/evidence-item comment date fields, for
 *   recording a result for a student who genuinely completed before this
 *   feature existed. save_final_status() gained an optional $setdate param;
 *   final_status.php parses it via the existing matrix_data::
 *   parse_comment_date() helper. No database schema changes for either
 *   change (the status table's timemodified/setby columns already existed
 *   for this purpose); no version bump beyond the release string.
 *
 * v26.3.1 — Client-reported bug fixed: a student enrolled in only one
 *   course was shown two final-status boxes at the bottom of the matrix,
 *   one for a course they were never registered on. Root cause:
 *   $courseidnamemap (build(), used to decide how many final-status boxes
 *   to render) was populated purely from the topic<->course m:m link table
 *   (block_exacompcoutopi_mm) — so a unit shared across more than one
 *   course/pathway variant (e.g. an optional unit common to two NVQ route
 *   options) caused every course that unit is linked to to get a box,
 *   regardless of which course the student actually holds an enrolment on.
 *   This is the same class of bug as the v26.1 portfolio-links fix, just
 *   surfacing in the new v26.3 final-status feature instead. Fixed by
 *   intersecting $courseidnamemap against enrol_get_users_courses($studentid,
 *   true) right after it's built, before it's used for anything —
 *   $topiccourseidmap and its "lowest courseid wins" grading tiebreak are
 *   untouched, since that map serves an unrelated purpose (which single
 *   course a grade write is scoped to) and was never the source of this
 *   bug. No database schema changes; no version bump beyond the release
 *   string, since there's no new upgrade step.
 *
 * v26.3 — Two new features, requested once the client was live on production
 * with real grades/comments already entered:
 *   (1) Evidence type dropdown on every evidence item (APL, EoE, NA, O, P,
 *   PD, Q, RA, S, WT) - saved instantly on change, independent of the
 *   comment on that item. Editable by assessors and IQA (same people who
 *   could already comment on evidence); visible read-only to everyone else.
 *   See matrix_data::save_evidence_type() and evidence_type.php.
 *   (2) Final Pass/Fail status per student per course, set by the assessor
 *   in a box at the bottom of the matrix, with a "Notify student" action
 *   that sends a completion message through Moodle's own messaging system
 *   (so it respects the student's notification preferences and shows up in
 *   the message drawer/email like any other Moodle notification). Sending
 *   is deliberately unconstrained - no requirement that grading/IQA be
 *   "finished" first, and it can be re-sent any number of times - but
 *   always asks for confirmation first. New capability
 *   block/nvq_matrix:finalstatus (editingteacher/manager, same as
 *   :grade). See matrix_data::save_final_status() /
 *   send_completion_notification(), final_status.php, db/messages.php.
 *
 * v26.2 — Two client-reported live-site issues, fixed together:
 *   (1) Clearing an assessor grade also cleared the assessor's comment,
 *   because both lived on the same row keyed only by value/comment with
 *   shared gradedby/timemodified attribution, and "clear" deleted the row
 *   outright. Comment now has its own commentedby/commenttime columns and
 *   clear_grade() only nulls the verdict fields, never the comment - see
 *   schema change 2026071600 in db/upgrade.php.
 *   (2) No way to backdate a comment when re-grading a student whose
 *   portfolio was actually completed earlier - every grade/IQA/evidence
 *   comment box now has an optional date field (defaults to today) that
 *   sets the timestamp shown in the "commented by" byline. See
 *   matrix_data::parse_comment_date() and the commentdate param on
 *   grade.php / unit_comment.php / evidence_comment.php.
 *
 * v26.1 — Client-reported bug, fixed the same day it was reported: students
 *   on "ProQual Level 3 Diploma in Engineering Surveying" were shown
 *   "Engineering Surveying (Experienced Route)" in the new v26 portfolio
 *   links panel — the wrong course.
 *   ROOT CAUSE: the first cut of get_portfolio_links() reused
 *   $topiccourseidmap to decide which course to link to. That map exists
 *   purely for GRADING — when a single topic is linked to more than one
 *   course (e.g. a course cloned into a variant, both sharing the same
 *   topic bank), grading needs some single deterministic course to
 *   receive the write, so it resolves the ambiguity with a "lowest
 *   courseid wins" tiebreak. That tiebreak has no concept of which of
 *   the ambiguous courses the VIEWED STUDENT is actually enrolled on —
 *   it just always prefers whichever course was created first, globally.
 *   The Diploma course and the Experienced Route variant share a topic
 *   bank (one was evidently cloned from the other), and the variant
 *   happened to have the lower courseid, so every Diploma student's
 *   panel got resolved to the variant instead, regardless of enrolment.
 *   FIX: get_portfolio_links() now resolves candidate courses fresh and
 *   independently, straight from the topics (SELECT DISTINCT courseid
 *   from block_exacompcoutopi_mm for the student's topicids — deliberately
 *   returns every linked course, no tiebreak applied at that step), then
 *   gates each candidate on real enrolment via is_enrolled($coursecontext,
 *   $studentid, '', true) — onlyactive:true, so a suspended enrolment
 *   doesn't count either — before it's eligible for a panel entry. A
 *   course that isn't configured in local_nvqportfolio, or that the
 *   viewer can't see per local_nvqportfolio's own
 *   local_nvqportfolio_can_view_student() check, is still excluded same
 *   as before — only the course-selection step changed.
 *   SCOPE, DELIBERATE: grading itself is untouched by this fix —
 *   $topiccourseidmap and its "lowest courseid wins" tiebreak still work
 *   exactly as before for grade-write scoping. This was a considered
 *   choice, not an oversight: the portfolio panel is a per-student
 *   display (wrong course shown = a real, visible bug), whereas grading
 *   needs a single deterministic write target and changing that tiebreak
 *   is a separate decision with its own risk (could silently move which
 *   course existing/future grades are scoped to). Flagged as an open
 *   action item: since this bug report *proves* the Diploma/Experienced
 *   Route pair is a real topic-sharing case on this site and not just a
 *   hypothetical, it's worth asking the client to check whether any
 *   Engineering Surveying student's grades/sampling/comments have landed
 *   under the wrong one of these two courses via that same tiebreak —
 *   that would be a write-path instance of this bug, not just the
 *   display-path one fixed here, and needs its own decision before
 *   touching grading's tiebreak logic.
 *   CONSEQUENCE WORTH KNOWING: a student genuinely, correctly enrolled on
 *   two topic-sharing courses at once will now correctly get two panels
 *   instead of one — that's intended given the fix, not a regression, but
 *   untested against a live case of exactly that at the time of this
 *   release (no Moodle install / PHP linter available in this session —
 *   same standing limitation noted in other addenda; recommend verifying
 *   on staging with a real dual-enrolled student before relying on it).
 *   No database schema changes; no version bump beyond the release
 *   string.
 *
 * v26 — local_nvqportfolio integration, shallow (links-only) version.
 *   The matrix now shows a small panel above the unit grid — one per
 *   distinct course the selected student's matrix touches — linking to
 *   that course's Assessment Plan, Sampling Plan, and Sampling Record in
 *   local_nvqportfolio. No data is read or duplicated from that plugin;
 *   these are plain links to its existing, self-contained, capability-
 *   checked view pages.
 *
 *   local_nvqportfolio is treated as a soft/optional dependency, not a
 *   hard one — deliberately no entry added to $plugin->dependencies,
 *   since this plugin must keep working on sites that don't have it
 *   installed. See classes/matrix_data.php's get_portfolio_links():
 *   it checks the plugin directory exists, its lib.php loads, its
 *   local_nvqport_qualification table exists, and — per course — that a
 *   qualification row exists for that course before it's considered
 *   linkable at all. Visibility is then delegated entirely to
 *   local_nvqportfolio's own local_nvqportfolio_can_view_student()
 *   rather than reimplementing that capability logic here, so the two
 *   plugins can never disagree about who's allowed to see what.
 *
 *   Why per-course rather than one link at the top of the page: the
 *   matrix is student-centric and can show units spanning more than one
 *   course at once (topiccourseidmap already resolves each unit's own
 *   course individually), whereas an Assessment/Sampling Plan is scoped
 *   to one course per student. A student on two courses gets two small
 *   panels; a student on one course (the common case) gets one. No
 *   database schema changes on this side.
 *
 * v25.1 — Privacy provider rewritten from scratch (classes/privacy/provider.php).
 *   It previously declared null_provider ("stores no personal data"), which
 *   stopped being true as of v17 and was never corrected. The client
 *   confirmed this instance stores real student grades/comments, so this
 *   was a genuine GDPR compliance gap, not just a code-quality issue.
 *   Now implements metadata_provider + plugin\provider + core_userlist_provider
 *   across all four of this plugin's tables (grades, sampling,
 *   evidence_comments, unit_comments). Evidence comments have no courseid
 *   column, so context resolution/deletion for that table joins through
 *   the same item→criterion→topic→course chain already proven correct in
 *   evidence_comment.php (mm.compid = dtm.descrid, dtm.topicid = ct.topicid).
 *   Deletion policy: a student's own rows are deleted on their request;
 *   a staff member's authorship on another student's row is left in place
 *   (academic-record retention basis) rather than deleted — see the design
 *   note at the top of provider.php before changing this. No schema changes.
 *
 * v25 — Client-reported issues fixed, no schema changes (reuses existing
 *   commentedby/gradedby/timemodified columns already on the relevant
 *   tables — see §3 of the handover):
 *   - BUG FIXED: evidence_comment.php reported a successful save using the
 *     'gradesaved' string ("Grade saved"), left over from copy/pasting the
 *     grade-save response shape. Added a dedicated 'evidencecommentsaved'
 *     string ("Comment saved") and pointed the endpoint at it.
 *   - ADDED: the evidence-item comment and the Assessor Grade comment now
 *     show a "commented by <name>, <date>" byline once a comment exists,
 *     matching the existing IQA comment's attribution — previously only
 *     the IQA comment showed who wrote it. Both new bylines are gated on
 *     the same has*comment flag in BOTH the editable and read-only
 *     template branches (matrix_data.php builds one pre-formatted byline
 *     string per comment, so there's a single source of truth rather than
 *     separate editable/read-only logic to keep in sync — the exact
 *     asymmetry that caused the v24 bug).
 *   - ADDED: the date/time the comment was saved is now part of the
 *     byline text for all three attributed comments (evidence-item, grade,
 *     and IQA) via a new shared format_comment_byline() helper, using
 *     userdate() so it respects the site's date format/timezone settings.
 *     The IQA comment's existing name-resolution and blank-clears-
 *     attribution logic (§3/v24) is unchanged — only the displayed text
 *     gained a date suffix.
 *   - Commenter-name resolution (previously only collected IQA commenter
 *     userids) now also collects evidence-item commenters and grade
 *     commenters into the same batched fullname() query, so no extra DB
 *     queries were added despite the two new bylines.
 *
 * v24 — Client-reported bug fixed: after testing the IQA comment as an
 *   admin account, the box showed the admin's name under it to other users
 *   even though no comment was currently present. Root cause: the
 *   "commented by <name>" byline in the editable view was shown whenever
 *   iqacommentby was set, without checking whether iqacomment itself was
 *   still non-blank — so a comment that had been typed then cleared back
 *   to empty left an orphaned name attached to an empty box. Fixed in two
 *   places:
 *   - Template: byline now only renders when hasiqacomment is true, in
 *     both the editable and read-only views (the read-only view already
 *     had this right; only the editable view had the bug).
 *   - save_unit_comment(): now clears iqacommentby/iqacommenttime whenever
 *     the comment is saved blank, so this can't recur going forward
 *     (previously it always stamped the current user/time regardless of
 *     whether the comment text was empty).
 *   - Upgrade step cleans up any row already left in the stale state by
 *     testing before this fix landed (and does the same for the dormant
 *     assessorcomment columns, for consistency).
 *
 * v23 — Two client-reported bugs fixed:
 *   - BUG FIXED: evidence-item comments were keyed only on itemid. The same
 *     evidence file can be linked to more than one criterion (a separate
 *     block_exacompcompuser_mm row per link), so a comment written against
 *     one criterion was appearing identically under every other criterion
 *     that file happened to also be attached to. Comments are now keyed on
 *     mmid (the specific item↔criterion link row) instead. Includes an
 *     upgrade step that adds the mmid column, best-effort backfills
 *     existing comment rows to one of their current links (there's no way
 *     to know which criterion a pre-existing comment was actually about,
 *     since that was never recorded — sites with more than a handful of
 *     such comments at upgrade time should sanity-check them afterwards),
 *     drops any comment rows that can't be matched to a current link at
 *     all, then swaps the unique index from (studentid, itemid) to
 *     (studentid, mmid).
 *   - BUG FIXED: no save confirmation. The evidence-item comment box had no
 *     status indicator at all (silently swallowed the response); the
 *     unit-level IQA comment box had one, but at 0.7rem/normal-weight it
 *     was easy to miss. Both now show an explicit "✓ Comment saved" /
 *     error message at 0.8rem bold directly under the box, matching the
 *     grade/sampling save-status pattern already used elsewhere on the
 *     matrix, and green/red colours with better contrast.
 *
 * v22 (bug audit + Moodle-upgrade readiness pass) —
 *   - BUG FOUND & FIXED: the commenter-name lookup only selected
 *     id/firstname/lastname from {user}, then passed that straight into
 *     fullname(). On any site whose fullnamedisplay format includes
 *     middlename, alternatename, or the phonetic name fields (not used
 *     here, but common on multi-language sites), fullname() would throw
 *     PHP warnings for the missing properties. Now selects the full set of
 *     name fields, matching the pattern already used for the student
 *     selector in view.php.
 *   - BUG FOUND & FIXED (pre-existing, not introduced by v22): the
 *     block/nvq_matrix:grade and block/nvq_matrix:sample capabilities
 *     (added in v18/v19) never had matching lang strings, so the
 *     "Define roles" / "Manage roles" screens showed a fallback
 *     "[[nvq_matrix:grade]]" style missing-string placeholder in Site
 *     Administration for both. Added nvq_matrix:grade and nvq_matrix:sample
 *     strings alongside the two new v22 capability strings.
 *   - Moodle-upgrade readiness: removed the hardcoded $plugin->supported
 *     upper bound ([405, 501]). That setting only drives a cosmetic
 *     "not officially supported on this version" notice on the plugin
 *     overview page — it doesn't block install/upgrade — but leaving a
 *     ceiling in place meant it would silently go stale and trigger that
 *     notice the moment the site moves past Moodle 5.1. Nothing in this
 *     plugin depends on APIs newer than the existing 4.5 floor
 *     ($plugin->requires), so there's no real ceiling to declare.
 *
 * v22 — Added optional unit-level assessor comment and IQA comment, per
 *   client request. NOTE: the "assessor comment" half was removed again
 *   shortly after (same v22→v23 pass) since the existing grade comment
 *   already covers that — kept here for history, and because the now-
 *   unused assessorcomment columns are still present on
 *   block_nvq_matrix_unit_comments (not dropped, see removal note below):
 *   - New dedicated table (block_nvq_matrix_unit_comments) holding both
 *     comments per student/unit/course. Each comment is fully optional and
 *     independent of the other and of the grade/sampling verdicts — an
 *     assessor comment can be left without setting a grade, and vice versa.
 *   - Two new capabilities: block/nvq_matrix:assessorcomment
 *     (editingteacher, manager — same as :grade) and
 *     block/nvq_matrix:iqacomment (teacher, editingteacher, manager). The
 *     IQA comment is deliberately the FIRST write capability given to the
 *     'teacher' archetype in this plugin — per client confirmation, IQA
 *     reviewers hold the 'teacher' role and are meant to be able to write
 *     this one field, unlike grading/sampling which remain read-only for
 *     them.
 *   - Every saved comment records who wrote it (assessorcommentby /
 *     iqacommentby) and the name is resolved and displayed next to the
 *     comment, since distinguishing assessor vs IQA authorship was the
 *     point of the request.
 *   - New unit_comment.php AJAX endpoint, following the same validation
 *     pattern as grade.php/sample.php: courseid-belongs-to-topic check,
 *     then capability check scoped to that exact course context (which
 *     capability depends on type=assessor|iqa), then the same
 *     not-also-:viewall check to stop one assessor/IQA commenting as if
 *     they were grading a peer.
 *   - Template: two new optional textareas per unit, editable on blur (same
 *     UX as evidence-item comments), read-only elsewhere, each showing the
 *     commenter's name once a comment exists.
 *
 * v21 — HOTFIX: fatal PHP syntax error, per client report.
 *   - v20's edit that inserted clear_grade() accidentally clipped the
 *     opening "/**" off the following build_item_url() docblock, leaving a
 *     dangling comment body starting with a bare "*" — a fatal parse error
 *     ("unexpected token *, expecting function or const") that broke the
 *     entire block on every page load. Restored the missing opener.
 *   - Full file re-scanned for the same class of issue (docblock open/close
 *     balance checked across every PHP file in the plugin) — none found.
 *
 * v20 — UI fixes + grade removal, per client testing feedback:
 *   - Fixed a real layout bug: the criteria table's column widths (38%+32%)
 *     were left over from the old 3-column per-criterion grading design and
 *     only filled 70% of the table after that column was removed in v17,
 *     making the table look compressed. Widths corrected to 46%/54%.
 *   - Fixed the evidence-item comment box being squashed onto the same line
 *     as the icon/link — the <li> was still flex-row from before the
 *     comment box existed. Now stacks properly with clearer spacing/border.
 *   - Added a "Clear grade" action: deletes the grade row entirely,
 *     distinct from setting value=0 (a real "Not Yet Competent" verdict).
 *     Only shown once a grade is set; requires confirmation; syncs the
 *     header badge back to "Not graded" on success.
 *   - evidence_comment.php SECURITY FIX: it verified the item belonged to
 *     the student and that the caller held :grade in the submitted course,
 *     but never checked those two facts were actually connected — an
 *     assessor with :grade in Course A could comment on evidence only
 *     linked to Course B, provided the student was enrolled in both. Now
 *     validates the item's own topic/course chain matches the submitted
 *     courseid, same pattern already used in grade.php/sample.php.
 *
 * v19 — Split grading onto its own capability, per client request:
 *   - New capability block/nvq_matrix:grade (editingteacher + manager only),
 *     mirroring :sample. Grading and evidence-item comments now require
 *     this capability instead of reusing :viewall.
 *   - The 'teacher' archetype (non-editing teacher, used for EQA/IQA
 *     reviewers) keeps :viewall — full read access to grades, sampling
 *     status, and comments — but can no longer set any of them. Previously
 *     this role could still grade, which the client flagged as unintended.
 *   - :viewall remains as the broader "can see all students' matrices"
 *     capability, still held by teacher/editingteacher/manager.
 *
 * v18 — Per-unit sampling status + per-evidence-item comments:
 *   - New "Sampling" indicator per unit: blank / Sampled / Not Yet Sampled,
 *     set via dropdown. New capability block/nvq_matrix:sample, granted only
 *     to editingteacher and manager — the 'teacher' archetype (used for
 *     non-editing EQA/IQA reviewers) can view but never set it.
 *   - New optional comment box on each individual evidence row, separate
 *     from the unit-level grading comment. Editable by anyone with
 *     block/nvq_matrix:viewall; visible read-only to students.
 *   - Two new dedicated tables (block_nvq_matrix_sampling,
 *     block_nvq_matrix_evidence_comments) — same isolation principle as
 *     block_nvq_matrix_grades; exacomp is never touched.
 *
 * v17 — Critical grading fix + unit-level regrading, per client review:
 *   - BUG FOUND: block_alexdd_assessor auto-writes 'value=1' to
 *     block_exacompcompuser (role=1) the instant evidence is linked,
 *     which v15/v16 grading mistakenly read as a real assessor verdict —
 *     causing every newly-uploaded item to show "Competent" by default,
 *     and occasionally colliding with duplicate rows from that same
 *     auto-seed logic (different reviewerid = no unique-row guarantee),
 *     which could make save_grade() silently fail or read back the wrong
 *     verdict. FIX: grading now lives entirely in a new, dedicated table
 *     (block_nvq_matrix_grades) that exacomp and the assessor plugin never
 *     touch — see db/install.xml / db/upgrade.php.
 *   - Grading is now UNIT-level, not per-criterion: one Competent / Not Yet
 *     Competent verdict per Topic (unit) per student per course, with an
 *     optional comment. Far less bulky to grade when a unit has many
 *     evidenced criteria.
 *   - Grade control appears twice: a compact read-only-style badge in the
 *     unit header (visible even collapsed), and full interactive controls
 *     in a summary row at the bottom of the expanded unit body. Both stay
 *     in sync via JS after a save.
 *   - Grading requires at least one criterion in the unit to have evidence.
 *   - Confirmation dialog before every save; buttons lock during save.
 *
 * v16 — Grading fixes and safeguards, per client review:
 *   - Grades are now scoped by the descriptor's actual course (resolved via
 *     block_exacompcoutopi_mm), fixing a v15 bug where grade rows had no
 *     courseid and could collide/leak across courses sharing a descriptor.
 *   - Orphan rows (no parent LO) are never gradeable — shows a dash.
 *   - Grading controls are disabled until evidence is linked to the criterion;
 *     enforced both in the template and server-side in grade.php.
 *   - Confirmation dialog required before every grade save.
 *   - Save buttons lock during an in-flight request to prevent double-submits.
 *   - Fixed a double-escaping bug where comment text was HTML-escaped twice
 *     (format_text() + Mustache), corrupting saved comments on re-edit.
 *   - No longer syncs block_exacompcompuser_mm or exacomp's own grading
 *     history — the NVQ matrix is the sole record of truth for this verdict,
 *     per client direction; exacomp's native ECG grid is not audited.
 *
 * v15 — Assessor grading column: a Competent / Not Yet Competent verdict
 * with optional comment, editable by users with block/nvq_matrix:viewall,
 * read-only for all other roles. Writes to block_exacompcompuser (role=1,
 * teacher). New grade.php AJAX endpoint, sesskey + strict per-student
 * capability checked server-side. No database schema changes (reuses
 * existing exacomp tables/columns).
 *
 * v14 — Clickable evidence links: file and note items open in Exabis ePortfolio
 * via shared_item.php; link items open the stored URL directly in a new tab.
 * No database schema changes.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026083101;
$plugin->requires  = 2024100700; // Moodle 4.5 — floor only, nothing here is version-pinned above that.
// $plugin->supported deliberately omitted. Setting an upper branch number here
// (e.g. [405, 501]) only controls a cosmetic "not officially supported"
// notice on the plugin overview page — it does NOT block installation or
// upgrades on a newer Moodle. Leaving it unset avoids that notice
// re-appearing (and someone forgetting to bump it) every time the site
// upgrades past whatever ceiling was hardcoded here. Nothing in this plugin
// uses APIs that are version-pinned above 4.5, so there is no real ceiling
// to declare. If a future Moodle major version deprecates something this
// plugin relies on (has_capability, is_enrolled, moodle_url, the mustache
// renderer, or the exacomp tables it reads from), that will surface as a
// clear error on upgrade — re-test at that point rather than pre-emptively.
$plugin->component = 'block_nvq_matrix';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.20.7';
