#!/bin/sh
# Rebuild the PHPUnit database `muni_ehrms_test` from the development schema.
#
# The base schema came from an SQL dump, so `php artisan migrate` on an empty
# database cannot build it. Instead we copy the structure of every table, plus
# the rows of the bookkeeping tables tests rely on (migrations, laravel-admin
# roles/permissions/menu), from the development database. Tests then run inside
# transactions and leave this database as they found it.
#
#   scripts/refresh-test-db.sh
#
# Run it after adding migrations.
set -e
cd "$(dirname "$0")/.."

MYSQL_BIN=/Applications/MAMP/Library/bin/mysql80/bin
SOCK=/Applications/MAMP/tmp/mysql/mysql.sock
SRC=$(grep '^DB_DATABASE=' .env | cut -d= -f2)
DST=muni_ehrms_test
AUTH="--socket=$SOCK -u$(grep '^DB_USERNAME=' .env | cut -d= -f2) -p$(grep '^DB_PASSWORD=' .env | cut -d= -f2)"
SEED_TABLES="migrations admin_roles admin_permissions admin_role_permissions admin_menu admin_role_menu"

if [ "$SRC" = "$DST" ]; then echo "Refusing: source and test database are the same." >&2; exit 1; fi

$MYSQL_BIN/mysql $AUTH -e "DROP DATABASE IF EXISTS \`$DST\`; CREATE DATABASE \`$DST\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
$MYSQL_BIN/mysqldump $AUTH --no-data --skip-add-drop-table "$SRC" 2>/dev/null | $MYSQL_BIN/mysql $AUTH "$DST" 2>/dev/null
$MYSQL_BIN/mysqldump $AUTH --no-create-info "$SRC" $SEED_TABLES 2>/dev/null | $MYSQL_BIN/mysql $AUTH "$DST" 2>/dev/null

echo "$DST rebuilt from $SRC: $($MYSQL_BIN/mysql $AUTH -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DST'" 2>/dev/null) tables."
