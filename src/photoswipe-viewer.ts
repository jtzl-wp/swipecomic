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
	EpisodeBoundaryHandler,
	BoundaryConfig,
	EpisodeData,
} from './episode-boundary-handler';
import { LogoOverlayController, LogoConfig } from './logo-overlay-controller';
import { showErrorNotification } from './notification-utils';
import {
	ImageData,
	DefaultSettings,
	ZoomValue,
	PanValue,
} from './settings-resolver';

export interface ViewerConfig {
	gallerySelector: string;
	globalDefaults: DefaultSettings;
	episodeDefaults: DefaultSettings;
	images: ImageData[];
	isMobile: boolean;
	seriesLogo?: LogoConfig;
	seriesArchiveUrl?: string; // URL to series archive page
	navigation?: {
		nextEpisodeId?: number;
		prevEpisodeId?: number;
	};
	ajaxUrl?: string;
	nonce?: string;
	episodeId?: number;
	showLightboxTools?: boolean;
}

export class PhotoSwipeViewer {
	private lightbox: PhotoSwipeLightbox | null = null;
	private config: ViewerConfig;
	private logoController: LogoOverlayController | null = null;
	private boundaryHandler: EpisodeBoundaryHandler | null = null;
	private isTransitioning = false;
	private uiHideTimeout: ReturnType<typeof setTimeout> | null = null;
	private uiShowHandler: (() => void) | null = null;
	private galleryClickHandler: (() => void) | null = null;
	private galleryKeyHandler: ((e: KeyboardEvent) => void) | null = null;

	constructor(config: ViewerConfig) {
		this.config = config;

		// Initialize logo controller if logo is configured
		if (config.seriesLogo && config.seriesLogo.url) {
			this.logoController = new LogoOverlayController(config.seriesLogo);
		}

		// Initialize episode boundary handler if navigation is available
		if (
			config.ajaxUrl &&
			config.nonce &&
			config.episodeId &&
			config.navigation
		) {
			const boundaryConfig: BoundaryConfig = {
				ajaxUrl: config.ajaxUrl,
				nonce: config.nonce,
				currentEpisodeId: config.episodeId,
			};
			this.boundaryHandler = new EpisodeBoundaryHandler(boundaryConfig);

			// Prefetch adjacent episodes
			this.boundaryHandler.prefetchAdjacentEpisodes(
				config.navigation.nextEpisodeId,
				config.navigation.prevEpisodeId
			);
		}
	}

