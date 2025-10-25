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
	 * original images for frontend display. Handles missing data gracefully.
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

		// Get episode-level defaults.
		$default_zoom = self::get_episode_zoom( $post_id );
		$default_pan  = self::get_episode_pan( $post_id );

		$images = array();
		foreach ( $images_meta as $image_data ) {
			if ( ! isset( $image_data['id'] ) ) {
				continue;
			}

			$attachment_id = absint( $image_data['id'] );

			// Get full-size image URL (original, unmodified).
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				continue; // Skip images that no longer exist.
			}

			// Validate and sanitize zoom override.
			$zoom_override = isset( $image_data['zoom_override'] ) ? $image_data['zoom_override'] : null;
			if ( null !== $zoom_override && ! self::is_valid_zoom_value( $zoom_override ) ) {
				$zoom_override = null; // Invalid override, use default.
			}

			// Validate and sanitize pan override.
			$pan_override = isset( $image_data['pan_override'] ) ? $image_data['pan_override'] : null;
			if ( null !== $pan_override && ! self::is_valid_pan_value( $pan_override ) ) {
				$pan_override = null; // Invalid override, use default.
			}

			$images[] = array(
				'id'            => $attachment_id,
				'url'           => $image_url, // Full-size original image.
				'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'zoom'          => null !== $zoom_override ? $zoom_override : $default_zoom,
				'pan'           => null !== $pan_override ? $pan_override : $default_pan,
				'zoom_override' => $zoom_override,
				'pan_override'  => $pan_override,
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

	/**
	 * Get episode zoom setting with fallback to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return string Zoom value ('fit', 'vFill', or numeric value).
	 */
	public static function get_episode_zoom( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$zoom_type  = get_post_meta( $post_id, '_swipecomic_default_zoom_type', true );
		$zoom_value = get_post_meta( $post_id, '_swipecomic_default_zoom_value', true );

		// Validate zoom type.
		if ( ! in_array( $zoom_type, array( 'fit', 'vFill', 'custom' ), true ) ) {
			$zoom_type = Settings::get_default_zoom();
		}

		// Return custom value if type is custom.
		if ( 'custom' === $zoom_type ) {
			$zoom_value = absint( $zoom_value );
			if ( $zoom_value > 0 ) {
				return (string) $zoom_value;
			}
			// Invalid custom value, fall back to fit.
			return 'fit';
		}

		return $zoom_type;
	}

	/**
	 * Get episode pan setting with fallback to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return string Pan value ('left', 'right', 'center', or 'x,y' coordinates).
	 */
	public static function get_episode_pan( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$pan_type = get_post_meta( $post_id, '_swipecomic_default_pan_type', true );
		$pan_x    = get_post_meta( $post_id, '_swipecomic_default_pan_x', true );
		$pan_y    = get_post_meta( $post_id, '_swipecomic_default_pan_y', true );

		// Validate pan type.
		if ( ! in_array( $pan_type, array( 'left', 'right', 'center', 'custom' ), true ) ) {
			$pan_type = Settings::get_default_pan();
		}

		// Return custom coordinates if type is custom.
		if ( 'custom' === $pan_type ) {
			if ( '' !== $pan_x && '' !== $pan_y && is_numeric( $pan_x ) && is_numeric( $pan_y ) ) {
				return intval( $pan_x ) . ',' . intval( $pan_y );
			}
			// Invalid custom coordinates, fall back to center.
			return 'center';
		}

		return $pan_type;
	}

	/**
	 * Validate zoom value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Zoom value to validate.
	 * @return bool True if valid.
	 */
	private static function is_valid_zoom_value( $value ) {
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
	private static function is_valid_pan_value( $value ) {
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
	 * Check if series has a logo.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $term_id Series term ID. Defaults to current post's first series.
	 * @return bool True if series has a logo.
	 */
	public static function has_series_logo( $term_id = null ) {
		if ( null === $term_id ) {
			$terms = get_the_terms( get_the_ID(), 'swipecomic_series' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				return false;
			}
			$term_id = $terms[0]->term_id;
		}

		$logo_id = get_term_meta( $term_id, 'series_logo_id', true );
		return ! empty( $logo_id ) && wp_attachment_is_image( $logo_id );
	}

	/**
	 * Get series logo URL.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null    $term_id Series term ID. Defaults to current post's first series.
	 * @param string|null $size    Image size. Defaults to 'medium'.
	 * @return string|false Logo URL or false if not found.
	 */
	public static function get_series_logo( $term_id = null, $size = 'medium' ) {
		if ( null === $term_id ) {
			$terms = get_the_terms( get_the_ID(), 'swipecomic_series' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				return false;
			}
			$term_id = $terms[0]->term_id;
		}

		$logo_id = get_term_meta( $term_id, 'series_logo_id', true );
		if ( ! $logo_id || ! wp_attachment_is_image( $logo_id ) ) {
			return false;
		}

		return wp_get_attachment_image_url( $logo_id, $size );
	}

	/**
	 * Display series logo.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null    $term_id Series term ID. Defaults to current post's first series.
	 * @param string|null $size    Image size. Defaults to 'medium'.
	 */
	public static function the_series_logo( $term_id = null, $size = 'medium' ) {
		$logo_url = self::get_series_logo( $term_id, $size );
		if ( $logo_url ) {
			echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Series Logo', 'swipecomic' ) . '" class="swipecomic-series-logo" />';
		}
	}

	/**
	 * Get series logo position.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $term_id Series term ID. Defaults to current post's first series.
	 * @return string Logo position ('upper-left', 'upper-right', 'lower-left', 'lower-right').
	 */
	public static function get_series_logo_position( $term_id = null ) {
		if ( null === $term_id ) {
			$terms = get_the_terms( get_the_ID(), 'swipecomic_series' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				return 'upper-left';
			}
			$term_id = $terms[0]->term_id;
		}

		$position        = get_term_meta( $term_id, 'series_logo_position', true );
		$valid_positions = array( 'upper-left', 'upper-right', 'lower-left', 'lower-right' );

		if ( ! in_array( $position, $valid_positions, true ) ) {
			return 'upper-left';
		}

		return $position;
	}
}
