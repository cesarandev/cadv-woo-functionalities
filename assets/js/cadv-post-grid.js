(function () {
	'use strict';

	var config = window.CadvPostGrid || {};

	function encodeForm(data) {
		var body = new URLSearchParams();

		Object.keys(data).forEach(function (key) {
			body.append(key, data[key]);
		});

		return body;
	}

	function initGrid(root) {
		var grid = root.querySelector('[data-cadv-blog-grid]');
		var moreButton = root.querySelector('[data-cadv-blog-more]');
		var count = root.querySelector('[data-cadv-blog-count]');
		var message = root.querySelector('[data-cadv-blog-message]');
		var filterButtons = root.querySelectorAll('.cadv-blog__filter');
		var requestController = null;

		if (!grid || !moreButton) {
			return;
		}

		function setLoading(isLoading, mode) {
			root.classList.toggle('is-loading', isLoading);
			root.setAttribute('aria-busy', isLoading ? 'true' : 'false');
			moreButton.disabled = isLoading;

			Array.prototype.forEach.call(filterButtons, function (button) {
				button.disabled = isLoading;
			});

			if (isLoading) {
				message.textContent = root.getAttribute('data-loading-label') || 'Cargando artículos...';
				message.hidden = false;

				if (mode === 'filter') {
					grid.classList.add('is-refreshing');
				}
			} else {
				if (!message.classList.contains('is-error')) {
					message.hidden = true;
				}
				grid.classList.remove('is-refreshing');
			}
		}

		function setActiveFilter(category) {
			Array.prototype.forEach.call(filterButtons, function (button) {
				var isActive = button.getAttribute('data-category') === String(category);
				button.classList.toggle('is-active', isActive);
				button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
			});
		}

		function showError(error) {
			message.textContent = error && error.message ?
				error.message :
				(root.getAttribute('data-error-label') || 'No fue posible cargar los artículos.');
			message.hidden = false;
			message.classList.add('is-error');
		}

		function load(page, category, mode) {
			if (requestController) {
				requestController.abort();
			}

			requestController = window.AbortController ? new AbortController() : null;
			message.classList.remove('is-error');
			setLoading(true, mode);

			return fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: encodeForm({
					action: config.action || 'cadv_load_blog_posts',
					nonce: config.nonce || '',
					page: page,
					category: category,
					per_page: root.getAttribute('data-per-page') || '6',
					excerpt_words: root.getAttribute('data-excerpt-words') || '18',
					order: root.getAttribute('data-order') || 'DESC',
					orderby: root.getAttribute('data-orderby') || 'date',
					categories: root.getAttribute('data-categories') || ''
				}),
				signal: requestController ? requestController.signal : undefined
			})
				.then(function (response) {
					return response.json().then(function (payload) {
						if (!response.ok || !payload.success) {
							throw new Error(payload.data && payload.data.message ? payload.data.message : '');
						}

						return payload.data;
					});
				})
				.then(function (data) {
					if (mode === 'more') {
						grid.insertAdjacentHTML('beforeend', data.html);
					} else {
						grid.innerHTML = data.html;
					}

					root.setAttribute('data-page', data.page);
					root.setAttribute('data-max-pages', data.maxPages);
					root.setAttribute('data-category', category);
					moreButton.hidden = data.page >= data.maxPages;
					count.textContent = data.countText;
				})
				.catch(function (error) {
					if (error.name !== 'AbortError') {
						showError(error);
					}
				})
				.finally(function () {
					setLoading(false, mode);
					requestController = null;
				});
		}

		Array.prototype.forEach.call(filterButtons, function (button) {
			button.addEventListener('click', function () {
				var category = button.getAttribute('data-category') || '0';

				if (category === root.getAttribute('data-category')) {
					return;
				}

				setActiveFilter(category);
				load(1, category, 'filter');
			});
		});

		moreButton.addEventListener('click', function () {
			var page = parseInt(root.getAttribute('data-page') || '1', 10) + 1;
			var category = root.getAttribute('data-category') || '0';

			load(page, category, 'more');
		});
	}

	Array.prototype.forEach.call(document.querySelectorAll('[data-cadv-blog]'), initGrid);
}());
