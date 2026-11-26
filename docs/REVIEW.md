# Reviewer guide: local_vcostaedu

This guide is for the person reviewing the plugin. It says what the plugin does and does
not do, how to test all of it **without VCOSTAEDU**, and why some decisions are unusual.

## What the plugin does

1. **Prepares Moodle for VCOSTAEDU** from the web interface (page *Site administration >
   Plugins > Local plugins > VCOSTAEDU > Connect to VCOSTAEDU*):
   - creates, on installation, the role `vcostaedu_ws` (29 capabilities at system level, listed
     below, may assign student, non-editing teacher and teacher), the service user `vcostaedu_ws`
     (manual authentication, random password nobody knows, `@example.invalid` address), the
     role assignment and the external service "VCOSTAEDU" (short name `local_vcostaedu`,
     authorised users only, 17 functions);
   - shows a diagnosis item by item and offers buttons, each with a confirmation and the
     session key: turn on web services and REST, fix role and service, generate a key,
     generate a new key (revokes only the plugin keys);
   - shows the Moodle address and the key to paste into VCOSTAEDU, with copy buttons.
2. **One-time login links**: the web service function `local_vcostaedu_request_login_url`
   returns a link to `local/vcostaedu/login.php` with a single-use key (core `create_user_key`,
   validity 15 to 300 seconds, 60 by default). Opening the link logs the student in.
3. **Accounts found by e-mail**: the web service function `local_vcostaedu_find_users_by_email`
   receives 1 to 100 e-mail addresses and answers, for each one, only the ids of the accounts
   that have it, so VCOSTAEDU can link accounts that already exist to its academic records.

## What it does not do

- It does not switch on web services by itself; the administrator does, with a button.
- It does not send anything to any server. VCOSTAEDU calls Moodle, never the other way.
- It does not change e-mail or enrolment settings of the site.
- It stores no personal data in tables of its own (it has none).
- It does not let site administrators, guests or the service account log in through a link.

## Capabilities of the service role

The role `vcostaedu_ws` gets only what the functions of the service need, at system level. The
list is `setup::CORE_CAPABILITIES` plus the plugin's own capability; PHPUnit
`test_role_capabilities` checks that the role has exactly these.

| Capability | Needed by |
|---|---|
| `webservice/rest:use` | every call (REST protocol) |
| `moodle/user:create` | `core_user_create_users` |
| `moodle/user:update` | `core_user_update_users` |
| `moodle/user:viewdetails` | `core_user_get_users_by_field`, `core_user_get_users`, `core_enrol_get_enrolled_users` (user details); `local_vcostaedu_find_users_by_email` |
| `moodle/user:viewhiddendetails` | the same, for fields hidden on the site |
| `moodle/user:viewalldetails` | `core_user_get_users_by_field`, `core_user_get_users` (id number and details at site level) |
| `moodle/course:view` | course functions and `core_enrol_get_enrolled_users`, `gradereport_user_get_grade_items` in courses the service user is not enrolled in |
| `moodle/course:viewhiddencourses` | `core_course_get_courses_by_field`, `core_enrol_get_users_courses` for hidden courses |
| `moodle/course:create` | `core_course_create_courses` |
| `moodle/course:update` | `core_course_update_courses` |
| `moodle/course:changefullname` | `core_course_update_courses`, field `fullname` |
| `moodle/course:changeshortname` | `core_course_update_courses`, field `shortname` |
| `moodle/course:changeidnumber` | `core_course_update_courses`, field `idnumber` |
| `moodle/course:changesummary` | `core_course_update_courses`, fields `summary` and `summaryformat` |
| `moodle/course:changecategory` | `core_course_update_courses`, field `categoryid` |
| `moodle/course:visibility` | `core_course_create_courses`, `core_course_update_courses`, field `visible` |
| `moodle/course:viewparticipants` | `core_enrol_get_enrolled_users` |
| `moodle/course:useremail` | `core_enrol_get_enrolled_users` (e-mail of the participants of a course) |
| `moodle/course:enrolreview` | `core_enrol_get_enrolled_users` (option `onlyactive`, enrolment status) |
| `moodle/category:manage` | `core_course_create_categories` |
| `moodle/category:viewhiddencategories` | `core_course_get_categories` for hidden categories |
| `moodle/role:assign` | `enrol_manual_enrol_users` (the role of the enrolment) |
| `moodle/site:accessallgroups` | `core_enrol_get_enrolled_users` (participants of every group) |
| `enrol/manual:enrol` | `enrol_manual_enrol_users` |
| `enrol/manual:unenrol` | `enrol_manual_unenrol_users` |
| `enrol/manual:manage` | `enrol_manual_enrol_users` (suspend, reactivate and dates of an enrolment) |
| `moodle/grade:viewall` | `gradereport_user_get_grade_items` |
| `gradereport/user:view` | `gradereport_user_get_grade_items` |
| `local/vcostaedu:requestloginurl` | `local_vcostaedu_request_login_url` |

