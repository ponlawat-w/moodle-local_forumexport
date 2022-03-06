<?php
define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/calendar/externallib.php');
require_once(__DIR__ . '/classes/form/extended_export_form.php');

$forumid = required_param('id', PARAM_INT);
$userids = optional_param_array('userids', [], PARAM_INT);
$discussionids = optional_param_array('discids', [], PARAM_INT);
$from = optional_param_array('from', [], PARAM_INT);
$to = optional_param_array('to', [], PARAM_INT);
$fromtimestamp = optional_param('timestampfrom', '', PARAM_INT);
$totimestamp = optional_param('timestampto', '', PARAM_INT);

if (!empty($from['enabled'])) {
    unset($from['enabled']);
    $from = core_calendar_external::get_timestamps([$from])['timestamps'][0]['timestamp'];
} else {
    $from = $fromtimestamp;
}

if (!empty($to['enabled'])) {
    unset($to['enabled']);
    $to = core_calendar_external::get_timestamps([$to])['timestamps'][0]['timestamp'];
} else {
    $to = $totimestamp;
}

$vaultfactory = mod_forum\local\container::get_vault_factory();
$managerfactory = mod_forum\local\container::get_manager_factory();
$legacydatamapperfactory = mod_forum\local\container::get_legacy_data_mapper_factory();

$forumvault = $vaultfactory->get_forum_vault();

$forum = $forumvault->get_from_id($forumid);
if (empty($forum)) {
    throw new moodle_exception('Unable to find forum with id ' . $forumid);
}

$capabilitymanager = $managerfactory->get_capability_manager($forum);

$course = $forum->get_course_record();
$coursemodule = $forum->get_course_module_record();
$cm = cm_info::create($coursemodule);

require_course_login($course, true, $cm);

$url = new moodle_url('/local/forumexport/export.php');
$pagetitle = get_string('export', 'mod_forum');
$context = $forum->get_context();

require_capability('local/forumexport:exportforum', $context);

