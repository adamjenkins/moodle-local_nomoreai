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
 * Per-activity "Web browser only" setting.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webonly {
    /** @var string Table name. */
    public const TABLE = 'local_nomoreai_cm';

    /**
     * Whether the activity is web browser only.
     *
     * @param int $cmid
     * @return bool
     */
    public static function is_webonly(int $cmid): bool {
        global $DB;
        return (bool) $DB->get_field(self::TABLE, 'webonly', ['cmid' => $cmid]);
    }

    /**
     * Save the setting for an activity.
     *
     * @param int $cmid
     * @param bool $webonly
     * @return void
     */
    public static function set(int $cmid, bool $webonly): void {
        global $DB;
        if ($id = $DB->get_field(self::TABLE, 'id', ['cmid' => $cmid])) {
            $DB->set_field(self::TABLE, 'webonly', (int) $webonly, ['id' => $id]);
        } else if ($webonly) {
            $DB->insert_record(self::TABLE, (object) ['cmid' => $cmid, 'webonly' => 1]);
        }
    }

    /**
     * Forget an activity's setting.
     *
     * @param int $cmid
     * @return void
     */
    public static function delete(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }
}