Left out on purpose: `moodle/site:viewuseridentity`.

### Why a function of its own for the search by e-mail

A school that already has students in Moodle must link each account to its academic record,
and the e-mail address is often the only data both have. Without
`moodle/site:viewuseridentity`, Moodle leaves the e-mail out of the user data it returns
outside a course, so `core_user_get_users_by_field` and `core_user_get_users` find nobody by
e-mail. That capability, given at system level, would show to the key the identity fields
chosen in *Site administration > Users > Permissions > User policies > Show user identity*
(e-mail by default; the site may add phone, address, institution, department, id number and
profile fields) of every user of the site.

`local_vcostaedu_find_users_by_email` answers only what the link needs: for each address sent,
the ids of the accounts that have it (case and spaces at the ends ignored; accounts of this site
only, never deleted accounts or the guest; suspended accounts included; every account when the
site allows the same address twice, in id order). It shows no name, username, id number or
status, and no data that the key cannot already learn: on a site that does not allow two
accounts with the same address (the default), `core_user_create_users` refuses an address that
is taken. It checks `moodle/user:viewdetails`, the capability the core user search
functions check, which the role already has; it runs one database query per call and stores
nothing. Proof: PHPUnit `find_users_by_email_test`; e2e 1.43 and 1.44.

## Quick path: the Postman collection

The repository has a Postman collection, `extra/postman/local_vcostaedu.postman_collection.json`
(not in the package). Import it, fill in the Moodle address and the key of *Connect to
VCOSTAEDU*, and run it: it calls the 17 functions of the service in the order VCOSTAEDU uses
them, shows the refusals of the login link with their error codes, and checks every answer with
tests. How to use it: [extra/postman/README.md](../extra/postman/README.md).

## Testing without VCOSTAEDU

Everything VCOSTAEDU does is a plain REST call. After installing:

1. On *Connect to VCOSTAEDU*, click **1. Turn on web services and REST**, then **2. Generate
   key**, and copy the key. Below, `SITE` is the Moodle address and `KEY` the key.
2. Check the service:

   ```
   curl -s SITE/webservice/rest/server.php -d wstoken=KEY -d moodlewsrestformat=json \
        -d wsfunction=core_webservice_get_site_info
   ```

   The answer lists 17 functions, including `local_vcostaedu_request_login_url` and
   `local_vcostaedu_find_users_by_email`.
3. Create a student and ask for a link:

   ```
   curl -s SITE/webservice/rest/server.php -d wstoken=KEY -d moodlewsrestformat=json \
        -d wsfunction=core_user_create_users -d 'users[0][username]=student1' \
        -d 'users[0][firstname]=Student' -d 'users[0][lastname]=One' \
        -d 'users[0][email]=student1@example.com' -d 'users[0][password]=Student!123'

   curl -s SITE/webservice/rest/server.php -d wstoken=KEY -d moodlewsrestformat=json \
        -d wsfunction=local_vcostaedu_request_login_url -d username=student1 \
        --data-urlencode 'returnurl=/my/'
   ```

   The answer has `loginurl` and `validuntil`. Open `loginurl` in a private browser window:
   the student is logged in and lands on the dashboard. Open it again: the page says the link
   is not valid any more, with status 403, and no session is created.
