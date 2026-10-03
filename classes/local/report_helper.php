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

use html_writer;

/**
 * Shared rendering for the teacher report and the admin dashboard.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_helper {
    /**
     * An activity's display name, or "Course" for course-level signals.
     *
     * @param \course_modinfo $modinfo
     * @param int $cmid
     * @return string plain text
     */
    public static function activity_name(\course_modinfo $modinfo, int $cmid): string {
        if (!$cmid) {
            return get_string('course');
        }
        $cms = $modinfo->get_cms();
        return isset($cms[$cmid]) ? $cms[$cmid]->get_formatted_name(['escape' => false]) :
            get_string('deletedactivity', 'local_nomoreai');
    }

    /**
     * Signal counts as a list.
     *
     * @param array $counts signal type => count
     * @return string HTML
     */
    public static function signal_list(array $counts): string {
        $items = [];
        foreach (signals::types() as $type) {
            if (!empty($counts[$type])) {
                $items[] = s(get_string('signal_' . $type, 'local_nomoreai')) . ' × ' . (int) $counts[$type];
            }
        }
        return html_writer::alist($items, ['class' => 'list-unstyled mb-0']);
    }

    /**
     * What each signal means and what else can cause it.
     *
     * @return string HTML
     */
    public static function legend(): string {
        $html = html_writer::tag('h3', s(get_string('legend', 'local_nomoreai')));
        $items = '';
        foreach (signals::types() as $type) {
            $items .= html_writer::tag('dt', s(get_string('signal_' . $type, 'local_nomoreai')));
            $items .= html_writer::tag('dd', s(get_string('signal_' . $type . '_help', 'local_nomoreai')));
        }
        return $html . html_writer::tag('dl', $items, ['class' => 'local-nomoreai-legend']);
    }

    /**
     * A notice saying what the current mode does.
     *
     * @return string HTML
     */
    public static function mode_notice(): string {
        global $OUTPUT;
        $mode = config::mode();
        return $OUTPUT->notification(get_string(
            'currentmode',
            'local_nomoreai',
            get_string('mode_' . $mode, 'local_nomoreai')
        ), \core\output\notification::NOTIFY_INFO, false);
    }
}
