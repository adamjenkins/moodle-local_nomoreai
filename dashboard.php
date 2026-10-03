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
 * Admin dashboard: how common is AI agent use on this site?
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nomoreai\local\report_helper;
use local_nomoreai\local\signals;

admin_externalpage_setup('local_nomoreai_dashboard');

$table = '{' . signals::TABLE . '}';
$weeks = 12;
$since = time() - $weeks * WEEKSECS;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'local_nomoreai'));
echo $OUTPUT->notification(get_string('dashboard_intro', 'local_nomoreai'), \core\output\notification::NOTIFY_INFO, false);
echo report_helper::mode_notice();

// Weekly counts per signal type, and distinct users per week.
$rows = $DB->get_records_sql(
    "SELECT " . $DB->sql_concat_join("'-'", ['week', 'signaltype']) . " AS rowkey, week, signaltype, n, users
       FROM (SELECT FLOOR(timecreated / " . WEEKSECS . ") AS week, signaltype, COUNT(1) AS n,
                    COUNT(DISTINCT userid) AS users
               FROM $table
              WHERE timecreated >= :since
           GROUP BY FLOOR(timecreated / " . WEEKSECS . "), signaltype) w
   ORDER BY week DESC",
    ['since' => $since]
);
$weekusers = $DB->get_records_sql_menu(
    "SELECT FLOOR(timecreated / " . WEEKSECS . ") AS week, COUNT(DISTINCT userid)
       FROM $table
      WHERE timecreated >= :since AND userid > 0
   GROUP BY FLOOR(timecreated / " . WEEKSECS . ")",
    ['since' => $since]
);

echo $OUTPUT->heading(get_string('dashboard_weekly', 'local_nomoreai'), 3);
if (!$rows) {
    echo $OUTPUT->notification(get_string('nosignals', 'local_nomoreai'), \core\output\notification::NOTIFY_INFO, false);
} else {
    $byweek = [];
    foreach ($rows as $row) {
        $byweek[(int) $row->week][$row->signaltype] = (int) $row->n;
    }
    krsort($byweek);
    $types = array_values(array_filter(signals::types(), fn($t) => array_filter(array_column($byweek, $t))));
    $t = new html_table();
    $t->head = array_merge(
        [get_string('weekstarting', 'local_nomoreai'), get_string('distinctusers', 'local_nomoreai')],
        array_map(fn($type) => s(get_string('signal_' . $type, 'local_nomoreai')), $types)
    );
    foreach ($byweek as $week => $counts) {
        $line = [userdate($week * WEEKSECS, get_string('strftimedate', 'langconfig')), (int) ($weekusers[$week] ?? 0)];
        foreach ($types as $type) {
            $line[] = (int) ($counts[$type] ?? 0);
        }
        $t->data[] = $line;
    }
    echo html_writer::table($t);
}

// Top activities.
$top = $DB->get_records_sql(
    "SELECT cmid, courseid, COUNT(1) AS n, COUNT(DISTINCT userid) AS users
       FROM $table
      WHERE timecreated >= :since AND cmid > 0
   GROUP BY cmid, courseid
   ORDER BY COUNT(1) DESC",
    ['since' => $since],
    0,
    10
);
if ($top) {
    echo $OUTPUT->heading(get_string('dashboard_topactivities', 'local_nomoreai'), 3);
    $t = new html_table();
    $t->head = [get_string('activity'), get_string('course'), get_string('signals', 'local_nomoreai'),
        get_string('distinctusers', 'local_nomoreai')];
    foreach ($top as $row) {
        $course = $DB->get_record('course', ['id' => $row->courseid]);
        if (!$course) {
            continue;
        }
        $modinfo = get_fast_modinfo($course);
        $coursename = format_string($course->shortname, true, ['context' => context_course::instance($course->id)]);
        $t->data[] = [
            s(report_helper::activity_name($modinfo, (int) $row->cmid)),
            html_writer::link(
                new moodle_url('/local/nomoreai/report.php', ['id' => $course->id, 'cmid' => $row->cmid]),
                $coursename
            ),
            (int) $row->n,
            (int) $row->users,
        ];
    }
    echo html_writer::table($t);
}

// Which agents identified themselves.
$clients = [];
$rs = $DB->get_recordset_select(
    signals::TABLE,
    'timecreated >= :since AND signaltype IN (:a, :b)',
    ['since' => $since, 'a' => signals::SIGNED_AGENT, 'b' => signals::AGENT_UA],
    '',
    'id, signaltype, detail',
    0,
    5000
);
foreach ($rs as $row) {
    $detail = json_decode((string) $row->detail, true);
    $client = is_array($detail) && isset($detail['client']) ? (string) $detail['client'] : '?';
    $key = get_string('signal_' . $row->signaltype, 'local_nomoreai') . ': ' . $client;
    $clients[$key] = ($clients[$key] ?? 0) + 1;
}
$rs->close();
if ($clients) {
    arsort($clients);
    echo $OUTPUT->heading(get_string('dashboard_clients', 'local_nomoreai'), 3);
    $t = new html_table();
    $t->head = [get_string('client', 'local_nomoreai'), get_string('signals', 'local_nomoreai')];
    foreach ($clients as $client => $n) {
        // The client value comes from request headers: attacker-controlled, so escaped here.
        $t->data[] = [s($client), (int) $n];
    }
    echo html_writer::table($t);
}

echo html_writer::div(html_writer::link(
    new moodle_url('/local/nomoreai/tokens.php'),
    get_string('tokenaudit', 'local_nomoreai')
), 'mb-3');
echo report_helper::legend();
echo $OUTPUT->footer();
