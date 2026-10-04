# Changelog

All notable changes to this plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Changed

- The site dashboard and the course report are now tabbed reports: Users, Courses and activities, Signal
  types and Identified agents, sharing one implementation (`classes/local/reports.php`). Users, courses and
  activities are linked everywhere; users link to their course profile.

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
