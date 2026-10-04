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

use local_nomoreai\external\record_pageview;
use local_nomoreai\local\agent_detector;
use local_nomoreai\local\quiz_analyser;
use local_nomoreai\local\signals;
use local_nomoreai\local\tokens;
use local_nomoreai\local\wsdenylist;

/**
 * Tests for agent matching, tokens, browser summaries, quiz signals, retention and the notice.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\agent_detector::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\pageview::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\quiz_analyser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\tokens::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\observer::class)]
final class detection_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enableintests', 1, 'local_nomoreai');
    }

    public function test_detector_matches(): void {
        $this->assertNull(agent_detector::detect(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0 Safari/537.36'], '10.0.0.1'));
        $this->assertSame(
            signals::SIGNED_AGENT,
            agent_detector::detect(['HTTP_SIGNATURE_INPUT' => 'sig1=("@authority");tag="web-bot-auth"'], '10.0.0.1')['signal']
        );
        $this->assertSame(
            'ChatGPT-User',
            agent_detector::detect(['HTTP_USER_AGENT' => 'Mozilla/5.0; chatgpt-user/1.0'], '10.0.0.1')['client']
        );

        set_config('ipdeny', "# comment\n203.0.113.0/24", 'local_nomoreai');
        $this->assertSame(signals::AGENT_IP, agent_detector::detect([], '203.0.113.9')['signal']);
        $this->assertNull(agent_detector::detect([], '198.51.100.1'));
    }

    public function test_denylist_functions_exist(): void {
        foreach (wsdenylist::functions() as $name) {
            $this->assertNotEmpty(
                \core_external\external_api::external_function_info($name, IGNORE_MISSING),
                "$name is not an external function"
            );
        }
    }

    public function test_block_tokens_deletes_self_created_token(): void {
        global $DB, $USER;
        set_config('mode', 'enforce', 'local_nomoreai');
        set_config('blocktokens', 1, 'local_nomoreai');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        $service->enabled = 1;
        $DB->update_record('external_services', $service);

        $this->setUser($student);
        \core_external\util::generate_token_for_current_user($service);
        $this->assertEquals(0, $DB->count_records('external_tokens', ['userid' => $student->id]));
        $this->assertTrue($DB->record_exists(signals::TABLE, ['signaltype' => signals::TOKEN_BLOCKED, 'userid' => $student->id]));

        $this->setUser($teacher);
        \core_external\util::generate_token_for_current_user($service);
        $this->assertEquals(1, $DB->count_records('external_tokens', ['userid' => $teacher->id]));
    }

    public function test_purge_removes_only_nonexempt_tokens(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        foreach ([$student, $teacher] as $user) {
            $this->setUser($user);
            \core_external\util::generate_token_for_current_user($service);
        }
        // A token an administrator issued to the student (an integration) is left alone.
        $this->setAdminUser();
        $issued = \core_external\util::generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            $student->id,
            \context_system::instance()
        );

        $this->assertCount(1, tokens::nonexempt_tokens());
        $this->assertSame(1, tokens::purge_nonexempt());
        $this->assertEquals([$issued], $DB->get_fieldset_select('external_tokens', 'token', 'userid = ?', [$student->id]));
        $this->assertTrue($DB->record_exists('external_tokens', ['userid' => $teacher->id]));
    }

    public function test_audit_and_purge_cover_personal_access_tokens(): void {
        global $DB;
        if (!tokens::personal_tokens_supported()) {
            $this->markTestSkipped('Personal access tokens need Moodle 5.3 (MDL-87706).');
        }
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        // A web-service token of the student's, whose id may equal a personal access token's id: the purge must
        // delete each from its own table.
        $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        $this->setUser($student);
        \core_external\util::generate_token_for_current_user($service);
        $this->setAdminUser();

        $repository = new \core\api\repository\api_token_repository();
        $expiry = time() + DAYSECS;
        $active = $repository->create_token('AI tool', random_string(32), $student->id, ['x'], null, $expiry)->get_id();
        $revoked = $repository->create_token('Old', random_string(32), $student->id, ['x'], null, $expiry)->get_id();
        $repository->revoke_token($revoked);
        $teachers = $repository->create_token('Mine', random_string(32), $teacher->id, ['x'], null, $expiry)->get_id();

        $list = tokens::nonexempt_tokens();
        $this->assertCount(2, $list);
        $personal = array_values(array_filter($list, fn($t) => $t->kind === tokens::KIND_PERSONAL));
        $this->assertCount(1, $personal);
        $this->assertEquals($active, $personal[0]->id);
        $this->assertEquals($student->id, $personal[0]->userid);

        $this->assertSame(2, tokens::purge_nonexempt());
        $this->assertFalse($DB->record_exists('rest_api_tokens', ['id' => $active]));
        $this->assertEquals(0, $DB->count_records('external_tokens', ['userid' => $student->id]));
        // A revoked token no longer works and is not listed; the exempt teacher's token is kept.
        $this->assertTrue($DB->record_exists('rest_api_tokens', ['id' => $revoked]));
        $this->assertTrue($DB->record_exists('rest_api_tokens', ['id' => $teachers]));
    }

    public function test_pageview_summary_records_signals_once(): void {
        global $DB;
        set_config('mode', 'detect', 'local_nomoreai');
        set_config('monitoring', 1, 'local_nomoreai');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $this->setUser($student);

        $args = [$forum->cmid, str_repeat('a', 24), true, ['claude', 'unknown'], 3, 10, 9, 1, 800];
        $result = record_pageview::execute(...$args);
        $this->assertEqualsCanonicalizing([signals::WEBDRIVER, signals::ARTIFACT, signals::TEXT_WITHOUT_KEYS,
            signals::CLICKS_WITHOUT_POINTER, signals::PASTE], $result['recorded']);

        // The same page view sends again: nothing new.
        $this->assertSame([], record_pageview::execute(...$args)['recorded']);
        $this->assertEquals(5, $DB->count_records(signals::TABLE, ['userid' => $student->id]));

        // A fresh client page-view id does not add rows either: at most one per signal, activity and day.
        $args[1] = str_repeat('c', 24);
        $this->assertSame([], record_pageview::execute(...$args)['recorded']);

        // Ordinary use records nothing.
        $this->assertSame([], record_pageview::execute($forum->cmid, str_repeat('b', 24), false, [], 0, 10, 1, 0, 0)['recorded']);
    }

    public function test_pageview_ignored_for_unmonitored_users(): void {
        global $DB;
        set_config('mode', 'detect', 'local_nomoreai');
        set_config('monitoring', 1, 'local_nomoreai');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/nomoreai:notmonitored', CAP_ALLOW, $roleid, \context_system::instance(), true);
        $this->getDataGenerator()->role_assign($roleid, $student->id, \context_course::instance($course->id));
        $this->setUser($student);

        $this->assertSame([], record_pageview::execute($forum->cmid, str_repeat('a', 24), true, [], 5, 5, 5, 0, 0)['recorded']);
        $this->assertEquals(0, $DB->count_records(signals::TABLE));
    }

    public function test_quiz_speed_and_pageview_signals(): void {
        global $DB;
        set_config('mode', 'detect', 'local_nomoreai');
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $base = time() - 10000;
        // Five other attempts of 600 s; median 600.
        for ($i = 1; $i <= 5; $i++) {
            $DB->insert_record('quiz_attempts', (object) ['quiz' => $quiz->id, 'userid' => $i + 1000, 'attempt' => 1,
                'uniqueid' => 9000 + $i, 'layout' => '', 'state' => 'finished', 'timestart' => $base,
                'timefinish' => $base + 600, 'timemodified' => $base, 'preview' => 0]);
        }
        $this->assertEquals(600.0, quiz_analyser::median_duration($quiz->id, 0));

        $attempt = (object) ['id' => 1, 'quiz' => $quiz->id, 'userid' => $student->id, 'preview' => 0,
            'timestart' => $base, 'timefinish' => $base + 60];
        // Enable the standard log only now, so no fixture events sit in its buffer when the test resets.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        get_log_manager(true);
        $recorded = quiz_analyser::analyse($attempt, $context);
        $this->assertContains(signals::FAST, $recorded);
        // No attempt page views are logged for this made-up attempt.
        $this->assertContains(signals::NO_PAGEVIEW, $recorded);

        $attempt->timefinish = $base + 500;
        $this->assertNotContains(signals::FAST, quiz_analyser::analyse($attempt, $context));
    }

    public function test_retention_purge(): void {
        global $DB;
        set_config('mode', 'detect', 'local_nomoreai');
        $old = signals::record(signals::AGENT_UA, null, [], false, 5);
        $new = signals::record(signals::AGENT_UA, null, [], false, 5);
        $DB->set_field(signals::TABLE, 'timecreated', time() - 91 * DAYSECS, ['id' => $old]);
        signals::purge_expired();
        $this->assertFalse($DB->record_exists(signals::TABLE, ['id' => $old]));
        $this->assertTrue($DB->record_exists(signals::TABLE, ['id' => $new]));
    }

    public function test_notice_shown_only_in_enforce_and_monitoring_sentence(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $context = \context_module::instance($forum->cmid);
        $this->setUser($student);

        set_config('mode', 'detect', 'local_nomoreai');
        $this->assertSame('', hook_callbacks::notice_html($context));

        set_config('monitoring', 1, 'local_nomoreai');
        $html = hook_callbacks::notice_html($context);
        $this->assertStringContainsString('Interaction patterns on this page are recorded', $html);
        $this->assertStringNotContainsString('AI agents must not', $html);

        set_config('mode', 'enforce', 'local_nomoreai');
        set_config('noticetext', 'Agents <b>stop</b>', 'local_nomoreai');
        $html = hook_callbacks::notice_html($context);
        $this->assertStringContainsString('Agents &lt;b&gt;stop&lt;/b&gt;', $html);
        $this->assertStringContainsString('role="note"', $html);
    }
}
