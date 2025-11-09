/**
 * PhotoSwipe Viewer Module
 *
 * Main initialization module for the SwipeComic viewer
 * Configures PhotoSwipe Lightbox with custom settings for comic reading
 */

import PhotoSwipe from 'photoswipe';
// eslint-disable-next-line import/no-unresolved
import PhotoSwipeLightbox from 'photoswipe/lightbox';

import { LogoOverlayController, LogoConfig } from './logo-overlay-controller';
import {
	SettingsResolver,
	ImageData,
	DefaultSettings,
} from './settings-resolver';

export interface ViewerConfig {
	gallerySelector: string;
	globalDefaults: DefaultSettings;
	episodeDefaults: DefaultSettings;
	images: ImageData[];
	isMobile: boolean;
	seriesLogo?: LogoConfig;
	seriesArchiveUrl?: string; // URL to series archive page
}

export interface EpisodeData {
	id: number;
	title: string;
	images: ImageData[];
}

export class PhotoSwipeViewer {
	private lightbox: PhotoSwipeLightbox | null = null;
	private settingsResolver: SettingsResolver;
	private config: ViewerConfig;
	private logoController: LogoOverlayController | null = null;

	constructor(config: ViewerConfig) {
		this.config = config;
		this.settingsResolver = new SettingsResolver(
			config.globalDefaults,
			config.episodeDefaults
		);

		// Initialize logo controller if logo is configured
		if (config.seriesLogo && config.seriesLogo.url) {
			this.logoController = new LogoOverlayController(config.seriesLogo);
		}
	}

	/**
	 * Initialize the PhotoSwipe Lightbox
	 * @param autoOpen - Whether to automatically open the viewer on initialization
	 */
	init(autoOpen = false): void {
		this.lightbox = new PhotoSwipeLightbox({
			gallery: this.config.gallerySelector,
			children: 'a',
			pswpModule: PhotoSwipe,

			// Reading experience (from PoC)
			wheelToZoom: false,
			allowPanToNext: false, // Horizontal drag pans image instead of swiping
			closeOnVerticalDrag: false,
			showHideAnimationType: 'none',
			showAnimationDuration: 0,
			hideAnimationDuration: 0,
			padding: { top: 20, bottom: 20, left: 0, right: 0 },
			bgOpacity: 0.9,

			// Disable accidental zooms (from PoC)
			imageClickAction: 'none',
			doubleTapAction: 'none',
			tapAction: this.config.isMobile ? 'none' : 'toggle-controls',

			// Zoom settings
			initialZoomLevel: this.getInitialZoomLevel.bind(this),
			secondaryZoomLevel: 2,
			maxZoomLevel: 4,

			// Keyboard navigation
			arrowKeys: true,
			escKey: true,

			// Preloading (from PoC)
			preload: [0, 1], // Only next slide preloads

			// Trap focus for accessibility
			trapFocus: true,

			// Error handling
			errorMsg:
				'<div class="pswp__error-msg">The image could not be loaded.</div>',
		});

		// Apply pan-to-edge logic on slide activation
		this.lightbox.on('contentActivate', ({ content }) => {
			if (!this.lightbox?.pswp) return;

			const pswp = this.lightbox.pswp;

			const applyPan = () => {
				// Ensure the slide is still the current one before panning
				if (pswp.currSlide === content.slide) {
					this.applyPanToEdge(pswp);
				}
			};

			// If content is still loading, wait for it to complete.
			// Otherwise, apply pan immediately.
			if (content.isLoading()) {
				// Listen for loadComplete event on the pswp instance
				// eslint-disable-next-line @typescript-eslint/no-explicit-any
				const onLoadComplete = (e: any) => {
					if (e.content === content) {
						applyPan();
						pswp.off('loadComplete', onLoadComplete);
					}
				};
				pswp.on('loadComplete', onLoadComplete);
			} else {
				applyPan();
			}
		});

		// Register custom UI
		this.lightbox.on('uiRegister', () => {
			this.registerCustomUI();
		});

		// Mobile detection and controls visibility
		if (this.config.isMobile) {
			this.enforceMobileControls();
		}

		// Render series logo when PhotoSwipe opens
		if (this.logoController) {
			this.lightbox.on('afterInit', () => {
				this.renderSeriesLogo();
			});

			// Update logo size on viewport resize
			this.lightbox.on('resize', () => {
				this.updateLogoSize();
			});

			// Remove logo when PhotoSwipe closes
			this.lightbox.on('destroy', () => {
				if (this.logoController) {
					this.logoController.remove();
				}
			});
		}

		// Hide page content when viewer opens, show when it closes
		this.lightbox.on('afterInit', () => {
			this.hidePageContent();
		});

		this.lightbox.on('destroy', () => {
			this.showPageContent();
		});

		this.lightbox.init();

		// Auto-open the viewer if requested
		if (autoOpen && this.config.images.length > 0) {
			// Open at the first image
			this.lightbox.loadAndOpen(0);
		}
	}

