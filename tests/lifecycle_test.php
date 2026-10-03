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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_nomoreai\local\signals;
use local_nomoreai\local\webonly;
use local_nomoreai\privacy\provider;

/**
 * Tests for privacy, backup/restore, deletion and reset.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\observer::class)]
final class lifecycle_test extends \core_privacy\tests\provider_testcase {
    public function test_privacy_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableintests', 1, 'local_nomoreai');
        set_config('mode', 'detect', 'local_nomoreai');
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $context = \context_module::instance($forum->cmid);
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        signals::record(signals::AGENT_UA, $context, ['client' => 'x'], false, $u1->id);
        signals::record(signals::AGENT_UA, $context, ['client' => 'y'], false, $u2->id);

        $contexts = provider::get_contexts_for_userid($u1->id);
        $this->assertEquals([$context->id], $contexts->get_contextids());

        $userlist = new userlist($context, 'local_nomoreai');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$u1->id, $u2->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($u1, 'local_nomoreai', [$context->id]));
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_nomoreai')]);
        $this->assertCount(1, $data->signals);

        provider::delete_data_for_user(new approved_contextlist($u1, 'local_nomoreai', [$context->id]));
        $this->assertFalse($DB->record_exists(signals::TABLE, ['userid' => $u1->id]));
        provider::delete_data_for_users(new approved_userlist($context, 'local_nomoreai', [$u2->id]));
        $this->assertEquals(0, $DB->count_records(signals::TABLE));
    }

    public function test_webonly_backup_restore_and_delete(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        webonly::set((int) $quiz->cmid, true);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'R1', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $newcm = $DB->get_record('course_modules', ['course' => $newcourseid,
            'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])], '*', MUST_EXIST);
        $this->assertTrue(webonly::is_webonly((int) $newcm->id));

        \core_courseformat\formatactions::cm($course->id)->delete((int) $quiz->cmid);
        $this->assertFalse($DB->record_exists(webonly::TABLE, ['cmid' => $quiz->cmid]));
    }

    public function test_course_reset_and_delete_remove_signals(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableintests', 1, 'local_nomoreai');
        set_config('mode', 'detect', 'local_nomoreai');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        signals::record(signals::AGENT_UA, $context, [], false, 7);
        $this->assertEquals(1, $DB->count_records(signals::TABLE, ['courseid' => $course->id]));

        \core\event\course_reset_ended::create(['context' => $context, 'courseid' => $course->id,
            'other' => ['reset_options' => ['courseid' => $course->id]]])->trigger();
        $this->assertEquals(0, $DB->count_records(signals::TABLE, ['courseid' => $course->id]));

        signals::record(signals::AGENT_UA, $context, [], false, 7);
        delete_course($course, false);
        $this->assertEquals(0, $DB->count_records(signals::TABLE, ['courseid' => $course->id]));
    }
}
