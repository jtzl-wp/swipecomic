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
		// Hook template loading.
		add_filter( 'single_template', array( $this, 'load_single_template' ) );
	}

	/**
	 * Load custom single template for swipecomic posts.
	 *
	 * Checks for theme override first, then falls back to plugin template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Path to the template file.
	 * @return string Modified template path.
	 */
	public function load_single_template( $template ) {
		if ( is_singular( 'swipecomic' ) ) {
			// Check for an override in the theme/child-theme directory.
			$theme_template = locate_template( 'single-swipecomic.php' );
			if ( ! empty( $theme_template ) ) {
				return $theme_template;
			}

			// Fallback to the plugin's template.
			$plugin_template = JTZL_SWIPECOMIC_DIR . 'templates/single-swipecomic.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/**
	 * Get episode navigation data.
	 *
	 * Returns previous and next episode IDs based on episode order.
	 *
	 * @since 2.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return array Array with 'prev' and 'next' episode IDs (or null if not found).
	 */
	public static function get_episode_navigation( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$prev_post = self::find_adjacent_episode( $post_id, 'prev' );
		$next_post = self::find_adjacent_episode( $post_id, 'next' );

		return array(
			'prev' => $prev_post ? $prev_post->ID : null,
			'next' => $next_post ? $next_post->ID : null,
		);
	}

	/**
	 * Find adjacent episode in series.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $episode_id Current episode ID.
	 * @param string $direction  Direction to search ('next' or 'prev').
	 * @return \WP_Post|null Adjacent episode post object or null if not found.
	 */
	public static function find_adjacent_episode( $episode_id, $direction ) {
		// Get current episode's series.
		$series_terms = wp_get_post_terms( $episode_id, 'swipecomic_series' );
		if ( empty( $series_terms ) || is_wp_error( $series_terms ) ) {
			return null;
		}

		$series_id = $series_terms[0]->term_id;

		// Get current episode order.
		$current_order = get_post_meta( $episode_id, '_swipecomic_episode_order', true );

		// Query for adjacent episode.
		$args = array(
			'post_type'      => 'swipecomic',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'swipecomic_series',
					'field'    => 'term_id',
					'terms'    => $series_id,
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_swipecomic_episode_order',
					'value'   => $current_order,
					'compare' => 'next' === $direction ? '>' : '<',
					'type'    => 'NUMERIC',
				),
			),
			'meta_key'       => '_swipecomic_episode_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'next' === $direction ? 'ASC' : 'DESC',
		);

		$query = new \WP_Query( $args );

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Get series data.
	 *
	 * Returns series metadata including title, description, and cover image.
	 *
	 * @since 2.0.0
	 *
	 * @param int|null $term_id Series term ID. Defaults to current post's first series.
	 * @return array|false Series data array or false if not found.
	 */
	public static function get_series_data( $term_id = null ) {
		if ( null === $term_id ) {
			$terms = get_the_terms( get_the_ID(), 'swipecomic_series' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				return false;
			}
			$term_id = $terms[0]->term_id;
		}

		$term = get_term( $term_id, 'swipecomic_series' );
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		$cover_image_id  = get_term_meta( $term_id, 'series_cover_image_id', true );
		$cover_image_url = $cover_image_id ? wp_get_attachment_image_url( $cover_image_id, 'large' ) : false;

		return array(
			'term_id'     => $term_id,
			'name'        => $term->name,
			'description' => $term->description,
			'cover_image' => array(
				'id'  => $cover_image_id,
				'url' => $cover_image_url,
			),
			'logo'        => array(
				'id'       => get_term_meta( $term_id, 'series_logo_id', true ),
				'url'      => self::get_series_logo( $term_id ),
				'position' => self::get_series_logo_position( $term_id ),
			),
		);
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

			// Get image dimensions.
			$image_meta = wp_get_attachment_metadata( $attachment_id );
			$width      = 0;
			$height     = 0;
			if ( is_array( $image_meta ) ) {
				$width  = isset( $image_meta['width'] ) ? absint( $image_meta['width'] ) : 0;
				$height = isset( $image_meta['height'] ) ? absint( $image_meta['height'] ) : 0;
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
				'width'         => $width,
				'height'        => $height,
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

	/**
	 * Get episode number.
	 *
	 * @since 2.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return string|false Episode number or false if not set.
	 */
	public static function get_episode_number( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$episode_number = get_post_meta( $post_id, '_swipecomic_episode_number', true );
		return ! empty( $episode_number ) ? $episode_number : false;
	}

	/**
	 * Get chapter number.
	 *
	 * @since 2.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return string|false Chapter number or false if not set.
	 */
	public static function get_chapter_number( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$chapter_number = get_post_meta( $post_id, '_swipecomic_chapter_number', true );
		return ! empty( $chapter_number ) ? $chapter_number : false;
	}

	/**
	 * Format episode and chapter numbers for display.
	 *
	 * @since 2.0.0
	 *
	 * @param int|null $post_id Post ID. Defaults to current post.
	 * @return string Formatted episode/chapter string or empty string if neither is set.
	 */
	public static function format_episode_chapter( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}

		$episode_number = self::get_episode_number( $post_id );
		$chapter_number = self::get_chapter_number( $post_id );

		if ( $chapter_number && $episode_number ) {
			return sprintf( 'Chapter %s, Episode %s', $chapter_number, $episode_number );
		} elseif ( $episode_number ) {
			return sprintf( 'Episode %s', $episode_number );
		} elseif ( $chapter_number ) {
			return sprintf( 'Chapter %s', $chapter_number );
		}

		return '';
	}
}