	/**
	 * Get initial zoom level for an image
	 * @param zoomLevelObject - PhotoSwipe zoom level object
	 */
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	private getInitialZoomLevel(zoomLevelObject: any): number | string {
		// Get the item data from PhotoSwipe
		const item = zoomLevelObject.itemData;

		if (!item || !item.element) {
			return 'fit'; // Default to fit
		}

		// Read zoom setting from data attribute
		const zoomAttr = item.element.getAttribute('data-initial-zoom');

		if (!zoomAttr) {
			return 'fit';
		}

		// Handle vFill - use PhotoSwipe's calculated vFill value
		if (zoomAttr === 'vFill') {
			return zoomLevelObject.vFill;
		}

		// Handle fit
		if (zoomAttr === 'fit') {
			return 'fit';
		}

		// Handle custom numeric zoom (e.g., "150" means 1.5x)
		const customZoom = parseFloat(zoomAttr);
		if (!isNaN(customZoom) && customZoom > 0) {
			// Convert percentage to decimal (150 -> 1.5)
			return customZoom / 100;
		}

		// Fallback to fit
		return 'fit';
	}

	/**
	 * Apply pan-to-edge logic from PoC
	 * @param pswp
	 */
	private applyPanToEdge(pswp: PhotoSwipe): void {
		const slide = pswp.currSlide;
		if (!slide || !slide.data || !slide.data.element) return;

		// Read pan direction from data attribute
		const panAttr = slide.data.element.getAttribute('data-pan-direction');

		if (!panAttr) return;

		const bounds = slide.bounds;
		if (!bounds || !bounds.min) return;

		// Apply pan position based on direction
		if (panAttr === 'left') {
			slide.pan.x = bounds.min.x;
		} else if (panAttr === 'right') {
			slide.pan.x = bounds.max.x;
		} else if (panAttr === 'center') {
			slide.pan.x = (bounds.min.x + bounds.max.x) / 2;
		} else if (panAttr.includes(',')) {
			// Custom x,y coordinates (e.g., "100,200")
			const [x, y] = panAttr.split(',').map((v) => parseFloat(v.trim()));
			if (!isNaN(x) && !isNaN(y)) {
				slide.pan.x = x;
				slide.pan.y = y;
			}
		}

		// Update the slide position
		slide.applyCurrentZoomPan();
	}

	/**
	 * Register custom UI elements
	 */
	private registerCustomUI(): void {
		if (!this.lightbox?.pswp) return;

		// Add custom counter
		this.lightbox.pswp.ui?.registerElement({
			name: 'custom-counter',
			order: 9,
			isButton: false,
			appendTo: 'wrapper',
			html: '',
			onInit: (el: HTMLElement, pswp: PhotoSwipe) => {
				pswp.on('change', () => {
					const total = pswp.getNumItems();
					const current = pswp.currIndex + 1;
					el.textContent = `${current} / ${total}`;
				});

				// Initial update
				const total = pswp.getNumItems();
				const current = pswp.currIndex + 1;
				el.textContent = `${current} / ${total}`;
			},
		});
	}

	/**
	 * Enforce mobile-specific controls
	 */
	private enforceMobileControls(): void {
		if (!this.lightbox || !this.config.isMobile) return;

		// Hide desktop-only controls on mobile
		this.lightbox.on('uiRegister', () => {
			if (!this.lightbox?.pswp) return;

			// Override the zoom button to prevent it from being added on mobile.
			this.lightbox.pswp.ui?.registerElement({
				name: 'zoom',
				// By not providing other properties, we effectively disable it.
			});
		});
	}

	/**
	 * Find image data by slide data
	 * @param slideData - PhotoSwipe slide data object
	 */
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	private findImageData(slideData: any): ImageData | null {
		if (!slideData || !slideData.src) {
			return null;
		}

		// Find matching image by URL
		const image = this.config.images.find((img) => img.url === slideData.src);
		return image || null;
	}

