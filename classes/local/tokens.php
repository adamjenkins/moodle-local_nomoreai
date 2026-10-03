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
 * Web-service token audit and purge.
 *
 * Purging matters because core reuses a user's existing token without re-checking the capability that was
 * needed to create it (core_external\util::generate_token_for_current_user()), so removing the capability
 * alone does not cut off a student who already holds a token.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tokens {
    /**
     * Tokens that users who are not exempt anywhere created for themselves (the Moodle app, AI tools, scripts).
     *
     * Tokens an administrator created for a user (t.creatorid <> t.userid), typically for an integration, are
     * left alone.
     *
     * @return \stdClass[] token rows with service name and the owner's name fields
     */
    public static function nonexempt_tokens(): array {
        global $DB;
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT t.id, t.userid, t.name, t.timecreated, t.lastaccess, t.validuntil, t.creatorid,
                       s.name AS servicename, s.shortname AS serviceshortname, $userfields
                  FROM {external_tokens} t
                  JOIN {user} u ON u.id = t.userid
             LEFT JOIN {external_services} s ON s.id = t.externalserviceid
                 WHERE u.deleted = 0 AND t.creatorid = t.userid
              ORDER BY t.lastaccess DESC, t.id DESC";
        $out = [];
        $exempt = [];
        foreach ($DB->get_records_sql($sql) as $token) {
            $exempt[$token->userid] = $exempt[$token->userid] ?? exemption::is_exempt_anywhere((int) $token->userid);
            if (!$exempt[$token->userid]) {
                $out[] = $token;
            }
        }
        return $out;
    }

    /**
     * Delete the tokens of every user who is not exempt anywhere.
     *
     * @return int number of tokens deleted
     */
    public static function purge_nonexempt(): int {
        global $DB;
        $ids = array_map(fn($t) => (int) $t->id, self::nonexempt_tokens());
        foreach (array_chunk($ids, 500) as $chunk) {
            $DB->delete_records_list('external_tokens', 'id', $chunk);
        }
        return count($ids);
    }

    /**
     * Queue a purge if Block tokens is in force (admin setting callback).
     *
     * @return void
     */
    public static function queue_purge_if_blocking(): void {
        if (config::enforcing() && config::enabled('blocktokens')) {
            \core\task\manager::queue_adhoc_task(new \local_nomoreai\task\purge_tokens(), true);
        }
    }
}
