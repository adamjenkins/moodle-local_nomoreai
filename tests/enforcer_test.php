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

use core_external\external_api;
use local_nomoreai\local\enforcer;
use local_nomoreai\local\signals;
use local_nomoreai\local\tokens;
use local_nomoreai\local\webonly;

/**
 * Tests for the deterministic controls, through core's real external-function path.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\enforcer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\tokens::class)]
final class enforcer_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass */
    private $teacher;
    /** @var \stdClass */
    private $forum;
    /** @var \stdClass */
    private $quiz;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        enforcer::reset();
        set_config('enableintests', 1, 'local_nomoreai');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->forum = $gen->create_module('forum', ['course' => $this->course->id]);
        $this->quiz = $gen->create_module('quiz', ['course' => $this->course->id]);
    }

    protected function tearDown(): void {
        enforcer::reset();
        unset($_SERVER['HTTP_SIGNATURE_AGENT'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['HTTP_USER_AGENT'] = '';
        parent::tearDown();
    }

    /**
     * Call an external function as a web-service (token) request would.
     *
     * @param string $function
     * @param array $args
     * @return array call_external_function() response
     */
    private function ws(string $function, array $args): array {
        // WS_SERVER is false under PHPUnit, so core checks a sesskey as it would for AJAX.
        $_POST['sesskey'] = sesskey();
        enforcer::$forcews = true;
        $response = external_api::call_external_function($function, $args);
        enforcer::$forcews = false;
        return $response;
    }

    /**
     * The error code of a failed response, or null if it succeeded.
     *
     * @param array $response
     * @return string|null
     */
    private function errorcode(array $response): ?string {
        return $response['error'] ? ($response['exception']->errorcode ?? 'other') : null;
    }

    /**
     * Forum post arguments.
     *
     * @return array
     */
    private function discussionargs(): array {
        return ['forumid' => $this->forum->id, 'subject' => 'S', 'message' => 'M'];
    }

    public function test_enforce_refuses_student_submission_through_token(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        $this->setUser($this->student);

        $this->assertSame('refused', $this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));
        $this->assertSame('refused', $this->errorcode($this->ws('mod_quiz_start_attempt', ['quizid' => $this->quiz->id])));
        $this->assertEquals(0, $GLOBALS['DB']->count_records('forum_discussions', ['forum' => $this->forum->id]));
        $this->assertEquals(2, $GLOBALS['DB']->count_records(
            signals::TABLE,
            ['signaltype' => signals::WS_SUBMISSION, 'outcome' => 'refused', 'userid' => $this->student->id]
        ));
    }

    public function test_xapi_submissions_are_decided_without_a_context(): void {
        global $DB;
        set_config('mode', 'enforce', 'local_nomoreai');
        $args = ['component' => 'mod_h5pactivity', 'requestjson' => '[]'];

        $this->setUser($this->student);
        $this->assertSame('refused', $this->errorcode($this->ws('core_xapi_statement_post', $args)));
        $this->assertSame('refused', $this->errorcode($this->ws('core_xapi_post_state', ['component' => 'mod_h5pactivity',
            'activityId' => 'http://example.com/xapi/activity/1', 'agent' => '{}', 'stateId' => 's', 'stateData' => '{}'])));
        $this->assertEquals(2, $DB->count_records(signals::TABLE, ['signaltype' => signals::WS_SUBMISSION,
            'outcome' => 'refused']));

        // The teacher's call reaches core (which rejects the empty statement list for its own reasons).
        $this->setUser($this->teacher);
        $this->assertNotSame('refused', $this->errorcode($this->ws('core_xapi_statement_post', $args)));

        // Detect records the student's call but lets it through.
        set_config('mode', 'detect', 'local_nomoreai');
        $this->setUser($this->student);
        $this->assertNotSame('refused', $this->errorcode($this->ws('core_xapi_statement_post', $args)));
        $this->assertEquals(1, $DB->count_records(signals::TABLE, ['signaltype' => signals::WS_SUBMISSION,
            'mode' => 'detect']));
    }

    public function test_enforce_allows_exempt_teacher(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        $this->setUser($this->teacher);

        $this->assertNull($this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));
        $this->assertEquals(1, $GLOBALS['DB']->count_records('forum_discussions', ['forum' => $this->forum->id]));
    }

    public function test_exemption_is_scoped_to_its_activity(): void {
        global $DB;
        set_config('mode', 'enforce', 'local_nomoreai');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/nomoreai:exempt', CAP_ALLOW, $roleid, \context_system::instance(), true);
        $this->getDataGenerator()->role_assign($roleid, $this->student->id, \context_module::instance($this->forum->cmid));
        $other = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->setUser($this->student);

        $this->assertNull($this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));
        $this->assertSame('refused', $this->errorcode($this->ws(
            'mod_forum_add_discussion',
            ['forumid' => $other->id, 'subject' => 'S', 'message' => 'M']
        )));
        $this->assertEquals(1, $DB->count_records('forum_discussions', ['forum' => $this->forum->id]));
    }

    public function test_detect_records_but_allows(): void {
        set_config('mode', 'detect', 'local_nomoreai');
        $this->setUser($this->student);

        $this->assertNull($this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));
        $this->assertEquals(1, $GLOBALS['DB']->count_records(
            signals::TABLE,
            ['signaltype' => signals::WS_SUBMISSION, 'outcome' => 'recorded', 'mode' => 'detect']
        ));
    }

    public function test_off_and_inert_do_nothing(): void {
        global $DB;
        $this->setUser($this->student);
        set_config('mode', 'off', 'local_nomoreai');
        $this->assertNull($this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));

        // Enforce, but inert because the test did not opt in.
        set_config('mode', 'enforce', 'local_nomoreai');
        set_config('enableintests', 0, 'local_nomoreai');
        $this->assertNull($this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));
        $this->assertEquals(0, $DB->count_records(signals::TABLE));
    }

    public function test_browser_ajax_is_not_a_token_request(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        $this->setUser($this->student);
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function('mod_forum_add_discussion', $this->discussionargs());
        $this->assertNull($this->errorcode($response));
    }

    public function test_lockdown_setting_off_allows(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        set_config('wslockdown', 0, 'local_nomoreai');
        $this->setUser($this->student);
        $this->assertNull($this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));
    }

    public function test_trusted_network_skips_checks(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        // Under the CLI the remote address is 0.0.0.0, which address_in_subnet() never matches.
        $_SERVER['REMOTE_ADDR'] = '10.1.2.3';
        set_config('trustednetworks', "192.168.0.0/16\n10.1.2.0/24", 'local_nomoreai');
        $this->setUser($this->student);
        $this->assertNull($this->errorcode($this->ws('mod_forum_add_discussion', $this->discussionargs())));
    }

    public function test_block_tokens_refuses_every_call_for_students_only(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        set_config('blocktokens', 1, 'local_nomoreai');

        $this->setUser($this->student);
        $this->assertSame('refused', $this->errorcode($this->ws('core_webservice_get_site_info', [])));

        $this->setUser($this->teacher);
        $this->assertNull($this->errorcode($this->ws('core_webservice_get_site_info', [])));
    }

    public function test_webonly_refuses_even_reading(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        webonly::set((int) $this->quiz->cmid, true);
        $this->setUser($this->student);

        $response = $this->ws('mod_quiz_get_quiz_access_information', ['quizid' => $this->quiz->id]);
        $this->assertSame('refused', $this->errorcode($response));
        $this->assertTrue($GLOBALS['DB']->record_exists(signals::TABLE, ['signaltype' => signals::WEBONLY]));

        webonly::set((int) $this->quiz->cmid, false);
        $this->assertNull($this->errorcode($this->ws('mod_quiz_get_quiz_access_information', ['quizid' => $this->quiz->id])));
    }

    public function test_agent_refused_on_activity_page(): void {
        set_config('mode', 'enforce', 'local_nomoreai');
        $this->setUser($this->student);
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 HeadlessChrome/140.0';
        $cm = get_coursemodule_from_id('forum', $this->forum->cmid);

        try {
            require_login($this->course, false, $cm, false, true);
            $this->fail('Expected a refusal');
        } catch (\moodle_exception $e) {
            $this->assertSame('refused', $e->errorcode);
        }
        $this->assertTrue($GLOBALS['DB']->record_exists(
            signals::TABLE,
            ['signaltype' => signals::AGENT_UA, 'cmid' => $this->forum->cmid]
        ));

        // A teacher with the same browser is not refused.
        $this->setUser($this->teacher);
        require_login($this->course, false, $cm, false, true);
    }

    public function test_signed_agent_refused_on_login_page(): void {
        global $SCRIPT;
        set_config('mode', 'enforce', 'local_nomoreai');
        $old = $SCRIPT;
        $SCRIPT = '/login/index.php';
        $_SERVER['HTTP_SIGNATURE_AGENT'] = '"https://chatgpt.com"';
        try {
            enforcer::after_config();
            $this->fail('Expected a refusal');
        } catch (\moodle_exception $e) {
            $this->assertSame('refused', $e->errorcode);
        } finally {
            $SCRIPT = $old;
        }
        $record = $GLOBALS['DB']->get_record(signals::TABLE, ['signaltype' => signals::SIGNED_AGENT]);
        $this->assertSame('https://chatgpt.com', json_decode($record->detail)->client);
    }

    public function test_detect_records_agent_once_per_day(): void {
        set_config('mode', 'detect', 'local_nomoreai');
        $this->setUser($this->student);
        $_SERVER['HTTP_USER_AGENT'] = 'HeadlessChrome';
        $cm = get_coursemodule_from_id('forum', $this->forum->cmid);
        require_login($this->course, false, $cm, false, true);
        require_login($this->course, false, $cm, false, true);
        $this->assertEquals(1, $GLOBALS['DB']->count_records(signals::TABLE, ['signaltype' => signals::AGENT_UA]));
    }

    /**
     * Create a personal access token (Moodle 5.3, MDL-87706) and the credential a client would send for it.
     *
     * Skips the test on branches without personal access tokens.
     *
     * @param int $userid owner
     * @return array [token id, credential]
     */
    private function personal_token(int $userid): array {
        if (!tokens::personal_tokens_supported()) {
            $this->markTestSkipped('Personal access tokens need Moodle 5.3 (MDL-87706).');
        }
        $secret = random_string(32);
        $id = (new \core\api\repository\api_token_repository())
            ->create_token('AI tool', $secret, $userid, ['x'], null, time() + DAYSECS)
            ->get_id();
        // The format core\api\token_manager::issue_token() hands to the user.
        return [$id, rtrim(tokens::PERSONAL_PREFIX . base64_encode($id . '/' . $secret), '=')];
    }

    public function test_block_tokens_refuses_personal_access_token_for_students_only(): void {
        global $DB;
        set_config('mode', 'enforce', 'local_nomoreai');
        set_config('blocktokens', 1, 'local_nomoreai');
        [$studentid, $studentcredential] = $this->personal_token((int) $this->student->id);
        [$teacherid, $teachercredential] = $this->personal_token((int) $this->teacher->id);

        // A forged credential naming the student's token but carrying the wrong secret is left to core: nothing
        // is refused, recorded or deleted on its account.
        enforcer::personal_access_token('Bearer ' . rtrim(tokens::PERSONAL_PREFIX . base64_encode($studentid . '/x'), '='));
        $this->assertTrue($DB->record_exists('rest_api_tokens', ['id' => $studentid]));

        // An exempt teacher's token works.
        enforcer::personal_access_token('Bearer ' . $teachercredential);
        $this->assertTrue($DB->record_exists('rest_api_tokens', ['id' => $teacherid]));
        $this->assertFalse($DB->record_exists(signals::TABLE, ['signaltype' => signals::TOKEN_BLOCKED]));

        // The student's request, read from the real header at the end of setup, is refused and the token deleted.
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $studentcredential;
        try {
            enforcer::after_config();
            $this->fail('Expected a refusal');
        } catch (\moodle_exception $e) {
            $this->assertSame('refused', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('rest_api_tokens', ['id' => $studentid]));
        $this->assertTrue($DB->record_exists(
            signals::TABLE,
            ['signaltype' => signals::TOKEN_BLOCKED, 'userid' => $this->student->id, 'outcome' => 'refused']
        ));
    }

    public function test_detect_records_personal_access_token_use_without_refusing(): void {
        global $DB;
        set_config('mode', 'detect', 'local_nomoreai');
        set_config('blocktokens', 1, 'local_nomoreai');
        [$id, $credential] = $this->personal_token((int) $this->student->id);

        enforcer::personal_access_token('Bearer ' . $credential);
        enforcer::personal_access_token('Bearer ' . $credential);

        $this->assertTrue($DB->record_exists('rest_api_tokens', ['id' => $id]));
        // Recorded once per user and day.
        $this->assertEquals(1, $DB->count_records(
            signals::TABLE,
            ['signaltype' => signals::TOKEN_BLOCKED, 'userid' => $this->student->id, 'outcome' => 'recorded']
        ));

        // With Block tokens off nothing more is recorded.
        set_config('blocktokens', 0, 'local_nomoreai');
        enforcer::personal_access_token('Bearer ' . $credential);
        $this->assertEquals(1, $DB->count_records(signals::TABLE, ['signaltype' => signals::TOKEN_BLOCKED]));
    }
}
