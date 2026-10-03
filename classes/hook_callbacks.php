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
use local_nomoreai\local\enforcer;
use local_nomoreai\local\exemption;
use local_nomoreai\local\pageview;

/**
 * Hook callbacks.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * End of setup: agent refusal on the login page.
     *
     * @param \core\hook\after_config $hook
     * @return void
     */
    public static function after_config(\core\hook\after_config $hook): void {
        enforcer::after_config();
    }

    /**
     * Top of the page body: the student notice and the monitoring sentence.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     * @return void
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        $context = self::student_activity_context($hook->renderer->get_page());
        if (!$context) {
            return;
        }
        $html = self::notice_html($context);
        if ($html !== '') {
            $hook->add_html($html);
        }
    }

    /**
     * Page footer: load the browser monitor.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        $page = $hook->renderer->get_page();
        $context = self::student_activity_context($page);
        if (!$context || !self::monitoring_runs($context)) {
            return;
        }
        $page->requires->js_call_amd('local_nomoreai/monitor', 'init', [
            (int) $context->instanceid,
            array_map(
                fn($id, $selector) => ['id' => $id, 'selector' => $selector],
                array_keys(pageview::artifacts()),
                pageview::artifacts()
            ),
        ]);
    }

    /**
     * The notice markup for an activity page, or '' when none is shown.
     *
     * @param \context_module $context
     * @return string
     */
    public static function notice_html(\context_module $context): string {
        $parts = [];
        if (config::enforcing() && config::enabled('notice') && !exemption::is_exempt($context)) {
            $text = trim((string) config::get('noticetext'));
            if ($text !== '') {
                $parts[] = nl2br(s($text));
            }
        }
        if (self::monitoring_runs($context)) {
            $parts[] = s(get_string('monitoringsentence', 'local_nomoreai'));
        }
        if (!$parts) {
            return '';
        }
        return \html_writer::div(
            implode(' ', $parts),
            'alert alert-info local-nomoreai-notice',
            ['role' => 'note', 'data-region' => 'local-nomoreai-notice']
        );
    }

    /**
     * Whether browser monitoring runs for the current user on this activity.
     *
     * @param \context_module $context
     * @return bool
     */
    public static function monitoring_runs(\context_module $context): bool {
        return config::active() && config::enabled('monitoring') && exemption::is_monitored($context);
    }

    /**
     * The activity context of a page viewed by a logged-in, non-guest user, if any.
     *
     * @param \moodle_page $page
     * @return \context_module|null
     */
    private static function student_activity_context(\moodle_page $page): ?\context_module {
        if (!config::active() || !isloggedin() || isguestuser() || during_initial_install()) {
            return null;
        }
        $context = $page->context;
        return ($context instanceof \context_module) ? $context : null;
    }
}
