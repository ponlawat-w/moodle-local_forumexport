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
        $forumid = $context->instanceid;
        $url = new moodle_url('/local/forumexport/export.php', ['id' => $forumid]);
        $PAGE->requires->string_for_js('export_extendedfunctionalities', 'local_forumexport');
        $PAGE->requires->js_call_amd('local_forumexport/injector', 'init', [$url->out()]);
    }
}

function local_forumexport_filterdiscussionidsbygroups($discussionids, $groupids) {
    global $DB;
    if (empty($discussionids) || empty($groupids)) {
        return [];
    }

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($ingroupsql, $groupparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $groupparams);

    $discussionidrecords = $DB->get_records_sql('SELECT id FROM {forum_discussions} WHERE id ' . $indiscussionsql . ' AND groupid ' . $ingroupsql, $params);

    return array_map(function($dicussion) { return $dicussion->id; }, $discussionidrecords);
}

function local_forumexport_getuseridsfromgroupids($groupids) {
    global $DB;
    if (!$groupids || empty($groupids)) {
        return [];
    }

    list($ingroupsql, $params) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);

    $groupmemberuserids = $DB->get_records_sql('SELECT userid FROM {groups_members} WHERE groupid ' . $ingroupsql, $params);

    return array_map(function($record) { return $record->userid; }, $groupmemberuserids);
}
