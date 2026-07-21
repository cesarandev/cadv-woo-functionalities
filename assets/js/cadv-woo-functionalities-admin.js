(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var field = document.querySelector('[data-cadv-login-image-field]');

		if (!field || !window.wp || !window.wp.media) {
			return;
		}

		var input = field.querySelector('[data-cadv-login-image-id]');
		var preview = field.querySelector('[data-cadv-login-image-preview]');
		var previewImage = preview.querySelector('img');
		var selectButton = field.querySelector('[data-cadv-login-image-select]');
		var removeButton = field.querySelector('[data-cadv-login-image-remove]');
		var frame;

		selectButton.addEventListener('click', function () {
			if (!frame) {
				frame = window.wp.media({
					title: field.getAttribute('data-frame-title'),
					button: { text: field.getAttribute('data-button-label') },
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

					input.value = attachment.id;
					previewImage.src = imageUrl;
					preview.hidden = false;
					removeButton.hidden = false;
					selectButton.textContent = field.getAttribute('data-change-label');
				});
			}

			frame.open();
		});

		removeButton.addEventListener('click', function () {
			input.value = '';
			previewImage.removeAttribute('src');
			preview.hidden = true;
			removeButton.hidden = true;
			selectButton.textContent = field.getAttribute('data-select-label');
		});
	});
}());
