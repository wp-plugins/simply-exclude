=== Simply Exclude ===
Contributors: Paul Menard
Donate link: http://www.codehooligans.com
Tags: admin, posts, pages, categories, tags, exclude, include, is_front, is_archive, is_search, is_feed
Requires at least: 2.6
Tested up to: 2.9.2
Stable tag: 1.7.7

== Description ==

Provides an interface to selectively exclude/include categories, tags, authors and pages from the 4 actions used by WordPress

is_front - When the user views the Front page. 
is_archive - When the user views an category or tags Archive.
is_search - When the user views a search result page.
is_feed - When a Feed is viewed/requested.

Note: Page exclusions only work for search.

[Plugin Homepage](http://www.codehooligans.com/projects/wordpress/simply-exclude/ "SimplyExclude Plugin for WordPress")

== Installation ==

1. Upload the extracted plugin folder and contained files to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings -> Simply Exclude (Options -> Simply Exclude in pre 2.5).
4. Once on the plugin admin page you should see navigation links at the top: Manage Categories, Manage Tags, Manage Pages.On each of these page you can selectively exclude/include the cat/tag/page for the given action is_front/is_archive/is_feed/is_search. 

== Frequently Asked Questions ==

= I've excluded all my categories and all tags why am I seeing my 404 page? =

Well you need to be careful when excluding both categories and tags. Since a post can be associated with both there is potential that you have excluded all your posts because they are either members of excluded categories or members or excluded tags. 

= I've excluded Pages but attachments (images) for those pages are showing up. Why? =

Only the parent Page itself is excluded from searches. By default WordPress does not yet include Pages in search. Make sure you have other search plugins correctly configured to not search attachments. 

= I've excluded a Page via the plugin but it still shows up in my sidebar when wp_list_pages is called. Why? =

At the time (version 1.6.1) the plugin only effects Pages included in the traditional Search feature on a site. It does not trap all selections of Pages via other internal WordPress functions...yet!


== Screenshots ==

1. Simply Exclude Admin interface showing Category exclusion options. 
2. Page Admin interface allow for exclusion of Page from search. 


== Changelog == 

= 1.7.7 =
2010-05-12
Fixes: Mainly bug fixes and code cleanup. Most bugs discovered via using WP_DEBUG for uninitialized variables. 

= 1.7.6 =
2009-11-14 
Fixes: Issue with the Pages exclusion. Many users reporting a permissions issue. 
Additions: Added handler logic to interface with two other plugins. One of the often used Google XML Sitemaps. When setting Page or Category exclusions you now have the option to update the Google XML Sitemaps exclude pages and categories automatically. The other plugin is Search Unleashed. 

= 1.7.5 =
2009-07-15 Fixed some PHP warning by checking variable is set. Also added style for 2.8 interface. Very minor changes. 

= 1.7.2.1 =
2009-07-01 Fixed some PHP warning by checking variable is set. Also added style for 2.8 interface. Very minor changes. 

= 1.7.2 =
2009-02-05 Fixed some PHP warning by checking variable is set. Also added style to 2.7 interface. 

= 1.7.1 =
2008-07-16 Fixed an issue with WP 2.6 where it automatically decided to unserialize the option data structure. 

= 1.7 =
2008-05-29 Added Author to the Include/Exclude logic. Now you can exclude Author's Posts from Search, Home, RSS, Archive.

= 1.6 =
2008-05-22 Fixed various items. Added format display for Categories and Pages to reveal hierarchy, Disable plugin functions when searching in admin. This also corrected a display exclusion bug when showing categories and pages.

= 1.5 = 
20008-04-27 Fixed display issues. Changes 'List' to 'Archive'. Added tags inclusion/exclusion login. Works only with WP 2.3 and greater.

= 1.1 =
2008-12-15: Added logic to work with WP version greater than 2.2

= 1.0 =
2007-11-20: Initial release
