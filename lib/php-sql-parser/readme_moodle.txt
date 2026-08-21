Description of the PHP-SQL-Parser import into report_sql.

Library:  greenlion/php-sql-parser
Version:  v4.7.0 (composer package version 4.7.0.0)
Ref:      0cd49149efc5868db9c32d1a09558ea516892586
Source:   https://github.com/greenlion/PHP-SQL-Parser
License:  BSD-3-Clause (see vendor/greenlion/php-sql-parser/LICENSE)

Why it is here
--------------
classes/local/sql/validator.php parses author-supplied SQL into a parse tree to
check the statement type (SELECT/WITH/UNION only) and that every JOIN carries an
ON/USING condition. The library is loaded lazily via
lib/php-sql-parser/vendor/autoload.php.

The parse result is ADVISORY defence-in-depth only: the keyword denylist, table
denylist, column denylist and the live CREATE VIEW / dry-run check all run
independently, so a parser miss cannot open SQL injection. Worst realistic case
from adversarial input is a failed request (caught as errparse), not data leak.

How to upgrade / re-vendor
--------------------------
The library is committed (vendored) rather than pulled by Composer at deploy, so
it is frozen at the version above until manually refreshed. To update:

  1. In a scratch dir:  composer require greenlion/php-sql-parser:^4.7
  2. Copy the resulting vendor/ tree over lib/php-sql-parser/vendor/
     (keep this readme_moodle.txt).
  3. Update the Version / Ref lines above from
     vendor/composer/installed.php.
  4. Re-run the plugin's PHPUnit suite (tests/sql_validator_test.php in
     particular) and commit.

Watch https://github.com/greenlion/PHP-SQL-Parser for security releases and
re-vendor on any advisory affecting the parser/lexer.
