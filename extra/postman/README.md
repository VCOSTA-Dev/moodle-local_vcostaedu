# Requests for reviewers (Postman collection)

`local_vcostaedu.postman_collection.json` is a Postman collection (format v2.1) that calls the
web service "VCOSTAEDU" (short name `local_vcostaedu`) the way VCOSTAEDU does. It shows the
service working and refusing what it must refuse, without reading any code. Every request
checks its own answer with tests.

The collection is not part of the plugin packages; it is a tool for whoever reviews the plugin.

## Before you start

On the Moodle site, as an administrator, open *Site administration > Plugins > Local plugins >
VCOSTAEDU > Connect to VCOSTAEDU*, follow the diagnosis (turn on web services and REST, generate
the key) and copy two items from the block "Data to paste into VCOSTAEDU":

- the **Moodle address**;
- the **key**.

Use a test site. The collection creates test items (see "What stays on the site").

## Import and fill in two values

1. In Postman, *Import* the file `local_vcostaedu.postman_collection.json`.
2. Open the collection, tab *Variables*, and fill in the two values:
   - `baseurl`: the Moodle address, without a slash at the end, for example
     `https://moodle.example.edu`;
   - `token`: the key.
3. Save. Nothing else needs to be prepared.

Every request is a POST to `{{baseurl}}/webservice/rest/server.php` with a JSON answer. The key
travels in the body of the request, never in the address.

## The folders, in order

1. **Check the connection**: site information; proves the address and the key, shows the
   Moodle version and the 17 functions of the service.
2. **Full cycle**: category, course (created, renamed, moved to another category), teacher and
   student (created, found by username, id number, e-mail and name, updated), enrolments,
   participants, courses of the student, grades and the one-time login link (with a course as
   the return address, and with an outside address that is dropped).
3. **Refusals**: the login link refused for an unknown user, a suspended account, the
   authentication "No login", a switched-off authentication method, the guest, the site
   administrator and the service account, each with its own error code, a malformed
   username and a malformed e-mail address (`invalidparameter`). The folder creates the
   accounts it needs.
4. **Enrolment changes**: a second role, suspension and reactivation of the enrolment,
   unenrolment and enrolment with another role. The service has no group functions.
5. **Wrong key**: the call of folder 1 with a key that does not exist; expects `invalidtoken`.
6. **Clean up**: unenrols the test users, hides the test course and suspends the test
   accounts.

Folders 4 and 6 use what folder 2 creates, and folder 6 also uses the accounts of folder 3. Run
the folders in this order, or the whole collection at once.

The collection uses the standard role ids of a Moodle installation (student 5, non-editing
teacher 4, teacher 3) and the administrator created at installation (user id 2).

## Run everything at once

- **In Postman**: open the collection, *Run*, keep the order and start. The runner shows each
  test.
- **On the command line**, with newman (needs Node.js; `npx` downloads newman for that run):

  ```
  npx newman run local_vcostaedu.postman_collection.json \
      --env-var "baseurl=https://moodle.example.edu" --env-var "token=<the key>"
  ```

  The key then goes to the shell history; clear it afterwards, or keep both values in a
  Postman environment file outside any repository and pass it with `-e <file>`.

Each run uses a new suffix in every name it creates, so the collection can run many times on
the same site.

## What stays on the site

The service cannot delete users, courses or categories. After folder 6 the test items stay on
the site, hidden or suspended, and are easy to recognise:

- category and course: names and id numbers that start with `VCOSTAEDU-TEST`;
- users: usernames that start with `vcostaedutest.`, e-mail addresses in the reserved domain
  `example.com`.

A site administrator can delete them in Moodle: *Site administration > Courses > Manage courses
and categories*, and *Site administration > Users > Bulk user actions*.

## Search by e-mail

Accounts are found by e-mail with the plugin's own function
`local_vcostaedu_find_users_by_email`: it receives 1 to 100 addresses and answers, for each one,
only the ids of the accounts that have it (case and spaces at the ends ignored). The core
functions cannot do it with this key: the role does not have `moodle/site:viewuseridentity`, on
purpose, because that capability shows the identity fields of every user of the site.

## The e-mail warning

When a course has a teacher, Moodle sends the course welcome message to the student on every
manual enrolment call (enrol, suspend, reactivate, enrol again). If the site cannot send e-mail,
Moodle saves the change and still answers with the error "Message was not sent.". The tests
treat that answer as a warning, not a failure, write a note in the console, and the next request
checks that the change was saved.

## Tested on

Moodle 4.5 and 5.3, with newman 6, twice in a row on each site: no failed test.
