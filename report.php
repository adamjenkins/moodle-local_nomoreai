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
 * Teacher report: AI agent signals in a course, in tabs by user, activity, signal type and identified agent.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nomoreai\local\report_helper;
use local_nomoreai\local\reports;
use local_nomoreai\local\signals;

$id = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$tab = reports::valid_tab(optional_param('tab', reports::TAB_USERS, PARAM_ALPHA));

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/nomoreai:viewreport', $context);

$modinfo = get_fast_modinfo($course);
if (
    $cmid && !isset($modinfo->get_cms()[$cmid]) &&
        !$DB->record_exists(signals::TABLE, ['courseid' => $course->id, 'cmid' => $cmid])
) {
    // An activity of another course: ignore it rather than report on it.
    $cmid = 0;
}

$baseurl = new moodle_url('/local/nomoreai/report.php', ['id' => $course->id]);
if ($cmid) {
    $baseurl->param('cmid', $cmid);
}
$PAGE->set_url(new moodle_url($baseurl, ['tab' => $tab]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report', 'local_nomoreai'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$reports = new reports($course, $cmid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'local_nomoreai'));
echo $OUTPUT->notification(
    get_string('indicatorsnotproof', 'local_nomoreai'),
    \core\output\notification::NOTIFY_WARNING,
    false
);
echo report_helper::mode_notice();

// Activity filter, kept across tabs.
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
    new moodle_url('/local/nomoreai/report.php', ['id' => $course->id, 'tab' => $tab]),
    'cmid',
    $options,
    $cmid,
    null
);

echo $reports->tabtree($baseurl, $tab);
echo $reports->render($tab);
echo report_helper::legend();
echo $OUTPUT->footer();
