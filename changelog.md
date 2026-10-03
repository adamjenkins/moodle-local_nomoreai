# Changelog

All notable changes to this plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
