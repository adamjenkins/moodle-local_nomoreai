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

namespace local_nomoreai\local;

/**
 * Anonymous per-page-view browser measurements, kept to calibrate the behavioural thresholds.
 *
 * Every monitored page view stores its measurements here, whether or not a signal fired, so an administrator can
 * see what normal page views on the site look like before trusting a threshold. No user id is stored. Rows are
 * deleted with the course or activity, on course reset, and after the signal retention period.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metrics {
    /** @var string Table name. */
    public const TABLE = 'local_nomoreai_metric';

    /** @var int Most measurement rows one browser session may create (the page-view id comes from the client). */
    public const MAX_PER_SESSION = 300;

    /** @var int Most measurement rows one user may create in a day, across all their sessions. */
    public const MAX_PER_USER_DAY = 300;

    /** @var int Most recent rows read for the calibration view. */
    public const MAX_CALIBRATION_ROWS = 20000;

    /** @var string[] Measured fields, all non-negative integers. */
    public const FIELDS = ['clicks', 'clickswithoutmove', 'measuredclicks', 'centreclicks', 'keyintervals', 'keymedian',
        'keycv', 'textwithoutkeys', 'pointermoves', 'scrolls'];

    /**
     * Store or update the measurements of one page view.
     *
     * @param \context_module $context
     * @param string $pageview validated page-view id
     * @param array $summary validated summary (FIELDS, plus firstinputms: int, -1 when nothing happened yet)
     * @return void
     */
    public static function save(\context_module $context, string $pageview, array $summary): void {
        global $DB, $SESSION, $USER;
        $cmid = (int) $context->instanceid;
        $row = (object) ['timemodified' => time()];
        foreach (self::FIELDS as $field) {
            $row->$field = (int) ($summary[$field] ?? 0);
        }
        $row->firstinputms = ($summary['firstinputms'] ?? -1) >= 0 ? (int) $summary['firstinputms'] : null;

        if ($id = $DB->get_field(self::TABLE, 'id', ['pageview' => $pageview, 'cmid' => $cmid])) {
            $row->id = $id;
            $DB->update_record(self::TABLE, $row);
            return;
        }
        // The session count is a fast path; logging in again resets it, so the real limit is per user and day.
        $SESSION->local_nomoreai_metricrows = (int) ($SESSION->local_nomoreai_metricrows ?? 0) + 1;
        if ($SESSION->local_nomoreai_metricrows > self::MAX_PER_SESSION || !self::count_user_row((int) $USER->id)) {
            return;
        }
        $row->pageview = $pageview;
        $row->cmid = $cmid;
        $row->courseid = (int) $DB->get_field('course_modules', 'course', ['id' => $cmid]);
        $row->timecreated = $row->timemodified;
        $DB->insert_record(self::TABLE, $row);
    }

    /**
     * Count one new measurement row against the user's daily limit.
     *
     * The count is kept in an application cache keyed by user and day, so it holds across sessions; the rows
     * themselves stay anonymous.
     *
     * @param int $userid
     * @param int|null $now
     * @return bool whether the row may be stored
     */
    public static function count_user_row(int $userid, ?int $now = null): bool {
        $cache = \cache::make('local_nomoreai', 'metricrows');
        $key = self::user_day_key($userid, $now);
        $count = (int) $cache->get($key) + 1;
        $cache->set($key, $count);
        return $count <= self::MAX_PER_USER_DAY;
    }

    /**
     * The cache key of a user's daily row count.
     *
     * @param int $userid
     * @param int|null $now
     * @return string
     */
    public static function user_day_key(int $userid, ?int $now = null): string {
        return 'u' . $userid . '_' . date('Ymd', $now ?? time());
    }

    /**
     * Delete measurements older than the signal retention period.
     *
     * @param int|null $now
     * @return void
     */
    public static function purge_expired(?int $now = null): void {
        global $DB;
        $days = max(1, (int) config::get('retentiondays'));
        $DB->delete_records_select(self::TABLE, 'timecreated < ?', [($now ?? time()) - $days * DAYSECS]);
    }

    /**
     * The calibration figures: for each behavioural measure, its spread over the page views that had enough
     * events to judge, and how many of them the current threshold would flag.
     *
     * @param int $courseid 0 for the whole site
     * @param int $cmid 0 for all activities
     * @return array[] one entry per measure: name, unit, threshold, direction ('above'|'below'), count,
     *     percentiles (5, 25, 50, 75, 95 => value), flagged
     */
    public static function calibration(int $courseid = 0, int $cmid = 0): array {
        global $DB;
        $conditions = [];
        if ($courseid) {
            $conditions['courseid'] = $courseid;
        }
        if ($cmid) {
            $conditions['cmid'] = $cmid;
        }
        $rows = $DB->get_records(self::TABLE, $conditions, 'timecreated DESC', '*', 0, self::MAX_CALIBRATION_ROWS);

        $values = ['centreshare' => [], 'barepointershare' => [], 'keymedian' => [], 'keycv' => [], 'firstinput' => []];
        foreach ($rows as $row) {
            if ($row->measuredclicks >= pageview::min_centre_clicks()) {
                $values['centreshare'][] = $row->centreclicks / $row->measuredclicks;
            }
            if ($row->clicks >= pageview::MIN_CLICKS) {
                $values['barepointershare'][] = $row->clickswithoutmove / $row->clicks;
            }
            if ($row->keyintervals >= pageview::min_key_intervals()) {
                $values['keymedian'][] = (int) $row->keymedian;
                $values['keycv'][] = $row->keycv / 1000;
            }
            if ($row->firstinputms !== null) {
                $values['firstinput'][] = (int) $row->firstinputms;
            }
        }

        $measures = [
            ['name' => 'centreshare', 'unit' => 'share', 'threshold' => pageview::centre_ratio(), 'direction' => 'above'],
            ['name' => 'barepointershare', 'unit' => 'share', 'threshold' => pageview::CLICK_RATIO, 'direction' => 'above'],
            ['name' => 'keymedian', 'unit' => 'ms', 'threshold' => pageview::key_median_ms(), 'direction' => 'below'],
            ['name' => 'keycv', 'unit' => 'ratio', 'threshold' => pageview::key_cv(), 'direction' => 'below'],
            ['name' => 'firstinput', 'unit' => 'ms', 'threshold' => pageview::instant_ms(), 'direction' => 'below'],
        ];
        foreach ($measures as &$measure) {
            $list = $values[$measure['name']];
            sort($list);
            $measure['count'] = count($list);
            $measure['percentiles'] = [];
            foreach ([5, 25, 50, 75, 95] as $p) {
                $measure['percentiles'][$p] = $list ? $list[(int) floor(($p / 100) * (count($list) - 1))] : null;
            }
            $threshold = $measure['threshold'];
            $measure['flagged'] = count(array_filter($list, fn($v) => $measure['direction'] === 'above' ?
                $v >= $threshold : $v < $threshold));
        }
        unset($measure);
        return $measures;
    }
}