	/**
	 * Update episode data (for episode boundary transitions)
	 * This will be used by the Episode Boundary Handler module
	 * @param episodeData
	 */
	updateEpisodeData(episodeData: EpisodeData): void {
		// Store episode data for future use
		// This method will be called by the Episode Boundary Handler
		// eslint-disable-next-line no-console
		console.log('Episode data updated:', episodeData.title);
	}

	/**
	 * Render series logo overlay
	 */
	private renderSeriesLogo(): void {
		if (!this.logoController || !this.lightbox?.pswp) return;

		// Get PhotoSwipe container element
		const pswpElement = this.lightbox.pswp.element;
		if (!pswpElement) return;

		// Get viewport size
		const viewportSize = {
			width: window.innerWidth,
			height: window.innerHeight,
		};

		// Render logo
		this.logoController.render(pswpElement, viewportSize);
	}

	/**
	 * Update logo size on viewport resize
	 */
	private updateLogoSize(): void {
		if (!this.logoController) return;

		const viewportSize = {
			width: window.innerWidth,
			height: window.innerHeight,
		};

		this.logoController.updateSize(viewportSize);
	}

	/**
	 * Hide page content when viewer opens
	 */
	private hidePageContent(): void {
		const article = document.querySelector('.swipecomic-episode');
		if (article) {
			article.classList.add('swipecomic-viewer-open');
			// eslint-disable-next-line no-console
			console.log('SwipeComic: Page content hidden');
		} else {
			// eslint-disable-next-line no-console
			console.warn('SwipeComic: Could not find .swipecomic-episode element');
		}
	}

	/**
	 * Show page content when viewer closes
	 */
	private showPageContent(): void {
		const article = document.querySelector('.swipecomic-episode');
		if (article) {
			article.classList.remove('swipecomic-viewer-open');
			// eslint-disable-next-line no-console
			console.log('SwipeComic: Page content shown');
		} else {
			// eslint-disable-next-line no-console
			console.warn('SwipeComic: Could not find .swipecomic-episode element');
		}
	}

	/**
	 * Destroy the lightbox instance
	 */
	destroy(): void {
		if (this.logoController) {
			this.logoController.remove();
		}

		if (this.lightbox) {
			this.lightbox.destroy();
			this.lightbox = null;
		}
	}
}

/**
 * Initialize PhotoSwipe viewer from DOM data
 * Reads configuration from window.swipecomicData (injected via wp_add_inline_script)
 */
export function initFromDOM(): PhotoSwipeViewer | null {
	// Check if data is available
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	const data = (window as any).swipecomicData;

	if (!data) {
		// eslint-disable-next-line no-console
		console.error('SwipeComic data not found on window object');
		return null;
	}

	try {
		// Validate required data structure
		if (!data.images || !Array.isArray(data.images)) {
			// eslint-disable-next-line no-console
			console.error('Invalid SwipeComic data: images array is missing');
			return null;
		}

		// Build viewer config
		const config: ViewerConfig = {
			gallerySelector: '#swipecomic-gallery',
			globalDefaults: data.globalDefaults || { zoom: 'fit', pan: 'center' },
			episodeDefaults: data.episodeDefaults || { zoom: 'fit', pan: 'center' },
			images: data.images,
			isMobile: window.innerWidth < 768,
			seriesArchiveUrl: data.seriesArchiveUrl || undefined,
			seriesLogo:
				data.seriesLogo && data.seriesLogo.url
					? {
							url: data.seriesLogo.url,
							position: data.seriesLogo.position || 'upper-left',
							alt: 'Series logo',
							linkUrl: data.seriesArchiveUrl || undefined,
						}
					: undefined,
		};

		// Create and initialize viewer
		const viewer = new PhotoSwipeViewer(config);
		// Use autoOpen setting from data, default to false
		// wp_localize_script converts booleans to strings, so check for both
		const autoOpen =
			data.autoOpen === true || data.autoOpen === '1' || data.autoOpen === 1;
		viewer.init(autoOpen);

		return viewer;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Failed to initialize SwipeComic viewer:', error);
		return null;
	}
}

// Auto-initialize when DOM is ready
if (typeof window !== 'undefined') {
	const autoInitViewer = () => {
		// Only initialize if the gallery element exists on the page
		if (document.getElementById('swipecomic-gallery')) {
			initFromDOM();
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', autoInitViewer);
	} else {
		// DOM already loaded
		autoInitViewer();
	}
}
