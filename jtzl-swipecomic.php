<?php
/**
 * Plugin Name: SwipeComic
 * Description: A mobile-first comic reader for WordPress with swipe navigation and responsive design.
 * Version:     1.0.4
 * Author:      JT G.
 * Text Domain: swipecomic
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package   JTZL_SwipeComic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin version and paths.
define( 'JTZL_SWIPECOMIC_VER', '1.0.4' );
define( 'JTZL_SWIPECOMIC_URL', plugin_dir_url( __FILE__ ) );
define( 'JTZL_SWIPECOMIC_DIR', plugin_dir_path( __FILE__ ) );

// Load Composer autoloader.
$jtzl_swipecomic_autoload = JTZL_SWIPECOMIC_DIR . 'vendor/autoload.php';
if ( file_exists( $jtzl_swipecomic_autoload ) ) {
	require_once $jtzl_swipecomic_autoload;
}

/**
 * Main plugin class for SwipeComic.
 *
 * @since 1.0.0
 */
class JTZL_SwipeComic {

	/**
	 * Settings instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Assets instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * PostType instance.
	 *
	 * @since 1.0.0
	 *
	 * @var PostType
	 */
	private $post_type;

	/**
	 * Taxonomy instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Taxonomy
	 */
	private $taxonomy;

	/**
	 * Rewrite instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Rewrite
	 */
	private $rewrite;

	/**
	 * MetaBoxes instance.
	 *
	 * @since 1.0.0
	 *
	 * @var MetaBoxes
	 */
	private $meta_boxes;

	/**
	 * ImageHandler instance.
	 *
	 * @since 1.0.0
	 *
	 * @var ImageHandler
	 */
	private $image_handler;

	/**
	 * TemplateFunctions instance.
	 *
	 * @since 1.0.0
	 *
	 * @var TemplateFunctions
	 */
	private $template_functions;

	/**
	 * TemplateLoader instance.
	 *
	 * @since 1.0.4
	 *
	 * @var TemplateLoader
	 */
	private $template_loader;

	/**
	 * AjaxHandlers instance.
	 *
	 * @since 1.0.4
	 *
	 * @var AjaxHandlers
	 */
	private $ajax_handlers;

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Initialize component classes.
		$this->settings           = new JTZL\SwipeComic\Settings();
		$this->assets             = new JTZL\SwipeComic\Assets();
		$this->post_type          = new JTZL\SwipeComic\PostType();
		$this->taxonomy           = new JTZL\SwipeComic\Taxonomy();
		$this->rewrite            = new JTZL\SwipeComic\Rewrite();
		$this->meta_boxes         = new JTZL\SwipeComic\MetaBoxes();
		$this->image_handler      = new JTZL\SwipeComic\ImageHandler();
		$this->template_functions = new JTZL\SwipeComic\TemplateFunctions();
		$this->template_loader    = new JTZL\SwipeComic\TemplateLoader();
		$this->ajax_handlers      = new JTZL\SwipeComic\AjaxHandlers();

		// Initialize components.
		$this->settings->init();
		$this->assets->init();
		$this->post_type->init();
		$this->taxonomy->init();
		$this->rewrite->init();
		$this->meta_boxes->init();
		$this->image_handler->init();
		$this->template_functions->init();
		$this->template_loader->init();
		$this->ajax_handlers->init();
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public static function init_plugin() {
		$swipecomic = new self();
		$swipecomic->init();
	}
}

// Initialize the plugin in the global namespace.
if ( ! function_exists( 'jtzl_swipecomic_init' ) ) {
	/**
	 * Initialize the SwipeComic plugin.
	 *
	 * @since 1.0.0
	 */
	function jtzl_swipecomic_init() { // phpcs:ignore.
		JTZL_SwipeComic::init_plugin();
	}
	add_action( 'plugins_loaded', 'jtzl_swipecomic_init' );
}

/**
 * Plugin activation hook.
 *
 * Runs when the plugin is activated. Sets up default options,
 * registers post types and taxonomies for rewrite rules, and
 * flushes rewrite rules.
 *
 * @since 1.0.0
 */
function jtzl_swipecomic_activate() {
	// Set default plugin options if they don't exist.
	add_option( 'swipecomic_default_zoom', 'fit' );
	add_option( 'swipecomic_default_pan', 'center' );
	add_option( 'swipecomic_thumbnail_size', 400 );
	add_option( 'swipecomic_media_optimization', 'keep_all' );

	// Set default URL structure options if they don't exist.
	// For reactivation, these will already exist and won't be overwritten.
	add_option( 'swipecomic_use_url_prefix', true );
	add_option( 'swipecomic_url_prefix', 'comic' );

	// Store plugin version for future migrations.
	add_option( 'swipecomic_version', JTZL_SWIPECOMIC_VER );

	// Read the actual values to use for rewrite rules.
	// This handles both new installs (uses defaults just set) and reactivations (uses existing values).
	$use_prefix = (bool) get_option( 'swipecomic_use_url_prefix' );
	$prefix     = get_option( 'swipecomic_url_prefix' );

	// Manually register post type and taxonomy before flushing rewrite rules.
	// This is necessary because the init hook has already fired during activation.
	$post_type = new JTZL\SwipeComic\PostType();
	$post_type->register_post_type();

	$taxonomy = new JTZL\SwipeComic\Taxonomy();
	$taxonomy->register_taxonomy();

	// Pass values directly to ensure correct rewrite rules are generated.
	$rewrite = new JTZL\SwipeComic\Rewrite();
	$rewrite->add_rewrite_rules_with_params( $use_prefix, $prefix );

	// Flush rewrite rules to ensure clean URLs work.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'jtzl_swipecomic_activate' );

/**
 * Plugin deactivation hook.
 *
 * Runs when the plugin is deactivated. Flushes rewrite rules
 * but preserves all plugin data.
 *
 * @since 1.0.0
 */
function jtzl_swipecomic_deactivate() {
	// Flush rewrite rules to clean up custom URLs.
	flush_rewrite_rules();

	// Note: We do NOT delete any data on deactivation.
	// Data should only be removed on uninstall (via uninstall.php).
}
register_deactivation_hook( __FILE__, 'jtzl_swipecomic_deactivate' );

/**
 * Add settings link to plugin list page.
 *
 * @since 1.0.0
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function jtzl_swipecomic_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'edit.php?post_type=swipecomic&page=swipecomic-settings' ) ),
		esc_html__( 'Settings', 'swipecomic' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'jtzl_swipecomic_plugin_action_links' );
