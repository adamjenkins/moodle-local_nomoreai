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

use html_table;
use html_writer;
use moodle_url;

/**
 * The tabbed signal reports, shared by the site dashboard and the course report.
 *
 * Each report is built for a scope: the whole site, or one course (optionally one activity in it). Every
 * user, course and activity in a report is linked: users to their profile in the course the signal was seen in
 * (their site profile for site-level signals such as the login page), courses to the course page and activities
 * to the activity.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports {
    /** @var string Tab: which users triggered signals. */
    public const TAB_USERS = 'users';
    /** @var string Tab: which courses and activities triggered the most signals. */
    public const TAB_ACTIVITIES = 'activities';
    /** @var string Tab: signal types. */
    public const TAB_TYPES = 'types';
    /** @var string Tab: agents that identified themselves. */
    public const TAB_AGENTS = 'agents';

    /** @var int Most rows shown in one table. */
    public const MAXROWS = 200;

    /** @var int Most agent signal rows read to build the agents report. */
    public const MAXAGENTROWS = 20000;

    /** @var \stdClass|null The course in scope, or null for the whole site. */
    protected $course;

    /** @var int Activity in scope (course reports only), 0 for all. */
    protected $cmid;

    /** @var array Course records by id, loaded on demand (false for a deleted course). */
    protected $courses = [];

    /** @var array User records by id, loaded on demand (false for a deleted user). */
    protected $users = [];

    /**
     * Constructor.
     *
     * @param \stdClass|null $course the course in scope, null for the whole site
     * @param int $cmid activity in scope, 0 for all (only with a course)
     */
    public function __construct(?\stdClass $course = null, int $cmid = 0) {
        $this->course = $course;
        $this->cmid = $course ? $cmid : 0;
        if ($course) {
            $this->courses[(int) $course->id] = $course;
        }
    }

    /**
     * The tab names, in display order.
     *
     * @return string[]
     */
    public static function tabs(): array {
        return [self::TAB_USERS, self::TAB_ACTIVITIES, self::TAB_TYPES, self::TAB_AGENTS];
    }

    /**
     * A tab name from a request parameter, defaulting to the first tab.
     *
     * @param string $tab
     * @return string
     */
    public static function valid_tab(string $tab): string {
        return in_array($tab, self::tabs(), true) ? $tab : self::TAB_USERS;
    }

    /**
     * The tab bar.
     *
     * @param moodle_url $baseurl page URL without the tab parameter
     * @param string $current current tab
     * @return string HTML
     */
    public function tabtree(moodle_url $baseurl, string $current): string {
        global $OUTPUT;
        $tabs = [];
        foreach (self::tabs() as $tab) {
            $tabs[] = new \tabobject(
                $tab,
                new moodle_url($baseurl, ['tab' => $tab]),
                get_string('tab_' . $tab, 'local_nomoreai')
            );
        }
        return $OUTPUT->tabtree($tabs, $current);
    }

    /**
     * Render one tab.
     *
     * @param string $tab
     * @return string HTML
     */
    public function render(string $tab): string {
        $html = html_writer::tag('p', s(get_string('tab_' . $tab . '_intro', 'local_nomoreai')), ['class' => 'mt-3']);
        switch (self::valid_tab($tab)) {
            case self::TAB_ACTIVITIES:
                return $html . $this->activities();
            case self::TAB_TYPES:
                return $html . $this->types();
            case self::TAB_AGENTS:
                return $html . $this->agents();
            default:
                return $html . $this->users();
        }
    }

    /**
     * Users report: one row per user and course, with their signals.
     *
     * @return string HTML
     */
    public function users(): string {
        global $DB;
        [$where, $params] = $this->where();
        $rows = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat_join("'-'", ['userid', 'courseid', 'signaltype']) . " AS rowkey,
                    userid, courseid, signaltype, COUNT(1) AS n, COUNT(DISTINCT cmid) AS activities,
                    MAX(timecreated) AS lastseen
               FROM {" . signals::TABLE . "}
              WHERE $where
           GROUP BY userid, courseid, signaltype",
            $params
        );
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->userid . '-' . $row->courseid;
            $grouped[$key] = $grouped[$key] ?? ['userid' => (int) $row->userid, 'courseid' => (int) $row->courseid,
                'signals' => [], 'total' => 0, 'lastseen' => 0];
            $grouped[$key]['signals'][$row->signaltype] = (int) $row->n;
            $grouped[$key]['total'] += (int) $row->n;
            $grouped[$key]['lastseen'] = max($grouped[$key]['lastseen'], (int) $row->lastseen);
        }
        if (!$grouped) {
            return $this->nodata();
        }
        uasort($grouped, fn($a, $b) => [$b['total'], $b['lastseen']] <=> [$a['total'], $a['lastseen']]);
        $this->load_users(array_column($grouped, 'userid'));
        $this->load_courses(array_column($grouped, 'courseid'));

        $table = $this->table([get_string('user'), get_string('course'), get_string('activities'),
            get_string('signals', 'local_nomoreai'), get_string('total'), get_string('lastseen', 'local_nomoreai')]);
        $activities = $this->activities_per_user_course();
        foreach (array_slice($grouped, 0, self::MAXROWS) as $item) {
            $table->data[] = [
                $this->user_link($item['userid'], $item['courseid']),
                $this->course_link($item['courseid']),
                $this->activity_links($activities[$item['userid'] . '-' . $item['courseid']] ?? []),
                report_helper::signal_list($item['signals']),
                $item['total'],
                userdate($item['lastseen']),
            ];
        }
        return html_writer::table($table) . $this->truncated(count($grouped));
    }

    /**
     * Courses and activities report: one row per activity (or course-level page), most signals first.
     *
     * @return string HTML
     */
    public function activities(): string {
        global $DB;
        [$where, $params] = $this->where();
        $rows = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat_join("'-'", ['courseid', 'cmid', 'signaltype']) . " AS rowkey,
                    courseid, cmid, signaltype, COUNT(1) AS n, MAX(timecreated) AS lastseen
               FROM {" . signals::TABLE . "}
              WHERE $where AND courseid > 0
           GROUP BY courseid, cmid, signaltype",
            $params
        );
        $users = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat_join("'-'", ['courseid', 'cmid']) . " AS rowkey, COUNT(DISTINCT userid) AS users
               FROM {" . signals::TABLE . "}
              WHERE $where AND courseid > 0 AND userid > 0
           GROUP BY courseid, cmid",
            $params
        );
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->courseid . '-' . $row->cmid;
            $grouped[$key] = $grouped[$key] ?? ['courseid' => (int) $row->courseid, 'cmid' => (int) $row->cmid,
                'signals' => [], 'total' => 0, 'lastseen' => 0, 'users' => (int) ($users[$key]->users ?? 0)];
            $grouped[$key]['signals'][$row->signaltype] = (int) $row->n;
            $grouped[$key]['total'] += (int) $row->n;
            $grouped[$key]['lastseen'] = max($grouped[$key]['lastseen'], (int) $row->lastseen);
        }
        if (!$grouped) {
            return $this->nodata();
        }
        uasort($grouped, fn($a, $b) => [$b['total'], $b['users']] <=> [$a['total'], $a['users']]);
        $this->load_courses(array_column($grouped, 'courseid'));

        $table = $this->table([get_string('course'), get_string('activity'), get_string('signals', 'local_nomoreai'),
            get_string('total'), get_string('distinctusers', 'local_nomoreai'), get_string('lastseen', 'local_nomoreai')]);
        foreach (array_slice($grouped, 0, self::MAXROWS) as $item) {
            $table->data[] = [
                $this->course_link($item['courseid']),
                $item['cmid'] ? $this->activity_link($item['courseid'], $item['cmid']) :
                    s(get_string('coursepages', 'local_nomoreai')),
                report_helper::signal_list($item['signals']),
                $item['total'],
                $item['users'],
                userdate($item['lastseen']),
            ];
        }
        return html_writer::table($table) . $this->truncated(count($grouped));
    }

    /**
     * Signal types report: totals per type with what else causes them, then counts per week.
     *
     * @return string HTML
     */
    public function types(): string {
        global $DB;
        [$where, $params] = $this->where();
        $rows = $DB->get_records_sql(
            "SELECT signaltype, COUNT(1) AS n, COUNT(DISTINCT CASE WHEN userid > 0 THEN userid END) AS users,
                    COUNT(DISTINCT CASE WHEN courseid > 0 THEN courseid END) AS courses,
                    COUNT(DISTINCT CASE WHEN cmid > 0 THEN cmid END) AS activities,
                    SUM(CASE WHEN outcome = 'refused' THEN 1 ELSE 0 END) AS refused,
                    MIN(timecreated) AS firstseen, MAX(timecreated) AS lastseen
               FROM {" . signals::TABLE . "}
              WHERE $where
           GROUP BY signaltype",
            $params
        );
        if (!$rows) {
            return $this->nodata();
        }
        $head = [get_string('signal', 'local_nomoreai'), get_string('total'),
            get_string('refusedcount', 'local_nomoreai'), get_string('distinctusers', 'local_nomoreai')];
        if (!$this->course) {
            $head[] = get_string('courses');
        }
        $head = array_merge($head, [get_string('activities'), get_string('firstseen', 'local_nomoreai'),
            get_string('lastseen', 'local_nomoreai'), get_string('alsocausedby', 'local_nomoreai')]);
        $table = $this->table($head);
        foreach (signals::types() as $type) {
            if (empty($rows[$type])) {
                continue;
            }
            $row = $rows[$type];
            $line = [
                html_writer::tag('strong', s(get_string('signal_' . $type, 'local_nomoreai'))),
                (int) $row->n,
                (int) $row->refused,
                (int) $row->users,
            ];
            if (!$this->course) {
                $line[] = (int) $row->courses;
            }
            $table->data[] = array_merge($line, [
                (int) $row->activities,
                userdate($row->firstseen, get_string('strftimedatetimeshort', 'langconfig')),
                userdate($row->lastseen, get_string('strftimedatetimeshort', 'langconfig')),
                s(get_string('signal_' . $type . '_help', 'local_nomoreai')),
            ]);
        }
        return html_writer::table($table) . $this->weekly();
    }

    /**
     * Counts per week and signal type over the last 12 weeks.
     *
     * @return string HTML
     */
    protected function weekly(): string {
        global $DB, $OUTPUT;
        [$where, $params] = $this->where();
        $params['since'] = time() - 12 * WEEKSECS;
        $rows = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat_join("'-'", ['week', 'signaltype']) . " AS rowkey, week, signaltype, n
               FROM (SELECT FLOOR(timecreated / " . WEEKSECS . ") AS week, signaltype, COUNT(1) AS n
                       FROM {" . signals::TABLE . "}
                      WHERE $where AND timecreated >= :since
                   GROUP BY FLOOR(timecreated / " . WEEKSECS . "), signaltype) w",
            $params
        );
        $weekusers = $DB->get_records_sql_menu(
            "SELECT FLOOR(timecreated / " . WEEKSECS . ") AS week, COUNT(DISTINCT userid)
               FROM {" . signals::TABLE . "}
              WHERE $where AND timecreated >= :since AND userid > 0
           GROUP BY FLOOR(timecreated / " . WEEKSECS . ")",
            $params
        );
        if (!$rows) {
            return '';
        }
        $byweek = [];
        foreach ($rows as $row) {
            $byweek[(int) $row->week][$row->signaltype] = (int) $row->n;
        }
        krsort($byweek);
        $types = array_values(array_filter(signals::types(), fn($t) => array_filter(array_column($byweek, $t))));
        $table = $this->table(array_merge(
            [get_string('weekstarting', 'local_nomoreai'), get_string('distinctusers', 'local_nomoreai')],
            array_map(fn($type) => s(get_string('signal_' . $type, 'local_nomoreai')), $types)
        ));
        foreach ($byweek as $week => $counts) {
            $line = [userdate($week * WEEKSECS, get_string('strftimedate', 'langconfig')), (int) ($weekusers[$week] ?? 0)];
            foreach ($types as $type) {
                $line[] = (int) ($counts[$type] ?? 0);
            }
            $table->data[] = $line;
        }
        return $OUTPUT->heading(get_string('dashboard_weekly', 'local_nomoreai'), 3) . html_writer::table($table);
    }

    /**
     * Agents that identified themselves: one row per agent, with where it was seen and as whom.
     *
     * @return string HTML
     */
    public function agents(): string {
        global $DB;
        [$where, $params] = $this->where();
        [$insql, $inparams] = $DB->get_in_or_equal(
            [signals::SIGNED_AGENT, signals::AGENT_UA, signals::AGENT_IP],
            SQL_PARAMS_NAMED,
            'agt'
        );
        $rs = $DB->get_recordset_select(
            signals::TABLE,
            "$where AND signaltype $insql",
            $params + $inparams,
            'timecreated DESC',
            'id, userid, courseid, cmid, signaltype, detail, outcome, origin, timecreated',
            0,
            self::MAXAGENTROWS
        );
        $agents = [];
        $read = 0;
        foreach ($rs as $row) {
            $read++;
            $detail = json_decode((string) $row->detail, true);
            $client = is_array($detail) && isset($detail['client']) ? (string) $detail['client'] : '?';
            $key = $row->signaltype . '|' . $client;
            $agent = $agents[$key] ?? ['type' => $row->signaltype, 'client' => $client, 'total' => 0, 'refused' => 0,
                'login' => 0, 'users' => [], 'places' => [], 'firstseen' => PHP_INT_MAX, 'lastseen' => 0];
            $agent['total']++;
            $agent['refused'] += $row->outcome === 'refused' ? 1 : 0;
            $agent['login'] += $row->origin === 'login' ? 1 : 0;
            if ($row->userid) {
                $agent['users'][$row->userid . '-' . $row->courseid] = [(int) $row->userid, (int) $row->courseid];
            }
            if ($row->courseid) {
                $agent['places'][$row->courseid . '-' . $row->cmid] = [(int) $row->courseid, (int) $row->cmid];
            }
            $agent['firstseen'] = min($agent['firstseen'], (int) $row->timecreated);
            $agent['lastseen'] = max($agent['lastseen'], (int) $row->timecreated);
            $agents[$key] = $agent;
        }
        $rs->close();
        if (!$agents) {
            return $this->nodata();
        }
        uasort($agents, fn($a, $b) => [$b['total'], $b['lastseen']] <=> [$a['total'], $a['lastseen']]);
        $userids = $courseids = [];
        foreach ($agents as $agent) {
            foreach ($agent['users'] as [$userid, $courseid]) {
                $userids[] = $userid;
                $courseids[] = $courseid;
            }
            foreach ($agent['places'] as [$courseid]) {
                $courseids[] = $courseid;
            }
        }
        $this->load_users($userids);
        $this->load_courses($courseids);

        $table = $this->table([get_string('client', 'local_nomoreai'), get_string('signal', 'local_nomoreai'),
            get_string('total'), get_string('refusedcount', 'local_nomoreai'), get_string('loginpage', 'local_nomoreai'),
            get_string('asusers', 'local_nomoreai'), get_string('where', 'local_nomoreai'),
            get_string('firstseen', 'local_nomoreai'), get_string('lastseen', 'local_nomoreai')]);
        foreach (array_slice($agents, 0, self::MAXROWS) as $agent) {
            $users = array_map(fn($u) => $this->user_link($u[0], $u[1]), array_values($agent['users']));
            $places = array_map(fn($p) => $p[1] ? $this->activity_link($p[0], $p[1]) . ' (' . $this->course_link($p[0]) . ')' :
                $this->course_link($p[0]), array_values($agent['places']));
            $table->data[] = [
                // The client text comes from request headers: attacker-controlled, so escaped here.
                html_writer::tag('code', s($agent['client'])),
                s(get_string('signal_' . $agent['type'], 'local_nomoreai')),
                $agent['total'],
                $agent['refused'],
                $agent['login'],
                $this->short_list($users),
                $this->short_list($places),
                userdate($agent['firstseen'], get_string('strftimedatetimeshort', 'langconfig')),
                userdate($agent['lastseen'], get_string('strftimedatetimeshort', 'langconfig')),
            ];
        }
        $html = html_writer::table($table) . $this->truncated(count($agents));
        if ($read >= self::MAXAGENTROWS) {
            $html .= html_writer::tag(
                'p',
                s(get_string('agentsread', 'local_nomoreai', self::MAXAGENTROWS)),
                ['class' => 'text-muted']
            );
        }
        return $html;
    }

    /**
     * The scope's WHERE clause.
     *
     * @return array [sql, params]
     */
    protected function where(): array {
        if (!$this->course) {
            return ['1 = 1', []];
        }
        $where = 'courseid = :scopecourseid';
        $params = ['scopecourseid' => (int) $this->course->id];
        if ($this->cmid) {
            $where .= ' AND cmid = :scopecmid';
            $params['scopecmid'] = $this->cmid;
        }
        return [$where, $params];
    }

    /**
     * The activities each user triggered signals in, per course.
     *
     * @return array "userid-courseid" => cmid[]
     */
    protected function activities_per_user_course(): array {
        global $DB;
        [$where, $params] = $this->where();
        $out = [];
        $rs = $DB->get_recordset_sql(
            "SELECT DISTINCT userid, courseid, cmid
               FROM {" . signals::TABLE . "}
              WHERE $where AND cmid > 0",
            $params
        );
        foreach ($rs as $row) {
            $out[$row->userid . '-' . $row->courseid][] = (int) $row->cmid;
        }
        $rs->close();
        return $out;
    }

    /**
     * A table with the report's styling.
     *
     * @param string[] $head column headings (plain text)
     * @return html_table
     */
    protected function table(array $head): html_table {
        $table = new html_table();
        $table->head = array_map('s', $head);
        $table->attributes['class'] = 'generaltable local-nomoreai-report';
        return $table;
    }

    /**
     * Load user records.
     *
     * @param int[] $ids
     * @return void
     */
    protected function load_users(array $ids): void {
        global $DB;
        $ids = array_diff(array_unique(array_filter(array_map('intval', $ids))), array_keys($this->users));
        foreach (array_chunk($ids, 500) as $chunk) {
            $records = $DB->get_records_list('user', 'id', $chunk);
            foreach ($chunk as $id) {
                $this->users[$id] = $records[$id] ?? false;
            }
        }
    }

    /**
     * Load course records.
     *
     * @param int[] $ids
     * @return void
     */
    protected function load_courses(array $ids): void {
        global $DB;
        $ids = array_diff(array_unique(array_filter(array_map('intval', $ids))), array_keys($this->courses));
        foreach (array_chunk($ids, 500) as $chunk) {
            $records = $DB->get_records_list('course', 'id', $chunk);
            foreach ($chunk as $id) {
                $this->courses[$id] = $records[$id] ?? false;
            }
        }
    }

    /**
     * A user's name, linked to their profile in the course the signal was seen in.
     *
     * Site-level signals (login page, token use) link to the site profile; signals before login name nobody.
     *
     * @param int $userid
     * @param int $courseid 0 for a site-level signal
     * @return string HTML
     */
    protected function user_link(int $userid, int $courseid): string {
        if (!$userid) {
            return s(get_string('notloggedin', 'local_nomoreai'));
        }
        $this->load_users([$userid]);
        $user = $this->users[$userid] ?? false;
        if (!$user || !empty($user->deleted)) {
            return s(get_string('deleteduser', 'local_nomoreai'));
        }
        $url = ($courseid && $courseid != SITEID && !empty($this->courses[$courseid])) ?
            new moodle_url('/user/view.php', ['id' => $userid, 'course' => $courseid]) :
            new moodle_url('/user/profile.php', ['id' => $userid]);
        return html_writer::link($url, s(fullname($user)));
    }

    /**
     * A course's name, linked to the course.
     *
     * @param int $courseid 0 for site-level signals
     * @return string HTML
     */
    protected function course_link(int $courseid): string {
        if (!$courseid || $courseid == SITEID) {
            return s(get_string('sitelevel', 'local_nomoreai'));
        }
        $this->load_courses([$courseid]);
        $course = $this->courses[$courseid] ?? false;
        if (!$course) {
            return s(get_string('deletedcourse', 'local_nomoreai'));
        }
        $name = format_string($course->fullname, true, ['context' => \context_course::instance($courseid)]);
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), $name);
    }

    /**
     * An activity's name, linked to the activity.
     *
     * @param int $courseid
     * @param int $cmid
     * @return string HTML
     */
    protected function activity_link(int $courseid, int $cmid): string {
        $this->load_courses([$courseid]);
        $course = $this->courses[$courseid] ?? false;
        $cms = $course ? get_fast_modinfo($course)->get_cms() : [];
        if (!isset($cms[$cmid])) {
            return s(get_string('deletedactivity', 'local_nomoreai'));
        }
        $cm = $cms[$cmid];
        $url = $cm->url ?? new moodle_url('/course/view.php', ['id' => $courseid], 'module-' . $cmid);
        return html_writer::link($url, $cm->get_formatted_name());
    }

    /**
     * Linked activity names.
     *
     * @param int[] $cmids
     * @return string HTML
     */
    protected function activity_links(array $cmids): string {
        if (!$cmids) {
            return s(get_string('coursepages', 'local_nomoreai'));
        }
        $links = [];
        foreach ($cmids as $cmid) {
            $courseid = $this->course ? (int) $this->course->id : $this->course_of($cmid);
            $links[] = $this->activity_link($courseid, $cmid);
        }
        return $this->short_list($links);
    }

    /**
     * The course an activity belongs to.
     *
     * @param int $cmid
     * @return int 0 when the activity no longer exists
     */
    protected function course_of(int $cmid): int {
        global $DB;
        static $cache = [];
        if (!array_key_exists($cmid, $cache)) {
            $cache[$cmid] = (int) $DB->get_field('course_modules', 'course', ['id' => $cmid]);
        }
        return $cache[$cmid];
    }

    /**
     * A list of HTML items, showing the first ten and a count of the rest.
     *
     * @param string[] $items HTML
     * @return string HTML
     */
    protected function short_list(array $items): string {
        if (!$items) {
            return '-';
        }
        $shown = array_slice($items, 0, 10);
        if (count($items) > 10) {
            $shown[] = s(get_string('andmore', 'local_nomoreai', count($items) - 10));
        }
        return html_writer::alist($shown, ['class' => 'list-unstyled mb-0']);
    }

    /**
     * A note when a table was cut at MAXROWS.
     *
     * @param int $count rows available
     * @return string HTML
     */
    protected function truncated(int $count): string {
        if ($count <= self::MAXROWS) {
            return '';
        }
        return html_writer::tag('p', s(get_string(
            'rowsshown',
            'local_nomoreai',
            ['shown' => self::MAXROWS, 'total' => $count]
        )), ['class' => 'text-muted']);
    }

    /**
     * The "no signals" notice.
     *
     * @return string HTML
     */
    protected function nodata(): string {
        global $OUTPUT;
        return $OUTPUT->notification(
            get_string('nosignals', 'local_nomoreai'),
            \core\output\notification::NOTIFY_INFO,
            false
        );
    }
}
