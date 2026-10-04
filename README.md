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

Requires Moodle 5.2 or 5.3. Installing changes nothing: the mode starts at **Off**.

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
  deleted. **The Moodle app stops working for them.** From Moodle 5.3 this also covers personal access tokens
  (`moodle/api:createtoken`): a student's use of one is refused and the token deleted.
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

- **AI agent activity** (Site administration > Reports, `local/nomoreai:viewdashboard`) and **AI agent
  signals** (course navigation, `local/nomoreai:viewreport`, limited to that course and filterable by
  activity) show the same four tabs:
  - **Users**: who triggered signals, in which course and activities, with their signals and when last seen.
  - **Courses and activities**: where the most signals were seen, and by how many users.
  - **Signal types**: totals, refusals, users and activities per signal, what else can cause each one, and
    counts per week for the last 12 weeks.
  - **Identified agents**: each agent that identified itself (signed agent name, agent user agent or listed
    IP address), the users it acted as and where it was seen, including the login page.

  Every user, course and activity is linked: users to their profile in that course (their site profile for
  site-level signals), courses to the course and activities to the activity. Signals are indicators, not proof.
- **Web service token audit** (Site administration > Plugins > Local plugins): web-service tokens, and from
  Moodle 5.3 personal access tokens, held by users who are not exempt, with a purge button. Moodle keeps using an existing token without re-checking permissions, so
  removing a permission does not cut off a student who already holds one.

## Privacy

Signals are stored per user with the activity, kind of signal and a few counts. They are covered by the
privacy API (export and deletion) and deleted after the retention period, on course reset and on course or
activity deletion.

## Licence

GNU GPL v3 or later. Copyright 2026 Adam Jenkins.
