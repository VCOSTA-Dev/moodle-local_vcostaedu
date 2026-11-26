# Moodle Marketplace plugin submission guidelines: checklist

Each requirement of the Moodle Marketplace plugin submission guidelines, its status for
local_vcostaedu 1.0.0-rc2 and where the evidence is.

| Requirement | Status | Evidence |
|---|---|---|
| Installs from the zip through the usual process | Done | `docs/INSTALL.md`; installed through *Install plugins* in English and Portuguese from a clean site, on Moodle 4.5 and 5.3 |
| Unusual steps described | Done | `README.md` (installation section), `docs/marketplace/description.md`: role, user and service created; name conflict check; web services left to the administrator |
| No Composer or similar tool needed by the administrator | Done | No `composer.json`, no `vendor/`, no build step; no JavaScript of its own |
| Tested with developer debugging, no PHP notice, warning or error | Done | `extra/e2e/e2e.sh` turns on developer debugging and checks every page and the installation output (test 1.40), on Moodle 4.5 and 5.3 |
| Works on MySQL/MariaDB and PostgreSQL; uses the data API | Done | PHPUnit on MariaDB 11.4 (Moodle 4.5 to 5.3) and PostgreSQL 17 (Moodle 4.5 and 5.3); only `$DB` methods with parameters |
| Moodle coding style; English comments and names | Done | `moodle-plugin-ci phpcs --max-warnings 0` (no errors, no warnings), `phpdoc` |
| Standard license header and `@copyright` with the author | Done | Every PHP file: GPL v3 or later header, `@copyright 2026 VCOSTA` |
| CSS limited to the plugin | Not applicable | No CSS |
| Frankenstyle prefix on tables, settings, functions, classes, constants, globals | Done | Namespace `local_vcostaedu\`; settings `local_vcostaedu/loginvalidity`; page functions none (moved into classes); no tables; no globals |
| Settings in `config_plugins`, read with `get_config()` | Done | `classes/setup.php` `login_validity()`; `settings.php` |
| Strings through `get_string()`; English only in the package; data only language file; no leading or trailing spaces; no title case | Done | `lang/en/local_vcostaedu.php`; Portuguese kept outside the package (`extra/lang/pt_br`) and shipped only in the separate `-ptbr.zip` |
| Privacy API with the metadata provider; all personal data described | Done | `classes/privacy/provider.php`: core user keys and the external location `vcostaedu` with each field; PHPUnit `privacy/provider_test.php` |
| No personal data beyond what is needed | Done | No tables; login keys deleted on use or expiry; the plugin stores only the ids of the role and user it created |
| Least privilege for the service role | Done | 29 capabilities, each needed by a function of the service (`docs/REVIEW.md`, "Capabilities of the service role"); `moodle/site:viewuseridentity` left out on purpose, with the function `local_vcostaedu_find_users_by_email` answering only ids for the search by e-mail; PHPUnit `test_role_capabilities` checks the exact list |
| Security: no superglobals, typed parameters, parameterised SQL, sesskey, login and capability checks, no eval/unserialize/call_user_func with user data | Done | `connect.php` (`admin_externalpage_setup`, confirmation, `require_sesskey`, `require_capability`), `login.php` (typed `optional_param`, see below), external function (`validate_parameters`, `validate_context`, `require_capability`); `docs/REVIEW.md` security table |
| `require_login()` where appropriate | Done, with one documented exception | `login.php` is a login page; the one-time key replaces `require_login()`; explained in the file and in `docs/REVIEW.md` |
| External service: what it is, what depends on it, what happens without it, how to get credentials | Done | `README.md` (external service section), `docs/marketplace/description.md` |
| Reviewer gets what is needed to test | Done | `docs/REVIEW.md`: testing without VCOSTAEDU, with plain web service calls |
| Public place for bug reports | To fill in | Placeholder in `README.md` and `docs/marketplace/description.md`; to be set when the public repository is created |
| PostgreSQL support | Done | PHPUnit on PostgreSQL 17, Moodle 4.5 and 5.3 |
| No name collisions | Done | Component `local_vcostaedu`; service short name `local_vcostaedu`; environment check stops the install if the service name "VCOSTAEDU" or the short name `local_vcostaedu` is taken |
| No payment, advertising or donation requests outside the Moodle Marketplace | Done | None in the plugin or the documents |
| Correct use of the Moodle trademark | Done | The plugin name does not include "Moodle"; Moodle is named only to describe compatibility |
| Version declares only what was tested | Done | `version.php`: requires Moodle 4.5, supported 405 to 503, maturity RC, no dependencies; each of 4.5, 5.0, 5.1, 5.2 and 5.3 tested (`docs/REVIEW.md`) |
| Automated checks (`moodle-plugin-ci`) | Done locally | phplint, phpcs, phpdoc, validate, savepoints, mustache, grunt and PHPUnit on both databases on Moodle 4.5 (PHP 8.1 and 8.3) and 5.3 (PHP 8.3); phpcs and PHPUnit on MariaDB on Moodle 5.0 and 5.1 (PHP 8.2); `.github/workflows/moodle-ci.yml` covers 4.5 to 5.3 on both databases with the lowest PHP of each branch and PHP 8.3 |
| License file in the package | Done | `LICENSE` (GPL v3) at the root of both zips |
