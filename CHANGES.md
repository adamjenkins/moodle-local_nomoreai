# Changes

## v0.1.3

- The site dashboard (AI agent activity) and the course report (AI agent signals) are now tabbed reports with
  the same tabs: Users (who triggered signals, in which course and activities), Courses and activities (where
  the most signals were seen), Signal types (totals, refusals, what else causes each, and counts per week for
  the last 12 weeks), Calibration (below) and Identified agents (each agent that identified itself, which users
  it acted as and where it was seen, including the login page). Every user, course and activity listed is a
  link: users go to their profile in that course, or their site profile for site-level signals.
- Browser monitoring now also spots clicks that land dead centre on their button or link, machine-like typing
  (too fast, or too evenly spaced) and reactions faster than a person can manage. Like the other browser
  signals these are only recorded for teachers, never used to refuse anything. Only summary numbers leave the
  browser (for typing, a median gap and how much the gaps vary), never what was typed or individual key
  timings. Their thresholds are new settings under Detection. Typing rhythm is a form of keystroke dynamics:
  check with your data protection officer before turning browser monitoring on.
- The new Calibration tab shows how ordinary monitored page views on your site spread on each of these measures
  and how many page views each current threshold would flag, so you can tune the thresholds in Detect only mode
  before trusting the signals. These measurements are stored without any user id, at most 300 per user and
  day, and are deleted with the signals (retention period, course reset, course or activity deletion).
- The course report now respects separate groups: a teacher without "Access all groups" sees only the signals
  of their own groups' members.
- Purging tokens from the web service token audit now needs the new capability
  `local/nomoreai:purgetokens` (given to managers by default). `local/nomoreai:viewdashboard` still lets a user
  view the audit, but no longer purge.
- Login-page signals from self-identifying agents are now recorded at most once an hour per signal type and
  address, whatever client name the agent sends, and at most 500 an hour site-wide. Above that limit agents are
  still refused in Enforce, just not recorded again.
- Deleting a course now also removes the "Web browser only" settings of its activities.
- The plugin has an icon on the plugins overview page.

After upgrading, a new table for the calibration measurements is created and the new capability is added. If
someone other than a manager or administrator purges tokens on your site, give their role
`local/nomoreai:purgetokens`.
