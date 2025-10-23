<?php
/**
 * Settings class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

/**
 * Handles plugin settings and options page.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Option group name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_GROUP = 'swipecomic_settings';

	/**
	 * Settings page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'swipecomic-settings';

	/**
	 * Initialize settings.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=swipecomic',
			__( 'SwipeComic Settings', 'swipecomic' ),
			__( 'Settings', 'swipecomic' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// URL Structure section.
		add_settings_section(
			'swipecomic_url_structure',
			__( 'URL Structure', 'swipecomic' ),
			array( $this, 'render_url_structure_section' ),
			self::PAGE_SLUG
		);

		// Use URL prefix setting.
		register_setting(
			self::OPTION_GROUP,
			'swipecomic_use_url_prefix',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => array( $this, 'sanitize_use_prefix' ),
			)
		);

		add_settings_field(
			'swipecomic_use_url_prefix',
			__( 'Use URL Prefix', 'swipecomic' ),
			array( $this, 'render_use_prefix_field' ),
			self::PAGE_SLUG,
			'swipecomic_url_structure'
		);

		// URL prefix slug setting.
		register_setting(
			self::OPTION_GROUP,
			'swipecomic_url_prefix',
			array(
				'type'              => 'string',
				'default'           => 'comic',
				'sanitize_callback' => array( $this, 'sanitize_url_prefix' ),
			)
		);

		add_settings_field(
			'swipecomic_url_prefix',
			__( 'Prefix Slug', 'swipecomic' ),
			array( $this, 'render_url_prefix_field' ),
			self::PAGE_SLUG,
			'swipecomic_url_structure'
		);
	}

	/**
	 * Render URL structure section description.
	 *
	 * @since 1.0.0
	 */
	public function render_url_structure_section() {
		echo '<p>' . esc_html__( 'Configure how your comic URLs are structured.', 'swipecomic' ) . '</p>';
	}

	/**
	 * Render use prefix field.
	 *
	 * @since 1.0.0
	 */
	public function render_use_prefix_field() {
		$use_prefix = get_option( 'swipecomic_use_url_prefix', true );
		?>
		<label>
			<input type="checkbox" name="swipecomic_use_url_prefix" id="swipecomic_use_url_prefix" value="1" <?php checked( $use_prefix ); ?> />
			<?php esc_html_e( 'Enable URL prefix (recommended)', 'swipecomic' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Using a prefix prevents conflicts with WordPress pages and other plugins.', 'swipecomic' ); ?>
		</p>
		<?php
		$this->render_url_preview();
	}

	/**
	 * Render URL prefix field.
	 *
	 * @since 1.0.0
	 */
	public function render_url_prefix_field() {
		$prefix     = get_option( 'swipecomic_url_prefix', 'comic' );
		$use_prefix = get_option( 'swipecomic_use_url_prefix', true );
		?>
		<input 
			type="text" 
			name="swipecomic_url_prefix" 
			id="swipecomic_url_prefix" 
			value="<?php echo esc_attr( $prefix ); ?>" 
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'The base slug for your comic URLs. Only alphanumeric characters and hyphens allowed.', 'swipecomic' ); ?>
		</p>
		<?php
	}

	/**
	 * Render URL preview.
	 *
	 * @since 1.0.0
	 */
	private function render_url_preview() {
		$use_prefix = get_option( 'swipecomic_use_url_prefix', true );
		$prefix     = get_option( 'swipecomic_url_prefix', 'comic' );
		$home_url   = trailingslashit( home_url() );

		if ( $use_prefix ) {
			$series_url  = $home_url . $prefix . '/my-series/';
			$episode_url = $home_url . $prefix . '/my-series/episode-1/';
			$orphan_url  = $home_url . $prefix . '/episode-without-series/';
		} else {
			$series_url  = $home_url . 'my-series/';
			$episode_url = $home_url . 'my-series/episode-1/';
			$orphan_url  = $home_url . $prefix . '/episode-without-series/';
		}
		?>
		<div class="swipecomic-url-preview" style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
			<strong><?php esc_html_e( 'URL Preview:', 'swipecomic' ); ?></strong>
			<ul style="margin: 10px 0 0 20px; list-style: disc;">
				<li><code><?php echo esc_html( $series_url ); ?></code> - <?php esc_html_e( 'Series archive', 'swipecomic' ); ?></li>
				<li><code><?php echo esc_html( $episode_url ); ?></code> - <?php esc_html_e( 'Episode in series', 'swipecomic' ); ?></li>
				<li><code><?php echo esc_html( $orphan_url ); ?></code> - <?php esc_html_e( 'Episode without series', 'swipecomic' ); ?></li>
			</ul>
			<?php if ( ! $use_prefix ) : ?>
				<p style="margin-top: 10px; color: #d63638;">
					<span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span>
					<strong><?php esc_html_e( 'Warning:', 'swipecomic' ); ?></strong>
					<?php esc_html_e( 'Disabling the prefix may cause conflicts with WordPress pages and other plugins. Episodes without a series will still use the prefix as a fallback.', 'swipecomic' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Sanitize use prefix setting.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Input value.
	 * @return bool Sanitized value.
	 */
	public function sanitize_use_prefix( $value ) {
		$old_value = get_option( 'swipecomic_use_url_prefix', true );
		$new_value = (bool) $value;

		// Flush rewrite rules if setting changed.
		if ( $old_value !== $new_value ) {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}

		return $new_value;
	}

	/**
	 * Sanitize URL prefix setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_url_prefix( $value ) {
		$old_value = get_option( 'swipecomic_url_prefix', 'comic' );

		// Sanitize: lowercase, alphanumeric and hyphens only.
		$value = sanitize_title( $value );

		// Prevent empty value.
		if ( empty( $value ) ) {
			add_settings_error(
				'swipecomic_url_prefix',
				'empty_prefix',
				__( 'URL prefix cannot be empty. Using default "comic".', 'swipecomic' ),
				'error'
			);
			return 'comic';
		}

		// Check for reserved slugs.
		$reserved = $this->get_reserved_slugs();
		if ( in_array( $value, $reserved, true ) ) {
			add_settings_error(
				'swipecomic_url_prefix',
				'reserved_slug',
				sprintf(
					/* translators: %s: the reserved slug */
					__( 'The slug "%s" is reserved by WordPress. Please choose a different prefix.', 'swipecomic' ),
					$value
				),
				'error'
			);
			return $old_value;
		}

		// Flush rewrite rules if setting changed.
		if ( $old_value !== $value ) {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}

		return $value;
	}

	/**
	 * Get list of reserved WordPress slugs.
	 *
	 * @since 1.0.0
	 *
	 * @return array Reserved slugs.
	 */
	private function get_reserved_slugs() {
		return array(
			'wp-admin',
			'wp-content',
			'wp-includes',
			'wp-json',
			'author',
			'category',
			'tag',
			'search',
			'page',
			'feed',
			'trackback',
			'sitemap',
			'robots',
			'favicon',
			'admin',
			'login',
			'register',
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle settings saved message.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'swipecomic_messages',
				'swipecomic_message',
				__( 'Settings saved. Rewrite rules have been flushed.', 'swipecomic' ),
				'success'
			);
		}

		settings_errors( 'swipecomic_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'swipecomic' ) );
				?>
			</form>
		</div>
		<script>
		(function() {
			const usePrefixCheckbox = document.getElementById('swipecomic_use_url_prefix');
			const prefixInput = document.getElementById('swipecomic_url_prefix');
			const urlPreview = document.querySelector('.swipecomic-url-preview');

			if (!usePrefixCheckbox || !prefixInput) return;

			// Find the table row containing the prefix field
			const prefixFieldRow = prefixInput.closest('tr');

			if (!prefixFieldRow) return;

			// Toggle prefix field row visibility
			function togglePrefixField() {
				if (usePrefixCheckbox.checked) {
					prefixFieldRow.style.display = '';
				} else {
					prefixFieldRow.style.display = 'none';
				}
				updatePreview();
			}

			// Update URL preview
			function updatePreview() {
				if (!urlPreview) return;

				const usePrefix = usePrefixCheckbox.checked;
				const prefix = prefixInput.value || 'comic';
				const homeUrl = '<?php echo esc_js( trailingslashit( home_url() ) ); ?>';

				let seriesUrl, episodeUrl, orphanUrl;

				if (usePrefix) {
					seriesUrl = homeUrl + prefix + '/my-series/';
					episodeUrl = homeUrl + prefix + '/my-series/episode-1/';
					orphanUrl = homeUrl + prefix + '/episode-without-series/';
				} else {
					seriesUrl = homeUrl + 'my-series/';
					episodeUrl = homeUrl + 'my-series/episode-1/';
					orphanUrl = homeUrl + prefix + '/episode-without-series/';
				}

				const seriesLi = urlPreview.querySelector('li:nth-child(1) code');
				const episodeLi = urlPreview.querySelector('li:nth-child(2) code');
				const orphanLi = urlPreview.querySelector('li:nth-child(3) code');

				if (seriesLi) seriesLi.textContent = seriesUrl;
				if (episodeLi) episodeLi.textContent = episodeUrl;
				if (orphanLi) orphanLi.textContent = orphanUrl;

				// Update warning visibility
				const warning = urlPreview.querySelector('p[style*="color"]');
				if (warning) {
					warning.style.display = usePrefix ? 'none' : '';
				}
			}

			// Attach event listeners
			usePrefixCheckbox.addEventListener('change', togglePrefixField);
			if (prefixInput) {
				prefixInput.addEventListener('input', updatePreview);
			}

			// Initial state
			togglePrefixField();
		})();
		</script>
		<?php
	}

	/**
	 * Get URL prefix setting.
	 *
	 * @since 1.0.0
	 *
	 * @return string URL prefix.
	 */
	public static function get_url_prefix() {
		return get_option( 'swipecomic_url_prefix', 'comic' );
	}

	/**
	 * Check if URL prefix is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if prefix is enabled.
	 */
	public static function use_url_prefix() {
		return (bool) get_option( 'swipecomic_use_url_prefix', true );
	}
}
