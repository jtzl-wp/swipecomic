/**
 * Logo Overlay Controller Module
 *
 * Displays series logo at configured position with responsive sizing
 * Ensures pointer-events: none to avoid blocking gestures
 */

export type LogoPosition =
	| 'upper-left'
	| 'upper-right'
	| 'lower-left'
	| 'lower-right';

export interface LogoConfig {
	url: string;
	position: LogoPosition;
	alt?: string;
	linkUrl?: string; // URL to navigate to when logo is clicked
}

export interface ViewportSize {
	width: number;
	height: number;
}

export class LogoOverlayController {
	private logoElement: HTMLElement | null = null;
	private config: LogoConfig;
	private maxWidthPercent: number = 15; // 15% of viewport width
	private maxHeightPercent: number = 10; // 10% of viewport height

	constructor(config: LogoConfig) {
		this.config = config;
	}

	/**
	 * Calculate responsive logo size based on viewport
	 *
	 * @param viewportSize - Current viewport dimensions
	 */
	calculateLogoSize(viewportSize: ViewportSize): {
		maxWidth: number;
		maxHeight: number;
	} {
		return {
			maxWidth: (viewportSize.width * this.maxWidthPercent) / 100,
			maxHeight: (viewportSize.height * this.maxHeightPercent) / 100,
		};
	}

	/**
	 * Get CSS position styles for logo
	 *
	 * @param position - Logo position
	 */
	getPositionStyles(position: LogoPosition): {
		top?: string;
		bottom?: string;
		left?: string;
		right?: string;
	} {
		const padding = '20px';

		switch (position) {
			case 'upper-left':
				return { top: padding, left: padding };
			case 'upper-right':
				return { top: padding, right: padding };
			case 'lower-left':
				return { bottom: padding, left: padding };
			case 'lower-right':
				return { bottom: padding, right: padding };
			default:
				return { top: padding, left: padding };
		}
	}

	/**
	 * Create and render logo overlay
	 *
	 * @param container    - Container element to append logo to
	 * @param viewportSize - Current viewport dimensions
	 */
	render(container: HTMLElement, viewportSize: ViewportSize): void {
		// Remove existing logo if any
		this.remove();

		// Calculate responsive size
		const size = this.calculateLogoSize(viewportSize);
		const positionStyles = this.getPositionStyles(this.config.position);

		// Create logo element
		this.logoElement = document.createElement('div');
		this.logoElement.className = 'swipecomic-logo-overlay';

		// Apply styles
		Object.assign(this.logoElement.style, {
			position: 'absolute',
			zIndex: '10',
			pointerEvents: this.config.linkUrl ? 'auto' : 'none', // Enable pointer events if clickable
			maxWidth: `${size.maxWidth}px`,
			maxHeight: `${size.maxHeight}px`,
			cursor: this.config.linkUrl ? 'pointer' : 'default',
			...positionStyles,
		});

		// Create image element (or link if linkUrl is provided)
		if (this.config.linkUrl) {
			// Create clickable link
			const link = document.createElement('a');
			link.href = this.config.linkUrl;
			link.style.display = 'block';
			link.style.textDecoration = 'none';

			const img = document.createElement('img');
			img.src = this.config.url;
			img.alt = this.config.alt || 'Series logo';
			img.style.width = '100%';
			img.style.height = 'auto';
			img.style.display = 'block';

			link.appendChild(img);
			this.logoElement.appendChild(link);
		} else {
			// Create non-clickable image
			const img = document.createElement('img');
			img.src = this.config.url;
			img.alt = this.config.alt || 'Series logo';
			img.style.width = '100%';
			img.style.height = 'auto';
			img.style.display = 'block';
			img.style.pointerEvents = 'none';

			this.logoElement.appendChild(img);
		}

		container.appendChild(this.logoElement);
	}

	/**
	 * Remove logo overlay
	 */
	remove(): void {
		if (this.logoElement && this.logoElement.parentNode) {
			this.logoElement.parentNode.removeChild(this.logoElement);
			this.logoElement = null;
		}
	}

	/**
	 * Update logo position
	 *
	 * @param position - New logo position
	 */
	updatePosition(position: LogoPosition): void {
		this.config.position = position;

		if (this.logoElement) {
			const positionStyles = this.getPositionStyles(position);

			// Clear all position properties first
			this.logoElement.style.top = '';
			this.logoElement.style.bottom = '';
			this.logoElement.style.left = '';
			this.logoElement.style.right = '';

			// Apply new position
			Object.assign(this.logoElement.style, positionStyles);
		}
	}

	/**
	 * Update logo size based on new viewport
	 *
	 * @param viewportSize - New viewport dimensions
	 */
	updateSize(viewportSize: ViewportSize): void {
		if (this.logoElement) {
			const size = this.calculateLogoSize(viewportSize);
			this.logoElement.style.maxWidth = `${size.maxWidth}px`;
			this.logoElement.style.maxHeight = `${size.maxHeight}px`;
		}
	}

	/**
	 * Toggle logo visibility
	 *
	 * @param visible - Whether logo should be visible
	 */
	setVisibility(visible: boolean): void {
		if (this.logoElement) {
			this.logoElement.style.display = visible ? 'block' : 'none';
		}
	}

	/**
	 * Check if logo is currently rendered
	 */
	isRendered(): boolean {
		return this.logoElement !== null && this.logoElement.parentNode !== null;
	}

	/**
	 * Get current logo element
	 */
	getElement(): HTMLElement | null {
		return this.logoElement;
	}
}
