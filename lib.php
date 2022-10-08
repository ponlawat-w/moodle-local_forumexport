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

/**
 * Convert posts array to associative array with key being post ID and value being the post
 *
 * @param array $posts
 * @return array
 */
function local_forumexport_getpostsdict($posts) {
    $results = [];
    foreach ($posts as $post) {
        $results[$post->id] = $post;
    }
    return $results;
}

/**
 * Get engagement values from posts array
 *
 * @param array $posts
 * @return array
 */
function local_forumexport_calculateengagements($posts) {
    $postsdict = local_forumexport_getpostsdict($posts);

    $postsdepth = [];
    $discussionsmaxdepth = [];
    $discussionslevels = [];

    foreach ($posts as $post) {
        $depth = 0;
        $currentpost = $post;
        while ($parent = isset($postsdict[$currentpost->parent]) ? $postsdict[$currentpost->parent] : null) {
            $depth++;
            $currentpost = $parent;
        }
        $postsdepth[$post->id] = $depth;

        if (!isset($discussionsmaxdepth[$post->discussion])) {
            $discussionsmaxdepth[$post->discussion] = 0;
        }
        if (!isset($discussionslevels[$post->discussion])) {
            $discussionslevels[$post->discussion] = [0, 0, 0, 0];
        }

        if ($depth > $discussionsmaxdepth[$post->discussion]) {
            $discussionsmaxdepth[$post->discussion] = $depth;
        }
        if ($depth > 0 && $depth < 4) {
            $discussionslevels[$post->discussion][$depth - 1]++;
        } else if ($depth >= 4) {
            $discussionslevels[$post->discussion][3]++;
        }
    }

    $results = [];
    foreach ($posts as $post) {
        $record = new stdClass();
        $record->depth = $postsdepth[$post->id];
        $record->maxdepth = $post->parent ? null : $discussionsmaxdepth[$post->discussion];
        $record->l1 = $post->parent ? null : $discussionslevels[$post->discussion][0];
        $record->l2 = $post->parent ? null : $discussionslevels[$post->discussion][1];
        $record->l3 = $post->parent ? null : $discussionslevels[$post->discussion][2];
        $record->l4up = $post->parent ? null : $discussionslevels[$post->discussion][3];

        $results[$post->id] = $record;
    }

    return $results;
}

/**
 * Count multimedia with methodologies from report_discussion_metrics plugin
 *
 * @param string $text
 * @return object
 */
function local_forumexport_report_discussion_metrics_get_mulutimedia_num($text)
{
    global $CFG, $PAGE;

    if (!is_string($text) or empty($text)) {
        // non string data can not be filtered anyway
        return 0;
    }

    if (stripos($text, '</a>') === false && stripos($text, '</video>') === false && stripos($text, '</audio>') === false && (stripos($text, '<img') === false)) {
        // Performance shortcut - if there are no </a>, </video> or </audio> tags, nothing can match.
        return 0;
    }

    // Looking for tags.
    $matches = preg_split('/(<[^>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    $count = new stdClass;
    $count->num = 0;
    $count->img = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link = 0;
    if (!$matches) {
        return 0;
    } else {
        // Regex to find media extensions in an <a> tag.
        $embedmarkers = core_media_manager::instance()->get_embeddable_markers();
        $re = '~<a\s[^>]*href="([^"]*(?:' .  $embedmarkers . ')[^"]*)"[^>]*>([^>]*)</a>~is';
        $tagname = '';
        foreach ($matches as $idx => $tag) {
            if (preg_match('/<(a|img|video|audio)\s[^>]*/', $tag, $tagmatches)) {
                $tagname = strtolower($tagmatches[1]);
                if ($tagname === "a" && preg_match($re, $tag)) {
                    $count->num++;
                    $count->link++;
                } else {
                    if ($tagname == "img") {
                        $count->img++;
                        $count->num++;
                    } else if ($tagname == "video") {
                        $count->video++;
                        $count->num++;
                    } else if ($tagname == "audio") {
                        $count->audio++;
                        $count->num++;
                    }
                }
            }
        }
    }
    return $count;
}
