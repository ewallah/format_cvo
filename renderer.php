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
 * Renderer for outputting the cvo course format.
 *
 * @package   format_cvo
 * @copyright 2018 cvo-ssh.be
 * @author    Renaat Debleu (info@eWallah.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/topics/renderer.php');
require_once($CFG->dirroot  . '/mod/forum/lib.php');

class format_cvo_renderer extends format_topics_renderer {

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

    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $USER;

        if ($section->section == 0 && !$onsectionpage) {
            if ($section->visible) {
                $forums = forum_get_readable_forums($USER->id, $course->id);
                foreach ($forums as $forum) {
                    forum_print_latest_discussions($course, $forum, 3);
                    break;
                }
            }
        }
        return parent::section_header($section, $course, $onsectionpage, $sectionreturn);
    }
}
