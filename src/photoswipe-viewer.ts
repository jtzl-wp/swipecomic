/**
 * PhotoSwipe Viewer Module
 *
 * Main initialization module for the SwipeComic viewer
 * Configures PhotoSwipe Lightbox with custom settings for comic reading
 */

import PhotoSwipe from 'photoswipe';
// eslint-disable-next-line import/no-unresolved
import PhotoSwipeLightbox from 'photoswipe/lightbox';

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

	constructor(config: ViewerConfig) {
		this.config = config;
		this.settingsResolver = new SettingsResolver(
			config.globalDefaults,
			config.episodeDefaults
		);
	}

	/**
	 * Initialize the PhotoSwipe Lightbox
	 */
	init(): void {
		this.lightbox = new PhotoSwipeLightbox({
			gallery: this.config.gallerySelector,
			children: 'a',
			pswpModule: PhotoSwipe,

			// Spacing and padding
			spacing: 0.1,
			padding: { top: 20, bottom: 20, left: 20, right: 20 },

			// Zoom settings
			initialZoomLevel: this.getInitialZoomLevel.bind(this),
			secondaryZoomLevel: 2,
			maxZoomLevel: 3,

			// UI settings
			bgOpacity: 0.9,
			showHideAnimationType: 'fade',
			closeOnVerticalDrag: false,

			// Mobile-specific settings
			pinchToClose: this.config.isMobile,

			// Keyboard navigation
			arrowKeys: true,
			escKey: true,

			// Preloading
			preload: [1, 2], // Preload next 1-2 images

			// Trap focus for accessibility
			trapFocus: true,

			// Error handling
			errorMsg:
				'<div class="pswp__error-msg">The image could not be loaded.</div>',
		});

		// Apply pan-to-edge logic
		this.lightbox.on('uiRegister', () => {
			this.registerCustomUI();
		});

		// Handle initial zoom and pan
		this.lightbox.on('contentLoad', (e) => {
			this.handleContentLoad(e);
		});

		// Mobile detection and controls visibility
		if (this.config.isMobile) {
			this.enforceMobileControls();
		}

		this.lightbox.init();
	}

	/**
	 * Get initial zoom level for an image
	 * @param zoomLevelObject - PhotoSwipe zoom level object
	 */
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	private getInitialZoomLevel(zoomLevelObject: any): number {
		const { pswp } = zoomLevelObject;

		if (!pswp || !pswp.currSlide) {
			return 1; // Default to 1x zoom
		}

		const slideData = pswp.currSlide.data;
		const imageData = this.findImageData(slideData);

		if (!imageData) {
			return 1;
		}

		// Resolve zoom value using settings resolver
		const zoomValue = this.settingsResolver.resolveZoom(imageData);
		const parsedZoom = this.settingsResolver.parseZoomValue(zoomValue);

		// Convert to PhotoSwipe format
		// PhotoSwipe expects numeric values, 'fit' and 'fill' are handled differently
		if (parsedZoom === 'fit' || parsedZoom === 'vFill') {
			// Return 1 for fit/fill, PhotoSwipe will handle it
			return 1;
		}

		// Numeric zoom level
		return parsedZoom;
	}

	/**
	 * Apply pan-to-edge logic from PoC
	 * @param pswp
	 */
	private applyPanToEdge(pswp: PhotoSwipe): void {
		const slide = pswp.currSlide;
		if (!slide) return;

		const imageData = this.findImageData(slide.data);
		if (!imageData) return;

		// Resolve pan value using settings resolver
		const panValue = this.settingsResolver.resolvePan(imageData);
		const parsedPan = this.settingsResolver.parsePanValue(panValue);

		// Apply pan position
		if (parsedPan === 'left') {
			slide.pan.x = slide.bounds.min.x;
		} else if (parsedPan === 'right') {
			slide.pan.x = slide.bounds.max.x;
		} else if (parsedPan === 'center') {
			slide.pan.x = (slide.bounds.min.x + slide.bounds.max.x) / 2;
		} else if (typeof parsedPan === 'object') {
			// Custom x,y coordinates
			slide.pan.x = parsedPan.x;
			slide.pan.y = parsedPan.y;
		}

		// Update the slide position
		slide.applyCurrentZoomPan();
	}

	/**
	 * Handle content load event
	 * @param e - PhotoSwipe content load event
	 */
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	private handleContentLoad(e: any): void {
		const { content } = e;

		if (!content || !content.data) {
			return;
		}

		// Apply pan settings after zoom is set
		if (this.lightbox?.pswp) {
			setTimeout(() => {
				this.applyPanToEdge(this.lightbox!.pswp!);
			}, 50);
		}
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
	 * Destroy the lightbox instance
	 */
	destroy(): void {
		if (this.lightbox) {
			this.lightbox.destroy();
			this.lightbox = null;
		}
	}
}

/**
 * Initialize PhotoSwipe viewer from DOM data
 * @param gallerySelector
 */
export function initFromDOM(gallerySelector: string): PhotoSwipeViewer | null {
	const galleryElement = document.querySelector(gallerySelector);

	if (!galleryElement) {
		// eslint-disable-next-line no-console
		console.error(`Gallery element not found: ${gallerySelector}`);
		return null;
	}

	// Read configuration from data attributes
	const configData = galleryElement.getAttribute('data-swipecomic-config');

	if (!configData) {
		// eslint-disable-next-line no-console
		console.error('SwipeComic configuration data not found');
		return null;
	}

	try {
		const config = JSON.parse(configData) as ViewerConfig;

		// Detect mobile
		config.isMobile = window.innerWidth < 768;

		// Create and initialize viewer
		const viewer = new PhotoSwipeViewer(config);
		viewer.init();

		return viewer;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Failed to parse SwipeComic configuration:', error);
		return null;
	}
}
