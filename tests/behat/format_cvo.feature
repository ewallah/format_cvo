@format @format_cvo @javascript

Feature: format_cvo

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format  | numsections |
      | Course 1 | C1        | cvo     | 2           |
    And the following "users" exist:
      | username |
      | teacher1 |
      | student1 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Forum" to section "0" and I fill the form with:
      | Forum name | Testforum1 |
      | Description | Test forum 1 description |
    And I add a new discussion to "Testforum1" forum with:
      | Subject | Teacher post subject 1 |
      | Message | Teacher post message 1 |
    And I add a new discussion to "Testforum1" forum with:
      | Subject | Teacher post subject 2 |
      | Message | Teacher post message 2 |
    And I add a new discussion to "Testforum1" forum with:
      | Subject | Teacher post subject 3 |
      | Message | Teacher post message 3 |
    And I am on "Course 1" course homepage
    And I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Testforum2 |
      | Description | Test forum description 2|
    And I add a new discussion to "Testforum2" forum with:
      | Subject | Teacher post subject 4 |
      | Message | Teacher post message 4 |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I reply "Teacher post subject 1" post from "Testforum1" forum with:
      | Subject | Student post subject 1 |
      | Message | Student post message 1 |

  @javascript
  Scenario: Teacher can add elements
    Given I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then I should see "Teacher post subject 1"
    And I should see "Teacher post subject 2"
    And I should see "Teacher post subject 3"
    And I should not see "Teacher post subject 4"
    And I click on "Add a new discussion topic" "button"
    And I set the following fields to these values:
      | Subject | Teacher post subject 5 |
      | Message | Teacher post message 5 |

  @javascript
  Scenario: Student can not add more discussions
    Given I am on "Course 1" course homepage
    Then I should see "Teacher post subject 1"
    And I should see "Teacher post subject 2"
    And I should see "Teacher post subject 3"
    And I should not see "Teacher post subject 4"
