#!/usr/bin/env bash
# This file is part of Moodle - https://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
#
# End-to-end tests of local_vcostaedu. TEST SITES ONLY: every block restores a clean
# database dump, installs the plugin from the zip, creates and deletes users and courses,
# and the run ends with the clean dump restored.
#
# Runs on the Moodle server, as root (uses sudo -u <web server user>), with a MySQL or
# MariaDB site. Environment variables (no key is ever written to a file):
#   MOODLE_URL    address of the site, e.g. https://moodle.example.edu
#   MOODLE_DIR    Moodle code folder (the one with config.php and admin/cli)
#   PLUGIN_ZIP    plugin package to install
#   CLEAN_DUMP    database dump of the site without the plugin (the starting point)
#   DB_NAME       site database name
#   WEB_USER      optional, web server user (default www-data)
#   PLUGIN_DIR    optional, where the plugin goes (default: public/local/vcostaedu or local/vcostaedu)
#
# @package    local_vcostaedu
# @copyright  2026 VCOSTA
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

set -u

: "${MOODLE_URL:?set MOODLE_URL}"
: "${MOODLE_DIR:?set MOODLE_DIR}"
: "${PLUGIN_ZIP:?set PLUGIN_ZIP}"
: "${CLEAN_DUMP:?set CLEAN_DUMP}"
: "${DB_NAME:?set DB_NAME}"
WEB_USER="${WEB_USER:-www-data}"
MOODLE_URL="${MOODLE_URL%/}"
if [ -z "${PLUGIN_DIR:-}" ]; then
    if [ -d "$MOODLE_DIR/public/local" ]; then PLUGIN_DIR="$MOODLE_DIR/public/local/vcostaedu"; else PLUGIN_DIR="$MOODLE_DIR/local/vcostaedu"; fi
fi
LOCAL_DIR="$(dirname "$PLUGIN_DIR")"

HERE="$(cd "$(dirname "$0")" && pwd)"
TMP="$(mktemp -d)"
chmod 755 "$TMP"
cp "$HERE/helper.php" "$TMP/helper.php"
chmod 644 "$TMP/helper.php"
trap 'rm -rf "$TMP"' EXIT

PASSED=0
FAILED=0
NOTICES=0
TS="$(date +%s)"
STUDENT="e2e.student$TS"
CLASSMATE="e2e.classmate$TS"
ADMIN_USER="e2e_admin"
ADMIN_PASS="E2e!$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)9a"
TOKEN=""

helper() {
    sudo -u "$WEB_USER" env E2E_VCOSTAEDU=1 MOODLE_DIR="$MOODLE_DIR" E2E_ADMIN_PASS="$ADMIN_PASS" php "$TMP/helper.php" "$@" 2>/dev/null
}

# ws <token> <function> [curl parameters...]
ws() {
    local token="$1" func="$2"
    shift 2
    curl -s "$MOODLE_URL/webservice/rest/server.php" \
        --data-urlencode "wstoken=$token" -d "wsfunction=$func" -d moodlewsrestformat=json "$@"
}

# json <python expression on d> < input
json() { python3 -c "import sys,json
try:
    d=json.load(sys.stdin)
except Exception:
    d=None
try:
    v=($1)
except Exception:
    v=''
print(v if v is not None else '')"; }

result() {
    local name="$1" ok="$2" detail="$3"
    # No key ever appears in the output.
    detail="$(echo "$detail" | sed -E 's/(key|wstoken|token)=[0-9A-Za-z]{16,}/\1=***/g')"
    if [ "$ok" = "1" ]; then
        PASSED=$((PASSED + 1))
        printf 'PASS  %-72s %s\n' "$name" "$detail"
    else
        FAILED=$((FAILED + 1))
        printf 'FAIL  %-72s %s\n' "$name" "$detail"
    fi
}

# Counts PHP notices shown on a page (developer debugging is on during the tests).
check_notices() {
    local n
    n="$(grep -c -E 'debuggingmessage|Notice: |Warning: |Deprecated: |Fatal error' "$1" 2>/dev/null || true)"
    NOTICES=$((NOTICES + ${n:-0}))
}

# open <link> <cookies>: prints "code target" without following the redirect.
open_link() {
    local r
    r="$(curl -s -o "$TMP/page.html" -c "$2" -b "$2" -w '%{http_code} %{redirect_url}' "$1")"
    check_notices "$TMP/page.html"
    echo "$r"
}

# dashboard <cookies>: code of /my/ (200 = logged in; 303 to the login page = no session).
dashboard() {
    local r
    r="$(curl -s -o "$TMP/my.html" -c "$1" -b "$1" -w '%{http_code} %{redirect_url}' "$MOODLE_URL/my/")"
    check_notices "$TMP/my.html"
    echo "$r"
}

refusal() { grep -o 'data-local-vcostaedu-refused="[a-z]*"' "$TMP/page.html" | cut -d'"' -f2; }

ask_link() { ws "$TOKEN" local_vcostaedu_request_login_url --data-urlencode "username=$1" "${@:2}"; }

no_session() {
    case "$(dashboard "$1")" in
        3*"/login/index.php"*) echo 1 ;;
        *) echo 0 ;;
    esac
}

