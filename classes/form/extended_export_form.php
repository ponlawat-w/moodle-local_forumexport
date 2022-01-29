<?php
namespace local_forumexport\form;

defined('MOODLE_INTERNAL') or die();

require_once(__DIR__ . '/../../lib.php');
require_once($CFG->dirroot . '/mod/forum/classes/form/export_form.php');

class extended_export_form extends \mod_forum\form\export_form {
    private function get_groups() {
        $groups = groups_get_all_groups($this->_customdata['courseid']);
        $results = [];
        foreach ($groups as $group) {
            $results[$group->id] = $group->name;
        }
        return $results;
    }

    public function definition() {
        parent::definition();

        $mform = $this->_form;

        $mform->insertElementBefore($mform->createElement('header', 'groupsheader', get_string('groupoptions', 'local_forumexport')), 'optionsheader');
        $mform->insertElementBefore($mform->createElement('select', 'groupmode', get_string('groupmode', 'local_forumexport'), [
            LOCAL_FORUMEXPORT_GROUP_ALL => get_string('groupmode_all', 'local_forumexport'),
            LOCAL_FORUMEXPORT_GROUP_MY => get_string('groupmode_my', 'local_forumexport'),
            LOCAL_FORUMEXPORT_GROUP_CUSTOM => get_string('groupmode_custom', 'local_forumexport')
        ]), 'optionsheader');
        $mform->setType('groupmode', PARAM_INT);
        $mform->setDefault('groupmode', LOCAL_FORUMEXPORT_GROUP_ALL);

        $groups = $this->get_groups();
        $mform->insertElementBefore($mform->createElement('autocomplete', 'groups', get_string('groups', 'local_forumexport'), $groups, [
            'multiple' => true
        ]), 'optionsheader');
        $mform->setDefault('groups', []);
        $mform->hideIf('groups', 'groupmode', 'neq', LOCAL_FORUMEXPORT_GROUP_CUSTOM);

        $mform->insertElementBefore($mform->createElement('checkbox', 'includeallreplies', get_string('includeallreplies', 'local_forumexport')), 'optionsheader');
        $mform->setType('includeallreplies', PARAM_BOOL);
        $mform->setDefault('includeallreplies', true);
        $mform->hideIf('includeallreplies', 'groupmode', 'eq', LOCAL_FORUMEXPORT_GROUP_ALL);
    }
}
