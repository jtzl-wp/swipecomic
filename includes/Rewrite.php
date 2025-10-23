<?php
/**
 * Rewrite class for SwipeComic plugin.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

namespace JTZL\SwipeComic;

/**
 * Handles custom rewrite rules for clean URLs.
 *
 * @since 1.0.0
 */
class Rewrite {

	/**
	 * Initialize rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_conflicts' ) );
		add_filter( 'post_type_link', array( $this, 'swipecomic_permalink' ), 10, 2 );
		add_filter( 'term_link', array( $this, 'series_permalink' ), 10, 3 );
	}

	/**
	 * Add custom rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public function add_rewrite_rules() {
		$use_prefix = Settings::use_url_prefix();
		$prefix     = Settings::get_url_prefix();

		if ( $use_prefix ) {
			// With prefix mode: /comic/series/episode/.
			$this->add_prefixed_rules( $prefix );
		} else {
			// Clean mode: /series/episode/.
			$this->add_clean_rules( $prefix );
		}
	}

	/**
	 * Add rewrite rules with URL prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix URL prefix slug.
	 */
	private function add_prefixed_rules( $prefix ) {
		// Comics archive pagination - Pattern: /comic/page/2/.
		add_rewrite_rule(
			"^{$prefix}/page/([0-9]+)/?$",
			'index.php?post_type=swipecomic&paged=$matches[1]',
			'top'
		);

		// Comics archive - Pattern: /comic/.
		add_rewrite_rule(
			"^{$prefix}/?$",
			'index.php?post_type=swipecomic',
			'top'
		);

		// Series archive pagination - Pattern: /comic/{series-slug}/page/2/.
		add_rewrite_rule(
			"^{$prefix}/([^/]+)/page/([0-9]+)/?$",
			'index.php?swipecomic_series=$matches[1]&paged=$matches[2]',
			'top'
		);

		// Episode single (with series) - Pattern: /comic/{series-slug}/{episode-slug}/.
		add_rewrite_rule(
			"^{$prefix}/([^/]+)/([^/]+)/?$",
			'index.php?swipecomic_series=$matches[1]&swipecomic=$matches[2]&swipecomic_check_series=1',
			'top'
		);

		// Single slug - could be series archive OR episode without series.
		// Pattern: /comic/{slug}/.
		// We set both query vars and let the conflict handler decide.
		add_rewrite_rule(
			"^{$prefix}/([^/]+)/?$",
			'index.php?swipecomic_series=$matches[1]&swipecomic=$matches[1]&swipecomic_check_single=1',
			'top'
		);
	}

	/**
	 * Add clean rewrite rules without prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix Fallback prefix for episodes without series.
	 */
	private function add_clean_rules( $prefix ) {
		// Comics archive pagination - Pattern: /comic/page/2/.
		add_rewrite_rule(
			"^{$prefix}/page/([0-9]+)/?$",
			'index.php?post_type=swipecomic&paged=$matches[1]',
			'top'
		);

		// Comics archive - Pattern: /comic/.
		add_rewrite_rule(
			"^{$prefix}/?$",
			'index.php?post_type=swipecomic',
			'top'
		);

		// Episode single (without series, fallback) - Pattern: /comic/{episode-slug}/.
		add_rewrite_rule(
			"^{$prefix}/([^/]+)/?$",
			'index.php?swipecomic=$matches[1]',
			'top'
		);

		// Series archive pagination - Pattern: /{series-slug}/page/2/.
		add_rewrite_rule(
			'^([^/]+)/page/([0-9]+)/?$',
			'index.php?swipecomic_series=$matches[1]&paged=$matches[2]',
			'top'
		);

		// Episode single (with series) - Pattern: /{series-slug}/{episode-slug}/.
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/?$',
			'index.php?swipecomic_series=$matches[1]&swipecomic=$matches[2]&swipecomic_check_series=1',
			'top'
		);

