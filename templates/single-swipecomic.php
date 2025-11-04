<?php
/**
 * Single SwipeComic Episode Template
 *
 * This template displays a single swipecomic episode with PhotoSwipe viewer.
 * Phase 2: Full-screen PhotoSwipe viewer with gesture navigation.
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

// Get episode data.
$images          = TemplateFunctions::get_swipecomic_images();
$episode_chapter = TemplateFunctions::format_episode_chapter();
$navigation      = TemplateFunctions::get_episode_navigation();

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

<article id="post-<?php the_ID(); ?>" <?php post_class( 'swipecomic-episode' ); ?>>
	
	<header class="swipecomic-header">
		<h1 class="swipecomic-title"><?php the_title(); ?></h1>
		
		<?php if ( $episode_chapter ) : ?>
			<div class="swipecomic-meta">
				<span class="swipecomic-episode-chapter"><?php echo esc_html( $episode_chapter ); ?></span>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( ! empty( $images ) ) : ?>
		<!-- PhotoSwipe gallery with thumbnail previews -->
		<div id="swipecomic-gallery" class="pswp-gallery swipecomic-images">
			<?php foreach ( $images as $image ) : ?>
				<a href="<?php echo esc_url( $image['url'] ); ?>"
					data-pswp-width="<?php echo esc_attr( $image['width'] ); ?>"
					data-pswp-height="<?php echo esc_attr( $image['height'] ); ?>"
					data-initial-zoom="<?php echo esc_attr( $image['zoom'] ); ?>"
					data-pan-direction="<?php echo esc_attr( $image['pan'] ); ?>"
					class="swipecomic-image-link">
					<img src="<?php echo esc_url( $image['url'] ); ?>" 
						alt="<?php echo esc_attr( $image['alt'] ); ?>"
						class="swipecomic-image"
						loading="lazy" />
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="swipecomic-no-images"><?php esc_html_e( 'No images available for this episode.', 'swipecomic' ); ?></p>
	<?php endif; ?>

	<!-- Drag hint element -->
	<div id="drag-hint" class="drag-hint">Drag sideways to read ↔︎</div>

	<!-- Episode navigation -->
	<?php if ( $navigation['prev'] || $navigation['next'] ) : ?>
		<nav class="swipecomic-navigation">
			<?php if ( $navigation['prev'] ) : ?>
				<a href="<?php echo esc_url( get_permalink( $navigation['prev'] ) ); ?>" class="prev-episode">
					← Previous Episode
				</a>
			<?php endif; ?>
			
			<?php if ( $navigation['next'] ) : ?>
				<a href="<?php echo esc_url( get_permalink( $navigation['next'] ) ); ?>" class="next-episode">
					Next Episode →
				</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<?php if ( ! empty( get_post()->post_content ) ) : ?>
		<footer class="swipecomic-footer">
			<?php the_content(); ?>
		</footer>
	<?php endif; ?>

	<?php
	// Prepare data for JavaScript - passed securely via wp_add_inline_script in Assets.php.
	// This data will be available as window.swipecomicData.
	?>

</article>

<style>
	/* Gallery layout */
	.swipecomic-images {
		display: flex;
		flex-direction: column;
		gap: 20px;
		margin: 20px 0;
	}
	
	.swipecomic-image-link {
		display: block;
		text-decoration: none;
	}
	
	.swipecomic-image {
		width: 100%;
		height: auto;
		display: block;
	}
	
	/* Hide PhotoSwipe placeholder */
	.pswp__img--placeholder {
		display: none !important;
	}
</style>

<?php

if ( ! $is_block_theme ) {
	get_footer();
} else {
	wp_footer();
	echo '</body></html>';
}
