#!/bin/bash
# Victorious MARKET - Shared Hosting Deployment Packager (Bash)
# Prepares two ready-to-upload directories: 'core' (outside public_html) and 'public_html'

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKSPACE_ROOT="$(dirname "$SCRIPT_DIR")"
BACKEND_DIR="$WORKSPACE_ROOT/backend"
OUTPUT_DIR="$WORKSPACE_ROOT/dist_shared_hosting"
CORE_DIR="$OUTPUT_DIR/core"
PUBLIC_HTML_DIR="$OUTPUT_DIR/public_html"

echo "=========================================================="
echo " 👑 Victorious MARKET - Shared Hosting Packaging Utility "
echo "=========================================================="

rm -rf "$OUTPUT_DIR"
mkdir -p "$CORE_DIR"
mkdir -p "$PUBLIC_HTML_DIR"

echo "1. Copying Core Application Files to 'core/'..."
rsync -av --exclude='public' --exclude='tests' --exclude='.git' --exclude='node_modules' "$BACKEND_DIR/" "$CORE_DIR/"

echo "2. Copying Public Assets to 'public_html/'..."
cp -r "$BACKEND_DIR/public/"* "$PUBLIC_HTML_DIR/"

echo "3. Patching public_html/index.php paths for shared hosting..."
sed -i "s|__DIR__.'/../vendor/autoload.php'|__DIR__.'/../core/vendor/autoload.php'|g" "$PUBLIC_HTML_DIR/index.php"
sed -i "s|__DIR__.'/../bootstrap/app.php'|__DIR__.'/../core/bootstrap/app.php'|g" "$PUBLIC_HTML_DIR/index.php"
sed -i "s|__DIR__.'/../storage/framework/maintenance.php'|__DIR__.'/../core/storage/framework/maintenance.php'|g" "$PUBLIC_HTML_DIR/index.php"

echo "4. Creating production .env.production template in 'core/'..."
cp "$BACKEND_DIR/.env.example" "$CORE_DIR/.env.production.example"

echo "=========================================================="
echo "✓ PACKAGING COMPLETE!"
echo "  Directory: $OUTPUT_DIR"
echo "  Structure:"
echo "    - core/        -> Upload to /home/your_username/core"
echo "    - public_html/ -> Upload into /home/your_username/public_html"
echo "=========================================================="
