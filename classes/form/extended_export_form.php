<?php
namespace local_forumexport\form;

defined('MOODLE_INTERNAL') or die();

require_once(__DIR__ . '/../../lib.php');
require_once($CFG->dirroot . '/mod/forum/classes/form/export_form.php');

class extended_export_form extends \mod_forum\form\export_form {
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
    }
}
