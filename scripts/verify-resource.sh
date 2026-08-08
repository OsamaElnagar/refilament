#!/usr/bin/env bash
set -e

taskkill //F //IM php.exe 2>/dev/null | head -1 || true
cd /d/ReFilament/ReFilament

nohup php -S 127.0.0.1:8000 -t workbench/public workbench/server.php > /tmp/refilament-serve.log 2>&1 &
sleep 3

echo '=== posts page status (discovered resource)'
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/refilament/posts

echo '=== create page status'
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/refilament/posts/create

echo '=== table payload id + columns'
curl -s 'http://127.0.0.1:8000/refilament/table/posts?perPage=1' | grep -oE '"id":"[^"]*"|"name":"[^"]*"' | head -8

echo '=== form payload (fields)'
curl -s 'http://127.0.0.1:8000/refilament/schema/post-form' | grep -oE '"name":"[^"]*"' | head -8

echo '=== server log tail'
tail -3 /tmp/refilament-serve.log
