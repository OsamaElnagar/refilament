#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/.."

RESOURCE=workbench/app/Refilament/Resources/UserResource.php
BACKUP=$(mktemp)
cp "$RESOURCE" "$BACKUP"

echo '=== regenerate User resource (throwaway proof — restored below)'
php vendor/bin/testbench refilament:make-resource User --model='Workbench\App\Models\User' --generate --force 2>&1 | tail -1

echo '=== model property + query lines now use the short name via import'
grep -n 'use Workbench' "$RESOURCE"
grep -n 'protected static ?string $model' "$RESOURCE"
grep -nF -- '->query(' "$RESOURCE"

echo '=== auth polish: remember_token skipped, password typed + revealable'
if grep -q 'remember_token' "$RESOURCE"; then echo 'FAIL: remember_token still generated'; exit 1; fi
echo 'remember_token: skipped'
grep -nF -- '->password()->revealable()' "$RESOURCE"

echo '=== lint + pint (generated file should be clean out of the box)'
php -l "$RESOURCE"
vendor/bin/pint --test "$RESOURCE" 2>&1 | tail -1

echo '=== restore the customized resource (plural ids) so the demo state survives'
cp "$BACKUP" "$RESOURCE"
rm -f "$BACKUP"

echo '=== restart server so discovery rescans'
taskkill //F //IM php.exe 2>/dev/null | head -1 || true
nohup php -S 127.0.0.1:8000 -t workbench/public workbench/server.php > /tmp/refilament-serve.log 2>&1 &
sleep 3

echo '=== users table endpoint (customized plural id, served by discovery)'
curl -s -o /dev/null -w '%{http_code}\n' 'http://127.0.0.1:8000/refilament/table/users?perPage=2'

echo '=== users payload: id + columns + rows'
curl -s 'http://127.0.0.1:8000/refilament/table/users?perPage=2' | grep -oE '"id":"[^"]*"|"name":"[^"]*"|"total":[0-9]+' | head -8

echo '=== users page (auto-registered by the package — no app route exists)'
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/refilament/users

echo '=== posts still fine (also auto-registered now)'
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/refilament/posts
