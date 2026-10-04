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

namespace local_nomoreai;

use local_nomoreai\local\reports;
use local_nomoreai\local\signals;

/**
 * Tests for the tabbed signal reports.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\reports::class)]
final class reports_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course1;
    /** @var \stdClass */
    private $course2;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass */
    private $forum;
    /** @var \stdClass */
    private $quiz;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableintests', 1, 'local_nomoreai');
        set_config('mode', 'detect', 'local_nomoreai');
        $gen = $this->getDataGenerator();
        $this->course1 = $gen->create_course(['fullname' => 'Course One']);
        $this->course2 = $gen->create_course(['fullname' => 'Course Two']);
        $this->student = $gen->create_user(['firstname' => 'Sam', 'lastname' => 'Student']);
        $gen->enrol_user($this->student->id, $this->course1->id, 'student');
        $gen->enrol_user($this->student->id, $this->course2->id, 'student');
        $this->forum = $gen->create_module('forum', ['course' => $this->course1->id, 'name' => 'Forum One']);
        $this->quiz = $gen->create_module('quiz', ['course' => $this->course2->id, 'name' => 'Quiz Two']);

        $forumctx = \context_module::instance($this->forum->cmid);
        $quizctx = \context_module::instance($this->quiz->cmid);
        signals::record(
            signals::WS_SUBMISSION,
            $forumctx,
            ['function' => 'mod_forum_add_discussion'],
            false,
            $this->student->id
        );
        signals::record(
            signals::WS_SUBMISSION,
            $forumctx,
            ['function' => 'mod_forum_add_discussion'],
            false,
            $this->student->id
        );
        signals::record(signals::AGENT_UA, $quizctx, ['client' => '<b>HeadlessChrome</b>'], false, $this->student->id);
        signals::record(signals::SIGNED_AGENT, null, ['client' => 'https://chatgpt.com'], false, 0);
    }

    public function test_users_tab_links_users_to_their_course_profile(): void {
        $html = (new reports())->render(reports::TAB_USERS);
        $profile1 = (new \moodle_url('/user/view.php', ['id' => $this->student->id, 'course' => $this->course1->id]))->out();
        $profile2 = (new \moodle_url('/user/view.php', ['id' => $this->student->id, 'course' => $this->course2->id]))->out();
        $this->assertStringContainsString('href="' . $profile1 . '"', $html);
        $this->assertStringContainsString('href="' . $profile2 . '"', $html);
        $this->assertStringContainsString('href="' . (new \moodle_url('/course/view.php', ['id' => $this->course1->id]))->out()
            . '"', $html);
        $this->assertStringContainsString('href="' . (new \moodle_url('/mod/forum/view.php', ['id' => $this->forum->cmid]))->out()
            . '"', $html);
        $this->assertStringContainsString('Not logged in', $html);
    }

    public function test_activities_tab_ranks_and_links_activities(): void {
        $html = (new reports())->render(reports::TAB_ACTIVITIES);
        $forum = strpos($html, '>Forum One</a>');
        $quiz = strpos($html, '>Quiz Two</a>');
        $this->assertNotFalse($forum);
        $this->assertNotFalse($quiz);
        // The forum has two signals, the quiz one: most signals first.
        $this->assertLessThan($quiz, $forum);
        $this->assertStringContainsString('href="' . (new \moodle_url('/mod/quiz/view.php', ['id' => $this->quiz->cmid]))->out()
            . '"', $html);
    }

    public function test_types_tab_counts_each_type(): void {
        $html = (new reports())->render(reports::TAB_TYPES);
        $this->assertStringContainsString(get_string('signal_ws_submission', 'local_nomoreai'), $html);
        $this->assertStringContainsString(get_string('signal_ws_submission_help', 'local_nomoreai'), $html);
        $this->assertStringContainsString(get_string('dashboard_weekly', 'local_nomoreai'), $html);
    }

    public function test_agents_tab_escapes_client_and_shows_who_and_where(): void {
        $html = (new reports())->render(reports::TAB_AGENTS);
        $this->assertStringContainsString('&lt;b&gt;HeadlessChrome&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>HeadlessChrome</b>', $html);
        $this->assertStringContainsString('https://chatgpt.com', $html);
        $this->assertStringContainsString('>Quiz Two</a>', $html);
        $profile = (new \moodle_url('/user/view.php', ['id' => $this->student->id, 'course' => $this->course2->id]))->out();
        $this->assertStringContainsString('href="' . $profile . '"', $html);
    }

    public function test_course_scope_excludes_other_courses(): void {
        $reports = new reports($this->course1);
        foreach (reports::tabs() as $tab) {
            $html = $reports->render($tab);
            $this->assertStringNotContainsString('Quiz Two', $html, $tab);
            $this->assertStringNotContainsString('chatgpt', $html, $tab);
        }
        $this->assertStringContainsString('>Forum One</a>', $reports->render(reports::TAB_ACTIVITIES));
        $this->assertStringContainsString(get_string('nosignals', 'local_nomoreai'), $reports->render(reports::TAB_AGENTS));
    }
}
