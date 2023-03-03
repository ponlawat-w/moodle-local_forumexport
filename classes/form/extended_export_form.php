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

namespace local_forumexport\form;

defined('MOODLE_INTERNAL') or die();

require_once(__DIR__ . '/../../lib.php');
require_once($CFG->dirroot . '/mod/forum/classes/form/export_form.php');
require_once(__DIR__ . '/../engagement.php');

/**
 * This class extends \mod_forum\form\export_form by keeping original form elements and adding extended elements needed.
 * 
 * @package local_forumexport
 * @copyright 2022 Ponlawat Weerapanpisit
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extended_export_form extends \mod_forum\form\export_form {

    /**
     * Get array of all groups in the course of current frm.
     *
     * @param bool $all True to get all available groups in the courses, false to get only current user's groups.
     * @return array Associative array with key being group ID and value being group name
     */
    private function get_groups($all) {
        $courseid = $this->_customdata['courseid'];
        if ($all) {
            $groups = groups_get_all_groups($courseid);
            $results = [];
            foreach ($groups as $group) {
                $results[$group->id] = $group->name;
            }
            return $results;
        }

        $grouppings = groups_get_user_groups($courseid);
        
        $results = [];
        foreach ($grouppings[0] as $groupid) {
            $group = groups_get_group($groupid);
            $results[$group->id] = $group->name;
        }
        return $results;
    }

    /**
     * Overridding parent's form definition by adding more necessary elements
     *
     * @return void
     */
    public function definition() {
        parent::definition();

        $context = $this->_customdata['forum']->get_context();

        $mform = $this->_form;

        $canexportdifferentgroup = has_capability('local/forumexport:exportdifferentgroup', $context);
        $groupmodeoptions = $canexportdifferentgroup ? [
            LOCAL_FORUMEXPORT_GROUP_ALL => get_string('groupmode_all', 'local_forumexport'),
            LOCAL_FORUMEXPORT_GROUP_MY => get_string('groupmode_my', 'local_forumexport'),
            LOCAL_FORUMEXPORT_GROUP_CUSTOM => get_string('groupmode_custom', 'local_forumexport')
        ] : [
            LOCAL_FORUMEXPORT_GROUP_MY => get_string('groupmode_my', 'local_forumexport'),
            LOCAL_FORUMEXPORT_GROUP_CUSTOM => get_string('groupmode_custom', 'local_forumexport')
        ];
        $mform->insertElementBefore($mform->createElement('header', 'groupsheader', get_string('groupoptions', 'local_forumexport')), 'optionsheader');
        $mform->insertElementBefore($mform->createElement('select', 'groupmode', get_string('groupmode', 'local_forumexport'), $groupmodeoptions), 'optionsheader');
        $mform->setType('groupmode', PARAM_INT);
        $mform->setDefault('groupmode', $canexportdifferentgroup ? LOCAL_FORUMEXPORT_GROUP_ALL : LOCAL_FORUMEXPORT_GROUP_MY);

        $groups = $this->get_groups($canexportdifferentgroup ? true : false);
        $mform->insertElementBefore($mform->createElement('autocomplete', 'groups', get_string('groups', 'local_forumexport'), $groups, [
            'multiple' => true
        ]), 'optionsheader');
        $mform->setDefault('groups', []);
        $mform->hideIf('groups', 'groupmode', 'neq', LOCAL_FORUMEXPORT_GROUP_CUSTOM);

        $mform->insertElementBefore($mform->createElement('checkbox', 'groupbydiscussiongroup', get_string('groupbydiscussiongroup', 'local_forumexport')), 'optionsheader');
        $mform->setType('groupbydiscussiongroup', PARAM_BOOL);
        $mform->setDefault('groupbydiscussiongroup', true);
        $mform->hideIf('groupbydiscussiongroup', 'groupmode', 'eq', LOCAL_FORUMEXPORT_GROUP_ALL);
        
        $mform->insertElementBefore($mform->createElement('checkbox', 'groupbydiscussionstarter', get_string('groupbydiscussionstarter', 'local_forumexport')), 'optionsheader');
        $mform->setType('groupbydiscussionstarter', PARAM_BOOL);
        $mform->setDefault('groupbydiscussionstarter', true);
        $mform->hideIf('groupbydiscussionstarter', 'groupmode', 'eq', LOCAL_FORUMEXPORT_GROUP_ALL);
        
        $mform->insertElementBefore($mform->createElement('checkbox', 'groupbyparticipants', get_string('groupbyparticipants', 'local_forumexport')), 'optionsheader');
        $mform->setType('groupbyparticipants', PARAM_BOOL);
        $mform->setDefault('groupbyparticipants', true);
        $mform->hideIf('groupbyparticipants', 'groupmode', 'eq', LOCAL_FORUMEXPORT_GROUP_ALL);
        
        if (has_capability('local/forumexport:includeparent', $context)) {
            $mform->insertElementBefore($mform->createElement('checkbox', 'includeparent', get_string('includeparent', 'local_forumexport')), 'optionsheader');
            $mform->setType('includeparent', PARAM_BOOL);
            $mform->setDefault('includeparent', true);
            $mform->hideIf('includeparent', 'groupmode', 'eq', LOCAL_FORUMEXPORT_GROUP_ALL);
        }

        if (has_capability('local/forumexport:includereplies', $context)) {
            $mform->insertElementBefore($mform->createElement('checkbox', 'includeallreplies', get_string('includeallreplies', 'local_forumexport')), 'optionsheader');
            $mform->setType('includeallreplies', PARAM_BOOL);
            $mform->setDefault('includeallreplies', true);
            $mform->hideIf('includeallreplies', 'groupmode', 'eq', LOCAL_FORUMEXPORT_GROUP_ALL);
        }
        
        $mform->insertElementBefore(
            $mform->createElement('select', 'engagementmethod', get_string('engagement_method', 'local_forumexport'), \local_forumexport\engagement::getselectoptions()),
            'groupsheader'
        );
        $mform->addHelpButton('engagementmethod', 'engagement_method', 'local_forumexport');
        $mform->setDefault('engagementmethod', get_config('local_forumexport', 'defaultengagementmethod'));
    }
}
