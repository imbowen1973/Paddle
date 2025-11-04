#!/bin/bash

# Paddle Plugin Server Deployment Script
# Run this on your cPanel server via SSH

echo "========================================"
echo "Paddle Plugin Server Deployment"
echo "========================================"
echo ""

# Configuration
MOODLE_DIR="/cloudclusters/moodle/html"
PLUGIN_DIR="$MOODLE_DIR/enrol/paddle"
GITHUB_ZIP_URL="https://github.com/imbowen1973/Paddle/archive/refs/heads/main.zip"
TEMP_DIR="/tmp/paddle_deploy_$$"

# Check if we're in the right location
if [ ! -d "$MOODLE_DIR" ]; then
    echo "ERROR: Moodle directory not found: $MOODLE_DIR"
    exit 1
fi

echo "Step 1: Creating temporary directory..."
mkdir -p "$TEMP_DIR"
cd "$TEMP_DIR"
echo "✓ Working in $TEMP_DIR"
echo ""

echo "Step 2: Downloading latest version from GitHub..."
wget -q --show-progress "$GITHUB_ZIP_URL" -O paddle.zip
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to download from GitHub"
    rm -rf "$TEMP_DIR"
    exit 1
fi
echo "✓ Downloaded paddle.zip"
echo ""

echo "Step 3: Extracting files..."
unzip -q paddle.zip
if [ $? -ne 0 ]; then
    echo "ERROR: Failed to extract ZIP file"
    rm -rf "$TEMP_DIR"
    exit 1
fi
echo "✓ Files extracted"
echo ""

echo "Step 4: Backing up current plugin..."
if [ -d "$PLUGIN_DIR" ]; then
    BACKUP_DIR="$PLUGIN_DIR.backup.$(date +%Y%m%d_%H%M%S)"
    cp -r "$PLUGIN_DIR" "$BACKUP_DIR"
    echo "✓ Backup created: $BACKUP_DIR"
else
    echo "! No existing plugin to backup"
fi
echo ""

echo "Step 5: Deploying new files..."
# GitHub zip extracts to Paddle-main directory
if [ -d "Paddle-main" ]; then
    rm -rf "$PLUGIN_DIR"/*
    cp -r Paddle-main/* "$PLUGIN_DIR/"
    echo "✓ Files deployed to $PLUGIN_DIR"
else
    echo "ERROR: Expected directory 'Paddle-main' not found"
    ls -la
    rm -rf "$TEMP_DIR"
    exit 1
fi
echo ""

echo "Step 6: Setting permissions..."
cd "$PLUGIN_DIR"
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
echo "✓ Permissions set (644 for files, 755 for directories)"
echo ""

echo "Step 7: Cleaning up..."
rm -rf "$TEMP_DIR"
echo "✓ Temporary files removed"
echo ""

echo "Step 8: Checking version..."
if [ -f "$PLUGIN_DIR/version.php" ]; then
    VERSION=$(grep '$plugin->version' "$PLUGIN_DIR/version.php" | grep -oP '\d{10}')
    echo "✓ Deployed version: $VERSION"
else
    echo "! Could not read version"
fi
echo ""

echo "========================================"
echo "Deployment Complete!"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Go to: https://advance.ebvs.eu/admin/index.php"
echo "2. Run database upgrades (if prompted)"
echo "3. Go to: Site Administration → Development → Purge all caches"
echo "4. Test the checkout button"
echo ""
echo "If you need to rollback:"
echo "  cp -r $BACKUP_DIR/* $PLUGIN_DIR/"
echo ""
