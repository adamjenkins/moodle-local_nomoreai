# No More AI (local_nomoreai)

Makes it as hard as practical for AI agents to do students' Moodle activities for them, and shows you how
often agents are seen on your site, so you can measure the problem before enforcing anything.

## What it can and cannot do

AI agents reach Moodle in several ways. The plugin closes or flags the ones a site can control:

| How the agent works | What the plugin does |
|---|---|
| AI tools connected to Moodle through a web-service token (MCP servers, scripts, CLIs) | **Stops them** doing students' work ("Block submissions"), or stops students using tokens at all ("Block tokens") |
| Cloud agents that sign their requests (Web Bot Auth) and agents with a telltale user agent or IP address | **Refuses them** on the login page and on course and activity pages |
| Browser automation (Playwright, Puppeteer, Selenium) and agents built into the student's browser | **Flags** them for teachers through optional browser monitoring and quiz signals (never refuses them, because some assistive technologies produce similar signals) |
| An agent operating the student's own computer like a person would | **Cannot be stopped or reliably detected** by any plugin |

For high-stakes quizzes, use Safe Exam Browser, a quiz password given out in the room or an IP address
restriction, and consider assessment designs that do not rely on unsupervised online work.

## Install

1. Copy the plugin to `public/local/nomoreai` in your Moodle code.
2. Visit Site administration > Notifications.

Requires Moodle 5.2. Installing changes nothing: the mode starts at **Off**.

## Use

Settings: Site administration > Plugins > Local plugins > No More AI.

- **Mode**
  - **Off**: nothing is checked or recorded.
  - **Detect only**: everything is recorded ("would have been refused" where applicable) but nothing is
    refused and no notice is shown. Start here to find out how common agent use is.
  - **Enforce**: refusals apply and the notice is shown.
- **Trusted networks**: IP ranges whose requests skip every check.
- **Block submissions** (on): refuses web-service calls that start, answer or submit students' work in
  quizzes, assignments, forums, lessons, H5P, workshops, glossaries, databases, wikis, choices, feedback and
  SCORM. Students keep the Moodle app for reading.
- **Block tokens** (off): students cannot create or use web-service tokens, and their existing tokens are
  deleted. **The Moodle app stops working for them.**
- **Refuse identified agents** (on), with editable lists of agent user-agent tokens and IP addresses.
- **Notice** (on): a visible notice at the top of activity pages in Enforce, addressed to people and agents.
- **Browser monitoring** (off): counts automation flags, known agents' page traces, text entered without key
  presses, clicks without pointer movement and large pastes, and records signals. Only counts are sent, never
  what was typed. A sentence tells students while it runs. Consult your data protection officer first.
- **Fast quiz attempt** and **retention** (90 days).

Each activity's settings have a **Web browser only** option. When it is on, the activity cannot be used
through any web-service token in Enforce, not even for reading.

### Exemptions

- `local/nomoreai:exempt` skips every check and all monitoring. Managers, course creators, teachers and
  non-editing teachers have it by default. Give it to a student through a role (site-wide, or in one course or
  activity), for example as an accessibility accommodation.
- `local/nomoreai:notmonitored` skips browser monitoring and the behavioural signals only.

### Reports

- **AI agent signals** (course navigation, `local/nomoreai:viewreport`): signals per student and activity, with
  what else can cause each one. They are indicators, not proof.
- **AI agent activity** (Site administration > Reports, `local/nomoreai:viewdashboard`): weekly counts,
  distinct users, top activities and the agents that identified themselves.
- **Web service token audit** (Site administration > Plugins > Local plugins): tokens held by users who are not
  exempt, with a purge button. Moodle keeps using an existing token without re-checking permissions, so
  removing a permission does not cut off a student who already holds one.

## Privacy

Signals are stored per user with the activity, kind of signal and a few counts. They are covered by the
privacy API (export and deletion) and deleted after the retention period, on course reset and on course or
activity deletion.

## Licence

GNU GPL v3 or later. Copyright 2026 Adam Jenkins.
