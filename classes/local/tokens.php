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
 * Web-service and personal access token audit, purge and lookup.
 *
 * Purging matters because core reuses a user's existing token without re-checking the capability that was
 * needed to create it (core_external\util::generate_token_for_current_user()), so removing the capability
 * alone does not cut off a student who already holds a token.
 *
 * From Moodle 5.3 users can also create personal access tokens for the REST API (MDL-87706, capability
 * moodle/api:createtoken, table rest_api_tokens). They are not web-service tokens: core raises no event when
 * one is created, and they are used through the routing API, not a web-service server. Everything here that
 * touches them is guarded so the plugin still runs on Moodle 5.2, where neither the table nor the classes exist.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tokens {
    /** @var string Kind of a web-service token (table external_tokens). */
    public const KIND_WS = 'ws';

    /** @var string Kind of a personal access token (table rest_api_tokens, Moodle 5.3 and later). */
    public const KIND_PERSONAL = 'personal';

    /** @var string Core's personal access token table (Moodle 5.3 and later). */
    public const PERSONAL_TABLE = 'rest_api_tokens';

    /** @var string Prefix of a personal access token, as core\api\token_manager::TOKEN_PREFIX. */
    public const PERSONAL_PREFIX = 'pat_';

    /**
     * Whether this site has personal access tokens (Moodle 5.3 and later).
     *
     * @return bool
     */
    public static function personal_tokens_supported(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::PERSONAL_TABLE);
    }

    /**
     * Tokens that users who are not exempt anywhere created for themselves (the Moodle app, AI tools, scripts).
     *
     * Web-service tokens an administrator created for a user (t.creatorid <> t.userid), typically for an
     * integration, are left alone. Personal access tokens are always created by their owner; only those that
     * still work (not revoked, not expired) are listed.
     *
     * @return \stdClass[] token rows (kind, id, userid, name, timecreated, lastaccess, servicename,
     *     serviceshortname and the owner's name fields), most recently used first
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
        $rows = [];
        foreach ($DB->get_records_sql($sql) as $token) {
            $token->kind = self::KIND_WS;
            $rows[] = $token;
        }

        if (self::personal_tokens_supported()) {
            $sql = "SELECT t.id, t.userid, t.name, t.timecreated, t.lastaccessed AS lastaccess, t.expirytime,
                           $userfields
                      FROM {" . self::PERSONAL_TABLE . "} t
                      JOIN {user} u ON u.id = t.userid
                     WHERE u.deleted = 0 AND t.revoked = 0 AND (t.expirytime IS NULL OR t.expirytime > :now)";
            foreach ($DB->get_records_sql($sql, ['now' => time()]) as $token) {
                $token->kind = self::KIND_PERSONAL;
                $token->servicename = null;
                $token->serviceshortname = null;
                $rows[] = $token;
            }
            usort($rows, fn($a, $b) => [(int) $b->lastaccess, (int) $b->timecreated, (int) $b->id]
                <=> [(int) $a->lastaccess, (int) $a->timecreated, (int) $a->id]);
        }

        $out = [];
        $exempt = [];
        foreach ($rows as $token) {
            $exempt[$token->userid] = $exempt[$token->userid] ?? exemption::is_exempt_anywhere((int) $token->userid);
            if (!$exempt[$token->userid]) {
                $out[] = $token;
            }
        }
        return $out;
    }

    /**
     * Delete the tokens of every user who is not exempt anywhere: web-service and personal access tokens.
     *
     * @return int number of tokens deleted
     */
    public static function purge_nonexempt(): int {
        global $DB;
        $ids = [self::KIND_WS => [], self::KIND_PERSONAL => []];
        foreach (self::nonexempt_tokens() as $token) {
            $ids[$token->kind][] = (int) $token->id;
        }
        $tables = [self::KIND_WS => 'external_tokens', self::KIND_PERSONAL => self::PERSONAL_TABLE];
        foreach ($ids as $kind => $list) {
            foreach (array_chunk($list, 500) as $chunk) {
                $DB->delete_records_list($tables[$kind], 'id', $chunk);
            }
        }
        return count($ids[self::KIND_WS]) + count($ids[self::KIND_PERSONAL]);
    }

    /**
     * The working personal access token a bearer credential stands for, validated by core.
     *
     * Core checks the secret, expiry and revocation, so a token id guessed or taken from a forged credential is
     * never returned.
     *
     * @param string $credential the token as sent after "Bearer "
     * @return \stdClass|null object with id and userid; null when the site has no personal access tokens or the
     *     credential is not a working one
     */
    public static function personal_token(string $credential): ?\stdClass {
        $repository = \core\api\repository\api_token_repository::class;
        if (!str_starts_with($credential, self::PERSONAL_PREFIX) || !class_exists($repository)) {
            return null;
        }
        try {
            $entity = \core\di::get($repository)->get_from_token($credential);
        } catch (\Throwable $e) {
            // Invalid, expired, revoked or unknown: core refuses it itself.
            return null;
        }
        return (object) ['id' => (int) $entity->get_id(), 'userid' => (int) $entity->get_userid()];
    }

    /**
     * Delete one personal access token.
     *
     * @param int $id rest_api_tokens id
     * @return void
     */
    public static function delete_personal_token(int $id): void {
        global $DB;
        if (self::personal_tokens_supported()) {
            $DB->delete_records(self::PERSONAL_TABLE, ['id' => $id]);
        }
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
