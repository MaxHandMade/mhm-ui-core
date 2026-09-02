#!/usr/bin/env bash
# Installs the WordPress test suite for the integration suite.
#
# Copied from mhm-rentiva. The deviations are listed here because this comment
# is what the claim "CI and the local container run the same script" rests on.
#
# What it FETCHES, changed:
#  1. download() fails loudly and leaves no artefact. The reference used a bare
#     `curl -s` writing straight to the target, so a failure wrote an empty file
#     and reported success -- and the re-run guards below then treated that
#     empty file as a finished install.
#  2. WP_VERSION=latest resolves to the current STABLE tag. The reference maps
#     it to `trunk` while downloading a stable core tarball, so the test
#     framework and the core it exercises come from different trees and a
#     WordPress commit can turn a plugin's CI red with no change to the plugin.
#  3. No wp-mysqli db.php drop-in. raw.github.com answers 301, the reference's
#     bare curl wrote a zero-byte file, and the constant that file would define
#     (WP_USE_EXT_MYSQL) has no readers left in core.
#
# What it DOES, changed:
#  4. `set -euo pipefail`. The reference tolerated a failing step and carried on;
#     here a half-finished install stops instead of pretending.
#  5. The re-run guards test for a file the step actually produces, not for the
#     directory it created before it started (see 1).
#  6. The wp-tests-config.php guard is anchored to $WP_TESTS_DIR. The reference
#     tested a bare relative path, so it re-downloaded and re-sed the config on
#     every run from a different working directory.
#  7. Portable shell for the host split and the numeric test (`IFS`/`read -ra`,
#     `grep -qE`) instead of the reference's word-splitting form.

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

# Downloads to a scratch name and renames on success, so a failed transfer
# cannot leave a file that a later run mistakes for a finished one.
download() {
	local target=$2
	local scratch="${target}.download"

	if curl -sSL --fail "$1" > "$scratch"; then
		mv "$scratch" "$target"
		return 0
	fi

	rm -f "$scratch"
	echo "download failed: $1" >&2
	return 1
}

if [ "$WP_VERSION" = 'latest' ]; then
	download https://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	WP_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//' | head -1 || true)
	if [ -z "$WP_VERSION" ]; then
		echo "could not resolve the current WordPress version from api.wordpress.org" >&2
		exit 1
	fi
fi

WP_TESTS_TAG="tags/$WP_VERSION"

install_wp() {
	# wp-settings.php, not the directory: the directory exists as soon as this
	# function starts, so guarding on it would skip a re-run after a failure.
	if [ -f "${WP_CORE_DIR}wp-settings.php" ]; then
		return
	fi

	mkdir -p "$WP_CORE_DIR"
	download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" /tmp/wordpress.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
}

install_test_suite() {
	if [ ! -f "$WP_TESTS_DIR/includes/functions.php" ]; then
		mkdir -p "$WP_TESTS_DIR"
		svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
	fi

	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
		WP_CORE_DIR_ESC=$(echo "$WP_CORE_DIR" | sed "s:/\+$::")
		sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR_ESC/':" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
	fi
}

install_db() {
	local PARTS
	IFS=':' read -ra PARTS <<< "$DB_HOST"
	local DB_HOSTNAME=${PARTS[0]}
	local DB_SOCK_OR_PORT=${PARTS[1]-}
	local EXTRA=""

	if [ -n "$DB_HOSTNAME" ]; then
		if echo "$DB_SOCK_OR_PORT" | grep -qE '^[0-9]+$'; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif [ -n "$DB_SOCK_OR_PORT" ]; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		else
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# Re-running the installer against a database that already exists is a
	# normal thing to do, and mysqladmin treats it as an error. Under `set -e`
	# that would abort a run whose only remaining work was already done.
	if mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA -N -e "SHOW DATABASES LIKE '${DB_NAME}';" | grep -q "$DB_NAME"; then
		return
	fi

	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_wp
install_test_suite
install_db
