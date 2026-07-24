(function () {
	'use strict';

	var config = window.CADVTailoredTo || {};

	function toArray(value) {
		return Array.prototype.slice.call(value || []);
	}

	function setMessage(node, text, type) {
		if (!node) {
			return;
		}
		node.textContent = text || '';
		node.classList.toggle('is-error', type === 'error');
		node.classList.toggle('is-success', type === 'success');
	}

	function createList(node, items) {
		if (!node) {
			return;
		}
		node.replaceChildren();
		(items || []).forEach(function (item) {
			var listItem = document.createElement('li');
			listItem.textContent = item;
			node.appendChild(listItem);
		});
	}

	function initRecaptchaWidgets() {
		var widgets;

		if (!window.grecaptcha || typeof window.grecaptcha.render !== 'function') {
			return;
		}

		widgets = document.querySelectorAll('.cesarandev-wf-recaptcha__widget:not([data-widget-id])');
		toArray(widgets).forEach(function (widget) {
			var widgetId = window.grecaptcha.render(widget, {
				sitekey: widget.getAttribute('data-sitekey') || '',
				theme: widget.getAttribute('data-theme') || 'light'
			});
			widget.setAttribute('data-widget-id', String(widgetId));
		});
	}

	function resetFormRecaptcha(form) {
		var widget = form ? form.querySelector('.cesarandev-wf-recaptcha__widget[data-widget-id]') : null;
		var widgetId = widget ? parseInt(widget.getAttribute('data-widget-id'), 10) : NaN;

		if (window.grecaptcha && typeof window.grecaptcha.reset === 'function' && !Number.isNaN(widgetId)) {
			window.grecaptcha.reset(widgetId);
		}
	}

	function hasCaptchaResponse(formData) {
		return Boolean(
			String(formData.get('captcha_answer') || '').trim() ||
			String(formData.get('g-recaptcha-response') || '').trim()
		);
	}

	var previousRecaptchaOnload = window.CadvRecaptchaOnload;
	window.CadvRecaptchaOnload = function () {
		if (typeof previousRecaptchaOnload === 'function') {
			previousRecaptchaOnload();
		}
		initRecaptchaWidgets();
	};

	function postData(action, formData) {
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', config.nonce || '');
		formData.forEach(function (value, key) {
			body.append(key, value);
		});

		return window.fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function (response) {
			return response.json().catch(function () {
				throw new Error(config.messages && config.messages.error ? config.messages.error : 'No fue posible procesar la respuesta.');
			}).then(function (payload) {
				if (!response.ok || !payload.success) {
					var message = payload && payload.data && payload.data.message
						? payload.data.message
						: (config.messages && config.messages.error ? config.messages.error : 'No fue posible procesar la solicitud.');
					throw new Error(message);
				}
				return payload.data;
			});
		});
	}

	function initializeCalculator(root) {
		var wizard = root.querySelector('[data-cadv-tt-wizard]');
		var steps = toArray(root.querySelectorAll('[data-cadv-tt-step]'));
		var progress = toArray(root.querySelectorAll('[data-cadv-tt-progress]'));
		var message = root.querySelector('[data-cadv-tt-message]');
		var dialog = root.querySelector('[data-cadv-tt-dialog]');
		var contactForm = root.querySelector('[data-cadv-tt-contact-form]');
		var contactMessage = root.querySelector('[data-cadv-tt-contact-message]');
		var currentStep = 1;
		var resultStep = steps.reduce(function (maximum, step) {
			return Math.max(maximum, Number(step.getAttribute('data-cadv-tt-step')) || 0);
		}, 1);
		var previewStep = resultStep - 1;
		var latestResult = null;
		var busy = false;

		if (!wizard || !steps.length) {
			return;
		}

		function showStep(number) {
			currentStep = Math.max(1, Math.min(resultStep, number));
			steps.forEach(function (step) {
				var active = Number(step.getAttribute('data-cadv-tt-step')) === currentStep;
				step.hidden = !active;
				step.classList.toggle('is-active', active);
			});
			progress.forEach(function (item) {
				var itemStep = Number(item.getAttribute('data-cadv-tt-progress'));
				item.classList.toggle('is-active', itemStep === currentStep);
				item.classList.toggle('is-complete', itemStep < currentStep);
				var marker = item.querySelector('span');
				if (marker) {
					marker.textContent = itemStep < currentStep ? '✓' : String(itemStep);
				}
			});
			setMessage(message, '');
			var activeStep = root.querySelector('[data-cadv-tt-step="' + currentStep + '"]');
			var heading = activeStep && activeStep.querySelector('legend, h3');
			if (heading) {
				heading.setAttribute('tabindex', '-1');
				heading.focus({ preventScroll: true });
			}
			root.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		function validateStep(number) {
			var step = root.querySelector('[data-cadv-tt-step="' + number + '"]');
			if (!step) {
				return true;
			}
			var fields = toArray(step.querySelectorAll('input[required], select[required], textarea[required]'));
			for (var index = 0; index < fields.length; index += 1) {
				if (!fields[index].checkValidity()) {
					fields[index].reportValidity();
					setMessage(message, config.messages && config.messages.required ? config.messages.required : 'Completa los campos obligatorios.', 'error');
					return false;
				}
			}
			return true;
		}

		function renderResult(result) {
			latestResult = result;
			var crop = result.crop_label || '';
			var areaField = wizard.elements.area;
			var area = areaField ? areaField.value : '';
			root.querySelector('[data-cadv-tt-result-formula]').textContent = result.formula || '--';
			root.querySelector('[data-cadv-tt-result-context]').textContent = crop + (area ? ' · ' + area + ' ha declaradas' : '');
			root.querySelector('[data-cadv-tt-result-n]').textContent = String(result.n);
			root.querySelector('[data-cadv-tt-result-p]').textContent = String(result.p2o5);
			root.querySelector('[data-cadv-tt-result-k]').textContent = String(result.k2o);
			createList(root.querySelector('[data-cadv-tt-result-notes]'), result.notes);
			createList(root.querySelector('[data-cadv-tt-result-missing]'), result.missing_requirements);
			var readiness = root.querySelector('[data-cadv-tt-result-readiness]');
			if (readiness) {
				readiness.textContent = result.readiness_label || '';
			}
			var evidenceGrid = root.querySelector('[data-cadv-tt-result-evidence]');
			if (evidenceGrid) {
				evidenceGrid.replaceChildren();
				(result.evidence_status || []).forEach(function (evidence) {
					var card = document.createElement('div');
					var marker = document.createElement('span');
					var copy = document.createElement('div');
					var title = document.createElement('strong');
					var text = document.createElement('small');
					card.className = 'cadv-tt__evidence-card is-' + evidence.status;
					marker.className = 'cadv-tt__evidence-marker';
					marker.textContent = evidence.status === 'ready' || evidence.status === 'not_required'
						? '✓'
						: (evidence.status === 'partial' || evidence.status === 'declared' ? '•' : '!');
					title.textContent = evidence.label;
					text.textContent = evidence.text;
					copy.appendChild(title);
					copy.appendChild(text);
					card.appendChild(marker);
					card.appendChild(copy);
					evidenceGrid.appendChild(card);
				});
			}
			updateWhatsApp();
			showStep(resultStep);
		}

		function buildPreview() {
			if (busy || !validateStep(3)) {
				return;
			}
			busy = true;
			root.classList.add('is-loading');
			setMessage(message, config.messages && config.messages.loading ? config.messages.loading : 'Construyendo la simulación...');
			postData(config.previewAction || 'cadv_tt_preview_formula', new FormData(wizard))
				.then(function (data) {
					renderResult(data.result);
				})
				.catch(function (error) {
					setMessage(message, error.message, 'error');
				})
				.finally(function () {
					busy = false;
					root.classList.remove('is-loading');
				});
		}

		function updateWhatsApp() {
			var link = root.querySelector('[data-cadv-tt-whatsapp]');
			if (!link) {
				return;
			}
			if (!config.whatsappBase) {
				link.hidden = true;
				return;
			}
			var crop = latestResult ? latestResult.crop_label : '';
			var formula = latestResult ? latestResult.formula : '';
			var copy = 'Hola, deseo solicitar revisión técnica de una simulación Tailored To';
			if (formula) {
				copy += ' para ' + crop + ' (perfil N-P₂O₅-K₂O ' + formula + ')';
			}
			copy += '. Entiendo que aún no incluye dosis y debe validarse con análisis de suelo y foliar.';
			link.href = config.whatsappBase + '?text=' + encodeURIComponent(copy);
		}

		toArray(root.querySelectorAll('[data-cadv-tt-next]')).forEach(function (button) {
			button.addEventListener('click', function () {
				if (!validateStep(currentStep)) {
					return;
				}
				if (currentStep === previewStep) {
					buildPreview();
				} else {
					showStep(currentStep + 1);
				}
			});
		});

		toArray(root.querySelectorAll('[data-cadv-tt-back]')).forEach(function (button) {
			button.addEventListener('click', function () {
				showStep(currentStep === resultStep ? previewStep : currentStep - 1);
			});
		});

		function setConditionalState(container, visible) {
			container.hidden = !visible;
			toArray(container.querySelectorAll('input, select, textarea')).forEach(function (field) {
				field.disabled = !visible;
			});
		}

		function updateConditionals() {
			var soilStatus = wizard.querySelector('input[name="soil_analysis_status"]:checked');
			var foliarStatus = wizard.querySelector('input[name="foliar_analysis_status"]:checked');
			var irrigationSystem = wizard.elements.irrigation_system;
			var application = wizard.elements.application;

			toArray(root.querySelectorAll('[data-cadv-tt-conditional="soil"]')).forEach(function (container) {
				setConditionalState(container, Boolean(soilStatus && soilStatus.value !== 'none'));
			});
			toArray(root.querySelectorAll('[data-cadv-tt-conditional="foliar"]')).forEach(function (container) {
				setConditionalState(container, Boolean(foliarStatus && foliarStatus.value !== 'none'));
			});
			toArray(root.querySelectorAll('[data-cadv-tt-conditional="irrigation"]')).forEach(function (container) {
				setConditionalState(container, Boolean(irrigationSystem && irrigationSystem.value && irrigationSystem.value !== 'rainfed'));
			});
			toArray(root.querySelectorAll('[data-cadv-tt-conditional="fertigation"]')).forEach(function (container) {
				setConditionalState(container, Boolean(application && (application.value === 'fertigation' || application.value === 'mixed')));
			});
		}

		toArray(wizard.querySelectorAll('input[name="soil_analysis_status"], input[name="foliar_analysis_status"], select[name="irrigation_system"], select[name="application"]')).forEach(function (field) {
			field.addEventListener('change', updateConditionals);
		});

		var converter = root.querySelector('[data-cadv-tt-conversion]');
		var elemental = root.querySelector('[data-cadv-tt-elemental]');
		var oxide = root.querySelector('[data-cadv-tt-oxide]');
		function updateConversion() {
			var factor = converter ? Number(converter.value) : 0;
			var input = elemental ? Number(elemental.value) : NaN;
			oxide.value = Number.isFinite(input) && input >= 0 ? (input * factor).toFixed(2) : '';
		}
		if (converter && elemental && oxide) {
			converter.addEventListener('change', updateConversion);
			elemental.addEventListener('input', updateConversion);
		}

		var openDialog = root.querySelector('[data-cadv-tt-open-contact]');
		var closeDialog = root.querySelector('[data-cadv-tt-close-contact]');
		if (openDialog && dialog) {
			openDialog.addEventListener('click', function () {
				setMessage(contactMessage, '');
				initRecaptchaWidgets();
				if (typeof dialog.showModal === 'function') {
					dialog.showModal();
				} else {
					dialog.setAttribute('open', 'open');
				}
			});
		}
		if (closeDialog && dialog) {
			closeDialog.addEventListener('click', function () {
				dialog.close();
			});
		}
		if (dialog) {
			dialog.addEventListener('click', function (event) {
				if (event.target === dialog) {
					dialog.close();
				}
			});
		}

		if (contactForm) {
			contactForm.addEventListener('submit', function (event) {
				event.preventDefault();
				if (busy || !contactForm.reportValidity()) {
					return;
				}
				var contactData = new FormData(contactForm);
				if (!hasCaptchaResponse(contactData)) {
					setMessage(
						contactMessage,
						config.messages && config.messages.captcha ? config.messages.captcha : 'Completa la verificación de seguridad.',
						'error'
					);
					return;
				}
				busy = true;
				var submitButton = contactForm.querySelector('[type="submit"]');
				var payload = new FormData(wizard);
				contactForm.setAttribute('aria-busy', 'true');
				contactData.forEach(function (value, key) {
					payload.set(key, value);
				});
				if (latestResult && latestResult.formula) {
					payload.set('client_formula', latestResult.formula);
				}
				if (submitButton) {
					submitButton.disabled = true;
				}
				setMessage(contactMessage, config.messages && config.messages.saving ? config.messages.saving : 'Guardando la solicitud...');
				postData(config.submitAction || 'cadv_tt_submit_request', payload)
					.then(function (data) {
						var suffix = data.requestCode ? ' Código: ' + data.requestCode + '.' : '';
						setMessage(contactMessage, data.message + suffix, 'success');
						setMessage(message, data.message + suffix, 'success');
						window.setTimeout(function () {
							if (dialog.open) {
								dialog.close();
							}
						}, 1800);
					})
					.catch(function (error) {
						setMessage(contactMessage, error.message, 'error');
					})
					.finally(function () {
						busy = false;
						contactForm.removeAttribute('aria-busy');
						resetFormRecaptcha(contactForm);
						if (submitButton) {
							submitButton.disabled = false;
						}
					});
			});
		}

		wizard.addEventListener('reset', function () {
			window.setTimeout(function () {
				latestResult = null;
				if (oxide) {
					oxide.value = '';
				}
				showStep(1);
				updateConditionals();
			}, 0);
		});

		updateConditionals();
		updateWhatsApp();
	}

	function boot() {
		toArray(document.querySelectorAll('[data-cadv-tt-calculator]')).forEach(initializeCalculator);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
