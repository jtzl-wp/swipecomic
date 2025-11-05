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
$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( ! $is_block_theme ) {
	get_header();
}

// Get current series term.
$current_term = get_queried_object();

// Get series metadata.
$series_data = TemplateFunctions::get_series_data( $current_term->term_id );

// Query episodes in series ordered by episode_number.
// For taxonomy archives, we need to check both 'paged' and 'page' query vars.
$current_page      = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
$episodes_per_page = Settings::get_episodes_per_page();

$episodes_query = new WP_Query(
	array(
		'post_type'      => 'swipecomic',
		'posts_per_page' => $episodes_per_page,
		'paged'          => $current_page,
		'post_status'    => 'publish',
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'swipecomic_series',
				'field'    => 'term_id',
				'terms'    => $current_term->term_id,
			),
		),
		'meta_key'       => '_swipecomic_episode_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
	)
);

?>

<?php if ( $is_block_theme ) : ?>
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

<div class="swipecomic-series-archive">
	
	<header class="series-header">
		<?php if ( $series_data && ! empty( $series_data['cover_image']['url'] ) ) : ?>
			<div class="series-cover-wrapper">
				<img src="<?php echo esc_url( $series_data['cover_image']['url'] ); ?>" 
					alt="<?php echo esc_attr( $series_data['name'] ); ?>" 
					class="series-cover" />
			</div>
		<?php endif; ?>
		
		<h1 class="series-title"><?php echo esc_html( $current_term->name ); ?></h1>
		
		<?php if ( $series_data && ! empty( $series_data['description'] ) ) : ?>
			<div class="series-description">
				<?php echo wp_kses_post( wpautop( $series_data['description'] ) ); ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( $episodes_query->have_posts() ) : ?>
		<div class="series-episodes">
			<?php
			while ( $episodes_query->have_posts() ) :
				$episodes_query->the_post();
				$thumbnail      = TemplateFunctions::get_swipecomic_thumbnail( get_the_ID() );
				$episode_number = TemplateFunctions::get_episode_number( get_the_ID() );
				$chapter_number = TemplateFunctions::get_chapter_number( get_the_ID() );
				?>
				<article class="series-episode-card">
					<a href="<?php echo esc_url( get_permalink() ); ?>" class="episode-card-link">
						<?php if ( $thumbnail ) : ?>
							<div class="episode-thumbnail-wrapper">
								<img src="<?php echo esc_url( $thumbnail ); ?>" 
									alt="<?php echo esc_attr( get_the_title() ); ?>" 
									class="episode-thumbnail"
									loading="lazy" />
							</div>
						<?php endif; ?>
						
						<div class="episode-info">
							<h2 class="episode-title"><?php the_title(); ?></h2>
							
							<?php if ( $episode_number || $chapter_number ) : ?>
								<div class="episode-meta">
									<?php
									if ( $chapter_number && $episode_number ) {
										echo esc_html( sprintf( 'Chapter %s, Episode %s', $chapter_number, $episode_number ) );
									} elseif ( $episode_number ) {
										echo esc_html( sprintf( 'Episode %s', $episode_number ) );
									} elseif ( $chapter_number ) {
										echo esc_html( sprintf( 'Chapter %s', $chapter_number ) );
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
		if ( $episodes_query->max_num_pages > 1 ) :
			?>
			<nav class="series-pagination" aria-label="<?php esc_attr_e( 'Episodes pagination', 'swipecomic' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'total'     => $episodes_query->max_num_pages,
							'current'   => $current_page,
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

if ( ! $is_block_theme ) {
	get_footer();
} else {
	wp_footer();
	echo '</body></html>';
}
