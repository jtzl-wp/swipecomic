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
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 3 );
	}

	/**
	 * Enqueue plugin assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		// Enqueue frontend CSS on swipecomic posts and series archives.
		if ( is_singular( 'swipecomic' ) || is_tax( 'swipecomic_series' ) ) {
			$manifest = $this->get_manifest();
			$css_file = $manifest['swipecomic.css'] ?? 'swipecomic.css';
			wp_enqueue_style(
				'swipecomic-frontend',
				JTZL_SWIPECOMIC_URL . 'build/' . $css_file,
				array(),
				JTZL_SWIPECOMIC_VER
			);
		}

		// Only enqueue PhotoSwipe assets on swipecomic posts.
		if ( is_singular( 'swipecomic' ) ) {
			// Enqueue PhotoSwipe CSS.
			wp_enqueue_style(
				'photoswipe',
				JTZL_SWIPECOMIC_URL . 'build/photoswipe.css',
				array( 'swipecomic-frontend' ),
				'5.4.3'
			);

			// Enqueue SwipeComic viewer (ES module with PhotoSwipe bundled).
			$manifest = $this->get_manifest();
			$js_file  = $manifest['swipecomic-viewer.js'] ?? 'swipecomic-viewer.js';

			wp_enqueue_script(
				'swipecomic-viewer',
				JTZL_SWIPECOMIC_URL . 'build/' . $js_file,
				array(),
				JTZL_SWIPECOMIC_VER,
				true
			);

			// Get episode/series data.
			$viewer_data = $this->inject_viewer_data();

			// Pass all data to JavaScript using wp_localize_script.
			// This creates window.swipecomicData with all episode and config data.
			wp_localize_script(
				'swipecomic-viewer',
				'swipecomicData',
				array_merge(
					$viewer_data,
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'swipecomic_viewer_nonce' ),
					)
				)
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
		$is_swipecomic_list = ( 'edit.php' === $hook ) && isset( $GLOBALS['post_type'] ) && 'swipecomic' === $GLOBALS['post_type'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Taxonomy parameter is sanitized and only used for comparison.
		$taxonomy           = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
		$is_series_taxonomy = ( 'term.php' === $hook || 'edit-tags.php' === $hook ) && 'swipecomic_series' === $taxonomy;

		// Only load on swipecomic screens or series taxonomy screens.
		if ( ! $is_swipecomic_post && ! $is_swipecomic_list && ! $is_series_taxonomy ) {
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
	 * Inject viewer data securely using wp_add_inline_script.
	 *
	 * @since 2.0.0
	 */
	private function inject_viewer_data() {
		if ( ! is_singular( 'swipecomic' ) ) {
			return;
		}

		// Get episode data.
		$images       = TemplateFunctions::get_swipecomic_images();
		$episode_zoom = TemplateFunctions::get_episode_zoom();
		$episode_pan  = TemplateFunctions::get_episode_pan();
		$global_zoom  = Settings::get_default_zoom();
		$global_pan   = Settings::get_default_pan();
		$series_data  = TemplateFunctions::get_series_data();
		$series_id    = $series_data ? $series_data['term_id'] : null;

		// Prepare series logo data.
		$series_logo_url      = false;
		$series_logo_position = 'upper-left';
		$series_archive_url   = false;
		if ( $series_data && isset( $series_data['logo'] ) ) {
			$series_logo_url      = $series_data['logo']['url'];
			$series_logo_position = $series_data['logo']['position'];
		}

		// Get series archive URL if series exists.
		if ( $series_id ) {
			$series_archive_url = get_term_link( $series_id, 'swipecomic_series' );
			if ( is_wp_error( $series_archive_url ) ) {
				$series_archive_url = false;
			}
		}

		// Get adjacent episode IDs for navigation.
		$navigation = TemplateFunctions::get_episode_navigation( get_the_ID() );

		// Return the data array to be merged with wp_localize_script.
		return array(
			'episodeId'        => get_the_ID(),
			'seriesId'         => $series_id,
			'images'           => $images,
			'episodeDefaults'  => array(
				'zoom' => $episode_zoom,
				'pan'  => $episode_pan,
			),
			'seriesLogo'       => array(
				'url'      => $series_logo_url,
				'position' => $series_logo_position,
			),
			'seriesArchiveUrl' => $series_archive_url,
			'globalDefaults'   => array(
				'zoom' => $global_zoom,
				'pan'  => $global_pan,
			),
			'autoOpen'         => true, // Auto-open viewer on page load for comic reading experience.
			'navigation'       => array(
				'nextEpisodeId' => $navigation['next'],
				'prevEpisodeId' => $navigation['prev'],
			),
		);
	}

	/**
	 * Add type="module" attribute to ES module scripts.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string Modified script tag.
	 */
	public function add_module_type_attribute( $tag, $handle, $src ) {
		// List of script handles that should be loaded as ES modules.
		$module_handles = array(
			'swipecomic-viewer',
		);

		if ( in_array( $handle, $module_handles, true ) ) {
			// Do not modify if type attribute already present.
			if ( false === stripos( $tag, ' type=' ) ) {
				// Safely inject before closing '>' of opening tag.
				$tag = preg_replace( '/\<script\b/i', '<script type="module"', $tag, 1 );
			}
		}

		return $tag;
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
