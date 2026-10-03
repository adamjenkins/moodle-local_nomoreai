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
 * The signal store: every refusal, would-be refusal and detection is one row.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signals {
    /** @var string Table name. */
    public const TABLE = 'local_nomoreai_signal';

    // Deterministic signals (refused in Enforce).
    /** @var string Request carried Web Bot Auth signature headers. */
    public const SIGNED_AGENT = 'signed_agent';
    /** @var string User agent contained a listed agent token. */
    public const AGENT_UA = 'agent_ua';
    /** @var string Address is on the IP deny list. */
    public const AGENT_IP = 'agent_ip';
    /** @var string Work submitted through a web-service token. */
    public const WS_SUBMISSION = 'ws_submission';
    /** @var string Web-service use while "Block tokens" is on. */
    public const TOKEN_BLOCKED = 'token_blocked';
    /** @var string Web-service access to a "Web browser only" activity. */
    public const WEBONLY = 'webonly_violation';

    // Behavioural signals (only ever recorded).
    /** @var string Browser reports navigator.webdriver. */
    public const WEBDRIVER = 'webdriver';
    /** @var string Page contained a known agent's DOM traces. */
    public const ARTIFACT = 'vendor_artifact';
    /** @var string Text arrived in fields without key presses. */
    public const TEXT_WITHOUT_KEYS = 'text_without_keys';
    /** @var string Clicks arrived without pointer movement. */
    public const CLICKS_WITHOUT_POINTER = 'clicks_without_pointer';
    /** @var string Large amount of pasted text. */
    public const PASTE = 'large_paste';
    /** @var string Quiz attempt finished with no attempt page viewed. */
    public const NO_PAGEVIEW = 'answer_without_pageview';
    /** @var string Quiz attempt much faster than the class median. */
    public const FAST = 'fast_completion';

    /**
     * All signal types, in report order.
     *
     * @return string[]
     */
    public static function types(): array {
        return [
            self::SIGNED_AGENT, self::AGENT_UA, self::AGENT_IP, self::WS_SUBMISSION, self::TOKEN_BLOCKED,
            self::WEBONLY, self::WEBDRIVER, self::ARTIFACT, self::TEXT_WITHOUT_KEYS, self::CLICKS_WITHOUT_POINTER,
            self::PASTE, self::NO_PAGEVIEW, self::FAST,
        ];
    }

    /**
     * Record one signal.
     *
     * @param string $type one of the type constants
     * @param \context|null $context where it was seen; null for the system context
     * @param array $detail small set of scalar values
     * @param bool $refused whether the request was refused
     * @param int|null $userid null for the current user
     * @param string|null $pageview browser page-view id; a signal is recorded once per page view
     * @return int|null new row id, or null when it was a duplicate
     */
    public static function record(
        string $type,
        ?\context $context,
        array $detail = [],
        bool $refused = false,
        ?int $userid = null,
        ?string $pageview = null
    ): ?int {
        global $DB, $USER;

        $context = $context ?? \context_system::instance();
        $userid = $userid ?? (int) ($USER->id ?? 0);

        if (
            $pageview !== null && $DB->record_exists(
                self::TABLE,
                ['pageview' => $pageview, 'signaltype' => $type, 'userid' => $userid]
            )
        ) {
            return null;
        }

        $courseid = 0;
        $cmid = 0;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cmid = (int) $context->instanceid;
            $courseid = (int) $DB->get_field('course_modules', 'course', ['id' => $cmid]);
        } else if ($coursecontext = $context->get_course_context(false)) {
            $courseid = (int) $coursecontext->instanceid;
        }

        return (int) $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'contextid' => $context->id,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'signaltype' => $type,
            'detail' => $detail ? json_encode($detail) : null,
            'mode' => config::enforcing() ? config::MODE_ENFORCE : config::MODE_DETECT,
            'outcome' => $refused ? 'refused' : 'recorded',
            'origin' => self::origin(),
            'pageview' => $pageview,
            'timecreated' => time(),
        ]);
    }

    /**
     * Where the current request came from.
     *
     * @return string web, ws or login
     */
    public static function origin(): string {
        global $SCRIPT;
        if (defined('WS_SERVER') && WS_SERVER) {
            return 'ws';
        }
        if (isset($SCRIPT) && strpos((string) $SCRIPT, '/login/') === 0) {
            return 'login';
        }
        return 'web';
    }

    /**
     * Delete signals older than the retention period.
     *
     * @param int|null $now
     * @return void
     */
    public static function purge_expired(?int $now = null): void {
        global $DB;
        $days = max(1, (int) config::get('retentiondays'));
        $DB->delete_records_select(self::TABLE, 'timecreated < ?', [($now ?? time()) - $days * DAYSECS]);
    }
}
