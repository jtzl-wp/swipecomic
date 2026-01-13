/**
 * SwipeComic Settings Page JavaScript
 *
 * Handles URL prefix toggle and preview functionality.
 *
 * @since 1.0.4
 */

/* global swipecomicSettingsData */

(function () {
	'use strict';

	const usePrefixCheckbox = document.getElementById(
		'swipecomic_use_url_prefix'
	);
	const prefixInput = document.getElementById('swipecomic_url_prefix');

	if (!usePrefixCheckbox || !prefixInput) return;

	// Find the table row containing the prefix field
	const prefixFieldRow = prefixInput.closest('tr');

	if (!prefixFieldRow) return;

	const urlPreview = document.querySelector('.swipecomic-url-preview');

	/**
	 * Toggle prefix field row visibility
	 */
	function togglePrefixField() {
		if (usePrefixCheckbox.checked) {
			prefixFieldRow.style.display = '';
		} else {
			prefixFieldRow.style.display = 'none';
		}
		updatePreview();
	}

	/**
	 * Update URL preview based on current settings
	 */
	function updatePreview() {
		if (!urlPreview) return;

		const usePrefix = usePrefixCheckbox.checked;
		const prefix = prefixInput.value || 'comic';
		const homeUrl = swipecomicSettingsData.homeUrl;

		let seriesUrl, episodeUrl, orphanUrl;

		if (usePrefix) {
			seriesUrl = homeUrl + prefix + '/my-series/';
			episodeUrl = homeUrl + prefix + '/my-series/episode-1/';
			orphanUrl = homeUrl + prefix + '/episode-without-series/';
		} else {
			seriesUrl = homeUrl + 'my-series/';
			episodeUrl = homeUrl + 'my-series/episode-1/';
			orphanUrl = homeUrl + 'episode-without-series/';
		}

		const seriesLi = urlPreview.querySelector('li:nth-child(1) code');
		const episodeLi = urlPreview.querySelector('li:nth-child(2) code');
		const orphanLi = urlPreview.querySelector('li:nth-child(3) code');

		if (seriesLi) seriesLi.textContent = seriesUrl;
		if (episodeLi) episodeLi.textContent = episodeUrl;
		if (orphanLi) orphanLi.textContent = orphanUrl;

		// Update warning visibility
		const warning = urlPreview.querySelector('p[style*="color"]');
		if (warning) {
			warning.style.display = usePrefix ? 'none' : '';
		}
	}

	// Attach event listeners
	usePrefixCheckbox.addEventListener('change', togglePrefixField);
	if (prefixInput) {
		prefixInput.addEventListener('input', updatePreview);
	}

	// Initial state
	togglePrefixField();
})();
