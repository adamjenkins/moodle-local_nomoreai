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
 * Teacher report: AI agent signals in a course.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nomoreai\local\report_helper;
use local_nomoreai\local\signals;

$id = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/nomoreai:viewreport', $context);

$url = new moodle_url('/local/nomoreai/report.php', ['id' => $course->id]);
if ($cmid) {
    $url->param('cmid', $cmid);
}
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report', 'local_nomoreai'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$params = ['courseid' => $course->id];
$where = 'courseid = :courseid';
if ($cmid) {
    $where .= ' AND cmid = :cmid';
    $params['cmid'] = $cmid;
}
$rows = $DB->get_records_sql(
    "SELECT " . $DB->sql_concat_join("'-'", ['userid', 'cmid', 'signaltype']) . " AS rowkey,
            userid, cmid, signaltype, COUNT(1) AS n, MAX(timecreated) AS lastseen
       FROM {" . signals::TABLE . "}
      WHERE $where
   GROUP BY userid, cmid, signaltype
   ORDER BY userid, cmid",
    $params
);

// One table row per student and activity.
$grouped = [];
foreach ($rows as $row) {
    $key = $row->userid . '-' . $row->cmid;
    $grouped[$key] = $grouped[$key] ?? ['userid' => (int) $row->userid, 'cmid' => (int) $row->cmid, 'signals' => [],
        'lastseen' => 0];
    $grouped[$key]['signals'][$row->signaltype] = (int) $row->n;
    $grouped[$key]['lastseen'] = max($grouped[$key]['lastseen'], (int) $row->lastseen);
}

$userids = array_unique(array_column($grouped, 'userid'));
$users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];
$modinfo = get_fast_modinfo($course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'local_nomoreai'));
echo $OUTPUT->notification(
    get_string('indicatorsnotproof', 'local_nomoreai'),
    \core\output\notification::NOTIFY_WARNING,
    false
);
echo report_helper::mode_notice();

// Activity filter.
$options = [0 => get_string('allactivities', 'local_nomoreai')];
foreach (
    $DB->get_fieldset_sql(
        "SELECT DISTINCT cmid FROM {" . signals::TABLE . "} WHERE courseid = ? AND cmid > 0",
        [$course->id]
    ) as $optioncmid
) {
    $options[$optioncmid] = report_helper::activity_name($modinfo, (int) $optioncmid);
}
echo $OUTPUT->single_select(
    new moodle_url('/local/nomoreai/report.php', ['id' => $course->id]),
    'cmid',
    $options,
    $cmid,
    null
);

if (!$grouped) {
    echo $OUTPUT->notification(get_string('nosignals', 'local_nomoreai'), \core\output\notification::NOTIFY_INFO, false);
} else {
    $table = new html_table();
    $table->head = [get_string('student', 'local_nomoreai'), get_string('activity'), get_string('signals', 'local_nomoreai'),
        get_string('lastseen', 'local_nomoreai')];
    $table->attributes['class'] = 'generaltable local-nomoreai-report';
    foreach ($grouped as $item) {
        $user = $users[$item['userid']] ?? null;
        $name = $user ? s(fullname($user)) : s(get_string('notloggedin', 'local_nomoreai'));
        if ($user) {
            $name = html_writer::link(new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]), $name);
        }
        $table->data[] = [
            $name,
            s(report_helper::activity_name($modinfo, $item['cmid'])),
            report_helper::signal_list($item['signals']),
            userdate($item['lastseen']),
        ];
    }
    echo html_writer::table($table);
}

echo report_helper::legend();
echo $OUTPUT->footer();
