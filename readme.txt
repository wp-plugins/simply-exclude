=== Simply Exclude ===
Contributors: Paul Menard
Donate link: http://www.codehooligans.com
Tags: admin, posts, pages, categories, tags, exclude, include, is_front, is_archive, is_search, is_feed
Requires at least: 2.3
Tested up to: 2.5
Stable tag: 1.2

== Description ==

Provides an interface to selectively exclude/include categories, tags and page from the 4 actions used by WordPress

is_front - When the user views the Front page. 
is_archive - When the user views an category or tags Archive.
is_search - When the user views a search result page.
is_feed - When a Feed is viewed/requested.

[Plugin Homepage](http://www.codehooligans.com/2008/04/27/simply-exclude-plugin/ "SimplyExclude Plugin for WordPress")

== Installation ==

1. Upload the extracted plugin folder and contained files to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings -> Simply Exclude (Options -> Simply Exclude is pre 2.5).
4. Once on the plugin admin page you should see navigation links at the top: Manage Categories, Manage Tags, Manage Pages.On each of these page you can selectively exclude/include the cat/tag/page for the given action is_front/is_archive/is_feed/is_search. 

== Frequently Asked Questions ==

= I've excluded all my categories and all tags why am I seeing my 404 page? =

Well you need to be careful when excluding both categories and tags. Since a post can be associated with both there is potential that you have excluded all your posts because they are either members of excluded categories or members or excluded tags. 

= Why doesn't the plugin auto-update itself?

I've not added that feature. A minor version coming soon. 

== Screenshots ==

None