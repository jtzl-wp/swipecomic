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
}
