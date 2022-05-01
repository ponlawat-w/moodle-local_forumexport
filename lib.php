<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Function libraries
 * 
 * @package local_forumexport
 * @copyright 2022 Ponlawat Weerapanpisit
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

const LOCAL_FORUMEXPORT_GROUP_ALL = 0;
const LOCAL_FORUMEXPORT_GROUP_MY = 1;
const LOCAL_FORUMEXPORT_GROUP_CUSTOM = 2;

/**
 * Extended forum module settings navigation by adding a button to access the plugin
 *
 * @param mixed $settingsnav
 * @param mixed $context
 * @return void
 */
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

/**
 * Check if a user can export data from specified group, otherwise exception to be thrown from require_capability
 *
 * @param context_module $modulecontext
 * @param int $courseid
 * @param int[] $groupids
 * @param int $userid
 * @return void
 */
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

/**
 * Get IDs of the dicussions started by a user from one of any specified groups
 *
 * @param int[] $discussionids
 * @param int[] $groupids
 * @return int[]
 */
function local_forumexport_getdiscussionidsstartedbygroups($discussionids, $groupids) {
    global $DB;

    $groupmemberids = local_forumexport_getuseridsfromgroupids($groupids);

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($inusersql, $userparams) = $DB->get_in_or_equal($groupmemberids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $userparams);

    $discussionidrecords = $DB->get_records_sql('SELECT id FROM {forum_discussions} WHERE id ' . $indiscussionsql . ' AND userid ' . $inusersql, $params);

    return array_values(array_map(function($dicussion) { return $dicussion->id; }, $discussionidrecords));
}

/**
 * Get IDs of the dicussions which belong to one of any specified groups
 *
 * @param int[] $discussionids
 * @param int[] $groupids
 * @return int[]
 */
function local_forumexport_getdiscussionidsingroups($discussionids, $groupids) {
    global $DB;

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($ingroupsql, $groupparams) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $groupparams);

    $discussionidrecords = $DB->get_records_sql('SELECT id FROM {forum_discussions} WHERE id ' . $indiscussionsql . ' AND groupid ' . $ingroupsql, $params);

    return array_values(array_map(function($discussion) { return $discussion->id; }, $discussionidrecords));
}

/**
 * Get IDs of the discussions which are participated by user from one of any specified groups
 *
 * @param int[] $discussionids
 * @param int[] $groupids
 * @return int[]
 */
function local_forumexport_getdiscussionidsparticipatedbygroups($discussionids, $groupids) {
    global $DB;

    $groupmemberids = local_forumexport_getuseridsfromgroupids($groupids);

    list($indiscussionsql, $discussionparams) = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
    list($inusersql, $userparams) = $DB->get_in_or_equal($groupmemberids, SQL_PARAMS_NAMED);

    $params = array_merge($discussionparams, $userparams);

    $postdiscussionrecords = $DB->get_records_sql('SELECT DISTINCT discussion FROM {forum_posts} WHERE discussion ' . $indiscussionsql . ' AND userid ' . $inusersql, $params);

    return array_values(array_map(function($post) { return $post->discussion; }, $postdiscussionrecords));
}

/**
 * Filter discussion IDs array by specified group IDs
 *
 * @param int[] $discussionids
 * @param int[] $groupids
 * @param bool $groupbydiscussiongroup True to include discussions that belong to any of the specified groups
 * @param bool $groupbydiscussionstarter True to include dicussions that are started by a user from any of the specified groups
 * @param bool $groupbyparticipants True to include dicussions that are participated by a user from any of the specified groups
 * @return int[]
 */
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

/**
 * Get IDs of the users who are members of any of the specified group IDs
 *
 * @param int[] $groupids
 * @return int[]
 */
function local_forumexport_getuseridsfromgroupids($groupids) {
    $groupmemberids = [];
    foreach ($groupids as $groupid) {
        $groupmemberids += array_map(function ($user) { return $user->id; }, groups_get_members($groupid));
    }

    return $groupmemberids;
}
