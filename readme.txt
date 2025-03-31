=== KeyCDN Push Zone Addon ===
Contributors: x3mp
Tags: cdn, keycdn, push zone, pull zone, cdn enabler
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add Push Zone functionality to KeyCDN integration when using CDN Enabler.

== Description ==

KeyCDN Push Zone Addon extends the functionality of the CDN Enabler plugin, allowing you to automatically upload your media files and static assets to KeyCDN Push Zones in addition to the Pull Zone functionality provided by CDN Enabler.

This add-on is perfect for WordPress users who want to leverage both Pull and Push Zones from KeyCDN for optimal performance and workflow.

= Features =

* Works alongside the existing CDN Enabler plugin
* Automatic file uploads to Push Zones when media is added or updated
* Automatic deletion from Push Zones when media is deleted
* Push static files (CSS, JS, images, etc.) after theme or plugin updates
* Batch upload all files to Push Zone
* Purge CDN cache with one click
* Support for custom upload directories
* Select which directories to include in push operations
* Real-time progress tracking for file operations
* Support for constants and environment variables for API credentials

= Why Use Both Pull and Push Zones? =

* **Pull Zones** are great for most content and are easier to set up, but they require KeyCDN to fetch content from your origin server the first time it's requested.
* **Push Zones** allow you to proactively upload content to the CDN, which is useful for ensuring all your content is available on the CDN before it's requested by users.

== Installation ==

1. Make sure the CDN Enabler plugin is installed and activated
2. Upload the `keycdn-push-addon` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to 'Settings > KeyCDN Push Zone' to configure the add-on

== Frequently Asked Questions ==

= Do I need CDN Enabler for this plugin to work? =

Yes, this is an add-on for CDN Enabler and requires it to be installed and activated.

= Where do I find my Push Zone ID? =

Log in to your KeyCDN dashboard, navigate to your Push Zone, and the Zone ID will be displayed in the zone details.

= How long does it take to push all files? =

The time depends on the number and size of your files. The process runs in the background to avoid timeouts and shows real-time progress.

= Can I define my API credentials in wp-config.php? =

Yes, you can define `KEYCDN_API_KEY` and `KEYCDN_PUSH_ZONE_ID` constants in your wp-config.php file for added security.

= What happens if the push process stalls? =

The plugin detects stalled processes and provides a reset button to restart the operation.

= I use a custom upload directory. Will this plugin work with it? =

Yes! The plugin includes a Custom Directories feature that allows you to specify which directories should be pushed to your CDN. This supports both the default WordPress uploads directory and any custom directories you may be using.

= How do I configure custom directories? =

In the plugin settings page, navigate to the "Directory Settings" section. You can enable/disable the default WordPress uploads directory and add any number of custom directories that should be included in push operations.
