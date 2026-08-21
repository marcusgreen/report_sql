@report @report_sql
Feature: Publish and unpublish a report source
  In order to turn a saved SQL query into a live Report Builder report
  As a report author
  I need to publish a draft and later take it back offline

  Background:
    Given the following "report_sql > queries" exist:
      | name           | querysql                                   |
      | Lifecycle demo | SELECT id AS userid, firstname FROM {user} |

  Scenario: Publish a draft then unpublish it
    Given I log in as "admin"
    When I visit "/report/sql/index.php"
    Then I should see "Lifecycle demo"
    # A freshly created query starts life as a draft.
    And "Draft" "text" should exist in the "Lifecycle demo" "table_row"
    # Publishing builds the backing view and Report Builder report.
    When I click on "Publish" "link" in the "Lifecycle demo" "table_row"
    Then I should see "Published"
    And "Published" "text" should exist in the "Lifecycle demo" "table_row"
    And "Unpublish" "link" should exist in the "Lifecycle demo" "table_row"
    # Unpublishing tears the report down and returns the query to draft.
    When I click on "Unpublish" "link" in the "Lifecycle demo" "table_row"
    Then "Draft" "text" should exist in the "Lifecycle demo" "table_row"
    And "Publish" "link" should exist in the "Lifecycle demo" "table_row"
