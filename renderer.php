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

/**
 * Renderer for outputting the cvo course format.
 *
 * @package   format_cvo
 * @copyright 2018 cvo-ssh.be
 * @author    Renaat Debleu (info@eWallah.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $USER;

        if ($section->section == 0 && !$onsectionpage) {
            if ($section->uservisible) {
                $forums = forum_get_readable_forums($USER->id, $course->id);
                foreach ($forums as $forum) {
                    $this->forum_print_latest_discussions($course, $forum, 3);
                    break;
                }
            }
        }
        return parent::section_header($section, $course, $onsectionpage, $sectionreturn);
    }

    /**
     * Prints the discussion view screen for a forum.
     *
     * @param object $course The current course object.
     * @param object $forum Forum to be printed.
     * @param int $maxdiscussions
     * @param string $displayformat The display format to use (optional).
     * @param string $sort Sort arguments for database query (optional).
     * @param int $currentgroup
     * @param int $groupmode Group mode of the forum (optional).
     * @param int $page Page mode, page to display (optional).
     * @param int $perpage The maximum number of discussions per page(optional)
     * @param stdClass $cm
     * @deprecated since Moodle 3.7
     */
    private function forum_print_latest_discussions($course, $forum, $maxdiscussions = -1, $displayformat = 'plain', $sort = '',
                                            $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = null) {
        global $CFG, $USER, $OUTPUT;

        require_once($CFG->dirroot . '/course/lib.php');

        if (!$cm) {
            if (!$cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course)) {
                print_error('invalidcoursemodule');
            }
        }
        $context = context_module::instance($cm->id);

        if (empty($sort)) {
            $sort = forum_get_default_sort_order();
        }

        $olddiscussionlink = false;

        // Sort out some defaults.
        if ($perpage <= 0) {
            $perpage = 0;
            $page    = -1;
        }

        if ($maxdiscussions == 0) {
            // All discussions - backwards compatibility.
            $page    = -1;
            $perpage = 0;
            if ($displayformat == 'plain') {
                $displayformat = 'header';  // Abbreviate display by default.
            }

        } else if ($maxdiscussions > 0) {
            $page    = -1;
            $perpage = $maxdiscussions;
        }

        $fullpost = false;
        if ($displayformat == 'plain') {
            $fullpost = true;
        }

        // Decide if current user is allowed to see ALL the current discussions or not.
        // First check the group stuff.
        if ($currentgroup == -1 or $groupmode == -1) {
            $groupmode    = groups_get_activity_groupmode($cm, $course);
            $currentgroup = groups_get_activity_group($cm);
        }

        // Cache.
        $groups = [];

        // If the user can post discussions, then this is a good place to put the
        // button for it. We do not show the button if we are showing site news
        // and the current user is a guest.

        $canstart = forum_user_can_post_discussion($forum, $currentgroup, $groupmode, $cm, $context);
        if (!$canstart and $forum->type !== 'news') {
            if (isguestuser() or !isloggedin()) {
                $canstart = true;
            }
            if (!is_enrolled($context) and !is_viewing($context)) {
                // Allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
                // normal users with temporary guest access see this button too, they are asked to enrol instead
                // do not show the button to users with suspended enrolments here.
                $canstart = enrol_selfenrol_available($course->id);
            }
        }

        if ($canstart) {
            switch ($forum->type) {
                case 'news':
                case 'blog':
                    $buttonadd = get_string('addanewtopic', 'forum');
                    break;
                case 'qanda':
                    $buttonadd = get_string('addanewquestion', 'forum');
                    break;
                default:
                    $buttonadd = get_string('addanewdiscussion', 'forum');
                    break;
            }
            $button = new single_button(new moodle_url('/mod/forum/post.php', ['forum' => $forum->id]), $buttonadd, 'get');
            $button->class = 'singlebutton forumaddnew';
            $button->formid = 'newdiscussionform';
            echo $OUTPUT->render($button);

        } else if (isguestuser() or !isloggedin() or $forum->type == 'news' or
            $forum->type == 'qanda' and !has_capability('mod/forum:addquestion', $context) or
            $forum->type != 'qanda' and !has_capability('mod/forum:startdiscussion', $context)) {
            // No button and no info.
            $ignore = true;
        } else if ($groupmode and !has_capability('moodle/site:accessallgroups', $context)) {
            // Inform users why they can not post new discussion.
            if (!$currentgroup) {
                if (!has_capability('mod/forum:canposttomygroups', $context)) {
                    echo $OUTPUT->notification(get_string('cannotadddiscussiongroup', 'forum'));
                } else {
                    echo $OUTPUT->notification(get_string('cannotadddiscussionall', 'forum'));
                }
            } else if (!groups_is_member($currentgroup)) {
                echo $OUTPUT->notification(get_string('cannotadddiscussion', 'forum'));
            }
        }

        // Get all the recent discussions we're allowed to see.

        $getuserlastmodified = ($displayformat == 'header');

        $discussions = forum_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage);
        if (!$discussions) {
            echo '<div class="forumnodiscuss">';
            if ($forum->type == 'news') {
                echo '('.get_string('nonews', 'forum').')';
            } else if ($forum->type == 'qanda') {
                echo '('.get_string('noquestions', 'forum').')';
            } else {
                echo '('.get_string('nodiscussions', 'forum').')';
            }
            echo "</div>\n";
            return;
        }

        // If we want paging.
        if ($page != -1) {
            // Get the number of discussions found.
            $numdiscussions = forum_get_discussions_count($cm);

            // Show the paging bar.
            echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forum->id");
            if ($numdiscussions > 1000) {
                // Saves some memory on sites with very large forums.
                $replies = forum_count_discussion_replies($forum->id, $sort, $maxdiscussions,
                    $page, $perpage, false);
            } else {
                $replies = forum_count_discussion_replies($forum->id, "", -1, -1, 0, false);
            }

        } else {
            $replies = forum_count_discussion_replies($forum->id, "", -1, -1, 0, false);

            if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
                $olddiscussionlink = true;
            }
        }

        $canviewparticipants = course_can_view_participants($context);
        $canviewhiddentimedposts = has_capability('mod/forum:viewhiddentimedposts', $context);

        $strdatestring = get_string('strftimerecentfull');

        // Check if the forum is tracked.
        if ($cantrack = forum_tp_can_track_forums($forum)) {
            $forumtracked = forum_tp_is_tracked($forum);
        } else {
            $forumtracked = false;
        }

        if ($forumtracked) {
            $unreads = forum_get_discussions_unread($cm);
        } else {
            $unreads = [];
        }

        if ($displayformat == 'header') {
            echo '<table cellspacing="0" class="forumheaderlist">';
            echo '<thead class="text-left">';
            echo '<tr>';
            echo '<th class="header topic" scope="col">'.get_string('discussion', 'forum').'</th>';
            echo '<th class="header author" scope="col">'.get_string('startedby', 'forum').'</th>';
            if ($groupmode > 0) {
                echo '<th class="header group" scope="col">'.get_string('group').'</th>';
            }
            if (has_capability('mod/forum:viewdiscussion', $context)) {
                echo '<th class="header replies" scope="col">'.get_string('replies', 'forum').'</th>';
                // If the forum can be tracked, display the unread column.
                if ($cantrack) {
                    echo '<th class="header replies" scope="col">'.get_string('unread', 'forum');
                    if ($forumtracked) {
                        echo '<a title="'.get_string('markallread', 'forum').
                             '" href="'.$CFG->wwwroot.'/mod/forum/markposts.php?f='.
                             $forum->id.'&amp;mark=read&amp;return=/mod/forum/view.php&amp;sesskey=' . sesskey() . '">'.
                             $OUTPUT->pix_icon('t/markasread', get_string('markallread', 'forum')) . '</a>';
                    }
                    echo '</th>';
                }
            }
            echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'forum').'</th>';
            if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/forum:viewdiscussion', $context)) {
                if (\mod_forum\subscriptions::is_subscribable($forum)) {
                    echo '<th class="header discussionsubscription" scope="col">';
                    echo forum_get_discussion_subscription_icon_preloaders();
                    echo '</th>';
                }
            }
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
        }

        foreach ($discussions as $discussion) {
            if ($forum->type == 'qanda' && !has_capability('mod/forum:viewqandawithoutposting', $context) &&
                !forum_user_has_posted($forum->id, $discussion->discussion, $USER->id)) {
                $canviewparticipants = false;
            }

            if (!empty($replies[$discussion->discussion])) {
                $discussion->replies = $replies[$discussion->discussion]->replies;
                $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
            } else {
                $discussion->replies = 0;
            }

            // SPECIAL CASE: The front page can display a news item post to non-logged in users.
            // All posts are read in this case.
            if (!$forumtracked) {
                $discussion->unread = '-';
            } else if (empty($USER)) {
                $discussion->unread = 0;
            } else {
                if (empty($unreads[$discussion->discussion])) {
                    $discussion->unread = 0;
                } else {
                    $discussion->unread = $unreads[$discussion->discussion];
                }
            }

            if (isloggedin()) {
                $ownpost = ($discussion->userid == $USER->id);
            } else {
                $ownpost = false;
            }
            // Use discussion name instead of subject of first post.
            $discussion->subject = $discussion->name;

            switch ($displayformat) {
                case 'header':
                    if ($groupmode > 0) {
                        if (isset($groups[$discussion->groupid])) {
                            $group = $groups[$discussion->groupid];
                        } else {
                            $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                        }
                    } else {
                        $group = -1;
                    }
                    forum_print_discussion_header($discussion, $forum, $group, $strdatestring, $cantrack, $forumtracked,
                        $canviewparticipants, $context, $canviewhiddentimedposts);
                break;
                default:
                    $link = false;

                    if ($discussion->replies) {
                        $link = true;
                    } else {
                        $modcontext = context_module::instance($cm->id);
                        $link = forum_user_can_see_discussion($forum, $discussion, $modcontext, $USER);
                    }

                    $discussion->forum = $forum->id;

                    $this->forum_print_post_start($discussion);
                    $this->forum_print_post($discussion, $discussion, $forum, $cm, $course, $ownpost, 0, $link, false,
                            '', null, true, $forumtracked);
                    $this->forum_print_post_end($discussion);
                break;
            }
        }

        if ($displayformat == "header") {
            echo '</tbody>';
            echo '</table>';
        }

        if ($olddiscussionlink) {
            if ($forum->type == 'news') {
                $strolder = get_string('oldertopics', 'forum');
            } else {
                $strolder = get_string('olderdiscussions', 'forum');
            }
            echo '<div class="forumolddiscuss">';
            echo '<a href="'.$CFG->wwwroot.'/mod/forum/view.php?f='.$forum->id.'&amp;showall=1">';
            echo $strolder.'</a> ...</div>';
        }

        if ($page != -1) {
            // Show the paging bar.
            echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forum->id");
        }
    }


    /**
     * Start a forum post container
     *
     * @param object $post The post to print.
     * @param bool $return Return the string or print it
     * @return string
     * @deprecated since Moodle 3.7
     */
    private function forum_print_post_start($post, $return = false) {
        $output = '';

        if ($this->forum_should_start_post_nesting($post->id)) {
            $attributes = [
                'id' => 'p'.$post->id,
                'tabindex' => -1,
                'class' => 'relativelink'
            ];
            $output .= html_writer::start_tag('article', $attributes);
        }
        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }


    /**
     * Return true for the first time this post was started
     *
     * @param int $id The id of the post to start
     * @return bool
     * @deprecated since Moodle 3.7
     */
    private function forum_should_start_post_nesting($id) {
        $cache = $this->forum_post_nesting_cache();
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = 1;
            return true;
        } else {
            $cache[$id]++;
            return false;
        }
    }

    /**
     * End a forum post container
     *
     * @param object $post The post to print.
     * @param bool $return Return the string or print it
     * @return string
     * @deprecated since Moodle 3.7
     */
    private function forum_print_post_end($post, $return = false) {
        $output = '';

        if ($this->forum_should_end_post_nesting($post->id)) {
            $output .= html_writer::end_tag('article');
        }
        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    /**
     * Return true when all the opens are nested with a close.
     *
     * @param int $id The id of the post to end
     * @return bool
     * @deprecated since Moodle 3.7
     */
    private function forum_should_end_post_nesting($id) {
        $cache = $this->forum_post_nesting_cache();
        if (!array_key_exists($id, $cache)) {
            return true;
        } else {
            $cache[$id]--;
            if ($cache[$id] == 0) {
                unset($cache[$id]);
                return true;
            }
        }
        return false;
    }

    /**
     * Return a static array of posts that are open.
     *
     * @return array
     * @deprecated since Moodle 3.7
     */
    private function forum_post_nesting_cache() {
        static $nesting = [];
        return $nesting;
    }

    /**
     * Print a forum post
     * This function should always be surrounded with calls to forum_print_post_start
     * and forum_print_post_end to create the surrounding container for the post.
     * Replies can be nested before forum_print_post_end and should reflect the structure of
     * thread.
     *
     * @param object $post The post to print.
     * @param object $discussion
     * @param object $forum
     * @param object $cm
     * @param object $course
     * @param boolean $ownpost Whether this post belongs to the current user.
     * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
     * @param boolean $link Just print a shortened version of the post as a link to the full post.
     * @param string $footer Extra stuff to print after the message.
     * @param string $highlight Space-separated list of terms to highlight.
     * @param int $postisread true, false or -99. If we already know whether this user
     *          has read this post, pass that in, otherwise, pass in -99, and this
     *          function will work it out.
     * @param boolean $dummyifcantsee When forum_user_can_see_post says that
     *          the current user can't see this post, if this argument is true
     *          (the default) then print a dummy 'you can't see this post' post.
     *          If false, don't output anything at all.
     * @param boolean $istracked
     * @param boolean $return
     * @return boolean void
     * @deprecated since Moodle 3.7
     */
    private function forum_print_post($post, $discussion, $forum, &$cm, $course, $ownpost=false, $reply=false, $link=false,
                              $footer="", $highlight="", $postisread=null, $dummyifcantsee=true, $istracked=null, $return=false) {
        global $USER, $CFG, $OUTPUT;

        require_once($CFG->libdir . '/filelib.php');

        // String cache.
        static $str;
        // This is an extremely hacky way to ensure we only print the 'unread' anchor
        // the first time we encounter an unread post on a page. Ideally this would
        // be moved into the caller somehow, and be better testable. But at the time
        // of dealing with this bug, this static workaround was the most surgical and
        // it fits together with only printing th unread anchor id once on a given page.
        static $firstunreadanchorprinted = false;

        $modcontext = context_module::instance($cm->id);

        $post->course = $course->id;
        $post->forum  = $forum->id;
        $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id,
           'mod_forum', 'post', $post->id);
        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir.'/plagiarismlib.php');
            $post->message .= plagiarism_get_links(['userid' => $post->userid,
                'content' => $post->message,
                'cmid' => $cm->id,
                'course' => $post->course,
                'forum' => $post->forum]);
        }

        // Caching.
        if (!isset($cm->cache)) {
            $cm->cache = new stdClass;
        }

        if (!isset($cm->cache->caps)) {
            $cm->cache->caps = [];
            $cm->cache->caps['mod/forum:viewdiscussion']   = has_capability('mod/forum:viewdiscussion', $modcontext);
            $cm->cache->caps['moodle/site:viewfullnames']  = has_capability('moodle/site:viewfullnames', $modcontext);
            $cm->cache->caps['mod/forum:editanypost']      = has_capability('mod/forum:editanypost', $modcontext);
            $cm->cache->caps['mod/forum:splitdiscussions'] = has_capability('mod/forum:splitdiscussions', $modcontext);
            $cm->cache->caps['mod/forum:deleteownpost']    = has_capability('mod/forum:deleteownpost', $modcontext);
            $cm->cache->caps['mod/forum:deleteanypost']    = has_capability('mod/forum:deleteanypost', $modcontext);
            $cm->cache->caps['mod/forum:viewanyrating']    = has_capability('mod/forum:viewanyrating', $modcontext);
            $cm->cache->caps['mod/forum:exportpost']       = has_capability('mod/forum:exportpost', $modcontext);
            $cm->cache->caps['mod/forum:exportownpost']    = has_capability('mod/forum:exportownpost', $modcontext);
        }

        if (!isset($cm->uservisible)) {
            $cm->uservisible = \core_availability\info_module::is_user_visible($cm, 0, false);
        }

        if ($istracked && is_null($postisread)) {
            $postisread = forum_tp_is_post_read($USER->id, $post);
        }

        if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm, false)) {
            // Do _not_ check the deleted flag - we need to display a different UI.
            $output = '';
            if (!$dummyifcantsee) {
                if ($return) {
                    return $output;
                }
                echo $output;
                return;
            }

            $output .= html_writer::start_tag('div', ['class' => 'forumpost clearfix',
                'aria-label' => get_string('hiddenforumpost', 'forum')]);
            $output .= html_writer::start_tag('header', ['class' => 'row header']);
            $output .= html_writer::tag('div', '', ['class' => 'left picture', 'role' => 'presentation']); // Picture.
            if ($post->parent) {
                $output .= html_writer::start_tag('div', ['class' => 'topic']);
            } else {
                $output .= html_writer::start_tag('div', ['class' => 'topic starter']);
            }
            $output .= html_writer::tag('div', get_string('forumsubjecthidden', 'forum'),
               ['class' => 'subject', 'role' => 'header', 'id' => ('headp' . $post->id)]); // Subject.
            $authorclasses = ['class' => 'author'];
            $output .= html_writer::tag('address', get_string('forumauthorhidden', 'forum'), $authorclasses); // Author.
            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('header'); // Header.
            $output .= html_writer::start_tag('div', ['class' => 'row']);
            $output .= html_writer::tag('div', '&nbsp;', ['class' => 'left side']); // Groups.
            $output .= html_writer::tag('div', get_string('forumbodyhidden', 'forum'), ['class' => 'content']); // Content.
            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('div');

            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }

        if (!empty($post->deleted)) {
            // Note: Posts marked as deleted are still returned by the above forum_user_can_post because it is required for
            // nesting of posts.
            $output = '';
            if (!$dummyifcantsee) {
                if ($return) {
                    return $output;
                }
                echo $output;
                return;
            }
            $output .= html_writer::start_tag('div', [
                    'class' => 'forumpost clearfix',
                    'aria-label' => get_string('forumbodydeleted', 'forum'),
                ]);

            $output .= html_writer::start_tag('header', ['class' => 'row header']);
            $output .= html_writer::tag('div', '', ['class' => 'left picture', 'role' => 'presentation']);

            $classes = ['topic'];
            if (!empty($post->parent)) {
                $classes[] = 'starter';
            }
            $output .= html_writer::start_tag('div', ['class' => implode(' ', $classes)]);

            // Subject.
            $output .= html_writer::tag('div', get_string('forumsubjectdeleted', 'forum'), [
                    'class' => 'subject',
                    'role' => 'header',
                    'id' => ('headp' . $post->id)
                ]);

            // Author.
            $output .= html_writer::tag('address', '', ['class' => 'author']);

            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('header'); // End header.
            $output .= html_writer::start_tag('div', ['class' => 'row']);
            $output .= html_writer::tag('div', '&nbsp;', ['class' => 'left side']); // Groups.
            $output .= html_writer::tag('div', get_string('forumbodydeleted', 'forum'), ['class' => 'content']); // Content.
            $output .= html_writer::end_tag('div'); // End row.
            $output .= html_writer::end_tag('div'); // End forumpost.

            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }

        if (empty($str)) {
            $str = new stdClass;
            $str->edit         = get_string('edit', 'forum');
            $str->delete       = get_string('delete', 'forum');
            $str->reply        = get_string('reply', 'forum');
            $str->parent       = get_string('parent', 'forum');
            $str->pruneheading = get_string('pruneheading', 'forum');
            $str->prune        = get_string('prune', 'forum');
            $str->displaymode     = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);
            $str->markread     = get_string('markread', 'forum');
            $str->markunread   = get_string('markunread', 'forum');
        }

        $discussionlink = new moodle_url('/mod/forum/discuss.php', ['d' => $post->discussion]);

        // Build an object that represents the posting user.
        $postuser = new stdClass;
        $postuserfields = explode(',', user_picture::fields());
        $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
        $postuser->id = $post->userid;
        $postuser->fullname    = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
        $postuser->profilelink = new moodle_url('/user/view.php', ['id' => $post->userid, 'course' => $course->id]);

        // Prepare the groups the posting user belongs to.
        if (isset($cm->cache->usersgroups)) {
            $groups = [];
            if (isset($cm->cache->usersgroups[$post->userid])) {
                foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                    $groups[$gid] = $cm->cache->groups[$gid];
                }
            }
        } else {
            $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
        }

        // Prepare the attachements for the post, files then images.
        list($attachments, $attachedimages) = forum_print_attachments($post, $cm, 'separateimages');

        // Determine if we need to shorten this post.
        $shortenpost = ($link && (strlen(strip_tags($post->message)) > $CFG->forum_longpost));

        // Prepare an array of commands.
        $commands = [];

        // Add a permalink.
        $permalink = new moodle_url($discussionlink);
        $permalink->set_anchor('p' . $post->id);
        $commands[] = ['url' => $permalink, 'text' => get_string('permalink', 'forum'), 'attributes' => ['rel' => 'bookmark']];

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // Don't display the mark read / unread controls in this case.
        if ($istracked && $CFG->forum_usermarksread && isloggedin()) {
            $url = new moodle_url($discussionlink, ['postid' => $post->id, 'mark' => 'unread']);
            $text = $str->markunread;
            if (!$postisread) {
                $url->param('mark', 'read');
                $text = $str->markread;
            }
            if ($str->displaymode == FORUM_MODE_THREADED) {
                $url->param('parent', $post->parent);
            } else {
                $url->set_anchor('p'.$post->id);
            }
            $commands[] = ['url' => $url, 'text' => $text, 'attributes' => ['rel' => 'bookmark']];
        }

        // Zoom in to the parent specifically.
        if ($post->parent) {
            $url = new moodle_url($discussionlink);
            if ($str->displaymode == FORUM_MODE_THREADED) {
                $url->param('parent', $post->parent);
            } else {
                $url->set_anchor('p'.$post->parent);
            }
            $commands[] = ['url' => $url, 'text' => $str->parent, 'attributes' => ['rel' => 'bookmark']];
        }

        // Hack for allow to edit news posts those are not displayed yet until they are displayed.
        $age = time() - $post->created;
        if (!$post->parent && $forum->type == 'news' && $discussion->timestart > time()) {
            $age = 0;
        }

        if ($forum->type == 'single' and $discussion->firstpost == $post->id) {
            if (has_capability('moodle/course:manageactivities', $modcontext)) {
                // The first post in single simple is the forum description.
                $commands[] = ['url' => new moodle_url('/course/modedit.php',
                   ['update' => $cm->id, 'sesskey' => sesskey(), 'return' => 1]), 'text' => $str->edit];
            }
        } else if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/forum:editanypost']) {
            $commands[] = ['url' => new moodle_url('/mod/forum/post.php', ['edit' => $post->id]), 'text' => $str->edit];
        }

        if ($cm->cache->caps['mod/forum:splitdiscussions'] && $post->parent && $forum->type != 'single') {
            $commands[] = ['url' => new moodle_url('/mod/forum/post.php', ['prune' => $post->id]),
               'text' => $str->prune, 'title' => $str->pruneheading];
        }

        if ($forum->type == 'single' and $discussion->firstpost == $post->id) {
            // Do not allow deleting of first post in single simple type.
            $tmp = 1;
        } else if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/forum:deleteownpost']) ||
            $cm->cache->caps['mod/forum:deleteanypost']) {
            $commands[] = ['url' => new moodle_url('/mod/forum/post.php', ['delete' => $post->id]), 'text' => $str->delete];
        }

        if ($reply) {
            $commands[] = ['url' => new moodle_url('/mod/forum/post.php#mformforum', ['reply' => $post->id]),
                'text' => $str->reply];
        }

        if ($CFG->enableportfolios && ($cm->cache->caps['mod/forum:exportpost'] ||
            ($ownpost && $cm->cache->caps['mod/forum:exportownpost']))) {
            $p = ['postid' => $post->id];
            require_once($CFG->libdir.'/portfoliolib.php');
            $button = new portfolio_add_button();
            $button->set_callback_options('forum_portfolio_caller', ['postid' => $post->id], 'mod_forum');
            if (empty($attachments)) {
                $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
            } else {
                $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
            }

            $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
            if (!empty($porfoliohtml)) {
                $commands[] = $porfoliohtml;
            }
        }
        // Finished building commands.

        // Begin output.

        $output  = '';

        if ($istracked) {
            if ($postisread) {
                $forumpostclass = ' read';
            } else {
                $forumpostclass = ' unread';
                // If this is the first unread post printed then give it an anchor and id of unread.
                if (!$firstunreadanchorprinted) {
                    $output .= html_writer::tag('a', '', ['id' => 'unread']);
                    $firstunreadanchorprinted = true;
                }
            }
        } else {
            // Ignore trackign status if not tracked or tracked param missing.
            $forumpostclass = '';
        }

        $topicclass = '';
        if (empty($post->parent)) {
            $topicclass = ' firstpost starter';
        }

        if (!empty($post->lastpost)) {
            $forumpostclass .= ' lastpost';
        }

        // Flag to indicate whether we should hide the author or not.
        $authorhidden = forum_is_author_hidden($post, $forum);
        $postbyuser = new stdClass;
        $postbyuser->post = $post->subject;
        $postbyuser->user = $postuser->fullname;
        $discussionbyuser = get_string('postbyuser', 'forum', $postbyuser);
        // Begin forum post.
        $output .= html_writer::start_div('forumpost clearfix' . $forumpostclass . $topicclass,
            ['aria-label' => $discussionbyuser]);
        // Begin header row.
        $output .= html_writer::start_tag('header', ['class' => 'row header clearfix']);

        // User picture.
        if (!$authorhidden) {
            $picture = $OUTPUT->user_picture($postuser, ['courseid' => $course->id]);
            $output .= html_writer::div($picture, 'left picture', ['role' => 'presentation']);
            $topicclass = 'topic' . $topicclass;
        }

        // Begin topic column.
        $output .= html_writer::start_div($topicclass);
        $postsubject = $post->subject;
        if (empty($post->subjectnoformat)) {
            $postsubject = format_string($postsubject);
        }
        $output .= html_writer::div($postsubject, 'subject',
           ['role' => 'heading', 'aria-level' => '1', 'id' => ('headp' . $post->id)]);

        if ($authorhidden) {
            $bytext = userdate($post->created);
        } else {
            $by = new stdClass();
            $by->date = userdate($post->created);
            $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
            $bytext = get_string('bynameondate', 'forum', $by);
        }
        $bytextoptions = [
            'class' => 'author'
        ];
        $output .= html_writer::tag('address', $bytext, $bytextoptions);
        // End topic column.
        $output .= html_writer::end_div();

        // End header row.
        $output .= html_writer::end_tag('header');

        // Row with the forum post content.
        $output .= html_writer::start_div('row maincontent clearfix');
        // Show if author is not hidden or we have groups.
        if (!$authorhidden || $groups) {
            $output .= html_writer::start_div('left');
            $groupoutput = '';
            if ($groups) {
                $groupoutput = print_group_picture($groups, $course->id, false, true, true);
            }
            if (empty($groupoutput)) {
                $groupoutput = '&nbsp;';
            }
            $output .= html_writer::div($groupoutput, 'grouppictures');
            $output .= html_writer::end_div(); // Left side.
        }

        $output .= html_writer::start_tag('div', ['class' => 'no-overflow']);
        $output .= html_writer::start_tag('div', ['class' => 'content']);

        $options = new stdClass;
        $options->para    = false;
        $options->trusted = $post->messagetrust;
        $options->context = $modcontext;
        if ($shortenpost) {
            // Prepare shortened version by filtering the text then shortening it.
            $postclass    = 'shortenedpost';
            $postcontent  = format_text($post->message, $post->messageformat, $options);
            $postcontent  = shorten_text($postcontent, $CFG->forum_shortpost);
            $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'forum'));
            $postcontent .= html_writer::tag('div', '('.get_string('numwords', 'moodle', count_words($post->message)).')',
                ['class' => 'post-word-count']);
        } else {
            // Prepare whole post.
            $postclass    = 'fullpost';
            $postcontent  = format_text($post->message, $post->messageformat, $options, $course->id);
            if (!empty($highlight)) {
                $postcontent = highlight($highlight, $postcontent);
            }
            if (!empty($forum->displaywordcount)) {
                $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($postcontent)),
                    ['class' => 'post-word-count']);
            }
            $postcontent .= html_writer::tag('div', $attachedimages, ['class' => 'attachedimages']);
        }

        if (\core_tag_tag::is_enabled('mod_forum', 'forum_posts')) {
            $postcontent .= $OUTPUT->tag_list(core_tag_tag::get_item_tags('mod_forum', 'forum_posts', $post->id),
                null, 'forum-tags');
        }

        // Output the post content.
        $output .= html_writer::tag('div', $postcontent, ['class' => 'posting '.$postclass]);
        $output .= html_writer::end_tag('div'); // Content.
        $output .= html_writer::end_tag('div'); // Content mask.
        $output .= html_writer::end_tag('div'); // Row.

        $output .= html_writer::start_tag('nav', ['class' => 'row side']);
        $output .= html_writer::tag('div', '&nbsp;', ['class' => 'left']);
        $output .= html_writer::start_tag('div', ['class' => 'options clearfix']);

        if (!empty($attachments)) {
            $output .= html_writer::tag('div', $attachments, ['class' => 'attachments']);
        }

        // Output ratings.
        if (!empty($post->rating)) {
            $output .= html_writer::tag('div', $OUTPUT->render($post->rating), ['class' => 'forum-post-rating']);
        }

        // Output the commands.
        $commandhtml = [];
        foreach ($commands as $command) {
            if (is_array($command)) {
                $attributes = ['class' => 'nav-item nav-link'];
                if (isset($command['attributes'])) {
                    $attributes = array_merge($attributes, $command['attributes']);
                }
                $commandhtml[] = html_writer::link($command['url'], $command['text'], $attributes);
            } else {
                $commandhtml[] = $command;
            }
        }
        $output .= html_writer::tag('div', implode(' ', $commandhtml), ['class' => 'commands nav']);

        // Output link to post if required.
        if ($link) {
            if (forum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext)) {
                $langstring = 'discussthistopic';
            } else {
                $langstring = 'viewthediscussion';
            }
            if ($post->replies == 1) {
                $replystring = get_string('repliesone', 'forum', $post->replies);
            } else {
                $replystring = get_string('repliesmany', 'forum', $post->replies);
            }
            if (!empty($discussion->unread) && $discussion->unread !== '-') {
                $replystring .= ' <span class="sep">/</span> <span class="unread">';
                $unreadlink = new moodle_url($discussionlink, null, 'unread');
                if ($discussion->unread == 1) {
                    $replystring .= html_writer::link($unreadlink, get_string('unreadpostsone', 'forum'));
                } else {
                    $replystring .= html_writer::link($unreadlink, get_string('unreadpostsnumber', 'forum', $discussion->unread));
                }
                $replystring .= '</span>';
            }

            $output .= html_writer::start_tag('div', ['class' => 'link']);
            $output .= html_writer::link($discussionlink, get_string($langstring, 'forum'));
            $output .= '&nbsp;('.$replystring.')';
            $output .= html_writer::end_tag('div');
        }

        // Output footer if required.
        if ($footer) {
            $output .= html_writer::tag('div', $footer, ['class' => 'footer']);
        }

        // Close remaining open divs.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('nav');
        $output .= html_writer::end_tag('div');

        // Mark the forum post as read if required.
        if ($istracked && !$CFG->forum_usermarksread && !$postisread) {
            forum_tp_mark_post_read($USER->id, $post);
        }

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }
}
