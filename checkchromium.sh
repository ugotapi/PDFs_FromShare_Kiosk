#!/bin/bash
# Detect "Aw, Snap!" in Chromium under Wayland and restart if needed.

# --- Wayland environment setup ---
export XDG_RUNTIME_DIR="/run/user/1000"
export WAYLAND_DISPLAY="wayland-0"
SCREENSHOT="/mnt/ramdisk/chromium_check.png"
TEXTFILE="/mnt/ramdisk/chromium_ocr.txt"
# LOGFILE="/mnt/ramdisk/chromium_watchdog.log"

# 1. Take screenshot of entire desktop (Wayland)
grim "$SCREENSHOT"

# 2. OCR the screenshot
tesseract "$SCREENSHOT" "$TEXTFILE" &>/dev/null

# 3. Read the OCR text
TEXT_CONTENT=$(cat "${TEXTFILE}.txt")

# 4. Check for "Aw, Snap!"
if echo "$TEXT_CONTENT" | grep -qi "snap"; then
    echo "$(date): Aw, Snap! detected — restarting Chromium" 

    # Kill all Chromium instances
    killall chromium

    sleep 5

    # 5. Restart Chromium in kiosk mode within the Wayland session
    chromium --ozone-platform=wayland \
        --enable-features=UseOzonePlatform \
        --start-fullscreen --noerrdialogs --kiosk "http://127.0.0.1/slideshow" &
else
    echo "$(date): Chromium OK" 



fi
