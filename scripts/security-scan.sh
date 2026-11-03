#!/usr/bin/env bash
#
# Security scan orchestrator for <plugin>. Reporting mode: writes JSON reports
# to scan-reports/ and exits 0 unless a tool fails to RUN. Findings never abort
# the battery - triage happens from the reports.
#
# Usage: scripts/security-scan.sh {phpcs|phpstan|semgrep|secrets|deps|wporg|all}
# Environment: WP_INSTALL_PATH (optional) - Local WP install, e.g.
#   /Volumes/DATA/LocalSites/anomalous/app/public   (enables scan:wporg)

set -u -o pipefail

cd "$(dirname "$0")/.."
SLUG="$(basename "$PWD")"
REPORTS="scan-reports"
mkdir -p "$REPORTS"
FAILURES=0
NOTES=""

note() { NOTES="${NOTES}${1}"$'\n'; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "ERROR: required tool '$1' not found in PATH" >&2; FAILURES=$((FAILURES+1)); return 1; }
}

# run <tolerated-exit> <cmd...>  - tolerated exit codes are tool "findings" codes.
run() {
  local tolerated="$1"; shift
  "$@"; local rc=$?
  if [ "$rc" -gt "$tolerated" ]; then
    echo "ERROR: command failed with exit $rc: $*" >&2
    FAILURES=$((FAILURES+1))
    return 1
  fi
  return 0
}

# Dist-equivalent exclude list - mirrors scripts/package.sh --target wporg.
# Used for both the Local-site refresh (wporg) and semgrep Pass B staging.
RSYNC_DIST_EXCLUDES=(
  --exclude=.DS_Store --exclude=.git --exclude=.gitignore --exclude=.gitattributes
  --exclude=.distignore --exclude=.deployignore --exclude=.editorconfig
  --exclude=node_modules/ --exclude=vendor/ --exclude=tests/ --exclude=scripts/
  --exclude=docs/ --exclude=dist/ --exclude=_archive/ --exclude=scan-reports/
  --exclude=composer.json --exclude=composer.lock --exclude=package.json
  --exclude=package-lock.json --exclude=npm-shrinkwrap.json
  --exclude=phpcs.xml --exclude=phpcs.xml.dist --exclude=phpcs-security.xml
  --exclude=phpunit.xml --exclude=phpunit.xml.dist
  --exclude=phpstan.neon --exclude=phpstan-baseline.neon
  --exclude=.semgrep/ --exclude=.gitleaks.toml --exclude=.gitleaksignore
  --exclude=README.md --exclude=readme.md
  --exclude=.github/ --exclude=.vscode/ --exclude=.idea/
)

scan_phpcs() {
  [ -x vendor/bin/phpcs ] || { echo "ERROR: vendor/bin/phpcs missing - run composer install" >&2; FAILURES=$((FAILURES+1)); return; }
  echo "== phpcs-security =="
  run 1 vendor/bin/phpcs --standard=phpcs-security.xml --basepath=. . \
    --report=json > "$REPORTS/phpcs-security.json"
  [ -s "$REPORTS/phpcs-security.json" ] && \
    jq -r '"findings: \(.totals.errors) errors, \(.totals.warnings) warnings"' "$REPORTS/phpcs-security.json" 2>/dev/null
}

scan_phpstan() {
  [ -x vendor/bin/phpstan ] || { echo "ERROR: vendor/bin/phpstan missing - run composer install" >&2; FAILURES=$((FAILURES+1)); return; }
  echo "== phpstan (level 5, php 7.4 target) =="
  run 1 vendor/bin/phpstan analyse --configuration=phpstan.neon \
    --memory-limit=1G --no-progress --error-format=json > "$REPORTS/phpstan.json"
  [ -s "$REPORTS/phpstan.json" ] && \
    jq -r '"findings: \(.totals.file_errors) errors"' "$REPORTS/phpstan.json" 2>/dev/null
}

