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
 * Admin settings for local_nomoreai.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_nomoreai\local\config;

// Registered outside $hassiteconfig so that holders of local/nomoreai:viewdashboard who are not site admins
// can reach them.
$ADMIN->add('reports', new admin_externalpage(
    'local_nomoreai_dashboard',
    new lang_string('dashboard', 'local_nomoreai'),
    new moodle_url('/local/nomoreai/dashboard.php'),
    'local/nomoreai:viewdashboard'
));
$ADMIN->add('localplugins', new admin_externalpage(
    'local_nomoreai_tokens',
    new lang_string('tokenaudit', 'local_nomoreai'),
    new moodle_url('/local/nomoreai/tokens.php'),
    'local/nomoreai:viewdashboard'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nomoreai', new lang_string('pluginname', 'local_nomoreai'));
    $d = config::defaults();
    $purge = 'local_nomoreai\local\tokens::queue_purge_if_blocking';

    $settings->add(new admin_setting_heading(
        'local_nomoreai/intro',
        '',
        new lang_string('intro', 'local_nomoreai')
    ));

    $setting = new admin_setting_configselect(
        'local_nomoreai/mode',
        new lang_string('mode', 'local_nomoreai'),
        new lang_string('mode_desc', 'local_nomoreai'),
        $d['mode'],
        [
            config::MODE_OFF => new lang_string('mode_off', 'local_nomoreai'),
            config::MODE_DETECT => new lang_string('mode_detect', 'local_nomoreai'),
            config::MODE_ENFORCE => new lang_string('mode_enforce', 'local_nomoreai'),
        ]
    );
    $setting->set_updatedcallback($purge);
    $settings->add($setting);

    $settings->add(new admin_setting_configtextarea(
        'local_nomoreai/trustednetworks',
        new lang_string('trustednetworks', 'local_nomoreai'),
        new lang_string('trustednetworks_desc', 'local_nomoreai'),
        $d['trustednetworks'],
        PARAM_RAW_TRIMMED,
        40,
        4
    ));

    // Web services and tokens.
    $settings->add(new admin_setting_heading(
        'local_nomoreai/wsheading',
        new lang_string('wsheading', 'local_nomoreai'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_nomoreai/wslockdown',
        new lang_string('wslockdown', 'local_nomoreai'),
        new lang_string('wslockdown_desc', 'local_nomoreai'),
        $d['wslockdown']
    ));
    $setting = new admin_setting_configcheckbox(
        'local_nomoreai/blocktokens',
        new lang_string('blocktokens', 'local_nomoreai'),
        new lang_string('blocktokens_desc', 'local_nomoreai'),
        $d['blocktokens']
    );
    $setting->set_updatedcallback($purge);
    $settings->add($setting);

    // Agents that identify themselves.
    $settings->add(new admin_setting_heading(
        'local_nomoreai/agentheading',
        new lang_string('agentheading', 'local_nomoreai'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_nomoreai/agentrefusal',
        new lang_string('agentrefusal', 'local_nomoreai'),
        new lang_string('agentrefusal_desc', 'local_nomoreai'),
        $d['agentrefusal']
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_nomoreai/uatokens',
        new lang_string('uatokens', 'local_nomoreai'),
        new lang_string('uatokens_desc', 'local_nomoreai'),
        $d['uatokens'],
        PARAM_RAW_TRIMMED,
        40,
        5
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_nomoreai/ipdeny',
        new lang_string('ipdeny', 'local_nomoreai'),
        new lang_string('ipdeny_desc', 'local_nomoreai'),
        $d['ipdeny'],
        PARAM_RAW_TRIMMED,
        40,
        4
    ));

    // Notice.
    $settings->add(new admin_setting_heading(
        'local_nomoreai/noticeheading',
        new lang_string('noticeheading', 'local_nomoreai'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_nomoreai/notice',
        new lang_string('notice', 'local_nomoreai'),
        new lang_string('notice_desc', 'local_nomoreai'),
        $d['notice']
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_nomoreai/noticetext',
        new lang_string('noticetext', 'local_nomoreai'),
        new lang_string('noticetext_desc', 'local_nomoreai'),
        $d['noticetext'],
        PARAM_TEXT,
        60,
        3
    ));

    // Detection.
    $settings->add(new admin_setting_heading(
        'local_nomoreai/detectheading',
        new lang_string('detectheading', 'local_nomoreai'),
        new lang_string('detectheading_desc', 'local_nomoreai')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_nomoreai/monitoring',
        new lang_string('monitoring', 'local_nomoreai'),
        new lang_string('monitoring_desc', 'local_nomoreai'),
        $d['monitoring']
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_nomoreai/artifacts',
        new lang_string('artifacts', 'local_nomoreai'),
        new lang_string('artifacts_desc', 'local_nomoreai'),
        $d['artifacts'],
        PARAM_RAW_TRIMMED,
        60,
        4
    ));
    $settings->add(new admin_setting_configtext(
        'local_nomoreai/fastfraction',
        new lang_string('fastfraction', 'local_nomoreai'),
        new lang_string('fastfraction_desc', 'local_nomoreai'),
        $d['fastfraction'],
        PARAM_FLOAT,
        6
    ));
    $settings->add(new admin_setting_configtext(
        'local_nomoreai/retentiondays',
        new lang_string('retentiondays', 'local_nomoreai'),
        new lang_string('retentiondays_desc', 'local_nomoreai'),
        $d['retentiondays'],
        PARAM_INT,
        6
    ));

    $ADMIN->add('localplugins', $settings);
}
