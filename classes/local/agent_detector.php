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
 * Recognises requests from agents that identify themselves: signed agents, agent user agents, listed IPs.
 *
 * Only deterministic, protocol-level signals are used here, never behaviour, so these can safely be refused.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class agent_detector {
    /**
     * Check a request.
     *
     * @param array|null $server the request's $_SERVER values, null for the current request
     * @param string|null $ip the request's address, null for the current request
     * @return array|null null when nothing matched, else ['signal' => type, 'client' => short description]
     */
    public static function detect(?array $server = null, ?string $ip = null): ?array {
        $server = $server ?? $_SERVER;

        // Web Bot Auth (HTTP Message Signatures, RFC 9421): signed agents such as ChatGPT's send these.
        $signatureagent = trim((string) ($server['HTTP_SIGNATURE_AGENT'] ?? ''));
        $signatureinput = (string) ($server['HTTP_SIGNATURE_INPUT'] ?? '');
        if ($signatureagent !== '' || stripos($signatureinput, 'web-bot-auth') !== false) {
            $client = $signatureagent !== '' ? $signatureagent : 'web-bot-auth';
            return ['signal' => signals::SIGNED_AGENT, 'client' => self::shorten(trim($client, '"'))];
        }

        $useragent = (string) ($server['HTTP_USER_AGENT'] ?? '');
        if ($useragent !== '') {
            foreach (config::lines('uatokens') as $token) {
                if (stripos($useragent, $token) !== false) {
                    return ['signal' => signals::AGENT_UA, 'client' => self::shorten($token)];
                }
            }
        }

        $denied = config::lines('ipdeny');
        if ($denied && address_in_subnet($ip ?? getremoteaddr(), implode(',', $denied))) {
            return ['signal' => signals::AGENT_IP, 'client' => 'ipdeny'];
        }

        return null;
    }

    /**
     * Cut a client description to a safe stored length.
     *
     * @param string $value
     * @return string
     */
    private static function shorten(string $value): string {
        return \core_text::substr(clean_param($value, PARAM_NOTAGS), 0, 100);
    }
}
