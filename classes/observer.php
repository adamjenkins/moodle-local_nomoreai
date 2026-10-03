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

use local_nomoreai\local\config;
use local_nomoreai\local\exemption;
use local_nomoreai\local\quiz_analyser;
use local_nomoreai\local\signals;
use local_nomoreai\local\webonly;

/**
 * Event observers.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Block tokens: remove a token a non-exempt user has just created for themselves.
     *
     * @param \core\event\webservice_token_created $event
     * @return void
     */
    public static function webservice_token_created(\core\event\webservice_token_created $event): void {
        global $DB;
        if (!config::active() || !config::enabled('blocktokens') || empty($event->other['auto'])) {
            return;
        }
        $userid = (int) $event->relateduserid;
        if (exemption::is_exempt_anywhere($userid) || exemption::trusted_network()) {
            return;
        }
        $enforce = config::enforcing();
        signals::record(signals::TOKEN_BLOCKED, null, ['minted' => 1], $enforce, $userid);
        if ($enforce) {
            $DB->delete_records('external_tokens', ['id' => $event->objectid]);
        }
    }

    /**
     * Quiz attempt submitted: speed and page-view signals.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     * @return void
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;
        if (!config::active()) {
            return;
        }
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid]);
        $context = $event->get_context();
        if ($attempt && $context instanceof \context_module) {
            quiz_analyser::analyse($attempt, $context);
        }
    }

    /**
     * Activity deleted: forget its setting and signals.
     *
     * @param \core\event\course_module_deleted $event
     * @return void
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        webonly::delete((int) $event->objectid);
        $DB->delete_records(signals::TABLE, ['cmid' => $event->objectid]);
    }

    /**
     * Course deleted: forget its signals.
     *
     * @param \core\event\course_deleted $event
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records(signals::TABLE, ['courseid' => $event->objectid]);
    }

    /**
     * Course reset: signals belong to the previous cohort's attempts, so they go.
     *
     * @param \core\event\course_reset_ended $event
     * @return void
     */
    public static function course_reset_ended(\core\event\course_reset_ended $event): void {
        global $DB;
        $DB->delete_records(signals::TABLE, ['courseid' => $event->courseid]);
    }
}
