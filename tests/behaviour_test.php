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
use local_nomoreai\local\metrics;
use local_nomoreai\local\pageview;
use local_nomoreai\local\reports;
use local_nomoreai\local\signals;

/**
 * Tests for the click-precision, typing-rhythm and reaction-time signals and their calibration.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\pageview::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\local\metrics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_nomoreai\external\record_pageview::class)]
final class behaviour_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass */
    private $forum;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enableintests', 1, 'local_nomoreai');
        set_config('mode', 'detect', 'local_nomoreai');
        set_config('monitoring', 1, 'local_nomoreai');
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['summary' => '']);
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->forum = $gen->create_module('forum', ['course' => $this->course->id]);
    }

    /**
     * Send a page-view summary as the monitor would.
     *
     * @param string $pageview
     * @param array $values new measurement values, by parameter name
     * @return string[] recorded signal types
     */
    private function send(string $pageview, array $values): array {
        $v = $values + ['clicks' => 0, 'measuredclicks' => 0, 'centreclicks' => 0, 'keyintervals' => 0,
            'keymedian' => 0, 'keycv' => 0, 'firstinputms' => -1, 'pointermoves' => 0, 'scrolls' => 0];
        return record_pageview::execute(
            $this->forum->cmid,
            $pageview,
            false,
            [],
            0,
            $v['clicks'],
            0,
            0,
            0,
            $v['measuredclicks'],
            $v['centreclicks'],
            $v['keyintervals'],
            $v['keymedian'],
            $v['keycv'],
            $v['firstinputms'],
            $v['pointermoves'],
            $v['scrolls']
        )['recorded'];
    }

    public function test_machine_like_behaviour_records_signals(): void {
        $this->setUser($this->student);
        $recorded = $this->send(str_repeat('a', 24), ['clicks' => 8, 'measuredclicks' => 8, 'centreclicks' => 8,
            'keyintervals' => 40, 'keymedian' => 4, 'keycv' => 20, 'firstinputms' => 120]);
        $this->assertEqualsCanonicalizing([signals::CENTRE_CLICKS, signals::MACHINE_TYPING, signals::INSTANT], $recorded);
    }

    public function test_human_like_behaviour_records_nothing(): void {
        $this->setUser($this->student);
        $recorded = $this->send(str_repeat('b', 24), ['clicks' => 8, 'measuredclicks' => 8, 'centreclicks' => 1,
            'keyintervals' => 40, 'keymedian' => 180, 'keycv' => 650, 'firstinputms' => 2400, 'pointermoves' => 300]);
        $this->assertSame([], $recorded);
    }

    public function test_even_typing_at_human_speed_is_still_machine_like(): void {
        $this->setUser($this->student);
        // A script typing with a fixed 120 ms delay: human speed, no human variation.
        $recorded = $this->send(str_repeat('c', 24), ['keyintervals' => 40, 'keymedian' => 120, 'keycv' => 30]);
        $this->assertSame([signals::MACHINE_TYPING], $recorded);
    }

    public function test_too_few_events_are_not_judged(): void {
        $this->setUser($this->student);
        $recorded = $this->send(str_repeat('d', 24), ['clicks' => 2, 'measuredclicks' => 2, 'centreclicks' => 2,
            'keyintervals' => 5, 'keymedian' => 3, 'keycv' => 0]);
        $this->assertSame([], $recorded);
    }

    public function test_thresholds_follow_settings(): void {
        set_config('keymedianms', 200, 'local_nomoreai');
        $this->setUser($this->student);
        $this->assertSame(
            [signals::MACHINE_TYPING],
            $this->send(str_repeat('e', 24), ['keyintervals' => 40, 'keymedian' => 180, 'keycv' => 650])
        );
    }

    public function test_measurements_are_stored_anonymously_and_updated(): void {
        global $DB;
        $this->setUser($this->student);
        $this->send(str_repeat('f', 24), ['clicks' => 3, 'measuredclicks' => 3]);
        $this->send(str_repeat('f', 24), ['clicks' => 9, 'measuredclicks' => 9, 'centreclicks' => 1, 'firstinputms' => 900]);

        $rows = $DB->get_records(metrics::TABLE);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals(9, $row->clicks);
        $this->assertEquals(900, $row->firstinputms);
        $this->assertEquals($this->course->id, $row->courseid);
        $this->assertObjectNotHasProperty('userid', $row);
    }

    public function test_client_values_are_clamped(): void {
        global $DB;
        $this->setUser($this->student);
        $this->send(str_repeat('g', 24), ['clicks' => 2, 'measuredclicks' => 50, 'centreclicks' => 99]);
        $row = $DB->get_record(metrics::TABLE, ['pageview' => str_repeat('g', 24)]);
        $this->assertEquals(2, $row->measuredclicks);
        $this->assertEquals(2, $row->centreclicks);
    }

    public function test_session_cap_limits_new_rows(): void {
        global $DB, $SESSION;
        $this->setUser($this->student);
        $SESSION->local_nomoreai_metricrows = metrics::MAX_PER_SESSION;
        $this->send(str_repeat('h', 24), ['clicks' => 3]);
        $this->assertEquals(0, $DB->count_records(metrics::TABLE));
    }

    public function test_daily_cap_holds_across_sessions(): void {
        global $DB, $SESSION;
        $this->setUser($this->student);
        // A fresh session, but the user already created the day's maximum.
        $SESSION->local_nomoreai_metricrows = 0;
        \cache::make('local_nomoreai', 'metricrows')->set(
            metrics::user_day_key((int) $this->student->id),
            metrics::MAX_PER_USER_DAY
        );
        $this->send(str_repeat('j', 24), ['clicks' => 3]);
        $this->assertEquals(0, $DB->count_records(metrics::TABLE));

        // Another user is not affected.
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);
        $SESSION->local_nomoreai_metricrows = 0;
        $this->send(str_repeat('k', 24), ['clicks' => 3]);
        $this->assertEquals(1, $DB->count_records(metrics::TABLE));
    }

    public function test_count_user_row_stops_at_the_daily_limit(): void {
        $now = time();
        \cache::make('local_nomoreai', 'metricrows')->set(metrics::user_day_key(5, $now), metrics::MAX_PER_USER_DAY - 1);
        $this->assertTrue(metrics::count_user_row(5, $now));
        $this->assertFalse(metrics::count_user_row(5, $now));
        // The next day starts again.
        $this->assertTrue(metrics::count_user_row(5, $now + DAYSECS));
    }

    public function test_calibration_spread_and_flagged_count(): void {
        $context = \context_module::instance($this->forum->cmid);
        foreach ([10, 20, 30, 40, 900] as $i => $first) {
            metrics::save($context, 'cal' . str_repeat('x', 20) . $i, ['clicks' => 6, 'measuredclicks' => 6,
                'centreclicks' => $i === 4 ? 6 : 0, 'firstinputms' => $first * 10]);
        }
        $measures = array_column(metrics::calibration((int) $this->course->id), null, 'name');

        $this->assertSame(5, $measures['centreshare']['count']);
        $this->assertSame(1, $measures['centreshare']['flagged']);
        // First inputs 100..9000 ms; with the 500 ms default only 100, 200, 300 and 400 count as instant.
        $this->assertSame(4, $measures['firstinput']['flagged']);
        $this->assertSame(300, $measures['firstinput']['percentiles'][50]);
        $this->assertSame(0, $measures['keymedian']['count']);

        $html = (new reports($this->course))->render(reports::TAB_CALIBRATION);
        $this->assertStringContainsString(get_string('calibration_centreshare', 'local_nomoreai'), $html);
        $this->assertStringContainsString('1 (20.0%)', $html);
    }

    public function test_calibration_without_data(): void {
        $html = (new reports())->render(reports::TAB_CALIBRATION);
        $this->assertStringContainsString(get_string('calibration_nodata', 'local_nomoreai'), $html);
    }

    public function test_activity_deletion_removes_measurements(): void {
        global $DB;
        metrics::save(\context_module::instance($this->forum->cmid), str_repeat('i', 24), ['clicks' => 1]);
        \core_courseformat\formatactions::cm($this->course->id)->delete((int) $this->forum->cmid);
        $this->assertEquals(0, $DB->count_records(metrics::TABLE));
    }
}
