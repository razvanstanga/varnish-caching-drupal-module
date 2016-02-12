=== VCaching ===
Donate link: www.paypal.com/use/email/razvan_stanga@yahoo.com
Contributors: razvanstanga
Tags: varnish, purge, cache, caching, optimization, performance, traffic
Requires at least: 4.0
Tested up to: 4.4
Stable tag: 1.3.3
License: GPLv2 or later

Drupal Varnish Cache 3.x/4.x integration

== Description ==
Complete Drupal Varnish Cache 3.x/4.x integration.

This plugin handles all integration with Varnish Cache. It was designed for high traffic websites.

Main features

* admin interface, see screenshots
* console for manual purges, supports regular expressions so you can purge an entire folder or just a single file
* supports every type of Varnish Cache implementation, see screenshots for examples
* unlimited number of Varnish Cache servers
* use of custom headers when communicating with Varnish Cache does not interfere with other caching plugins, cloudflare, etc
* purge key method so you don't need to setup ACLs
* debugging
* actively maintained

You can control the following from the Varnish Caching admin panel :

* Enable/Disable caching
* Homepage cache TTL
* Cache TTL (for every other page)
* IPs/Hosts to clear cache to support every type of Varnish Cache implementation
* Override default TTL in posts/pages
* Purge key based PURGE
* Debugging option
* console for precise manual purges

This plugin also auto purges Varnish Cache when your site is modified.

Varnish Caching sends a PURGE request to Varnish Cache when a page or post is modified. This occurs when editing, publishing, commenting or deleting an item, and when changing themes.
Not all pages are purged every time, depending on your Varnish configuration. When a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories or tags associated with the page

<a href="https://www.varnish-cache.org/">Varnish Cache</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor does it configure Varnish for Drupal. It's expected you already did that on your own using the provided config files.

Ported from my Wordpress plugin https://wordpress.org/plugins/vcaching/

== Installation ==

Use the provided config files for Varnish Cache located in vcaching/varnish-conf folder. Just edit the backend IP/port and ACLs.
You can also use the purge key method. You must fill in lib/purge.vcl the purge key.

== Frequently Asked Questions ==

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x/4.x.

= Why doesn't every page flush when I make a new post? =

The only pages that should purge are the post's page, the front page, categories, and tags.

= How do I manually purge the whole cache? =

Click the 'Purge cache' menu link on the "Configure/system/Varnish Caching" menu.

= How do I manually purge cache? =

Use the console. For example you can purge the whole uploads folder with the URL /wp-content/uploads/.*

= Varnish Statistics =

Statistics need a special setup. More info on the Statistics tab on your Drupal environment.

= How do I configure my Varnish Cache VCL? =

Use the provided Varnish Cache configuration files located in vcaching/varnish-conf folder.

= Can I use this with a proxy service like CloudFlare? =

Yes.

== Changelog ==

= 1.0 =
* Initial commit

== Upgrade Notice ==

* none

== Screenshots ==

1. admin panel
2. example integration
3. override default TTL in posts/pages
4. console purge
5. varnish statistics
