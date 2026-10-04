# Changes

## Unreleased

- The site dashboard (AI agent activity) and the course report (AI agent signals) now show four tabs: Users,
  Courses and activities, Signal types (with counts per week) and Identified agents (which agent, acting as
  which users, where). Every user, course and activity listed is a link: users go to their profile in that
  course.

## v0.1.2

- The plugin's maturity is now Beta (it was Alpha).
- Block tokens and the token audit now cover the personal access tokens users can create from Moodle 5.3
  (MDL-87706, capability `moodle/api:createtoken`). The audit lists and purges the working personal access
  tokens of users who are not exempt, and with Block tokens on, such a user's use of one is recorded and, in
  Enforce, refused and the token deleted. Moodle 5.2 is unaffected.
- The Composer package now accepts any Moodle 5.x release from 5.2 on (`moodle/moodle` `^5.2`). The
  supported versions declared in `version.php` are unchanged.
- The automated tests now run against the released Moodle 5.3 (`MOODLE_503_STABLE`) instead of Moodle's
  development branch, and those runs now count towards a pass.

No database changes. No action is required after upgrading.
