#!/bin/bash

# Configuration
SERVER_IP="192.168.12.100"
SHARE_NAME="TV/pdfs"
# Windows username
USERNAME="tvuser"
PASSWD="yourwindowspasswordfortvuser"
DOMAIN="pcnameordomain"
MOUNT_POINT="/mnt/smb"
LOCAL_DIR="/mnt/ramdisk/slideshow/pdfs"

# Create mount point if it doesn't exist
sudo mkdir -p "$MOUNT_POINT"

# Mount the Windows share
echo "🔗 Mounting network share..."
sudo mount.cifs "//$SERVER_IP/$SHARE_NAME" "$MOUNT_POINT" -o username="$USERNAME",password="$PASSWD",dom="$DOMAIN",iocharset=utf8,nounix,noserverino
if [ $? -ne 0 ]; then
    echo "❌ Failed to mount share"
    exit 1
fi

# Ensure local directory exists
sudo mkdir -p "$LOCAL_DIR"

echo "🔍 Checking for new, updated, or missing files..."

# Loop through each remote file
find "$MOUNT_POINT" -type f | while read -r remote_file; do
    file_name=$(basename "$remote_file")
    local_file="$LOCAL_DIR/$file_name"

    # If the file does not exist locally, copy it
    if [ ! -f "$local_file" ]; then
        echo "🆕 New file found: $file_name"
        sudo cp -p "$remote_file" "$local_file"
        continue
    fi

    # Compare timestamps (copy if remote file is newer or changed)
    remote_mtime=$(stat -c %Y "$remote_file" 2>/dev/null)
    local_mtime=$(stat -c %Y "$local_file" 2>/dev/null)

    if [ "$remote_mtime" != "$local_mtime" ]; then
        echo "⬆️ File updated on share: $file_name"
        sudo cp -p "$remote_file" "$local_file"
    fi
done

echo "🧹 Checking for files to remove..."

# Build a list of remote basenames
remote_files=$(find "$MOUNT_POINT" -type f -exec basename {} \;)

# Remove any local files that no longer exist remotely
find "$LOCAL_DIR" -type f | while read -r local_file; do
    file_name=$(basename "$local_file")
    if ! grep -qxF "$file_name" <<< "$remote_files"; then
        echo "❌ Removing file not on share: $file_name"
        sudo rm -f "$local_file"
    fi
done

echo "✅ Sync complete."

# Unmount share
echo "📤 Unmounting share..."
sudo umount "$MOUNT_POINT"
if [ $? -ne 0 ]; then
    echo "⚠️ Failed to unmount share"
    exit 1
fi

# Trigger slideshow refresh
echo "🎉 Refreshing slideshow..."
sudo -u user WAYLAND_DISPLAY=wayland-0 XDG_RUNTIME_DIR=/run/user/1000 /usr/bin/wtype -M ctrl -P F5 -p F5 -m ctrl

echo "✅ Script completed successfully."
/usr/bin/wtype -M ctrl -P F5

exit 0