4. Each refusal: ask for a link for `admin`, `guest`, `vcostaedu_ws`, a user that does not
   exist, a suspended user (`core_user_update_users` with `suspended=1`), a user with the
   "No login" authentication, an unconfirmed user. Each answers with its own error code (table
   below) and no key is created (table `user_private_key`, script `local_vcostaedu`).
5. Suspension after the link is issued: ask for a link, suspend the user, open the link. The
   page refuses and `/my/` still sends to the login page: no session was created.

The repository also has a scripted end-to-end run, `extra/e2e/e2e.sh` (not in the package),
which does all of this and more from a clean database dump.

## Security model of the login link

| Rule | How | Proof |
|---|---|---|
| Only holders of `local/vcostaedu:requestloginurl` ask for links | `require_capability()` in the external function; the capability is given only to the service role | PHPUnit `request_login_url_test::test_requires_capability`; e2e 1.33 |
| No key for accounts that must not enter | `login::check_user()` refuses missing, deleted, guest, site administrator, the service account, suspended, unconfirmed, `nologin` and disabled authentication, each with its own code, before creating a key | PHPUnit `login_test::test_check_user`, `request_login_url_test::test_refusals`; e2e 1.17, 1.22 to 1.29 |
| Single use | `login::consume_key()` validates with core `validate_user_key()` and deletes the key at once, under a lock, before anything else; an expired key is deleted too | PHPUnit `test_consume_key_once`, `test_consume_key_expired_and_unknown`; e2e 1.12, 1.15 |
| The account is checked again right before the session | `login.php` reads the user again from the database and calls `check_user()` before `complete_user_login()`; `login::complete_login()` checks once more on the complete record and after the login, and ends the session on any error | PHPUnit `test_complete_login_refuses_inactive_user`; e2e 1.18 (suspended after the link was issued: refused, no session, no login event) |
| A refused link never ends somebody else's session | refusals only show the 403 page; the session in the browser is left alone | e2e 1.13, 1.14, 1.16, 1.19 |
| A valid link of an account that can no longer enter ends that account's own session | `login::end_owner_session()` ends the session only when it belongs to the owner of the key | PHPUnit `test_end_owner_session`; e2e 1.20, 1.21 |
| A valid link with somebody else logged in replaces that session | the other session is ended with `require_logout()`, then the login goes on in a new request with a 30 second internal key | e2e 1.11 |
| Redirects only inside the site | `login::clean_returnurl()` keeps only local paths (no other host, no `//`, no backslash, no control characters, no `javascript:`), checked again with `PARAM_LOCALURL`; the target is always the site address plus the path | PHPUnit `test_clean_returnurl` (11 cases); e2e 1.30 to 1.32 |
| Everything is logged | events "Login link created", "Login link used" and "Login link refused" (with reason and stage) | PHPUnit `test_log_refused`, `test_refusal_is_logged`; e2e 1.39 |
| Admin page actions | `admin_externalpage_setup()` (requires `moodle/site:config`), a confirmation page, `require_sesskey()` and the capability checked again before acting; output escaped | e2e 1.5 to 1.7, 1.36 |

### Error codes of `local_vcostaedu_request_login_url`

