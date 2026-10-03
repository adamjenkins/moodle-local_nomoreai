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
 * Token audit: web-service tokens held by users who are not exempt, with a purge action.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nomoreai\local\tokens;

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('local_nomoreai_tokens');
$pageurl = new moodle_url('/local/nomoreai/tokens.php');

if ($action === 'purge') {
    if ($confirm && confirm_sesskey()) {
        $count = tokens::purge_nonexempt();
        redirect(
            $pageurl,
            get_string('tokenspurged', 'local_nomoreai', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('purgeconfirm', 'local_nomoreai'),
        new moodle_url($pageurl, ['action' => 'purge', 'confirm' => 1, 'sesskey' => sesskey()]),
        $pageurl
    );
    echo $OUTPUT->footer();
    die();
}

$list = tokens::nonexempt_tokens();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tokenaudit', 'local_nomoreai'));
echo $OUTPUT->notification(get_string('tokenaudit_intro', 'local_nomoreai'), \core\output\notification::NOTIFY_INFO, false);

if (!$list) {
    echo $OUTPUT->notification(get_string('notokens', 'local_nomoreai'), \core\output\notification::NOTIFY_SUCCESS, false);
} else {
    $table = new html_table();
    $table->head = [get_string('user'), get_string('service', 'webservice'), get_string('tokenname', 'local_nomoreai'),
        get_string('tokencreated', 'local_nomoreai'), get_string('lastaccess')];
    foreach ($list as $token) {
        $table->data[] = [
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $token->userid]), s(fullname($token))),
            s((string) ($token->servicename ?? $token->serviceshortname ?? '')),
            s((string) $token->name),
            userdate($token->timecreated),
            $token->lastaccess ? userdate($token->lastaccess) : get_string('never'),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'purge']),
        get_string('purgetokens', 'local_nomoreai'),
        'get'
    );
}

echo $OUTPUT->footer();
