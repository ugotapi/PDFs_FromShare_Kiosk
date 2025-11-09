# PDFs_FromShare_Kiosk
Kiosk Script to display PDFs from a Windows share on Raspberry Pi OS - Debian Trixie with Raspberry Pi Desktop compatible
Installation is copying the shell script to a terminal on the pi and Running it. 

- This will display PDF files in a slideshow that are on a Windows PC or servers network share.
- If a PDF is added to the network share on the next crontab run for refresh.sh the file will be added to the slideshow on the Pi connected monitor.
- The files that apache uses for the PDFs on the Pi Device are on a ramdisk. A ramdisk uses RAM to store files. This helps eliminate wear and tear to the microSD Card in the raspberry Pi devices. 
- On a reboot the files on the ramdisk are deleted.
- The working html is copied over from the /home/user/slideshow folder for apache to use for the slideshow.
- There is a script that checks to see if Chromium crashes displaying Aw, Snap. If the error messsage says Aw Snap. It stops and restarts Chromium.
- The main.pdf uses pdf.js from Mozilla https://mozilla.github.io/pdf.js/ to display multipage pdfs and cycle to the next pdf.
- If a pdf is deleted from the network share and it is on the pi pdfs folder it will be deleted from the pi slideshow.
- The Windows share should be setup with a username and password to access. 


WIP



sudo mkdir -p /mnt/ramdisk/pdfs
sudo nano /etc/fstab

----------------------------------------------------------------------------------------------------------------------
add new line at end of file and save	
tmpfs   /mnt/ramdisk   tmpfs   defaults,size=1024M,mode=1777   0   0
----------------------------------------------------------------------------------------------------------------------		
sudo reboot

THEN:
sudo apt install cifs-utils wtype apache2 php grim tesseract-ocr imagemagick -y

sudo cp /etc/xdg/labwc/rc.xml .config/labwc/
nano .config/labwc/rc.xml
----------------------------------------------------------------------------------------------------------------------
ADD THIS INTO KEYBIND


<keybind key="A-W-h">
  <action name="HideCursor"/>
</keybind>


CHANGE WINDOW RULES TO THIS

<windowRules>
    <windowRule identifier="*" serverDecoration="no"/>
  </windowRules>

  

----------------------------------------------------------------------------------------------------------------------

nano .config/wf-panel-pi/wf-panel-pi.ini


----------------------------------------------------------------------------------------------------------------------
Add this to the new file

[panel]
notify_enable=false
notify_timeout=15
autohide=true
autohide_duration=500

----------------------------------------------------------------------------------------------------------------------


nano refresh.sh
----------------------------------------------------------------------------------------------------------------------

PASTE IN TO A NEW FILE

#!/bin/bash

# Configuration
SERVER_IP="yourserver.domain.com"
SHARE_NAME="pdfs"
USERNAME="tvuser"
PASSWD="yoursupercoolpasswordforthewindowsshare"
DOMAIN="machinename_or_domainshortname"
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
# refresh web page by hitting ctrl F5
/usr/bin/wtype -M ctrl -P F5

exit 0

----------------------------------------------------------------------------------------------------------------------

chmod +x refresh.sh
nano checkchromium.sh

----------------------------------------------------------------------------------------------------------------------
PASTE INTO NEW FILE

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

----------------------------------------------------------------------------------------------------------------------

chmod +x checkchromium.sh

----------------------------------------------------------------------------------------------------------------------
In windows Filezilla the slideshow files to /home/user

----------------------------------------------------------------------------------------------------------------------

sudo crontab -e

----------------------------------------------------------------------------------------------------------------------
FOR ROOT ADD A NEW LINE

0 * * * * /home/user/refresh.sh
----------------------------------------------------------------------------------------------------------------------



----------------------------------------------------------------------------------------------------------------------
crontab -e

----------------------------------------------------------------------------------------------------------------------
ADD A NEW LINE
11 * * * * /home/user/checkchromium.sh
----------------------------------------------------------------------------------------------------------------------



sudo nano /etc/apache2/apache2.conf 
----------------------------------------------------------------------------------------------------------------------

PASTE THIS IN THE DIRECTORY AREA

<Directory /mnt/ramdisk>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

----------------------------------------------------------------------------------------------------------------------


sudo nano /etc/apache2/sites-available/000-default.conf 

----------------------------------------------------------------------------------------------------------------------

CHANGE DOCUMENTROOT AREA TO RAMDISK

 DocumentRoot /mnt/ramdisk 
 
 