	/**
	 * Initialize the PhotoSwipe Lightbox
	 * @param autoOpen - Whether to automatically open the viewer on initialization
	 */
	init(autoOpen = false): void {
		this.lightbox = new PhotoSwipeLightbox({
			// Use dynamic content instead of DOM elements
			dataSource: this.config.images.map((img) => ({
				src: img.url,
				width: img.width,
				height: img.height,
				alt: '',
			})),
			pswpModule: PhotoSwipe,

			// Reading experience (from PoC)
			wheelToZoom: false,
			allowPanToNext: true, // Allow swipe navigation between images
			closeOnVerticalDrag: false,
			showHideAnimationType: 'none',
			showAnimationDuration: 0,
			hideAnimationDuration: 0,
			padding: { top: 20, bottom: 20, left: 0, right: 0 },
			bgOpacity: 0.9,

			// Disable accidental zooms (from PoC)
			imageClickAction: false,
			doubleTapAction: false,
			tapAction: this.config.isMobile ? false : 'toggle-controls',

			// Touch gestures (built-in PhotoSwipe features)
			// - Swipe left/right: Navigate between images (enabled by default)
			// - Pinch-to-zoom: Zoom in/out on images (enabled by default)
			// - Drag-to-pan: Pan within zoomed images (enabled by default)

			// Zoom settings
			initialZoomLevel: this.getInitialZoomLevel.bind(this),
			secondaryZoomLevel: 2,
			maxZoomLevel: 4,

			// Keyboard navigation
			arrowKeys: true, // Left/right arrows navigate between images
			escKey: true, // Escape key closes viewer

			// Preloading - load current image + 1 ahead for optimal performance
			preload: [0, 1], // Load current image and preload 1 ahead (as per lazy loading spec)

			// Loading indicators
			preloaderDelay: 100, // Show loading spinner after 100ms delay for immediate feedback

			// Trap focus for accessibility
			trapFocus: true,

			// Error handling
			errorMsg:
				'<div class="pswp__error-msg">The image could not be loaded.</div>',
		});

		// Track which slides have shown the drag hint
		const hintShownForSlide = new Set<number>();

		// Apply pan-to-edge logic on slide activation
		this.lightbox.on('contentActivate', ({ content }) => {
			if (!this.lightbox?.pswp) return;

			const pswp = this.lightbox.pswp;

			const applyPan = () => {
				// Ensure the slide is still the current one before panning
				if (pswp.currSlide === content.slide) {
					this.applyPanToEdge(pswp);
					// Show drag hint if image is wide (skip first slide, handled in openingAnimationEnd)
					if (pswp.currIndex !== 0) {
						this.showDragHintIfWide(pswp, content.slide, hintShownForSlide);
					}
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
			this.setupCustomKeyboardHandlers();
			this.setupUIAutoHide();
			this.setupPopStateHandler();

			// Set up boundary navigation after PhotoSwipe is initialized
			if (this.boundaryHandler) {
				this.setupBoundaryNavigation();
			}

			// Apply tools visibility setting
			if (this.config.showLightboxTools === false) {
				this.hideTopBarTools();
			}
		});

		// Show drag hint on opening animation end (always show for first slide)
		this.lightbox.on('openingAnimationEnd', () => {
			if (!this.lightbox?.pswp) return;

			const pswp = this.lightbox.pswp;

			// Always show hint on first slide, check width on other slides
			if (pswp.currIndex === 0) {
				const hintEl = document.getElementById('drag-hint');
				if (hintEl) {
					hintShownForSlide.add(0);
					hintEl.style.display = 'block';
					setTimeout(() => {
						hintEl.style.display = 'none';
					}, 2500);
				}
			} else {
				this.showDragHintIfWide(pswp, pswp.currSlide, hintShownForSlide);
			}
		});

		this.lightbox.on('destroy', () => {
			this.showPageContent();
			this.removeCustomKeyboardHandlers();
			this.removePopStateHandler();
		});

		// Handle episode boundary transitions
		if (this.boundaryHandler) {
			this.lightbox.on('change', () => {
				this.handleBoundaryCheck();
			});
		}

		// Set up loading state feedback after PhotoSwipe initializes
		this.lightbox.on('afterInit', () => {
			if (!this.lightbox?.pswp) return;

			const pswp = this.lightbox.pswp;

			// Add custom loading overlay for immediate feedback
			this.addLoadingOverlay(pswp);

			// Loading state feedback
			pswp.on('contentLoad', ({ content }) => {
				// Content started loading
				// eslint-disable-next-line no-console
				console.log(`Loading image ${content.index + 1}...`);

				// Show loading overlay for current slide
				if (content.index === pswp.currIndex) {
					this.showLoadingOverlay(pswp);
				}
			});

			pswp.on('loadComplete', ({ content, isError }) => {
				if (isError) {
					// Content failed to load
					// eslint-disable-next-line no-console
					console.error(`✗ Failed to load image ${content.index + 1}`);
					this.showImageLoadError(content.index + 1);

					// Hide loading overlay only if the error is for the current slide
					if (content.index === pswp.currIndex) {
						this.hideLoadingOverlay(pswp);
					}
				} else {
					// Content finished loading successfully
					// eslint-disable-next-line no-console
					console.log(`✓ Image ${content.index + 1} loaded`);

					// Hide loading overlay for current slide
					if (content.index === pswp.currIndex) {
						this.hideLoadingOverlay(pswp);
					}
				}
			});
		});

		// Add click handler to gallery container to open viewer
		const galleryElement = document.querySelector(
			this.config.gallerySelector
		) as HTMLElement;
		if (galleryElement) {
			// Make gallery accessible for keyboard users
			galleryElement.setAttribute('role', 'button');
			galleryElement.setAttribute('tabindex', '0');
			galleryElement.setAttribute(
				'aria-label',
				'Open comic viewer to read episode'
			);

			// Store handlers for cleanup
			this.galleryClickHandler = () => {
				if (this.lightbox && this.config.images.length > 0) {
					this.lightbox.loadAndOpen(0);
				}
			};

			this.galleryKeyHandler = (e: KeyboardEvent) => {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					if (this.lightbox && this.config.images.length > 0) {
						this.lightbox.loadAndOpen(0);
					}
				}
			};

			galleryElement.addEventListener('click', this.galleryClickHandler);
			galleryElement.addEventListener('keydown', this.galleryKeyHandler);

			// Make it look clickable
			galleryElement.style.cursor = 'pointer';
		}

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
	private getInitialZoomLevel(zoomLevelObject: any): number {
		// Get the current slide index
		const slideIndex = zoomLevelObject.index;

		if (slideIndex === undefined || !this.config.images[slideIndex]) {
			return zoomLevelObject.fit; // Default to fit
		}

		// Get zoom setting from our config
		const zoomSetting = this.config.images[slideIndex].zoom;

		if (!zoomSetting) {
			return zoomLevelObject.fit;
		}

		// Handle vFill - use PhotoSwipe's calculated vFill value
		if (zoomSetting === 'vFill') {
			return zoomLevelObject.vFill;
		}

		// Handle fit
		if (zoomSetting === 'fit') {
			return zoomLevelObject.fit;
		}

		// Handle custom numeric zoom (e.g., "150" means 1.5x)
		const customZoom = parseFloat(String(zoomSetting));
		if (!isNaN(customZoom) && customZoom > 0) {
			// Convert percentage to decimal (150 -> 1.5)
			return customZoom / 100;
		}

		// Fallback to fit
		return zoomLevelObject.fit;
	}

	/**
	 * Apply pan-to-edge logic from PoC
	 * @param pswp
	 */
	private applyPanToEdge(pswp: PhotoSwipe): void {
		const slide = pswp.currSlide;
		if (!slide) return;

		// Get pan direction from our config
		const slideIndex = pswp.currIndex;
		if (slideIndex === undefined || !this.config.images[slideIndex]) return;

		const panSetting = this.config.images[slideIndex].pan;
		if (!panSetting) return;

		const bounds = slide.bounds;
		if (!bounds || !bounds.min) return;

		// Apply pan position based on direction
		if (panSetting === 'left') {
			slide.pan.x = bounds.min.x;
		} else if (panSetting === 'right') {
			slide.pan.x = bounds.max.x;
		} else if (panSetting === 'center') {
			slide.pan.x = (bounds.min.x + bounds.max.x) / 2;
		} else if (panSetting.includes(',')) {
			// Custom x,y coordinates (e.g., "100,200")
			const [x, y] = panSetting.split(',').map((v) => parseFloat(v.trim()));
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

		const pswp = this.lightbox.pswp;

		// Add custom counter in top bar (before zoom button)
		pswp.ui?.registerElement({
			name: 'custom-counter',
			order: 5, // Before zoom button (order 7)
			isButton: false,
			appendTo: 'bar',
			html: '',
			onInit: (el: HTMLElement, pswpInstance: PhotoSwipe) => {
				pswpInstance.on('change', () => {
					const total = pswpInstance.getNumItems();
					const current = pswpInstance.currIndex + 1;
					el.textContent = `${current} / ${total}`;
				});

				// Initial update
				const total = pswpInstance.getNumItems();
				const current = pswpInstance.currIndex + 1;
				el.textContent = `${current} / ${total}`;
			},
		});

		// Modify arrow button behavior when episode navigation is available
		if (this.boundaryHandler) {
			// Wait for UI to be ready, then modify arrow button behavior
			setTimeout(() => {
				const updateArrowState = () => {
					if (!pswp) return;

					const currentIndex = pswp.currIndex;
					const totalImages = pswp.getNumItems();

					// Find the arrow buttons
					const arrowNext = pswp.element?.querySelector(
						'.pswp__button--arrow--next'
					) as HTMLElement;
					const arrowPrev = pswp.element?.querySelector(
						'.pswp__button--arrow--prev'
					) as HTMLElement;

					// Update next button state
					if (arrowNext) {
						const hasNextImage = currentIndex < totalImages - 1;
						const hasNextEpisode = this.config.navigation?.nextEpisodeId;
						const shouldEnable = hasNextImage || hasNextEpisode;

						// Use disabled attribute instead of display to preserve auto-hide
						if (shouldEnable) {
							arrowNext.removeAttribute('disabled');
							arrowNext.style.pointerEvents = 'auto';
						} else {
							arrowNext.setAttribute('disabled', 'true');
							arrowNext.style.pointerEvents = 'none';
							arrowNext.style.opacity = '0.3';
						}
					}

					// Update prev button state
					if (arrowPrev) {
						const hasPrevImage = currentIndex > 0;
						const hasPrevEpisode = this.config.navigation?.prevEpisodeId;
						// Enable prev button if:
						// - There's a previous image in current episode, OR
						// - We're on first image AND there's a previous episode
						const shouldEnable =
							hasPrevImage || (currentIndex === 0 && hasPrevEpisode);

						// Use disabled attribute instead of display to preserve auto-hide
						if (shouldEnable) {
							arrowPrev.removeAttribute('disabled');
							arrowPrev.style.pointerEvents = 'auto';
						} else {
							arrowPrev.setAttribute('disabled', 'true');
							arrowPrev.style.pointerEvents = 'none';
							arrowPrev.style.opacity = '0.3';
						}
					}
				};

				// Update on slide change
				pswp.on('change', updateArrowState);

				// Initial update
				updateArrowState();
			}, 0);
		}
	}

	/**
	 * Show drag hint if image is wider than viewport
	 * Based on PoC implementation
	 * @param pswp
	 * @param slide
	 * @param hintShownForSlide
	 */
	private showDragHintIfWide(
		pswp: PhotoSwipe,
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		slide: any,
		hintShownForSlide: Set<number>
	): void {
		if (!slide) return;

		const slideKey = slide.index !== undefined ? slide.index : pswp.currIndex;

		const viewportW = pswp.viewportSize?.x;
		const viewportH = pswp.viewportSize?.y;
		if (!viewportW || !viewportH) return;

		// Get image dimensions
		let imgWidth = slide.width || slide.data?.width || slide.data?.w;
		let imgHeight = slide.height || slide.data?.height || slide.data?.h;

		// If still no dimensions, try from the element
		if (!imgWidth || !imgHeight) {
			const element = slide.data?.element;
			if (element) {
				imgWidth = parseInt(element.getAttribute('data-pswp-width'));
				imgHeight = parseInt(element.getAttribute('data-pswp-height'));
			}
		}

		if (!imgWidth || !imgHeight) return;

		// Calculate if image at vFill zoom (filling viewport height) would be wider than viewport
		// vFill means image height = viewport height, so image width at vFill = (imgWidth/imgHeight) * viewportH
		const widthAtVFill = (imgWidth / imgHeight) * viewportH;
		const imageIsWider = widthAtVFill > viewportW;

		if (!imageIsWider) return;

		// Show hint only once per slide per session
		if (hintShownForSlide.has(slideKey)) return;
		hintShownForSlide.add(slideKey);

		const hintEl = document.getElementById('drag-hint');
		if (!hintEl) return;

		hintEl.style.display = 'block';
		setTimeout(() => {
			hintEl.style.display = 'none';
		}, 2200);
	}

	/**
	 * Enforce mobile-specific controls (from PoC)
	 */
	private enforceMobileControls(): void {
		if (!this.lightbox || !this.config.isMobile) return;

		// On mobile, force controls to always be visible (from PoC)
		this.lightbox.on('afterInit', () => {
			if (!this.lightbox?.pswp) return;

			const pswp = this.lightbox.pswp;

			// Add ui-visible class immediately
			pswp.element?.classList.add('pswp--ui-visible');

			// Prevent controls from ever being hidden on mobile (from PoC)
			const keepControlsVisible = () => {
				if (!pswp.element?.classList.contains('pswp--ui-visible')) {
					pswp.element?.classList.add('pswp--ui-visible');
				}
			};

			// Also check on any UI update event (from PoC)
			pswp.on('change', keepControlsVisible);

			// Use MutationObserver to efficiently keep controls visible
			const observer = new MutationObserver((mutationsList) => {
				for (const mutation of mutationsList) {
					if (
						mutation.type === 'attributes' &&
						mutation.attributeName === 'class'
					) {
						const target = mutation.target as HTMLElement;
						if (!target.classList.contains('pswp--ui-visible')) {
							target.classList.add('pswp--ui-visible');
						}
					}
				}
			});

			if (pswp.element) {
				observer.observe(pswp.element, { attributes: true });
			}

			// Clean up on destroy
			pswp.on('destroy', () => {
				observer.disconnect();
			});
		});

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
	 * Set up auto-hide UI functionality
	 * PhotoSwipe v5 doesn't have built-in auto-hide, so we implement it
	 */
	private setupUIAutoHide(): void {
		if (!this.lightbox?.pswp) return;

		const pswp = this.lightbox.pswp;
		const UI_HIDE_DELAY = 4000; // 4 seconds

		const scheduleUIHide = () => {
			// Clear existing timeout
			if (this.uiHideTimeout) {
				clearTimeout(this.uiHideTimeout);
			}

			// Show UI
			pswp.element?.classList.add('pswp--ui-visible');

			// Schedule hide
			this.uiHideTimeout = setTimeout(() => {
				pswp.element?.classList.remove('pswp--ui-visible');
			}, UI_HIDE_DELAY);
		};

		this.uiShowHandler = () => {
			pswp.element?.classList.add('pswp--ui-visible');
			scheduleUIHide();
		};

		// Show UI on mouse move (desktop)
		pswp.element?.addEventListener('mousemove', this.uiShowHandler);

		// Show UI on touch/tap (mobile)
		pswp.element?.addEventListener('touchstart', this.uiShowHandler);

		// Show UI on keyboard interaction
		document.addEventListener('keydown', this.uiShowHandler);

		// Initial schedule
		scheduleUIHide();

		// Clean up on destroy
		pswp.on('destroy', () => {
			if (this.uiHideTimeout) {
				clearTimeout(this.uiHideTimeout);
			}
			// Remove event listeners
			if (this.uiShowHandler) {
				pswp.element?.removeEventListener('mousemove', this.uiShowHandler);
				pswp.element?.removeEventListener('touchstart', this.uiShowHandler);
				document.removeEventListener('keydown', this.uiShowHandler);
			}
		});
	}

	/**
	 * Handle browser back/forward navigation
	 * When user clicks back/forward after episode transitions, reload the page
	 */
	private popStateHandler = (): void => {
		// If PhotoSwipe is open and user navigates back/forward, reload the page
		// This ensures the page content matches the URL
		if (this.lightbox?.pswp) {
			window.location.reload();
		}
	};

	/**
	 * Set up popstate handler for browser back/forward buttons
	 */
	private setupPopStateHandler(): void {
		window.addEventListener('popstate', this.popStateHandler);
	}

	/**
	 * Remove popstate handler
	 */
	private removePopStateHandler(): void {
		window.removeEventListener('popstate', this.popStateHandler);
	}

	/**
	 * Custom keyboard handler for Home/End keys and episode navigation
	 * @param e - Keyboard event
	 */
	private keyboardHandler = (e: KeyboardEvent): void => {
		if (!this.lightbox?.pswp) return;

		const pswp = this.lightbox.pswp;

		// Home key - go to first image
		if (e.key === 'Home') {
			e.preventDefault();
			pswp.goTo(0);
		}

		// End key - go to last image
		if (e.key === 'End') {
			e.preventDefault();
			const lastIndex = pswp.getNumItems() - 1;
			pswp.goTo(lastIndex);
		}

		// Handle episode navigation for single-image episodes
		if (this.boundaryHandler && pswp.getNumItems() === 1) {
			// Right arrow - go to next episode
			if (e.key === 'ArrowRight' && this.config.navigation?.nextEpisodeId) {
				e.preventDefault();
				this.transitionToNextEpisode();
			}

			// Left arrow - go to previous episode
			if (e.key === 'ArrowLeft' && this.config.navigation?.prevEpisodeId) {
				e.preventDefault();
				this.transitionToPrevEpisode();
			}
		}
	};

	/**
	 * Mouse wheel handler for desktop zoom (with Ctrl key)
	 * @param e - Wheel event
	 */
	private wheelHandler = (e: WheelEvent): void => {
		if (!this.lightbox?.pswp) return;

		// Only enable on desktop (not mobile)
		if (this.config.isMobile) return;

		// Only zoom with Ctrl key pressed
		if (!e.ctrlKey && !e.metaKey) return;

		e.preventDefault();

		const pswp = this.lightbox.pswp;
		const slide = pswp.currSlide;

		if (!slide) return;

		// Get mouse position relative to the slide
		const rect = pswp.element?.getBoundingClientRect();
		if (!rect) return;

		// Get current zoom level
		const currentZoom = slide.currZoomLevel || 1;

		// Calculate new zoom level based on wheel direction
		const zoomDelta = e.deltaY > 0 ? -0.1 : 0.1; // Zoom out if scrolling down, zoom in if scrolling up
		const maxZoom =
			typeof pswp.options.maxZoomLevel === 'number'
				? pswp.options.maxZoomLevel
				: 4;
		const newZoom = Math.max(
			slide.zoomLevels?.fit || 1,
			Math.min(maxZoom, currentZoom + zoomDelta)
		);

		const mouseX = e.clientX - rect.left;
		const mouseY = e.clientY - rect.top;

		// Zoom to the mouse position
		slide.zoomTo(
			newZoom,
			{ x: mouseX, y: mouseY },
			300 // Animation duration in ms
		);
	};

	/**
	 * Set up custom keyboard handlers
	 */
	private setupCustomKeyboardHandlers(): void {
		document.addEventListener('keydown', this.keyboardHandler);

		// Add wheel handler for desktop zoom (Ctrl + wheel)
		if (!this.config.isMobile) {
			document.addEventListener('wheel', this.wheelHandler, { passive: false });
		}
	}

	/**
	 * Remove custom keyboard handlers
	 */
	private removeCustomKeyboardHandlers(): void {
		document.removeEventListener('keydown', this.keyboardHandler);

		// Remove wheel handler
		if (!this.config.isMobile) {
			document.removeEventListener('wheel', this.wheelHandler);
		}
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
	 * Set up boundary navigation handlers
	 * Intercepts navigation attempts at episode boundaries
	 */
	private setupBoundaryNavigation(): void {
		if (!this.lightbox?.pswp) {
			return;
		}

		const pswp = this.lightbox.pswp;

		// Override the next() method to handle episode transitions
		const originalNext = pswp.next.bind(pswp);
		pswp.next = () => {
			const currentIndex = pswp.currIndex;
			const totalImages = pswp.getNumItems();

			// If at last image and next episode exists, transition
			if (
				currentIndex === totalImages - 1 &&
				this.config.navigation?.nextEpisodeId &&
				!this.isTransitioning
			) {
				this.transitionToNextEpisode();
			} else {
				originalNext();
			}
		};

		// Override the prev() method to handle episode transitions
		const originalPrev = pswp.prev.bind(pswp);
		pswp.prev = () => {
			const currentIndex = pswp.currIndex;

			// If at first image and prev episode exists, transition
			if (
				currentIndex === 0 &&
				this.config.navigation?.prevEpisodeId &&
				!this.isTransitioning
			) {
				this.transitionToPrevEpisode();
			} else {
				originalPrev();
			}
		};

		// Detect swipe attempts at boundaries
		// PhotoSwipe's gesture system prevents swiping beyond boundaries,
		// but we can detect the attempt and trigger episode transitions
		const swipeState = {
			startX: 0,
			startIndex: 0,
			startPanX: 0,
		};

		pswp.on('pointerDown', (e) => {
			if (this.isTransitioning) return;
			const event = e.originalEvent as PointerEvent | TouchEvent;
			if ('touches' in event && event.touches) {
				swipeState.startX = event.touches[0]?.clientX || 0;
			} else if ('clientX' in event) {
				swipeState.startX = event.clientX || 0;
			}
			swipeState.startIndex = pswp.currIndex;
			// Store the pan position at the start of the gesture
			swipeState.startPanX = pswp.currSlide?.pan?.x || 0;
		});

		pswp.on('pointerUp', (e) => {
			if (this.isTransitioning) return;

			const event = e.originalEvent as PointerEvent | TouchEvent;
			let swipeEndX = 0;
			if ('changedTouches' in event && event.changedTouches) {
				swipeEndX = event.changedTouches[0]?.clientX || 0;
			} else if ('clientX' in event) {
				swipeEndX = event.clientX || 0;
			}

			const swipeDelta = swipeState.startX - swipeEndX;
			const swipeThreshold = 50; // Minimum swipe distance in pixels

			// Get current slide
			const currSlide = pswp.currSlide;
			if (!currSlide) return;

			const currentIndex = pswp.currIndex;
			const totalImages = pswp.getNumItems();

			// Check if the pan position changed during the gesture
			const swipeEndPanX = currSlide.pan?.x || 0;
			const panChanged = Math.abs(swipeEndPanX - swipeState.startPanX) > 5; // 5px tolerance

			// Check if image is at pan boundary (can't pan further in swipe direction)
			const bounds = currSlide.bounds;
			let atPanBoundary = false;
			if (bounds) {
				const panTolerance = 5; // pixels
				if (swipeDelta > 0 && typeof bounds.max?.x === 'number') {
					// Swiping left (next) - check if at right edge of pan
					atPanBoundary = Math.abs(swipeEndPanX - bounds.max.x) < panTolerance;
				} else if (swipeDelta < 0 && typeof bounds.min?.x === 'number') {
					// Swiping right (prev) - check if at left edge of pan
					atPanBoundary = Math.abs(swipeEndPanX - bounds.min.x) < panTolerance;
				}
			}

			// Only trigger episode transitions if:
			// 1. We're still on the same slide (swipe was blocked by boundary)
			// 2. Either the pan didn't change (not zoomed/panning) OR we're at the pan boundary
			if (
				currentIndex === swipeState.startIndex &&
				Math.abs(swipeDelta) > swipeThreshold &&
				(!panChanged || atPanBoundary)
			) {
				// Swipe left (next) at last image
				if (
					swipeDelta > 0 &&
					currentIndex === totalImages - 1 &&
					this.config.navigation?.nextEpisodeId
				) {
					this.transitionToNextEpisode();
				}
				// Swipe right (prev) at first image
				else if (
					swipeDelta < 0 &&
					currentIndex === 0 &&
					this.config.navigation?.prevEpisodeId
				) {
					this.transitionToPrevEpisode();
				}
			}
		});
	}

	/**
	 * Handle episode boundary checking
	 * Detects when user reaches the last image and loads next episode
	 */
	private handleBoundaryCheck(): void {
		if (!this.lightbox?.pswp || !this.boundaryHandler || this.isTransitioning) {
			return;
		}

		const pswp = this.lightbox.pswp;
		const currentIndex = pswp.currIndex;
		const totalImages = pswp.getNumItems();

		// Check if we're at the last image
		if (currentIndex === totalImages - 1) {
			// Check if there's a next episode
			if (this.config.navigation?.nextEpisodeId) {
				// Prefetch next episode if not already cached
				if (
					!this.boundaryHandler.isCached(this.config.navigation.nextEpisodeId)
				) {
					this.boundaryHandler.fetchEpisode(
						this.config.navigation.nextEpisodeId,
						'next'
					);
				}
			}
		}
	}

	/**
	 * Transition to previous episode
	 * Called when user swipes backward from the first image
	 */
	private async transitionToPrevEpisode(): Promise<void> {
		if (
			!this.boundaryHandler ||
			!this.config.navigation?.prevEpisodeId ||
			this.isTransitioning
		) {
			return;
		}

		this.isTransitioning = true;

		try {
			const prevEpisodeData = await this.boundaryHandler.fetchEpisode(
				this.config.navigation.prevEpisodeId,
				'prev'
			);

			if (!prevEpisodeData) {
				// Error notification already shown by boundary handler
				// eslint-disable-next-line no-console
				console.error('Failed to load previous episode');
				return;
			}

			// Update current episode ID
			this.boundaryHandler.updateCurrentEpisodeId(prevEpisodeData.id);

			// Get episode defaults from response or fall back to current defaults
			const episodeDefaults: DefaultSettings = prevEpisodeData.episodeDefaults
				? {
						zoom: prevEpisodeData.episodeDefaults.zoom as ZoomValue,
						pan: prevEpisodeData.episodeDefaults.pan as PanValue,
					}
				: {
						zoom: this.config.episodeDefaults.zoom,
						pan: this.config.episodeDefaults.pan,
					};

			// Convert episode data to ImageData format
			const newImages: ImageData[] = prevEpisodeData.images.map((img) => ({
				id: img.id,
				url: img.url,
				width: img.width,
				height: img.height,
				zoom: episodeDefaults.zoom,
				pan: episodeDefaults.pan,
			}));

			// Update config with new images and navigation
			this.config.images = newImages;
			this.config.episodeId = prevEpisodeData.id;
			this.config.episodeDefaults = episodeDefaults;

			// Update navigation from the response
			if (prevEpisodeData.navigation) {
				this.config.navigation = {
					nextEpisodeId: prevEpisodeData.navigation.nextEpisodeId,
					prevEpisodeId: prevEpisodeData.navigation.prevEpisodeId,
				};
			}

			// Update browser URL and title
			this.updateBrowserURL(prevEpisodeData.url, prevEpisodeData.title);

			// Update page content
			this.updatePageContent(prevEpisodeData);

			// Reload the gallery with new images, opening at the last image
			this.reloadGallery(newImages, newImages.length - 1);

			// eslint-disable-next-line no-console
			console.log(
				`✅ Episode transition: "${prevEpisodeData.title}" (${newImages.length} image${newImages.length !== 1 ? 's' : ''})`
			);
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Failed to transition to previous episode:', error);
		} finally {
			this.isTransitioning = false;
		}
	}

	/**
	 * Transition to next episode
	 * Called when user swipes forward from the last image
	 */
	private async transitionToNextEpisode(): Promise<void> {
		if (
			!this.boundaryHandler ||
			!this.config.navigation?.nextEpisodeId ||
			this.isTransitioning
		) {
			return;
		}

		this.isTransitioning = true;

		try {
			const nextEpisodeData = await this.boundaryHandler.fetchEpisode(
				this.config.navigation.nextEpisodeId,
				'next'
			);

			if (!nextEpisodeData) {
				// Error notification already shown by boundary handler
				// eslint-disable-next-line no-console
				console.error('Failed to load next episode');
				return;
			}

			// Update current episode ID
			this.boundaryHandler.updateCurrentEpisodeId(nextEpisodeData.id);

			// Get episode defaults from response or fall back to current defaults
			const episodeDefaults: DefaultSettings = nextEpisodeData.episodeDefaults
				? {
						zoom: nextEpisodeData.episodeDefaults.zoom as ZoomValue,
						pan: nextEpisodeData.episodeDefaults.pan as PanValue,
					}
				: {
						zoom: this.config.episodeDefaults.zoom,
						pan: this.config.episodeDefaults.pan,
					};

			// Convert episode data to ImageData format
			const newImages: ImageData[] = nextEpisodeData.images.map((img) => ({
				id: img.id,
				url: img.url,
				width: img.width,
				height: img.height,
				zoom: episodeDefaults.zoom,
				pan: episodeDefaults.pan,
			}));

			// Update config with new images and navigation
			this.config.images = newImages;
			this.config.episodeId = nextEpisodeData.id;
			this.config.episodeDefaults = episodeDefaults;

			// Update navigation from the response
			if (nextEpisodeData.navigation) {
				this.config.navigation = {
					nextEpisodeId: nextEpisodeData.navigation.nextEpisodeId,
					prevEpisodeId: nextEpisodeData.navigation.prevEpisodeId,
				};
			}

			// Update browser URL and title
			this.updateBrowserURL(nextEpisodeData.url, nextEpisodeData.title);

			// Update page content
			this.updatePageContent(nextEpisodeData);

			// Reload the gallery with new images
			this.reloadGallery(newImages);

			// eslint-disable-next-line no-console
			console.log(
				`✅ Episode transition: "${nextEpisodeData.title}" (${newImages.length} image${newImages.length !== 1 ? 's' : ''})`
			);
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Failed to transition to next episode:', error);
		} finally {
			this.isTransitioning = false;
		}
	}

	/**
	 * Update browser URL and document title
	 * @param url   - New URL to set
	 * @param title - New page title
	 */
	private updateBrowserURL(url: string, title: string): void {
		if (!url) {
			return;
		}

		try {
			// Update browser URL without reloading the page
			window.history.pushState({ episodeUrl: url }, '', url);

			// Update document title
			document.title = title;

			// eslint-disable-next-line no-console
			console.log(`🔗 URL updated: ${url}`);
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Failed to update browser URL:', error);
		}
	}

	/**
	 * Update page content with new episode data
	 * @param episodeData - Episode data to display
	 */
	private updatePageContent(episodeData: EpisodeData): void {
		// Update title
		const titleElement = document.querySelector('.swipecomic-title');
		if (titleElement) {
			titleElement.textContent = episodeData.title;
		}

		// Update episode/chapter metadata
		const metaElement = document.querySelector('.swipecomic-episode-chapter');
		if (metaElement) {
			if (episodeData.episodeChapter) {
				metaElement.textContent = episodeData.episodeChapter;
				// Show the meta container if it was hidden
				const metaContainer = metaElement.closest('.swipecomic-meta');
				if (metaContainer) {
					(metaContainer as HTMLElement).style.display = '';
				}
			} else {
				// Hide the meta container if there's no episode/chapter info
				const metaContainer = metaElement.closest('.swipecomic-meta');
				if (metaContainer) {
					(metaContainer as HTMLElement).style.display = 'none';
				}
			}
		}

		// Update content (footer)
		const footerElement = document.querySelector('.swipecomic-footer');
		if (footerElement && episodeData.content) {
			footerElement.innerHTML = episodeData.content;
			(footerElement as HTMLElement).style.display = '';
		} else if (footerElement) {
			// Hide footer if there's no content
			(footerElement as HTMLElement).style.display = 'none';
		}

		// Update navigation links
		const prevLink = document.querySelector(
			'.swipecomic-navigation .prev-episode'
		) as HTMLAnchorElement;
		const nextLink = document.querySelector(
			'.swipecomic-navigation .next-episode'
		) as HTMLAnchorElement;

		if (prevLink) {
			if (episodeData.navigation?.prevEpisodeId) {
				// We don't have the URL for the prev episode, so we'll just show/hide it
				prevLink.style.display = '';
			} else {
				prevLink.style.display = 'none';
			}
		}

		if (nextLink) {
			if (episodeData.navigation?.nextEpisodeId) {
				// We don't have the URL for the next episode, so we'll just show/hide it
				nextLink.style.display = '';
			} else {
				nextLink.style.display = 'none';
			}
		}

		// eslint-disable-next-line no-console
		console.log('📄 Page content updated');
	}

	/**
	 * Reload gallery with new images
	 * @param images     - New images to load
	 * @param startIndex - Index to open at (default: 0)
	 */
	private reloadGallery(images: ImageData[], startIndex = 0): void {
		if (!this.lightbox?.pswp) {
			return;
		}

		const pswp = this.lightbox.pswp;

		// Define the reload logic to be executed after PhotoSwipe is fully destroyed
		const reload = () => {
			// Update DOM with new images
			this.updateGalleryDOM(images);

			// Small delay to ensure PhotoSwipe is fully cleaned up
			// This is necessary because PhotoSwipe needs time to remove event listeners
			// and clean up its internal state before we can reinitialize
			setTimeout(() => {
				if (this.lightbox) {
					this.lightbox.loadAndOpen(startIndex);
				}
			}, 50);

			// Remove the event listener to avoid it being called again
			pswp.off('destroy', reload);
		};

		// Listen for the 'destroy' event (fires after close is complete)
		pswp.on('destroy', reload);

		// Close the current instance
		pswp.close();
	}

	/**
	 * Update gallery data with new images
	 * No DOM manipulation needed - PhotoSwipe uses dataSource directly
	 * @param images - New images to display
	 */
	private updateGalleryDOM(images: ImageData[]): void {
		// Update the lightbox dataSource with new images
		if (this.lightbox) {
			this.lightbox.options.dataSource = images.map((img) => ({
				src: img.url,
				width: img.width,
				height: img.height,
				alt: '',
			}));
		}
	}

	/**
	 * Add custom loading overlay to PhotoSwipe container
	 * @param pswp - PhotoSwipe instance
	 */
	private addLoadingOverlay(pswp: PhotoSwipe): void {
		if (!pswp.element) return;

		const overlay = document.createElement('div');
		overlay.className = 'pswp__loading-overlay';

		const spinner = document.createElement('div');
		spinner.className = 'pswp__loading-spinner';
		overlay.appendChild(spinner);

		pswp.element.appendChild(overlay);
	}

	/**
	 * Show loading overlay
	 * @param pswp - PhotoSwipe instance
	 */
	private showLoadingOverlay(pswp: PhotoSwipe): void {
		const overlay = pswp.element?.querySelector(
			'.pswp__loading-overlay'
		) as HTMLElement;
		if (overlay) {
			overlay.classList.remove('pswp__loading-overlay--hidden');
		}
	}

	/**
	 * Hide loading overlay
	 * @param pswp - PhotoSwipe instance
	 */
	private hideLoadingOverlay(pswp: PhotoSwipe): void {
		const overlay = pswp.element?.querySelector(
			'.pswp__loading-overlay'
		) as HTMLElement;
		if (overlay) {
			overlay.classList.add('pswp__loading-overlay--hidden');
		}
	}

	/**
	 * Show error message for failed image load
	 * @param imageNumber - The image number that failed to load
	 */
	private showImageLoadError(imageNumber: number): void {
		showErrorNotification(
			`Failed to load image ${imageNumber}. Please check your connection and try again.`,
			5000
		);
	}

	/**
	 * Hide top bar tools (close, zoom, counter)
	 * Called when showLightboxTools setting is false
	 */
	private hideTopBarTools(): void {
		if (!this.lightbox?.pswp) return;

		const pswp = this.lightbox.pswp;

		// Add a class to hide the top bar tools
		pswp.element?.classList.add('pswp--hide-tools');
	}

	/**
	 * Destroy the lightbox instance
	 */
	destroy(): void {
		// Clean up gallery event listeners to prevent memory leaks
		const galleryElement = document.querySelector(
			this.config.gallerySelector
		) as HTMLElement;
		if (galleryElement) {
			if (this.galleryClickHandler) {
				galleryElement.removeEventListener('click', this.galleryClickHandler);
			}
			if (this.galleryKeyHandler) {
				galleryElement.removeEventListener('keydown', this.galleryKeyHandler);
			}
		}

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

		// Detect mobile/touch devices (from PoC)
		const isMobile =
			('ontouchstart' in window || navigator.maxTouchPoints > 0) &&
			/Mobi/i.test(navigator.userAgent);

		// Build viewer config
		const config: ViewerConfig = {
			gallerySelector: '#swipecomic-gallery',
			globalDefaults: data.globalDefaults || { zoom: 'fit', pan: 'center' },
			episodeDefaults: data.episodeDefaults || { zoom: 'fit', pan: 'center' },
			images: data.images,
			isMobile,
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
			navigation: data.navigation || undefined,
			ajaxUrl: data.ajaxUrl || undefined,
			nonce: data.nonce || undefined,
			episodeId: data.episodeId || undefined,
			showLightboxTools:
				data.showLightboxTools !== undefined
					? Boolean(data.showLightboxTools)
					: true,
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