		// Series archive - Pattern: /{series-slug}/.
		add_rewrite_rule(
			'^([^/]+)/?$',
			'index.php?swipecomic_series=$matches[1]',
			'top'
		);
	}

	/**
	 * Add custom query vars.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'swipecomic_check_series';
		$vars[] = 'swipecomic_check_single';
		return $vars;
	}

	/**
	 * Handle conflicts with WordPress pages.
	 *
	 * Prevents swipecomic URLs from overriding existing WordPress pages.
	 *
	 * @since 1.0.0
	 */
	public function handle_conflicts() {
		global $wp_query;

		// Handle single slug that could be series or episode.
		if ( isset( $wp_query->query_vars['swipecomic_check_single'] ) ) {
			$this->handle_single_slug_conflict();
			return;
		}

		// Handle series/episode combination.
		if ( ! isset( $wp_query->query_vars['swipecomic_check_series'] ) ) {
			return;
		}

		$series_slug  = $wp_query->query_vars['swipecomic_series'] ?? '';
		$episode_slug = $wp_query->query_vars['swipecomic'] ?? '';

		if ( empty( $series_slug ) || empty( $episode_slug ) ) {
			return;
		}

		// Check if series exists.
		$series = get_term_by( 'slug', $series_slug, 'swipecomic_series' );

		if ( ! $series ) {
			// Series doesn't exist, check if it's a WordPress page.
			$full_path_page = get_page_by_path( $series_slug . '/' . $episode_slug );
			if ( $full_path_page ) {
				// It's a valid hierarchical page, let WordPress handle it.
				return;
			}

			$page = get_page_by_path( $series_slug );
			if ( $page ) {
				// The first part of the URL is a page, but the full path is not.
				// This is a 404, not a redirect. Let WordPress handle it.
				$wp_query->set_404();
				status_header( 404 );
				return;
			}

			// Not a page either, let WordPress handle 404.
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		// Series exists, check if episode exists and belongs to series.
		$episode = get_page_by_path( $episode_slug, OBJECT, 'swipecomic' );

		if ( ! $episode ) {
			// Episode doesn't exist, check if second segment is a page.
			$page = get_page_by_path( $series_slug . '/' . $episode_slug );
			if ( $page ) {
				wp_safe_redirect( get_permalink( $page ) );
				exit;
			}

			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		// Check if episode belongs to the series.
		$episode_series = wp_get_post_terms( $episode->ID, 'swipecomic_series', array( 'fields' => 'ids' ) );

		if ( ! in_array( $series->term_id, $episode_series, true ) ) {
			// Episode doesn't belong to this series.
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		// All checks passed, set up the query properly.
		$wp_query->queried_object    = $episode;
		$wp_query->queried_object_id = $episode->ID;
	}

	/**
	 * Handle single slug that could be series or episode.
	 *
	 * @since 1.0.0
	 */
	private function handle_single_slug_conflict() {
		global $wp_query;

		$slug = $wp_query->query_vars['swipecomic'] ?? '';

		if ( empty( $slug ) ) {
			return;
		}

		// Check if it's an episode first.
		$episode = get_page_by_path( $slug, OBJECT, 'swipecomic' );

		if ( $episode ) {
			// It's an episode - set up the query for single post.
			unset( $wp_query->query_vars['swipecomic_series'] );
			unset( $wp_query->query_vars['swipecomic_check_single'] );
			$wp_query->queried_object    = $episode;
			$wp_query->queried_object_id = $episode->ID;
			return;
		}

		// Not an episode, check if it's a series.
		$series = get_term_by( 'slug', $slug, 'swipecomic_series' );

		if ( $series ) {
			// It's a series - set up the query for taxonomy archive.
			unset( $wp_query->query_vars['swipecomic'] );
			unset( $wp_query->query_vars['swipecomic_check_single'] );
			$wp_query->queried_object    = $series;
			$wp_query->queried_object_id = $series->term_id;
			$wp_query->is_tax            = true;
			$wp_query->is_archive        = true;
			$wp_query->is_404            = false;
			return;
		}

		// Neither episode nor series - 404.
		$wp_query->set_404();
		status_header( 404 );
	}

	/**
	 * Generate custom permalink for swipecomic posts.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post object.
	 * @return string Modified permalink.
	 */
	public function swipecomic_permalink( $post_link, $post ) {
		if ( 'swipecomic' !== $post->post_type || 'publish' !== $post->post_status ) {
			return $post_link;
		}

		$use_prefix = Settings::use_url_prefix();
		$prefix     = Settings::get_url_prefix();

		// Get the first series assigned to this episode.
		$series = wp_get_post_terms( $post->ID, 'swipecomic_series' );

		if ( ! empty( $series ) && ! is_wp_error( $series ) ) {
			// Episode has a series.
			$series_slug  = $series[0]->slug;
			$episode_slug = $post->post_name;

			if ( $use_prefix ) {
				// With prefix: /comic/{series-slug}/{episode-slug}/.
				return home_url( "/{$prefix}/{$series_slug}/{$episode_slug}/" );
			} else {
				// Clean mode: /{series-slug}/{episode-slug}/.
				return home_url( "/{$series_slug}/{$episode_slug}/" );
			}
		} else {
			// Episode without series: always use prefix as fallback.
			return home_url( "/{$prefix}/{$post->post_name}/" );
		}
	}

	/**
	 * Generate custom permalink for series taxonomy terms.
	 *
	 * @since 1.0.0
	 *
	 * @param string $term_link Term link URL.
	 * @param object $term      Term object.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return string Modified term link.
	 */
	public function series_permalink( $term_link, $term, $taxonomy ) {
		if ( 'swipecomic_series' !== $taxonomy ) {
			return $term_link;
		}

		$use_prefix = Settings::use_url_prefix();
		$prefix     = Settings::get_url_prefix();

		if ( $use_prefix ) {
			// With prefix: /comic/{series-slug}/.
			return home_url( "/{$prefix}/{$term->slug}/" );
		} else {
			// Clean mode: /{series-slug}/.
			return home_url( "/{$term->slug}/" );
		}
	}
}
