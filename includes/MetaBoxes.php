<?php
/**
 * MetaBoxes class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		add_action( 'save_post_swipecomic', array( $this, 'save_cover_image' ), 10, 2 );
		add_action( 'save_post_swipecomic', array( $this, 'save_episode_images' ), 10, 2 );
		add_action( 'save_post_swipecomic', array( $this, 'save_episode_settings' ), 10, 2 );

		// AJAX handlers for immediate operations.
		add_action( 'wp_ajax_swipecomic_delete_image', array( $this, 'ajax_delete_image' ) );
		add_action( 'wp_ajax_swipecomic_save_images', array( $this, 'ajax_save_images' ) );

		// Display validation errors.
		add_action( 'admin_notices', array( $this, 'display_validation_errors' ) );
	}

	/**
	 * Register meta boxes.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'swipecomic_cover_image',
			__( 'Episode Cover Image', 'swipecomic' ),
			array( $this, 'render_cover_image_meta_box' ),
			'swipecomic',
			'normal',
			'high'
		);

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
			'normal',
			'high'
		);

		// Remove the default featured image meta box from sidebar.
		remove_meta_box( 'postimagediv', 'swipecomic', 'side' );
	}

	/**
	 * Render cover image meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_cover_image_meta_box( $post ) {
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		?>
		<div class="swipecomic-cover-image-container">
			<p class="description">
				<?php esc_html_e( 'Set a cover image for this episode. This will be used as the thumbnail in series archives. If not set, the first episode image will be used automatically.', 'swipecomic' ); ?>
			</p>
			
			<div class="swipecomic-cover-image-wrapper">
				<div id="swipecomic-cover-image-preview" class="swipecomic-cover-preview">
					<?php if ( $thumbnail_id ) : ?>
						<?php echo wp_get_attachment_image( $thumbnail_id, 'medium', false, array( 'id' => 'swipecomic-cover-image-display' ) ); ?>
					<?php else : ?>
						<div class="swipecomic-cover-placeholder">
							<span class="dashicons dashicons-format-image"></span>
							<p><?php esc_html_e( 'No cover image set', 'swipecomic' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
				
				<div class="swipecomic-cover-actions">
					<button type="button" class="button button-primary swipecomic-set-cover-image" id="swipecomic-set-cover-image">
						<?php echo $thumbnail_id ? esc_html__( 'Change Cover Image', 'swipecomic' ) : esc_html__( 'Set Cover Image', 'swipecomic' ); ?>
					</button>
					<?php if ( $thumbnail_id ) : ?>
						<button type="button" class="button swipecomic-remove-cover-image" id="swipecomic-remove-cover-image" title="<?php echo esc_attr( Settings::delete_on_remove() ? __( 'Delete Cover Image', 'swipecomic' ) : __( 'Remove Cover Image', 'swipecomic' ) ); ?>">
							<?php echo Settings::delete_on_remove() ? esc_html__( 'Delete Cover Image', 'swipecomic' ) : esc_html__( 'Remove Cover Image', 'swipecomic' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
			
			<input type="hidden" id="swipecomic-cover-image-id" name="swipecomic_cover_image_id" value="<?php echo esc_attr( $thumbnail_id ); ?>" />
		</div>
		<?php
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
				<button type="button" class="button swipecomic-image-remove" title="<?php echo esc_attr( Settings::delete_on_remove() ? __( 'Delete Image', 'swipecomic' ) : __( 'Remove Image', 'swipecomic' ) ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save cover image data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_cover_image( $post_id ) {
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

		// Save cover image.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WordPress core for post saves.
		if ( isset( $_POST['swipecomic_cover_image_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WordPress core for post saves.
			$thumbnail_id = absint( $_POST['swipecomic_cover_image_id'] );

			if ( $thumbnail_id > 0 ) {
				// Verify it's a valid image attachment.
				if ( wp_attachment_is_image( $thumbnail_id ) ) {
					set_post_thumbnail( $post_id, $thumbnail_id );
				}
			} else {
				// Remove the thumbnail if ID is 0.
				delete_post_thumbnail( $post_id );
			}
		}
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

		// Sanitize and validate images using shared helper method.
		$sanitized_images = $this->sanitize_and_validate_images( $images_data );

		// Save to post meta.
		// Note: Images are deleted immediately via AJAX when removed, not during post save.
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

		// Set defaults from plugin settings if empty (for new posts).
		if ( empty( $default_zoom ) ) {
			$default_zoom = Settings::get_default_zoom();
		}
		if ( empty( $default_pan ) ) {
			$default_pan = Settings::get_default_pan();
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

		$has_errors = false;

		// Save episode number.
		if ( isset( $_POST['swipecomic_episode_number'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via trim below.
			$episode_number_raw = trim( wp_unslash( $_POST['swipecomic_episode_number'] ) );

			if ( '' !== $episode_number_raw ) {
				$episode_number = absint( $episode_number_raw );

				if ( $episode_number > 0 ) {
					update_post_meta( $post_id, '_swipecomic_episode_number', $episode_number );
				} else {
					$has_errors = true;
					$this->add_validation_error( __( 'Episode number must be a positive integer.', 'swipecomic' ) );
					delete_post_meta( $post_id, '_swipecomic_episode_number' );
				}
			} else {
				delete_post_meta( $post_id, '_swipecomic_episode_number' );
			}
		}

		// Save default zoom.
		if ( isset( $_POST['swipecomic_default_zoom'] ) ) {
			$zoom_type = sanitize_text_field( wp_unslash( $_POST['swipecomic_default_zoom'] ) );

			// Validate zoom type.
			if ( ! in_array( $zoom_type, array( 'fit', 'vFill', 'custom' ), true ) ) {
				$has_errors = true;
				$this->add_validation_error( __( 'Invalid zoom level. Using default "fit".', 'swipecomic' ) );
				$zoom_type = 'fit'; // Default fallback for invalid values.
			}

			update_post_meta( $post_id, '_swipecomic_default_zoom_type', $zoom_type );

			// Save custom zoom value if type is custom.
			if ( 'custom' === $zoom_type && isset( $_POST['swipecomic_zoom_custom_value'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via trim below.
				$custom_value_raw = trim( wp_unslash( $_POST['swipecomic_zoom_custom_value'] ) );

				if ( '' !== $custom_value_raw ) {
					$custom_value = absint( $custom_value_raw );

					if ( $custom_value > 0 ) {
						update_post_meta( $post_id, '_swipecomic_default_zoom_value', $custom_value );
					} else {
						$has_errors = true;
						$this->add_validation_error( __( 'Custom zoom value must be a positive number.', 'swipecomic' ) );
						delete_post_meta( $post_id, '_swipecomic_default_zoom_value' );
					}
				} else {
					$has_errors = true;
					$this->add_validation_error( __( 'Custom zoom value is required when zoom type is set to custom.', 'swipecomic' ) );
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
				$has_errors = true;
				$this->add_validation_error( __( 'Invalid pan position. Using default "center".', 'swipecomic' ) );
				$pan_type = 'center'; // Default fallback for invalid values.
			}

			update_post_meta( $post_id, '_swipecomic_default_pan_type', $pan_type );

			// Save custom pan values if type is custom.
			if ( 'custom' === $pan_type && isset( $_POST['swipecomic_pan_custom_x'] ) && isset( $_POST['swipecomic_pan_custom_y'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via trim and intval below.
				$custom_x_val = trim( wp_unslash( $_POST['swipecomic_pan_custom_x'] ) );
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via trim and intval below.
				$custom_y_val = trim( wp_unslash( $_POST['swipecomic_pan_custom_y'] ) );

				if ( '' === $custom_x_val || '' === $custom_y_val ) {
					$has_errors = true;
					$this->add_validation_error( __( 'Both X and Y coordinates are required for custom pan position.', 'swipecomic' ) );
					delete_post_meta( $post_id, '_swipecomic_default_pan_x' );
					delete_post_meta( $post_id, '_swipecomic_default_pan_y' );
				} elseif ( ! is_numeric( $custom_x_val ) || ! is_numeric( $custom_y_val ) ) {
					$has_errors = true;
					$this->add_validation_error( __( 'Custom pan coordinates must be numeric values.', 'swipecomic' ) );
					delete_post_meta( $post_id, '_swipecomic_default_pan_x' );
					delete_post_meta( $post_id, '_swipecomic_default_pan_y' );
				} else {
					$custom_x = intval( $custom_x_val );
					$custom_y = intval( $custom_y_val );
					update_post_meta( $post_id, '_swipecomic_default_pan_x', $custom_x );
					update_post_meta( $post_id, '_swipecomic_default_pan_y', $custom_y );
				}
			} else {
				delete_post_meta( $post_id, '_swipecomic_default_pan_x' );
				delete_post_meta( $post_id, '_swipecomic_default_pan_y' );
			}
		}
	}





	/**
	 * AJAX handler to delete an episode image immediately.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_image() {
		// Verify nonce.
		check_ajax_referer( 'swipecomic_admin_nonce', 'nonce' );

		// Get attachment ID.
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'swipecomic' ) ) );
		}

		// Verify it's an image attachment.
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Attachment is not a valid image.', 'swipecomic' ) ) );
		}

		// Check if user has permission to delete this specific attachment.
		if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swipecomic' ) ) );
		}

		// Move the attachment to trash instead of permanent deletion.
		$deleted = wp_delete_attachment( $attachment_id, false );

		if ( false === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete image.', 'swipecomic' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Image deleted successfully.', 'swipecomic' ) ) );
	}



	/**
	 * AJAX handler to save episode images immediately.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_images() {
		// Verify nonce.
		check_ajax_referer( 'swipecomic_admin_nonce', 'nonce' );

		// Get post ID.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'No post ID provided.', 'swipecomic' ) ) );
		}

		// Check if user has permission for this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swipecomic' ) ) );
		}

		if ( 'swipecomic' !== get_post_type( $post_id ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: post type */
						__( 'Invalid post type: %s', 'swipecomic' ),
						get_post_type( $post_id )
					),
				)
			);
		}

		// Get and sanitize images data.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data is sanitized after decoding.
		$images_json = isset( $_POST['images_data'] ) ? wp_unslash( $_POST['images_data'] ) : '';
		$images_data = json_decode( $images_json, true );

		if ( ! is_array( $images_data ) ) {
			$images_data = array();
		}

		// Sanitize and validate images using shared helper method.
		$sanitized_images = $this->sanitize_and_validate_images( $images_data );

		// Save to post meta.
		update_post_meta( $post_id, '_swipecomic_images', $sanitized_images );

		wp_send_json_success(
			array(
				'message'      => __( 'Images saved successfully.', 'swipecomic' ),
				'images_count' => count( $sanitized_images ),
				'post_id'      => $post_id,
			)
		);
	}



	/**
	 * Sanitize and validate images data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $images_data Raw images data array.
	 * @return array Sanitized and validated images array.
	 */
	private function sanitize_and_validate_images( $images_data ) {
		$sanitized_images = array();

		foreach ( $images_data as $index => $image ) {
			if ( ! isset( $image['id'] ) ) {
				continue;
			}

			$image_id = absint( $image['id'] );

			// Verify the attachment exists and is an image.
			if ( ! wp_attachment_is_image( $image_id ) ) {
				continue; // Skip invalid attachments.
			}

			$sanitized_image = array(
				'id'    => $image_id,
				'order' => absint( $index ),
			);

			// Sanitize and validate zoom override.
			if ( isset( $image['zoom_override'] ) && ! empty( $image['zoom_override'] ) ) {
				$zoom_override = sanitize_text_field( $image['zoom_override'] );
				if ( $this->is_valid_zoom_value( $zoom_override ) ) {
					$sanitized_image['zoom_override'] = $zoom_override;
				}
			}

			// Sanitize and validate pan override.
			if ( isset( $image['pan_override'] ) && ! empty( $image['pan_override'] ) ) {
				$pan_override = sanitize_text_field( $image['pan_override'] );
				if ( $this->is_valid_pan_value( $pan_override ) ) {
					$sanitized_image['pan_override'] = $pan_override;
				}
			}

			$sanitized_images[] = $sanitized_image;
		}

		return $sanitized_images;
	}

	/**
	 * Validate zoom value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Zoom value to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_zoom_value( $value ) {
		// Valid zoom values: 'fit', 'vFill', or a positive number.
		if ( in_array( $value, array( 'fit', 'vFill' ), true ) ) {
			return true;
		}

		// Check if it's a numeric value.
		if ( is_numeric( $value ) && floatval( $value ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate pan value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Pan value to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_pan_value( $value ) {
		// Valid pan values: 'left', 'right', 'center', or 'x,y' coordinates.
		if ( in_array( $value, array( 'left', 'right', 'center' ), true ) ) {
			return true;
		}

		// Check if it's custom coordinates in format 'x,y' using regex for stricter validation.
		if ( preg_match( '/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add a validation error to be displayed as an admin notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Error message.
	 */
	private function add_validation_error( $message ) {
		$user_id       = get_current_user_id();
		$transient_key = 'swipecomic_validation_errors_' . $user_id;

		$errors = get_transient( $transient_key );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}
		$errors[] = $message;
		set_transient( $transient_key, $errors, 45 );
	}

	/**
	 * Display validation errors as admin notices.
	 *
	 * @since 1.0.0
	 */
	public function display_validation_errors() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$transient_key = 'swipecomic_validation_errors_' . $user_id;

		$errors = get_transient( $transient_key );
		if ( ! is_array( $errors ) || empty( $errors ) ) {
			return;
		}

		// Only show on swipecomic edit screens.
		$screen = get_current_screen();
		if ( ! $screen || 'swipecomic' !== $screen->post_type ) {
			return;
		}

		foreach ( $errors as $error ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php esc_html_e( 'SwipeComic Validation Error:', 'swipecomic' ); ?></strong> <?php echo esc_html( $error ); ?></p>
			</div>
			<?php
		}

		// Clear the errors after displaying.
		delete_transient( $transient_key );
	}
}
