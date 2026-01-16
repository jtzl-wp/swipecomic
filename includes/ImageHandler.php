<?php
/**
 * ImageHandler class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom image size registration and generation.
 *
 * @since 1.0.0
 */
class ImageHandler {

	/**
	 * Initialize image handler.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );

		$optimization = Settings::get_media_optimization();

		if ( 'disable_all' === $optimization ) {
			// Disable all default sizes globally.
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'disable_all_default_sizes' ), 10, 1 );
		} elseif ( 'cleanup_swipecomic' === $optimization ) {
			// Clean up sizes after generation for swipecomic images only.
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'cleanup_generated_sizes' ), 10, 2 );
		}
		// If 'keep_all', do nothing - WordPress generates all sizes normally.

		add_filter( 'big_image_size_threshold', array( $this, 'disable_scaled_image' ), 10, 4 );

		// Validate image MIME types on upload.
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate_image_mime_type' ) );
	}

	/**
	 * Register custom image sizes.
	 *
	 * @since 1.0.0
	 */
	public function register_image_sizes() {
		$thumbnail_size = Settings::get_thumbnail_size();

		// Register swipecomic-thumbnail size with aspect ratio preservation.
		add_image_size( 'swipecomic-thumbnail', $thumbnail_size, $thumbnail_size, false );
	}

	/**
	 * Disable all default WordPress image sizes globally.
	 *
	 * This is used when the "Disable all" optimization mode is selected.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sizes An associative array of image sizes.
	 * @return array Filtered image sizes with only swipecomic-thumbnail.
	 */
	public function disable_all_default_sizes( $sizes ) {
		// Remove all default WordPress image sizes.
		unset( $sizes['thumbnail'] );      // 150x150
		unset( $sizes['medium'] );         // 300x300
		unset( $sizes['medium_large'] );   // 768px
		unset( $sizes['large'] );          // 1024x1024
		unset( $sizes['1536x1536'] );      // 2x medium-large
		unset( $sizes['2048x2048'] );      // 2x large

		// Keep only swipecomic-thumbnail.
		return $sizes;
	}

	/**
	 * Clean up generated image sizes after WordPress creates them.
	 *
	 * This filter runs AFTER image generation, when we can finally determine
	 * if the attachment belongs to a swipecomic post. We delete unwanted sizes
	 * and clean up the metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Filtered metadata.
	 */
	public function cleanup_generated_sizes( $metadata, $attachment_id ) {
		if ( ! $this->is_swipecomic_context( $attachment_id ) ) {
			return $metadata;
		}

		// If sizes were generated, keep only swipecomic-thumbnail.
		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$swipecomic_thumbnail = isset( $metadata['sizes']['swipecomic-thumbnail'] ) ? $metadata['sizes']['swipecomic-thumbnail'] : null;

			// Get the upload directory for this attachment.
			$upload_dir = wp_upload_dir();
			$file_path  = isset( $metadata['file'] ) ? $metadata['file'] : '';

			if ( $file_path ) {
				$dir_path = dirname( $upload_dir['basedir'] . '/' . $file_path );

				// Delete physical files for all sizes except swipecomic-thumbnail.
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					if ( 'swipecomic-thumbnail' !== $size_name && isset( $size_data['file'] ) ) {
						$file_to_delete = $dir_path . '/' . $size_data['file'];
						if ( file_exists( $file_to_delete ) ) {
							wp_delete_file( $file_to_delete );
						}
					}
				}
			}

			// Clear all sizes from metadata.
			$metadata['sizes'] = array();

