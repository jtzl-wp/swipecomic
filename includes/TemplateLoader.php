<?php
/**
 * Template Loader class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.4
 */

namespace JTZL\SwipeComic;

/**
 * Handles loading custom templates for SwipeComic post types and taxonomies.
 *
 * @since 1.0.4
 */
class TemplateLoader {

	/**
	 * Initialize template loader.
	 *
	 * @since 1.0.4
	 */
	public function init() {
		add_filter( 'template_include', array( $this, 'load_template' ) );
		add_filter( 'generate_sidebar_layout', array( $this, 'force_full_width_layout' ) );
		add_filter( 'body_class', array( $this, 'add_full_width_body_class' ) );
	}

	/**
	 * Load custom template for SwipeComic archives and singles.
	 *
	 * @since 1.0.4
	 *
	 * @param string $template The path of the template to include.
	 * @return string Template path.
	 */
	public function load_template( $template ) {
		// Check if this is a swipecomic post type archive.
		if ( is_post_type_archive( 'swipecomic' ) ) {
			$custom_template = $this->locate_template( 'archive-swipecomic.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		// Check if this is a swipecomic single post.
		if ( is_singular( 'swipecomic' ) ) {
			$custom_template = $this->locate_template( 'single-swipecomic.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		// Check if this is a swipecomic_series taxonomy archive.
		if ( is_tax( 'swipecomic_series' ) ) {
			$custom_template = $this->locate_template( 'taxonomy-swipecomic_series.php' );
			if ( $custom_template ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Locate template file.
	 *
	 * Looks in theme first, then plugin templates directory.
	 *
	 * @since 1.0.4
	 *
	 * @param string $template_name Template file name.
	 * @return string|false Template path or false if not found.
	 */
	private function locate_template( $template_name ) {
		// Check if template exists in theme.
		$theme_template = locate_template( array( 'swipecomic/' . $template_name, $template_name ) );
		if ( $theme_template ) {
			return $theme_template;
		}

		// Check if template exists in plugin.
		$plugin_template = plugin_dir_path( __DIR__ ) . 'templates/' . $template_name;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return false;
	}

	/**
	 * Force full-width layout for SwipeComic archives.
	 *
	 * @since 1.0.4
	 *
	 * @param string $layout Current layout.
	 * @return string Modified layout.
	 */
	public function force_full_width_layout( $layout ) {
		if ( is_post_type_archive( 'swipecomic' ) || is_tax( 'swipecomic_series' ) ) {
			return 'no-sidebar';
		}
		return $layout;
	}

	/**
	 * Add full-width body class for SwipeComic archives.
	 *
	 * @since 1.0.4
	 *
	 * @param array $classes Body classes.
	 * @return array Modified classes.
	 */
	public function add_full_width_body_class( $classes ) {
		if ( is_post_type_archive( 'swipecomic' ) || is_tax( 'swipecomic_series' ) ) {
			$classes[] = 'full-width-content';
		}
		return $classes;
	}
}
