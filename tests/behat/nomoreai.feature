@local @local_nomoreai
Feature: No More AI notices, activity setting and reports
  In order to discourage and measure AI agents doing students' work
  As an administrator or teacher
  I need the notice, the per-activity setting and the reports to work

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
      | teacher1 | Tina      | Teacher  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber |
      | forum    | Forum 1 | C1     | forum1   |
    And the following config values are set as admin:
      | enableintests | 1 | local_nomoreai |

  Scenario: The notice is shown to students in Enforce mode only
    Given the following config values are set as admin:
      | mode | detect | local_nomoreai |
    When I am on the "Forum 1" "forum activity" page logged in as "student1"
    Then I should not see "AI agents must not complete this activity"
    And the following config values are set as admin:
      | mode | enforce | local_nomoreai |
    And I am on the "Forum 1" "forum activity" page
    And I should see "AI agents must not complete this activity"
    And I am on the "Forum 1" "forum activity" page logged in as "teacher1"
    And I should not see "AI agents must not complete this activity"

  Scenario: The monitoring sentence is shown while monitoring runs
    Given the following config values are set as admin:
      | mode       | detect | local_nomoreai |
      | monitoring | 1      | local_nomoreai |
    When I am on the "Forum 1" "forum activity" page logged in as "student1"
    Then I should see "Interaction patterns on this page are recorded"

  Scenario: A teacher sets an activity to web browser only
    When I am on the "Forum 1" "forum activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I set the field "Web browser only" to "1"
    And I press "Save and return to course"
    And I am on the "Forum 1" "forum activity editing" page
    And I expand all fieldsets
    Then the field "Web browser only" matches value "1"

  Scenario: The teacher report and admin pages render
    When I am on the "Course 1" course page logged in as teacher1
    And I navigate to "AI agent signals" in current page administration
    Then I should see "These are indicators, not proof"
    And I log in as "admin"
    And I navigate to "Reports > AI agent activity" in site administration
    And I should see "No signals recorded."
    And I navigate to "Plugins > Local plugins > Web service token audit" in site administration
    And I should see "No user who lacks an exemption holds a web service token or a personal access token."
    And I navigate to "Plugins > Local plugins > No More AI" in site administration
    And I should see "Block tokens"
