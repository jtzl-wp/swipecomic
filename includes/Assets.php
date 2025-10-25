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
		// Enqueue frontend CSS for swipecomic posts.
		if ( is_singular( 'swipecomic' ) ) {
			wp_enqueue_style(
				'swipecomic-frontend',
				JTZL_SWIPECOMIC_URL . 'assets/css/swipecomic-frontend.css',
				array(),
				JTZL_SWIPECOMIC_VER
			);
		}

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
		$is_swipecomic_post = ( 'post.php' === $hook || 'post-new.php' === $hook ) && isset( $GLOBALS['post_type'] ) && 'swipecomic' === $GLOBALS['post_type'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Taxonomy parameter is sanitized and only used for comparison.
		$taxonomy           = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
		$is_series_taxonomy = ( 'term.php' === $hook || 'edit-tags.php' === $hook ) && 'swipecomic_series' === $taxonomy;

		// Only load on swipecomic edit screens or series taxonomy screens.
		if ( ! $is_swipecomic_post && ! $is_series_taxonomy ) {
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

		// Get delete on remove setting.
		$delete_on_remove = Settings::delete_on_remove();

		// Localize script with data.
		wp_localize_script(
			'swipecomic-admin',
			'swipecomicAdmin',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'swipecomic_admin_nonce' ),
				'uploadButtonText'   => __( 'Select Images', 'swipecomic' ),
				'uploadButtonTitle'  => __( 'Select Episode Images', 'swipecomic' ),
				'deleteOnRemove'     => $delete_on_remove,
				'removeConfirm'      => $delete_on_remove
					? __( 'Are you sure you want to delete this image? This will permanently remove it from your Media Library and cannot be undone.', 'swipecomic' )
					: __( 'Remove this image from the episode? It will remain in your Media Library.', 'swipecomic' ),
				'removeButtonText'   => $delete_on_remove ? __( 'Delete Image', 'swipecomic' ) : __( 'Remove Image', 'swipecomic' ),
				'coverUploadTitle'   => __( 'Select Series Cover Image', 'swipecomic' ),
				'coverUploadButton'  => __( 'Use as Cover', 'swipecomic' ),
				'uploadCoverText'    => __( 'Upload Cover Image', 'swipecomic' ),
				'changeCoverText'    => __( 'Change Cover Image', 'swipecomic' ),
				'removeCoverConfirm' => $delete_on_remove
					? __( 'Are you sure you want to delete the cover image? This will permanently remove it from your Media Library and cannot be undone.', 'swipecomic' )
					: __( 'Remove the cover image from this series? It will remain in your Media Library.', 'swipecomic' ),
				'logoUploadTitle'    => __( 'Select Logo Image', 'swipecomic' ),
				'logoUploadButton'   => __( 'Use as Logo', 'swipecomic' ),
				'uploadLogoText'     => __( 'Upload Logo', 'swipecomic' ),
				'changeLogoText'     => __( 'Change Logo', 'swipecomic' ),
				'removeLogoConfirm'  => $delete_on_remove
					? __( 'Are you sure you want to delete the logo? This will permanently remove it from your Media Library and cannot be undone.', 'swipecomic' )
					: __( 'Remove the logo from this series? It will remain in your Media Library.', 'swipecomic' ),
				'savingOrder'        => __( 'Saving order...', 'swipecomic' ),
				'orderError'         => __( 'Error updating episode order.', 'swipecomic' ),
				'episodeNumberLabel' => __( 'Episode #', 'swipecomic' ),
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
