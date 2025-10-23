<?php
/**
 * Assets class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

/**
 * Handles asset loading and management.
 *
 * @since 1.0.0
 */
class Assets {

	/**
	 * Asset manifest cache.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	private $manifest = null;

	/**
	 * Initialize assets.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue plugin assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		// Only enqueue if shortcode is present.
		if ( ! $this->should_enqueue() ) {
			return;
		}

		$manifest = $this->get_manifest();

		// Enqueue JavaScript.
		$js_file = $manifest['swipecomic.js'] ?? 'swipecomic.js';
		wp_enqueue_script(
			'swipecomic',
			JTZL_SWIPECOMIC_URL . 'build/' . $js_file,
			array(),
			JTZL_SWIPECOMIC_VER,
			true
		);

		// Enqueue CSS.
		$css_file = $manifest['swipecomic.css'] ?? 'swipecomic.css';
		wp_enqueue_style(
			'swipecomic',
			JTZL_SWIPECOMIC_URL . 'build/' . $css_file,
			array(),
			JTZL_SWIPECOMIC_VER
		);

		// Localize script with data.
		wp_localize_script(
			'swipecomic',
			'swipecomicData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'swipecomic_nonce' ),
			)
		);
	}

	/**
	 * Check if assets should be enqueued.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if assets should be enqueued.
	 */
	private function should_enqueue() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'swipecomic' );
	}

	/**
	 * Get asset manifest.
	 *
	 * @since 1.0.0
	 *
	 * @return array Asset manifest.
	 */
	private function get_manifest() {
		if ( null !== $this->manifest ) {
			return $this->manifest;
		}

		$manifest_path = JTZL_SWIPECOMIC_DIR . 'build/asset-manifest.json';

		if ( ! file_exists( $manifest_path ) ) {
			$this->manifest = array();
			return $this->manifest;
		}

		$manifest_content = file_get_contents( $manifest_path );
		$this->manifest   = json_decode( $manifest_content, true ) ?? array();

		return $this->manifest;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on swipecomic edit screens.
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		global $post_type;
		if ( 'swipecomic' !== $post_type ) {
			return;
		}

		// Enqueue WordPress media uploader.
		wp_enqueue_media();

		// Enqueue jQuery UI Sortable.
		wp_enqueue_script( 'jquery-ui-sortable' );

		$manifest = $this->get_manifest();

		// Enqueue admin JavaScript.
		$admin_js_file = $manifest['swipecomic-admin.js'] ?? 'swipecomic-admin.js';
		wp_enqueue_script(
			'swipecomic-admin',
			JTZL_SWIPECOMIC_URL . 'admin/js/' . $admin_js_file,
			array( 'jquery', 'jquery-ui-sortable' ),
			JTZL_SWIPECOMIC_VER,
			true
		);

		// Enqueue admin CSS.
		$admin_css_file = $manifest['swipecomic-admin.css'] ?? 'swipecomic-admin.css';
		wp_enqueue_style(
			'swipecomic-admin',
			JTZL_SWIPECOMIC_URL . 'admin/css/' . $admin_css_file,
			array(),
			JTZL_SWIPECOMIC_VER
		);

		// Localize script with data.
		wp_localize_script(
			'swipecomic-admin',
			'swipecomicAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'swipecomic_admin_nonce' ),
				'uploadButtonText'  => __( 'Select Images', 'swipecomic' ),
				'uploadButtonTitle' => __( 'Select Episode Images', 'swipecomic' ),
				'removeConfirm'     => __( 'Are you sure you want to remove this image?', 'swipecomic' ),
				'logoUploadTitle'   => __( 'Select Logo Image', 'swipecomic' ),
				'logoUploadButton'  => __( 'Use as Logo', 'swipecomic' ),
				'uploadLogoText'    => __( 'Upload Logo', 'swipecomic' ),
				'changeLogoText'    => __( 'Change Logo', 'swipecomic' ),
				'removeLogoConfirm' => __( 'Are you sure you want to remove the logo?', 'swipecomic' ),
			)
		);
	}

	/**
	 * Get versioned asset URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $asset Asset filename.
	 * @return string Versioned asset URL.
	 */
	public function get_asset_url( $asset ) {
		$manifest = $this->get_manifest();
		$file     = $manifest[ $asset ] ?? $asset;

		return JTZL_SWIPECOMIC_URL . 'build/' . $file;
	}
}
