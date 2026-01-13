<?php
/**
 * PostType class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles swipecomic custom post type registration and configuration.
 *
 * @since 1.0.0
 */
class PostType {

	/**
	 * Post type slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const POST_TYPE = 'swipecomic';

	/**
	 * Initialize post type.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'add_sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_series_filter_dropdown' ) );
		add_filter( 'parse_query', array( $this, 'filter_by_series' ) );
	}

	/**
	 * Register swipecomic custom post type.
	 *
	 * @since 1.0.0
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'SwipeComics', 'Post type general name', 'swipecomic' ),
			'singular_name'         => _x( 'SwipeComic', 'Post type singular name', 'swipecomic' ),
			'menu_name'             => _x( 'SwipeComics', 'Admin Menu text', 'swipecomic' ),
			'name_admin_bar'        => _x( 'SwipeComic', 'Add New on Toolbar', 'swipecomic' ),
			'add_new'               => __( 'Add New', 'swipecomic' ),
			'add_new_item'          => __( 'Add New SwipeComic', 'swipecomic' ),
			'new_item'              => __( 'New SwipeComic', 'swipecomic' ),
			'edit_item'             => __( 'Edit SwipeComic', 'swipecomic' ),
			'view_item'             => __( 'View SwipeComic', 'swipecomic' ),
			'all_items'             => __( 'All SwipeComics', 'swipecomic' ),
			'search_items'          => __( 'Search SwipeComics', 'swipecomic' ),
			'parent_item_colon'     => __( 'Parent SwipeComics:', 'swipecomic' ),
			'not_found'             => __( 'No swipecomics found.', 'swipecomic' ),
			'not_found_in_trash'    => __( 'No swipecomics found in Trash.', 'swipecomic' ),
			'featured_image'        => _x( 'Cover Image', 'Overrides the "Featured Image" phrase', 'swipecomic' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'swipecomic' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'swipecomic' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'swipecomic' ),
			'archives'              => _x( 'SwipeComic archives', 'The post type archive label', 'swipecomic' ),
			'insert_into_item'      => _x( 'Insert into swipecomic', 'Overrides the "Insert into post" phrase', 'swipecomic' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this swipecomic', 'Overrides the "Uploaded to this post" phrase', 'swipecomic' ),
			'filter_items_list'     => _x( 'Filter swipecomics list', 'Screen reader text for the filter links', 'swipecomic' ),
			'items_list_navigation' => _x( 'SwipeComics list navigation', 'Screen reader text for the pagination', 'swipecomic' ),
			'items_list'            => _x( 'SwipeComics list', 'Screen reader text for the items list', 'swipecomic' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => false, // Custom rewrite rules handled separately.
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-images-alt2',
			'supports'           => array( 'title', 'thumbnail' ), // Support cover image for episodes.
			'show_in_rest'       => false, // Disable block editor, use classic editor.
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Add custom admin columns.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_custom_columns( $columns ) {
		// Keep title as second column for proper mobile display, add Series as 3rd, then custom columns.
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			// Skip series taxonomy for now, we'll add it after title.
			if ( 'taxonomy-swipecomic_series' === $key ) {
				continue;
			}

			$new_columns[ $key ] = $value;

			// Add Series and custom columns after title.
			if ( 'title' === $key ) {
				// Add Series as 3rd column if it exists.
				if ( isset( $columns['taxonomy-swipecomic_series'] ) ) {
					$new_columns['taxonomy-swipecomic_series'] = $columns['taxonomy-swipecomic_series'];
				}
				$new_columns['episode_number'] = __( 'Episode #', 'swipecomic' );
				$new_columns['image_count']    = __( 'Images', 'swipecomic' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom admin columns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'episode_number':
				$episode_number = get_post_meta( $post_id, '_swipecomic_episode_number', true );
				echo $episode_number ? esc_html( $episode_number ) : '—';
				break;

			case 'image_count':
				$images = get_post_meta( $post_id, '_swipecomic_images', true );
				$count  = is_array( $images ) ? count( $images ) : 0;
				echo esc_html( $count );
				break;
		}
	}

	/**
	 * Add sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function add_sortable_columns( $columns ) {
		$columns['episode_number'] = 'episode_number';
		return $columns;
	}

	/**
	 * Add series filter dropdown to admin list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type Current post type.
	 */
	public function add_series_filter_dropdown( $post_type ) {
		// Only add filter for swipecomic post type.
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		// Get all series terms.
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		// Get current filter value.
		$current_series = '';
		if ( isset( $_GET['swipecomic_series_filter'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for filter, no action taken.
			$filter_value = sanitize_text_field( wp_unslash( $_GET['swipecomic_series_filter'] ) );
			if ( 'none' === $filter_value ) {
				$current_series = 'none';
			} else {
				$current_series = absint( $filter_value );
			}
		}

		?>
		<select name="swipecomic_series_filter" id="swipecomic_series_filter">
			<option value=""><?php esc_html_e( 'All Series', 'swipecomic' ); ?></option>
			<option value="none" <?php selected( $current_series, 'none' ); ?>>
				<?php esc_html_e( 'No Series', 'swipecomic' ); ?>
			</option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $current_series, $term->term_id ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Filter episodes by selected series.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $query Current query object.
	 */
	public function filter_by_series( $query ) {
		global $pagenow;

		// Only filter on admin edit.php page for swipecomic post type.
		if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== $pagenow || ! isset( $query->query_vars['post_type'] ) || self::POST_TYPE !== $query->query_vars['post_type'] ) {
			return;
		}

		// Check if series filter is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for filter, no action taken.
		if ( ! isset( $_GET['swipecomic_series_filter'] ) || empty( $_GET['swipecomic_series_filter'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for filter, no action taken.
		$filter_value = sanitize_text_field( wp_unslash( $_GET['swipecomic_series_filter'] ) );

		// Filter for episodes with no series.
		if ( 'none' === $filter_value ) {
			$tax_query = array(
				array(
					'taxonomy' => Taxonomy::TAXONOMY,
					'operator' => 'NOT EXISTS',
				),
			);
			$query->set( 'tax_query', $tax_query );
		} else {
			// Filter by specific series.
			$term_id = absint( $filter_value );
			if ( $term_id > 0 ) {
				$tax_query = array(
					array(
						'taxonomy' => Taxonomy::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				);
				$query->set( 'tax_query', $tax_query );
			}
		}
	}
}
