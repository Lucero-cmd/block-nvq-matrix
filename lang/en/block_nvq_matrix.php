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
 * Language strings for the block_nvq_matrix plugin.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'NVQ Competence Matrix';
$string['messageprovider:coursecomplete'] = 'Course completion notifications';
$string['nvq_matrix:addinstance']  = 'Add an NVQ Competence Matrix block';
$string['nvq_matrix:myaddinstance']= 'Add an NVQ Competence Matrix block to My Dashboard';
$string['nvq_matrix:viewall']      = 'View all students in the NVQ Competence Matrix';
$string['nvq_matrix:grade']        = 'Set the unit grade in the NVQ Competence Matrix';
$string['nvq_matrix:sample']       = 'Set the unit sampling status in the NVQ Competence Matrix';
$string['nvq_matrix:iqacomment']   = 'Set the unit-level IQA comment in the NVQ Competence Matrix';
$string['nvq_matrix:finalstatus']  = 'Set the final Pass/Fail status in the NVQ Competence Matrix';
$string['nvq_matrix:manageassessor'] = 'Set the designated assessor in the NVQ Competence Matrix';
$string['nvq_matrix:exportportfolio'] = 'Export a student\'s full portfolio from the NVQ Competence Matrix';
$string['nvq_matrix:deletearchived'] = 'Permanently delete an archived student\'s NVQ matrix data for a course';

$string['exportportfolio']         = 'Export portfolio';
$string['exportportfolioconfirm']  = 'Export {$a}\'s full portfolio (matrix overview, evidence files, assessment plan and sampling records) as a zip?';

$string['selectstudent']           = 'Select student';
$string['selectstudentprompt']     = '— Select a student —';
$string['searchstudentplaceholder'] = 'Search by name…';
$string['filterneedsgrading']      = 'On-going';
$string['filtercompleted']         = 'Completed';
$string['filterall']               = 'All';
$string['statuscompleted']         = 'Completed';
$string['statusneedsgrading']      = 'On-going';
$string['statusarchived']          = 'Archived';
$string['deletearchived']          = 'Delete';
$string['deletearchivedconfirm']   = 'Permanently delete {$a->name}\'s NVQ matrix data (grades, sampling, comments, final status, and evidence comments) for {$a->coursename}? This cannot be undone.';
$string['deletearchivedsuccess']   = 'Archived student data deleted.';
$string['deletearchivederror']     = 'Something went wrong deleting this data. Nothing was changed.';
$string['deletearchivednopermission'] = 'You do not have permission to delete this data.';
$string['deletearchivedstillenrolled'] = 'This student is still actively enrolled on this course, so their data cannot be deleted here.';
$string['showarchived']            = 'Show archived (unenrolled) students';
$string['finalstatuspass']         = 'Pass';
$string['finalstatusfail']         = 'Fail';
$string['finalstatusnotset']       = 'Not set';
$string['nostudentsmatch']         = 'No students match your search or filter.';
$string['completedfrom']           = 'Completed from';
$string['completedto']             = 'Completed to';
$string['completedondate']         = 'Completed {$a}';
$string['togglesearch']            = 'Search students';
$string['togglefilter']            = 'Filter students';
$string['idleprompt']              = 'Select a student above to view their competence matrix.';
$string['nostudents']              = 'No students are enrolled in this course.';
$string['noevidence']              = 'No evidence linked yet';
$string['nodata']                  = 'No competence data found. Ensure the Exabis Competence Grid block is configured for this course and students have linked portfolio evidence.';
$string['assessmentcriterion']     = 'Assessment Criterion';
$string['evidencelinked']          = 'Evidence Linked';
$string['assessorverdict']         = 'Assessor Verdict';
$string['competent']               = 'Competent';
$string['notcompetent']            = 'Not Yet Competent';
$string['notyet']                  = 'Not Yet Competent';
$string['notgraded']               = 'Not yet graded';
$string['notassessed']             = 'Not Yet Assessed';
$string['col_grade']               = 'Assessor Grade';
$string['gradecomment']            = 'Add a comment (optional)';
$string['commentdate']             = 'Comment date';
$string['commentdatehelp']         = 'Defaults to today. Change this to backdate the comment, e.g. when grading a portfolio that was actually completed earlier.';
$string['gradesaved']              = 'Grade saved';
$string['gradecleared']            = 'Grade removed';
$string['gradeerror']              = 'Could not save grade. Please try again.';
$string['gradenopermission']       = 'You do not have permission to grade this student.';
$string['gradenoevidence']         = 'Evidence must be linked before this criterion can be graded.';
$string['gradedisabledtext']       = 'Link evidence to enable grading';
$string['confirmgradecompetent']   = 'Mark this criterion as Competent for this student?';
$string['confirmgradenotyet']      = 'Mark this criterion as Not Yet Competent for this student?';
$string['confirmgradeclear']       = 'Remove the grade for this unit? The student will show as Not Graded.';
$string['cleargrade']              = 'Clear grade';
$string['orphannograde']           = '—';
$string['sampled']                 = 'Sampled';
$string['notyetsampled']           = 'Not Yet Sampled';
$string['samplingstatus']          = 'Sampling Status';
$string['samplingblank']           = '— Not set —';
$string['samplingsaved']           = 'Sampling status saved';
$string['samplingerror']           = 'Could not save sampling status. Please try again.';
$string['samplingnopermission']    = 'You do not have permission to set sampling status.';
$string['evidencecommentplaceholder'] = 'Add a comment on this evidence (optional)';
$string['evidencecommentsaved']       = 'Comment saved';
$string['iqacommentlabel']            = 'IQA comment';
$string['iqacommentplaceholder']      = 'Add an IQA comment (optional)';
$string['commentedbyprefix']          = '— {$a}';
$string['unitcommentsaved']           = 'Comment saved';
$string['unitcommenterror']           = 'Could not save comment. Please try again.';
$string['unitcommentnopermission']    = 'You do not have permission to add this comment.';
$string['unitprogress']            = '{$a->met} of {$a->total} criteria evidenced';
$string['overallprogress']         = 'Overall: {$a->met} of {$a->total} criteria evidenced across all units';
$string['assessorprogress']        = 'Assessor progress: {$a->graded} of {$a->total} units graded';
$string['gapanalysis']             = 'Gaps Remaining';
$string['gapanalysisdone']         = 'Showing Gaps Only';
$string['nogapsremaining']         = 'Fully Evidenced';
$string['viewportfolio']           = 'View in portfolio';
$string['configtitle']             = 'Block title';
$string['configtitle_desc']        = 'The title displayed on the block header.';
$string['defaulttitle']            = 'NVQ Competence Matrix';
$string['viewfullpage']            = 'View full matrix page';
$string['backtodashboard']         = 'Back to dashboard';
// Deprecated: this block used to declare null_provider and cite this reason.
// Kept only so old language-pack overrides/caches don't show a missing-string
// warning; no longer referenced by classes/privacy/provider.php since v25.
$string['privacy:no_data_reason']  = 'This block displays data from Exabis ePortfolio and Exabis Competence Grid. It does not store any personal data of its own.';

