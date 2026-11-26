# Changes

## 1.0.0-rc2 (2026111700)

First public version.

- Installation creates the role `vcostaedu_ws`, the service user `vcostaedu_ws`, the role
  assignment and the external service "VCOSTAEDU" (short name `local_vcostaedu`) with 17
  functions. The role has only the capabilities those functions use, so VCOSTAEDU can create
  and update users, categories, courses (including their name, short name, id number, summary
  and category) and enrolments. An existing role or user with those names is reused. Running
  the setup again duplicates nothing.
- Before installing, an environment check stops the installation when another service
  already uses the name "VCOSTAEDU" or the short name `local_vcostaedu`.
- Uninstalling removes the external service with its keys, the pending login links, and the
  role and the user the plugin created, and leaves a role or user that existed before the
  plugin.
- Capability `local/vcostaedu:requestloginurl`.
- Page "Connect to VCOSTAEDU": item by item diagnosis, buttons to turn on web services and
  REST, fix the role and service, and generate or replace the key; numbered steps when more
  than one action is pending; the Moodle address and the key with copy buttons (core
  modules `core/copy_to_clipboard` and `core/togglesensitive`).
- Web service function `local_vcostaedu_request_login_url` and page `login.php`: one-time
  login links that refuse accounts that must not enter, check the account again right before
  the session is created, never end somebody else's session on a refused link, and only
  redirect inside the site.
- Web service function `local_vcostaedu_find_users_by_email`: the ids of the accounts that have
  each e-mail address (1 to 100 per call), to link accounts that already exist to the academic
  record. Only ids come out; the role needs no identity fields.
- Events: login link created, used and refused.
- Privacy provider: login keys and the external system VCOSTAEDU.
- English strings in the package; Brazilian Portuguese available in a separate package.
- Works on Moodle 4.5 to 5.3 with one code base: where the core API changed (creating and
  deleting a user, checking whether the session is still open), the plugin checks which
  method this Moodle has.
- Tested on Moodle 4.5 and 5.3 with MariaDB and PostgreSQL, and on 5.0, 5.1 and 5.2 with
  MariaDB.
