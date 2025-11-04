/**
 * Episode Boundary Handler Module
 *
 * Handles transitions between episodes with AJAX fetching and caching
 * Provides seamless navigation across episode boundaries
 */

export interface EpisodeData {
	id: number;
	title: string;
	images: Array<{
		id: number;
		url: string;
		width: number;
		height: number;
	}>;
}

export interface AjaxResponse {
	success: boolean;
	data?: EpisodeData;
	error?: string;
}

export interface BoundaryConfig {
	ajaxUrl: string;
	nonce: string;
	currentEpisodeId: number;
}

export class EpisodeBoundaryHandler {
	private cache: Map<number, EpisodeData> = new Map();
	private config: BoundaryConfig;
	private pendingRequests: Map<number, Promise<EpisodeData | null>> = new Map();

	constructor(config: BoundaryConfig) {
		this.config = config;
	}

	/**
	 * Fetch adjacent episode data via AJAX
	 *
	 * @param episodeId - ID of the episode to fetch
	 * @param direction - Direction of navigation ('next' or 'prev')
	 */
	async fetchEpisode(
		episodeId: number,
		direction: 'next' | 'prev'
	): Promise<EpisodeData | null> {
		// Check cache first
		if (this.cache.has(episodeId)) {
			return this.cache.get(episodeId) || null;
		}

		// Check if request is already pending
		if (this.pendingRequests.has(episodeId)) {
			return this.pendingRequests.get(episodeId) || null;
		}

		// Create new request
		const requestPromise = this.performAjaxRequest(episodeId, direction);
		this.pendingRequests.set(episodeId, requestPromise);

		try {
			const data = await requestPromise;
			if (data) {
				// Use the ID from the fetched data as the cache key.
				this.cache.set(data.id, data);
				// Ensure the fetched episode is the one that was requested.
				if (data.id === episodeId) {
					return data;
				}
				// If not, it might be a response to a concurrent request.
				// Check cache again for the correct episode.
				return this.cache.get(episodeId) || null;
			}
			return null;
		} finally {
			this.pendingRequests.delete(episodeId);
		}
	}

	/**
	 * Perform AJAX request to fetch episode data
	 *
	 * @param _episodeId - ID of the episode to fetch (unused in request, used for caching)
	 * @param direction  - Direction of navigation
	 */
	private async performAjaxRequest(
		_episodeId: number,
		direction: 'next' | 'prev'
	): Promise<EpisodeData | null> {
		try {
			const formData = new FormData();
			formData.append('action', 'swipecomic_get_adjacent_episode');
			formData.append('episode_id', this.config.currentEpisodeId.toString());
			formData.append('direction', direction);
			formData.append('nonce', this.config.nonce);

			const response = await fetch(this.config.ajaxUrl, {
				method: 'POST',
				body: formData,
			});

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const result: AjaxResponse = await response.json();

			if (!result.success || !result.data) {
				throw new Error(result.error || 'Failed to fetch episode data');
			}

			return result.data;
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Failed to fetch episode:', error);
			return null;
		}
	}

	/**
	 * Detect if at episode boundary
	 *
	 * @param currentIndex - Current image index
	 * @param totalImages  - Total number of images in current episode
	 * @param direction    - Direction of navigation
	 */
	isAtBoundary(
		currentIndex: number,
		totalImages: number,
		direction: 'next' | 'prev'
	): boolean {
		if (direction === 'next') {
			return currentIndex === totalImages - 1;
		}
		return currentIndex === 0;
	}

	/**
	 * Get cached episode data
	 *
	 * @param episodeId - ID of the episode
	 */
	getCachedEpisode(episodeId: number): EpisodeData | null {
		return this.cache.get(episodeId) || null;
	}

	/**
	 * Check if episode is cached
	 *
	 * @param episodeId - ID of the episode
	 */
	isCached(episodeId: number): boolean {
		return this.cache.has(episodeId);
	}

	/**
	 * Clear cache
	 */
	clearCache(): void {
		this.cache.clear();
	}

	/**
	 * Get cache size
	 */
	getCacheSize(): number {
		return this.cache.size;
	}

	/**
	 * Prefetch adjacent episodes
	 *
	 * @param nextEpisodeId - ID of next episode (if exists)
	 * @param prevEpisodeId - ID of previous episode (if exists)
	 */
	async prefetchAdjacentEpisodes(
		nextEpisodeId?: number,
		prevEpisodeId?: number
	): Promise<void> {
		const promises: Promise<EpisodeData | null>[] = [];

		if (nextEpisodeId !== undefined && !this.isCached(nextEpisodeId)) {
			promises.push(this.fetchEpisode(nextEpisodeId, 'next'));
		}

		if (prevEpisodeId !== undefined && !this.isCached(prevEpisodeId)) {
			promises.push(this.fetchEpisode(prevEpisodeId, 'prev'));
		}

		await Promise.all(promises);
	}

	/**
	 * Handle episode transition
	 *
	 * @param targetEpisodeId - ID of the episode to transition to
	 * @param direction       - Direction of transition
	 * @param onTransition    - Callback when transition occurs
	 */
	async handleTransition(
		targetEpisodeId: number,
		direction: 'next' | 'prev',
		onTransition: (episodeData: EpisodeData) => void
	): Promise<boolean> {
		const episodeData = await this.fetchEpisode(targetEpisodeId, direction);

		if (episodeData) {
			onTransition(episodeData);
			return true;
		}

		return false;
	}

	/**
	 * Get current episode ID
	 */
	getCurrentEpisodeId(): number {
		return this.config.currentEpisodeId;
	}

	/**
	 * Update current episode ID
	 *
	 * @param episodeId - New episode ID
	 */
	updateCurrentEpisodeId(episodeId: number): void {
		this.config.currentEpisodeId = episodeId;
	}
}
