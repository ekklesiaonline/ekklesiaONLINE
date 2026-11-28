#!/bin/bash

# Fix writable directory permissions for CodeIgniter 4
# This script ensures all writable directories have proper permissions

echo "Setting writable directory permissions..."

# Ensure directories exist
mkdir -p writable/cache
mkdir -p writable/logs
mkdir -p writable/session
mkdir -p writable/uploads
mkdir -p writable/debugbar
mkdir -p writable/backups
mkdir -p writable/reports

# Set permissions for all writable subdirectories. 775/664 (rwx for
# owner+group, none for others) instead of world-writable 777: the web
# server user and any deploy/service group can still read/write, but
# arbitrary other local users on the host cannot. Adjust the group
# ownership (chgrp) to match your web server user on the actual deploy
# target if group-write access is needed.
for dir in writable/cache writable/logs writable/session writable/uploads writable/debugbar writable/reports; do
    find "$dir" -type d -exec chmod 775 {} \;
    find "$dir" -type f -exec chmod 664 {} \;
done

# Database backups and hierarchy exports contain PII and restricted tax IDs:
# keep writable/backups strictly owner-only (700 dirs, 600 files).
find writable/backups -type d -exec chmod 700 {} \;
find writable/backups -type f -exec chmod 600 {} \;

# Create index.html files if they don't exist
for dir in writable/cache writable/logs writable/session writable/uploads writable/debugbar writable/reports writable/backups; do
    if [ ! -f "$dir/index.html" ]; then
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > "$dir/index.html"
    fi
done
chmod 600 writable/backups/index.html

echo "Permissions set successfully."
echo "Writable directories now have 775/664 permissions (writable/backups: 700/600)."
