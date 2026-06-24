(function () {
	'use strict';

	var cfg = window.blackbeanShop;
	if (!cfg || !cfg.restUrl) {
		return;
	}

	function api(path, body) {
		var opts = {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce,
			},
		};
		if (body) {
			opts.method = 'POST';
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		}
		return fetch(cfg.restUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, ''), opts).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					throw new Error((data && data.message) || cfg.strings.error);
				}
				return data;
			});
		});
	}

	function fetchCart() {
		return api('cart');
	}

	function cartCountLabel(count) {
		if (count === 1) {
			return (cfg.strings.cartItem || 'Cart, %d item').replace('%d', String(count));
		}
		if (count > 1) {
			return (cfg.strings.cartItems || 'Cart, %d items').replace('%d', String(count));
		}
		return cfg.strings.cartLabel || 'Cart';
	}

	function formatSubtotal(label) {
		return (cfg.strings.subtotalFmt || 'Subtotal: %s').replace('%s', label);
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function renderPanelBody(cart) {
		if (!cart || !cart.items || !cart.items.length) {
			return '<p class="bb-header-cart__empty">' + escapeHtml(cfg.strings.emptyCart) + '</p>';
		}
		var html = '<ul class="bb-header-cart__list">';
		var max = 5;
		var i;
		for (i = 0; i < cart.items.length && i < max; i++) {
			var item = cart.items[i];
			html +=
				'<li class="bb-header-cart__line">' +
				'<a class="bb-header-cart__line-title" href="' +
				escapeHtml(item.url) +
				'">' +
				escapeHtml(item.title) +
				'</a>' +
				'<span class="bb-header-cart__line-meta">' +
				escapeHtml(item.price_label + ' × ' + item.qty) +
				'</span></li>';
		}
		html += '</ul>';
		var extra = cart.items.length - max;
		if (extra > 0) {
			var moreTpl = extra === 1 ? cfg.strings.moreItem : cfg.strings.moreItems;
			html += '<p class="bb-header-cart__more">' + escapeHtml(moreTpl.replace('%d', String(extra))) + '</p>';
		}
		html +=
			'<div class="bb-header-cart__footer">' +
			'<p class="bb-header-cart__subtotal">' +
			escapeHtml(formatSubtotal(cart.subtotal_label)) +
			'</p>' +
			'<div class="bb-header-cart__actions">' +
			'<a class="bb-header-cart__link" href="' +
			escapeHtml(cfg.cartUrl) +
			'">' +
			escapeHtml(cfg.strings.viewCart) +
			'</a>' +
			'<a class="bb-header-cart__link bb-header-cart__link--primary" href="' +
			escapeHtml(cfg.checkoutUrl) +
			'">' +
			escapeHtml(cfg.strings.checkout) +
			'</a></div></div>';
		return html;
	}

	function getHeaderCartPanel(root) {
		var widgetId = root.id || 'bb-header-cart-widget';
		return document.querySelector('[data-bb-header-cart-panel][data-bb-header-cart-for="' + widgetId + '"]');
	}

	function mountHeaderCartPanel(root) {
		var panel = getHeaderCartPanel(root);
		if (!panel || panel.parentElement === document.body) {
			return panel;
		}
		document.body.appendChild(panel);
		return panel;
	}

	function updateHeaderCart(cart) {
		if (!cart) {
			return;
		}
		var count = parseInt(cart.count, 10) || 0;
		document.querySelectorAll('[data-bb-header-cart]').forEach(function (root) {
			var badge = root.querySelector('[data-bb-cart-badge]');
			var trigger = root.querySelector('.bb-header-cart__trigger');
			var panel = mountHeaderCartPanel(root);
			var body = panel ? panel.querySelector('[data-bb-cart-panel-body]') : null;
			if (badge) {
				if (count > 0) {
					badge.hidden = false;
					badge.textContent = count > 99 ? '99+' : String(count);
				} else {
					badge.hidden = true;
					badge.textContent = '';
				}
			}
			if (trigger) {
				trigger.setAttribute('aria-label', cartCountLabel(count));
			}
			if (body) {
				body.innerHTML = renderPanelBody(cart);
			}
		});
		syncHeaderCartPanels();
	}

	function positionHeaderCartPanel(root) {
		var trigger = root.querySelector('.bb-header-cart__trigger');
		var panel = mountHeaderCartPanel(root);
		if (!trigger || !panel) {
			return;
		}
		var rect = trigger.getBoundingClientRect();
		var panelWidth = panel.offsetWidth || 288;
		var left = rect.right - panelWidth;

		panel.style.position = 'fixed';
		panel.style.top = Math.round(rect.bottom + 8) + 'px';
		panel.style.left = Math.round(Math.max(8, left)) + 'px';
		panel.style.right = 'auto';
		panel.style.bottom = 'auto';
	}

	function syncHeaderCartPanels() {
		document.querySelectorAll('[data-bb-header-cart]').forEach(positionHeaderCartPanel);
	}

	function setHeaderCartOpen(root, open) {
		var panel = mountHeaderCartPanel(root);
		if (open) {
			root.classList.add('bb-header-cart--open');
			if (panel) {
				panel.classList.add('is-visible');
			}
			positionHeaderCartPanel(root);
			return;
		}
		root.classList.remove('bb-header-cart--open');
		if (panel) {
			panel.classList.remove('is-visible');
		}
	}

	function initHeaderCart() {
		document.querySelectorAll('[data-bb-header-cart]').forEach(function (root) {
			var panel = mountHeaderCartPanel(root);
			var hideTimer = null;

			function openCart() {
				if (hideTimer) {
					clearTimeout(hideTimer);
					hideTimer = null;
				}
				setHeaderCartOpen(root, true);
			}

			function scheduleClose() {
				if (hideTimer) {
					clearTimeout(hideTimer);
				}
				hideTimer = setTimeout(function () {
					setHeaderCartOpen(root, false);
					hideTimer = null;
				}, 120);
			}

			var refresh = function () {
				positionHeaderCartPanel(root);
				fetchCart().then(updateHeaderCart).catch(function () {});
			};

			root.addEventListener('mouseenter', function () {
				openCart();
				refresh();
			});
			root.addEventListener('mouseleave', scheduleClose);
			if (panel) {
				panel.addEventListener('mouseenter', openCart);
				panel.addEventListener('mouseleave', scheduleClose);
			}
			root.addEventListener('focusin', function () {
				openCart();
				refresh();
			});
			root.addEventListener('focusout', function (e) {
				var next = e.relatedTarget;
				if (!root.contains(next) && (!panel || !panel.contains(next))) {
					setHeaderCartOpen(root, false);
				}
			});
			positionHeaderCartPanel(root);
		});
		window.addEventListener('resize', syncHeaderCartPanels);
		window.addEventListener('scroll', syncHeaderCartPanels, true);
		fetchCart().then(updateHeaderCart).catch(function () {});
	}

	function showAddNotice(notice, success) {
		if (!notice) {
			return;
		}
		notice.hidden = false;
		notice.innerHTML = '';
		if (!success) {
			return;
		}
		var msg = document.createElement('span');
		msg.className = 'text-sm text-stone-600 dark:text-stone-400';
		msg.textContent = cfg.strings.added;
		var viewCart = document.createElement('a');
		viewCart.href = cfg.cartUrl;
		viewCart.className = cfg.viewCartButtonClass || 'blackbean-btn blackbean-btn--secondary';
		viewCart.textContent = cfg.strings.viewCart;
		notice.appendChild(msg);
		notice.appendChild(viewCart);
	}

	document.querySelectorAll('.blackbean-shop-add').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var id = parseInt(form.getAttribute('data-product-id'), 10);
			var qtyInput = form.querySelector('[name="qty"]');
			var qty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
			var notice = form.parentElement.querySelector('.blackbean-shop-add-notice');
			var btn = form.querySelector('[type="submit"]');
			if (notice) {
				notice.hidden = true;
				notice.innerHTML = '';
			}
			if (btn) {
				btn.disabled = true;
			}
			api('cart', { action: 'add', product_id: id, qty: qty || 1 })
				.then(function (cart) {
					showAddNotice(notice, true);
					updateHeaderCart(cart);
				})
				.catch(function (err) {
					if (notice) {
						notice.hidden = false;
						notice.textContent = err.message || cfg.strings.error;
					}
				})
				.finally(function () {
					if (btn) {
						btn.disabled = false;
					}
				});
		});
	});

	function showCartAlert(page, message) {
		var alert = page.querySelector('[data-bb-cart-alert]');
		if (!alert) {
			return;
		}
		if (!message) {
			alert.hidden = true;
			alert.textContent = '';
			return;
		}
		alert.hidden = false;
		alert.textContent = message;
	}

	function cartLineHtml(item) {
		var maxAttr = item.stock >= 0 && item.stock > 0 ? ' max="' + String(item.stock) + '"' : '';
		var inputClass = cfg.inputClass || '';
		return (
			'<li class="bb-cart-line flex flex-wrap items-center justify-between gap-4 p-4" data-bb-cart-line data-product-id="' +
			String(item.id) +
			'">' +
			'<div class="min-w-0 flex-1">' +
			'<a class="font-semibold hover:text-brand-600 dark:hover:text-brand-400" href="' +
			escapeHtml(item.url) +
			'">' +
			escapeHtml(item.title) +
			'</a>' +
			'<p class="mt-1 text-sm text-stone-500 dark:text-stone-400">' +
			'<span data-bb-unit-price>' +
			escapeHtml(item.price_label) +
			'</span> ' +
			escapeHtml(cfg.strings.each || 'each') +
			'</p></div>' +
			'<div class="flex flex-wrap items-center gap-3 sm:gap-4">' +
			'<div class="bb-cart-qty" data-bb-cart-qty>' +
			'<button type="button" class="bb-cart-qty__btn" data-bb-qty-dec aria-label="' +
			escapeHtml(cfg.strings.decreaseQty || 'Decrease quantity') +
			'">−</button>' +
			'<label class="sr-only" for="bb-qty-' +
			String(item.id) +
			'">' +
			escapeHtml(cfg.strings.quantity || 'Quantity') +
			'</label>' +
			'<input type="number" class="' +
			escapeHtml(inputClass) +
			' bb-cart-qty__input" id="bb-qty-' +
			String(item.id) +
			'" data-bb-qty-input value="' +
			String(item.qty) +
			'" min="1"' +
			maxAttr +
			' />' +
			'<button type="button" class="bb-cart-qty__btn" data-bb-qty-inc aria-label="' +
			escapeHtml(cfg.strings.increaseQty || 'Increase quantity') +
			'">+</button></div>' +
			'<p class="min-w-[5rem] text-right font-medium" data-bb-line-total>' +
			escapeHtml(item.line_label) +
			'</p>' +
			'<button type="button" class="bb-cart-remove text-sm font-medium text-stone-500 underline-offset-2 hover:text-red-600 hover:underline dark:text-stone-400 dark:hover:text-red-400" data-bb-cart-remove>' +
			escapeHtml(cfg.strings.remove || 'Remove') +
			'</button></div></li>'
		);
	}

	function renderCartPage(cart) {
		var page = document.querySelector('[data-bb-cart-page]');
		if (!page) {
			return;
		}
		showCartAlert(page, '');
		if (!cart || !cart.items || !cart.items.length) {
			page.innerHTML =
				'<div data-bb-cart-empty><p class="text-stone-600 dark:text-stone-400">' +
				escapeHtml(cfg.strings.emptyCart) +
				'</p><a class="' +
				escapeHtml(cfg.primaryButtonClass || 'blackbean-btn blackbean-btn--primary') +
				' mt-4 inline-flex" href="' +
				escapeHtml(cfg.shopUrl || cfg.cartUrl) +
				'">' +
				escapeHtml(cfg.strings.goToShop || 'Go to shop') +
				'</a></div>';
			return;
		}
		var lines = '';
		var i;
		for (i = 0; i < cart.items.length; i++) {
			lines += cartLineHtml(cart.items[i]);
		}
		page.innerHTML =
			'<div data-bb-cart-alert class="bb-shop-cart-alert" hidden role="alert"></div>' +
			'<div class="bb-card overflow-hidden" data-bb-cart-panel>' +
			'<ul class="divide-y divide-stone-200 dark:divide-stone-700" data-bb-cart-lines>' +
			lines +
			'</ul>' +
			'<div class="flex flex-wrap items-center justify-between gap-4 border-t border-stone-200 bg-stone-50/80 p-4 dark:border-stone-700 dark:bg-stone-900/50" data-bb-cart-footer>' +
			'<p class="text-lg font-semibold">' +
			escapeHtml(cfg.strings.subtotal || 'Subtotal') +
			': <span data-bb-cart-subtotal>' +
			escapeHtml(cart.subtotal_label) +
			'</span></p>' +
			'<a class="' +
			escapeHtml(cfg.primaryButtonClass || 'blackbean-btn blackbean-btn--primary') +
			'" href="' +
			escapeHtml(cfg.checkoutUrl) +
			'">' +
			escapeHtml(cfg.strings.checkout) +
			'</a></div></div>';
	}

	function setLineBusy(line, busy) {
		if (!line) {
			return;
		}
		line.classList.toggle('bb-cart-line--busy', busy);
		line.querySelectorAll('button, input').forEach(function (el) {
			el.disabled = busy;
		});
	}

	function applyCartToPage(cart) {
		renderCartPage(cart);
		updateHeaderCart(cart);
	}

	function cartSetQty(productId, qty, line) {
		setLineBusy(line, true);
		showCartAlert(document.querySelector('[data-bb-cart-page]'), '');
		return api('cart', { action: 'set', product_id: productId, qty: qty })
			.then(applyCartToPage)
			.catch(function (err) {
				showCartAlert(document.querySelector('[data-bb-cart-page]'), err.message || cfg.strings.error);
				return fetchCart().then(function (fresh) {
					applyCartToPage(fresh);
				});
			})
			.finally(function () {
				setLineBusy(line, false);
			});
	}

	function cartRemove(productId, line) {
		setLineBusy(line, true);
		return api('cart', { action: 'remove', product_id: productId })
			.then(applyCartToPage)
			.catch(function (err) {
				showCartAlert(document.querySelector('[data-bb-cart-page]'), err.message || cfg.strings.error);
			})
			.finally(function () {
				setLineBusy(line, false);
			});
	}

	function parseQtyInput(input) {
		var val = parseInt(input.value, 10);
		if (!val || val < 1) {
			val = 1;
		}
		var max = parseInt(input.getAttribute('max'), 10);
		if (max > 0 && val > max) {
			val = max;
			input.value = String(val);
		}
		return val;
	}

	function initCartPage() {
		var page = document.querySelector('[data-bb-cart-page]');
		if (!page) {
			return;
		}
		var debounceTimers = {};

		page.addEventListener('click', function (e) {
			var dec = e.target.closest('[data-bb-qty-dec]');
			var inc = e.target.closest('[data-bb-qty-inc]');
			var removeBtn = e.target.closest('[data-bb-cart-remove]');
			var line = e.target.closest('[data-bb-cart-line]');
			if (!line) {
				return;
			}
			var productId = parseInt(line.getAttribute('data-product-id'), 10);
			var input = line.querySelector('[data-bb-qty-input]');
			if (!input || !productId) {
				return;
			}
			if (removeBtn) {
				e.preventDefault();
				cartRemove(productId, line);
				return;
			}
			if (!dec && !inc) {
				return;
			}
			e.preventDefault();
			var qty = parseQtyInput(input);
			if (dec) {
				qty = Math.max(1, qty - 1);
			} else {
				var max = parseInt(input.getAttribute('max'), 10);
				qty = max > 0 ? Math.min(max, qty + 1) : qty + 1;
			}
			input.value = String(qty);
			cartSetQty(productId, qty, line);
		});

		page.addEventListener('change', function (e) {
			var input = e.target.closest('[data-bb-qty-input]');
			if (!input) {
				return;
			}
			var line = input.closest('[data-bb-cart-line]');
			var productId = line ? parseInt(line.getAttribute('data-product-id'), 10) : 0;
			if (!line || !productId) {
				return;
			}
			var qty = parseQtyInput(input);
			cartSetQty(productId, qty, line);
		});

		page.addEventListener('input', function (e) {
			var input = e.target.closest('[data-bb-qty-input]');
			if (!input) {
				return;
			}
			var line = input.closest('[data-bb-cart-line]');
			var productId = line ? parseInt(line.getAttribute('data-product-id'), 10) : 0;
			if (!line || !productId) {
				return;
			}
			clearTimeout(debounceTimers[productId]);
			debounceTimers[productId] = setTimeout(function () {
				var qty = parseQtyInput(input);
				cartSetQty(productId, qty, line);
			}, 450);
		});
	}

	initHeaderCart();
	initCartPage();

	if (document.querySelector('[data-bb-checkout-success]')) {
		api('cart', { action: 'clear' })
			.then(updateHeaderCart)
			.catch(function () {});
	}
})();
