=== Content Manager for AI Agents ===
Contributors: fyrins
Tags: mcp, ai, abilities, agents, api
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let AI agents manage your content through the Abilities API: posts, media, terms, meta, templates, WooCommerce and SEOPress.

== Description ==

Content Manager for AI Agents registers content management abilities with the WordPress Abilities API. With the MCP Adapter active, an MCP client (Claude, ChatGPT, Cursor…) can discover and run them: an AI agent can draft and publish posts, fix a typo across a page, upload images, classify content, fill in SEO fields or build a variable product, always within the permissions of the WordPress user it authenticates as.

Ability families, with a few names:

* **Content**: five abilities for each public post type, such as `list-posts`, `get-page`, `create-post`, `update-page`, `delete-post`, with dates, sticky posts and terms in every taxonomy. Plus `replace-in-post-content`, which swaps a literal string without resending the whole content, with a dry run and an expected count.
* **Taxonomies**: `list-taxonomies`, `list-terms`, `get-term`, `create-term`, `update-term`, `delete-term`.
* **Media**: `list-media`, `upload-media` (from a URL), `update-media`, `set-featured-image`, `delete-media`.
* **Meta**: `get-post-meta`, `update-post-meta`, `get-term-meta`, `update-term-meta`, `delete-term-meta`.
* **Templates**: `list-templates`, `get-template`, `create-template`, `update-template`, `delete-template`, for block templates and template parts.
* **WooCommerce**, when active: `create-product`, `update-product`, product variations and global attributes.
* **SEOPress**, when active: `get-post-seopress`, `update-post-seopress`, `get-term-seopress`, `update-term-seopress`, and `update-post-schemas-seopress` with SEOPress Pro.
* **WP Grid Builder**, when active: `list-wpgb-facets`, `index-wpgb-facets` (queues facets for indexing, off by default) and `clear-wpgb-cache`, for users who can manage options.

Every name carries the `cm4ai/` prefix.

Each ability can be switched on or off from Settings → Content Manager for AI Agents. Abilities that delete something are off until you enable them.

= Requirements =

