=== Ship Fixes ===
Contributors: patriciabt
Tags: block, table of contents, youtube, embed, privacy
Requires at least: 7.2
Tested up to: 7.2
Requires PHP: 8.4
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Small fixes and stopgaps for my sites, each one switched on or off: the Table of Contents block without the Gutenberg plugin, and YouTube privacy-enhanced links in the Embed block.

== Description ==

Ship Fixes holds small fixes and stopgaps, each one switched on or off in its settings.

* **Table of Contents block**: the Table of Contents block of the Gutenberg plugin, for sites without that plugin. Posts written with the block keep their table of contents, and the block stays in the editor. It steps aside when the Gutenberg plugin or WordPress itself provides the block.
* **YouTube privacy-enhanced links**: the Embed block accepts YouTube links in privacy-enhanced mode (`https://www.youtube-nocookie.com/embed/...`, as YouTube's Share, Embed, "privacy-enhanced mode" gives them) and shows them as YouTube videos, like youtube.com links. The player is written by your site, without asking YouTube's oEmbed service.
* **Settings**: on a single site, or activated on some sites of a network, each site has Settings, Ship Fixes (its administrators): one switch per fix. Activated for the whole network, it also has Network Admin, Settings, Ship Fixes: the fixes for every site, and whether site administrators may decide for their own site.

The Table of Contents block comes from the Gutenberg plugin (version 24.4.1, GPL-2.0-or-later).

== Privacy ==

Ship Fixes keeps no personal data, sets no cookies and keeps nothing in the visitor's browser. A page with a YouTube video loads YouTube's player in the visitor's browser: see External services.

== External services ==

**YouTube** (YouTube privacy-enhanced links fix): a page with a youtube-nocookie.com video loads YouTube's player from youtube-nocookie.com in the visitor's browser, which sends YouTube the visitor's address and the page's address. In privacy-enhanced mode YouTube says it stores no information about visitors unless they play the video. Ship Fixes itself sends YouTube nothing, neither from the editor nor when the page is shown. YouTube's terms: https://www.youtube.com/t/terms ; Google's privacy policy: https://policies.google.com/privacy

Updates are installed by hand (Plugins, Add Plugin, Upload Plugin).

== Uninstall ==

Deleting the plugin removes its settings from every site. It keeps nothing else.

== Changelog ==

= 0.2.0 =
* New fix: YouTube privacy-enhanced links (youtube-nocookie.com/embed/...) in the Embed block, shown as YouTube videos. On by default.

= 0.1.1 =
* Code: the generated script files (`*.asset.php`, `blocks-manifest.php`) stop when opened directly.

= 0.1.0 =
* First version: the Table of Contents block for sites without the Gutenberg plugin, switched on or off per site or for the whole network.
