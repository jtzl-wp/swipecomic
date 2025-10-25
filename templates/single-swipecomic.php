<?php
/**
 * Single SwipeComic Episode Template
 *
 * This template displays a single swipecomic episode with basic layout.
 * Phase 1: Simple sequential image display without advanced viewer.
 *
 * @package   JTZL_SwipeComic
 * @since     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

use JTZL\SwipeComic\TemplateFunctions;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'swipecomic-episode' ); ?>>
	
	<?php
	// Display series logo if available.
	if ( TemplateFunctions::has_series_logo() ) :
		$logo_position = TemplateFunctions::get_series_logo_position();
		?>
		<div class="swipecomic-logo swipecomic-logo-<?php echo esc_attr( $logo_position ); ?>">
			<?php TemplateFunctions::the_series_logo(); ?>
		</div>
	<?php endif; ?>

	<header class="swipecomic-header">
		<h1 class="swipecomic-title"><?php the_title(); ?></h1>
	</header>

	<div class="swipecomic-images">
		<?php
		$images = TemplateFunctions::get_swipecomic_images();

		if ( ! empty( $images ) ) :
			foreach ( $images as $image ) :
				?>
				<div class="swipecomic-image-wrapper">
					<img 
						src="<?php echo esc_url( $image['url'] ); ?>" 
						alt="<?php echo esc_attr( $image['alt'] ); ?>"
						data-zoom="<?php echo esc_attr( $image['zoom'] ); ?>"
						data-pan="<?php echo esc_attr( $image['pan'] ); ?>"
						class="swipecomic-image"
						loading="lazy"
					/>
				</div>
				<?php
			endforeach;
		else :
			?>
			<p class="swipecomic-no-images"><?php esc_html_e( 'No images available for this episode.', 'swipecomic' ); ?></p>
			<?php
		endif;
		?>
	</div>

	<?php if ( ! empty( get_post()->post_content ) ) : ?>
		<footer class="swipecomic-footer">
			<?php the_content(); ?>
		</footer>
	<?php endif; ?>

</article>

<?php

get_footer();
