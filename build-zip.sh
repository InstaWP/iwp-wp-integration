#!/bin/bash
#
# Build a clean plugin zip for upload/distribution.
# Output: ~/Desktop/iwp-wp-integration.zip
#

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_NAME="$(basename "$PLUGIN_DIR")"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"
OUTPUT="$HOME/Desktop/${PLUGIN_NAME}.zip"

# Remove old zip if present
rm -f "$OUTPUT"

cd "$PARENT_DIR" || exit 1

zip -r "$OUTPUT" "$PLUGIN_NAME/" \
  -x "${PLUGIN_NAME}/.git/*" \
     "${PLUGIN_NAME}/.github/*" \
     "${PLUGIN_NAME}/node_modules/*" \
     "${PLUGIN_NAME}/vendor/*" \
     "${PLUGIN_NAME}/tests/*" \
     "${PLUGIN_NAME}/*.sh"

echo "Created: $OUTPUT"
