# PlatformDisplay UK

**PLEASE NOTE: This is still very much a work in progress and some features may not work correctly or be missing.**

For a working example you are free to use to your heart's content please visit [PlatformDisplay UK](https://platformdisplay.uk/).

PlatformDisplay UK is a project to create a vistual display board that you would find on a station platform across the UK.  
Data is sourced from the Realtime Trains API and is pretty much as accurate as possible, however please note - this is for fun only and shuold not be used for journey planning or any time-sensitive activity - basically, please don't blame me if you miss your train because the time was wrong :rofl::rofl::rofl:! 

To use, please upload to the root or a folder on your web server. You must be running a supported version of PHP for this to work.  

If you are using a Linux server (assuming your webroot is in `/var/www/html/` and you want this to be in the root) - please run the following:

```
cd /var/www/html
git clone https://github.com/pauljrowland/PlatformDisplayUk
cd PlatformDisplayUK
cp * ../ -Rf
cd ..
rm PlatformDisplayUK -Rf
```

You will also need an API key from the [Realtime Trains API](https://api.rtt.io) - which needs to be added into the `/creds/creds.php` file (see `/creds/README.md` for more info).  

I will be adding more customisation options moving forward and feel free to check out the free version at [PlatformDisplay UK](https://platformdisplay.uk/).  

Please feel free to notify me of any issues or suggestions on the [Issues](https://github.com/pauljrowland/PlatformDisplay-UK/issues) page.

**With Thanks so Far:**  
[Sean Petykowski](https://github.com/petykowski) for their [London Underground Font](https://github.com/petykowski/London-Underground-Dot-Matrix-Typeface)  
The [Realtime Trains API](https://api.rtt.io)  
The [Realtime Trains](https://www.realtimetrains.co.uk/) website
