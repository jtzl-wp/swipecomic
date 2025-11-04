/**
 * Settings Resolver Module
 *
 * Resolves zoom and pan settings with proper hierarchy:
 * per-image override > episode default > global default
 */

export type ZoomValue = 'fit' | 'vFill' | number;
export type PanValue = 'left' | 'right' | 'center' | string;

export interface PanCoordinates {
	x: number;
	y: number;
}

export interface DefaultSettings {
	zoom: ZoomValue;
	pan: PanValue;
}

export interface ImageData {
	id: number;
	url: string;
	width: number;
	height: number;
	zoom?: ZoomValue;
	pan?: PanValue;
	zoom_override?: ZoomValue;
	pan_override?: PanValue;
}

export class SettingsResolver {
	private globalDefaults: DefaultSettings;
	private episodeDefaults: DefaultSettings;

	constructor(
		globalDefaults: DefaultSettings,
		episodeDefaults: DefaultSettings
	) {
		this.globalDefaults = globalDefaults;
		this.episodeDefaults = episodeDefaults;
	}

	/**
	 * Resolve zoom value with hierarchy: override > episode > global
	 * @param imageData
	 */
	resolveZoom(imageData: ImageData): ZoomValue {
		const candidates: Array<ZoomValue | undefined> = [
			imageData.zoom_override,
			this.episodeDefaults.zoom,
			this.globalDefaults.zoom,
			'fit', // Hardcoded fallback
		];

		for (const value of candidates) {
			if (this.isValidZoom(value)) {
				return value;
			}
		}

		// eslint-disable-next-line no-console
		console.warn('All zoom values invalid, using fallback');
		return 'fit';
	}

	/**
	 * Resolve pan value with hierarchy: override > episode > global
	 * @param imageData
	 */
	resolvePan(imageData: ImageData): PanValue {
		const candidates: Array<PanValue | undefined> = [
			imageData.pan_override,
			this.episodeDefaults.pan,
			this.globalDefaults.pan,
			'center', // Hardcoded fallback
		];

		for (const value of candidates) {
			if (this.isValidPan(value)) {
				return value;
			}
		}

		// eslint-disable-next-line no-console
		console.warn('All pan values invalid, using fallback');
		return 'center';
	}

	/**
	 * Parse zoom value to PhotoSwipe-compatible format
	 * @param zoomString
	 */
	parseZoomValue(zoomString: ZoomValue): number | 'fit' | 'vFill' {
		if (zoomString === 'fit' || zoomString === 'vFill') {
			return zoomString;
		}

		if (typeof zoomString === 'number') {
			if (zoomString <= 0) {
				// eslint-disable-next-line no-console
				console.warn(`Invalid zoom value: ${zoomString}, using 'fit'`);
				return 'fit';
			}
			return zoomString;
		}

		// Try to parse as numeric string (e.g., "150" -> 1.5)
		const numericValue = parseFloat(String(zoomString));
		if (!isNaN(numericValue) && numericValue > 0) {
			// Convert percentage to decimal (150 -> 1.5)
			return numericValue >= 10 ? numericValue / 100 : numericValue;
		}

		// eslint-disable-next-line no-console
		console.warn(`Invalid zoom value: ${zoomString}, using 'fit'`);
		return 'fit';
	}

	/**
	 * Parse pan value to coordinates or named position
	 * @param panString
	 */
	parsePanValue(
		panString: PanValue
	): PanCoordinates | 'left' | 'right' | 'center' {
		if (
			panString === 'left' ||
			panString === 'right' ||
			panString === 'center'
		) {
			return panString;
		}

		// Try to parse as "x,y" coordinates
		if (typeof panString === 'string' && panString.includes(',')) {
			const parts = panString.split(',').map((p) => parseFloat(p.trim()));
			if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
				return { x: parts[0], y: parts[1] };
			}
		}

		// eslint-disable-next-line no-console
		console.warn(`Invalid pan value: ${panString}, using 'center'`);
		return 'center';
	}

	/**
	 * Type guard for zoom values
	 * @param value
	 */
	private isValidZoom(value: unknown): value is ZoomValue {
		if (value === undefined || value === null) {
			return false;
		}

		if (value === 'fit' || value === 'vFill') {
			return true;
		}

		if (typeof value === 'number' && value > 0) {
			return true;
		}

		// Check if it's a numeric string
		if (typeof value === 'string') {
			const numericValue = parseFloat(value);
			return !isNaN(numericValue) && numericValue > 0;
		}

		return false;
	}

	/**
	 * Type guard for pan values
	 * @param value
	 */
	private isValidPan(value: unknown): value is PanValue {
		if (value === undefined || value === null) {
			return false;
		}

		if (value === 'left' || value === 'right' || value === 'center') {
			return true;
		}

		// Check if it's a valid "x,y" coordinate string
		if (typeof value === 'string' && value.includes(',')) {
			const parts = value.split(',').map((p) => parseFloat(p.trim()));
			return parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1]);
		}

		return false;
	}
}
