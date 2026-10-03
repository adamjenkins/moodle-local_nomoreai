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

/**
 * Restore support for local_nomoreai.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores each activity's "Web browser only" setting.
 *
 * The element is read before the new course module id is final, so it is stashed and written in
 * after_restore_module().
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_nomoreai_plugin extends restore_local_plugin {
    /** @var bool|null Setting read from the backup. */
    protected $webonly = null;

    /**
     * Paths handled at module level.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [new restore_path_element('nomoreai', $this->get_pathfor('/nomoreai'))];
    }

    /**
     * Stash the setting.
     *
     * @param array|\stdClass $data
     * @return void
     */
    public function process_nomoreai($data) {
        $data = (object) $data;
        $this->webonly = !empty($data->webonly);
    }

    /**
     * Write the setting for the restored activity.
     *
     * @return void
     */
    public function after_restore_module() {
        if ($this->webonly !== null) {
            \local_nomoreai\local\webonly::set((int) $this->task->get_moduleid(), $this->webonly);
        }
    }
}
