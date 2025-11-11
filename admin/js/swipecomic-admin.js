/**
 * SwipeComic Admin JavaScript
 *
 * @since 1.0.0
 * @param {Object} $ jQuery object
 */

/* global jQuery, swipecomicAdmin, ajaxurl */

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
		 * Auto-save images to post meta via AJAX
		 */
		autoSaveImages() {
			const postId = $('#post_ID').val();

			if (!postId || postId === '0') {
				// New post without ID yet, will be saved on first publish/save
				return;
			}

			$.ajax({
				url: swipecomicAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'swipecomic_save_images',
					nonce: swipecomicAdmin.nonce,
					post_id: postId,
					images_data: JSON.stringify(this.imagesData),
				},
				error: () => {
					// eslint-disable-next-line no-alert
					alert('Auto-save failed. Please save your changes manually.');
				},
			});
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

			// Cover image set button
			$('#swipecomic-set-cover-image').on('click', (e) => {
				e.preventDefault();
				this.openCoverImageUploader();
			});

			// Cover image remove button (using event delegation for dynamically added button)
			$(document).on('click', '#swipecomic-remove-cover-image', (e) => {
				e.preventDefault();
				this.removeCoverImage();
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
						uploadedTo: wp.media.view.settings.post.id, // Pass post ID
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

			// Pass SwipeComic context to upload requests
			if (wp.media.view.settings.post && wp.media.view.settings.post.id) {
				this.frame.uploader.options.uploader.params =
					this.frame.uploader.options.uploader.params || {};
				this.frame.uploader.options.uploader.params.swipecomic_context_post_id =
					wp.media.view.settings.post.id;
				this.frame.uploader.options.uploader.params.swipecomic_upload_nonce =
					swipecomicAdmin.nonce;
			}

			// Open the media frame
			this.frame.open();
		},

		/**
		 * Open WordPress media uploader for cover image
		 */
		openCoverImageUploader() {
			// Create cover image frame if it doesn't exist
			if (!this.coverFrame) {
				this.coverFrame = wp.media({
					title: 'Select Cover Image',
					button: {
						text: 'Set Cover Image',
					},
					multiple: false,
					library: {
						type: 'image',
					},
				});

				// Handle image selection
				this.coverFrame.on('select', () => {
					const attachment = this.coverFrame
						.state()
						.get('selection')
						.first()
						.toJSON();
					this.setCoverImage(attachment);
				});
			}

			// Open the media frame
			this.coverFrame.open();
		},

		/**
		 * Set cover image
		 *
		 * @param {Object} attachment - WordPress media attachment object
		 */
		setCoverImage(attachment) {
			const preview = $('#swipecomic-cover-image-preview');
			const imageId = $('#swipecomic-cover-image-id');
			const setButton = $('#swipecomic-set-cover-image');
			const removeButton = $('#swipecomic-remove-cover-image');

			// Update preview
			const imgHtml = attachment.sizes?.medium
				? `<img src="${attachment.sizes.medium.url}" id="swipecomic-cover-image-display" alt="${attachment.alt || ''}" />`
				: `<img src="${attachment.url}" id="swipecomic-cover-image-display" alt="${attachment.alt || ''}" />`;

			preview.html(imgHtml);

			// Update hidden field
			imageId.val(attachment.id);

			// Update button text
			setButton.text('Change Cover Image');

			// Show remove button if it doesn't exist
			if (removeButton.length === 0) {
				setButton.after(
					'<button type="button" class="button swipecomic-remove-cover-image" id="swipecomic-remove-cover-image">Remove Cover Image</button>'
				);
			} else {
				removeButton.show();
			}
		},

		/**
		 * Remove cover image
		 */
		removeCoverImage() {
			const confirmMessage = swipecomicAdmin.deleteOnRemove
				? 'Are you sure you want to delete the cover image? This will permanently remove it from your Media Library and cannot be undone.'
				: 'Remove the cover image from this episode? It will remain in your Media Library.';

			// eslint-disable-next-line no-alert
			if (!confirm(confirmMessage)) {
				return;
			}

			const preview = $('#swipecomic-cover-image-preview');
			const imageId = $('#swipecomic-cover-image-id');
			const setButton = $('#swipecomic-set-cover-image');
			const removeButton = $('#swipecomic-remove-cover-image');
			const currentImageId = parseInt(imageId.val(), 10);

			// Check if we should delete or just detach
			if (swipecomicAdmin.deleteOnRemove && currentImageId) {
				// Delete the image via AJAX
				$.ajax({
					url: swipecomicAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'swipecomic_delete_image',
						nonce: swipecomicAdmin.nonce,
						attachment_id: currentImageId,
					},
					success: (response) => {
						if (response.success) {
							this.clearCoverImageUI(preview, imageId, setButton, removeButton);
						} else {
							// eslint-disable-next-line no-alert
							alert(
								response.data.message ||
									'Failed to delete cover image. Please try again.'
							);
						}
					},
					error: () => {
						// eslint-disable-next-line no-alert
						alert('Error deleting cover image. Please try again.');
					},
				});
			} else {
				// Just detach - remove from episode but keep in Media Library
				this.clearCoverImageUI(preview, imageId, setButton, removeButton);
			}
		},

		/**
		 * Clear cover image UI elements
		 *
		 * @param {Object} preview      Preview element
		 * @param {Object} imageId      Image ID input element
		 * @param {Object} setButton    Set button element
		 * @param {Object} removeButton Remove button element
		 */
		clearCoverImageUI(preview, imageId, setButton, removeButton) {
			// Update preview to placeholder
			preview.html(
				'<div class="swipecomic-cover-placeholder"><span class="dashicons dashicons-format-image"></span><p>No cover image set</p></div>'
			);

			// Clear hidden field
			imageId.val('');

			// Update button text
			setButton.text('Set Cover Image');

			// Hide remove button
			removeButton.hide();
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

			// Auto-save to post meta immediately
			this.autoSaveImages();
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
						<button type="button" class="button swipecomic-image-remove" title="${swipecomicAdmin.removeButtonText}">
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
			const imageId = parseInt($item.data('image-id'), 10);

			// Check if we should delete or just detach
			if (swipecomicAdmin.deleteOnRemove) {
				// Delete the image via AJAX
				$.ajax({
					url: swipecomicAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'swipecomic_delete_image',
						nonce: swipecomicAdmin.nonce,
						attachment_id: imageId,
					},
					success: (response) => {
						if (response.success) {
							// Remove from data array
							this.imagesData.splice(index, 1);

							// Remove from DOM
							$item.remove();

							// Update indices
							this.updateImageOrder();
						} else {
							// eslint-disable-next-line no-alert
							alert(
								response.data.message ||
									'Failed to delete image. Please try again.'
							);
						}
					},
					error: () => {
						// eslint-disable-next-line no-alert
						alert('Error deleting image. Please try again.');
					},
				});
			} else {
				// Just detach - remove from episode but keep in Media Library
				this.imagesData.splice(index, 1);
				$item.remove();
				this.updateImageOrder();
			}
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

			// Auto-save the new order
			this.autoSaveImages();
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

			// Auto-save to post meta
			EpisodeImagesGallery.autoSaveImages();

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
	 * Episode Settings Conditional Fields
	 */
	const EpisodeSettings = {
		/**
		 * Initialize episode settings
		 */
		init() {
			this.bindEvents();
			this.updateConditionalFields();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Zoom select change
			$('#swipecomic_default_zoom').on('change', () => {
				this.updateConditionalFields();
			});

			// Pan select change
			$('#swipecomic_default_pan').on('change', () => {
				this.updateConditionalFields();
			});
		},

		/**
		 * Update conditional field visibility
		 */
		updateConditionalFields() {
			const zoomValue = $('#swipecomic_default_zoom').val();
			const panValue = $('#swipecomic_default_pan').val();

			// Show/hide zoom custom field
			if (zoomValue === 'custom') {
				$('.swipecomic-zoom-custom').show();
			} else {
				$('.swipecomic-zoom-custom').hide();
			}

			// Show/hide pan custom fields
			if (panValue === 'custom') {
				$('.swipecomic-pan-custom').show();
			} else {
				$('.swipecomic-pan-custom').hide();
			}
		},
	};

	/**
	 * Create a media manager for handling image uploads
	 *
	 * @param {Object} config Configuration object
	 * @return {Object} Media manager instance
	 */
	function createMediaManager(config) {
		return {
			/**
			 * Media frame instance
			 */
			frame: null,

			/**
			 * Initialize the manager
			 */
			init() {
				this.bindEvents();
			},

			/**
			 * Bind event handlers
			 */
			bindEvents() {
				// Upload button
				$(config.uploadButton)
					.off('click.swipecomicMedia')
					.on('click.swipecomicMedia', (e) => {
						e.preventDefault();
						this.openMediaUploader();
					});

				// Remove button
				$(config.removeButton)
					.off('click.swipecomicMedia')
					.on('click.swipecomicMedia', (e) => {
						e.preventDefault();
						this.removeMedia();
					});
			},

			/**
			 * Open WordPress media uploader
			 */
			openMediaUploader() {
				// Create media frame if it doesn't exist
				if (!this.frame) {
					this.frame = wp.media({
						title: config.mediaTitle,
						button: {
							text: config.mediaButtonText,
						},
						multiple: false,
						library: {
							type: 'image',
						},
					});

					// Handle image selection
					this.frame.on('select', () => {
						const attachment = this.frame
							.state()
							.get('selection')
							.first()
							.toJSON();
						this.setMedia(attachment);
					});
				}

				// Open the media frame
				this.frame.open();
			},

			/**
			 * Set media image
			 *
			 * @param {Object} attachment Attachment data
			 */
			setMedia(attachment) {
				// Validate attachment
				if (
					!attachment ||
					(!attachment.url && !attachment.sizes) ||
					(attachment.type && attachment.type.indexOf('image') !== 0)
				) {
					// eslint-disable-next-line no-alert
					alert(
						swipecomicAdmin.invalidImageSelected ||
							'Please select a valid image file.'
					);
					return;
				}

				const imageUrl =
					(attachment.sizes &&
						attachment.sizes.medium &&
						attachment.sizes.medium.url) ||
					attachment.url;
				if (!imageUrl || !attachment.id) {
					// eslint-disable-next-line no-alert
					alert(
						swipecomicAdmin.invalidImageSelected ||
							'Please select a valid image file.'
					);
					return;
				}

				const alt = attachment.alt || '';

				// Update hidden input
				$(config.hiddenInput).val(attachment.id);

				// Update preview
				const $img = $('<img>', {
					src: imageUrl,
					alt,
					style: 'max-width: 200px; height: auto; display: block;',
				});
				$(config.previewArea).html($img).show();

				// Update button text
				$(config.uploadButton).html(
					'<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>' +
						config.changeButtonText
				);

				// Show remove button
				$(config.removeButton).show();

				// Auto-save if configured
				if (config.autoSave) {
					this.autoSave(attachment.id);
				}
			},

			/**
			 * Remove media image
			 */
			removeMedia() {
				// eslint-disable-next-line no-alert
				if (!confirm(config.removeConfirmText)) {
					return;
				}

				const imageId = parseInt($(config.hiddenInput).val(), 10);

				// Check if we should delete or just detach
				if (config.deleteOnRemove && imageId) {
					// Delete the image via AJAX
					$.ajax({
						url: swipecomicAdmin.ajaxUrl,
						type: 'POST',
						data: {
							action: 'swipecomic_delete_image',
							nonce: swipecomicAdmin.nonce,
							attachment_id: imageId,
						},
						success: (response) => {
							if (response.success) {
								this.clearMedia();
							} else {
								// eslint-disable-next-line no-alert
								alert(
									response.data.message ||
										'Failed to delete image. Please try again.'
								);
							}
						},
						error: () => {
							// eslint-disable-next-line no-alert
							alert('Error deleting image. Please try again.');
						},
					});
				} else {
					// Just detach - clear the field
					this.clearMedia();
				}
			},

			/**
			 * Clear media from UI
			 */
			clearMedia() {
				// Clear hidden input
				$(config.hiddenInput).val('');

				// Clear preview
				$(config.previewArea).html('').hide();

				// Update button text
				$(config.uploadButton).html(
					'<span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>' +
						config.uploadButtonText
				);

				// Hide remove button
				$(config.removeButton).hide();

				// Auto-save removal if configured
				if (config.autoSave) {
					this.autoSave(0);
				}
			},

			/**
			 * Auto-save media to database
			 *
			 * @param {number} mediaId Media attachment ID (0 to remove)
			 */
			autoSave(mediaId) {
				if (!config.autoSaveAction) {
					return;
				}

				const termId = $('#swipecomic_series_term_id').val();

				if (!termId) {
					// New term without ID yet, will be saved on form submit
					return;
				}

				$.ajax({
					url: swipecomicAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: config.autoSaveAction,
						nonce: swipecomicAdmin.nonce,
						term_id: termId,
						[config.autoSaveParam]: mediaId,
					},
					error: () => {
						// Silent failure - data will be saved on form submit
					},
				});
			},
		};
	}

	/**
	 * Series Cover Image Manager
	 */
	const SeriesCoverManager = createMediaManager({
		uploadButton: '#swipecomic-upload-series-cover',
		removeButton: '#swipecomic-remove-series-cover',
		hiddenInput: '#series_cover_image_id',
		previewArea: '#swipecomic-series-cover-preview',
		mediaTitle: swipecomicAdmin.coverUploadTitle || 'Select Series Cover Image',
		mediaButtonText: swipecomicAdmin.coverUploadButton || 'Use as Cover',
		uploadButtonText: swipecomicAdmin.uploadCoverText || 'Upload Cover Image',
		changeButtonText: swipecomicAdmin.changeCoverText || 'Change Cover Image',
		removeConfirmText:
			swipecomicAdmin.removeCoverConfirm ||
			'Remove cover image from this series? It will remain in your Media Library.',
		deleteOnRemove: swipecomicAdmin.deleteOnRemove || false,
		autoSave: true,
		autoSaveAction: 'swipecomic_save_series_cover',
		autoSaveParam: 'cover_image_id',
	});

	/**
	 * Series Logo Manager
	 */
	const SeriesLogoManager = createMediaManager({
		uploadButton: '#swipecomic-upload-series-logo',
		removeButton: '#swipecomic-remove-series-logo',
		hiddenInput: '#series_logo_id',
		previewArea: '#swipecomic-series-logo-preview',
		mediaTitle: swipecomicAdmin.logoUploadTitle || 'Select Series Logo',
		mediaButtonText: swipecomicAdmin.logoUploadButton || 'Use as Logo',
		uploadButtonText: swipecomicAdmin.uploadLogoText || 'Upload Logo',
		changeButtonText: swipecomicAdmin.changeLogoText || 'Change Logo',
		removeConfirmText:
			swipecomicAdmin.removeLogoConfirm ||
			'Remove logo from this series? It will remain in your Media Library.',
		deleteOnRemove: swipecomicAdmin.deleteOnRemove || false,
		autoSave: true,
		autoSaveAction: 'swipecomic_save_series_logo',
		autoSaveParam: 'logo_id',
	});

	/**
	 * Episode Order Manager for Series
	 */
	const EpisodeOrderManager = {
		/**
		 * Initialize the order manager
		 */
		init() {
			this.initSortable();
		},

		/**
		 * Initialize jQuery UI Sortable for episode list
		 */
		initSortable() {
			const list = $('#swipecomic-episode-order-list');

			if (!list.length) {
				return;
			}

			list.sortable({
				items: '.swipecomic-episode-order-item',
				cursor: 'move',
				opacity: 0.7,
				placeholder: 'swipecomic-episode-order-placeholder',
				update: () => {
					this.saveOrder();
				},
			});
		},

		/**
		 * Update episode numbers in the UI
		 */
		updateEpisodeNumbers() {
			const list = $('#swipecomic-episode-order-list');
			list.find('.swipecomic-episode-order-item').each(function (index) {
				const $item = $(this);
				const newEpisodeNumber = index + 1;
				const $episodeNumber = $item.find('.episode-number');

				// Update the episode number text
				if ($episodeNumber.length) {
					$episodeNumber.text(
						(swipecomicAdmin.episodeNumberLabel || 'Episode #') +
							newEpisodeNumber
					);
				}
			});
		},

		/**
		 * Save episode order via AJAX
		 */
		saveOrder() {
			const list = $('#swipecomic-episode-order-list');
			const termId = list.data('term-id');
			const order = [];

			list.find('.swipecomic-episode-order-item').each(function () {
				order.push($(this).data('post-id'));
			});

			// Update episode numbers immediately for better UX
			this.updateEpisodeNumbers();

			// Show loading message
			const messageDiv = $('#swipecomic-episode-order-message');
			messageDiv
				.removeClass('notice-success notice-error')
				.addClass('notice notice-info')
				.html(
					'<p>' + (swipecomicAdmin.savingOrder || 'Saving order...') + '</p>'
				)
				.show();

			// Send AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'swipecomic_update_episode_order',
					nonce: $('#swipecomic_episode_order_nonce').val(),
					term_id: termId,
					order,
				},
				success: (response) => {
					if (response.success) {
						messageDiv
							.removeClass('notice-info notice-error')
							.addClass('notice-success')
							.html('<p>' + response.data.message + '</p>');
					} else {
						messageDiv
							.removeClass('notice-info notice-success')
							.addClass('notice-error')
							.html('<p>' + response.data.message + '</p>');
					}

					// Hide message after 3 seconds
					setTimeout(() => {
						messageDiv.fadeOut();
					}, 3000);
				},
				error: () => {
					messageDiv
						.removeClass('notice-info notice-success')
						.addClass('notice-error')
						.html(
							'<p>' +
								(swipecomicAdmin.orderError ||
									'Error updating episode order.') +
								'</p>'
						);

					// Hide message after 3 seconds
					setTimeout(() => {
						messageDiv.fadeOut();
					}, 3000);
				},
			});
		},
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		if ($('#swipecomic-images-grid').length) {
			EpisodeImagesGallery.init();
		}

		if ($('#swipecomic_default_zoom').length) {
			EpisodeSettings.init();
		}

		if ($('#swipecomic-upload-series-cover').length) {
			SeriesCoverManager.init();
		}

		if ($('#swipecomic-upload-series-logo').length) {
			SeriesLogoManager.init();
		}

		if ($('#swipecomic-episode-order-list').length) {
			EpisodeOrderManager.init();
		}
	});
})(jQuery);
