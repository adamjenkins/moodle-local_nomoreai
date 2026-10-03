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

namespace local_nomoreai\task;

/**
 * Deletes non-exempt users' web-service tokens, queued when Block tokens comes into force.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_tokens extends \core\task\adhoc_task {
    /**
     * Run.
     *
     * @return void
     */
    public function execute(): void {
        $count = \local_nomoreai\local\tokens::purge_nonexempt();
        mtrace("local_nomoreai: deleted $count web service tokens of non-exempt users.");
    }
}
