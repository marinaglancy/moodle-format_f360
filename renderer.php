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
 * Renderer for outputting the topics course format.
 *
 * @package    format_f360
 * @copyright  2016 Marina Glancy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Basic renderer for f360 format.
 *
 * @package    format_f360
 * @copyright  2016 Marina Glancy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_f360_renderer extends format_section_renderer_base {

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'topics'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Generate the section title to be displayed on the section page, without a link
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }

    public function display(format_f360_info $f360info) {

        $modnames = get_module_types_names();
        if (!array_key_exists('feedback', $modnames)) {
            echo $this->output->notification(get_string('modfeedbacknotenabled', 'format_f360'), 'notifyerror');
            return;
        }

        $course = $f360info->get_course();
        $context = context_course::instance($course->id);
        $cansetup = has_capability('moodle/course:manageactivities', $context);

        $mainfeedback = $f360info->get_main_feedback(true);
        if (!$mainfeedback) {
            echo $this->output->notification(get_string('notsetup', 'format_f360'), 'notifyerror');
            return;
        }

        if ($this->page->user_is_editing() && $cansetup) {
            $this->display_setup($f360info);
        } else {
            if ($cansetup) {
                \core\notification::info('Turn editing mode on to setup this Feedback-360'); // TODO string.
            }
            $this->display_view($f360info);
        }

    }

    protected function display_setup(format_f360_info $f360info) {
        $course = $f360info->get_course();
        $context = context_course::instance($course->id);
        $modinfo = get_fast_modinfo($course);

        echo $this->output->heading('Step 1: Setup the feedback prototype', 4); // TODO string
        $section1 = $modinfo->get_section_info(1);
        echo $this->courserenderer->course_section_cm_list($course, $section1, 0);

        echo $this->output->heading('Step 2: Setup teams and managers', 4); // TODO string

        $form = new format_f360_managers_form(null, ['course' => $course, 'info' => $f360info]);
        $form->display();

        //echo $this->output->heading('Step 3: Adjust feedbacks manually', 4); // TODO string

        echo $this->output->heading('Step 3: Generate individual feedback questionnaires', 4); // TODO string

        echo html_writer::div('When the feedback prototype is ready and teams and managers are set up instructor '
                . 'can generate feedbacks for each individual user. The grouping "Circle of Username" will be created '
                . 'for each user with three groups - user himself, his manager and his team mates. Before opening '
                . 'feedback to public instructor can manually adjust members of these groups');

        $url = new moodle_url(course_get_url($course), ['sesskey' => sesskey(), 'action' => 'generate']);
        echo $this->output->single_button($url, 'Generate'); // TODO string

        $url = new moodle_url(course_get_url($course), ['sesskey' => sesskey(), 'action' => 'removegenerated']);
        echo html_writer::div(html_writer::link($url, 'Remove all generated data'));
        //echo $this->output->single_button($url, 'Remove all generated data'); // TODO string

        if (!empty($modinfo->sections[2])) {
            echo $this->output->heading('Individual feedbacks', 4); // TODO string
            $section2 = $modinfo->get_section_info(2);
            echo $this->courserenderer->course_section_cm_list($course, $section2, 0);
        }

        echo $this->output->heading('Step 4: Publish results', 4); // TODO string

        echo html_writer::div('To publish results all individual feedbacks will be changed to be available to the '
                . 'user only and also each feedback will be changed to set "Show analysis page" to Yes. '
                . 'Teacher need to make sure that all users have capability "mod/feedback:viewanalysepage". '
                . 'Users will be able to view "Analysis" page of their own feedbacks but only if they answered '
                . 'quesitons about themselves.');

        $url = new moodle_url(course_get_url($course), ['sesskey' => sesskey(), 'action' => 'publish']);
        echo $this->output->single_button($url, 'Publish results'); // TODO string

        $url = new moodle_url(course_get_url($course), ['sesskey' => sesskey(), 'action' => 'unpublish']);
        echo html_writer::div(html_writer::link($url, 'Revert publishing results'));
        //echo $this->output->single_button($url, 'Revert publishing results'); // TODO string

    }

    protected function display_view(format_f360_info $f360info) {
        $course = $f360info->get_course();
        $section0 = get_fast_modinfo($course)->get_section_info(0);
        echo $this->courserenderer->course_section_cm_list($course, $section0, 0);
        $section2 = get_fast_modinfo($course)->get_section_info(2);
        echo $this->courserenderer->course_section_cm_list($course, $section2, 0);

    }
}
