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
 * Contains class format_f360_managers_form
 *
 * @package   format_f360
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

/**
 * Form for editing managers
 *
 * @package   format_f360
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_f360_managers_form extends moodleform {
    public function definition() {
        $course = $this->_customdata['course'];
        $f360info = $this->_customdata['info'];
        $context = context_course::instance($course->id);
        $mform = $this->_form;
        if (!has_capability('moodle/course:managegroups', $context)) {
            $mform->addElement('static', 'something', '', 'You dont have permission'); // TODO string;
            return;
        }

        // TODO groups queries must be in definition_after_data() or some other way so we don't call it every time
        // in format_f360::page_set_course()
        $groups = $f360info->get_groups();
        $groupsurl = new moodle_url('/group/index.php', ['id' => $course->id]);
        $edithere = html_writer::link($groupsurl, 'click here'); // TODO string
        if (empty($groups)) {
            $mform->addElement('static', 'something', '', 'No groups setup yet, to edit '.$edithere); // TODO string;
            return;
        }
        $mform->addElement('static', 'something', '', 'To modify groups/teams '.$edithere); // TODO string;

        $mform->addElement('hidden', 'id', $course->id);
        $mform->setType('id', PARAM_INT);

        foreach ($groups as $group) {
            $members = $f360info->get_group_members($group);
            $options = array();
            foreach ($members as $user) {
                $options[$user->id] = fullname($user);
            }
            if ($options) {
                $mform->addElement('autocomplete', 'group' . $group->id,
                        format_string($group->name, null, ['context' => $context]),
                        $options, ['multiple' => true]);
            } else {
                $mform->addElement('static', 'group' . $group->id,
                        format_string($group->name, null, ['context' => $context]),
                        'No users'); // TODO string.
            }
        }

        $this->add_action_buttons();

        // Set data.
        $managers = $f360info->get_managers();
        $data = [];
        foreach ($managers as $groupid => $users) {
            $data['group' . $groupid] = array_keys($users);
        }
        $this->set_data($data);
    }
}