scan_semgrep() {
  need_cmd semgrep || return
  echo "== semgrep p/php (source tree) =="
  run 0 semgrep scan --config p/php --metrics=off --json \
    --output "$REPORTS/semgrep-php.json" .
  echo "== semgrep p/wordpress (staged dist-equivalent copy) =="
  # p/wordpress rules are path-anchored to **/wp-content/plugins/** and produce
  # zero findings against a standalone repo layout - hence the staging dir.
  mkdir -p "$REPORTS/semgrep-stage/wp-content/plugins"
  rsync -a --delete --delete-excluded "${RSYNC_DIST_EXCLUDES[@]}" \
    ./ "$REPORTS/semgrep-stage/wp-content/plugins/$SLUG/"
  run 0 semgrep scan --config p/wordpress --metrics=off --no-git-ignore --json \
    --output "$REPORTS/semgrep-wordpress.json" "$REPORTS/semgrep-stage"
  jq -r '"findings: \(.results | length)"' "$REPORTS/semgrep-php.json" 2>/dev/null
  jq -r '"findings: \(.results | length)"' "$REPORTS/semgrep-wordpress.json" 2>/dev/null
}

scan_secrets() {
  need_cmd gitleaks || return
  echo "== gitleaks (working tree + git history) =="
  run 1 gitleaks dir --no-banner --report-format json \
    --report-path "$REPORTS/gitleaks-dir.json" .
  run 1 gitleaks detect --no-banner --report-format json \
    --report-path "$REPORTS/gitleaks-detect.json"
  local leaks
  leaks=$(( $(jq 'length' "$REPORTS/gitleaks-dir.json" 2>/dev/null || echo 0)
          + $(jq 'length' "$REPORTS/gitleaks-detect.json" 2>/dev/null || echo 0) ))
  if [ "$leaks" -gt 0 ]; then
    echo "!!! gitleaks: $leaks FINDINGS - REVIEW IMMEDIATELY (possible leaked secrets)"
  else
    echo "gitleaks: clean"
  fi
}

scan_deps() {
  need_cmd composer || return
  echo "== composer audit =="
  run 1 composer audit --format=json --locked > "$REPORTS/composer-audit.json"
  jq -r '"findings: \(.advisories | length) advisories"' "$REPORTS/composer-audit.json" 2>/dev/null
  if [ -f package-lock.json ]; then
    need_cmd npm || return
    echo "== npm audit =="
    run 1 npm audit --json > "$REPORTS/npm-audit.json"
    jq -r '"findings: \(.metadata.vulnerabilities.total) vulnerabilities"' "$REPORTS/npm-audit.json" 2>/dev/null
  fi
  if need_cmd trivy; then
    echo "== trivy (vuln+secret) =="
    run 0 trivy fs --scanners vuln,secret --include-dev-deps \
      --skip-dirs vendor --skip-dirs node_modules \
      --format json --output "$REPORTS/trivy.json" --skip-version-check .
  fi
}

