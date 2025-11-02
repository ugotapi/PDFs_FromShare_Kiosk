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