intact() {
    # The session of whoever was logged in goes on: /my/ opens (200) and shows that person.
    local r
    r="$(dashboard "$1")"
    if [ "${r%% *}" = "200" ] && grep -q "$2" "$TMP/my.html"; then echo 1; else echo 0; fi
}

reset_clean() {
    rm -rf "$PLUGIN_DIR"
    mariadb "$DB_NAME" < "$CLEAN_DUMP"
    sudo -u "$WEB_USER" php "$MOODLE_DIR/admin/cli/purge_caches.php" > /dev/null 2>&1
}

developer_debug() {
    helper set-config debug 32767 > /dev/null
    helper set-config debugdisplay 1 > /dev/null
}

# Installs the plugin from the zip as an administrator copying it would, then runs the upgrade.
install_zip() {
    local owner
    rm -rf "$TMP/unzip" "$PLUGIN_DIR"
    mkdir -p "$TMP/unzip" && unzip -q "$PLUGIN_ZIP" -d "$TMP/unzip"
    cp -r "$TMP/unzip/vcostaedu" "$PLUGIN_DIR"
    owner="$(stat -c '%U:%G' "$LOCAL_DIR")"
    chown -R "$owner" "$PLUGIN_DIR"
    find "$PLUGIN_DIR" -type d -exec chmod 755 {} + ; find "$PLUGIN_DIR" -type f -exec chmod 644 {} +
    sudo -u "$WEB_USER" php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive > "$TMP/upgrade.txt" 2>&1
    echo $?
}

upgrade_notices() { grep -c -i -E 'notice|warning|deprecated|debugging' "$TMP/upgrade.txt" || true; }

uninstall() {
    sudo -u "$WEB_USER" php "$MOODLE_DIR/admin/cli/uninstall_plugins.php" --plugins=local_vcostaedu --run > "$TMP/uninstall.txt" 2>&1
    echo $?
}

functions_count() { ws "$1" core_webservice_get_site_info | json "len(d['functions'])"; }

