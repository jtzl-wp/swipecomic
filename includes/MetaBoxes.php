<?php
/**
 * MetaBoxes class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

/**
 * Handles meta boxes for swipecomic post type.
 *
 * @since 1.0.0
 */
class MetaBoxes {

	/**
	 * Initialize meta boxes.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_swipecomic', array( $this, 'save_episode_images' ), 10, 2 );
		add_action( 'save_post_swipecomic', array( $this, 'save_episode_settings' ), 10, 2 );
		add_action( 'save_post_swipecomic', array( $this, 'save_episode_logo' ), 10, 2 );
	}

	/**
	 * Register meta boxes.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'swipecomic_images',
			__( 'Episode Images', 'swipecomic' ),
			array( $this, 'render_episode_images_meta_box' ),
			'swipecomic',
			'normal',
			'high'
		);

		add_meta_box(
			'swipecomic_settings',
			__( 'Episode Settings', 'swipecomic' ),
			array( $this, 'render_episode_settings_meta_box' ),
			'swipecomic',
			'side',
			'default'
		);

		add_meta_box(
			'swipecomic_logo',
			__( 'Episode Logo', 'swipecomic' ),
			array( $this, 'render_episode_logo_meta_box' ),
			'swipecomic',
			'side',
			'default'
		);
	}

	/**
	 * Render episode images meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_episode_images_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'swipecomic_save_images', 'swipecomic_images_nonce' );

		// Get existing images.
		$images = get_post_meta( $post->ID, '_swipecomic_images', true );
		if ( ! is_array( $images ) ) {
			$images = array();
		}
		?>
		<div class="swipecomic-images-container">
			<div class="swipecomic-images-toolbar">
				<button type="button" class="button button-primary swipecomic-upload-images">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Upload Images', 'swipecomic' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'Upload images for this episode. Drag to reorder.', 'swipecomic' ); ?>
				</p>
			</div>

			<div class="swipecomic-images-grid" id="swipecomic-images-grid">
				<?php foreach ( $images as $index => $image_data ) : ?>
					<?php $this->render_image_item( $image_data, $index ); ?>
				<?php endforeach; ?>
			</div>

			<input type="hidden" name="swipecomic_images_data" id="swipecomic-images-data" value="<?php echo esc_attr( wp_json_encode( $images ) ); ?>" />
		</div>
		<?php
	}

	/**
	 * Render a single image item in the gallery.
	 *
	 * @since 1.0.0
	 *
	 * @param array $image_data Image data array.
	 * @param int   $index      Image index.
	 */
	private function render_image_item( $image_data, $index ) {
		$image_id      = isset( $image_data['id'] ) ? absint( $image_data['id'] ) : 0;
		$zoom_override = isset( $image_data['zoom_override'] ) ? $image_data['zoom_override'] : null;
		$pan_override  = isset( $image_data['pan_override'] ) ? $image_data['pan_override'] : null;
		$has_overrides = ! empty( $zoom_override ) || ! empty( $pan_override );
		$thumbnail_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
		$thumbnail_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		if ( ! $thumbnail_url ) {
			return;
		}
		?>
		<div class="swipecomic-image-item" data-image-id="<?php echo esc_attr( $image_id ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="swipecomic-image-preview">
				<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $thumbnail_alt ); ?>" />
				<?php if ( $has_overrides ) : ?>
					<span class="swipecomic-override-badge" title="<?php esc_attr_e( 'Has custom settings', 'swipecomic' ); ?>">
						<span class="dashicons dashicons-admin-generic"></span>
					</span>
				<?php endif; ?>
			</div>
			<div class="swipecomic-image-actions">
				<button type="button" class="button swipecomic-image-settings" title="<?php esc_attr_e( 'Image Settings', 'swipecomic' ); ?>">
					<span class="dashicons dashicons-admin-generic"></span>
				</button>
				<button type="button" class="button swipecomic-image-remove" title="<?php esc_attr_e( 'Remove Image', 'swipecomic' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save episode images data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_episode_images( $post_id ) {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verification doesn't require sanitization.
		if ( ! isset( $_POST['swipecomic_images_nonce'] ) || ! wp_verify_nonce( $_POST['swipecomic_images_nonce'], 'swipecomic_save_images' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Don't save for revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Get and sanitize images data.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data is sanitized after decoding.
		$images_json = isset( $_POST['swipecomic_images_data'] ) ? wp_unslash( $_POST['swipecomic_images_data'] ) : '';
		$images_data = json_decode( $images_json, true );

		if ( ! is_array( $images_data ) ) {
			$images_data = array();
		}

		// Sanitize each image entry.
		$sanitized_images = array();
		foreach ( $images_data as $index => $image ) {
			if ( ! isset( $image['id'] ) ) {
				continue;
			}

			$sanitized_image = array(
				'id'    => absint( $image['id'] ),
				'order' => absint( $index ),
			);

			// Sanitize zoom override.
			if ( isset( $image['zoom_override'] ) && ! empty( $image['zoom_override'] ) ) {
				$sanitized_image['zoom_override'] = sanitize_text_field( $image['zoom_override'] );
			}

			// Sanitize pan override.
			if ( isset( $image['pan_override'] ) && ! empty( $image['pan_override'] ) ) {
				$sanitized_image['pan_override'] = sanitize_text_field( $image['pan_override'] );
			}

			$sanitized_images[] = $sanitized_image;
		}

		// Save to post meta.
		update_post_meta( $post_id, '_swipecomic_images', $sanitized_images );
	}

	/**
	 * Render episode settings meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_episode_settings_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'swipecomic_save_settings', 'swipecomic_settings_nonce' );

		// Get existing settings.
		$episode_number    = get_post_meta( $post->ID, '_swipecomic_episode_number', true );
		$default_zoom      = get_post_meta( $post->ID, '_swipecomic_default_zoom_type', true );
		$zoom_custom_value = get_post_meta( $post->ID, '_swipecomic_default_zoom_value', true );
		$default_pan       = get_post_meta( $post->ID, '_swipecomic_default_pan_type', true );
		$pan_custom_x      = get_post_meta( $post->ID, '_swipecomic_default_pan_x', true );
		$pan_custom_y      = get_post_meta( $post->ID, '_swipecomic_default_pan_y', true );

		// Set defaults if empty.
		if ( empty( $default_zoom ) ) {
			$default_zoom = 'fit';
		}
		if ( empty( $default_pan ) ) {
			$default_pan = 'center';
		}
		?>
		<div class="swipecomic-settings-container">
			<p>
				<label for="swipecomic_episode_number">
					<?php esc_html_e( 'Episode Number', 'swipecomic' ); ?>
				</label>
				<input type="number" id="swipecomic_episode_number" name="swipecomic_episode_number" value="<?php echo esc_attr( $episode_number ); ?>" min="1" step="1" class="widefat" />
			</p>

			<p>
				<label for="swipecomic_default_zoom">
					<?php esc_html_e( 'Default Zoom Level', 'swipecomic' ); ?>
				</label>
				<select id="swipecomic_default_zoom" name="swipecomic_default_zoom" class="widefat swipecomic-zoom-select">
					<option value="fit" <?php selected( $default_zoom, 'fit' ); ?>><?php esc_html_e( 'Fit', 'swipecomic' ); ?></option>
					<option value="vFill" <?php selected( $default_zoom, 'vFill' ); ?>><?php esc_html_e( 'Vertical Fill', 'swipecomic' ); ?></option>
					<option value="custom" <?php selected( $default_zoom, 'custom' ); ?>><?php esc_html_e( 'Custom', 'swipecomic' ); ?></option>
				</select>
			</p>

			<p class="swipecomic-zoom-custom" style="<?php echo ( 'custom' === $default_zoom ) ? '' : 'display:none;'; ?>">
				<label for="swipecomic_zoom_custom_value">
					<?php esc_html_e( 'Custom Zoom Value (%)', 'swipecomic' ); ?>
				</label>
				<input type="number" id="swipecomic_zoom_custom_value" name="swipecomic_zoom_custom_value" value="<?php echo esc_attr( $zoom_custom_value ); ?>" min="1" step="1" class="widefat" />
			</p>

			<p>
				<label for="swipecomic_default_pan">
					<?php esc_html_e( 'Default Pan Position', 'swipecomic' ); ?>
				</label>
				<select id="swipecomic_default_pan" name="swipecomic_default_pan" class="widefat swipecomic-pan-select">
					<option value="left" <?php selected( $default_pan, 'left' ); ?>><?php esc_html_e( 'Left', 'swipecomic' ); ?></option>
					<option value="right" <?php selected( $default_pan, 'right' ); ?>><?php esc_html_e( 'Right', 'swipecomic' ); ?></option>
					<option value="center" <?php selected( $default_pan, 'center' ); ?>><?php esc_html_e( 'Center', 'swipecomic' ); ?></option>
					<option value="custom" <?php selected( $default_pan, 'custom' ); ?>><?php esc_html_e( 'Custom', 'swipecomic' ); ?></option>
				</select>
			</p>

			<p class="swipecomic-pan-custom" style="<?php echo ( 'custom' === $default_pan ) ? '' : 'display:none;'; ?>">
				<label for="swipecomic_pan_custom_x">
					<?php esc_html_e( 'Custom Pan X (px)', 'swipecomic' ); ?>
				</label>
				<input type="number" id="swipecomic_pan_custom_x" name="swipecomic_pan_custom_x" value="<?php echo esc_attr( $pan_custom_x ); ?>" step="1" class="widefat" />
			</p>

			<p class="swipecomic-pan-custom" style="<?php echo ( 'custom' === $default_pan ) ? '' : 'display:none;'; ?>">
				<label for="swipecomic_pan_custom_y">
					<?php esc_html_e( 'Custom Pan Y (px)', 'swipecomic' ); ?>
				</label>
				<input type="number" id="swipecomic_pan_custom_y" name="swipecomic_pan_custom_y" value="<?php echo esc_attr( $pan_custom_y ); ?>" step="1" class="widefat" />
			</p>
		</div>
		<?php
	}

	/**
	 * Save episode settings data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_episode_settings( $post_id ) {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verification doesn't require sanitization.
		if ( ! isset( $_POST['swipecomic_settings_nonce'] ) || ! wp_verify_nonce( $_POST['swipecomic_settings_nonce'], 'swipecomic_save_settings' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Don't save for revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Save episode number.
		if ( isset( $_POST['swipecomic_episode_number'] ) ) {
			$episode_number = absint( $_POST['swipecomic_episode_number'] );
			if ( $episode_number > 0 ) {
				update_post_meta( $post_id, '_swipecomic_episode_number', $episode_number );
			} else {
				delete_post_meta( $post_id, '_swipecomic_episode_number' );
			}
		}

		// Save default zoom.
		if ( isset( $_POST['swipecomic_default_zoom'] ) ) {
			$zoom_type = sanitize_text_field( wp_unslash( $_POST['swipecomic_default_zoom'] ) );

			// Validate zoom type.
			if ( ! in_array( $zoom_type, array( 'fit', 'vFill', 'custom' ), true ) ) {
				$zoom_type = 'fit'; // Default fallback for invalid values.
			}

			update_post_meta( $post_id, '_swipecomic_default_zoom_type', $zoom_type );

			// Save custom zoom value if type is custom.
			if ( 'custom' === $zoom_type && isset( $_POST['swipecomic_zoom_custom_value'] ) ) {
				$custom_value = absint( $_POST['swipecomic_zoom_custom_value'] );
				if ( $custom_value > 0 ) {
					update_post_meta( $post_id, '_swipecomic_default_zoom_value', $custom_value );
				} else {
					delete_post_meta( $post_id, '_swipecomic_default_zoom_value' );
				}
			} else {
				delete_post_meta( $post_id, '_swipecomic_default_zoom_value' );
			}
		}

		// Save default pan.
		if ( isset( $_POST['swipecomic_default_pan'] ) ) {
			$pan_type = sanitize_text_field( wp_unslash( $_POST['swipecomic_default_pan'] ) );

			// Validate pan type.
			if ( ! in_array( $pan_type, array( 'left', 'right', 'center', 'custom' ), true ) ) {
				$pan_type = 'center'; // Default fallback for invalid values.
			}

			update_post_meta( $post_id, '_swipecomic_default_pan_type', $pan_type );

			// Save custom pan values if type is custom.
			if ( 'custom' === $pan_type && isset( $_POST['swipecomic_pan_custom_x'] ) && isset( $_POST['swipecomic_pan_custom_y'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via trim and intval below.
				$custom_x_val = trim( wp_unslash( $_POST['swipecomic_pan_custom_x'] ) );
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via trim and intval below.
				$custom_y_val = trim( wp_unslash( $_POST['swipecomic_pan_custom_y'] ) );

				if ( '' !== $custom_x_val && '' !== $custom_y_val ) {
					$custom_x = intval( $custom_x_val );
					$custom_y = intval( $custom_y_val );
					update_post_meta( $post_id, '_swipecomic_default_pan_x', $custom_x );
					update_post_meta( $post_id, '_swipecomic_default_pan_y', $custom_y );
				} else {
					delete_post_meta( $post_id, '_swipecomic_default_pan_x' );
					delete_post_meta( $post_id, '_swipecomic_default_pan_y' );
				}
			} else {
				delete_post_meta( $post_id, '_swipecomic_default_pan_x' );
				delete_post_meta( $post_id, '_swipecomic_default_pan_y' );
			}
		}
	}

	/**
	 * Render episode logo meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_episode_logo_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'swipecomic_save_logo', 'swipecomic_logo_nonce' );

		// Get existing logo.
		$logo_id  = get_post_meta( $post->ID, '_swipecomic_logo_id', true );
		$logo_url = '';
		$logo_alt = '';

		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'thumbnail' );
			$logo_alt = get_post_meta( $logo_id, '_wp_attachment_image_alt', true );
		}
		?>
		<div class="swipecomic-logo-container">
			<div class="swipecomic-logo-preview" id="swipecomic-logo-preview" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" style="max-width: 100%; height: auto; display: block; margin-bottom: 10px;" />
				<?php endif; ?>
			</div>

			<p>
				<button type="button" class="button button-secondary swipecomic-upload-logo" id="swipecomic-upload-logo">
					<span class="dashicons dashicons-format-image"></span>
					<?php echo $logo_id ? esc_html__( 'Change Logo', 'swipecomic' ) : esc_html__( 'Upload Logo', 'swipecomic' ); ?>
				</button>

				<a href="#" class="button-link-delete swipecomic-remove-logo" id="swipecomic-remove-logo" style="<?php echo $logo_id ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Remove Logo', 'swipecomic' ); ?>
				</a>
			</p>

			<input type="hidden" name="swipecomic_logo_id" id="swipecomic-logo-id" value="<?php echo esc_attr( $logo_id ); ?>" />

			<p class="description">
				<?php esc_html_e( 'Upload a custom logo or title image for this episode.', 'swipecomic' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save episode logo data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_episode_logo( $post_id ) {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verification doesn't require sanitization.
		if ( ! isset( $_POST['swipecomic_logo_nonce'] ) || ! wp_verify_nonce( $_POST['swipecomic_logo_nonce'], 'swipecomic_save_logo' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Don't save for revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Save logo ID.
		if ( isset( $_POST['swipecomic_logo_id'] ) ) {
			$logo_id = absint( $_POST['swipecomic_logo_id'] );

			if ( $logo_id > 0 ) {
				update_post_meta( $post_id, '_swipecomic_logo_id', $logo_id );
			} else {
				delete_post_meta( $post_id, '_swipecomic_logo_id' );
			}
		}
	}
}