| Code | Meaning |
|---|---|
| `vcostaedu_usernotfound` | No user with that username (users deleted by Moodle lose their username) |
| `vcostaedu_userdeleted` | The account is marked as deleted |
| `vcostaedu_usersuspended` | The account is suspended |
| `vcostaedu_usernotconfirmed` | The account is not confirmed |
| `vcostaedu_usernologin` | The account uses the "No login" authentication |
| `vcostaedu_authdisabled` | The authentication method of the account is turned off on the site |
| `vcostaedu_userisguest` | The guest account |
| `vcostaedu_userisadmin` | A site administrator |
| `vcostaedu_userisservice` | The service account `vcostaedu_ws` |
| `nopermissions` | The key has no `local/vcostaedu:requestloginurl` |

Reasons shown by `login.php` (status 403, no session): `invalidkey`, `expiredkey`, `busy`,
`loginfailed`, and the account reasons above without the prefix.

## Decisions out of the ordinary, and why

- **Role and user created on installation.** VCOSTAEDU needs a service account with exactly
  the capabilities it uses. Creating it on installation lets an administrator finish the setup
  in two clicks, without editing roles by hand. An existing role or user with the same names is
  reused and only gets what is missing.
- **Uninstall removes only what the plugin created.** The plugin records, in its own settings
  (`createdroleid`, `createduserid`), the role and the user it created. `db/uninstall.php`
  removes those two, plus pending login keys; a role or user that existed before the plugin is
  left on the site. Moodle itself removes the service, its keys, the capability and the
  settings. Proof: PHPUnit `test_uninstall_removes_created_items`,
  `test_uninstall_keeps_preexisting_items`; e2e 3.1 and 4.1.
- **Name conflict checked before installing.** Service names are unique in Moodle. The plugin's
  `environment.xml` declares a required custom check
  (`local_vcostaedu\environment::check_service_name`), so the server check page and the command
  line upgrade stop before anything is created, with a message saying what to do. The
  installation repeats the check as a second barrier. Proof: PHPUnit
  `test_service_name_conflict`; e2e 2.1 to 2.3.
- **Site administrators never enter through a link.** A link is a bearer credential; issuing it
  for an administrator would turn the VCOSTAEDU key into a site administration key.
- **`login.php` does not call `require_login()`.** It is a login page: the visitor is not
  logged in yet, and the one-time key is what authorises the request. The phpcs rule is
  silenced only on the `config.php` line, with that reason.
- **Students with manual authentication.** VCOSTAEDU creates students as manual accounts. Any
  enabled authentication method works; disabled or `nologin` methods are refused.
- **No JavaScript of its own.** The copy buttons use the core module `core/copy_to_clipboard`
  and the key field uses the core `core/togglesensitive` toggle.

## E-mail and enrolments

When a course has a teacher, the core manual enrolment plugin sends the course welcome message
to the student on **every** enrolment call (the hook `after_user_enrolled` fires on each
`enrol_user()` call, including suspending, reactivating or repeating an enrolment). If Moodle
cannot send e-mail, `enrol_manual_enrol_users` saves the change and the role and still answers
with the error "Message was not sent." (`lib/classes/message/manager.php`). Tested on Moodle
4.5.16 and 5.3.1, with the same results, without an SMTP server and without a sendmail program:

- course without a teacher: no error, changes saved;
- course with a teacher: "Message was not sent." on the first enrolment, on suspending, on
  reactivating and on repeating; every change saved;
- e-mail message output turned off: no error, changes saved;
- `noemailever` on: no error, changes saved.

The plugin only warns and explains this on the connect page (set up outgoing mail, or turn off
the welcome message of manual enrolments). It does not change these settings.

## Moodle versions

One code base serves Moodle 4.5 to 5.3. Where the core API differs, the plugin checks once,
in one method, whether this Moodle has the newer method, and says in a comment from which
version it exists:

| What | Moodle 5.3 | Moodle 4.5 to 5.2 | Where |
|---|---|---|---|
| Create the service user | `\core\user::create_user()` (`user_create_user()` is deprecated) | `user_create_user()` | `setup::create_user()` |
| Delete the user the plugin created | `\core\user::delete_user()` (`delete_user()` is deprecated) | `delete_user()` | `setup::delete_user()` |
| Is the session still open after a logout in the same request? | `\core\session\manager::is_session_active()` | PHP session status: the session manager closes the PHP session whenever it marks it inactive | `login::session_is_open()` |
| Plugin folder | `public/local/vcostaedu` (5.1 and later) | `local/vcostaedu` (4.5 and 5.0) | install documents |

