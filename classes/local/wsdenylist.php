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
 * The external functions that do a student's work: refused through web-service tokens by "Block submissions".
 *
 * Built by reading the "write" functions of every core activity's db/services.php on Moodle 5.2 and keeping
 * those that start, answer, submit or post a student's own work. Teacher-only functions (grading, overrides,
 * question editing) are left out because staff are exempt anyway. tests/detection_test.php fails if a listed
 * function no longer exists.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wsdenylist {
    /**
     * The denied function names.
     *
     * @return string[]
     */
    public static function functions(): array {
        return [
            'core_completion_update_activity_completion_status_manually',
            'core_xapi_post_state',
            'core_xapi_statement_post',
            'mod_assign_copy_previous_attempt',
            'mod_assign_save_submission',
            'mod_assign_start_submission',
            'mod_assign_submit_for_grading',
            'mod_choice_submit_choice_response',
            'mod_data_add_entry',
            'mod_data_update_entry',
            'mod_feedback_launch_feedback',
            'mod_feedback_process_page',
            'mod_forum_add_discussion',
            'mod_forum_add_discussion_post',
            'mod_forum_update_discussion_post',
            'mod_glossary_add_entry',
            'mod_glossary_update_entry',
            'mod_lesson_finish_attempt',
            'mod_lesson_launch_attempt',
            'mod_lesson_process_page',
            'mod_quiz_process_attempt',
            'mod_quiz_save_attempt',
            'mod_quiz_start_attempt',
            'mod_scorm_insert_scorm_tracks',
            'mod_wiki_edit_page',
            'mod_wiki_new_page',
            'mod_workshop_add_submission',
            'mod_workshop_update_assessment',
            'mod_workshop_update_submission',
        ];
    }

    /**
     * Whether a function is denied.
     *
     * @param string $name
     * @return bool
     */
    public static function contains(string $name): bool {
        return in_array($name, self::functions(), true);
    }

    /**
     * Whether a denied function never calls validate_context(), so must be decided without an activity context.
     *
     * The xAPI functions (H5P answers) resolve the activity from the statement and only check
     * mod/h5pactivity:view (mod/h5pactivity/classes/xapi/handler.php); they never call require_login().
     *
     * @param string $name
     * @return bool
     */
    public static function without_context(string $name): bool {
        return in_array($name, ['core_xapi_post_state', 'core_xapi_statement_post'], true);
    }
}
