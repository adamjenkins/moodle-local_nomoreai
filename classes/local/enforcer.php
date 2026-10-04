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
 * Applies the deterministic controls: agent refusal, web-service lockdown, Block tokens and Web browser only.
 *
 * Web-service decisions need the activity's context to scope exemptions, but the web-service override
 * callback that sees the function name gets no context. So the override callback only notes the function,
 * and the decision is taken in after_require_login(), which every external function reaches through
 * validate_context() with the course and activity it is about (external_api::validate_context() calls
 * require_login(), which calls the after_require_login callbacks).
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enforcer {
    /** @var string|null Name of the external function being executed in this web-service request. */
    private static $currentws = null;

    /** @var bool Whether that function is a denied submission function still waiting for a decision. */
    private static $pendingsubmission = false;

    /** @var bool Tests only: treat the request as a web-service (token) request. */
    public static $forcews = false;

    /**
     * Forget the request state (tests).
     *
     * @return void
     */
    public static function reset(): void {
        self::$currentws = null;
        self::$pendingsubmission = false;
        self::$forcews = false;
    }

    /**
     * Whether the request is a web-service (token) request, as opposed to the browser or its AJAX calls.
     *
     * @return bool
     */
    public static function is_ws(): bool {
        return self::$forcews || (defined('WS_SERVER') && WS_SERVER);
    }

    /**
     * Called before an external function runs (override_webservice_execution callback).
     *
     * @param \stdClass $function external function info
     * @return void
     * @throws \moodle_exception when the call is refused
     */
    public static function webservice_call(\stdClass $function): void {
        global $USER;

        if (!self::is_ws() || !config::active() || exemption::trusted_network()) {
            return;
        }
        self::$currentws = (string) $function->name;
        self::$pendingsubmission = config::enabled('wslockdown') && wsdenylist::contains(self::$currentws);

        if (config::enabled('blocktokens') && !exemption::is_exempt_anywhere((int) $USER->id)) {
            self::$pendingsubmission = false;
            self::act(signals::TOKEN_BLOCKED, null, ['function' => self::$currentws], self::daykey(null));
        }

        if (self::$pendingsubmission && wsdenylist::without_context(self::$currentws)) {
            // These never call validate_context(), so after_require_login() would never decide them: decide
            // now, by whether the user is exempt anywhere.
            self::$pendingsubmission = false;
            if (!exemption::is_exempt_anywhere((int) $USER->id)) {
                self::act(signals::WS_SUBMISSION, null, ['function' => self::$currentws]);
            }
        }
    }

    /**
     * Called at the end of every require_login() (after_require_login callback).
     *
     * @param mixed $courseorid course object or id, as passed to require_login()
     * @param \stdClass|\cm_info|null $cm
     * @return void
     * @throws \moodle_exception when the request is refused
     */
    public static function after_require_login($courseorid, $cm): void {
        global $USER;

        if (!config::active() || exemption::trusted_network()) {
            return;
        }
        $context = self::context_for($courseorid, $cm);

        if (self::is_ws()) {
            self::decide_webservice($context, $cm, (int) $USER->id);
            return;
        }

        if (!$context || !config::enabled('agentrefusal') || exemption::is_exempt($context)) {
            return;
        }
        if ($hit = agent_detector::detect()) {
            self::act($hit['signal'], $context, ['client' => $hit['client']], self::daykey($context));
        }
    }

    /**
     * Called at the end of setup (after_config hook): Block tokens for personal access tokens, and agent
     * refusal on the login page.
     *
     * The login page is checked for everyone because nobody is known yet; staff using a signed agent can
     * be let through with the trusted networks setting.
     *
     * @return void
     * @throws \moodle_exception when the request is refused
     */
    public static function after_config(): void {
        global $SCRIPT;

        self::personal_access_token();

        // No CLI_SCRIPT guard: CLI scripts never have the login page as $SCRIPT, and the guard would make
        // this untestable under PHPUnit (where CLI_SCRIPT is true).
        if (self::is_ws() || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return;
        }
        if (($SCRIPT ?? '') !== '/login/index.php' || !config::active() || !config::enabled('agentrefusal')) {
            return;
        }
        if (exemption::trusted_network()) {
            return;
        }
        if ($hit = agent_detector::detect()) {
            // Nobody is logged in here, so record each client at most once an hour per address (hashed, so no
            // address is stored); otherwise anyone could grow the table without limit.
            $key = 'l' . date('YmdH') . substr(sha1($hit['client'] . '|' . getremoteaddr()), 0, 21);
            self::act($hit['signal'], null, ['client' => $hit['client']], $key);
        }
    }

    /**
     * Block tokens for personal access tokens (Moodle 5.3 and later, MDL-87706).
     *
     * A personal access token is used through the routing API (Authorization: Bearer pat_...), which never
     * defines WS_SERVER or reaches the web-service callbacks, and core raises no event when one is created. So
     * the request is checked here, at the end of setup, before core's API middleware logs its owner in. In
     * Enforce the token is deleted, as Block tokens deletes the web-service tokens of users who are not exempt,
     * and the request is refused.
     *
     * @param string|null $authorization the Authorization header; null to read it from the request
     * @return void
     * @throws \moodle_exception under PHPUnit when the request is refused
     */
    public static function personal_access_token(?string $authorization = null): void {
        $authorization = $authorization ?? self::authorization_header();
        $prefix = 'Bearer ' . tokens::PERSONAL_PREFIX;
        if (strncasecmp($authorization, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        if (!config::active() || !config::enabled('blocktokens') || exemption::trusted_network()) {
            return;
        }
        $token = tokens::personal_token(trim(substr($authorization, strlen('Bearer '))));
        if (!$token || exemption::is_exempt_anywhere($token->userid)) {
            return;
        }
        $enforce = config::enforcing();
        signals::record(signals::TOKEN_BLOCKED, null, ['personaltoken' => 1], $enforce, $token->userid, self::daykey(null));
        if (!$enforce) {
            return;
        }
        tokens::delete_personal_token($token->id);
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            throw new \moodle_exception('refused', 'local_nomoreai');
        }
        self::refuse_api();
    }

    /**
     * The request's Authorization header, or '' when there is none.
     *
     * @return string
     */
    private static function authorization_header(): string {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                return (string) $_SERVER[$key];
            }
        }
        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    return (string) $value;
                }
            }
        }
        return '';
    }

    /**
     * Send a JSON 403 response to an API client and stop.
     *
     * @return never
     */
    private static function refuse_api(): never {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode([
            'error' => 'access_denied',
            'message' => get_string('refused', 'local_nomoreai'),
        ]);
        exit;
    }

    /**
     * Decide a web-service call now that its context is known.
     *
     * @param \context|null $context null when the function validated the system context
     * @param \stdClass|\cm_info|null $cm
     * @param int $userid
     * @return void
     */
    private static function decide_webservice(?\context $context, $cm, int $userid): void {
        if (self::$currentws === null) {
            return;
        }
        $exempt = $context ? exemption::is_exempt($context) : exemption::is_exempt_anywhere($userid);
        if ($exempt) {
            self::$pendingsubmission = false;
            return;
        }
        $detail = ['function' => self::$currentws];

        if ($cm && webonly::is_webonly((int) $cm->id)) {
            self::$pendingsubmission = false;
            self::act(signals::WEBONLY, $context, $detail, self::daykey($context));
        }
        if (self::$pendingsubmission) {
            // Decide once per call: the function may call require_login() again.
            self::$pendingsubmission = false;
            self::act(signals::WS_SUBMISSION, $context, $detail);
        }
    }

    /**
     * Record a signal and, in Enforce, refuse the request.
     *
     * @param string $type signal type
     * @param \context|null $context
     * @param array $detail
     * @param string|null $dedupe record at most once per this key (per user and type)
     * @return void
     * @throws \moodle_exception in Enforce
     */
    private static function act(string $type, ?\context $context, array $detail, ?string $dedupe = null): void {
        $enforce = config::enforcing();
        signals::record($type, $context, $detail, $enforce, null, $dedupe);
        if (!$enforce) {
            return;
        }
        $browser = !self::is_ws() && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT);
        if (!$browser || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            // Web-service and AJAX clients get a normal error response.
            throw new \moodle_exception('refused', 'local_nomoreai');
        }
        self::refuse_browser();
    }

    /**
     * Send a plain 403 page and stop.
     *
     * Core renders an exception thrown this early with status 500 (bootstrap_renderer) or 404 (core_renderer),
     * so a refusal renders its own minimal page to give clients a correct 403.
     *
     * @return never
     */
    private static function refuse_browser(): never {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }
        $title = s(get_string('refusedtitle', 'local_nomoreai'));
        echo '<!DOCTYPE html><html lang="' . s(current_language()) . '"><head><meta charset="utf-8">' .
            '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title></head>' .
            '<body><main><h1>' . $title . '</h1><p>' . s(get_string('refused', 'local_nomoreai')) . '</p></main></body></html>';
        exit;
    }

    /**
     * A key that records repeated page-level signals once per user, context and day.
     *
     * @param \context|null $context
     * @return string
     */
    private static function daykey(?\context $context): string {
        return 'd' . date('Ymd') . '-' . ($context ? $context->id : 0);
    }

    /**
     * The context a require_login() call is about, or null for site-level pages.
     *
     * @param mixed $courseorid
     * @param \stdClass|\cm_info|null $cm
     * @return \context|null
     */
    private static function context_for($courseorid, $cm): ?\context {
        if ($cm && !empty($cm->id)) {
            return \context_module::instance((int) $cm->id, IGNORE_MISSING) ?: null;
        }
        $courseid = is_object($courseorid) ? (int) ($courseorid->id ?? 0) : (int) $courseorid;
        if ($courseid && $courseid != SITEID) {
            return \context_course::instance($courseid, IGNORE_MISSING) ?: null;
        }
        return null;
    }
}