* WordPress 6.9 or later: the Abilities API ships with core from 6.9.
* The [MCP Adapter](https://github.com/WordPress/mcp-adapter/releases) plugin, to reach the abilities from an MCP client. Without it, the abilities are still registered and usable through the Abilities API, and a notice on the Plugins screen and the settings screen says the adapter is missing.

= Security =

* Every call runs as a logged-in WordPress user and is checked against that user's capabilities, on the object it targets: `edit_post` or `delete_post` on that very post, `edit_term` on that term. Publishing, changing the author, pinning a post and assigning terms need the same capabilities as in the admin.
* Protected meta keys (starting with `_`) stay out of reach unless a developer allow-lists them.
* Templates can only be changed by users who can edit the theme (`edit_theme_options`).
* WP Grid Builder facets, index and cache are reserved to users who can manage options (`manage_options`), as in its own screens.
* Markup goes through the usual WordPress filtering for users without the `unfiltered_html` capability, and the response says when something was stripped.
* Destructive abilities are disabled by default, and any ability can be disabled individually. The settings screen requires `manage_options`.

= Third-party abilities =

The settings screen also lists the abilities other plugins register, grouped by plugin.

* An ability its plugin opened to MCP can be switched off for MCP requests. The plugin that provides it keeps using it everywhere else.
* An ability its plugin kept closed to MCP (SEOPress's own abilities, for instance) can be exposed to the MCP server as a tool. These are off by default, and exposing one grants no extra right: the ability's own permission check still decides.

Core and MCP Adapter abilities are never listed.

= Privacy =

* The plugin sends no data to its author or any third party, collects nothing, and has no tracking or telemetry.
* The only outgoing request it can make is the download performed by `upload-media`, to the URL the MCP client supplies.
* The MCP client acts with the rights of the WordPress user it authenticates as, and sees what that user can see. Choose that account, and its role, deliberately.
* What the AI service connected to your MCP client does with the content it reads is governed by that service, not by this plugin.

= For developers =

Every query, result and permission decision can be adjusted through `cm4ai_*` filters, and new abilities can be added. Custom abilities are added on the `cm4ai_register_abilities` action, and every call can be observed or adjusted with `cm4ai_before_execute`, `cm4ai_execute_result` and `cm4ai_after_execute`. Full reference on GitHub: https://github.com/Fyrins/content-manager-for-ai

= Source code =

The settings screen ships compiled in `build/`. Its sources live in `src/` in the public repository, https://github.com/Fyrins/content-manager-for-ai, and are built with `@wordpress/scripts`: `npm install`, then `npm run build`.

== Installation ==

1. Install and activate Content Manager for AI Agents.
2. Install and activate the MCP Adapter from its GitHub releases.
3. Go to Settings → Content Manager for AI Agents and choose which abilities are enabled.
4. In the Connection tab, create an application password for the WordPress user the agent will act as.
5. Copy the configuration of your client (Claude Code, Claude Desktop, Cursor, Codex or another HTTP client) into it. It points at `https://your-site.example/wp-json/mcp/mcp-adapter-default-server`, authenticated with that user and application password.

== Frequently Asked Questions ==

= Which MCP clients work with it? =

Any MCP client that can reach the MCP Adapter's server and authenticate as a WordPress user, for instance with an application password. The abilities are found through the adapter's discovery tool and run through its execute tool.

= What can the agent do on my site? =

Exactly what the WordPress user it authenticates as can do, limited to the abilities you left enabled. An Author account cannot publish someone else's post through an ability any more than in the admin.

= How do I disable an ability? =

Uncheck it under Settings → Content Manager for AI Agents. A disabled ability is not registered at all, so MCP clients do not see it. Developers can also force the state with the `cm4ai_is_enabled` filter.

= WooCommerce or SEOPress is not installed. Does it matter? =

No. Their abilities are simply not registered, and they appear on their own once the plugin is active. The SEOPress schema ability also needs SEOPress Pro.

= Does it work without the MCP Adapter? =

The abilities are registered through the core Abilities API and can be used by any other consumer of that API. To reach them from an MCP client, install the adapter. A notice on the Plugins screen and on the plugin's settings screen reminds you until it runs.

= Why is the MCP Adapter not bundled? =

It is not hosted on WordPress.org, and several plugins may ship it. Installing it once, as its own plugin, avoids version conflicts.

= Can I extend or restrict it from code? =

Yes. Filters let you narrow the post types and taxonomies, remove operations, override any permission decision, allow-list protected meta keys, change queries and results, and add your own abilities. See the developer documentation on GitHub.

= Does it delete anything on its own? =

No. Abilities only run when a client calls them, and every ability that deletes is off until an administrator enables it. Uninstalling the plugin removes its two options.

== Screenshots ==

1. Connection tab: the MCP server URL, an application password created in one click, and ready-to-paste configurations for Claude Code, Claude Desktop, Cursor, Codex and other clients.
2. Abilities tab: enable or disable each ability, grouped in cards and split into read-only and write.
3. Other plugins tab: abilities registered by other plugins, such as SEOPress, with a legend explaining the badges.

== Changelog ==

= 2.0.0 =
* Renamed from WP MCP Abilities to Content Manager for AI Agents, for its release on WordPress.org. The plugin folder, text domain and every identifier change: abilities are now named `cm4ai/<name>`, hooks and options use the `cm4ai_` prefix. Update the MCP clients and code that call the former names.
* The switches saved under the former name are copied on activation.
* Security: writing or deleting a meta key also checks the meta capability of that key, so an `auth_callback` given to `register_meta()` applies.
* Fix: `upload-media` stops the download as soon as the file exceeds the allowed size, instead of writing it to disk in full first.

Earlier versions, released as WP MCP Abilities: https://github.com/Fyrins/content-manager-for-ai/blob/main/CHANGELOG.md

== Upgrade Notice ==

= 2.0.0 =
New name and identifiers. Abilities are now `cm4ai/<name>`: update your MCP client configurations and any code using the former `wpmcpa_` hooks.