$form = new \local_forumexport\form\extended_export_form($url->out(false), [
    'forum' => $forum,
    'courseid' => $course->id
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/forum/view.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    $dataformat = $data->format;

    // This may take a very long time and extra memory.
    \core_php_time_limit::raise();
    raise_memory_limit(MEMORY_HUGE);

    $discussionvault = $vaultfactory->get_discussion_vault();
    $postvault = $vaultfactory->get_post_vault();
    if ($data->discussionids) {
        $discussionids = $data->discussionids;
    } else if (empty($discussionids)) {
        $discussions = $discussionvault->get_all_discussions_in_forum($forum);
        $discussionids = array_map(function ($discussion) {
            return $discussion->get_id();
        }, $discussions);
    }

    $groupmode = isset($data->groupmode) ? $data->groupmode : LOCAL_FORUMEXPORT_GROUP_ALL;

    $groupbydiscussiongroup = isset($data->groupbydiscussiongroup) ? $data->groupbydiscussiongroup : false;
    $groupbydiscussionstarter = isset($data->groupbydiscussionstarter) ? $data->groupbydiscussionstarter : false;
    $groupbyparticipants = isset($data->groupbyparticipants) ? $data->groupbyparticipants : false;

    if ($groupmode == LOCAL_FORUMEXPORT_GROUP_ALL) {
        require_capability('local/forumexport:exportdifferentgroup', $context);
        $groupids = null;
    } else if ($groupmode == LOCAL_FORUMEXPORT_GROUP_MY) {
        $mygroups = groups_get_all_groups($course->id, $USER->id);
        $groupids = array_map(function($group) { return $group->id; }, $mygroups);
        local_forumexport_checkexportablegroups($context, $course->id, $groupids);

        $discussionids = local_forumexport_filterdiscussionidsbygroups($discussionids, $groupids, $groupbydiscussiongroup, $groupbydiscussionstarter, $groupbyparticipants);
    } else if ($groupmode == LOCAL_FORUMEXPORT_GROUP_CUSTOM) {
        $groupids = $data->groups;
        local_forumexport_checkexportablegroups($context, $course->id, $groupids);

        $discussionids = local_forumexport_filterdiscussionidsbygroups($discussionids, $groupids, $groupbydiscussiongroup, $groupbydiscussionstarter, $groupbyparticipants);
    }

    $filters = ['discussionids' => $discussionids];
    if ($data->useridsselected) {
        $filters['userids'] = $data->useridsselected;
    }
    if ($data->from) {
        $filters['from'] = $data->from;
    }
    if ($data->to) {
        $filters['to'] = $data->to;
    }

    // Retrieve posts based on the selected filters.
    $posts = empty($discussionids) ? [] : $postvault->get_from_filters($USER, $filters, $capabilitymanager->can_view_any_private_reply($USER));

    $striphtml = !empty($data->striphtml);
    $humandates = !empty($data->humandates);

    $fields = ['id', 'discussion', 'parent', 'userid', 'userfullname', 'created', 'modified', 'mailed', 'subject', 'message',
                'messageformat', 'messagetrust', 'attachment', 'totalscore', 'mailnow', 'deleted', 'privatereplyto',
                'privatereplytofullname', 'wordcount', 'charcount'];

    $canviewfullname = has_capability('moodle/site:viewfullnames', $context);

    $datamapper = $legacydatamapperfactory->get_post_data_mapper();
    $exportdata = new ArrayObject($datamapper->to_legacy_objects($posts));
    $iterator = $exportdata->getIterator();

    $havegroupmode = $groupmode != LOCAL_FORUMEXPORT_GROUP_ALL;
    $includeallreplies = isset($data->includeallreplies) && $data->includeallreplies ? true : false;
    $includeparent = isset($data->includeparent) && $data->includeparent ? true: false;

    if ($includeallreplies) {
        require_capability('local/forumexport:includereplies', $context);
    }
    if ($includeparent) {
        require_capability('local/forumexport:includeparent', $context);
    }
    
    $useridsingroups = $havegroupmode && !$includeallreplies ? local_forumexport_getuseridsfromgroupids($groupids) : [];

    $filename = clean_filename('discussion');
    \core\dataformat::download_data(
        $filename,
        $dataformat,
        $fields,
        $iterator,
        function($exportdata) use (
            $fields, $striphtml, $humandates, $canviewfullname, $context,
            $havegroupmode, $includeallreplies, $includeparent, $useridsingroups
        ) {
            $data = new stdClass();

            if ($havegroupmode) {
                if (!$includeallreplies && $exportdata->parent != 0 && !in_array($exportdata->userid, $useridsingroups)) {
                    return null;
                }
                if (!$includeparent && $exportdata->parent == 0 && !in_array($exportdata->userid, $useridsingroups)) {
                    return null;
                }
            }

            foreach ($fields as $field) {
                // Set data field's value from the export data's equivalent field by default.
                $data->$field = $exportdata->$field ?? null;

                if ($field == 'userfullname') {
                    $user = \core_user::get_user($data->userid);
                    $data->userfullname = fullname($user, $canviewfullname);
                }

                if ($field == 'privatereplytofullname' && !empty($data->privatereplyto)) {
                    $user = \core_user::get_user($data->privatereplyto);
                    $data->privatereplytofullname = fullname($user, $canviewfullname);
                }

                if ($field == 'message') {
                    $data->message = file_rewrite_pluginfile_urls($data->message, 'pluginfile.php', $context->id, 'mod_forum',
                        'post', $data->id);
                }

                // Convert any boolean fields to their integer equivalent for output.
                if (is_bool($data->$field)) {
                    $data->$field = (int) $data->$field;
                }
            }

            if ($striphtml) {
                $data->message = html_to_text(format_text($data->message, $data->messageformat), 0, false);
                $data->messageformat = FORMAT_PLAIN;
            }
            if ($humandates) {
                $data->created = userdate($data->created);
                $data->modified = userdate($data->modified);
            }
            return $data;
        });
    die;
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pagetitle);

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

// It is possible that the following fields have been provided in the URL.
$form->set_data(['useridsselected' => $userids, 'discussionids' => $discussionids, 'from' => $from, 'to' => $to]);

$form->display();

echo $OUTPUT->footer();