$string['privacy:authoredentries'] = 'Entries you wrote about other students';

$string['privacy:metadata:block_nvq_matrix_grades'] = 'The unit-level assessor grade (Competent / Not Yet Competent) and optional comment recorded for a student against a unit.';
$string['privacy:metadata:block_nvq_matrix_grades:studentid'] = 'The ID of the student the grade applies to.';
$string['privacy:metadata:block_nvq_matrix_grades:topicid'] = 'The unit (exacomp topic) the grade applies to.';
$string['privacy:metadata:block_nvq_matrix_grades:courseid'] = 'The course the unit belongs to.';
$string['privacy:metadata:block_nvq_matrix_grades:value'] = 'The grade verdict: Competent or Not Yet Competent.';
$string['privacy:metadata:block_nvq_matrix_grades:comment'] = 'An optional comment the assessor wrote alongside the grade.';
$string['privacy:metadata:block_nvq_matrix_grades:gradedby'] = 'The ID of the assessor who last saved this grade.';
$string['privacy:metadata:block_nvq_matrix_grades:timemodified'] = 'The date and time the grade was last saved.';
$string['privacy:metadata:block_nvq_matrix_grades:commentedby'] = 'The ID of the assessor who last saved the comment. Tracked separately from the assessor who set the grade verdict, since clearing the verdict does not clear the comment.';
$string['privacy:metadata:block_nvq_matrix_grades:commenttime'] = 'The date shown against the comment. Normally the save time, but may be manually backdated by the assessor.';

$string['privacy:metadata:block_nvq_matrix_sampling'] = 'The IQA/EQA sampling status recorded for a student against a unit.';
$string['privacy:metadata:block_nvq_matrix_sampling:studentid'] = 'The ID of the student the sampling status applies to.';
$string['privacy:metadata:block_nvq_matrix_sampling:topicid'] = 'The unit (exacomp topic) the sampling status applies to.';
$string['privacy:metadata:block_nvq_matrix_sampling:courseid'] = 'The course the unit belongs to.';
$string['privacy:metadata:block_nvq_matrix_sampling:status'] = 'The sampling status: Sampled or Not Yet Sampled.';
$string['privacy:metadata:block_nvq_matrix_sampling:sampledby'] = 'The ID of the IQA/EQA user who last set this status.';
$string['privacy:metadata:block_nvq_matrix_sampling:timemodified'] = 'The date and time the sampling status was last saved.';

