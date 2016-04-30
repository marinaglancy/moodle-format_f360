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
 * Contains class format_f360_info
 *
 * @package   format_f360
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Main class for format_f360
 *
 * @package   format_f360
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_f360_info {
    /** @var format_f360 */
    protected $courseformat;

    public function __construct(format_f360 $courseformat) {
        $this->courseformat = $courseformat;

        // Make sure all sections are created.
        course_create_sections_if_missing($this->get_course(), range(0, 2));
    }

    /**
     *
     * @param bool $createifmissing
     * @return cm_info
     */
    public function get_main_feedback($createifmissing = false) {
        global $DB, $CFG;
        $course = $this->courseformat->get_course();
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_sections();
        if (isset($sections[1])) {
            foreach ($sections[1] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->modname === 'feedback') {
                    return $cm;
                }
            }
        }
        if ($createifmissing) {
            if (course_allowed_module($course, 'feedback')) {
                require_once($CFG->dirroot.'/course/modlib.php');
                $record = (object)[
                    'anonymous' => 1,
                    'email_notification' => 0,
                    'multiple_submit' => 1,
                    'autonumbering' => 1,
                    'site_after_submit' => '',
                    'page_after_submit' => '', // TODO hardcode 'Thank you'.
                    'publish_stats' => 0,
                    'timeopen' => 0,
                    'timeclose' => 0,
                    'timemodified' => time(),
                    'completionsubmit' => 0, // TODO completion?
                    'idnumber' => '',
                    'course' => $course->id,
                    'module' => $DB->get_field('modules', 'id', array('name' => 'feedback')),
                    'modulename' => 'feedback',
                    'section' => 1,
                    'visible' => 1,
                    'cmidnumber' => '',
                    'groupmode' => 0,
                    'groupingid' => 0,
                    'availability' => null,
                    'completion' => 0,
                    'completionview' => 0,
                    'completionexpected' => 0,
                    'conditiongradegroup' => array(),
                    'conditionfieldgroup' => array(),
                    'conditioncompletiongroup' => array(),
                    'name' => 'Main feedback', // TODO change
                    'intro' => '',
                    'introformat' => FORMAT_MOODLE,

                ];

                // Hack to bypass draft processing of feedback_add_instance.
                $record->page_after_submit_editor['itemid'] = false;

                $moduleinfo = add_moduleinfo($record, $course, $mform = null);

                return get_fast_modinfo($course)->get_cm($moduleinfo->coursemodule);
            }
        }
        return null;
    }

    public function get_course() {
        return $this->courseformat->get_course();
    }

    /**
     *
     * @global moodle_database $DB
     * @param stdClass $data
     */
    public function update_managers($data) {
        global $DB;
        $currentmanagers = $this->get_managers();
        $toinsert = [];
        $todelete = [];
        foreach ($data as $key => $value) {
            if (preg_match('/^group(\d+)$/', $key, $matches)) {
                $groupid = (int)$matches[1];
                foreach ($value as $userid) {
                    if (isset($currentmanagers[$groupid][$userid])) {
                        unset($currentmanagers[$groupid][$userid]);
                    } else {
                        $toinsert[] = ['groupid' => $groupid, 'userid' => $userid];
                    }
                }
            }
        }
        foreach ($currentmanagers as $groupid => $values) {
            $todelete = array_merge($todelete, array_values($values));
        }

        if ($toinsert) {
            $DB->insert_records('format_f360_manager', $toinsert);
        }
        if ($todelete) {
            $DB->delete_records_list('format_f360_manager', 'id', $todelete);
        }
        $this->cachedmanagers = null; // Reset cache.
    }

    protected $cachedmanagers = null;
    public function get_managers() {
        global $DB;
        if ($this->cachedmanagers === null) {
            $this->cachedmanagers = [];
            $records = $DB->get_records('format_f360_manager');
            foreach ($records as $record) {
                $groupid = $record->groupid;
                $this->cachedmanagers += [$groupid => []];
                $this->cachedmanagers[$groupid][$record->userid] = $record->id;
            }
        }
        return $this->cachedmanagers;
    }

    protected $cachedusers = null;
    public function get_users() {
        // TODO only users with capability.
        if ($this->cachedusers === null) {
            $course = $this->courseformat->get_course();
            $context = context_course::instance($course->id);
            $fields = user_picture::fields('u');
            $this->cachedusers = get_enrolled_users($context, '', 0, $fields);
        }
        return $this->cachedusers;
    }

    protected $cachedgroupings = null;
    public function get_groupings() {
        if ($this->cachedgroupings === null) {
            $course = $this->courseformat->get_course();
            $this->cachedgroupings = array_filter(groups_get_all_groupings($course->id),
                    function($grouping) {
                        return !preg_match('/^f360_/', $grouping->idnumber);
                    });
        }
        return $this->cachedgroupings;
    }

    protected $cachedgroups = null;
    public function get_groups() {
        if ($this->cachedgroups === null) {
            $course = $this->courseformat->get_course();
            $this->cachedgroups = array_filter(groups_get_all_groups($course->id),
                    function($group) {
                        return !preg_match('/^f360_/', $group->idnumber);
                    });
        }
        return $this->cachedgroups;
    }

    protected $cachedmembers = [];
    public function get_group_members($group) {
        if (!array_key_exists($group->id, $this->cachedmembers)) {
            // TODO fetch all at once?
            $fields = user_picture::fields('u');
            $this->cachedmembers[$group->id] = array_intersect_key(
                    groups_get_members($group->id, $fields), $this->get_users());
        }
        return $this->cachedmembers[$group->id];
    }

    public function get_user_managers($user) {
        $usermanagers = [];
        $allmanagers = $this->get_managers();
        $groups = $this->get_groups();
        foreach ($groups as $group) {
            $members = $this->get_group_members($group);
            if (array_key_exists($user->id, $members) && !empty($allmanagers[$group->id])) {
                foreach ($allmanagers[$group->id] as $userid => $unused) {
                    $usermanagers[$userid] = $members[$userid];
                }
            }
        }
        return array_diff_key($usermanagers, [$user->id => $user]);
    }

    public function get_user_teammates($user) {
        $userteammates = [];
        $groups = $this->get_groups();
        foreach ($groups as $group) {
            $members = $this->get_group_members($group);
            if (array_key_exists($user->id, $members)) {
                $userteammates += $members;
            }
        }
        return array_diff_key($userteammates, $this->get_user_managers($user), [$user->id => $user]);
    }

    public function delete_generated_circles() {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        // 1. Remove all groups and groupings with idnumbers that start with f360_ .
        $course = $this->courseformat->get_course();
        $groups = groups_get_all_groups($course->id);
        $groupings = groups_get_all_groupings($course->id);
        foreach ($groups as $group) {
            if (preg_match('/^f360_/', $group->idnumber)) {
                groups_delete_group($group);
            }
        }
        foreach ($groupings as $grouping) {
            if (preg_match('/^f360_/', $grouping->idnumber)) {
                groups_delete_grouping($grouping);
            }
        }
    }

    public function generate_circles() {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        $this->delete_generated_circles();

        // 2. Create groupings and groups for each user.
        $course = $this->courseformat->get_course();
        $users = $this->get_users();
        foreach ($users as $user) {
            $fullname = fullname($user);
            $data = (object)['name' => 'Circle of '.$fullname,
                'idnumber' => 'f360_circle_'.$user->id,
                'courseid' => $course->id];
            $groupingid = groups_create_grouping($data);

            $data->idnumber = 'f360_'.$user->id.'_self';
            $data->name = 'Self ('.$fullname.')';
            $groupid1 = groups_create_group($data);
            groups_add_member($groupid1, $user->id);

            $data->idnumber = 'f360_'.$user->id.'_mates';
            $data->name = 'Teammates of '.$fullname;
            $groupid2 = groups_create_group($data);
            foreach ($this->get_user_teammates($user) as $u) {
                groups_add_member($groupid2, $u->id);
            }

            $data->idnumber = 'f360_'.$user->id.'_manager';
            $data->name = 'Manager of '.$fullname;
            $groupid3 = groups_create_group($data);
            foreach ($this->get_user_managers($user) as $u) {
                groups_add_member($groupid3, $u->id);
            }

            groups_assign_grouping($groupingid, $groupid1);
            groups_assign_grouping($groupingid, $groupid2);
            groups_assign_grouping($groupingid, $groupid3);
        }
    }

    /**
     *
     * @return cm_info[] array $userid=>$cm
     */
    public function get_generated_feedbacks() {
        $course = $this->courseformat->get_course();
        $modinfo = get_fast_modinfo($course);
        $cms = [];
        if (isset($modinfo->sections[2])) {
            foreach ($modinfo->sections[2] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->modname === 'feedback' && preg_match('/^f360_feedback_(\d+)$/', $cm->idnumber, $matches)) {
                    $cms[(int)$matches[1]] = $cm;
                }
            }
        }
        return $cms;
    }

    public function delete_generated_feedbacks() {
        foreach ($this->get_generated_feedbacks() as $cm) {
            course_delete_module($cm->id);
        }
    }

    public function generate_feedbacks() {
        // duplicate_module();
        $this->delete_generated_feedbacks();
        $this->generate_circles();

        $course = $this->courseformat->get_course();
        $groupings = [];
        foreach (groups_get_all_groupings($course->id) as $grouping) {
            if (preg_match('/^f360_circle_(\d+)$/', $grouping->idnumber, $matches)) {
                $groupings[(int)$matches[1]] = $grouping;
            }
        }

        $users = $this->get_users();
        $section = get_fast_modinfo($course)->get_section_info(2);
        foreach ($users as $user) {
            $this->create_individual_feedback($user, $groupings[$user->id], $section);
        }
    }

    protected function create_individual_feedback($user, $grouping, $section) {
        global $CFG, $DB;
        $mainfeedback = $this->get_main_feedback();
        if (!$mainfeedback) {
            return;
        }
        $course = $this->courseformat->get_course();
        $cm = duplicate_module($course, $mainfeedback);
        if (!$cm) {
            return;
        }
        set_coursemodule_name($cm->id, fullname($user));

        $data = ['id' => $cm->id, 'groupmode' => VISIBLEGROUPS, 'groupingid' => $grouping->id,
            'idnumber' => 'f360_feedback_' . $user->id];
        $data['availability'] = '{"op":"&","c":[{"type":"grouping","id":'.$grouping->id.'}],"showc":[false]}';
        $DB->update_record('course_modules', $data);
        rebuild_course_cache($cm->course, true);

        moveto_module($cm, $section);
    }

    public function publish_results() {
        global $DB;
        $cms = $this->get_generated_feedbacks();
        $course = $this->courseformat->get_course();
        $groups = groups_get_all_groups($course->id);
        foreach ($cms as $userid => $cm) {
            $groupid = 0;
            foreach ($groups as $group) {
                if ($group->idnumber === 'f360_'.$userid.'_self') {
                    $groupid = $group->id;
                }
            }
            $data = ['id' => $cm->id];
            $data['availability'] = '{"op":"&","c":[{"type":"group","id":'.$groupid.'}],"showc":[false]}';
            $DB->update_record('course_modules', $data);
            $DB->update_record('feedback', ['id' => $cm->instance, 'publish_stats' => 1]);
        }
        rebuild_course_cache($course->id, true);
    }

    public function unpublish_results() {
        global $DB;
        $cms = $this->get_generated_feedbacks();
        $course = $this->courseformat->get_course();
        $groupings = groups_get_all_groupings($course->id);
        foreach ($cms as $userid => $cm) {
            $groupingid = 0;
            foreach ($groupings as $grouping) {
                if ($grouping->idnumber === 'f360_circle_'.$userid) {
                    $groupingid = $grouping->id;
                }
            }
            $data = ['id' => $cm->id];
            $data['availability'] = '{"op":"&","c":[{"type":"grouping","id":'.$groupingid.'}],"showc":[false]}';
            $DB->update_record('course_modules', $data);
            $DB->update_record('feedback', ['id' => $cm->instance, 'publish_stats' => 0]);
        }
        rebuild_course_cache($course->id, true);
    }
}