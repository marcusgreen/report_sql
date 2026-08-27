@report @report_sql @javascript
Feature: Bulk actions on report rows
  In order to act on the people a report lists
  As a report author with the run-bulk-actions capability
  I need to enable actions on a published report, select rows and apply an operation

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | One      | student1@test.com |
      | student2 | Sue       | Two      | student2@test.com |
    And the following "report_sql > queries" exist:
      | name            | querysql                                      |
      | Actionable demo | SELECT id AS userid, firstname FROM {user}    |

  Scenario: Enable actions, then reach the actions page from the listing
    Given I log in as "admin"
    And I visit "/report/sql/index.php"
    When I click on "Publish" "link" in the "Actionable demo" "table_row"
    Then "Published" "text" should exist in the "Actionable demo" "table_row"
    # Configure the actionable section on the now-published query.
    When I click on "Edit query" "link" in the "Actionable demo" "table_row"
    And I expand all fieldsets
    And I set the field "Enable bulk actions" to "1"
    And I set the field "Subject ID column" to "userid"
    And I set the field "Available actions" to "Message users"
    And I set the field "Message text" to "Hello from the report"
    And I press "Save changes"
    # The listing now offers the actions entry, and it opens the actionable table.
    And I open the action menu in "Actionable demo" "table_row"
    And I choose "Open actions" in the open action menu
    Then I should see "Row actions"
    # The report renders a select-all checkbox column.
    And "input[data-togglegroup='report-select-all'][data-toggle='toggler']" "css_element" should exist
