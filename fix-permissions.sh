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

# Set permissions for all writable subdirectories. 775/664 (rwx for
# owner+group, none for others) instead of world-writable 777: the web
# server user and any deploy/service group can still read/write, but
# arbitrary other local users on the host cannot. Adjust the group
# ownership (chgrp) to match your web server user on the actual deploy
# target if group-write access is needed.
for dir in writable/cache writable/logs writable/session writable/uploads writable/debugbar; do
    find "$dir" -type d -exec chmod 775 {} \;
    find "$dir" -type f -exec chmod 664 {} \;
done

# Create index.html files if they don't exist
if [ ! -f writable/cache/index.html ]; then
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > writable/cache/index.html
fi

if [ ! -f writable/logs/index.html ]; then
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > writable/logs/index.html
fi

if [ ! -f writable/session/index.html ]; then
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > writable/session/index.html
fi

if [ ! -f writable/uploads/index.html ]; then
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > writable/uploads/index.html
fi

if [ ! -f writable/debugbar/index.html ]; then
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > writable/debugbar/index.html
fi

echo "Permissions set successfully."
echo "Writable directories now have 775/664 permissions."
