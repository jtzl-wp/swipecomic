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
$jtzl_swipecomic_is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( ! $jtzl_swipecomic_is_block_theme ) {
	get_header();
}

// Get episode data.
$jtzl_swipecomic_images          = TemplateFunctions::get_swipecomic_images();
$jtzl_swipecomic_episode_chapter = TemplateFunctions::format_episode_chapter();
$jtzl_swipecomic_navigation      = TemplateFunctions::get_episode_navigation();

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

<article id="post-<?php the_ID(); ?>" <?php post_class( 'swipecomic-episode' ); ?>>
	
	<header class="swipecomic-header">
		<h1 class="swipecomic-title"><?php the_title(); ?></h1>
		
		<?php if ( $jtzl_swipecomic_episode_chapter ) : ?>
			<div class="swipecomic-meta">
				<span class="swipecomic-episode-chapter"><?php echo esc_html( $jtzl_swipecomic_episode_chapter ); ?></span>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( ! empty( $jtzl_swipecomic_images ) ) : ?>
		<!-- PhotoSwipe gallery with thumbnail previews -->
		<div id="swipecomic-gallery" class="pswp-gallery swipecomic-images">
			<?php foreach ( $jtzl_swipecomic_images as $jtzl_swipecomic_image ) : ?>
				<a href="<?php echo esc_url( $jtzl_swipecomic_image['url'] ); ?>"
					data-pswp-width="<?php echo esc_attr( $jtzl_swipecomic_image['width'] ); ?>"
					data-pswp-height="<?php echo esc_attr( $jtzl_swipecomic_image['height'] ); ?>"
					data-initial-zoom="<?php echo esc_attr( $jtzl_swipecomic_image['zoom'] ); ?>"
					data-pan-direction="<?php echo esc_attr( $jtzl_swipecomic_image['pan'] ); ?>"
					class="swipecomic-image-link">
					<img src="<?php echo esc_url( $jtzl_swipecomic_image['url'] ); ?>" 
						alt="<?php echo esc_attr( $jtzl_swipecomic_image['alt'] ); ?>"
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
	<?php if ( $jtzl_swipecomic_navigation['prev'] || $jtzl_swipecomic_navigation['next'] ) : ?>
		<nav class="swipecomic-navigation">
			<?php if ( $jtzl_swipecomic_navigation['prev'] ) : ?>
				<a href="<?php echo esc_url( get_permalink( $jtzl_swipecomic_navigation['prev'] ) ); ?>" class="prev-episode">
					← Previous Episode
				</a>
			<?php endif; ?>
			
			<?php if ( $jtzl_swipecomic_navigation['next'] ) : ?>
				<a href="<?php echo esc_url( get_permalink( $jtzl_swipecomic_navigation['next'] ) ); ?>" class="next-episode">
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

if ( ! $jtzl_swipecomic_is_block_theme ) {
	get_footer();
} else {
	wp_footer();
	echo '</body></html>';
}
