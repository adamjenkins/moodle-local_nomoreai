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

namespace local_nomoreai\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_nomoreai\local\config;
use local_nomoreai\local\exemption;
use local_nomoreai\local\pageview;

/**
 * Receives the browser monitor's page-view summary.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_pageview extends external_api {
    /** @var int Upper bound accepted for any counter. */
    private const MAXCOUNT = 1000000;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the page'),
            'pageview' => new external_value(PARAM_ALPHANUM, 'Random page-view id'),
            'webdriver' => new external_value(PARAM_BOOL, 'navigator.webdriver was true'),
            'artifacts' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Matched agent signature id'),
                'Matched signatures',
                VALUE_DEFAULT,
                []
            ),
            'textwithoutkeys' => new external_value(PARAM_INT, 'Text-field changes with no key press just before'),
            'clicks' => new external_value(PARAM_INT, 'Clicks'),
            'clickswithoutmove' => new external_value(PARAM_INT, 'Clicks with no pointer movement just before'),
            'pastes' => new external_value(PARAM_INT, 'Paste events'),
            'pastedchars' => new external_value(PARAM_INT, 'Characters pasted'),
        ]);
    }

    /**
     * Record the summary.
     *
     * @param int $cmid
     * @param string $pageview
     * @param bool $webdriver
     * @param array $artifacts
     * @param int $textwithoutkeys
     * @param int $clicks
     * @param int $clickswithoutmove
     * @param int $pastes
     * @param int $pastedchars
     * @return array
     */
    public static function execute(
        int $cmid,
        string $pageview,
        bool $webdriver,
        array $artifacts,
        int $textwithoutkeys,
        int $clicks,
        int $clickswithoutmove,
        int $pastes,
        int $pastedchars
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'cmid',
            'pageview',
            'webdriver',
            'artifacts',
            'textwithoutkeys',
            'clicks',
            'clickswithoutmove',
            'pastes',
            'pastedchars'
        ));

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);

        if (isguestuser()) {
            throw new \require_login_exception('Guests are not monitored');
        }
        if (!config::active() || !config::enabled('monitoring') || !exemption::is_monitored($context)) {
            return ['recorded' => []];
        }
        if (\core_text::strlen($params['pageview']) < 16 || \core_text::strlen($params['pageview']) > 32) {
            throw new \invalid_parameter_exception('pageview');
        }

        $summary = ['webdriver' => $params['webdriver'], 'artifacts' => array_slice($params['artifacts'], 0, 10)];
        foreach (['textwithoutkeys', 'clicks', 'clickswithoutmove', 'pastes', 'pastedchars'] as $name) {
            $summary[$name] = max(0, min(self::MAXCOUNT, $params[$name]));
        }
        $summary['clickswithoutmove'] = min($summary['clickswithoutmove'], $summary['clicks']);

        return ['recorded' => pageview::evaluate($context, $summary)];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'recorded' => new external_multiple_structure(new external_value(PARAM_ALPHANUMEXT, 'Signal type recorded')),
        ]);
    }
}
