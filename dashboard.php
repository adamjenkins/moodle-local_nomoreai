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
 * Admin dashboard: how common is AI agent use on this site? Tabbed reports by user, activity, signal type and
 * identified agent.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nomoreai\local\report_helper;
use local_nomoreai\local\reports;

$tab = reports::valid_tab(optional_param('tab', reports::TAB_USERS, PARAM_ALPHA));

admin_externalpage_setup('local_nomoreai_dashboard', '', ['tab' => $tab]);

$reports = new reports();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'local_nomoreai'));
echo $OUTPUT->notification(get_string('dashboard_intro', 'local_nomoreai'), \core\output\notification::NOTIFY_INFO, false);
echo report_helper::mode_notice();
echo $reports->tabtree(new moodle_url('/local/nomoreai/dashboard.php'), $tab);
echo $reports->render($tab);
echo html_writer::div(html_writer::link(
    new moodle_url('/local/nomoreai/tokens.php'),
    get_string('tokenaudit', 'local_nomoreai')
), 'mb-3');
echo report_helper::legend();
echo $OUTPUT->footer();
