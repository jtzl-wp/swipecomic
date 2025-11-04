/**
 * Drag Hint Controller Module
 *
 * Displays drag hints for wide images that extend beyond viewport
 * Shows hint once per slide, with unconditional display for first slide
 */

export interface SlideData {
	width: number;
	height: number;
	index: number;
}

export interface ViewportSize {
	width: number;
	height: number;
}

export class DragHintController {
	private shownHints: Set<number> = new Set();
	private hintElement: HTMLElement | null = null;
	private hintDuration: number = 2000; // 2 seconds
	private hintTimeout: number | null = null;

	constructor(hintDuration: number = 2000) {
		this.hintDuration = hintDuration;
	}

	/**
	 * Check if image is wide (extends beyond viewport at vFill zoom)
	 * @param slideData    - Slide dimensions and index
	 * @param viewportSize - Current viewport dimensions
	 */
	isWideImage(slideData: SlideData, viewportSize: ViewportSize): boolean {
		// Calculate width at vFill (vertical fill) zoom
		// vFill means image height matches viewport height
		const vFillZoom = viewportSize.height / slideData.height;
		const widthAtVFill = slideData.width * vFillZoom;

		// Image is "wide" if it extends beyond viewport at vFill
		return widthAtVFill > viewportSize.width;
	}

	/**
	 * Check if hint should be shown for this slide
	 * @param slideIndex   - Index of the current slide
	 * @param slideData    - Slide dimensions and index
	 * @param viewportSize - Current viewport dimensions
	 */
	shouldShowHint(
		slideIndex: number,
		slideData: SlideData,
		viewportSize: ViewportSize
	): boolean {
		// Always show for first slide (index 0)
		if (slideIndex === 0) {
			return this.isWideImage(slideData, viewportSize);
		}

		// For other slides, only show if not shown before and image is wide
		if (this.shownHints.has(slideIndex)) {
			return false;
		}

		return this.isWideImage(slideData, viewportSize);
	}

	/**
	 * Display the drag hint
	 * @param container - Container element to append hint to
	 */
	showHint(container: HTMLElement): void {
		// Remove existing hint if any
		this.hideHint();

		// Create hint element
		this.hintElement = document.createElement('div');
		this.hintElement.className = 'swipecomic-drag-hint';
		this.hintElement.innerHTML = `
			<div class="swipecomic-drag-hint__content">
				<svg class="swipecomic-drag-hint__icon" width="48" height="48" viewBox="0 0 48 48">
					<path d="M20 12l-8 8 8 8M28 12l8 8-8 8" stroke="currentColor" stroke-width="3" fill="none"/>
				</svg>
				<span class="swipecomic-drag-hint__text">Drag to pan</span>
			</div>
		`;

		container.appendChild(this.hintElement);

		// Auto-hide after duration
		if (this.hintTimeout) {
			clearTimeout(this.hintTimeout);
		}

		this.hintTimeout = window.setTimeout(() => {
			this.hideHint();
		}, this.hintDuration);
	}

	/**
	 * Hide the drag hint
	 */
	hideHint(): void {
		if (this.hintElement && this.hintElement.parentNode) {
			this.hintElement.parentNode.removeChild(this.hintElement);
			this.hintElement = null;
		}

		if (this.hintTimeout) {
			clearTimeout(this.hintTimeout);
			this.hintTimeout = null;
		}
	}

	/**
	 * Mark hint as shown for a slide
	 * @param slideIndex - Index of the slide
	 */
	markHintShown(slideIndex: number): void {
		this.shownHints.add(slideIndex);
	}

	/**
	 * Reset hint tracking (useful for episode transitions)
	 */
	reset(): void {
		this.shownHints.clear();
		this.hideHint();
	}

	/**
	 * Handle slide change event
	 * @param slideIndex   - Index of the new slide
	 * @param slideData    - Slide dimensions and index
	 * @param viewportSize - Current viewport dimensions
	 * @param container    - Container element to show hint in
	 */
	handleSlideChange(
		slideIndex: number,
		slideData: SlideData,
		viewportSize: ViewportSize,
		container: HTMLElement
	): void {
		if (this.shouldShowHint(slideIndex, slideData, viewportSize)) {
			this.showHint(container);
			this.markHintShown(slideIndex);
		}
	}

	/**
	 * Get the set of slides that have shown hints
	 */
	getShownHints(): Set<number> {
		return new Set(this.shownHints);
	}
}
