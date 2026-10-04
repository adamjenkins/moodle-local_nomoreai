# Changes

## [Unreleased]

- Block tokens and the token audit now cover the personal access tokens users can create from Moodle 5.3
  (MDL-87706, capability `moodle/api:createtoken`). The audit lists and purges the working personal access
  tokens of users who are not exempt, and with Block tokens on, such a user's use of one is recorded and, in
  Enforce, refused and the token deleted. Moodle 5.2 is unaffected.

## v0.1.1

- First version. Modes Off (default), Detect only and Enforce. Refuses, or in Detect only records, agents
  that identify themselves (Web Bot Auth signatures, agent user agents, listed IP addresses), web-service
  submissions of students' work ("Block submissions"), optionally all student web-service tokens ("Block
  tokens", off by default), and token use of activities set to "Web browser only". Shows a visible notice to
  students in Enforce. Optional flag-only browser monitoring (counts only), quiz speed and page-view signals,
  a teacher report, an admin dashboard and a token audit with purge. Exemptions through the
  `local/nomoreai:exempt` and `local/nomoreai:notmonitored` capabilities and a trusted networks list.
- Supports Moodle 5.2 and 5.3. Installable with Composer (`adamjenkins/moodle-local_nomoreai`).