scan_wporg() {
  if [ -z "${WP_INSTALL_PATH:-}" ]; then
    note "scan:wporg SKIPPED - set WP_INSTALL_PATH to the Local WP install,"
    note "  e.g. WP_INSTALL_PATH=/Volumes/DATA/LocalSites/anomalous/app/public"
    return
  fi
  need_cmd php || return; need_cmd wp || return; need_cmd jq || return; need_cmd rsync || return

  local LOCAL_DIR="$HOME/Library/Application Support/Local"
  local SITE_ID SOCKET RUN_DIR MYSQLD_BIN MYSQLADMIN_BIN STARTED_DB=0

  SITE_ID="$(jq -r --arg p "$WP_INSTALL_PATH" \
    'to_entries[] | select(.value.path as $path | $path != null and ($p | startswith($path))) | .key' \
    "$LOCAL_DIR/sites.json" 2>/dev/null | head -1)"
  if [ -z "$SITE_ID" ]; then
    note "scan:wporg SKIPPED - no Local site matches $WP_INSTALL_PATH in sites.json"
    return
  fi
  RUN_DIR="$LOCAL_DIR/run/$SITE_ID"
  SOCKET="$RUN_DIR/mysql/mysqld.sock"
  local MYSQL_VER SITE_NAME DB_USER DB_PASS
  MYSQL_VER="$(jq -r --arg k "$SITE_ID" '.[$k].services.mysql.version // "8.0.35"' "$LOCAL_DIR/sites.json" 2>/dev/null)"
  SITE_NAME="$(jq -r --arg k "$SITE_ID" '.[$k].name // ""' "$LOCAL_DIR/sites.json" 2>/dev/null)"
  DB_USER="$(jq -r --arg k "$SITE_ID" '.[$k].mysql.user // "root"' "$LOCAL_DIR/sites.json" 2>/dev/null)"
  DB_PASS="$(jq -r --arg k "$SITE_ID" '.[$k].mysql.password // "root"' "$LOCAL_DIR/sites.json" 2>/dev/null)"
  for MYSQLD_BIN in "$LOCAL_DIR"/lightning-services/mysql-"$MYSQL_VER"*/bin/darwin-arm64/bin/mysqld \
                    "$LOCAL_DIR"/lightning-services/mysql-"$MYSQL_VER"*/bin/darwin-x64/bin/mysqld; do
    [ -x "$MYSQLD_BIN" ] && break
  done
  MYSQLADMIN_BIN="$(dirname "$MYSQLD_BIN")/mysqladmin"

  WP_BIN="$(command -v wp)"
  wprun() { php -d mysqli.default_socket="$SOCKET" "$WP_BIN" "$@"; }

  # 1. Bring the database up (only if needed, only stopping what we start).
  if ! wprun option get siteurl --path="$WP_INSTALL_PATH" >/dev/null 2>&1; then
    if command -v localwp >/dev/null 2>&1 && [ -n "$SITE_NAME" ]; then
      localwp start "$SITE_NAME"
      STARTED_DB=1
    elif [ -x "$MYSQLD_BIN" ]; then
      echo "Starting site database manually ($MYSQLD_BIN)"
      nohup "$MYSQLD_BIN" --defaults-file="$RUN_DIR/conf/mysql/my.cnf" \
        > "$REPORTS/mysqld-wporg.log" 2>&1 &
      STARTED_DB=1
    else
      note "scan:wporg SKIPPED - cannot start site DB (no localwp, no lightning mysqld)"
      return
    fi
    local i up=0
    for i in $(seq 1 30); do
      if wprun option get siteurl --path="$WP_INSTALL_PATH" >/dev/null 2>&1; then up=1; break; fi
      sleep 2
    done
    if [ "$up" -ne 1 ]; then
      [ "$STARTED_DB" -eq 1 ] && "$MYSQLADMIN_BIN" --socket="$SOCKET" -u "$DB_USER" -p"$DB_PASS" shutdown 2>/dev/null
      note "scan:wporg SKIPPED - site database did not become ready (see $REPORTS/mysqld-wporg.log)"
      return
    fi
  fi

  # 2. Refresh the deployed copy so the scan target equals what ships.
  rsync -a --delete --delete-excluded "${RSYNC_DIST_EXCLUDES[@]}" \
    ./ "$WP_INSTALL_PATH/wp-content/plugins/$SLUG/"

  # 3. Ensure plugin-check is active, then run the FULL check battery.
  wprun plugin activate plugin-check --path="$WP_INSTALL_PATH" >/dev/null 2>&1 || true
  echo "== wp plugin check $SLUG (full run) =="
  run 0 wprun plugin check "$SLUG" --path="$WP_INSTALL_PATH" \
    --format=strict-json --fields=file,line,column,type,code,message \
    > "$REPORTS/wporg-$SLUG.json"
  jq -r 'group_by(.type) | .[] | "\(.[0].type): \(length)"' "$REPORTS/wporg-$SLUG.json" 2>/dev/null

  # 4. Leave the site as we found it.
  if [ "$STARTED_DB" -eq 1 ]; then
    "$MYSQLADMIN_BIN" --socket="$SOCKET" -u "$DB_USER" -p"$DB_PASS" shutdown 2>/dev/null \
      && echo "Site database stopped (was started by this script)."
  fi
}

case "${1:-}" in
  phpcs)    scan_phpcs ;;
  phpstan)  scan_phpstan ;;
  semgrep)  scan_semgrep ;;
  secrets)  scan_secrets ;;
  deps)     scan_deps ;;
  wporg)    scan_wporg ;;
  all)
    scan_phpcs; scan_phpstan; scan_semgrep; scan_secrets; scan_deps; scan_wporg
    ;;
  *) echo "Usage: $0 {phpcs|phpstan|semgrep|secrets|deps|wporg|all}" >&2; exit 64 ;;
esac

if [ -n "$NOTES" ]; then echo ""; echo "$NOTES"; fi
echo "Reports in $REPORTS/ ($(ls "$REPORTS" | grep -c '\.json$') JSON files)."
if [ "$FAILURES" -gt 0 ]; then
  echo "DONE WITH TOOL FAILURES: $FAILURES (reports may be incomplete)" >&2
  exit 2
fi
echo "DONE (reporting mode - findings above are recorded, not gating)."
