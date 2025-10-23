<?php
/**
 * Taxonomy class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

/**
 * Handles swipecomic_series taxonomy registration and configuration.
 *
 * @since 1.0.0
 */
class Taxonomy {

	/**
	 * Taxonomy slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const TAXONOMY = 'swipecomic_series';

	/**
	 * Initialize taxonomy.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register swipecomic_series taxonomy.
	 *
	 * @since 1.0.0
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Series', 'taxonomy general name', 'swipecomic' ),
			'singular_name'              => _x( 'Series', 'taxonomy singular name', 'swipecomic' ),
			'search_items'               => __( 'Search Series', 'swipecomic' ),
			'popular_items'              => __( 'Popular Series', 'swipecomic' ),
			'all_items'                  => __( 'All Series', 'swipecomic' ),
			'parent_item'                => __( 'Parent Series', 'swipecomic' ),
			'parent_item_colon'          => __( 'Parent Series:', 'swipecomic' ),
			'edit_item'                  => __( 'Edit Series', 'swipecomic' ),
			'update_item'                => __( 'Update Series', 'swipecomic' ),
			'add_new_item'               => __( 'Add New Series', 'swipecomic' ),
			'new_item_name'              => __( 'New Series Name', 'swipecomic' ),
			'separate_items_with_commas' => __( 'Separate series with commas', 'swipecomic' ),
			'add_or_remove_items'        => __( 'Add or remove series', 'swipecomic' ),
			'choose_from_most_used'      => __( 'Choose from the most used series', 'swipecomic' ),
			'not_found'                  => __( 'No series found.', 'swipecomic' ),
			'menu_name'                  => __( 'Series', 'swipecomic' ),
			'back_to_items'              => __( '← Back to Series', 'swipecomic' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true, // Hierarchical taxonomy for series organization.
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true, // Enable admin column display.
			'show_in_nav_menus' => true,
			'show_tagcloud'     => false,
			'query_var'         => true,
			'rewrite'           => false, // Custom rewrite rules handled separately.
			'show_in_rest'      => false, // Classic editor interface.
		);

		register_taxonomy( self::TAXONOMY, array( 'swipecomic' ), $args );
	}
}