$string['privacy:metadata:block_nvq_matrix_evidence_comments'] = 'An optional comment an assessor attached to a single piece of evidence for a student, plus its evidence type classification.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:studentid'] = 'The ID of the student who owns the evidence item being commented on.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:comment'] = 'The comment text.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:evidencetype'] = 'The evidence type classification chosen for this evidence item (e.g. Observation, Product, Witness Testimony).';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:commentedby'] = 'The ID of the user who wrote this comment.';
$string['privacy:metadata:block_nvq_matrix_evidence_comments:timemodified'] = 'The date and time the comment was last saved.';
$string['privacy:metadata:block_nvq_matrix_evidence_types'] = 'One or more evidence type classifications chosen for a single piece of evidence (an item can genuinely fit more than one type, e.g. both Observation and Professional Discussion).';
$string['privacy:metadata:block_nvq_matrix_evidence_types:evidencecommentid'] = 'The evidence item this type classification applies to.';
$string['privacy:metadata:block_nvq_matrix_evidence_types:code'] = 'The evidence type code (e.g. Observation, Product, Witness Testimony).';
$string['privacy:metadata:block_nvq_matrix_status'] = 'The final Pass/Fail status recorded for a student on a course, and a record of when/by whom a completion notification was sent.';
$string['privacy:metadata:block_nvq_matrix_status:studentid'] = 'The ID of the student this final status applies to.';
$string['privacy:metadata:block_nvq_matrix_status:courseid'] = 'The course this final status applies to.';
$string['privacy:metadata:block_nvq_matrix_status:status'] = 'The final result: Pass or Fail.';
$string['privacy:metadata:block_nvq_matrix_status:setby'] = 'The ID of the assessor who set this final status.';
$string['privacy:metadata:block_nvq_matrix_status:timemodified'] = 'The date and time the final status was last set.';
$string['privacy:metadata:block_nvq_matrix_status:notifiedtime'] = 'The date and time the completion notification was last sent to the student, if ever.';
$string['privacy:metadata:block_nvq_matrix_status:notifiedby'] = 'The ID of the assessor who last sent the completion notification.';
$string['privacy:metadata:block_nvq_matrix_assessor'] = 'The designated assessor for a student\'s course.';
$string['privacy:metadata:block_nvq_matrix_assessor:courseid'] = 'The ID of the course this assessor assignment applies to.';
$string['privacy:metadata:block_nvq_matrix_assessor:userid'] = 'The ID of the user assigned as assessor.';
$string['privacy:metadata:block_nvq_matrix_assessor:setby'] = 'The ID of the user who made this assessor assignment.';
$string['privacy:metadata:block_nvq_matrix_assessor:timemodified'] = 'When this assessor assignment was last changed.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive'] = 'A record of when an archived student\'s NVQ matrix data was permanently deleted.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:studentid'] = 'The ID of the student whose archived data was deleted.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:courseid'] = 'The ID of the course the deleted data belonged to.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:timecleared'] = 'When the archived data was deleted.';
$string['privacy:metadata:block_nvq_matrix_cleared_archive:clearedby'] = 'The ID of the user who deleted the archived data.';
$string['privacy:assessorrole:assignee'] = 'Assigned as assessor';
$string['privacy:assessorrole:assignedby'] = 'Made this assessor assignment';
$string['privacy:metadata:block_nvq_matrix:core_message'] = 'This plugin sends a completion notification to the student via Moodle\'s own messaging system when an assessor confirms they want to notify the student of their final status.';

$string['privacy:metadata:block_nvq_matrix_unit_comments'] = 'Optional unit-level assessor and IQA comments recorded for a student against a unit.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:studentid'] = 'The ID of the student the comments apply to.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:topicid'] = 'The unit (exacomp topic) the comments apply to.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:courseid'] = 'The course the unit belongs to.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:iqacomment'] = 'The IQA comment text.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:iqacommentby'] = 'The ID of the IQA user who wrote the IQA comment.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:iqacommenttime'] = 'The date and time the IQA comment was last saved.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:assessorcomment'] = 'A legacy unit-level assessor comment field, not shown in the current UI, kept for sites that briefly ran the version of this plugin that displayed it.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:assessorcommentby'] = 'The ID of the assessor who wrote the legacy assessor comment.';
$string['privacy:metadata:block_nvq_matrix_unit_comments:assessorcommenttime'] = 'The date and time the legacy assessor comment was last saved.';
$string['launcherdesc']   = 'View your NVQ competence matrix, track evidence against each assessment criterion, and monitor your qualification progress.';
$string['launcherbutton'] = 'View Matrix';
$string['assessmentplan'] = 'Assessment plan';
$string['samplingplan'] = 'Sampling plan';
$string['samplingrecord'] = 'Sampling record';

