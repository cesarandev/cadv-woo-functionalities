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

			if (!String(formData.get('captcha_answer') || '').trim()) {
				return getMessage('captcha', 'Completa la verificacion de seguridad.');
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
			var serviceSelect = form.querySelector('[data-cesarandev-wf-service-select]');
			var demoDateField = form.querySelector('[data-cesarandev-wf-demo-date-field]');
			var demoDateInput = form.querySelector('[data-cesarandev-wf-demo-date]');
			var message = form.querySelector('[data-cesarandev-wf-cta-message]');

			function getSelectedServiceKey() {
				var option;

				if (!serviceSelect || serviceSelect.selectedIndex < 0) {
					return '';
				}

				option = serviceSelect.options[serviceSelect.selectedIndex];
				return option ? String(option.getAttribute('data-service-key') || '') : '';
			}

			function syncDemoDateField() {
				var isAgropilot = getSelectedServiceKey() === 'agropilot';

				if (!demoDateField || !demoDateInput) {
					return;
				}

				demoDateField.hidden = !isAgropilot;
				demoDateInput.disabled = !isAgropilot;
				demoDateInput.required = isAgropilot;
			}

			if (serviceSelect) {
				serviceSelect.addEventListener('change', syncDemoDateField);
				syncDemoDateField();
			}

			form.addEventListener('submit', function (event) {
				event.preventDefault();

				var formData = new FormData(form);
				var email = String(formData.get('email') || '').trim();
				var type = String(formData.get('cta_type') || '').trim();
				var requiredFields = type === 'newsletter' ? ['full_name', 'email'] : ['full_name', 'company', 'position', 'phone', 'email'];
				var selectedServiceKey = getSelectedServiceKey();
				var i;

				if (type === 'services') {
					requiredFields.push('product_interest');

					if (selectedServiceKey === 'agropilot') {
						requiredFields.push('demo_date');
					}
				}

				for (i = 0; i < requiredFields.length; i += 1) {
					if (!String(formData.get(requiredFields[i]) || '').trim()) {
						setMessage(message, getMessage('required', 'Completa todos los campos obligatorios.'), 'error');
						return;
					}
				}

				if (selectedServiceKey === 'agropilot' && demoDateInput && (demoDateInput.value < demoDateInput.min || demoDateInput.value > demoDateInput.max)) {
					setMessage(message, getMessage('demoDate', 'Selecciona una fecha de demostracion entre hoy y un mes a partir de hoy.'), 'error');
					return;
				}

				if (!isValidEmail(email)) {
					setMessage(message, getMessage('email', 'Ingresa un correo electronico valido.'), 'error');
					return;
				}

				if (!formData.get('privacy_acceptance')) {
					setMessage(message, getMessage('privacy', 'Debes aceptar la politica de privacidad.'), 'error');
					return;
				}

				if (!String(formData.get('captcha_answer') || '').trim()) {
					setMessage(message, getMessage('captcha', 'Completa la verificacion de seguridad.'), 'error');
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
						syncDemoDateField();
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

	function initCtaModals() {
		var modals = document.querySelectorAll('[data-cesarandev-wf-cta-modal]');
		var activeModal = null;
		var lastFocusedElement = null;

		if (!modals.length) {
			return;
		}

		function getModalFromTarget(target) {
			var id;
			var modal;

			if (!target || target.indexOf('#') === -1) {
				return null;
			}

			id = target.substring(target.lastIndexOf('#') + 1);
			modal = document.getElementById(id);

			return modal && modal.hasAttribute('data-cesarandev-wf-cta-modal') ? modal : null;
		}

		function openModal(modal) {
			var closeButton;
			var firstInput;
			var isMobile;

			if (!modal) {
				return;
			}

			lastFocusedElement = document.activeElement;
			activeModal = modal;
			modal.hidden = false;
			document.body.classList.add('cesarandev-wf-modal-open');

			isMobile = window.matchMedia && window.matchMedia('(max-width: 767px), (pointer: coarse)').matches;
			closeButton = modal.querySelector('[data-cesarandev-wf-close-cta-modal].cesarandev-wf-modal__close');
			firstInput = modal.querySelector('input:not([type="hidden"])');

			if (isMobile && closeButton) {
				closeButton.focus({ preventScroll: true });
			} else if (firstInput) {
				firstInput.focus();
			}
		}

		function closeModal(modal) {
			if (!modal) {
				return;
			}

			modal.hidden = true;
			activeModal = null;
			document.body.classList.remove('cesarandev-wf-modal-open');

			if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
				lastFocusedElement.focus();
			}
		}

		document.addEventListener('click', function (event) {
			var closeButton = event.target.closest('[data-cesarandev-wf-close-cta-modal]');
			var trigger;
			var target;
			var modal;

			if (closeButton) {
				closeModal(closeButton.closest('[data-cesarandev-wf-cta-modal]'));
				return;
			}

			trigger = event.target.closest('a[href], [data-cesarandev-wf-cta-target]');
			if (!trigger) {
				return;
			}

			target = trigger.getAttribute('data-cesarandev-wf-cta-target') || trigger.getAttribute('href');
			modal = getModalFromTarget(target);

			if (modal) {
				event.preventDefault();
				openModal(modal);
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && activeModal) {
				closeModal(activeModal);
			}
		});

		openModal(getModalFromTarget(window.location.hash));
	}

	initTechnicalSheetModal();
	initCtaModals();
	initCtaForms();
}());