Everything else the plugin uses is the same on every version, checked in the code of each:
the core modules `core/copy_to_clipboard` and `core/togglesensitive` and the template
`core/form_input_toggle_sensitive`; the environment check through a class method in
`environment.xml` (run on the server check page and by `admin/cli/upgrade.php`); the privacy
API with `add_external_location_link()` and the `core_userkey` subsystem link; user keys
(`create_user_key()`, `validate_user_key()`), `complete_user_login()`, `require_logout()`
and the lock API; the 15 core web service functions and the 28 core capabilities; the welcome
message of manual enrolments and the answer "Message was not sent.".

The tests carry both forms of PHPUnit metadata: doc-comment tags (`@covers`,
`@dataProvider`), read by PHPUnit 9 on Moodle 4.5, and attributes, read by PHPUnit 11 on
Moodle 5.0 and later, which then ignores the tags. No deprecation is reported on either. The
attributes come first and the docblock right before the class or method: on Moodle 4.5 the
code checker reads the coverage only from a docblock that touches the class declaration.

## What was tested

- Moodle 4.5.16 with PHP 8.1 and 8.3, Moodle 5.0.12 and 5.1.9 with PHP 8.2, Moodle 5.3.1 with
  PHP 8.3; MariaDB 11.4 and PostgreSQL 17.
- The test tool `moodle-plugin-ci`, tool version 4.5.11 (not a Moodle version), installed with
  each PHP as the GitHub workflow does, run with the plugin inside the Moodle code: phplint,
  phpcs (no errors, no warnings), phpdoc, validate, savepoints, mustache, grunt (no files to
  process) and PHPUnit on Moodle 4.5 and 5.3; phpcs and PHPUnit on Moodle 5.0 and 5.1.
- End-to-end run from a clean database (`extra/e2e/e2e.sh`), with developer debugging on: no
  PHP notice, warning or error on any page.
- Installation through *Install plugins* in a headless browser, in English and in Brazilian
  Portuguese, and the show/copy buttons of the key.
- The Moodle validator of *Install plugins* accepts both zips on 4.5 and 5.3.
- `phpcs` with PHPCompatibility for PHP 8.1 to 8.3: no findings.
- Moodle 5.2.5: PHPUnit on MariaDB.

| Moodle | PHP | PHPUnit | MariaDB 11.4 | PostgreSQL 17 | moodle-plugin-ci | End-to-end, browser |
|---|---|---|---|---|---|---|
| 4.5.16 | 8.1 | 9.6 | PHPUnit | PHPUnit | all steps | - |
| 4.5.16 | 8.3 | 9.6 | PHPUnit | PHPUnit | all steps | both zips |
| 5.0.12 | 8.2 | 11 | PHPUnit | - | phpcs | - |
| 5.0.12 | 8.3 | 11 | PHPUnit | - | - | - |
| 5.1.9 | 8.2 | 11 | PHPUnit | - | phpcs | - |
| 5.1.9 | 8.3 | 11 | PHPUnit | - | - | - |
| 5.2.5 | 8.3 | 11 | PHPUnit | - | - | - |
| 5.3.1 | 8.3 | 11 | PHPUnit | PHPUnit | all steps | both zips |

## What was not tested

- PHP 8.4 and later, PostgreSQL on Moodle 5.0 to 5.2, Microsoft SQL Server.
- Behat (there is no browser driver in the test environment).
- The GitHub workflow in `.github/workflows/` ran once before the change of the test
  attributes (one job failed on the style check: PHP 8.1, Moodle 4.5); the same
  combinations were run here after the change, but the workflow itself has not run again.