admin_login() {
    local t
    rm -f "$TMP/adm.jar"
    # Moodle 4.5 prints the login form token twice on the page; the first one is the form's.
    t="$(curl -s -c "$TMP/adm.jar" -b "$TMP/adm.jar" "$MOODLE_URL/login/index.php" | grep -o 'name="logintoken" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')"
    curl -s -o /dev/null -c "$TMP/adm.jar" -b "$TMP/adm.jar" --data-urlencode "username=$ADMIN_USER" \
        --data-urlencode "password=$ADMIN_PASS" --data-urlencode "logintoken=$t" "$MOODLE_URL/login/index.php"
}
connect_page() {
    curl -s -b "$TMP/adm.jar" -c "$TMP/adm.jar" "$MOODLE_URL/local/vcostaedu/connect.php" > "$TMP/connect.html"
    check_notices "$TMP/connect.html"
}
button() {
    # Class of the button of an action on the page (btn-primary = highlighted), or "missing".
    python3 - "$1" "$TMP/connect.html" <<'PY'
import re, sys
s = open(sys.argv[2], encoding='utf-8').read()
m = re.search(r'local-vcostaedu-action-' + sys.argv[1] + r'[^"]*">.*?<button type="submit" class="btn ([a-z-]+)', s, re.S)
print(m.group(1) if m else 'missing')
PY
}
steps() {
    # How many steps the numbered list has, and which buttons carry a number.
    python3 - "$TMP/connect.html" <<'PY'
import html, re, sys
s = open(sys.argv[1], encoding='utf-8').read()
m = re.search(r'<ol class="local-vcostaedu-steps">(.*?)</ol>', s, re.S)
n = len(re.findall(r'<li>', m.group(1))) if m else 0
labels = [html.unescape(re.sub(r'\s+', ' ', b)).strip()
          for b in re.findall(r'<button type="submit" class="btn [a-z-]+"[^>]*>(.*?)</button>', s, re.S)]
numbered = [x for x in labels if re.match(r'^\d+\. ', x)]
print('steps=%d buttons=[%s]' % (n, '|'.join(numbered)))
PY
}
click() {
    local sk
    sk="$(grep -o '"sesskey":"[^"]*"' "$TMP/connect.html" | head -1 | cut -d'"' -f4)"
    curl -s -o /dev/null -b "$TMP/adm.jar" -c "$TMP/adm.jar" -w '%{http_code}' --data "sesskey=$sk" \
        "$MOODLE_URL/local/vcostaedu/connect.php?action=$1&confirm=1"
}

create_user() {
    ws "$TOKEN" core_user_create_users -d "users[0][username]=$1" -d "users[0][firstname]=$2" \
        -d "users[0][lastname]=Example" -d "users[0][email]=$1@example.com" \
        --data-urlencode "users[0][password]=E2e!$TS-aA1" | json "d[0]['id']"
}
expect_error() {
    local name="$1" username="$2" expected="$3" code
    code="$(ask_link "$username" | json "d.get('errorcode')")"
    result "$name" "$([ "$code" = "$expected" ] && echo 1)" "error=$code"
}

echo "== local_vcostaedu end-to-end tests, $(date '+%Y-%m-%d %H:%M:%S'), package $(basename "$PLUGIN_ZIP") =="

# ---------------------------------------------------------------------------------------------
# Block 1. Installation from scratch, the connect page, and the login link rules.
reset_clean
developer_debug
helper create-admin "$ADMIN_USER" en > /dev/null
WS0="$(helper ws-config)"
RC="$(install_zip)"
ST="$(helper state)"
PL="$(helper plugin)"
result "1.1 install from the zip on a site without the plugin" "$([ "$RC" = "0" ] && [ "$(upgrade_notices)" = "0" ] && echo 1)" \
    "upgrade=$RC notices_in_upgrade=$(upgrade_notices) $PL"
result "1.2 creates role, user, assignment and service; records what it created" \
    "$(echo "$ST" | grep -q 'role=1 capabilities=29 assignable=3 user=1 assignments=1 services=1 functions=17 authorised=1 keys=0' \
        && echo "$PL" | grep -q 'createdrole=1 createduser=1' && echo 1)" "$ST"
result "1.3 does not switch on web services or REST" "$([ "$(helper ws-config)" = "$WS0" ] && echo 1)" "$WS0"
D="$(helper diag)"
result "1.4 diagnosis: web services, REST and key missing, each with its button" \
    "$(echo "$D" | grep -q 'webservices=error->enablews rest=error->enablews' && echo "$D" | grep -q 'token=error->generatekey' && echo 1)" "$D"
admin_login
connect_page
P="$(steps)"
result "1.5 two actions pending: numbered list in order and numbered buttons" \
    "$([ "$P" = "steps=2 buttons=[1. Turn on web services and REST|2. Generate key]" ] && [ "$(button fix)" = "btn-secondary" ] && echo 1)" \
    "$P fix=$(button fix)"
C="$(click enablews)"
connect_page
P="$(steps)"
result "1.6 \"Turn on web services and REST\" works, then disappears; one action left: no list" \
    "$([ "$(helper ws-config)" = "enablewebservices=1 protocols=rest" ] && [ "$(button enablews)" = "missing" ] \
        && echo "$P" | grep -q '^steps=0 buttons=\[\]' && echo 1)" "click=$C $(helper ws-config) $P"
C="$(click generatekey)"
connect_page
TOKEN="$(grep -o 'id="local_vcostaedu_token" [^>]*value="[0-9a-f]*"' "$TMP/connect.html" | grep -o 'value="[0-9a-f]*"' | cut -d'"' -f2)"
P="$(steps)"
result "1.7 \"Generate key\" creates the key; the page shows it; no list; new key button is red" \
    "$([ -n "$TOKEN" ] && echo "$P" | grep -q '^steps=0' && [ "$(button newkey)" = "btn-danger" ] \
        && grep -q 'data-action="copytoclipboard"' "$TMP/connect.html" && echo 1)" \
    "click=$C key_on_page=$([ -n "$TOKEN" ] && echo yes || echo no) newkey=$(button newkey)"
N="$(functions_count "$TOKEN")"
result "1.8 core_webservice_get_site_info with the key lists the 17 functions" "$([ "$N" = "17" ] && echo 1)" "functions=$N"

# Full cycle.
R="$(ws "$TOKEN" core_course_create_categories -d "categories[0][name]=E2E $TS" -d "categories[0][idnumber]=E2E-CAT-$TS")"
CAT="$(echo "$R" | json "d[0]['id']")"
R="$(ws "$TOKEN" core_course_create_courses -d "courses[0][fullname]=E2E course $TS" -d "courses[0][shortname]=E2E-$TS" \
    -d "courses[0][idnumber]=E2E-$TS" -d "courses[0][categoryid]=$CAT")"
COURSE="$(echo "$R" | json "d[0]['id']")"
STUDENTID="$(create_user "$STUDENT" "Student$TS")"
CLASSMATEID="$(create_user "$CLASSMATE" "Classmate$TS")"
ws "$TOKEN" enrol_manual_enrol_users -d "enrolments[0][roleid]=5" -d "enrolments[0][userid]=$STUDENTID" \
    -d "enrolments[0][courseid]=$COURSE" > /dev/null
ENROLLED="$(ws "$TOKEN" core_enrol_get_enrolled_users -d "courseid=$COURSE" | json "int(any(u['id']==$STUDENTID for u in d))")"
FOUND="$(ws "$TOKEN" core_course_get_courses_by_field -d field=idnumber -d "value=E2E-$TS" | json "d['courses'][0]['id']")"
LINK="$(ask_link "$STUDENT" | json "d['loginurl']")"
result "1.9 cycle: category, course with idnumber, student, enrol, check, link" \
    "$([ -n "$CAT" ] && [ "$FOUND" = "$COURSE" ] && [ -n "$STUDENTID" ] && [ "$ENROLLED" = "1" ] && [ -n "$LINK" ] && echo 1)" \
    "category=$CAT course=$COURSE student=$STUDENTID enrolled=$ENROLLED"

J1="$TMP/c1.jar"
R="$(open_link "$LINK" "$J1")"
P="$(dashboard "$J1")"
result "1.10 a valid link opens the student session (/my/ logged in)" \
    "$([ "${P%% *}" = "200" ] && grep -q "Student$TS" "$TMP/my.html" && echo 1)" "login.php=${R%% *} /my/=${P%% *} sessions=$(helper sessions "$STUDENT")"

LINK2="$(ask_link "$CLASSMATE" | json "d['loginurl']")"
R1="$(open_link "$LINK2" "$J1")"
R2="$(open_link "${R1#* }" "$J1")"
P="$(dashboard "$J1")"
S1="$(helper sessions "$STUDENT")"
result "1.11 valid link with somebody else logged in: that session ends, the classmate enters" \
    "$([ "${P%% *}" = "200" ] && grep -q "Classmate$TS" "$TMP/my.html" && [ "$S1" = "0" ] && echo 1)" \
    "1st=${R1%% *} 2nd=${R2%% *} /my/=${P%% *} student_sessions=$S1"

J2="$TMP/c2.jar"
R="$(open_link "$LINK" "$J2")"
MOTIVE="$(refusal)"
result "1.12 the same link again: refused, no session" "$([ "${R%% *}" = "403" ] && [ "$MOTIVE" = "invalidkey" ] && [ "$(no_session "$J2")" = "1" ] && echo 1)" \
    "login.php=${R%% *} reason=$MOTIVE"
R="$(open_link "$LINK" "$J1")"
MOTIVE="$(refusal)"
result "1.13 used link with somebody else logged in: refused, that session goes on" \
    "$([ "${R%% *}" = "403" ] && [ "$MOTIVE" = "invalidkey" ] && [ "$(intact "$J1" "Classmate$TS")" = "1" ] && echo 1)" "reason=$MOTIVE"
R="$(open_link "$MOODLE_URL/local/vcostaedu/login.php?key=00000000000000000000000000000000" "$J1")"
MOTIVE="$(refusal)"
result "1.14 unknown link with somebody else logged in: refused, that session goes on" \
    "$([ "${R%% *}" = "403" ] && [ "$MOTIVE" = "invalidkey" ] && [ "$(intact "$J1" "Classmate$TS")" = "1" ] && echo 1)" "reason=$MOTIVE"

helper set-validity 15 > /dev/null
R="$(ask_link "$STUDENT")"
LINK="$(echo "$R" | json "d['loginurl']")"
LINKB="$(ask_link "$STUDENT" | json "d['loginurl']")"
DUR="$(( $(echo "$R" | json "d['validuntil']") - $(date +%s) ))"
sleep 17
J3="$TMP/c3.jar"
R="$(open_link "$LINK" "$J3")"
MOTIVE="$(refusal)"
result "1.15 expired link (validity 15 s, waited 17 s): refused, no session" \
    "$([ "$MOTIVE" = "expiredkey" ] && [ "$(no_session "$J3")" = "1" ] && echo 1)" "validity=${DUR}s reason=$MOTIVE"
R="$(open_link "$LINKB" "$J1")"
MOTIVE="$(refusal)"
helper set-validity 60 > /dev/null
result "1.16 expired link with somebody else logged in: refused, that session goes on" \
    "$([ "$MOTIVE" = "expiredkey" ] && [ "$(intact "$J1" "Classmate$TS")" = "1" ] && echo 1)" "reason=$MOTIVE"

ws "$TOKEN" core_user_update_users -d "users[0][id]=$STUDENTID" -d "users[0][suspended]=1" > /dev/null
BEFORE="$(helper count-keys "$STUDENT")"
CODE="$(ask_link "$STUDENT" | json "d['errorcode']")"
result "1.17 suspended user: no link, own error code" \
    "$([ "$CODE" = "vcostaedu_usersuspended" ] && [ "$BEFORE" = "$(helper count-keys "$STUDENT")" ] && echo 1)" "error=$CODE"

ws "$TOKEN" core_user_update_users -d "users[0][id]=$STUDENTID" -d "users[0][suspended]=0" > /dev/null
sleep 1
START="$(date +%s)"
LINK="$(ask_link "$STUDENT" | json "d['loginurl']")"
ws "$TOKEN" core_user_update_users -d "users[0][id]=$STUDENTID" -d "users[0][suspended]=1" > /dev/null
J4="$TMP/c4.jar"
R="$(open_link "$LINK" "$J4")"
MOTIVE="$(refusal)"
P="$(dashboard "$J4")"
EVENTS="$(helper events "$STUDENT" "$START")"
OK=0
case "$P" in 3*"/login/index.php"*) [ "$MOTIVE" = "usersuspended" ] && [ "$(helper sessions "$STUDENT")" = "0" ] \
    && ! echo "$EVENTS" | grep -q user_loggedin && OK=1 ;; esac
result "1.18 suspended between asking and opening: refused, /my/ goes to the login page" "$OK" \
    "login.php=${R%% *} reason=$MOTIVE /my/=${P%% *} events=[$EVENTS]"
ws "$TOKEN" core_user_update_users -d "users[0][id]=$STUDENTID" -d "users[0][suspended]=0" > /dev/null

LINK="$(ask_link "$STUDENT" | json "d['loginurl']")"
ws "$TOKEN" core_user_update_users -d "users[0][id]=$STUDENTID" -d "users[0][suspended]=1" > /dev/null
R="$(open_link "$LINK" "$J1")"
MOTIVE="$(refusal)"
result "1.19 the same, with somebody else logged in: refused, that session goes on" \
    "$([ "$MOTIVE" = "usersuspended" ] && [ "$(intact "$J1" "Classmate$TS")" = "1" ] && [ "$(helper sessions "$STUDENT")" = "0" ] && echo 1)" \
    "reason=$MOTIVE"
ws "$TOKEN" core_user_update_users -d "users[0][id]=$STUDENTID" -d "users[0][suspended]=0" > /dev/null

# The owner of the link is logged in.
JA="$TMP/ca.jar"
open_link "$(ask_link "$STUDENT" | json "d['loginurl']")" "$JA" > /dev/null
USED="$(ask_link "$STUDENT" | json "d['loginurl']")"
open_link "$USED" "$JA" > /dev/null
R="$(open_link "$USED" "$JA")"
MOTIVE="$(refusal)"
result "1.20 used link opened by its own owner, logged in: refused, the session goes on" \
    "$([ "$MOTIVE" = "invalidkey" ] && [ "$(intact "$JA" "Student$TS")" = "1" ] && echo 1)" "reason=$MOTIVE"
LINK="$(ask_link "$STUDENT" | json "d['loginurl']")"
# Suspended straight in the database: Moodle itself does not end the session then.
helper set-field "$STUDENT" suspended=1 > /dev/null
ALIVE="$(intact "$JA" "Student$TS")"
R="$(open_link "$LINK" "$JA")"
[ "${R%% *}" = "303" ] && R2="$(open_link "${R#* }" "$JA")" || R2="$R"
MOTIVE="$(refusal)"
P="$(dashboard "$JA")"
OK=0
case "$P" in 3*"/login/index.php"*) [ "$ALIVE" = "1" ] && [ "${R2%% *}" = "403" ] && [ "$MOTIVE" = "usersuspended" ] \
    && [ "$(helper sessions "$STUDENT")" = "0" ] && OK=1 ;; esac
result "1.21 owner logged in and now suspended opens a valid link: refused, own session ended" "$OK" \
    "session_before=$ALIVE page=${R2%% *} reason=$MOTIVE /my/=${P%% *}"
helper set-field "$STUDENT" suspended=0 > /dev/null

GONE="e2e.gone$TS"
create_user "$GONE" "Gone" > /dev/null
helper delete-user "$GONE" > /dev/null
expect_error "1.22 user deleted by Moodle (username is gone): no link" "$GONE" "vcostaedu_usernotfound"
GHOST="e2e.ghost$TS"
helper make-deleted-record "$GHOST" > /dev/null
expect_error "1.23 deleted record that kept its username: no link" "$GHOST" "vcostaedu_userdeleted"
expect_error "1.24 unknown user: no link" "e2e.nobody$TS" "vcostaedu_usernotfound"
expect_error "1.25 site administrator: no link" "admin" "vcostaedu_userisadmin"
expect_error "1.26 guest: no link" "guest" "vcostaedu_userisguest"
expect_error "1.27 service account vcostaedu_ws: no link" "vcostaedu_ws" "vcostaedu_userisservice"
NOL="e2e.nologin$TS"
create_user "$NOL" "Nologin" > /dev/null
helper set-field "$NOL" auth=nologin > /dev/null
expect_error "1.28 nologin authentication: no link" "$NOL" "vcostaedu_usernologin"
UNC="e2e.unconfirmed$TS"
create_user "$UNC" "Unconfirmed" > /dev/null
helper set-field "$UNC" confirmed=0 > /dev/null
expect_error "1.29 unconfirmed account: no link" "$UNC" "vcostaedu_usernotconfirmed"

for EXT in "https://evil.example.com/steal" "//evil.example.com/x" "/\\evil.example.com" "javascript:alert(1)"; do
    LINK="$(ask_link "$STUDENT" --data-urlencode "returnurl=$EXT" | json "d['loginurl']")"
    J5="$TMP/c5.jar"; rm -f "$J5"
    R="$(open_link "$LINK" "$J5")"
    result "1.30 external return address ignored: $EXT" \
        "$([ "${R#* }" = "$MOODLE_URL/my/" ] && ! echo "$LINK" | grep -q 'returnurl=' && echo 1)" "target=${R#* }"
done
LINK="$(ask_link "$STUDENT" | json "d['loginurl']")"
J5="$TMP/c5.jar"; rm -f "$J5"
R="$(open_link "$LINK&returnurl=https%3A%2F%2Fevil.example.com%2F" "$J5")"
result "1.31 external return address added to the link by hand: ignored" "$([ "${R#* }" = "$MOODLE_URL/my/" ] && echo 1)" "target=${R#* }"
LINK="$(ask_link "$STUDENT" --data-urlencode "returnurl=/course/view.php?id=$COURSE" | json "d['loginurl']")"
J5="$TMP/c5.jar"; rm -f "$J5"
R="$(open_link "$LINK" "$J5")"
result "1.32 local return address kept" "$([ "${R#* }" = "$MOODLE_URL/course/view.php?id=$COURSE" ] && echo 1)" "target=${R#* }"

NOPERM="$(helper create-noperm)"
CODE="$(ws "$NOPERM" local_vcostaedu_request_login_url -d "username=$STUDENT" | json "d.get('errorcode')")"
NOPERM=""
result "1.33 key without local/vcostaedu:requestloginurl: refused" "$([ "$CODE" = "nopermissions" ] && echo 1)" "error=$CODE"

BEFORE="$(helper state)"
UPG="$(sudo -u "$WEB_USER" php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive > /dev/null 2>&1; echo $?)"
helper repair > /dev/null
helper repair > /dev/null
result "1.34 upgrade.php again and repair twice: nothing duplicated" "$([ "$UPG" = "0" ] && [ "$BEFORE" = "$(helper state)" ] && echo 1)" "$(helper state)"
helper remove-cap moodle/course:create > /dev/null
D1="$(helper diag | grep -o 'role=[a-z>-]*')"
helper repair > /dev/null
D2="$(helper diag | grep -o 'role=[a-z>-]*')"
result "1.35 missing capability: diagnosis reports it and \"Fix\" puts it back" \
    "$([ "$D1" = "role=error->fix" ] && [ "$D2" = "role=ok" ] && echo 1)" "before=$D1 after=$D2"

JS="$TMP/cs.jar"
open_link "$(ask_link "$STUDENT" | json "d['loginurl']")" "$JS" > /dev/null
C="$(curl -s -o "$TMP/student-connect.html" -b "$JS" -c "$JS" -w '%{http_code}' "$MOODLE_URL/local/vcostaedu/connect.php")"
result "1.36 the connect page is closed to a student" \
    "$([ "$C" != "200" ] && ! grep -q 'local_vcostaedu_token' "$TMP/student-connect.html" && echo 1)" "code=$C"

# E-mail: enrolment in a course with a teacher (the welcome message is sent from the teacher).
TEACHER="e2e.teacher$TS"
TEACHERID="$(create_user "$TEACHER" "Teacher$TS")"
ws "$TOKEN" enrol_manual_enrol_users -d "enrolments[0][roleid]=3" -d "enrolments[0][userid]=$TEACHERID" -d "enrolments[0][courseid]=$COURSE" > /dev/null
answer() { echo "$1" | json "'no error' if d is None else d.get('errorcode')"; }
enrol() { ws "$TOKEN" enrol_manual_enrol_users -d "enrolments[0][roleid]=5" -d "enrolments[0][userid]=$STUDENTID" -d "enrolments[0][courseid]=$COURSE" "$@"; }
EMAIL="$(helper diag | grep -o 'email=[a-z]*')"
A1="$(answer "$(enrol -d 'enrolments[0][suspend]=1')")"; S1="$(helper enrol-status "$STUDENT" "$COURSE")"
A2="$(answer "$(enrol -d 'enrolments[0][suspend]=0')")"; S2="$(helper enrol-status "$STUDENT" "$COURSE")"
A3="$(answer "$(enrol)")"; S3="$(helper enrol-status "$STUDENT" "$COURSE")"
result "1.37 e-mail ($EMAIL): suspend, reactivate and repeat enrolment in a course with a teacher" \
    "$([ "$S1" = "suspended" ] && [ "$S2" = "active" ] && [ "$S3" = "active" ] && echo 1)" \
    "suspend=[$A1] saved=$S1 | reactivate=[$A2] saved=$S2 | repeat=[$A3] saved=$S3"
helper email-processor 0 > /dev/null
EMAILP="$(helper diag | grep -o 'email=[a-z]*')"
B1="$(answer "$(enrol -d 'enrolments[0][suspend]=1')")"; T1="$(helper enrol-status "$STUDENT" "$COURSE")"
B2="$(answer "$(enrol -d 'enrolments[0][suspend]=0')")"; T2="$(helper enrol-status "$STUDENT" "$COURSE")"
helper email-processor 1 > /dev/null
result "1.38 e-mail output turned off ($EMAILP): the same calls" \
    "$([ "$T1" = "suspended" ] && [ "$T2" = "active" ] && [ "$EMAILP" = "email=warning" ] && echo 1)" \
    "suspend=[$B1] saved=$T1 | reactivate=[$B2] saved=$T2"

EV="$(helper events "$STUDENT" "$TS" | tr ' ' '\n' | grep -E 'login_link|user_loggedin' | sort | uniq -c | tr -s ' ' | tr '\n' ' ')"
result "1.39 login link created, used and refused events in the site log" \
    "$(echo "$EV" | grep -q 'login_link_used' && echo "$EV" | grep -q 'login_link_refused(usersuspended)' && echo 1)" "$EV"
result "1.40 developer debugging on: no PHP notice, warning or error on any page" "$([ "$NOTICES" = "0" ] && echo 1)" "notices=$NOTICES"

# The course follows the academic record: rename it and move it to another category.
course_field() { ws "$TOKEN" core_course_get_courses_by_field -d field=id -d "value=$COURSE" | json "d['courses'][0]['$1']"; }
R="$(ws "$TOKEN" core_course_update_courses -d "courses[0][id]=$COURSE" -d "courses[0][fullname]=E2E renamed $TS" \
    -d "courses[0][shortname]=E2E-R-$TS" -d "courses[0][idnumber]=E2E-R-$TS" -d "courses[0][summary]=E2E summary $TS")"
W="$(echo "$R" | json "len(d['warnings'])")"
result "1.41 rename the course: full name, short name, id number and summary" \
    "$([ "$W" = "0" ] && [ "$(course_field fullname)" = "E2E renamed $TS" ] && [ "$(course_field shortname)" = "E2E-R-$TS" ] \
        && [ "$(course_field idnumber)" = "E2E-R-$TS" ] && [ "$(course_field summary)" = "E2E summary $TS" ] && echo 1)" \
    "warnings=$W fullname=$(course_field fullname)"
CAT2="$(ws "$TOKEN" core_course_create_categories -d "categories[0][name]=E2E other $TS" | json "d[0]['id']")"
R="$(ws "$TOKEN" core_course_update_courses -d "courses[0][id]=$COURSE" -d "courses[0][categoryid]=$CAT2")"
W="$(echo "$R" | json "len(d['warnings'])")"
result "1.42 move the course to another category" "$([ "$W" = "0" ] && [ "$(course_field categoryid)" = "$CAT2" ] && echo 1)" \
    "warnings=$W category=$(course_field categoryid) expected=$CAT2"
# Search by e-mail: the plugin's own function answers only the ids (the role has no identity
# fields on purpose); spaces and case are ignored, and the address comes back as it was sent.
find_email() { ws "$TOKEN" local_vcostaedu_find_users_by_email --data-urlencode "emails[0]=$1"; }
BYMAIL="$(find_email "  ${STUDENT^^}@EXAMPLE.COM " | json "','.join(str(i) for i in d[0]['userids'])")"
KEYS="$(find_email "$STUDENT@example.com" | json "','.join(sorted(d[0].keys()))")"
result "1.43 find an account by e-mail with local_vcostaedu_find_users_by_email: only its id" \
    "$([ "$BYMAIL" = "$STUDENTID" ] && [ "$KEYS" = "email,userids" ] && echo 1)" "userids=$BYMAIL fields=$KEYS"
NONE="$(find_email "e2e.nobody$TS@example.com" | json "len(d[0]['userids'])")"
BAD="$(find_email "not an address" | json "d.get('errorcode')")"
result "1.44 e-mail without an account: empty list; malformed address: invalidparameter" \
    "$([ "$NONE" = "0" ] && [ "$BAD" = "invalidparameter" ] && echo 1)" "userids=$NONE malformed=$BAD"

# ---------------------------------------------------------------------------------------------
# Block 2. Another service already uses the plugin service name.
reset_clean
developer_debug
helper create-admin "$ADMIN_USER" en > /dev/null
helper create-conflict > /dev/null
RC="$(install_zip)"
PL="$(helper plugin)"
result "2.1 command line: installation stops before creating anything" \
    "$([ "$RC" != "0" ] && grep -q 'e2e_conflict' "$TMP/upgrade.txt" && echo "$PL" | grep -q 'version=- role=0 user=0 service=0' && echo 1)" \
    "upgrade=$RC $PL"
admin_login
curl -s -b "$TMP/adm.jar" -c "$TMP/adm.jar" -L "$MOODLE_URL/admin/index.php" > "$TMP/admin.html"
result "2.2 screen: the server check shows the conflict and what to do" \
    "$(grep -q 'e2e_conflict' "$TMP/admin.html" && echo 1)" \
    "message_on_page=$(grep -c 'e2e_conflict' "$TMP/admin.html")"
helper remove-conflict > /dev/null
RC="$(install_zip)"
PL="$(helper plugin)"
result "2.3 after the other service is renamed or removed, the installation works" \
    "$([ "$RC" = "0" ] && echo "$PL" | grep -q 'role=1 user=1 service=1' && echo 1)" "upgrade=$RC $PL"

# ---------------------------------------------------------------------------------------------
# Block 3. Uninstalling removes what the plugin created.
reset_clean
RC="$(install_zip)"
U="$(uninstall)"
PL="$(helper plugin)"
SITE="$(curl -s -o /dev/null -w '%{http_code}' "$MOODLE_URL/login/index.php")"
result "3.1 uninstall: role, user and service created by the plugin are removed; site opens" \
    "$([ "$RC" = "0" ] && [ "$U" = "0" ] && [ "$SITE" = "200" ] && echo "$PL" | grep -q 'version=- role=0 user=0 service=0' && echo 1)" \
    "install=$RC uninstall=$U site=$SITE $PL"

# ---------------------------------------------------------------------------------------------
# Block 4. A role and a user that existed before the plugin stay after uninstalling.
reset_clean
helper create-preexisting > /dev/null
RC="$(install_zip)"
PL1="$(helper plugin)"
U="$(uninstall)"
PL2="$(helper plugin)"
result "4.1 pre-existing role and user: reused, not recorded as created, kept on uninstall" \
    "$([ "$RC" = "0" ] && echo "$PL1" | grep -q 'createdrole=0 createduser=0' && [ "$U" = "0" ] \
        && echo "$PL2" | grep -q 'version=- role=1 user=1 service=0' && echo 1)" "installed: $PL1 | uninstalled: $PL2"

reset_clean
echo "== Result: $PASSED passed, $FAILED failed (site restored to the clean dump) =="
[ "$FAILED" = "0" ]
