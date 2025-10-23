/**
 * SwipeComic Admin JavaScript
 *
 * @since 1.0.0
 * @param {Object} $ jQuery object
 */

/* global jQuery, swipecomicAdmin */

(function ($) {
	'use strict';

	/**
	 * Episode Images Gallery Manager
	 */
	const EpisodeImagesGallery = {
		/**
		 * Media frame instance
		 */
		frame: null,

		/**
		 * Images data array
		 */
		imagesData: [],

		/**
		 * Initialize the gallery
		 */
		init() {
			this.loadImagesData();
			this.bindEvents();
			this.initSortable();
		},

		/**
		 * Load images data from hidden input
		 */
		loadImagesData() {
			const dataInput = $('#swipecomic-images-data');
			if (dataInput.length && dataInput.val()) {
				try {
					this.imagesData = JSON.parse(dataInput.val());
				} catch (e) {
					this.imagesData = [];
				}
			}
		},

		/**
		 * Save images data to hidden input
		 */
		saveImagesData() {
			$('#swipecomic-images-data').val(JSON.stringify(this.imagesData));
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Upload images button
			$('.swipecomic-upload-images').on('click', (e) => {
				e.preventDefault();
				this.openMediaUploader();
			});

			// Remove image button
			$(document).on('click', '.swipecomic-image-remove', (e) => {
				e.preventDefault();
				this.removeImage($(e.currentTarget).closest('.swipecomic-image-item'));
			});

			// Image settings button
			$(document).on('click', '.swipecomic-image-settings', (e) => {
				e.preventDefault();
				this.openImageSettings(
					$(e.currentTarget).closest('.swipecomic-image-item')
				);
			});
		},

		/**
		 * Initialize jQuery UI Sortable
		 */
		initSortable() {
			const grid = $('#swipecomic-images-grid');

			grid.sortable({
				items: '.swipecomic-image-item',
				cursor: 'move',
				opacity: 0.7,
				placeholder: 'swipecomic-image-placeholder',
				update: () => {
					this.updateImageOrder();
				},
			});
		},

		/**
		 * Open WordPress media uploader
		 */
		openMediaUploader() {
			// Create media frame if it doesn't exist
			if (!this.frame) {
				this.frame = wp.media({
					title: swipecomicAdmin.uploadButtonTitle,
					button: {
						text: swipecomicAdmin.uploadButtonText,
					},
					multiple: true,
					library: {
						type: 'image',
					},
				});

				// Handle image selection
				this.frame.on('select', () => {
					const selection = this.frame.state().get('selection');
					selection.each((attachment) => {
						this.addImage(attachment.toJSON());
					});
				});
			}

			// Open the media frame
			this.frame.open();
		},

		/**
		 * Add image to gallery
		 *
		 * @param {Object} attachment Attachment data
		 */
		addImage(attachment) {
			// Check if image already exists
			const exists = this.imagesData.some((img) => img.id === attachment.id);
			if (exists) {
				return;
			}

			const imageData = {
				id: attachment.id,
				order: this.imagesData.length,
			};

			this.imagesData.push(imageData);
			this.renderImage(attachment, this.imagesData.length - 1);
			this.saveImagesData();
		},

		/**
		 * Render image item
		 *
		 * @param {Object} attachment Attachment data
		 * @param {number} index      Image index
		 */
		renderImage(attachment, index) {
			const thumbnailUrl = attachment.sizes?.thumbnail?.url || attachment.url;
			const imageData = this.imagesData[index];
			const hasOverrides =
				(imageData.zoom_override && imageData.zoom_override !== '') ||
				(imageData.pan_override && imageData.pan_override !== '');

			const overrideBadge = hasOverrides
				? '<span class="swipecomic-override-badge" title="Has custom settings"><span class="dashicons dashicons-admin-generic"></span></span>'
				: '';

			// Escape HTML to prevent XSS
			const escapeHtml = (str) => {
				const div = document.createElement('div');
				div.textContent = str;
				return div.innerHTML;
			};

			const alt = escapeHtml(attachment.alt || '');

			const html = `
				<div class="swipecomic-image-item" data-image-id="${attachment.id}" data-index="${index}">
					<div class="swipecomic-image-preview">
						<img src="${thumbnailUrl}" alt="${alt}" />
						${overrideBadge}
					</div>
					<div class="swipecomic-image-actions">
						<button type="button" class="button swipecomic-image-settings" title="Image Settings">
							<span class="dashicons dashicons-admin-generic"></span>
						</button>
						<button type="button" class="button swipecomic-image-remove" title="Remove Image">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
				</div>
			`;

			$('#swipecomic-images-grid').append(html);
		},

		/**
		 * Remove image from gallery
		 *
		 * @param {Object} $item Image item element
		 */
		removeImage($item) {
			// eslint-disable-next-line no-alert
			if (!confirm(swipecomicAdmin.removeConfirm)) {
				return;
			}

			const index = parseInt($item.data('index'), 10);

			// Remove from data array
			this.imagesData.splice(index, 1);

			// Remove from DOM
			$item.remove();

			// Update indices
			this.updateImageOrder();
		},

		/**
		 * Update image order after drag-and-drop
		 */
		updateImageOrder() {
			const newOrder = [];

			$('#swipecomic-images-grid .swipecomic-image-item').each(
				(index, element) => {
					const $item = $(element);
					const imageId = parseInt($item.data('image-id'), 10);

					// Update data-index attribute
					$item.attr('data-index', index);

					// Find image data and update order
					const imageData = this.imagesData.find((img) => img.id === imageId);
					if (imageData) {
						imageData.order = index;
						newOrder.push(imageData);
					}
				}
			);

			this.imagesData = newOrder;
			this.saveImagesData();
		},

		/**
		 * Open image settings modal
		 *
		 * @param {Object} $item Image item element
		 */
		openImageSettings($item) {
			const index = parseInt($item.data('index'), 10);
			const imageData = this.imagesData[index];
			const imageId = imageData.id;

			ImageSettingsModal.open(imageId, index, imageData);
		},
	};

	/**
	 * Image Settings Modal Manager
	 */
	const ImageSettingsModal = {
		/**
		 * Current image index
		 */
		currentIndex: null,

		/**
		 * Current image ID
		 */
		currentImageId: null,

		/**
		 * Open the modal
		 *
		 * @param {number} imageId   Image attachment ID
		 * @param {number} index     Image index
		 * @param {Object} imageData Current image data
		 */
		open(imageId, index, imageData) {
			this.currentIndex = index;
			this.currentImageId = imageId;

			// Create modal HTML
			const modalHtml = this.createModalHtml(imageId, imageData);
			$('body').append(modalHtml);

			// Bind events
			this.bindModalEvents();

			// Initialize conditional fields
			this.updateConditionalFields();
		},

		/**
		 * Create modal HTML
		 *
		 * @param {number} imageId   Image attachment ID
		 * @param {Object} imageData Current image data
		 * @return {string} Modal HTML
		 */
		createModalHtml(imageId, imageData) {
			const zoomOverride = imageData.zoom_override || '';
			const panOverride = imageData.pan_override || '';

			// Parse zoom value
			let zoomType = 'inherit';
			let zoomCustom = '';
			if (zoomOverride) {
				if (zoomOverride === 'fit' || zoomOverride === 'vFill') {
					zoomType = zoomOverride;
				} else {
					zoomType = 'custom';
					zoomCustom = zoomOverride;
				}
			}

			// Parse pan value
			let panType = 'inherit';
			let panCustomX = '';
			let panCustomY = '';
			if (panOverride) {
				if (['left', 'right', 'center'].includes(panOverride)) {
					panType = panOverride;
				} else {
					panType = 'custom';
					const coords = panOverride.split(',');
					panCustomX = coords[0] || '';
					panCustomY = coords[1] || '';
				}
			}

			return `
				<div class="swipecomic-modal-overlay" id="swipecomic-settings-modal">
					<div class="swipecomic-modal">
						<div class="swipecomic-modal-header">
							<h2>Image Settings</h2>
						</div>
						<div class="swipecomic-modal-body">
							<div class="swipecomic-modal-field">
								<label><strong>Zoom Level</strong></label>
								<p class="description">Override the default zoom level for this image.</p>
								<select id="swipecomic-zoom-type" class="widefat">
									<option value="inherit" ${zoomType === 'inherit' ? 'selected' : ''}>Use episode default</option>
									<option value="fit" ${zoomType === 'fit' ? 'selected' : ''}>Fit</option>
									<option value="vFill" ${zoomType === 'vFill' ? 'selected' : ''}>vFill</option>
									<option value="custom" ${zoomType === 'custom' ? 'selected' : ''}>Custom</option>
								</select>
								<div id="swipecomic-zoom-custom-field" style="margin-top: 10px; display: none;">
									<label>Custom Zoom Value (%)</label>
									<input type="number" id="swipecomic-zoom-custom" class="widefat" value="${zoomCustom}" min="1" step="1" />
								</div>
							</div>

							<div class="swipecomic-modal-field" style="margin-top: 20px;">
								<label><strong>Pan Position</strong></label>
								<p class="description">Override the default pan position for this image.</p>
								<select id="swipecomic-pan-type" class="widefat">
									<option value="inherit" ${panType === 'inherit' ? 'selected' : ''}>Use episode default</option>
									<option value="left" ${panType === 'left' ? 'selected' : ''}>Left</option>
									<option value="right" ${panType === 'right' ? 'selected' : ''}>Right</option>
									<option value="center" ${panType === 'center' ? 'selected' : ''}>Center</option>
									<option value="custom" ${panType === 'custom' ? 'selected' : ''}>Custom</option>
								</select>
								<div id="swipecomic-pan-custom-field" style="margin-top: 10px; display: none;">
									<label>Custom Pan Position (X, Y in pixels)</label>
									<div style="display: flex; gap: 10px;">
										<input type="number" id="swipecomic-pan-custom-x" class="widefat" placeholder="X" value="${panCustomX}" />
										<input type="number" id="swipecomic-pan-custom-y" class="widefat" placeholder="Y" value="${panCustomY}" />
									</div>
								</div>
							</div>
						</div>
						<div class="swipecomic-modal-footer">
							<button type="button" class="button" id="swipecomic-modal-cancel">Cancel</button>
							<button type="button" class="button button-primary" id="swipecomic-modal-save">Save Settings</button>
						</div>
					</div>
				</div>
			`;
		},

		/**
		 * Bind modal events
		 */
		bindModalEvents() {
			// Close on overlay click
			$('#swipecomic-settings-modal').on('click', (e) => {
				if ($(e.target).is('.swipecomic-modal-overlay')) {
					this.close();
				}
			});

			// Cancel button
			$('#swipecomic-modal-cancel').on('click', () => {
				this.close();
			});

			// Save button
			$('#swipecomic-modal-save').on('click', () => {
				this.save();
			});

			// Zoom type change
			$('#swipecomic-zoom-type').on('change', () => {
				this.updateConditionalFields();
			});

			// Pan type change
			$('#swipecomic-pan-type').on('change', () => {
				this.updateConditionalFields();
			});

			// ESC key to close
			$(document).on('keydown.swipecomic-modal', (e) => {
				if (e.key === 'Escape') {
					this.close();
				}
			});
		},

		/**
		 * Update conditional field visibility
		 */
		updateConditionalFields() {
			const zoomType = $('#swipecomic-zoom-type').val();
			const panType = $('#swipecomic-pan-type').val();

			// Show/hide zoom custom field
			if (zoomType === 'custom') {
				$('#swipecomic-zoom-custom-field').show();
			} else {
				$('#swipecomic-zoom-custom-field').hide();
			}

			// Show/hide pan custom field
			if (panType === 'custom') {
				$('#swipecomic-pan-custom-field').show();
			} else {
				$('#swipecomic-pan-custom-field').hide();
			}
		},

		/**
		 * Save settings
		 */
		save() {
			const zoomType = $('#swipecomic-zoom-type').val();

			let zoomValue = '';

			// Get zoom value
			if (zoomType === 'inherit') {
				zoomValue = '';
			} else if (zoomType === 'custom') {
				const customZoom = $('#swipecomic-zoom-custom').val();
				if (!customZoom || parseFloat(customZoom) <= 0) {
					// eslint-disable-next-line no-alert
					alert('Please enter a valid positive zoom value.');
					return;
				}
				zoomValue = customZoom;
			} else {
				zoomValue = zoomType;
			}

			const panType = $('#swipecomic-pan-type').val();
			let panValue = '';

			// Get pan value
			if (panType === 'inherit') {
				panValue = '';
			} else if (panType === 'custom') {
				const x = $('#swipecomic-pan-custom-x').val();
				const y = $('#swipecomic-pan-custom-y').val();
				if (!x || !y || isNaN(x) || isNaN(y)) {
					// eslint-disable-next-line no-alert
					alert('Please enter valid numeric pan coordinates.');
					return;
				}
				panValue = x + ',' + y;
			} else {
				panValue = panType;
			}

			// Update image data
			const imageData = EpisodeImagesGallery.imagesData[this.currentIndex];
			if (zoomValue) {
				imageData.zoom_override = zoomValue;
			} else {
				delete imageData.zoom_override;
			}

			if (panValue) {
				imageData.pan_override = panValue;
			} else {
				delete imageData.pan_override;
			}

			// Update visual indicator
			this.updateOverrideBadge();

			// Save data
			EpisodeImagesGallery.saveImagesData();

			// Close modal
			this.close();
		},

		/**
		 * Update override badge on image item
		 */
		updateOverrideBadge() {
			const $item = $(
				`.swipecomic-image-item[data-index="${this.currentIndex}"]`
			);
			const imageData = EpisodeImagesGallery.imagesData[this.currentIndex];
			const hasOverrides =
				(imageData.zoom_override && imageData.zoom_override !== '') ||
				(imageData.pan_override && imageData.pan_override !== '');

			// Remove existing badge
			$item.find('.swipecomic-override-badge').remove();

			// Add badge if has overrides
			if (hasOverrides) {
				$item
					.find('.swipecomic-image-preview')
					.append(
						'<span class="swipecomic-override-badge" title="Has custom settings"><span class="dashicons dashicons-admin-generic"></span></span>'
					);
			}
		},

		/**
		 * Close modal
		 */
		close() {
			$(document).off('keydown.swipecomic-modal');
			$('#swipecomic-settings-modal').remove();
			this.currentIndex = null;
			this.currentImageId = null;
		},
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		if ($('#swipecomic-images-grid').length) {
			EpisodeImagesGallery.init();
		}
	});
})(jQuery);