			// Add back only swipecomic-thumbnail if it exists.
			if ( $swipecomic_thumbnail ) {
				$metadata['sizes']['swipecomic-thumbnail'] = $swipecomic_thumbnail;
			}
		}

		return $metadata;
	}

	/**
	 * Disable scaled image size.
	 *
	 * WordPress 5.3+ creates a "scaled" version of large images (2560px).
	 * This filter disables that based on the optimization setting.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $threshold      The threshold value in pixels. Default 2560.
	 * @param array  $imagesize      Indexed array of the image width and height in pixels.
	 * @param string $file           Full path to the uploaded image file.
	 * @param int    $attachment_id  Attachment ID.
	 * @return int|bool Threshold value or false to disable scaling.
	 */
	public function disable_scaled_image( $threshold, $imagesize, $file, $attachment_id ) {
		$optimization = Settings::get_media_optimization();

		// If disabling all sizes globally, disable scaled image too.
		if ( 'disable_all' === $optimization ) {
			return false;
		}

		// If cleaning up swipecomic only, check if this is a swipecomic attachment.
		if ( 'cleanup_swipecomic' === $optimization && $this->is_swipecomic_context( $attachment_id ) ) {
			return false;
		}

		return $threshold;
	}

	/**
	 * Validate image MIME type on upload.
	 *
	 * Ensures only valid image types are uploaded for SwipeComic images.
	 *
	 * @since 1.0.0
	 *
	 * @param array $file File upload data.
	 * @return array Modified file data or error.
	 */
	public function validate_image_mime_type( $file ) {
		// Only validate if this is a SwipeComic context with a valid nonce.
		if ( ! isset( $_REQUEST['swipecomic_context_post_id'], $_REQUEST['swipecomic_upload_nonce'] ) ) {
			return $file;
		}

		// Verify nonce - must use wp_unslash() and sanitize_text_field() since wp_verify_nonce() is pluggable.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['swipecomic_upload_nonce'] ) ), 'swipecomic_admin_nonce' ) ) {
			return $file;
		}

		// Verify the context post ID is valid - sanitize before using absint().
		$post_id = absint( sanitize_text_field( wp_unslash( $_REQUEST['swipecomic_context_post_id'] ) ) );
		if ( ! $post_id || 'swipecomic' !== get_post_type( $post_id ) ) {
			return $file;
		}

		// Define allowed MIME types for SwipeComic images.
		$allowed_mime_types = array(
			'image/jpeg',
			'image/jpg',   // Non-standard but sometimes reported by older systems.
			'image/pjpeg', // Progressive JPEG (older IE).
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
			'image/avif-sequence', // Animated AVIF sequences.
		);

		// Check if the uploaded file's MIME type is allowed.
		if ( ! in_array( $file['type'], $allowed_mime_types, true ) ) {
			$file['error'] = sprintf(
				/* translators: %s: comma-separated list of allowed file types */
				__( 'Invalid file type. Only images are allowed: %s', 'swipecomic' ),
				implode( ', ', array( 'JPEG', 'PNG', 'GIF', 'WebP', 'AVIF' ) )
			);
		}

		return $file;
	}

	/**
	 * Check if an attachment is in a SwipeComic context.
	 *
	 * This method uses multiple detection strategies:
	 * 1. JavaScript-passed context (most reliable)
	 * 2. Attachment parent post type
	 * 3. HTTP_REFERER fallback (least reliable)
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if attachment is in SwipeComic context.
	 */
	private function is_swipecomic_context( $attachment_id ) {
		// Method 1: Check JavaScript-passed context (most reliable).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Context detection only.
		if ( isset( $_REQUEST['swipecomic_context_post_id'] ) ) {
			$post_id = absint( $_REQUEST['swipecomic_context_post_id'] );
			if ( 'swipecomic' === get_post_type( $post_id ) ) {
				return true;
			}
		}

		// Method 2: Check the attachment's post parent.
		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( $parent_id && 'swipecomic' === get_post_type( $parent_id ) ) {
			return true;
		}

		// Method 3: Fallback to HTTP_REFERER (least reliable, but better than nothing).
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only checking for post type in URL.
			$referer = wp_unslash( $_SERVER['HTTP_REFERER'] );

			// Check if referer contains post.php or post-new.php with swipecomic post type.
			if ( preg_match( '/(post|post-new)\.php/', $referer ) ) {
				$parsed_url = wp_parse_url( $referer );
				if ( isset( $parsed_url['query'] ) ) {
					parse_str( $parsed_url['query'], $query_params );

					// Check if it's a swipecomic post.
					if ( isset( $query_params['post'] ) && 'swipecomic' === get_post_type( absint( $query_params['post'] ) ) ) {
						return true;
					} elseif ( isset( $query_params['post_type'] ) && 'swipecomic' === $query_params['post_type'] ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
