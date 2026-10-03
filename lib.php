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
 * Legacy callbacks for local_nomoreai (those core has no hook for yet).
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_nomoreai\local\enforcer;
use local_nomoreai\local\webonly;

/**
 * Note each external function before it runs, for the web-service lockdown.
 *
 * Called by core_external\external_api::call_external_function() and webservice_server::execute().
 *
 * @param stdClass $function external function info
 * @param array $params
 * @return false never overrides the call; refusals are thrown
 */
function local_nomoreai_override_webservice_execution($function, $params) {
    enforcer::webservice_call($function);
    return false;
}

/**
 * Decide refusals once the course and activity of a request are known.
 *
 * @param stdClass|int|null $courseorid
 * @param bool $autologinguest
 * @param stdClass|cm_info|null $cm
 * @param bool $setwantsurltome
 * @param bool $preventredirect
 * @return void
 */
function local_nomoreai_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect) {
    enforcer::after_require_login($courseorid, $cm);
}

/**
 * Add the "Web browser only" setting to every activity's settings form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 * @return void
 */
function local_nomoreai_coursemodule_standard_elements($formwrapper, $mform) {
    $mform->addElement('header', 'local_nomoreai_header', get_string('activitysettings', 'local_nomoreai'));
    $mform->addElement(
        'advcheckbox',
        'local_nomoreai_webonly',
        get_string('webonly', 'local_nomoreai'),
        get_string('webonly_label', 'local_nomoreai')
    );
    $mform->setType('local_nomoreai_webonly', PARAM_BOOL);
    $mform->addHelpButton('local_nomoreai_webonly', 'webonly', 'local_nomoreai');
    $cm = $formwrapper->get_coursemodule();
    $mform->setDefault('local_nomoreai_webonly', $cm ? (int) webonly::is_webonly((int) $cm->id) : 0);
}

/**
 * Save the "Web browser only" setting.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function local_nomoreai_coursemodule_edit_post_actions($data, $course) {
    if (isset($data->local_nomoreai_webonly) && !empty($data->coursemodule)) {
        webonly::set((int) $data->coursemodule, !empty($data->local_nomoreai_webonly));
    }
    return $data;
}

/**
 * Add the "AI agent signals" report to the course's reports.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 * @return void
 */
function local_nomoreai_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/nomoreai:viewreport', $context)) {
        $url = new moodle_url('/local/nomoreai/report.php', ['id' => $course->id]);
        $navigation->add(
            get_string('report', 'local_nomoreai'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_nomoreai_report',
            new pix_icon('i/report', '')
        );
    }
}
