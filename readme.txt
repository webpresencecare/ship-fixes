=== Ship Fixes ===
Contributors: patriciabt
Tags: block, table of contents, youtube, embed, privacy
Requires at least: 7.1
Tested up to: 7.2
Requires PHP: 8.4
Stable tag: 0.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Small fixes and stopgaps for my sites, each one switched on or off: the Table of Contents block without the Gutenberg plugin, and YouTube videos in privacy-enhanced mode.

== Description ==

Ship Fixes holds small fixes and stopgaps, each one switched on or off in its settings.

* **Table of Contents block**: the Table of Contents block of the Gutenberg plugin, for sites without that plugin. Posts written with the block keep their table of contents, and the block stays in the editor. It steps aside when the Gutenberg plugin or WordPress itself provides the block.
* **YouTube privacy-enhanced mode**: YouTube videos in Embed blocks play from youtube-nocookie.com (YouTube's privacy-enhanced mode), whatever the form of the link: youtube.com (watch, embed, Shorts, live, playlists), youtu.be or youtube-nocookie.com. The video's title stays on the player (for screen readers), the link's start time is kept (`t=2117s`, `t=35m17s`...) and YouTube's share-tracking parameters (`si`, `feature`, `pp`) are left out. Players already on your pages change too, nothing to empty. Switched off, the players are shown as WordPress makes them.
* **Settings**: on a single site, or activated on some sites of a network, each site has Settings, Ship Fixes (its administrators): one switch per fix. Activated for the whole network, it also has Network Admin, Settings, Ship Fixes: the fixes for every site, and whether site administrators may decide for their own site.

The Table of Contents block comes from the Gutenberg plugin (version 24.4.1, GPL-2.0-or-later).

== Privacy ==

Ship Fixes keeps no personal data, sets no cookies and keeps nothing in the visitor's browser. A page with a YouTube video loads YouTube's player in the visitor's browser: see External services.

== External services ==

**YouTube** (YouTube privacy-enhanced mode fix): as WordPress does for every YouTube link, your site asks YouTube's oEmbed service (https://www.youtube.com/oembed) for the player and the video's title when a post with a YouTube video is saved or first shown, then keeps the answer; the request carries the video's link and your site's address, nothing about visitors. A page with a YouTube video loads YouTube's player from youtube-nocookie.com in the visitor's browser, which sends YouTube the visitor's address and the page's address; in privacy-enhanced mode YouTube says it stores no information about visitors unless they play the video. YouTube's terms: https://www.youtube.com/t/terms ; Google's privacy policy: https://policies.google.com/privacy

Updates are installed by hand (Plugins, Add Plugin, Upload Plugin).

== Uninstall ==

Deleting the plugin removes its settings from every site. It keeps nothing else.

== Changelog ==

= 0.3.0 =
* YouTube privacy-enhanced mode (was: privacy-enhanced links): every YouTube link in an Embed block plays from youtube-nocookie.com (youtube.com, youtu.be, Shorts, live, embed and playlist links), with the video's own title, the link's start time and without share-tracking parameters. WordPress's oEmbed makes the players again; players cached before are changed too.
* French translation of the new texts.

= 0.2.0 =
* New fix: YouTube privacy-enhanced links (youtube-nocookie.com/embed/...) in the Embed block, shown as YouTube videos. On by default.

= 0.1.1 =
* Code: the generated script files (`*.asset.php`, `blocks-manifest.php`) stop when opened directly.

= 0.1.0 =
* First version: the Table of Contents block for sites without the Gutenberg plugin, switched on or off per site or for the whole network.
