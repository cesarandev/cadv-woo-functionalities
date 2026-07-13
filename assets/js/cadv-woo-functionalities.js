(function () {
	'use strict';

	var config = window.CesarandevWooFunc || {};

	function getMessage(key, fallback) {
		return config.messages && config.messages[key] ? config.messages[key] : fallback;
	}

	function isValidEmail(email) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
	}

	function setMessage(messageElement, text, type) {
		if (!messageElement) {
			return;
		}

		messageElement.textContent = text;
		messageElement.className = messageElement.className.replace(/\s?is-(error|success|loading)/g, '');
		messageElement.classList.add('is-visible', 'is-' + type);
	}

	function clearMessage(messageElement) {
		if (!messageElement) {
			return;
		}

		messageElement.textContent = '';
		messageElement.className = messageElement.className.replace(/\s?is-(error|success|loading|visible)/g, '');
	}

	function setSubmitting(form, isSubmitting) {
		var submit = form.querySelector('[type="submit"]');
		if (submit) {
			submit.disabled = isSubmitting;
		}
	}

	function postForm(formData) {
		return fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json();
		});
	}

	function initTechnicalSheetModal() {
		var modal = document.querySelector('[data-cesarandev-wf-modal]');
		var form = document.querySelector('[data-cesarandev-wf-form]');
		var message = document.querySelector('[data-cesarandev-wf-message]');
		var openButtons = document.querySelectorAll('[data-cesarandev-wf-open-modal]');
		var closeButtons = document.querySelectorAll('[data-cesarandev-wf-close-modal]');
		var lastFocusedElement = null;

		if (!modal || !form) {
			return;
		}

		function openModal() {
			lastFocusedElement = document.activeElement;
			modal.hidden = false;
			document.body.classList.add('cesarandev-wf-modal-open');
			clearMessage(message);

			var firstInput = form.querySelector('input:not([type="hidden"])');
			if (firstInput) {
				firstInput.focus();
			}
		}

		function closeModal() {
			modal.hidden = true;
			document.body.classList.remove('cesarandev-wf-modal-open');

			if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
				lastFocusedElement.focus();
			}
		}

		function validateForm(formData) {
			var requiredFields = ['full_name', 'company', 'position', 'email', 'phone'];
			var i;

			for (i = 0; i < requiredFields.length; i += 1) {
				if (!String(formData.get(requiredFields[i]) || '').trim()) {
					return getMessage('required', 'Completa todos los campos obligatorios.');
				}
			}

			if (!isValidEmail(String(formData.get('email') || '').trim())) {
				return getMessage('email', 'Ingresa un correo electronico valido.');
			}

			if (!formData.get('privacy_acceptance')) {
				return getMessage('privacy', 'Debes aceptar la politica de privacidad.');
			}

			return '';
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			var formData = new FormData(form);
			var validationError = validateForm(formData);

			if (validationError) {
				setMessage(message, validationError, 'error');
				return;
			}

			formData.append('action', config.action || 'cesarandev_wf_request_technical_sheet');
			formData.append('nonce', config.nonce || '');
			setSubmitting(form, true);
			setMessage(message, getMessage('loading', 'Enviando solicitud...'), 'loading');

			postForm(formData)
				.then(function (response) {
					var data = response && response.data ? response.data : {};

					if (!response || !response.success) {
						throw new Error(data.message || getMessage('error', 'No se pudo registrar la solicitud. Intentalo de nuevo.'));
					}

					if (!config.isLoggedIn) {
						form.reset();
					}

					if (data.downloadsUrl) {
						setMessage(message, data.message + ' ', 'success');
						var link = document.createElement('a');
						link.href = data.downloadsUrl;
						link.textContent = 'Ir a mis descargas';
						message.appendChild(link);
					} else {
						setMessage(message, data.message, 'success');
					}
				})
				.catch(function (error) {
					setMessage(message, error.message || getMessage('error', 'No se pudo registrar la solicitud. Intentalo de nuevo.'), 'error');
				})
				.finally(function () {
					setSubmitting(form, false);
				});
		});

		Array.prototype.forEach.call(openButtons, function (button) {
			button.addEventListener('click', openModal);
		});

		Array.prototype.forEach.call(closeButtons, function (button) {
			button.addEventListener('click', closeModal);
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !modal.hidden) {
				closeModal();
			}
		});
	}

	function initCtaForms() {
		var forms = document.querySelectorAll('[data-cesarandev-wf-cta-form]');

		Array.prototype.forEach.call(forms, function (form) {
			var message = form.querySelector('[data-cesarandev-wf-cta-message]');

			form.addEventListener('submit', function (event) {
				event.preventDefault();

				var formData = new FormData(form);
				var email = String(formData.get('email') || '').trim();
				var type = String(formData.get('cta_type') || '').trim();
				var requiredFields = type === 'newsletter' ? ['full_name', 'email'] : ['full_name', 'company', 'position', 'phone', 'email'];
				var i;

				for (i = 0; i < requiredFields.length; i += 1) {
					if (!String(formData.get(requiredFields[i]) || '').trim()) {
						setMessage(message, getMessage('required', 'Completa todos los campos obligatorios.'), 'error');
						return;
					}
				}

				if (!isValidEmail(email)) {
					setMessage(message, getMessage('email', 'Ingresa un correo electronico valido.'), 'error');
					return;
				}

				if (!formData.get('privacy_acceptance')) {
					setMessage(message, getMessage('privacy', 'Debes aceptar la politica de privacidad.'), 'error');
					return;
				}

				formData.append('action', config.ctaAction || 'cesarandev_wf_submit_cta');
				formData.append('nonce', config.ctaNonce || '');
				setSubmitting(form, true);
				setMessage(message, getMessage('loading', 'Enviando solicitud...'), 'loading');

				postForm(formData)
					.then(function (response) {
						var data = response && response.data ? response.data : {};

						if (!response || !response.success) {
							throw new Error(data.message || getMessage('error', 'No se pudo registrar la solicitud. Intentalo de nuevo.'));
						}

						form.reset();
						setMessage(message, data.message, 'success');
					})
					.catch(function (error) {
						setMessage(message, error.message || getMessage('error', 'No se pudo registrar la solicitud. Intentalo de nuevo.'), 'error');
					})
					.finally(function () {
						setSubmitting(form, false);
					});
			});
		});
	}

	initTechnicalSheetModal();
	initCtaForms();
}());
