# Changelog

All notable changes to this plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [0.1.3] - 2026-11-10

### Added

- Signals `centre_clicks`, `machine_typing` and `instant_interaction` (`signals::CENTRE_CLICKS`,
  `MACHINE_TYPING`, `INSTANT`) from new browser measurements in `amd/src/monitor.js`: click offset from the
  control's centre, median and variation (standard deviation / mean) of the gaps between typed keys, time from
  page ready to the first click or answer, plus pointer-move and scroll counts. Evaluated in
  `pageview::evaluate()` against the new settings `centreratio` (0.6), `centreminclicks` (5), `keymedianms`
  (30), `keycv` (0.15), `keyminintervals` (20) and `instantms` (500).
- `local_nomoreai_record_pageview` accepts the optional parameters `measuredclicks`, `centreclicks`,
  `keyintervals`, `keymedian`, `keycv`, `firstinputms`, `pointermoves` and `scrolls` (all `VALUE_DEFAULT`, so
  pages still running the previous script keep working); values are clamped server side.
- Table `local_nomoreai_metric` (`db/upgrade.php` step 2026101400): anonymous per-page-view measurements, no
  user id, unique on `pageview, cmid`. Written by `classes/local/metrics.php`, capped at 300 rows per session
  and per user and day (application cache `metricrows`, `db/caches.php`), purged by the existing
  `purge_signals` task and on course reset, course deletion and activity deletion.
- Calibration report tab: percentiles of each measure and the number of page views each current threshold
  would flag (`metrics::calibration()`), computed from the latest 20000 measurements of the site, course or
  activity shown.
- Capability `local/nomoreai:purgetokens` (`RISK_DATALOSS | RISK_CONFIG`, manager archetype), required by the
  purge action on `tokens.php`; the purge button is only shown with it.
- `pix/icon.svg`.
- New PHPUnit tests (`tests/behaviour_test.php`, `tests/reports_test.php`, additions to
  `detection_test.php`, `enforcer_test.php`, `lifecycle_test.php`) and Behat steps for the report tabs.

### Changed

- The site dashboard (`dashboard.php`) and the course report (`report.php`) share one implementation,
  `classes/local/reports.php`, with the tabs Users, Courses and activities, Signal types (including the weekly
  counts), Calibration and Identified agents. Users, courses and activities are linked everywhere; users link
  to their course profile, or their site profile for site-level signals.
- The course report honours separate groups for viewers without `moodle/site:accessallgroups`.
- `local/nomoreai:viewdashboard` now covers viewing the token audit only, no longer the purge.
- Lang strings: added the tab, calibration, threshold-setting and new-signal strings; removed
  `dashboard_clients`, `dashboard_topactivities` and `student`; `monitoring_desc` describes the new
  measurements.

### Fixed

- Login-page agent signals are deduplicated per hour by signal type and hashed address instead of the
  header-supplied client name, so varying that header no longer creates new rows, and are capped at
  `enforcer::LOGIN_SIGNALS_PER_HOUR` (500) per clock hour site-wide; above the cap a request is still refused
  in Enforce, just not recorded.
- Course deletion now also removes the `local_nomoreai_cm` (Web browser only) rows of its activities, which
  core deletes without raising `course_module_deleted`.
- The supported-range comment in the CI workflow.

## [0.1.2] - 2026-10-04

### Changed

- Maturity raised from Alpha to Beta (`$plugin->maturity = MATURITY_BETA`).
- composer.json's `moodle/moodle` constraint is now `^5.2` (was `>=5.2`).
- CI tests `MOODLE_503_STABLE` (PHP 8.3-8.4, PostgreSQL 17, MariaDB 11.4) as blocking rows, replacing the
  non-blocking moodle.git `main` rows.

### Fixed

- Block tokens and the web service token audit now cover Moodle 5.3 personal access tokens (MDL-87706,
  `moodle/api:createtoken`): the audit lists and purges working personal access tokens of users who are not
  exempt, and with Block tokens on their use is recorded and, in Enforce, refused and the token deleted.

## [0.1.1] - 2026-10-04

### Added

- Site modes: Off (default), Detect only, Enforce.
- Refusal of self-identifying agents (Web Bot Auth signature headers, agent user-agent tokens, IP list) on
  the login, course and activity pages.
- "Block submissions": web-service calls that do students' work are refused.
- "Block tokens" (off by default): students cannot create or use web-service tokens; existing tokens are purged.
- Per-activity "Web browser only" setting, with backup and restore.
- Student notice in Enforce, and a monitoring sentence whenever browser monitoring runs.
- Flag-only browser monitoring (counts and booleans only), quiz fast-attempt and no-page-view signals.
- Teacher report "AI agent signals", admin dashboard "AI agent activity", web service token audit with purge.
- Capabilities `local/nomoreai:exempt`, `local/nomoreai:notmonitored`, `local/nomoreai:viewreport`,
  `local/nomoreai:viewdashboard`; trusted networks setting; 90-day signal retention; privacy provider.
- Support for Moodle 5.2 and 5.3.
- `composer.json` for installation with Composer.
