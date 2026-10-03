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
 * Turns a browser page-view summary (counts and booleans only) into behavioural signals.
 *
 * These signals are never used to refuse anything: assistive technology, dictation, autofill and password
 * managers produce some of them too, so they are recorded for a teacher to interpret.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pageview {
    /** @var int Text-field changes without key presses needed for a signal. */
    public const MIN_TEXT_WITHOUT_KEYS = 2;
    /** @var int Clicks needed before the click ratio is judged. */
    public const MIN_CLICKS = 3;
    /** @var float Share of clicks without pointer movement needed for a signal. */
    public const CLICK_RATIO = 0.8;
    /** @var int Pasted characters needed for a signal. */
    public const MIN_PASTED = 500;

    /**
     * The configured agent DOM signatures.
     *
     * @return array id => CSS selector list
     */
    public static function artifacts(): array {
        $out = [];
        foreach (config::lines('artifacts') as $line) {
            [$id, $selector] = array_pad(explode('|', $line, 2), 2, '');
            $id = clean_param(trim($id), PARAM_ALPHANUMEXT);
            if ($id !== '' && trim($selector) !== '') {
                $out[$id] = trim($selector);
            }
        }
        return $out;
    }

    /**
     * Record the signals a summary shows.
     *
     * @param \context_module $context the activity the page belongs to
     * @param array $summary validated summary values
     * @return string[] signal types recorded by this call
     */
    public static function evaluate(\context_module $context, array $summary): array {
        $found = [];
        if (!empty($summary['webdriver'])) {
            $found[signals::WEBDRIVER] = [];
        }
        $known = self::artifacts();
        foreach ($summary['artifacts'] ?? [] as $id) {
            if (isset($known[$id])) {
                $found[signals::ARTIFACT] = ['artifact' => $id];
                break;
            }
        }
        if (($summary['textwithoutkeys'] ?? 0) >= self::MIN_TEXT_WITHOUT_KEYS) {
            $found[signals::TEXT_WITHOUT_KEYS] = ['count' => (int) $summary['textwithoutkeys']];
        }
        $clicks = (int) ($summary['clicks'] ?? 0);
        $bare = (int) ($summary['clickswithoutmove'] ?? 0);
        if ($clicks >= self::MIN_CLICKS && $bare / $clicks >= self::CLICK_RATIO) {
            $found[signals::CLICKS_WITHOUT_POINTER] = ['clicks' => $clicks, 'withoutmove' => $bare];
        }
        if (($summary['pastedchars'] ?? 0) >= self::MIN_PASTED) {
            $found[signals::PASTE] = ['pastes' => (int) ($summary['pastes'] ?? 0), 'chars' => (int) $summary['pastedchars']];
        }

        // Record each signal once per user, activity and day: the page-view id comes from the client, so it must
        // not decide how many rows are written.
        $key = 'd' . date('Ymd') . '-' . $context->id;
        $recorded = [];
        foreach ($found as $type => $detail) {
            if (signals::record($type, $context, $detail, false, null, $key) !== null) {
                $recorded[] = $type;
            }
        }
        return $recorded;
    }
}
