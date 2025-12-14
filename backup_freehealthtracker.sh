#!/bin/bash
set -euo pipefail

# ---- Config ----
SITE_ROOT="/var/www/FreeHealthTracker"
BACKUP_DIR="${SITE_ROOT}/backups"
TS="$(date +%Y%m%d_%H%M%S)"
ARCHIVE_BASENAME="FreeHealthTracker_${TS}.tar.gz"
ARCHIVE_PATH="${BACKUP_DIR}/${ARCHIVE_BASENAME}"

# Optional: reduce I/O impact a bit (comment out if you don't want it)
NICE_BIN="$(command -v nice || true)"
IONICE_BIN="$(command -v ionice || true)"

echo "=== FreeHealthTracker backup ==="
echo "Site root : ${SITE_ROOT}"
echo "Backup dir: ${BACKUP_DIR}"
echo "Archive   : ${ARCHIVE_PATH}"
echo

# ---- Checks ----
if [[ ! -d "${SITE_ROOT}" ]]; then
  echo "ERROR: SITE_ROOT does not exist: ${SITE_ROOT}" >&2
  exit 1
fi

# Create backups dir if missing
mkdir -p "${BACKUP_DIR}"

# Permission hardening for backups folder (root-owned, not world-readable)
# (If you prefer different ownership, change this.)
chown root:root "${BACKUP_DIR}"
chmod 750 "${BACKUP_DIR}"

# ---- Build tar command ----
# -C so the archive contains FreeHealthTracker/... rather than absolute paths.
# --exclude backups to avoid recursion.
TAR_ARGS=(
  -czf "${ARCHIVE_PATH}"
  --numeric-owner
  --xattrs
  --acls
  --exclude="./backups"
  --exclude="./backups/*"
  -C "$(dirname "${SITE_ROOT}")"
  "$(basename "${SITE_ROOT}")"
)

echo "Creating archive..."
if [[ -n "${IONICE_BIN}" ]]; then
  # best-effort: idle I/O class
  if "${IONICE_BIN}" -c3 true >/dev/null 2>&1; then
    if [[ -n "${NICE_BIN}" ]]; then
      "${IONICE_BIN}" -c3 "${NICE_BIN}" -n 19 tar "${TAR_ARGS[@]}"
    else
      "${IONICE_BIN}" -c3 tar "${TAR_ARGS[@]}"
    fi
  else
    tar "${TAR_ARGS[@]}"
  fi
else
  tar "${TAR_ARGS[@]}"
fi

echo
echo "Done."
ls -lh "${ARCHIVE_PATH}"
echo "=== Backup complete ==="
