<?php
/**
 * Template functions for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

/**
 * Provides template helper functions for frontend display.
 *
 * @since 1.0.0
 */
class TemplateFunctions {

	/**
	 * Initialize template functions.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Template functions are loaded but not hooked to actions.
		// They are called directly from templates.
	}

	/**
	 * Get episode images with full-size URLs.
	 *
	 * Returns an array of image data including full-size URLs to preserve
	 * original images for frontend display.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return array Array of image data with full-size URLs.
	 */
	public static function get_swipecomic_images( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$images_meta = get_post_meta( $post_id, '_swipecomic_images', true );
		if ( ! is_array( $images_meta ) || empty( $images_meta ) ) {
			return array();
		}

		$images = array();
		foreach ( $images_meta as $image_data ) {
			if ( ! isset( $image_data['id'] ) ) {
				continue;
			}

			$attachment_id = absint( $image_data['id'] );

			// Get full-size image URL (original, unmodified).
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				continue;
			}

			$images[] = array(
				'id'            => $attachment_id,
				'url'           => $image_url, // Full-size original image.
				'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'zoom_override' => $image_data['zoom_override'] ?? null,
				'pan_override'  => $image_data['pan_override'] ?? null,
				'order'         => $image_data['order'] ?? 0,
			);
		}

		return $images;
	}

	/**
	 * Get swipecomic thumbnail URL.
	 *
	 * Returns the custom swipecomic-thumbnail size for archive pages.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return string|false Thumbnail URL or false if not found.
	 */
	public static function get_swipecomic_thumbnail( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$images = get_post_meta( $post_id, '_swipecomic_images', true );
		if ( ! is_array( $images ) || empty( $images ) ) {
			return false;
		}

		// Get first image as thumbnail.
		$first_image   = reset( $images );
		$attachment_id = isset( $first_image['id'] ) ? absint( $first_image['id'] ) : 0;

		if ( ! $attachment_id ) {
			return false;
		}

		// Get swipecomic-thumbnail size.
		$thumbnail = wp_get_attachment_image_src( $attachment_id, 'swipecomic-thumbnail' );
		return $thumbnail ? $thumbnail[0] : false;
	}
}
