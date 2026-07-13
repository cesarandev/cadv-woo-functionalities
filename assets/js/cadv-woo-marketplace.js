(function () {
	'use strict';

	var config = window.CADVWooMarketplace || {};

	function getMessage(key, fallback) {
		return config.messages && config.messages[key] ? config.messages[key] : fallback;
	}

	function postProducts(formData) {
		return fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json();
		});
	}

	function setStatus(root, text, type) {
		var status = root.querySelector('[data-cadv-marketplace-status]');

		if (!status) {
			return;
		}

		status.textContent = text || '';
		status.hidden = !text;
		status.className = 'cadv-marketplace__status';

		if (type) {
			status.classList.add('is-' + type);
		}
	}

	function setActiveLine(root, categoryId) {
		var buttons = root.querySelectorAll('[data-cadv-marketplace-line]');

		Array.prototype.forEach.call(buttons, function (button) {
			button.classList.toggle('is-active', String(button.getAttribute('data-cadv-marketplace-line')) === String(categoryId));
		});
	}

	function initMarketplace(root) {
		var grid = root.querySelector('[data-cadv-marketplace-grid]');
		var summary = root.querySelector('[data-cadv-marketplace-summary]');
		var search = root.querySelector('[data-cadv-marketplace-search]');
		var ica = root.querySelector('[data-cadv-marketplace-ica]');
		var clear = root.querySelector('[data-cadv-marketplace-clear]');
		var loadMore = root.querySelector('[data-cadv-marketplace-load-more]');
		var lineButtons = root.querySelectorAll('[data-cadv-marketplace-line]');
		var debounceTimer = null;
		var state = {
			category: '',
			search: '',
			hasIca: false,
			page: 1,
			maxPages: loadMore && !loadMore.hidden ? 2 : 1,
			perPage: parseInt(root.getAttribute('data-per-page'), 10) || 12
		};

		if (!grid || !summary) {
			return;
		}

		function setLoading(isLoading) {
			root.classList.toggle('is-loading', isLoading);

			if (loadMore) {
				loadMore.disabled = isLoading;
			}
		}

		function buildFormData() {
			var formData = new FormData();

			formData.append('action', config.action || 'cadv_marketplace_products');
			formData.append('nonce', config.nonce || '');
			formData.append('category', state.category || '');
			formData.append('search', state.search || '');
			formData.append('has_ica', state.hasIca ? '1' : '0');
			formData.append('page', String(state.page));
			formData.append('per_page', String(state.perPage));

			return formData;
		}

		function loadProducts(append) {
			setLoading(true);
			setStatus(root, getMessage('loading', 'Cargando productos...'), 'loading');

			postProducts(buildFormData())
				.then(function (response) {
					var data = response && response.data ? response.data : {};

					if (!response || !response.success) {
						throw new Error(data.message || getMessage('error', 'No se pudieron cargar los productos. Intentalo de nuevo.'));
					}

					if (append) {
						grid.insertAdjacentHTML('beforeend', data.html || '');
					} else {
						grid.innerHTML = data.html || '';
					}

					summary.textContent = data.summary || '';
					state.page = parseInt(data.page, 10) || state.page;
					state.maxPages = parseInt(data.maxPages, 10) || 1;

					if (loadMore) {
						loadMore.hidden = !data.has_more;
					}

					if (!data.html && !append) {
						setStatus(root, getMessage('empty', 'No encontramos productos con estos filtros.'), 'empty');
					} else {
						setStatus(root, '', '');
					}
				})
				.catch(function (error) {
					setStatus(root, error.message || getMessage('error', 'No se pudieron cargar los productos. Intentalo de nuevo.'), 'error');
				})
				.finally(function () {
					setLoading(false);
				});
		}

		function reloadFromFirstPage() {
			state.page = 1;
			loadProducts(false);
		}

		Array.prototype.forEach.call(lineButtons, function (button) {
			button.addEventListener('click', function () {
				var categoryId = button.getAttribute('data-cadv-marketplace-line') || '';

				state.category = state.category === categoryId ? '' : categoryId;
				setActiveLine(root, state.category);
				reloadFromFirstPage();
			});
		});

		if (search) {
			search.addEventListener('input', function () {
				window.clearTimeout(debounceTimer);
				debounceTimer = window.setTimeout(function () {
					state.search = search.value.trim();
					reloadFromFirstPage();
				}, 320);
			});
		}

		if (ica) {
			ica.addEventListener('change', function () {
				state.hasIca = Boolean(ica.checked);
				reloadFromFirstPage();
			});
		}

		if (clear) {
			clear.addEventListener('click', function () {
				state.category = '';
				state.search = '';
				state.hasIca = false;
				state.page = 1;

				if (search) {
					search.value = '';
				}

				if (ica) {
					ica.checked = false;
				}

				setActiveLine(root, '');
				loadProducts(false);
			});
		}

		if (loadMore) {
			loadMore.addEventListener('click', function () {
				if (state.page >= state.maxPages) {
					loadMore.hidden = true;
					return;
				}

				state.page += 1;
				loadProducts(true);
			});
		}
	}

	function initAll() {
		var marketplaces = document.querySelectorAll('[data-cadv-marketplace]');

		Array.prototype.forEach.call(marketplaces, initMarketplace);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
}());
