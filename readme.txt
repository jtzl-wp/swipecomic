=== SwipeComic ===
Contributors: jtzl
Tags: comic, webcomic, manga, reader, mobile, photoswipe
Requires at least: 6.8
Tested up to: 6.9
Stable tag: 1.0.4
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A mobile-first comic reader for WordPress with PhotoSwipe integration, swipe navigation, and intuitive episode management.

== Description ==

SwipeComic is a modern comic reader plugin for WordPress designed for webcomic creators. It provides a streamlined workflow for managing comic episodes with powerful image handling and flexible display options.

**Core Features:**

* Custom post type for comic episodes with classic editor support
* A custom series taxonomy for organizing episodes
* Clean, SEO-friendly URLs (e.g., /series-name/episode-name/)
* PhotoSwipe 5 integration for immersive comic viewing
* Touch-optimized swipe navigation with pinch-to-zoom
* Drag-and-drop image gallery with reordering
* Per-image zoom and pan settings with inheritance
* Series cover images and logos with positioning
* Episode ordering within series via drag-and-drop
* Automatic episode navigation (previous/next)
* Series archive pages with episode listings
* Optimized image handling (preserves originals, generates only needed thumbnails)

**Reader Features:**

* PhotoSwipe 5 lightbox viewer with smooth transitions
* Touch gestures: swipe left/right, pinch-to-zoom, drag-to-pan
* Keyboard navigation: arrow keys, Escape, Home, End
* Automatic episode transitions at boundaries
* Drag hint for wide images (shows once per slide)
* Series logo overlay with configurable positioning
* Mobile-optimized controls and touch targets
* Loading indicators and error handling
* Responsive design for mobile, tablet, and desktop

**Admin Features:**

* Intuitive episode editor with image gallery meta box
* Episode cover image support for better thumbnails
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
* Optional episode cover image for archive thumbnails
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
* Modern browser with ES6 module support
* Composer (for development)
* Node.js 20.x (for asset building)

**Browser Support:**

* Chrome 90+
* Firefox 88+
* Safari 14+
* Edge 90+
* iOS Safari 14+
* Chrome Android 90+

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

= Can I set a custom cover image for episodes? =

Yes! Each episode has a "Cover Image" field in the sidebar (WordPress's featured image). This is used for thumbnails in series archives. If no cover image is set, the plugin automatically uses the first episode image as a fallback.

= Can I add a logo to my series? =

Yes! Edit your series and use the Series Logo section to upload a logo and choose its position (upper-left, upper-right, lower-left, or lower-right).

= What image sizes are generated? =

SwipeComic generates only a single thumbnail size (400px by default, configurable) for admin previews. Original images are preserved and used for frontend display to maintain quality.

= How do I customize the URLs? =

Go to Settings > SwipeComic and configure the URL prefix. You can also disable the prefix entirely to use root-level URLs.

= I'm getting 404 errors on comic pages after updating "Use URL Prefix" - what should I do? =

If your site uses object caching (like Redis, Memcached, or similar), WordPress may cache the old permalink structure. After changing the "Use URL Prefix" setting, go to Settings > Permalinks in your WordPress admin and click "Save Changes" to manually flush the permalink cache. This will regenerate the rewrite rules and resolve the 404 errors.

= Is this plugin mobile-friendly? =

Yes! SwipeComic is built with a mobile-first approach and includes touch-optimized swipe navigation, pinch-to-zoom, and drag-to-pan gestures. The PhotoSwipe viewer is fully responsive and works seamlessly on mobile, tablet, and desktop devices.

= Can I customize the frontend templates? =

Yes! Copy the template files from the plugin's `templates/` directory to your theme's `swipecomic/` directory and customize as needed.

= How do I navigate between episodes? =

Episodes display previous/next navigation links automatically. In the PhotoSwipe viewer, you can also swipe through all images in an episode, and the plugin will automatically load the next episode when you reach the end.

= What is the drag hint? =

For wide images that extend beyond the viewport, a subtle drag hint appears to indicate you can pan horizontally. The hint shows once per slide and always appears on the first image.

= Can I use keyboard shortcuts? =

Yes! Use arrow keys to navigate between images, Escape to close the viewer, and Home/End to jump to the first/last image in the current episode.

== Screenshots ==

1. Episode editor with image gallery and drag-and-drop reordering
2. Per-image settings modal for zoom and pan overrides
3. Series management with cover image and logo
4. Episode ordering within series via drag-and-drop
5. Plugin settings page with global defaults
6. Clean URL structure for episodes and series

== Changelog ==

= 1.0.4 =

Security enhancements and improvements.

= 1.0.3 =

Improved the preloader effect.

= 1.0.2 =

Improved the page transitions between episodes by eliminating a short flash of the background.

= 1.0.1 =

Added image preloading in the PhotoSwipe viewer for smoother transitions, while do not load all images at once.

= 1.0.0 =

**Initial Release**

SwipeComic 1.0.0 is a complete comic reader plugin for WordPress, providing everything you need to publish and manage webcomics with a modern, mobile-first reading experience.

**Features:**

* Custom post type for comic episodes with classic editor support
* Hierarchical series taxonomy for organizing episodes
* Clean, SEO-friendly URL structure
* PhotoSwipe 5.4.3 integration for immersive comic viewing
* Touch-optimized swipe navigation with pinch-to-zoom
* Keyboard navigation support (arrows, Escape, Home, End)
* Automatic episode transitions at boundaries
* AJAX-powered adjacent episode loading
* Drag hint for wide images
* Series logo overlay with configurable positioning
* Episode navigation links (previous/next)
* Series archive pages with episode listings
* Drag-and-drop image gallery with reordering
* Per-image zoom and pan settings with inheritance
* Episode settings (episode number, defaults)
* Series cover images and logos with positioning
* Episode ordering within series via drag-and-drop
* Plugin settings page with global defaults
* Optimized image handling (preserves originals)
* Mobile-optimized controls and touch targets
* Loading indicators and error handling
* Responsive design for all devices
* CSS isolation to prevent theme conflicts
* Tested with Twenty Twenty Five and GeneratePress themes
* Security measures (nonces, capability checks, sanitization)
* Comprehensive data validation and error handling
