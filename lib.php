<?php

defined('MOODLE_INTERNAL') or die();

const LOCAL_FORUMEXPORT_GROUP_ALL = 0;
const LOCAL_FORUMEXPORT_GROUP_MY = 1;
const LOCAL_FORUMEXPORT_GROUP_CUSTOM = 2;

function local_forumexport_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;
    $context = $PAGE->context;
    if (!($context instanceof context_module)) {
        return;
    }

    $cm = get_coursemodule_from_id('forum', $context->instanceid);
    if (!$cm) {
        return;
    }

    if (has_capability('local/forumexport:exportforum', $context)) {
        $vaultfactory = mod_forum\local\container::get_vault_factory();
        $legacydatamapperfactory = mod_forum\local\container::get_legacy_data_mapper_factory();
        $forumvault = $vaultfactory->get_forum_vault();
        $forumentity = $forumvault->get_from_id($PAGE->cm->instance);
        $forumobject = $legacydatamapperfactory->get_forum_data_mapper()->to_legacy_object($forumentity);    

        $modulesettings = $settingsnav->find('modulesettings', null);
        if ($modulesettings) {
            $url = new moodle_url('/local/forumexport/export.php', ['id' => $forumobject->id]);
            $node = $modulesettings->create(get_string('export_extendedfunctionalities', 'local_forumexport'), $url, navigation_node::NODETYPE_LEAF, null, 'export_extendedfunctionalities');
            $modulesettings->add_node($node);
        }
    }
}

function local_forumexport_checkexportablegroups($modulecontext, $courseid, $groupids, $userid = 0) {
    global $USER;

    if (has_capability('local/forumexport:exportdifferentgroup', $modulecontext)) {
        return;
    }
    
    $userid = $userid ? $userid : $USER->id;

    $mygrouppings = groups_get_user_groups($courseid, $userid);
    $mygroupids = $mygrouppings[0];

    foreach ($groupids as $groupid) {
        if (!in_array($groupid, $mygroupids)) {
            require_capability('local/forumexport:exportdifferentgroup', $modulecontext);
        }
    }
}

function local_forumexport_getdiscussionidsstartedbygroups($discussionids, $groupids) {
    global $DB;

    $groupmemberids = local_forumexport_getuseridsfromgroupids($groupids);

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($inusersql, $userparams) = $DB->get_in_or_equal($groupmemberids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $userparams);

    $discussionidrecords = $DB->get_records_sql('SELECT id FROM {forum_discussions} WHERE id ' . $indiscussionsql . ' AND userid ' . $inusersql, $params);

    return array_values(array_map(function($dicussion) { return $dicussion->id; }, $discussionidrecords));
}

function local_forumexport_getdiscussionidsingroups($discussionids, $groupids) {
    global $DB;

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($ingroupsql, $groupparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $groupparams);

    $discussionidrecords = $DB->get_records_sql('SELECT id FROM {forum_discussions} WHERE id ' . $indiscussionsql . ' AND groupid ' . $ingroupsql, $params);

    return array_values(array_map(function($discussion) { return $discussion->id; }, $discussionidrecords));
}

function local_forumexport_getdiscussionidsparticipatedbygroups($discussionids, $groupids) {
    global $DB;

    $groupmemberids = local_forumexport_getuseridsfromgroupids($groupids);

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($inusersql, $userparams) = $DB->get_in_or_equal($groupmemberids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $userparams);

    $postdiscussionrecords = $DB->get_records_sql('SELECT DISTINCT discussion FROM {forum_posts} WHERE discussion ' . $indiscussionsql . ' AND userid ' . $inusersql, $params);

    return array_values(array_map(function($post) { return $post->discussion; }, $postdiscussionrecords));
}

function local_forumexport_filterdiscussionidsbygroups($discussionids, $groupids, $groupbydiscussiongroup, $groupbydiscussionstarter, $groupbyparticipants) {
    if (empty($discussionids) || empty($groupids)) {
        return [];
    }

    return array_unique(array_merge(
        $groupbydiscussiongroup ? local_forumexport_getdiscussionidsingroups($discussionids, $groupids) : [],
        $groupbydiscussionstarter ? local_forumexport_getdiscussionidsstartedbygroups($discussionids, $groupids) : [],
        $groupbyparticipants ? local_forumexport_getdiscussionidsparticipatedbygroups($discussionids, $groupids) : []
    ));
}

function local_forumexport_getuseridsfromgroupids($groupids) {
    $groupmemberids = [];
    foreach ($groupids as $groupid) {
        $groupmemberids += array_map(function ($user) { return $user->id; }, groups_get_members($groupid));
    }

    return $groupmemberids;
}
