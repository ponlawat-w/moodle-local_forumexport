<?php

defined('MOODLE_INTERNAL') or die();

const LOCAL_FORUMEXPORT_GROUP_ALL = 0;
const LOCAL_FORUMEXPORT_GROUP_MY = 1;
const LOCAL_FORUMEXPORT_GROUP_CUSTOM = 2;

function local_forumexport_extend_navigation() {
    global $PAGE;
    $context = $PAGE->context;
    if (!($context instanceof context_module)) {
        return;
    }
    $path = $PAGE->url->get_path();
    if (!preg_match('/\/mod\/forum\/view.php/i', $path)) {
        return;
    }

    if (has_capability('mod/forum:exportforum', $context)) {
        $vaultfactory = mod_forum\local\container::get_vault_factory();
        $legacydatamapperfactory = mod_forum\local\container::get_legacy_data_mapper_factory();
        $forumvault = $vaultfactory->get_forum_vault();
        $forumentity = $forumvault->get_from_id($PAGE->cm->instance);
        $forumobject = $legacydatamapperfactory->get_forum_data_mapper()->to_legacy_object($forumentity);    

        $url = new moodle_url('/local/forumexport/export.php', ['id' => $forumobject->id]);
        $PAGE->requires->string_for_js('export_extendedfunctionalities', 'local_forumexport');
        $PAGE->requires->js_call_amd('local_forumexport/injector', 'init', [$url->out()]);
    }
}

function local_forumexport_filterdiscussionidsbygroups($discussionids, $groupids) {
    global $DB;
    if (empty($discussionids) || empty($groupids)) {
        return [];
    }

    $groupmemberids = local_forumexport_getuseridsfromgroupids($groupids);

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($inusersql, $userparams) = $DB->get_in_or_equal($groupmemberids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $userparams);

    $discussionidrecords = $DB->get_records_sql('SELECT id FROM {forum_discussions} WHERE id ' . $indiscussionsql . ' AND userid ' . $inusersql, $params);

    return array_map(function($dicussion) { return $dicussion->id; }, $discussionidrecords);
}

function local_forumexport_getuseridsfromgroupids($groupids) {
    $groupmemberids = [];
    foreach ($groupids as $groupid) {
        $groupmemberids += array_map(function ($user) { return $user->id; }, groups_get_members($groupid));
    }

    return $groupmemberids;
}
