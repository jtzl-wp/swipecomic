<?php
/**
 * AJAX Handlers class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.4
 */

namespace JTZL\SwipeComic;

/**
 * Handles AJAX requests for frontend viewer.
 *
 * @since 1.0.4
 */
class AjaxHandlers {

	/**
	 * Initialize AJAX handlers.
	 *
	 * @since 1.0.4
	 */
	public function init() {
		add_action( 'wp_ajax_swipecomic_get_adjacent_episode', array( $this, 'get_adjacent_episode' ) );
		add_action( 'wp_ajax_nopriv_swipecomic_get_adjacent_episode', array( $this, 'get_adjacent_episode' ) );
	}

	/**
	 * Get adjacent episode data via AJAX.
	 *
	 * Returns episode data for the next or previous episode in a series.
	 *
	 * @since 1.0.4
	 */
	public function get_adjacent_episode() {
		// Verify nonce.
		check_ajax_referer( 'swipecomic_viewer_nonce', 'nonce' );

		// Sanitize inputs.
		$episode_id = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;
		$direction  = isset( $_POST['direction'] ) ? sanitize_text_field( wp_unslash( $_POST['direction'] ) ) : '';

		// Validate direction.
		if ( ! in_array( $direction, array( 'next', 'prev' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid direction' ) );
			return;
		}

		// Check if episode exists and is published.
		$episode = get_post( $episode_id );
		if ( ! $episode || 'swipecomic' !== $episode->post_type || 'publish' !== $episode->post_status ) {
			wp_send_json_error( array( 'message' => 'Episode not found' ) );
			return;
		}

		// Find adjacent episode.
		$adjacent = $this->find_adjacent_episode( $episode_id, $direction );

		if ( $adjacent ) {
			// Get navigation for the adjacent episode.
			$adjacent_navigation = TemplateFunctions::get_episode_navigation( $adjacent->ID );

			// Setup post data to use WordPress content functions.
			global $post;
			$original_post = $post;
			$post          = $adjacent; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );

			$content = get_the_content();
			$content = apply_filters( 'swipecomic_ajax_content', $content, $adjacent->ID );

			wp_reset_postdata();
			$post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			wp_send_json_success(
				array(
					'id'              => $adjacent->ID,
					'title'           => $adjacent->post_title,
					'url'             => get_permalink( $adjacent->ID ),
					'episodeChapter'  => TemplateFunctions::format_episode_chapter( $adjacent->ID ),
					'content'         => $content,
					'images'          => TemplateFunctions::get_swipecomic_images( $adjacent->ID ),
					'episodeDefaults' => array(
						'zoom' => TemplateFunctions::get_episode_zoom( $adjacent->ID ),
						'pan'  => TemplateFunctions::get_episode_pan( $adjacent->ID ),
					),
					'navigation'      => array(
						'nextEpisodeId' => $adjacent_navigation['next'],
						'prevEpisodeId' => $adjacent_navigation['prev'],
					),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'No adjacent episode found' ) );
		}
	}

	/**
	 * Find adjacent episode in series.
	 *
	 * @since 1.0.4
	 *
	 * @param int    $episode_id Current episode ID.
	 * @param string $direction  Direction to search ('next' or 'prev').
	 * @return \WP_Post|null Adjacent episode post object or null if not found.
	 */
	public function find_adjacent_episode( $episode_id, $direction ) {
		return TemplateFunctions::find_adjacent_episode( $episode_id, $direction );
	}
}