// Evidence type dropdown.
$string['evidencetype']            = 'Evidence type';
$string['evidencetypeedit']        = 'Edit types';
$string['evidencetypenotset']      = 'Not set';
$string['editmatrix']              = 'Edit matrix';
$string['doneediting']             = 'Done editing';
$string['evidencetypesaved']       = 'Evidence type saved';

// Final Pass/Fail status + completion notification.
$string['finalstatusheading']  = 'Final status';
$string['statusnotset']        = 'Not yet set';
$string['statuspass']          = 'Pass';
$string['statusfail']          = 'Fail';
$string['setpass']             = 'Pass';
$string['setfail']             = 'Fail';
$string['statussaved']         = 'Status saved';
$string['statussetby']         = 'Set by {$a->name}, {$a->date}';
$string['statusdate']          = 'Status date';
$string['statusdatehelp']      = 'Defaults to today. Change this to backdate the status, e.g. recording a result for a student who genuinely completed before this feature existed.';
$string['clearstatus']         = 'Clear status';
$string['clearstatusconfirm']  = 'Clear the final status for {$a->name} on {$a->course}? This cannot be undone — you\'ll need to set it again if this was wrong.';
$string['statuscleared']       = 'Status cleared';
$string['notifystudent']       = 'Notify student';
$string['notifyconfirm']       = 'Send a "{$a->status}" completion notification to {$a->name} for {$a->course} now?';
$string['notificationsent']    = 'Notification sent';
$string['nostatusset']         = 'Set a Pass or Fail status first, before sending a notification.';
$string['notifiedbyondate']    = 'Notified by {$a->name} on {$a->date}';
$string['notifiedondate']      = 'Notified on {$a->date}';
$string['viewyourmatrix']      = 'View your competence matrix';

// Completion notification content — sent via Moodle messaging (see db/messages.php).
$string['completionsubjectpass'] = 'You\'ve completed {$a}!';
$string['completionbodypass']    = '<p>Hi {$a->name},</p><p><strong>Congratulations — you have successfully completed {$a->course}!</strong></p><p>Well done on all the hard work that got you here. Your assessor has confirmed your final result as a <strong>Pass</strong>.</p><p>You can view your full portfolio and competence matrix at any time by logging into your account and accessing your NVQ competence matrix.</p>';
$string['completionsubjectfail'] = 'Your {$a} result is now available';
$string['completionbodyfail']    = '<p>Hi {$a->name},</p><p>Your assessor has recorded your final result for <strong>{$a->course}</strong>.</p><p>This has been recorded as <strong>Not Yet Achieved</strong>. This isn\'t the end of the road — please speak to your assessor about what\'s needed to get there.</p>';

// Assessor designation (dropdown at the top of the matrix) + submission notification.
$string['assessorheading']       = 'Assessor';
$string['assessornotset']        = 'Not set';
$string['assessorsaving']        = 'Saving…';
$string['assessorsaved']         = 'Assessor saved';
$string['assessorcleared']       = 'Assessor cleared';
$string['assessorstale']         = '(no longer eligible)';
$string['backtomatrix']          = 'Back to matrix';
$string['assessorpageintro']     = 'Choose the designated Assessor for each course you manage. The Assessor is notified when a student submits new evidence.';
$string['tasknotifyassessors']   = 'Notify assessors of new evidence submissions';
$string['renotifyonedit']        = 'Notify assessor on every resubmission';
$string['renotifyonedit_desc']   = 'When enabled (default), the designated Assessor is notified every time a student saves changes to an eportfolio item that has competencies linked to it - including a re-edit where the linked competencies haven\'t actually changed (exaport re-links them on every save regardless). When disabled, the Assessor is only notified the first time a given item triggers a submission; later re-edits of that same item are silent.';
$string['assessorerror']         = 'Could not save assessor. Please try again.';
$string['assessornopermission']  = 'You do not have permission to set the assessor for this course.';
$string['invalidassessor']       = 'The selected user is not a valid assessor for this course.';
$string['assessorsubmissionsubject'] = 'New submission in {$a}';
$string['assessorsubmissionbody']    = '<p>{$a->student} has submitted new evidence in <strong>{$a->course}</strong>.</p><p>You are receiving this because you are the designated Assessor for this course.</p>';
