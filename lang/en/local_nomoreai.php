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
 * English language strings for local_nomoreai.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activitysettings'] = 'AI agents';
$string['agentheading'] = 'Agents that identify themselves';
$string['agentrefusal'] = 'Refuse identified agents';
$string['agentrefusal_desc'] = 'Refuse requests to the login page, and to course and activity pages for users who are not exempt, from agents that identify themselves: requests signed with Web Bot Auth (a Signature-Agent header, used by signed cloud agents such as ChatGPT\'s), user agents containing one of the tokens below, and the IP addresses below. Agents that run inside a student\'s own browser do not identify themselves and are not caught by this.';
$string['agentsread'] = 'Only the latest {$a} identified-agent signals were read.';
$string['allactivities'] = 'All activities';
$string['alsocausedby'] = 'What it means and what else causes it';
$string['andmore'] = 'and {$a} more';
$string['artifacts'] = 'Agent page traces';
$string['artifacts_desc'] = 'One per line, as id|CSS selector, for elements that a known AI agent adds to pages. These change when vendors update their agents.';
$string['asusers'] = 'Acting as';
$string['blocktokens'] = 'Block tokens';
$string['blocktokens_desc'] = '<strong>Warning: the Moodle app stops working for every user who is not exempt.</strong> Users who are not exempt anywhere cannot create web service tokens, and any web-service call they make is refused. When this comes into force, their existing tokens are deleted. Use this only if students should not use the Moodle app at all. Accounts used by integrations (for example a student information system) must be exempt site-wide, through a role with local/nomoreai:exempt at system level, or their web-service calls are refused too. The purge only deletes tokens users created for themselves, not tokens an administrator issued. From Moodle 5.3 this also covers personal access tokens (which users can create when they have moodle/api:createtoken): a user who is not exempt cannot use one, and it is deleted when they try.';
$string['client'] = 'Agent';
$string['coursepages'] = 'Course pages';
$string['currentmode'] = 'Current mode: {$a}';
$string['dashboard'] = 'AI agent activity';
$string['dashboard_intro'] = 'How often AI agents are seen on this site. Run the site in Detect only mode for a while to find out how common agent use is before enforcing anything. No setting can stop an AI agent that operates a student\'s own computer: it leaves no signals, so low numbers here are not proof that agents are not being used.';
$string['dashboard_weekly'] = 'Signals per week (last 12 weeks)';
$string['deletedactivity'] = 'Deleted activity';
$string['deletedcourse'] = 'Deleted course';
$string['deleteduser'] = 'Deleted user';
$string['detectheading'] = 'Detection';
$string['detectheading_desc'] = 'These signals are only ever recorded for teachers, never used to refuse anything, because assistive technology, dictation, autofill and password managers can produce some of them too. Consult your data protection officer before enabling browser monitoring: interaction statistics may count as personal or even biometric data.';
$string['distinctusers'] = 'Users';
$string['fastfraction'] = 'Fast quiz attempt';
$string['fastfraction_desc'] = 'Record a signal when a quiz attempt takes less than this fraction of the median time of the other finished attempts (at least 5 are needed).';
$string['firstseen'] = 'First seen';
$string['indicatorsnotproof'] = 'These are indicators, not proof. They don\'t establish that an AI was used, and no signals doesn\'t mean no AI: an agent driving the student\'s own computer leaves none. Talk to the student.';
$string['intro'] = '<p>No More AI makes it harder for AI agents to do students\' work for them, and shows you how often they are seen. It cannot stop an agent that operates a student\'s own computer or browser like a person would; for high-stakes quizzes use Safe Exam Browser, a quiz password given out in the room or an IP address restriction, and consider assessment designs that do not rely on unsupervised online work.</p><p>Teachers, managers and course creators are exempt by default (capability <code>local/nomoreai:exempt</code>). To exempt a student, for example one using assistive technology, give them that capability through a role, site-wide or in one course or activity. <code>local/nomoreai:notmonitored</code> exempts a user from browser monitoring only.</p>';
$string['ipdeny'] = 'Agent IP addresses';
$string['ipdeny_desc'] = 'IP addresses or ranges, one per line, of AI agents\' servers. Some vendors publish them (for example OpenAI and Perplexity for their user-triggered fetchers); copy them here and keep them up to date.';
$string['lastseen'] = 'Last seen';
$string['legend'] = 'What the signals mean';
$string['loginpage'] = 'On the login page';
$string['mode'] = 'Mode';
$string['mode_desc'] = '<strong>Off</strong>: nothing is checked or recorded. <strong>Detect only</strong>: everything below is recorded, as "would have been refused" where applicable, but nothing is refused and no notice is shown to students, so you can measure how common agent use is. <strong>Enforce</strong>: agents that identify themselves and web-service submissions are refused, and the notice is shown.';
$string['mode_detect'] = 'Detect only';
$string['mode_enforce'] = 'Enforce';
$string['mode_off'] = 'Off';
$string['monitoring'] = 'Browser monitoring';
$string['monitoring_desc'] = 'On activity pages, count automation flags, traces of known agents in the page, text entered without key presses, clicks without pointer movement and large pastes, and record a signal when they look automated. Only counts are sent: never what was typed, key timings or pointer paths. While it runs, a sentence saying so is shown at the top of the page. Users with local/nomoreai:notmonitored are not monitored.';
$string['monitoringsentence'] = 'Interaction patterns on this page are recorded to help detect automated completion.';
$string['nomoreai:exempt'] = 'Exempt from AI agent checks';
$string['nomoreai:notmonitored'] = 'Exempt from AI agent browser monitoring';
$string['nomoreai:viewdashboard'] = 'View AI agent activity for the site and audit tokens';
$string['nomoreai:viewreport'] = 'View AI agent signals in a course';
$string['nosignals'] = 'No signals recorded.';
$string['notice'] = 'Show the notice';
$string['notice_desc'] = 'In Enforce mode, show the text below at the top of every activity page to users who are not exempt. AI agents read page text, and some stop when told to; it is visible to everyone, never hidden, so screen-reader users are not misled.';
$string['noticeheading'] = 'Notice to students';
$string['noticetext'] = 'Notice text';
$string['noticetext_desc'] = 'Plain text.';
$string['notloggedin'] = 'Not logged in';
$string['notokens'] = 'No user who lacks an exemption holds a web service token or a personal access token.';
$string['personalaccesstoken'] = 'Personal access token';
$string['pluginname'] = 'No More AI';
$string['privacy:metadata:signal'] = 'Signals that a request or page view looked like an AI agent\'s, recorded for teachers and administrators.';
$string['privacy:metadata:signal:cmid'] = 'The activity it was seen in.';
$string['privacy:metadata:signal:contextid'] = 'The context (activity, course or site) it was seen in.';
$string['privacy:metadata:signal:courseid'] = 'The course it was seen in.';
$string['privacy:metadata:signal:detail'] = 'Counts or values behind the signal, such as the agent name a request carried.';
$string['privacy:metadata:signal:mode'] = 'Whether the site was in Detect only or Enforce mode.';
$string['privacy:metadata:signal:origin'] = 'Whether it came from the web, the login page or a web service.';
$string['privacy:metadata:signal:outcome'] = 'Whether the request was refused or only recorded.';
$string['privacy:metadata:signal:pageview'] = 'A key used to record a signal only once per day or per hour.';
$string['privacy:metadata:signal:signaltype'] = 'The kind of signal.';
$string['privacy:metadata:signal:timecreated'] = 'When it was recorded.';
$string['privacy:metadata:signal:userid'] = 'The user the signal is about.';
$string['purgeconfirm'] = 'Delete every web service token and personal access token held by a user who is not exempt? Those users are logged out of the Moodle app and any connected tools, and must log in again (unless Block tokens is on).';
$string['purgetokens'] = 'Purge these tokens';
$string['refused'] = 'Automated agents can\'t be used to access this site as a student. If you think this is a mistake, contact your teacher or site administrator.';
$string['refusedcount'] = 'Refused';
$string['refusedtitle'] = 'Access refused';
$string['report'] = 'AI agent signals';
$string['retentiondays'] = 'Keep signals for (days)';
$string['retentiondays_desc'] = 'Signals older than this are deleted every night.';
$string['rowsshown'] = 'Showing the first {$a->shown} of {$a->total} rows.';
$string['signal'] = 'Signal';
$string['signal_agent_ip'] = 'Agent IP address';
$string['signal_agent_ip_help'] = 'The request came from an address on the agent IP list. Also caused by an out-of-date list, or a shared address such as a VPN.';
$string['signal_agent_ua'] = 'Agent user agent';
$string['signal_agent_ua_help'] = 'The browser identified itself as an automated agent (for example HeadlessChrome, which browser automation sends by default). Browsers used by people do not.';
$string['signal_answer_without_pageview'] = 'Quiz answered without viewing it';
$string['signal_answer_without_pageview_help'] = 'The attempt was finished without any attempt page being viewed: the answers were sent by a program. Also caused by log settings that do not record page views.';
$string['signal_clicks_without_pointer'] = 'Clicks without pointer movement';
$string['signal_clicks_without_pointer_help'] = 'Most mouse clicks came with no pointer movement before them. Keyboard clicks are not counted. Also produced by touch screens, some switch-access and voice-control software.';
$string['signal_fast_completion'] = 'Very fast quiz attempt';
$string['signal_fast_completion_help'] = 'The attempt took far less time than the class median. Also caused by well-prepared students, retakes and very short quizzes.';
$string['signal_large_paste'] = 'Large paste';
$string['signal_large_paste_help'] = 'A lot of text was pasted. Also caused by students writing elsewhere first, which is common and legitimate.';
$string['signal_signed_agent'] = 'Signed AI agent';
$string['signal_signed_agent_help'] = 'The request carried a Web Bot Auth signature (Signature-Agent header), which cloud AI agents such as ChatGPT\'s send to identify themselves. Browsers used by people do not send it.';
$string['signal_text_without_keys'] = 'Text arrived without typing';
$string['signal_text_without_keys_help'] = 'Text appeared in answer fields with no key presses. Also produced by dictation, autofill, password managers, translation tools and some assistive technology.';
$string['signal_token_blocked'] = 'Token use while Block tokens is on';
$string['signal_token_blocked_help'] = 'The user created or used a web-service token, or used a personal access token, while Block tokens was on: usually the Moodle app, otherwise an AI tool or script.';
$string['signal_vendor_artifact'] = 'AI agent traces in the page';
$string['signal_vendor_artifact_help'] = 'The page contained elements a known AI agent adds while it is working (see the Agent page traces setting).';
$string['signal_webdriver'] = 'Automated browser';
$string['signal_webdriver_help'] = 'The browser reported that it is controlled by automation (navigator.webdriver). Ordinary browsers, assistive technology included, do not.';
$string['signal_webonly_violation'] = 'Token use of a web-only activity';
$string['signal_webonly_violation_help'] = 'A Web browser only activity was accessed through a web-service token: the Moodle app, an AI tool or a script.';
$string['signal_ws_submission'] = 'Work submitted through a token';
$string['signal_ws_submission_help'] = 'Work was started, answered or submitted through a web-service token: the Moodle app, an AI tool connected to Moodle, or a script.';
$string['signals'] = 'Signals';
$string['sitelevel'] = 'Site (login page or web services)';
$string['tab_activities'] = 'Courses and activities';
$string['tab_activities_intro'] = 'The courses and activities where the most signals were seen.';
$string['tab_agents'] = 'Identified agents';
$string['tab_agents_intro'] = 'Agents that identified themselves: signed agents, agent user agents and listed IP addresses. Each row shows which users they acted as and where they were seen.';
$string['tab_types'] = 'Signal types';
$string['tab_types_intro'] = 'How often each kind of signal was seen, how many were refused, and what else can cause it.';
$string['tab_users'] = 'Users';
$string['tab_users_intro'] = 'Who triggered signals, in which course and activities. Names link to the user\'s profile in that course.';
$string['task_purgesignals'] = 'Delete expired AI agent signals';
$string['tokenaudit'] = 'Web service token audit';
$string['tokenaudit_intro'] = 'Web service tokens that users who are not exempt anywhere created for themselves (tokens an administrator issued are not listed), and, from Moodle 5.3, their personal access tokens that still work. The Moodle app, AI tools connected to Moodle and scripts all use these tokens. Moodle keeps using an existing token without re-checking the user\'s permissions, so removing a permission alone does not cut off someone who already holds a token: purge them.';
$string['tokencreated'] = 'Created';
$string['tokenname'] = 'Token name';
$string['tokenspurged'] = '{$a} tokens deleted.';
$string['trustednetworks'] = 'Trusted networks';
$string['trustednetworks_desc'] = 'IP addresses or ranges, one per line (for example 192.168.1.0/24 or 10.1.2.3), whose requests skip every check: monitoring tools, accessibility testing, staff networks.';
$string['uatokens'] = 'Agent user-agent tokens';
$string['uatokens_desc'] = 'One per line, matched anywhere in the User-Agent header, ignoring case. HeadlessChrome is sent by headless browser automation by default.';
$string['webonly'] = 'Web browser only';
$string['webonly_help'] = 'When the site\'s No More AI mode is Enforce, students cannot use this activity through any web-service token: no Moodle app, no AI tool connected to Moodle (such as an MCP server), no script. They can only use it in a web browser. Exempt users (teachers, and anyone given the exemption) are not affected. In Detect only mode, such use is recorded but allowed. This does not stop an AI agent that drives a web browser.';
$string['webonly_label'] = 'Refuse the Moodle app, web-service tokens and AI tools for this activity';
$string['weekstarting'] = 'Week';
$string['where'] = 'Where';
$string['wsheading'] = 'Web services, the Moodle app and AI tools';
$string['wslockdown'] = 'Block submissions';
$string['wslockdown_desc'] = 'Refuse every web-service call that does a student\'s work (starting, answering or submitting a quiz, saving or submitting an assignment, posting to a forum, and the same in lessons, H5P, workshops, glossaries, databases, wikis, choices, feedback and SCORM). Students can still use the Moodle app to read their courses, but must do their work in a web browser. AI tools connected to Moodle through a token (such as MCP servers) can no longer do the work.';
