<?php
/**
 * Series Archive Template
 *
 * This template displays all episodes in a swipecomic series.
 * Shows series metadata (title, description, cover) and episode list.
 *
 * @package   JTZL_SwipeComic
 * @since     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JTZL\SwipeComic\TemplateFunctions;
use JTZL\SwipeComic\Settings;

// Check if theme supports FSE or traditional templates.
$jtzl_swipecomic_is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( ! $jtzl_swipecomic_is_block_theme ) {
	get_header();
}

// Get current series term.
$jtzl_swipecomic_current_term = get_queried_object();

// Get series metadata.
$jtzl_swipecomic_series_data = TemplateFunctions::get_series_data( $jtzl_swipecomic_current_term->term_id );

// Query episodes in series ordered by episode_number.
// For taxonomy archives, we need to check both 'paged' and 'page' query vars.
$jtzl_swipecomic_current_page      = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
$jtzl_swipecomic_episodes_per_page = Settings::get_episodes_per_page();

$jtzl_swipecomic_episodes_query = new WP_Query(
	array(
		'post_type'      => 'swipecomic',
		'posts_per_page' => $jtzl_swipecomic_episodes_per_page,
		'paged'          => $jtzl_swipecomic_current_page,
		'post_status'    => 'publish',
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'swipecomic_series',
				'field'    => 'term_id',
				'terms'    => $jtzl_swipecomic_current_term->term_id,
			),
		),
		'meta_key'       => '_swipecomic_episode_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
	)
);

?>

<?php if ( $jtzl_swipecomic_is_block_theme ) : ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
<?php endif; ?>

<div class="swipecomic-series-archive" style="--thumbnail-size: <?php echo esc_attr( Settings::get_thumbnail_size() ); ?>px;">
	
	<header class="series-header">
		<?php if ( $jtzl_swipecomic_series_data && ! empty( $jtzl_swipecomic_series_data['cover_image']['url'] ) ) : ?>
			<div class="series-cover-wrapper">
				<img src="<?php echo esc_url( $jtzl_swipecomic_series_data['cover_image']['url'] ); ?>" 
					alt="<?php echo esc_attr( $jtzl_swipecomic_series_data['name'] ); ?>" 
					class="series-cover" />
			</div>
		<?php endif; ?>
		
		<h1 class="series-title"><?php echo esc_html( $jtzl_swipecomic_current_term->name ); ?></h1>
		
		<?php if ( $jtzl_swipecomic_series_data && ! empty( $jtzl_swipecomic_series_data['description'] ) ) : ?>
			<div class="series-description">
				<?php echo wp_kses_post( wpautop( $jtzl_swipecomic_series_data['description'] ) ); ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( $jtzl_swipecomic_episodes_query->have_posts() ) : ?>
		<div class="series-episodes">
			<?php
			while ( $jtzl_swipecomic_episodes_query->have_posts() ) :
				$jtzl_swipecomic_episodes_query->the_post();
				$jtzl_swipecomic_thumbnail      = TemplateFunctions::get_swipecomic_thumbnail( get_the_ID() );
				$jtzl_swipecomic_episode_number = TemplateFunctions::get_episode_number( get_the_ID() );
				$jtzl_swipecomic_chapter_number = TemplateFunctions::get_chapter_number( get_the_ID() );
				?>
				<article class="series-episode-card">
					<a href="<?php echo esc_url( get_permalink() ); ?>" class="episode-card-link">
						<?php if ( $jtzl_swipecomic_thumbnail ) : ?>
							<div class="episode-thumbnail-wrapper">
								<img src="<?php echo esc_url( $jtzl_swipecomic_thumbnail ); ?>" 
									alt="<?php echo esc_attr( get_the_title() ); ?>" 
									class="episode-thumbnail"
									loading="lazy" />
							</div>
						<?php endif; ?>
						
						<div class="episode-info">
							<h2 class="episode-title"><?php the_title(); ?></h2>
							
							<?php if ( $jtzl_swipecomic_episode_number || $jtzl_swipecomic_chapter_number ) : ?>
								<div class="episode-meta">
									<?php
									if ( $jtzl_swipecomic_chapter_number && $jtzl_swipecomic_episode_number ) {
										echo esc_html( sprintf( 'Chapter %s, Episode %s', $jtzl_swipecomic_chapter_number, $jtzl_swipecomic_episode_number ) );
									} elseif ( $jtzl_swipecomic_episode_number ) {
										echo esc_html( sprintf( 'Episode %s', $jtzl_swipecomic_episode_number ) );
									} elseif ( $jtzl_swipecomic_chapter_number ) {
										echo esc_html( sprintf( 'Chapter %s', $jtzl_swipecomic_chapter_number ) );
									}
									?>
								</div>
							<?php endif; ?>
						</div>
					</a>
				</article>
			<?php endwhile; ?>
		</div>
		
		<?php
		// Pagination.
		if ( $jtzl_swipecomic_episodes_query->max_num_pages > 1 ) :
			?>
			<nav class="series-pagination" aria-label="<?php esc_attr_e( 'Episodes pagination', 'swipecomic' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'total'     => $jtzl_swipecomic_episodes_query->max_num_pages,
							'current'   => $jtzl_swipecomic_current_page,
							'prev_text' => __( '&laquo; Previous', 'swipecomic' ),
							'next_text' => __( 'Next &raquo;', 'swipecomic' ),
							'type'      => 'list',
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	<?php else : ?>
		<p class="series-no-episodes"><?php esc_html_e( 'No episodes found in this series.', 'swipecomic' ); ?></p>
	<?php endif; ?>

	<?php wp_reset_postdata(); ?>

</div>

<?php

if ( ! $jtzl_swipecomic_is_block_theme ) {
	get_footer();
} else {
	wp_footer();
	echo '</body></html>';
}
