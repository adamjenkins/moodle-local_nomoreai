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

namespace local_nomoreai\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_nomoreai\local\signals;

/**
 * Privacy provider: the plugin stores recorded signals about users.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(signals::TABLE, [
            'userid' => 'privacy:metadata:signal:userid',
            'contextid' => 'privacy:metadata:signal:contextid',
            'courseid' => 'privacy:metadata:signal:courseid',
            'cmid' => 'privacy:metadata:signal:cmid',
            'signaltype' => 'privacy:metadata:signal:signaltype',
            'mode' => 'privacy:metadata:signal:mode',
            'pageview' => 'privacy:metadata:signal:pageview',
            'detail' => 'privacy:metadata:signal:detail',
            'outcome' => 'privacy:metadata:signal:outcome',
            'origin' => 'privacy:metadata:signal:origin',
            'timecreated' => 'privacy:metadata:signal:timecreated',
        ], 'privacy:metadata:signal');
        return $collection;
    }

    /**
     * Contexts holding the user's signals.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT contextid FROM {' . signals::TABLE . '} WHERE userid = :userid',
            ['userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with signals in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {' . signals::TABLE . '} WHERE contextid = :contextid',
            ['contextid' => $userlist->get_context()->id]
        );
    }

    /**
     * Export the user's signals.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $rows = $DB->get_records(signals::TABLE, ['userid' => $userid, 'contextid' => $context->id], 'timecreated, id');
            if (!$rows) {
                continue;
            }
            $data = [];
            foreach ($rows as $row) {
                $data[] = (object) [
                    'signal' => get_string('signal_' . $row->signaltype, 'local_nomoreai'),
                    'detail' => $row->detail,
                    'mode' => $row->mode,
                    'outcome' => $row->outcome,
                    'origin' => $row->origin,
                    'timecreated' => transform::datetime($row->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_nomoreai')],
                (object) ['signals' => $data]
            );
        }
    }

    /**
     * Delete all signals in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->delete_records(signals::TABLE, ['contextid' => $context->id]);
    }

    /**
     * Delete the user's signals in the given contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contextids() as $contextid) {
            $DB->delete_records(signals::TABLE, ['userid' => $userid, 'contextid' => $contextid]);
        }
    }

    /**
     * Delete the listed users' signals in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['contextid'] = $userlist->get_context()->id;
        $DB->delete_records_select(signals::TABLE, "contextid = :contextid AND userid $insql", $params);
    }
}
