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
 * Server-side behavioural signals for finished quiz attempts.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_analyser {
    /** @var int Finished attempts by others needed before speed is judged. */
    public const MIN_ATTEMPTS = 5;

    /**
     * Analyse a submitted attempt.
     *
     * @param \stdClass $attempt quiz_attempts row
     * @param \context_module $context the quiz
     * @return string[] signal types recorded
     */
    public static function analyse(\stdClass $attempt, \context_module $context): array {
        $userid = (int) $attempt->userid;
        if (!empty($attempt->preview) || !exemption::is_monitored($context, $userid)) {
            return [];
        }
        $recorded = [];

        $duration = (int) $attempt->timefinish - (int) $attempt->timestart;
        $median = self::median_duration((int) $attempt->quiz, (int) $attempt->id);
        $fraction = (float) config::get('fastfraction');
        if ($median !== null && $duration >= 0 && $fraction > 0 && $duration < $fraction * $median) {
            signals::record(
                signals::FAST,
                $context,
                ['duration' => $duration, 'median' => (int) round($median)],
                false,
                $userid
            );
            $recorded[] = signals::FAST;
        }

        if (self::attempt_views((int) $attempt->id, $userid) === 0) {
            signals::record(signals::NO_PAGEVIEW, $context, ['attempt' => (int) $attempt->id], false, $userid);
            $recorded[] = signals::NO_PAGEVIEW;
        }
        return $recorded;
    }

    /**
     * Median duration of other finished, non-preview attempts at the quiz.
     *
     * @param int $quizid
     * @param int $excludeid attempt to leave out
     * @return float|null null when there are too few attempts
     */
    public static function median_duration(int $quizid, int $excludeid): ?float {
        global $DB;
        $durations = $DB->get_fieldset_sql(
            "SELECT timefinish - timestart
               FROM {quiz_attempts}
              WHERE quiz = :quiz AND id <> :id AND state = :state AND preview = 0 AND timefinish > 0",
            ['quiz' => $quizid, 'id' => $excludeid, 'state' => 'finished']
        );
        if (count($durations) < self::MIN_ATTEMPTS) {
            return null;
        }
        $durations = array_map('intval', $durations);
        sort($durations);
        $n = count($durations);
        $mid = intdiv($n, 2);
        return $n % 2 ? (float) $durations[$mid] : ($durations[$mid - 1] + $durations[$mid]) / 2;
    }

    /**
     * How many times the user viewed the attempt's pages, from the standard log.
     *
     * @param int $attemptid
     * @param int $userid
     * @return int|null null when the standard log store is not recording
     */
    public static function attempt_views(int $attemptid, int $userid): ?int {
        global $DB;
        $stores = explode(',', (string) get_config('tool_log', 'enabled_stores'));
        if (!in_array('logstore_standard', $stores, true)) {
            return null;
        }
        return $DB->count_records('logstore_standard_log', [
            'eventname' => '\\mod_quiz\\event\\attempt_viewed',
            'objectid' => $attemptid,
            'userid' => $userid,
        ]);
    }
}
