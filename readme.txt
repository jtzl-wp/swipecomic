=== SwipeComic ===
Contributors: jtzl
Tags: comic, webcomic, manga, reader, mobile
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 1.0.0-alpha.8
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A mobile-first comic reader for WordPress with intuitive episode management and clean URLs.

== Description ==

SwipeComic is a modern comic reader plugin for WordPress designed for webcomic creators. It provides a streamlined workflow for managing comic episodes with powerful image handling and flexible display options.

**Core Features:**

* Custom post type for comic episodes with classic editor support
* A custom series taxonomy for organizing episodes
* Clean, SEO-friendly URLs (e.g., /series-name/episode-name/)
* Drag-and-drop image gallery with reordering
* Per-image zoom and pan settings with inheritance
* Series cover images and logos with positioning
* Episode ordering within series via drag-and-drop
* Optimized image handling (preserves originals, generates only needed thumbnails)

**Admin Features:**

* Intuitive episode editor with image gallery meta box
* Per-image settings modal for zoom/pan overrides
* Episode settings with episode number and defaults
* Series management with cover images and logos
* Drag-and-drop episode reordering within series
* Custom admin columns showing series, episode number, and image count
* Global plugin settings for defaults and optimization

**URL Structure:**

* Episodes: `/series-slug/episode-slug/` or `/comic/episode-slug/` when episode doesn't belong to any series
* Series archives: `/series/series-slug/`
* Configurable URL prefix (default: "comic") can be left empty for cleaner URLs (can degrade the site performance so please use with caution)

**Image Handling:**

* Upload multiple images per episode
* Drag-and-drop reordering
* Per-image zoom settings (fit, vertical fill, or custom percentage)
* Per-image pan settings (left, center, right, or custom coordinates)
* Settings inheritance from episode defaults to global defaults
* Optimized thumbnail generation (400px default, configurable)
* Original images preserved for frontend display

== Installation ==

**Requirements:**

* WordPress 6.8 or higher
* PHP 8.1 or higher
* Composer (for development)
* Node.js 20.x (for asset building)

**Installation Steps:**

1. Upload the plugin files to `/wp-content/plugins/swipecomic/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure global settings under Settings > SwipeComic
4. Create your first comic episode under SwipeComic > Add New

**Quick Start:**

1. Create a series under SwipeComic > Series
2. Add a cover image and logo to your series
3. Create a new episode and assign it to your series
4. Upload images using the Episode Images meta box
5. Drag to reorder images as needed
6. Set episode number and default zoom/pan settings
7. Publish and view at `/series-slug/episode-slug/`

== Frequently Asked Questions ==

= How do I create a comic episode? =

Go to SwipeComic > Add New in your WordPress admin. Upload images using the Episode Images meta box, set your episode number and defaults in the Episode Settings sidebar, and assign the episode to a series using the Series taxonomy box.

= Can I reorder images within an episode? =

Yes! Simply drag and drop images in the Episode Images meta box to reorder them.

= How do I organize episodes into series? =

Use the Series taxonomy to create and assign series. You can also drag-and-drop episodes to reorder them within a series from the Series edit screen.

= What are zoom and pan settings? =

Zoom controls how images are sized (fit to screen, fill vertically, or custom percentage). Pan controls horizontal positioning (left, center, right, or custom). These can be set globally, per-episode, or per-image with inheritance.

= Can I add a logo to my series? =

Yes! Edit your series and use the Series Logo section to upload a logo and choose its position (upper-left, upper-right, lower-left, or lower-right).

= What image sizes are generated? =

SwipeComic generates only a single thumbnail size (400px by default, configurable) for admin previews. Original images are preserved and used for frontend display to maintain quality.

= How do I customize the URLs? =

Go to Settings > SwipeComic and configure the URL prefix. You can also disable the prefix entirely to use root-level URLs.

= I'm getting 404 errors on comic pages after updating "Use URL Prefix" - what should I do? =

If your site uses object caching (like Redis, Memcached, or similar), WordPress may cache the old permalink structure. After changing the "Use URL Prefix" setting, go to Settings > Permalinks in your WordPress admin and click "Save Changes" to manually flush the permalink cache. This will regenerate the rewrite rules and resolve the 404 errors.

= Is this plugin mobile-friendly? =

Yes! SwipeComic is built with a mobile-first approach. The current alpha release provides basic responsive display. Advanced mobile features like swipe navigation will be added in future releases.

= Can I customize the frontend templates? =

Yes! Copy the template files from the plugin's `templates/` directory to your theme's `swipecomic/` directory and customize as needed.

== Screenshots ==

1. Episode editor with image gallery and drag-and-drop reordering
2. Per-image settings modal for zoom and pan overrides
3. Series management with cover image and logo
4. Episode ordering within series via drag-and-drop
5. Plugin settings page with global defaults
6. Clean URL structure for episodes and series

== Changelog ==

= 1.0.0-alpha.1 =

**Initial Alpha Release**

This is the first alpha release of SwipeComic, focusing on core content management features. The plugin is feature-complete for Phase 1 (admin functionality and basic display).

**Implemented Features:**

* Custom post type registration with classic editor support
* Hierarchical series taxonomy
* Clean URL rewriting with conflict prevention
* Episode images meta box with drag-and-drop reordering
* Per-image zoom and pan settings with modal interface
* Episode settings meta box (episode number, defaults)
* Series cover images and logos with positioning
* Episode ordering within series
* Plugin settings page with global defaults
* Optimized image size handling
* Basic frontend templates for episode display
* Comprehensive admin JavaScript and CSS
* Security measures (nonces, capability checks, sanitization)
* Activation/deactivation hooks with proper cleanup
* Data validation and error handling

**Known Limitations:**

* Basic frontend display only (no advanced viewer yet)
* No swipe navigation (planned for Phase 2)
* No episode navigation controls (planned for Phase 2)
* No PhotoSwipe integration (planned for Phase 2)
* Limited template customization options

**Coming in Future Releases:**

* Advanced image viewer with zoom/pan controls
* Touch-optimized swipe navigation
* Episode navigation (previous/next)
* PhotoSwipe integration for lightbox viewing
* Series archive pages with episode listings
* Additional template tags and hooks
* Performance optimizations
* Accessibility improvements

== Upgrade Notice ==

= 1.0.0-alpha.1 =
Initial alpha release. This is a development version for testing and feedback. Not recommended for production use yet.
