=== the beheader ===
Contributors: postpostmodern
Donate link: http://www.heifer.org/
Tags: admin, header, sortable
Requires at least: 2.9.2
Tested up to: 3.1RC4
Stable tag: trunk

Sortable headers for admin tables.  Currently handles the edit posts/pages, user management, and plugins pages.

== Description ==
Adds sortable headers in wp-admin.  Compatible with Wordpress < 3.1, and improves upon the WP_List_Table class in 3.1
Tested with Wordpress 2.9.2, 3.0.4, and 3.1 RC4. More testing to come.
Requires PHP 5, as any halfway decent PHP application should.

== Installation ==
1. Place /beheader/ in the /wp-content/plugins/ directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Sort them columns.  Currently handles /wp-admin/edit.php , /wp-admin/users.php , and /wp-admin/plugins.php

== Changelog ==
= 0.43 =
* Fixed bug in uasort callback, now works in PHP < 5.3

= 0.42 =
* Fixed bug in plugins.php sort callback

= 0.4 =
* Sortable headers added for plugins.php

= 0.3 =
* Compatibility with sortable headers in Wordpress 3.1

= 0.2 =
* Tags and categories is much better. Lots of joins, but seems to work pretty well, there are still cases on multiple tags/categories where they are in the wrong order.

= 0.15 =
* First support for sorting by tags and categories.  It's buggy, and screws up the post count and pagination.  You might want to hold off on this one.

= 0.1 =
* Initial public release. No documentation, NOT recommended for production use.
