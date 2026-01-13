<?php
/**
 * Taxonomy class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		add_action( 'init', array( $this, 'register_term_meta' ) );
		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'add_series_fields' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'edit_series_fields' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'save_series_fields' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'save_series_fields' ) );
		add_action( 'set_object_terms', array( $this, 'assign_episode_order' ), 10, 6 );
		add_action( self::TAXONOMY . '_edit_form', array( $this, 'render_episode_order_section' ), 10, 2 );
		add_action( 'wp_ajax_swipecomic_update_episode_order', array( $this, 'ajax_update_episode_order' ) );
		add_action( 'wp_ajax_swipecomic_save_series_cover', array( $this, 'ajax_save_series_cover' ) );
		add_action( 'wp_ajax_swipecomic_save_series_logo', array( $this, 'ajax_save_series_logo' ) );
		add_action( 'add_meta_boxes', array( $this, 'replace_series_meta_box' ) );
		add_action( 'save_post_swipecomic', array( $this, 'save_series_selection' ), 10, 2 );
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

	/**
	 * Register term meta fields for series.
	 *
	 * @since 1.0.0
	 */
	public function register_term_meta() {
		register_term_meta(
			self::TAXONOMY,
			'series_cover_image_id',
			array(
				'type'              => 'integer',
				'description'       => __( 'Series cover image attachment ID', 'swipecomic' ),
				'single'            => true,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => false,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'series_logo_id',
			array(
				'type'              => 'integer',
				'description'       => __( 'Series logo image attachment ID', 'swipecomic' ),
				'single'            => true,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => false,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'series_logo_position',
			array(
				'type'              => 'string',
				'description'       => __( 'Series logo position', 'swipecomic' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Add custom fields to series add form.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function add_series_fields( $taxonomy ) {
		?>
		<div class="form-field term-cover-image-wrap">
			<label for="series_cover_image_id"><?php esc_html_e( 'Cover Image', 'swipecomic' ); ?></label>
			<div class="swipecomic-series-cover-preview" id="swipecomic-series-cover-preview" style="display:none; margin-bottom: 10px;">
				<img src="" alt="" style="max-width: 200px; height: auto; display: block;" />
			</div>
			<button type="button" class="button button-secondary swipecomic-upload-series-cover" id="swipecomic-upload-series-cover">
				<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span><?php esc_html_e( 'Upload Cover Image', 'swipecomic' ); ?>
			</button>
			<button type="button" class="button swipecomic-remove-series-cover" id="swipecomic-remove-series-cover" style="display:none;">
				<?php esc_html_e( 'Remove Cover Image', 'swipecomic' ); ?>
			</button>
			<input type="hidden" name="series_cover_image_id" id="series_cover_image_id" value="" />
			<p class="description"><?php esc_html_e( 'Upload a cover image for this series.', 'swipecomic' ); ?></p>
		</div>

		<div class="form-field term-logo-wrap">
			<label for="series_logo_id"><?php esc_html_e( 'Series Logo', 'swipecomic' ); ?></label>
			<div class="swipecomic-series-logo-preview" id="swipecomic-series-logo-preview" style="display:none; margin-bottom: 10px;">
				<img src="" alt="" style="max-width: 200px; height: auto; display: block;" />
			</div>
			<button type="button" class="button button-secondary swipecomic-upload-series-logo" id="swipecomic-upload-series-logo">
				<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span><?php esc_html_e( 'Upload Logo', 'swipecomic' ); ?>
			</button>
			<button type="button" class="button swipecomic-remove-series-logo" id="swipecomic-remove-series-logo" style="display:none;">
				<?php esc_html_e( 'Remove Logo', 'swipecomic' ); ?>
			</button>
			<input type="hidden" name="series_logo_id" id="series_logo_id" value="" />
			<p class="description"><?php esc_html_e( 'Upload a logo image for this series.', 'swipecomic' ); ?></p>
		</div>

		<div class="form-field term-logo-position-wrap">
			<label for="series_logo_position"><?php esc_html_e( 'Logo Position', 'swipecomic' ); ?></label>
			<select name="series_logo_position" id="series_logo_position" class="postform">
				<option value="upper-left"><?php esc_html_e( 'Upper Left', 'swipecomic' ); ?></option>
				<option value="upper-right"><?php esc_html_e( 'Upper Right', 'swipecomic' ); ?></option>
				<option value="lower-left"><?php esc_html_e( 'Lower Left', 'swipecomic' ); ?></option>
				<option value="lower-right"><?php esc_html_e( 'Lower Right', 'swipecomic' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Choose where the logo should appear on episode pages.', 'swipecomic' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add custom fields to series edit form.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term Term object.
	 */
	public function edit_series_fields( $term ) {
		$cover_image_id  = get_term_meta( $term->term_id, 'series_cover_image_id', true );
		$cover_image_url = '';

		if ( $cover_image_id ) {
			$cover_image_url = wp_get_attachment_image_url( $cover_image_id, 'medium' );
		}

		$logo_id       = get_term_meta( $term->term_id, 'series_logo_id', true );
		$logo_url      = '';
		$logo_position = get_term_meta( $term->term_id, 'series_logo_position', true );

		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
		}

		if ( ! $logo_position ) {
			$logo_position = 'upper-left';
		}
		?>
		<input type="hidden" id="swipecomic_series_term_id" value="<?php echo esc_attr( $term->term_id ); ?>" />
		<tr class="form-field term-cover-image-wrap">
			<th scope="row">
				<label for="series_cover_image_id"><?php esc_html_e( 'Cover Image', 'swipecomic' ); ?></label>
			</th>
			<td>
				<div class="swipecomic-series-cover-preview" id="swipecomic-series-cover-preview" style="<?php echo $cover_image_url ? '' : 'display:none;'; ?> margin-bottom: 10px;">
					<?php if ( $cover_image_url ) : ?>
						<img src="<?php echo esc_url( $cover_image_url ); ?>" alt="" style="max-width: 200px; height: auto; display: block;" />
					<?php endif; ?>
				</div>
				<button type="button" class="button button-secondary swipecomic-upload-series-cover" id="swipecomic-upload-series-cover">
					<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span><?php echo $cover_image_id ? esc_html__( 'Change Cover Image', 'swipecomic' ) : esc_html__( 'Upload Cover Image', 'swipecomic' ); ?>
				</button>
				<button type="button" class="button swipecomic-remove-series-cover" id="swipecomic-remove-series-cover" style="<?php echo $cover_image_id ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Remove Cover Image', 'swipecomic' ); ?>
				</button>
				<input type="hidden" name="series_cover_image_id" id="series_cover_image_id" value="<?php echo esc_attr( $cover_image_id ); ?>" />
				<p class="description"><?php esc_html_e( 'Upload a cover image for this series.', 'swipecomic' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-logo-wrap">
			<th scope="row">
				<label for="series_logo_id"><?php esc_html_e( 'Series Logo', 'swipecomic' ); ?></label>
			</th>
			<td>
				<div class="swipecomic-series-logo-preview" id="swipecomic-series-logo-preview" style="<?php echo $logo_url ? '' : 'display:none;'; ?> margin-bottom: 10px;">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width: 200px; height: auto; display: block;" />
					<?php endif; ?>
				</div>
				<button type="button" class="button button-secondary swipecomic-upload-series-logo" id="swipecomic-upload-series-logo">
					<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span><?php echo $logo_id ? esc_html__( 'Change Logo', 'swipecomic' ) : esc_html__( 'Upload Logo', 'swipecomic' ); ?>
				</button>
				<button type="button" class="button swipecomic-remove-series-logo" id="swipecomic-remove-series-logo" style="<?php echo $logo_id ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Remove Logo', 'swipecomic' ); ?>
				</button>
				<input type="hidden" name="series_logo_id" id="series_logo_id" value="<?php echo esc_attr( $logo_id ); ?>" />
				<p class="description"><?php esc_html_e( 'Upload a logo image for this series.', 'swipecomic' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-logo-position-wrap">
			<th scope="row">
				<label for="series_logo_position"><?php esc_html_e( 'Logo Position', 'swipecomic' ); ?></label>
			</th>
			<td>
				<select name="series_logo_position" id="series_logo_position" class="postform">
					<option value="upper-left" <?php selected( $logo_position, 'upper-left' ); ?>><?php esc_html_e( 'Upper Left', 'swipecomic' ); ?></option>
					<option value="upper-right" <?php selected( $logo_position, 'upper-right' ); ?>><?php esc_html_e( 'Upper Right', 'swipecomic' ); ?></option>
					<option value="lower-left" <?php selected( $logo_position, 'lower-left' ); ?>><?php esc_html_e( 'Lower Left', 'swipecomic' ); ?></option>
					<option value="lower-right" <?php selected( $logo_position, 'lower-right' ); ?>><?php esc_html_e( 'Lower Right', 'swipecomic' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Choose where the logo should appear on episode pages.', 'swipecomic' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save custom series fields.
	 *
	 * @since 1.0.0
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_series_fields( $term_id ) {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// WordPress handles nonce verification for taxonomy term forms.
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		// Save cover image ID.
		if ( isset( $_POST['series_cover_image_id'] ) ) {
			$cover_image_id = absint( $_POST['series_cover_image_id'] );
			if ( $cover_image_id > 0 ) {
				// Verify the attachment exists and is an image.
				if ( wp_attachment_is_image( $cover_image_id ) ) {
					update_term_meta( $term_id, 'series_cover_image_id', $cover_image_id );
				}
			} else {
				delete_term_meta( $term_id, 'series_cover_image_id' );
			}
		}

		// Save logo image ID and position.
		if ( isset( $_POST['series_logo_id'] ) ) {
			$logo_id = absint( $_POST['series_logo_id'] );
			if ( $logo_id > 0 && wp_attachment_is_image( $logo_id ) ) {
				update_term_meta( $term_id, 'series_logo_id', $logo_id );

				// Save logo position only if a logo is set.
				if ( isset( $_POST['series_logo_position'] ) ) {
					$logo_position   = sanitize_text_field( wp_unslash( $_POST['series_logo_position'] ) );
					$valid_positions = array( 'upper-left', 'upper-right', 'lower-left', 'lower-right' );
					if ( in_array( $logo_position, $valid_positions, true ) ) {
						update_term_meta( $term_id, 'series_logo_position', $logo_position );
					}
				}
			} else {
				// If no valid logo, delete both logo ID and position meta.
				delete_term_meta( $term_id, 'series_logo_id' );
				delete_term_meta( $term_id, 'series_logo_position' );
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Assign sequential episode number when added to a series.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Array of term IDs.
	 * @param array  $tt_ids     Array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append terms.
	 * @param array  $old_tt_ids Old term taxonomy IDs.
	 */
	public function assign_episode_order( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		// Only process for swipecomic_series taxonomy and swipecomic post type.
		if ( self::TAXONOMY !== $taxonomy || 'swipecomic' !== get_post_type( $object_id ) ) {
			return;
		}

		// Check if episode already has a number.
		$episode_number = get_post_meta( $object_id, '_swipecomic_episode_number', true );

		// If no episode number set, assign the next sequential number.
		if ( empty( $episode_number ) && ! empty( $tt_ids ) ) {
			// Get term ID from first term taxonomy ID.
			$term = get_term_by( 'term_taxonomy_id', $tt_ids[0], self::TAXONOMY );
			if ( $term ) {
				$max_episode_number = $this->get_max_episode_number( $term->term_id );
				update_post_meta( $object_id, '_swipecomic_episode_number', $max_episode_number + 1 );
			}
		}
	}

	/**
	 * Get the maximum episode number for a series.
	 *
	 * @since 1.0.0
	 *
	 * @param int $term_id Series term ID.
	 * @return int Maximum episode number.
	 */
	private function get_max_episode_number( $term_id ) {
		// Get all posts in this series.
		$posts = get_posts(
			array(
				'post_type'      => 'swipecomic',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
				'fields'         => 'ids',
			)
		);

		$max_number = 0;

		// Find the maximum episode number for this series.
		foreach ( $posts as $post_id ) {
			$episode_number = get_post_meta( $post_id, '_swipecomic_episode_number', true );
			if ( $episode_number ) {
				$number = intval( $episode_number );
				if ( $number > $max_number ) {
					$max_number = $number;
				}
			}
		}

		return $max_number;
	}

	/**
	 * Render episode ordering section on series edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy slug.
	 */
	public function render_episode_order_section( $term, $taxonomy ) {
		// Get all episodes in this series, ordered by current order.
		$episodes = $this->get_series_episodes( $term->term_id );

		if ( empty( $episodes ) ) {
			return;
		}

		wp_nonce_field( 'swipecomic_episode_order', 'swipecomic_episode_order_nonce' );
		?>
		<div class="swipecomic-episode-order-section" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Episode Order', 'swipecomic' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Drag and drop episodes to reorder them within this series.', 'swipecomic' ); ?>
			</p>
			<ul id="swipecomic-episode-order-list" class="swipecomic-episode-order-list" data-term-id="<?php echo esc_attr( $term->term_id ); ?>">
				<?php foreach ( $episodes as $episode ) : ?>
					<li class="swipecomic-episode-order-item" data-post-id="<?php echo esc_attr( $episode->ID ); ?>">
						<span class="dashicons dashicons-menu"></span>
						<span class="episode-title"><?php echo esc_html( $episode->post_title ); ?></span>
						<span class="episode-number">
							<?php
							$episode_number = get_post_meta( $episode->ID, '_swipecomic_episode_number', true );
							if ( $episode_number ) {
								/* translators: %d: Episode number */
								echo esc_html( sprintf( __( 'Episode #%d', 'swipecomic' ), $episode_number ) );
							}
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div id="swipecomic-episode-order-message" style="display:none; margin-top: 10px;"></div>
		</div>
		<?php
	}

	/**
	 * Get episodes in a series, ordered by episode number.
	 *
	 * @since 1.0.0
	 *
	 * @param int $term_id Series term ID.
	 * @return array Array of post objects.
	 */
	private function get_series_episodes( $term_id ) {
		// Get all posts in this series, ordered by episode number.
		$posts = get_posts(
			array(
				'post_type'      => 'swipecomic',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
				'meta_key'       => '_swipecomic_episode_number',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);

		return $posts;
	}

	/**
	 * AJAX handler to update episode order.
	 *
	 * @since 1.0.0
	 */
	public function ajax_update_episode_order() {
		// Verify nonce.
		check_ajax_referer( 'swipecomic_episode_order', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'swipecomic' ) ) );
		}

		// Get data.
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$order   = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();

		if ( ! $term_id || empty( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data provided.', 'swipecomic' ) ) );
		}

		// Validate the term exists.
		$term = get_term( $term_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid series term.', 'swipecomic' ) ) );
		}

		// Validate posts belong to the series and are correct post type.
		foreach ( $order as $post_id ) {
			if ( 'swipecomic' !== get_post_type( $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid episode in order.', 'swipecomic' ) ) );
			}
			$terms = wp_get_post_terms( $post_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || ! in_array( $term_id, $terms, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Episode does not belong to this series.', 'swipecomic' ) ) );
			}
		}

		// Update episode number for each episode based on new order.
		foreach ( $order as $index => $post_id ) {
			// Episode numbers start at 1, not 0.
			update_post_meta( $post_id, '_swipecomic_episode_number', $index + 1 );
		}

		wp_send_json_success( array( 'message' => __( 'Episode order updated successfully.', 'swipecomic' ) ) );
	}

	/**
	 * AJAX handler to save series cover image.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_series_cover() {
		// Verify nonce.
		check_ajax_referer( 'swipecomic_admin_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'swipecomic' ) ) );
		}

		// Get data.
		$term_id        = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$cover_image_id = isset( $_POST['cover_image_id'] ) ? absint( $_POST['cover_image_id'] ) : 0;

		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid series term.', 'swipecomic' ) ) );
		}

		// Validate the term exists.
		$term = get_term( $term_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid series term.', 'swipecomic' ) ) );
		}

		// Save or delete cover image.
		if ( $cover_image_id > 0 ) {
			// Verify the attachment exists and is an image.
			if ( wp_attachment_is_image( $cover_image_id ) ) {
				update_term_meta( $term_id, 'series_cover_image_id', $cover_image_id );
				wp_send_json_success( array( 'message' => __( 'Cover image saved successfully.', 'swipecomic' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Invalid image attachment.', 'swipecomic' ) ) );
			}
		} else {
			delete_term_meta( $term_id, 'series_cover_image_id' );
			wp_send_json_success( array( 'message' => __( 'Cover image removed successfully.', 'swipecomic' ) ) );
		}
	}

	/**
	 * AJAX handler to save series logo image.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_series_logo() {
		// Verify nonce.
		check_ajax_referer( 'swipecomic_admin_nonce', 'nonce' );

		// Get data early to validate capability against specific term.
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

		// Check capabilities against the specific term and general capability.
		if ( ! $term_id || ! current_user_can( 'manage_categories' ) || ! current_user_can( 'edit_term', $term_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'swipecomic' ) ) );
		}

		$logo_id = isset( $_POST['logo_id'] ) ? absint( $_POST['logo_id'] ) : 0;

		// Validate the term exists.
		$term = get_term( $term_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid series term.', 'swipecomic' ) ) );
		}

		// Save or delete logo image.
		if ( $logo_id > 0 ) {
			// Verify the attachment exists and is an image.
			if ( wp_attachment_is_image( $logo_id ) ) {
				update_term_meta( $term_id, 'series_logo_id', $logo_id );
				wp_send_json_success( array( 'message' => __( 'Logo saved successfully.', 'swipecomic' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Invalid image attachment.', 'swipecomic' ) ) );
			}
		} else {
			// Delete both logo ID and position when logo is removed.
			delete_term_meta( $term_id, 'series_logo_id' );
			delete_term_meta( $term_id, 'series_logo_position' );
			wp_send_json_success( array( 'message' => __( 'Logo removed successfully.', 'swipecomic' ) ) );
		}
	}

	/**
	 * Replace default series meta box with radio button version.
	 *
	 * @since 1.0.0
	 */
	public function replace_series_meta_box() {
		// Remove default meta box from all contexts to prevent duplicates.
		remove_meta_box( 'swipecomic_seriesdiv', 'swipecomic', 'side' );
		remove_meta_box( 'swipecomic_seriesdiv', 'swipecomic', 'normal' );
		remove_meta_box( 'swipecomic_seriesdiv', 'swipecomic', 'advanced' );

		// Add custom radio button meta box.
		add_meta_box(
			'swipecomic_series_radio',
			__( 'Series', 'swipecomic' ),
			array( $this, 'render_series_radio_meta_box' ),
			'swipecomic',
			'side',
			'default',
			array( 'taxonomy' => self::TAXONOMY )
		);
	}

	/**
	 * Render series selection meta box with radio buttons.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_series_radio_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'swipecomic_save_series', 'swipecomic_series_nonce' );

		// Get current series.
		$current_series = wp_get_post_terms( $post->ID, self::TAXONOMY );
		$current_id     = ! empty( $current_series ) && ! is_wp_error( $current_series ) ? $current_series[0]->term_id : 0;

		// Get all series terms.
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'No series available. Please create a series first.', 'swipecomic' ) . '</p>';
			return;
		}
		?>
		<div id="taxonomy-<?php echo esc_attr( self::TAXONOMY ); ?>" class="categorydiv">
			<div id="<?php echo esc_attr( self::TAXONOMY ); ?>-all" class="tabs-panel">
				<ul id="<?php echo esc_attr( self::TAXONOMY ); ?>checklist" class="categorychecklist form-no-clear">
					<li>
						<label class="selectit">
							<input type="radio" name="swipecomic_series_selection" value="0" <?php checked( $current_id, 0 ); ?> />
							<?php esc_html_e( 'None', 'swipecomic' ); ?>
						</label>
					</li>
					<?php foreach ( $terms as $term ) : ?>
						<li>
							<label class="selectit">
								<input type="radio" name="swipecomic_series_selection" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( $current_id, $term->term_id ); ?> />
								<?php echo esc_html( $term->name ); ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Save series selection from radio button.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_series_selection( $post_id, $post ) {
		// Verify nonce.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verification doesn't require sanitization.
		if ( ! isset( $_POST['swipecomic_series_nonce'] ) || ! wp_verify_nonce( $_POST['swipecomic_series_nonce'], 'swipecomic_save_series' ) ) {
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

		// Get the selected series from the custom input field.
		$selected_series = 0;
		if ( isset( $_POST['swipecomic_series_selection'] ) ) {
			$selected_series = absint( $_POST['swipecomic_series_selection'] );
		}

		// Set the series term (convert single ID to array for wp_set_object_terms).
		if ( $selected_series > 0 ) {
			// Validate that the term exists and belongs to the correct taxonomy.
			$term_obj = get_term( $selected_series, self::TAXONOMY );
			if ( $term_obj && ! is_wp_error( $term_obj ) ) {
				wp_set_object_terms( $post_id, array( $selected_series ), self::TAXONOMY, false );
			}
		} else {
			// Remove all series if "None" is selected.
			wp_set_object_terms( $post_id, array(), self::TAXONOMY, false );
		}
	}
}
