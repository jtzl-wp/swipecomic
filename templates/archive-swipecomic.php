<?php
/**
 * SwipeComic Post Type Archive Template
 *
 * This template displays all series (not individual episodes).
 * Shows series with their cover images in a grid layout.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JTZL\SwipeComic\Settings;

// Check if theme supports FSE or traditional templates.
$jtzl_swipecomic_is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( ! $jtzl_swipecomic_is_block_theme ) {
	get_header();
}

// Get all series terms.
$jtzl_swipecomic_series = get_terms(
	array(
		'taxonomy'   => 'swipecomic_series',
		'hide_empty' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
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

<div class="swipecomic-series-archive" style="--thumbnail-size: <?php echo (int) Settings::get_thumbnail_size(); ?>px;">
	
	<header class="series-header">
		<h1 class="series-title"><?php esc_html_e( 'All Series', 'swipecomic' ); ?></h1>
		<div class="series-description">
			<p><?php esc_html_e( 'Browse all available comic series.', 'swipecomic' ); ?></p>
		</div>
	</header>

	<?php if ( ! empty( $jtzl_swipecomic_series ) && ! is_wp_error( $jtzl_swipecomic_series ) ) : ?>
		<div class="series-episodes">
			<?php foreach ( $jtzl_swipecomic_series as $jtzl_swipecomic_series_term ) : ?>
				<?php
				$jtzl_swipecomic_cover_image_id  = get_term_meta( $jtzl_swipecomic_series_term->term_id, 'series_cover_image_id', true );
				$jtzl_swipecomic_cover_image_url = '';

				if ( $jtzl_swipecomic_cover_image_id ) {
					$jtzl_swipecomic_cover_image_url = wp_get_attachment_image_url( $jtzl_swipecomic_cover_image_id, 'swipecomic-thumbnail' );
				}

				$jtzl_swipecomic_series_link = get_term_link( $jtzl_swipecomic_series_term );

				// Get episode count for this series.
				$jtzl_swipecomic_episode_count = $jtzl_swipecomic_series_term->count;
				?>
				<article class="series-episode-card">
					<a href="<?php echo esc_url( $jtzl_swipecomic_series_link ); ?>" class="episode-card-link">
						<div class="episode-thumbnail-wrapper">
							<?php if ( $jtzl_swipecomic_cover_image_url ) : ?>
								<img src="<?php echo esc_url( $jtzl_swipecomic_cover_image_url ); ?>" 
									alt="<?php echo esc_attr( $jtzl_swipecomic_series_term->name ); ?>" 
									class="episode-thumbnail"
									loading="lazy" />
							<?php else : ?>
								<div class="episode-thumbnail" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #f5f5f5; display: flex; align-items: center; justify-content: center; color: #999;">
									<span style="font-size: 3em;">📚</span>
								</div>
							<?php endif; ?>
						</div>
						
						<div class="episode-info">
							<h2 class="episode-title"><?php echo esc_html( $jtzl_swipecomic_series_term->name ); ?></h2>
							
							<div class="episode-meta">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of episodes */
										_n( '%d Episode', '%d Episodes', $jtzl_swipecomic_episode_count, 'swipecomic' ),
										$jtzl_swipecomic_episode_count
									)
								);
								?>
							</div>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="series-no-episodes"><?php esc_html_e( 'No series found.', 'swipecomic' ); ?></p>
	<?php endif; ?>

</div>

<?php

if ( ! $jtzl_swipecomic_is_block_theme ) {
	get_footer();
} else {
	wp_footer();
	echo '</body></html>';
}
