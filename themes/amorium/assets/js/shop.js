/*
 * Storefront scripts of the AmoriumCms.Shop plugin. They talk to the REST API of the
 * active site; window.shopConfig is printed by partials/shop/config.htm. Each page marks
 * its root with data-shop-page and gets its module from the pages map at the bottom.
 */
(() => {
    const config = window.shopConfig;
    if (!config) {
        return;
    }

    // Helpers

    const t = (text, params = {}) => Object.entries(params).reduce(
        (result, [key, value]) => result.split(':' + key).join(value),
        config.messages[text] ?? text
    );

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);

    const money = (amount, currency = config.currency) => {
        if (amount === null || amount === undefined) {
            return '';
        }
        const options = currency ? { style: 'currency', currency } : { minimumFractionDigits: 2, maximumFractionDigits: 2 };
        return new Intl.NumberFormat(config.locale, options).format(amount);
    };

    const date = (value, withTime = false) => value
        ? new Intl.DateTimeFormat(config.locale, withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(new Date(value))
        : '';

    const statusLabel = (code) => t(code.charAt(0).toUpperCase() + code.slice(1).replace(/_/g, ' '));

    const badgeColors = {
        pending_payment: 'bg-amber-100 text-amber-800',
        pending: 'bg-amber-100 text-amber-800',
        processing: 'bg-blue-100 text-blue-800',
        shipped: 'bg-violet-100 text-violet-800',
        delivered: 'bg-green-100 text-green-800',
        paid: 'bg-green-100 text-green-800',
        completed: 'bg-green-100 text-green-800',
        approved: 'bg-green-100 text-green-800',
        in_stock: 'bg-green-100 text-green-800',
        low_stock: 'bg-amber-100 text-amber-800',
        refunded: 'bg-orange-100 text-orange-800',
        payment_not_made: 'bg-gray-100 text-gray-700',
        cancelled: 'bg-red-100 text-red-800',
        rejected: 'bg-red-100 text-red-800',
        failed: 'bg-red-100 text-red-800',
        out_of_stock: 'bg-red-100 text-red-800',
    };

    const badge = (status) => `<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${badgeColors[status] || 'bg-gray-100 text-gray-700'}">${esc(statusLabel(status))}</span>`;

    const stars = (rating) => {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            html += `<i class="${rating >= i ? 'ri-star-fill' : (rating >= i - 0.5 ? 'ri-star-half-line' : 'ri-star-line')}" aria-hidden="true"></i>`;
        }
        return `<span class="inline-flex items-center text-amber-400" role="img" aria-label="${esc(t(':rating out of 5', { rating }))}">${html}</span>`;
    };

    const pageUrl = (name, params = {}) => Object.entries(params).reduce(
        (url, [key, value]) => url.replace('__' + key + '__', encodeURIComponent(value)),
        config.urls[name]
    );

    const notify = (message, type = 'success') => {
        if (!message) {
            return;
        }
        if (window.oc?.flashMsg) {
            window.oc.flashMsg({ message, type, interval: 4 });
        }
        else {
            window.alert(message);
        }
    };

    const loading = () => `<p class="flex items-center gap-2 py-12 text-muted" role="status"><i class="ri-loader-4-line animate-spin text-xl" aria-hidden="true"></i> ${esc(t('Loading…'))}</p>`;

    const emptyState = (icon, message, action = '') => `
        <div class="rounded-md border border-dashed border-line p-12 text-center dark:border-night-line">
            <i class="${icon} text-5xl text-muted" aria-hidden="true"></i>
            <p class="mt-4 text-lg">${esc(message)}</p>
            ${action}
        </div>`;

    const image = (img, alt = '', size = 'size-20') => img
        ? `<img src="${esc(img.thumb || img.url)}" alt="${esc(alt)}" loading="lazy" class="${size} shrink-0 rounded-md object-cover" />`
        : `<span class="${size} flex shrink-0 items-center justify-center rounded-md bg-page text-2xl text-muted dark:bg-night-page"><i class="ri-image-line"></i></span>`;

    const variantText = (variant) => Object.entries(variant || {}).map(([name, value]) => `${esc(name)}: ${esc(value)}`).join(' · ');

    const setBusy = (button, busy) => {
        if (button) {
            button.disabled = busy;
            button.setAttribute('aria-busy', busy ? 'true' : 'false');
        }
    };

    const uuid = () => window.crypto?.randomUUID
        ? window.crypto.randomUUID()
        : 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2);

    // Validation errors arrive as { "shipping_address.city": ["..."] } and are shown in data-shop-error elements

    const clearErrors = (root) => root.querySelectorAll('[data-shop-error]').forEach((el) => { el.textContent = ''; });

    const showErrors = (root, errors) => {
        let first = null;
        Object.entries(errors || {}).forEach(([field, messages]) => {
            const el = root.querySelector(`[data-shop-error="${CSS.escape(field)}"]`);
            if (el) {
                el.textContent = [].concat(messages)[0];
                first ??= el;
            }
        });
        first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return !!first;
    };

    // API client

    const cartToken = {
        key: 'shop.cart.' + config.site,
        get() {
            try { return localStorage.getItem(this.key); } catch (e) { return null; }
        },
        set(value) {
            try { value ? localStorage.setItem(this.key, value) : localStorage.removeItem(this.key); } catch (e) { /* storage is disabled */ }
        },
    };

    class ApiError extends Error {
        constructor(body, status) {
            super(body?.message || t('Something went wrong. Please try again later.'));
            this.code = body?.code || 'SERVER_ERROR';
            this.status = status;
            this.data = body?.data ?? null;
            this.errors = body?.errors || {};
        }
    }

    let tokenRequest = null;

    // A page can stay open longer than the token lives; the layout component issues a new one
    const renewToken = () => tokenRequest ??= new Promise((resolve) => {
        if (!window.oc?.ajax) {
            resolve(null);
            return;
        }
        window.oc.ajax('shopConfig::onShopToken', {
            success: (data) => resolve(data?.token || null),
            error: () => resolve(null),
        });
    }).finally(() => { tokenRequest = null; });

    const api = async (method, path, body, headers = {}, retried = false) => {
        const requestHeaders = { Accept: 'application/json', ...headers };
        if (body !== undefined) {
            requestHeaders['Content-Type'] = 'application/json';
        }
        if (config.token) {
            requestHeaders.Authorization = 'Bearer ' + config.token;
        }
        const token = cartToken.get();
        if (token) {
            requestHeaders['X-Cart-Token'] = token;
        }

        let response;
        try {
            response = await fetch(config.api + '/' + path, {
                method,
                headers: requestHeaders,
                body: body !== undefined ? JSON.stringify(body) : undefined,
                credentials: 'same-origin',
            });
        }
        catch (e) {
            throw new ApiError({ code: 'NETWORK_ERROR', message: t('Could not connect. Please check your connection.') }, 0);
        }

        const json = await response.json().catch(() => null);

        if (response.status === 401 && config.token && !retried && ['TOKEN_EXPIRED', 'TOKEN_INVALID'].includes(json?.code)) {
            config.token = await renewToken();
            if (!config.token) {
                window.location.reload();
            }
            return api(method, path, body, headers, true);
        }

        if (!response.ok || !json || json.success === false) {
            if (json?.code === 'CART_NOT_FOUND') {
                cartToken.set(null);
            }
            throw new ApiError(json, response.status);
        }

        return json;
    };

    // Cart counter in the header

    const updateCartCount = (count) => {
        document.querySelectorAll('[data-shop-cart-count]').forEach((el) => {
            el.textContent = count > 99 ? '99+' : String(count);
            el.classList.toggle('hidden', !count);
        });
    };

    const rememberCart = (cart) => {
        if (cart?.token && !config.token) {
            cartToken.set(cart.token);
        }
        updateCartCount(cart?.item_count || 0);
        return cart;
    };

    const loadCartCount = async () => {
        if (!config.token && !cartToken.get()) {
            return;
        }
        try {
            rememberCart((await api('GET', 'cart')).data);
        }
        catch (e) {
            updateCartCount(0);
        }
    };

    const addToCart = async (productId, quantity = 1) => {
        const cart = rememberCart((await api('POST', 'cart/items', { product_id: productId, quantity })).data);
        notify(t('The product has been added to your cart.'));
        return cart;
    };

    // Errors that point at the cart, such as OUT_OF_STOCK, list the affected barcodes
    const cartErrorMessage = (error) => {
        if (error.code === 'OUT_OF_STOCK' && error.data?.items?.length) {
            return t('Some products are not available in the requested quantity.');
        }
        return error.message;
    };

    // Address helpers shared by the checkout and the address book

    const readFields = (container) => {
        const data = {};
        container.querySelectorAll('[data-field]').forEach((field) => {
            if (field.closest('[data-shop-billing-fields].hidden')) {
                return;
            }
            data[field.dataset.field] = field.type === 'checkbox' ? field.checked : field.value.trim();
        });
        return data;
    };

    const fillFields = (container, values = {}) => {
        container.querySelectorAll('[data-field]').forEach((field) => {
            const value = values[field.dataset.field];
            if (field.type === 'checkbox') {
                field.checked = !!value;
            }
            else {
                field.value = value ?? (field.tagName === 'SELECT' ? field.options[0]?.value : '');
            }
        });
    };

    const addressText = (address) => [
        [address.first_name, address.last_name].filter(Boolean).join(' '),
        address.company_name,
        address.address_line,
        [address.district, address.city, address.postal_code].filter(Boolean).join(' / '),
        address.phone,
        address.tax_number ? t('Tax number: :number', { number: address.tax_number }) + (address.tax_office ? ` (${address.tax_office})` : '') : null,
    ].filter(Boolean).map(esc).join('<br>');

    // The company fields of the address book form are only shown for billing addresses
    const bindAddressType = (form) => {
        const type = form.querySelector('[data-field="type"]');
        const billing = form.querySelector('[data-shop-billing-fields]');
        if (type && billing) {
            const sync = () => billing.classList.toggle('hidden', type.value !== 'billing');
            type.addEventListener('change', sync);
            sync();
        }
    };

    // Product page

    const productPage = (root) => {
        const product = JSON.parse(root.querySelector('[data-shop-product]').textContent);
        const variants = product.variants;
        const axes = product.axes;
        const selected = { ...(variants[0]?.options || {}) };
        // The axis the visitor picked last stays open. The other axes only offer barcodes that match it.
        let anchor = axes[0]?.code || null;
        const stock = {};
        let favorites = null;

        const addButton = root.querySelector('[data-shop-add]');
        const qtyInput = root.querySelector('[data-shop-qty]');
        const stockLabel = root.querySelector('[data-shop-stock]');
        const favoriteButton = root.querySelector('[data-shop-favorite]');

        const optionsOf = (variant) => variant.options || {};
        const matches = (variant, choice) => Object.entries(choice).every(([code, option]) => optionsOf(variant)[code] === option);
        const current = () => variants.find((variant) => matches(variant, selected)) || null;

        const optionIds = (variant) => axes.flatMap((axis) => axis.options.filter((option) => optionsOf(variant)[axis.code] === option.code).map((option) => option.id));

        const showImage = (variant) => {
            const main = root.querySelector('[data-shop-main-image]');
            const thumbs = [...root.querySelectorAll('[data-shop-thumb]')];
            if (!main || !thumbs.length) {
                return;
            }
            const ids = optionIds(variant).map(String);
            const thumb = thumbs.find((el) => el.dataset.shopImageOption && ids.includes(el.dataset.shopImageOption));
            if (thumb) {
                selectThumb(thumb);
            }
        };

        const selectThumb = (thumb) => {
            const main = root.querySelector('[data-shop-main-image]');
            if (main) {
                main.src = thumb.dataset.shopThumb;
            }
            root.querySelectorAll('[data-shop-thumb]').forEach((el) => {
                el.classList.toggle('border-primary', el === thumb);
                el.classList.toggle('border-transparent', el !== thumb);
            });
        };

        const renderStock = (variant) => {
            const status = variant ? stock[variant.product_id] : null;
            const labels = {
                in_stock: ['text-success', 'ri-checkbox-circle-line', t('In stock')],
                low_stock: ['text-amber-600', 'ri-error-warning-line', t('Only a few left')],
                out_of_stock: ['text-danger', 'ri-close-circle-line', t('Out of stock')],
            };
            const [color, icon, text] = labels[status] || ['text-muted', '', variant ? '' : t('This combination is not available.')];
            stockLabel.className = `mt-6 text-sm ${color}`;
            stockLabel.innerHTML = text ? `${icon ? `<i class="${icon}" aria-hidden="true"></i> ` : ''}${esc(text)}` : '';
            addButton.disabled = !variant || status === 'out_of_stock';
        };

        const renderFavorite = (variant) => {
            if (!favoriteButton) {
                return;
            }
            const active = !!(variant && favorites?.has(variant.product_id));
            favoriteButton.setAttribute('aria-pressed', active ? 'true' : 'false');
            favoriteButton.setAttribute('aria-label', active ? t('Remove from favorites') : t('Add to favorites'));
            favoriteButton.innerHTML = `<i class="${active ? 'ri-heart-fill text-danger' : 'ri-heart-line'}"></i>`;
        };

        const render = () => {
            const variant = current();

            root.querySelectorAll('[data-shop-axis]').forEach((button) => {
                const code = button.dataset.shopAxis;
                const option = button.dataset.shopOption;
                const active = selected[code] === option;
                const others = Object.fromEntries(Object.entries(selected).filter(([key]) => key !== code));
                // Values on the axis just chosen stay available. A value on another axis is disabled when no barcode joins it to the current choice.
                const possible = code === anchor
                    ? variants.some((item) => optionsOf(item)[code] === option)
                    : variants.some((item) => optionsOf(item)[code] === option && matches(item, others));

                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.disabled = !possible && !active;
                const isColor = button.style.backgroundColor !== '';
                button.classList.toggle('border-primary', active);
                button.classList.toggle(isColor ? 'ring-2' : 'text-primary', active);
                if (isColor) {
                    button.classList.toggle('ring-primary/30', active);
                }
                button.classList.toggle('border-line', !active);
            });

            axes.forEach((axis) => {
                const label = root.querySelector(`[data-shop-axis-label="${CSS.escape(axis.code)}"]`);
                if (label) {
                    label.textContent = axis.options.find((option) => option.code === selected[axis.code])?.label || '';
                }
            });

            if (variant) {
                root.querySelector('[data-shop-price]').textContent = money(variant.price, product.currency);
                const compare = root.querySelector('[data-shop-compare]');
                compare.textContent = money(variant.compare_at_price, product.currency);
                compare.classList.toggle('hidden', !(variant.compare_at_price > variant.price));
                root.querySelector('[data-shop-barcode]').textContent = variant.barcode;
                root.querySelector('[data-shop-variant-specs]').innerHTML = (variant.specs || []).map((row) => `
                    <tr class="border-b border-line dark:border-night-line"><th class="py-2 pe-4 text-start font-normal text-muted">${esc(row.name)}</th><td class="py-2">${esc(row.display)}</td></tr>`).join('');
                showImage(variant);
            }

            renderStock(variant);
            renderFavorite(variant);
        };

        root.querySelectorAll('[data-shop-axis]').forEach((button) => {
            button.addEventListener('click', () => {
                const code = button.dataset.shopAxis;
                anchor = code;
                selected[code] = button.dataset.shopOption;
                // Keep the other choices when possible, otherwise take the first variant with this option
                if (!current()) {
                    const fallback = variants.find((item) => optionsOf(item)[code] === selected[code]);
                    if (fallback) {
                        Object.assign(selected, optionsOf(fallback));
                    }
                }
                render();
            });
        });

        root.querySelectorAll('[data-shop-thumb]').forEach((thumb) => thumb.addEventListener('click', () => selectThumb(thumb)));

        root.querySelectorAll('[data-shop-qty-step]').forEach((button) => {
            button.addEventListener('click', () => {
                qtyInput.value = Math.min(999, Math.max(1, (parseInt(qtyInput.value, 10) || 1) + parseInt(button.dataset.shopQtyStep, 10)));
            });
        });

        addButton.addEventListener('click', async () => {
            const variant = current();
            if (!variant) {
                return;
            }
            setBusy(addButton, true);
            try {
                await addToCart(variant.product_id, Math.max(1, parseInt(qtyInput.value, 10) || 1));
            }
            catch (error) {
                notify(cartErrorMessage(error), 'error');
                if (error.code === 'OUT_OF_STOCK') {
                    await loadStock();
                }
            }
            finally {
                setBusy(addButton, false);
                renderStock(current());
            }
        });

        favoriteButton?.addEventListener('click', async () => {
            const variant = current();
            if (!variant || !favorites) {
                return;
            }
            setBusy(favoriteButton, true);
            try {
                if (favorites.has(variant.product_id)) {
                    await api('DELETE', `account/favorites/${variant.product_id}`);
                    favorites.delete(variant.product_id);
                    notify(t('The product has been removed from your favorites.'));
                }
                else {
                    await api('POST', 'account/favorites', { product_id: variant.product_id });
                    favorites.add(variant.product_id);
                    notify(t('The product has been added to your favorites.'));
                }
            }
            catch (error) {
                notify(error.message, 'error');
            }
            finally {
                setBusy(favoriteButton, false);
                renderFavorite(current());
            }
        });

        const loadStock = async () => {
            try {
                const ids = variants.map((variant) => variant.product_id).slice(0, 100).join(',');
                Object.assign(stock, (await api('GET', `stock?products=${ids}`)).data.products);
            }
            catch (e) {
                // Without the stock status the add button still works; the API checks the stock again
            }
            renderStock(current());
        };

        const loadFavorites = async () => {
            if (!config.token || !favoriteButton) {
                return;
            }
            try {
                favorites = new Set((await api('GET', 'account/favorites')).data.map((item) => item.product_id));
            }
            catch (e) {
                favorites = new Set();
            }
            renderFavorite(current());
        };

        render();
        loadStock();
        loadFavorites();
        reviewsSection(root, product);
    };

    const reviewsSection = (root, product) => {
        const list = root.querySelector('[data-shop-review-list]');
        const more = root.querySelector('[data-shop-review-more]');
        const form = root.querySelector('[data-shop-review-form]');

        const reviewHtml = (review) => `
            <article class="border-b border-line py-5 last:border-0 dark:border-night-line">
                <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                    ${stars(review.rating)}
                    ${review.title ? `<h3 class="text-base font-medium">${esc(review.title)}</h3>` : ''}
                </div>
                ${review.body ? `<p class="text-body dark:text-night-body">${esc(review.body).replace(/\n/g, '<br>')}</p>` : ''}
                <p class="mt-2 flex flex-wrap items-center gap-x-3 text-sm text-muted">
                    <span>${esc(review.author || t('Customer'))}</span>
                    <span>${esc(date(review.created_at))}</span>
                    ${variantText(review.variant) ? `<span>${variantText(review.variant)}</span>` : ''}
                    ${review.is_verified_purchase ? `<span class="text-success"><i class="ri-shield-check-line" aria-hidden="true"></i> ${esc(t('Verified purchase'))}</span>` : ''}
                </p>
            </article>`;

        more?.addEventListener('click', async () => {
            const page = parseInt(more.dataset.page, 10) + 1;
            setBusy(more, true);
            try {
                const result = await api('GET', `products/${encodeURIComponent(product.slug)}/reviews?page=${page}&per_page=5`);
                list.insertAdjacentHTML('beforeend', result.data.map(reviewHtml).join(''));
                more.dataset.page = String(page);
                more.classList.toggle('hidden', page >= result.meta.last_page);
            }
            catch (error) {
                notify(error.message, 'error');
            }
            finally {
                setBusy(more, false);
            }
        });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(form);
            const button = form.querySelector('[type="submit"]');
            const data = new FormData(form);
            setBusy(button, true);
            try {
                await api('POST', `products/${encodeURIComponent(product.slug)}/reviews`, {
                    rating: parseInt(data.get('rating'), 10),
                    title: data.get('title') || null,
                    body: data.get('body') || null,
                });
                form.innerHTML = `<p class="text-success"><i class="ri-checkbox-circle-line" aria-hidden="true"></i> ${esc(t('Thank you! Your review will be published after it is approved.'))}</p>`;
            }
            catch (error) {
                if (!showErrors(form, error.errors)) {
                    notify(error.message, 'error');
                }
            }
            finally {
                setBusy(button, false);
            }
        });
    };

    // Cart page

    const cartPage = (root) => {
        const container = root.querySelector('[data-shop-cart]');

        const render = (cart) => {
            rememberCart(cart);

            if (!cart || !cart.items.length) {
                container.innerHTML = emptyState('ri-shopping-bag-3-line', t('Your cart is empty.'), `<a href="${esc(config.urls.shop)}" class="btn-primary mt-6">${esc(t('Continue shopping'))}</a>`);
                return;
            }

            const unavailable = cart.items.some((item) => !item.available);

            container.innerHTML = `
                <div class="grid gap-8 lg_992:grid-cols-3">
                    <div class="lg_992:col-span-2">
                        <ul class="divide-y divide-line rounded-md bg-surface shadow dark:divide-night-line dark:bg-night-surface">
                            ${cart.items.map((item) => `
                                <li class="flex gap-4 p-4 ${item.available ? '' : 'opacity-60'}" data-product-id="${item.product_id}">
                                    <a href="${esc(pageUrl('product', { slug: item.slug }))}">${image(item.image, item.name)}</a>
                                    <div class="min-w-0 flex-1">
                                        <a href="${esc(pageUrl('product', { slug: item.slug }))}" class="font-medium hover:text-primary">${esc(item.name)}</a>
                                        ${variantText(item.variant) ? `<p class="text-sm text-muted">${variantText(item.variant)}</p>` : ''}
                                        ${item.available ? `<p class="mt-1 text-sm text-muted">${esc(money(item.unit_price, cart.currency))}</p>` : `<p class="mt-1 text-sm text-danger">${esc(t('This product is no longer available.'))}</p>`}
                                        ${item.available && item.stock_status === 'low_stock' ? `<p class="text-sm text-amber-600">${esc(t('Only a few left'))}</p>` : ''}
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            ${item.available ? `
                                                <div class="flex items-center rounded-md border border-line dark:border-night-line">
                                                    <button type="button" class="px-3 py-1" data-step="-1" aria-label="${esc(t('Decrease quantity'))}"><i class="ri-subtract-line"></i></button>
                                                    <input type="number" min="1" max="999" value="${item.quantity}" class="w-12 bg-transparent text-center outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" data-qty aria-label="${esc(t('Quantity'))}" />
                                                    <button type="button" class="px-3 py-1" data-step="1" aria-label="${esc(t('Increase quantity'))}"><i class="ri-add-line"></i></button>
                                                </div>` : ''}
                                            <button type="button" class="text-sm text-danger" data-remove><i class="ri-delete-bin-line" aria-hidden="true"></i> ${esc(t('Remove'))}</button>
                                        </div>
                                    </div>
                                    <p class="font-medium whitespace-nowrap">${item.available ? esc(money(item.line_total, cart.currency)) : ''}</p>
                                </li>`).join('')}
                        </ul>
                        <div class="mt-4 flex justify-between">
                            <a href="${esc(config.urls.shop)}" class="text-sm text-muted hover:text-primary"><i class="ri-arrow-left-line" aria-hidden="true"></i> ${esc(t('Continue shopping'))}</a>
                            <button type="button" class="cursor-pointer text-sm text-muted hover:text-danger" data-clear>${esc(t('Clear cart'))}</button>
                        </div>
                    </div>
                    <aside>
                        <div class="rounded-md bg-surface p-6 shadow dark:bg-night-surface">
                            <h2 class="mb-4 text-xl font-medium">${esc(t('Order summary'))}</h2>
                            <dl class="space-y-2">
                                <div class="flex justify-between"><dt class="text-muted">${esc(t('Subtotal'))}</dt><dd>${esc(money(cart.totals.subtotal, cart.currency))}</dd></div>
                                <div class="flex justify-between border-t border-line pt-2 text-lg font-medium dark:border-night-line"><dt>${esc(t('Total'))}</dt><dd>${esc(money(cart.totals.grand_total, cart.currency))}</dd></div>
                            </dl>
                            <p class="mt-2 text-xs text-muted">${esc(t('VAT included. Shipping is calculated at checkout.'))}</p>
                            ${unavailable ? `<p class="mt-4 text-sm text-danger">${esc(t('Please remove the products that are no longer available.'))}</p>` : ''}
                            <a href="${esc(config.urls.checkout)}" class="btn-primary mt-6 block w-full ${unavailable ? 'pointer-events-none opacity-50' : ''}" ${unavailable ? 'aria-disabled="true"' : ''}>${esc(t('Proceed to checkout'))}</a>
                        </div>
                    </aside>
                </div>`;
        };

        const run = async (request) => {
            container.setAttribute('aria-busy', 'true');
            try {
                render((await request()).data);
            }
            catch (error) {
                notify(cartErrorMessage(error), 'error');
                await load();
            }
            finally {
                container.removeAttribute('aria-busy');
            }
        };

        const load = async () => {
            if (!config.token && !cartToken.get()) {
                render(null);
                return;
            }
            try {
                render((await api('GET', 'cart')).data);
            }
            catch (error) {
                render(null);
                if (error.code !== 'CART_NOT_FOUND') {
                    notify(error.message, 'error');
                }
            }
        };

        const setQuantity = (productId, quantity) => run(() => api('PATCH', `cart/items/${productId}`, { quantity }));

        container.addEventListener('click', (event) => {
            const row = event.target.closest('[data-product-id]');
            if (event.target.closest('[data-clear]')) {
                if (window.confirm(t('Remove all products from your cart?'))) {
                    run(() => api('DELETE', 'cart'));
                }
                return;
            }
            if (!row) {
                return;
            }
            const productId = row.dataset.productId;
            const step = event.target.closest('[data-step]');
            if (step) {
                const input = row.querySelector('[data-qty]');
                setQuantity(productId, Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(step.dataset.step, 10)));
            }
            if (event.target.closest('[data-remove]')) {
                run(() => api('DELETE', `cart/items/${productId}`));
            }
        });

        container.addEventListener('change', (event) => {
            const input = event.target.closest('[data-qty]');
            if (input) {
                setQuantity(input.closest('[data-product-id]').dataset.productId, Math.min(999, Math.max(1, parseInt(input.value, 10) || 1)));
            }
        });

        load();
    };

    // Checkout page

    const checkoutPage = (root) => {
        const form = root.querySelector('[data-shop-checkout]');
        const placeButton = root.querySelector('[data-shop-place]');
        const placeError = root.querySelector('[data-shop-place-error]');
        const billingSame = root.querySelector('[data-shop-billing-same]');
        const billingSection = root.querySelector('[data-shop-billing-section]');
        const idempotencyKey = uuid();
        let state = null;
        let saving = Promise.resolve();

        const isGuest = !config.token;

        const totalsHtml = (cart) => {
            const totals = cart.totals;
            return `
                <ul class="mb-4 space-y-3">
                    ${cart.items.map((item) => `
                        <li class="flex gap-3 ${item.available ? '' : 'opacity-60'}">
                            <span class="relative">${image(item.image, item.name, 'size-14')}<span class="absolute -end-2 -top-2 min-w-5 rounded-full bg-contrast px-1 text-center text-xs leading-5 text-white">${item.quantity}</span></span>
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block font-medium">${esc(item.name)}</span>
                                ${variantText(item.variant) ? `<span class="block text-muted">${variantText(item.variant)}</span>` : ''}
                                ${item.available ? '' : `<span class="block text-danger">${esc(t('This product is no longer available.'))}</span>`}
                            </span>
                            <span class="text-sm whitespace-nowrap">${esc(money(item.line_total, cart.currency))}</span>
                        </li>`).join('')}
                </ul>
                <dl class="space-y-2 border-t border-line pt-4 text-sm dark:border-night-line">
                    <div class="flex justify-between"><dt class="text-muted">${esc(t('Subtotal'))}</dt><dd>${esc(money(totals.subtotal, cart.currency))}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">${esc(t('Shipping'))}</dt><dd>${totals.shipping_total === null ? '—' : (totals.shipping_total > 0 ? esc(money(totals.shipping_total, cart.currency)) : esc(t('Free')))}</dd></div>
                    ${totals.payment_fee > 0 ? `<div class="flex justify-between"><dt class="text-muted">${esc(t('Payment fee'))}</dt><dd>${esc(money(totals.payment_fee, cart.currency))}</dd></div>` : ''}
                    <div class="flex justify-between border-t border-line pt-2 text-lg font-medium dark:border-night-line"><dt>${esc(t('Total'))}</dt><dd>${esc(money(totals.grand_total, cart.currency))}</dd></div>
                    <div class="flex justify-between text-xs text-muted"><dt>${esc(t('Included VAT'))}</dt><dd>${esc(money(totals.tax_total, cart.currency))}</dd></div>
                </dl>`;
        };

        const choice = (name, value, checked, title, detail, extra = '') => `
            <label class="flex cursor-pointer items-start gap-3 rounded-md border p-4 duration-300 has-[:checked]:border-primary ${checked ? 'border-primary' : 'border-line dark:border-night-line'}">
                <input type="radio" name="${esc(name)}" value="${esc(value)}" ${checked ? 'checked' : ''} class="mt-1 accent-primary" />
                <span class="flex-1">
                    <span class="flex justify-between gap-3 font-medium"><span>${title}</span>${extra}</span>
                    ${detail ? `<span class="mt-1 block text-sm text-muted">${detail}</span>` : ''}
                </span>
            </label>`;

        const renderAddresses = () => {
            if (isGuest) {
                return;
            }
            const addresses = state.addresses;
            ['shipping', 'billing'].forEach((type) => {
                const container = root.querySelector(`[data-shop-address-choices="${type}"]`);
                const selectedId = state.selection[`${type}_address_id`];
                container.innerHTML = addresses.length
                    ? addresses.map((address) => choice(
                        `${type}_address_id`,
                        address.id,
                        address.id === selectedId,
                        esc(address.title || statusLabel(address.type === 'billing' ? 'billing_address' : 'shipping_address')),
                        addressText(address)
                    )).join('')
                    : `<p class="text-muted sm:col-span-2">${esc(t('You have no saved addresses yet. Add one below.'))}</p>`;
            });
            if (!addresses.length) {
                root.querySelector('[data-shop-new-address]')?.setAttribute('open', '');
            }
        };

        const renderMethods = () => {
            const shipping = root.querySelector('[data-shop-shipping-methods]');
            shipping.innerHTML = state.shipping_methods.length
                ? state.shipping_methods.map((method) => choice(
                    'shipping_method_id',
                    method.id,
                    method.id === state.selection.shipping_method_id,
                    esc(method.name),
                    [method.description, method.free_over ? t('Free over :amount', { amount: money(method.free_over, method.currency) }) : null].filter(Boolean).map(esc).join(' · '),
                    `<span class="whitespace-nowrap">${method.price > 0 ? esc(money(method.price, method.currency)) : esc(t('Free'))}</span>`
                )).join('')
                : `<p class="text-muted">${esc(t('No shipping method is available.'))}</p>`;

            const payment = root.querySelector('[data-shop-payment-methods]');
            payment.innerHTML = state.payment_methods.length
                ? state.payment_methods.map((method) => choice(
                    'payment_method',
                    method.code,
                    method.code === state.selection.payment_method,
                    esc(method.name),
                    method.description ? esc(method.description) : (method.code === 'bank_transfer' ? esc(t('The bank account details are shown after you place the order.')) : ''),
                    method.fee > 0 ? `<span class="whitespace-nowrap">+${esc(money(method.fee, method.currency))}</span>` : ''
                )).join('')
                : `<p class="text-muted">${esc(t('No payment method is available.'))}</p>`;
        };

        const renderSummary = () => {
            root.querySelector('[data-shop-summary]').innerHTML = totalsHtml(state.cart);
        };

        const showPaymentError = (data) => {
            const params = new URLSearchParams(window.location.search);
            const generic = t('The card payment could not be completed.');
            const gateway = [data?.payment_error, params.get('payment_message')]
                .map((value) => (value || '').trim())
                .find((value) => value && value !== generic) || '';
            const failed = params.has('payment_error') || params.has('payment_message') || !!data?.payment_error;
            const banner = root.querySelector('[data-shop-payment-error]');
            if (!failed && !gateway) {
                return;
            }
            if (banner) {
                banner.replaceChildren();
                const summary = document.createElement('span');
                summary.textContent = generic;
                banner.append(summary);
                if (gateway) {
                    const detail = document.createElement('span');
                    detail.className = 'mt-1 block';
                    detail.textContent = gateway;
                    banner.append(detail);
                }
                banner.classList.remove('hidden');
            }
            if (placeError) {
                placeError.textContent = [generic, gateway].filter(Boolean).join(' ');
            }
            if (!root.dataset.paymentErrorShown) {
                root.dataset.paymentErrorShown = '1';
                banner?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            if (params.has('payment_error') || params.has('payment_message')) {
                params.delete('payment_error');
                params.delete('payment_message');
                const next = params.toString();
                history.replaceState(null, '', window.location.pathname + (next ? `?${next}` : '') + window.location.hash);
            }
        };

        const apply = (data) => {
            state = data;
            rememberCart(data.cart);
            showPaymentError(data);
            if (!data.cart.items.length) {
                root.querySelector('[data-shop-checkout-loading]').classList.add('hidden');
                form.classList.add('hidden');
                form.classList.remove('grid');
                root.querySelector('[data-shop-checkout-empty]').classList.remove('hidden');
                return;
            }
            renderAddresses();
            renderMethods();
            renderSummary();
        };

        const fillGuest = () => {
            if (!isGuest) {
                return;
            }
            const email = form.querySelector('[data-field="email"]');
            if (email && !email.value) {
                email.value = state.email || '';
            }
            const shipping = form.querySelector('[data-shop-address-fields="shipping_address"]');
            const billing = form.querySelector('[data-shop-address-fields="billing_address"]');
            if (state.selection.shipping_address) {
                fillFields(shipping, state.selection.shipping_address);
            }
            if (state.selection.billing_address) {
                fillFields(billing, state.selection.billing_address);
                const same = JSON.stringify(state.selection.billing_address) === JSON.stringify(state.selection.shipping_address);
                billingSame.checked = same;
                billingSection.classList.toggle('hidden', same);
            }
        };

        // Choices that change the totals are saved at once; the guest address is sent when the order is placed
        const quickPayload = () => {
            const payload = {
                shipping_method_id: parseInt(form.querySelector('[name="shipping_method_id"]:checked')?.value, 10) || null,
                payment_method: form.querySelector('[name="payment_method"]:checked')?.value || null,
                customer_note: form.querySelector('[data-field="customer_note"]').value.trim() || null,
            };
            if (isGuest) {
                const email = form.querySelector('[data-field="email"]').value.trim();
                if (email) {
                    payload.email = email;
                }
            }
            else {
                payload.shipping_address_id = parseInt(form.querySelector('[name="shipping_address_id"]:checked')?.value, 10) || null;
                payload.billing_address_id = billingSame.checked
                    ? payload.shipping_address_id
                    : parseInt(form.querySelector('[name="billing_address_id"]:checked')?.value, 10) || null;
            }
            return payload;
        };

        const fullPayload = () => {
            const payload = quickPayload();
            if (isGuest) {
                payload.shipping_address = readFields(form.querySelector('[data-shop-address-fields="shipping_address"]'));
                if (billingSame.checked) {
                    payload.billing_same_as_shipping = true;
                }
                else {
                    payload.billing_address = readFields(form.querySelector('[data-shop-address-fields="billing_address"]'));
                }
            }
            return payload;
        };

        const save = (payload) => {
            saving = saving.then(async () => {
                try {
                    apply((await api('PUT', 'checkout', payload)).data);
                    return true;
                }
                catch (error) {
                    if (!showErrors(form, error.errors)) {
                        notify(error.message, 'error');
                    }
                    return false;
                }
            });
            return saving;
        };

        form.addEventListener('change', (event) => {
            if (event.target.matches('[name="shipping_method_id"], [name="payment_method"], [name="shipping_address_id"], [name="billing_address_id"], [data-field="email"], [data-field="customer_note"]')) {
                clearErrors(form);
                save(quickPayload());
            }
        });

        billingSame.addEventListener('change', () => {
            billingSection.classList.toggle('hidden', billingSame.checked);
        });

        const newAddress = root.querySelector('[data-shop-address-form]');
        if (newAddress) {
            bindAddressType(newAddress);
            newAddress.querySelector('[data-shop-save-address]').addEventListener('click', async (event) => {
                const button = event.currentTarget;
                clearErrors(newAddress);
                setBusy(button, true);
                try {
                    const address = (await api('POST', 'account/addresses', readFields(newAddress))).data;
                    fillFields(newAddress, {});
                    bindAddressType(newAddress);
                    root.querySelector('[data-shop-new-address]').removeAttribute('open');
                    const payload = quickPayload();
                    if (address.type === 'shipping' || !payload.shipping_address_id) {
                        payload.shipping_address_id = address.id;
                    }
                    if (address.type === 'billing' || !payload.billing_address_id || billingSame.checked) {
                        payload.billing_address_id = address.type === 'billing' ? address.id : payload.shipping_address_id;
                    }
                    if (address.type === 'billing') {
                        billingSame.checked = false;
                        billingSection.classList.remove('hidden');
                    }
                    await save(payload);
                    notify(t('The address has been saved.'));
                }
                catch (error) {
                    if (!showErrors(newAddress, error.errors)) {
                        notify(error.message, 'error');
                    }
                }
                finally {
                    setBusy(button, false);
                }
            });
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(form);
            placeError.textContent = '';
            setBusy(placeButton, true);

            try {
                await saving;
                if (!await save(fullPayload())) {
                    return;
                }

                const result = await api('POST', 'checkout/place', { expected_total: state.cart.totals.grand_total }, { 'Idempotency-Key': idempotencyKey });
                const order = result.data;

                if (order.payment_instructions?.type === 'redirect' && order.payment_instructions.url) {
                    window.location.href = order.payment_instructions.url;
                    return;
                }

                cartToken.set(null);
                updateCartCount(0);

                const url = new URL(pageUrl('order', { number: order.number }), window.location.href);
                url.searchParams.set('placed', '1');
                if (order.guest_access_token) {
                    url.searchParams.set('token', order.guest_access_token);
                }
                window.location.href = url.toString();
            }
            catch (error) {
                if (error.code === 'PRICE_CHANGED' && error.data?.cart) {
                    state.cart = error.data.cart;
                    renderSummary();
                }
                if (error.code === 'OUT_OF_STOCK' || error.code === 'PRICE_CHANGED') {
                    const fresh = await api('GET', 'checkout').catch(() => null);
                    if (fresh) {
                        apply(fresh.data);
                    }
                }
                if (!showErrors(form, error.errors)) {
                    placeError.textContent = cartErrorMessage(error);
                }
            }
            finally {
                setBusy(placeButton, false);
            }
        });

        (async () => {
            try {
                if (!config.token && !cartToken.get()) {
                    throw new ApiError({ code: 'CART_NOT_FOUND' }, 404);
                }
                apply((await api('GET', 'checkout')).data);
                if (!state.cart.items.length) {
                    return;
                }
                fillGuest();
                form.querySelector('[data-field="customer_note"]').value = state.selection.customer_note || '';
                root.querySelector('[data-shop-checkout-loading]').classList.add('hidden');
                form.classList.remove('hidden');
                form.classList.add('grid');
            }
            catch (error) {
                showPaymentError(null);
                root.querySelector('[data-shop-checkout-loading]').classList.add('hidden');
                root.querySelector('[data-shop-checkout-empty]').classList.remove('hidden');
                if (error.code !== 'CART_NOT_FOUND') {
                    notify(error.message, 'error');
                }
            }
        })();
    };

    // Order page: the customer's own order, or a guest order opened with its token

    const orderPage = (root) => {
        const container = root.querySelector('[data-shop-order]');
        const number = root.dataset.number;
        const params = new URLSearchParams(window.location.search);
        const guestToken = params.get('token');

        if (params.get('placed') === '1') {
            cartToken.set(null);
            updateCartCount(0);
        }

        const render = (order) => {
            const instructions = order.payment_instructions;
            const placed = params.get('placed') === '1';

            container.innerHTML = `
                ${placed ? `
                    <div class="mb-8 rounded-md border border-success/40 bg-success/10 p-6">
                        <h1 class="text-2xl font-medium text-success"><i class="ri-checkbox-circle-line" aria-hidden="true"></i> ${esc(t('Thank you for your order'))}</h1>
                        <p class="mt-2">${esc(t('Your order :number has been received. We have sent the details to :email.', { number: order.number, email: order.customer_email }))}</p>
                    </div>` : ''}

                <div class="mb-6 flex flex-wrap items-center gap-3">
                    <${placed ? 'h2' : 'h1'} class="text-2xl font-medium">${esc(t('Order :number', { number: order.number }))}</${placed ? 'h2' : 'h1'}>
                    ${badge(order.status)}
                    ${badge(order.payment_status)}
                    <span class="text-sm text-muted">${esc(date(order.placed_at, true))}</span>
                    ${order.can_cancel && config.token ? `<button type="button" class="ms-auto rounded-md border border-danger px-4 py-1.5 text-sm text-danger duration-300 hover:bg-danger hover:text-white" data-cancel>${esc(t('Cancel order'))}</button>` : ''}
                </div>

                ${instructions?.type === 'bank_transfer' ? `
                    <div class="mb-6 rounded-md border border-amber-300 bg-amber-50 p-6 text-amber-900">
                        <h2 class="mb-2 text-lg font-medium">${esc(t('Bank transfer details'))}</h2>
                        <p>${esc(t('Please write :reference in the description of the transfer.', { reference: instructions.reference }))}</p>
                        ${instructions.due_days ? `<p class="mt-1 text-sm">${esc(t('Orders that are not paid within :days days are cancelled.', { days: instructions.due_days }))}</p>` : ''}
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            ${instructions.accounts.map((account) => `
                                <div class="rounded-md bg-white/70 p-4">
                                    <p class="font-medium">${esc(account.bank_name)}</p>
                                    <p class="text-sm">${esc(account.account_holder)}</p>
                                    <p class="mt-1 font-mono text-sm select-all">${esc(account.iban)}</p>
                                </div>`).join('')}
                        </div>
                        <p class="mt-4 font-medium">${esc(t('Amount to pay: :amount', { amount: money(order.totals.grand_total, order.currency) }))}</p>
                    </div>` : ''}

                ${instructions?.type === 'redirect' && instructions.url && order.payment_status === 'pending' ? `
                    <div class="mb-6 rounded-md border border-amber-300 bg-amber-50 p-6 text-amber-900">
                        <h2 class="mb-2 text-lg font-medium">${esc(t('The card payment is not complete yet.'))}</h2>
                        <a href="${esc(instructions.url)}" class="btn-primary mt-4">${esc(t('Pay now'))}</a>
                    </div>` : ''}

                <div class="grid gap-8 lg_992:grid-cols-3">
                    <div class="lg_992:col-span-2">
                        <ul class="divide-y divide-line rounded-md bg-surface shadow dark:divide-night-line dark:bg-night-surface">
                            ${order.items.map((item) => `
                                <li class="flex gap-4 p-4">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">${esc(item.name)}</p>
                                        ${variantText(item.variant) ? `<p class="text-sm text-muted">${variantText(item.variant)}</p>` : ''}
                                        <p class="text-sm text-muted">${esc(t(':quantity × :price', { quantity: item.quantity, price: money(item.unit_price, order.currency) }))}</p>
                                    </div>
                                    <p class="font-medium whitespace-nowrap">${esc(money(item.line_total, order.currency))}</p>
                                </li>`).join('')}
                        </ul>

                        ${order.history.length ? `
                            <h2 class="mt-8 mb-4 text-xl font-medium">${esc(t('Order history'))}</h2>
                            <ol class="space-y-3 border-s border-line ps-5 dark:border-night-line">
                                ${order.history.map((row) => `
                                    <li class="relative">
                                        <span class="absolute -start-[25px] top-1.5 size-2.5 rounded-full bg-primary"></span>
                                        <p>${badge(row.status)} <span class="ms-2 text-sm text-muted">${esc(date(row.created_at, true))}</span></p>
                                        ${row.note ? `<p class="mt-1 text-sm">${esc(row.note)}</p>` : ''}
                                    </li>`).join('')}
                            </ol>` : ''}
                    </div>

                    <aside class="space-y-6">
                        <div class="rounded-md bg-surface p-6 shadow dark:bg-night-surface">
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="text-muted">${esc(t('Subtotal'))}</dt><dd>${esc(money(order.totals.subtotal, order.currency))}</dd></div>
                                <div class="flex justify-between"><dt class="text-muted">${esc(t('Shipping'))}</dt><dd>${order.totals.shipping_total > 0 ? esc(money(order.totals.shipping_total, order.currency)) : esc(t('Free'))}</dd></div>
                                ${order.totals.payment_fee > 0 ? `<div class="flex justify-between"><dt class="text-muted">${esc(t('Payment fee'))}</dt><dd>${esc(money(order.totals.payment_fee, order.currency))}</dd></div>` : ''}
                                <div class="flex justify-between border-t border-line pt-2 text-lg font-medium dark:border-night-line"><dt>${esc(t('Total'))}</dt><dd>${esc(money(order.totals.grand_total, order.currency))}</dd></div>
                                <div class="flex justify-between text-xs text-muted"><dt>${esc(t('Included VAT'))}</dt><dd>${esc(money(order.totals.tax_total, order.currency))}</dd></div>
                            </dl>
                            <dl class="mt-4 space-y-1 border-t border-line pt-4 text-sm dark:border-night-line">
                                <div class="flex justify-between gap-3"><dt class="text-muted">${esc(t('Payment method'))}</dt><dd>${esc(order.payment_method.name)}</dd></div>
                                ${order.shipping_method ? `<div class="flex justify-between gap-3"><dt class="text-muted">${esc(t('Shipping method'))}</dt><dd>${esc(order.shipping_method)}</dd></div>` : ''}
                            </dl>
                        </div>
                        <div class="rounded-md bg-surface p-6 shadow dark:bg-night-surface">
                            <h2 class="mb-2 font-medium">${esc(t('Shipping address'))}</h2>
                            <p class="text-sm">${addressText(order.shipping_address || {})}</p>
                            <h2 class="mt-4 mb-2 font-medium">${esc(t('Billing address'))}</h2>
                            <p class="text-sm">${addressText(order.billing_address || {})}</p>
                            ${order.customer_note ? `<h2 class="mt-4 mb-2 font-medium">${esc(t('Order note'))}</h2><p class="text-sm">${esc(order.customer_note)}</p>` : ''}
                        </div>
                    </aside>
                </div>`;
        };

        const load = async () => {
            try {
                const result = config.token
                    ? await api('GET', `account/orders/${encodeURIComponent(number)}`)
                    : await api('GET', `orders/${encodeURIComponent(number)}?token=${encodeURIComponent(guestToken || '')}`);
                render(result.data);
            }
            catch (error) {
                container.innerHTML = emptyState('ri-file-search-line', error.status === 404 ? t('The order was not found.') : error.message,
                    config.token ? '' : `<a href="${esc(config.urls.login)}" class="btn-primary mt-6">${esc(t('Sign in'))}</a>`);
            }
        };

        container.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-cancel]');
            if (!button || !window.confirm(t('Cancel this order?'))) {
                return;
            }
            setBusy(button, true);
            try {
                render((await api('POST', `account/orders/${encodeURIComponent(number)}/cancel`)).data);
                notify(t('Your order has been cancelled.'));
            }
            catch (error) {
                notify(error.message, 'error');
                setBusy(button, false);
            }
        });

        load();
    };

    // Account: orders

    const ordersPage = (root) => {
        const container = root.querySelector('[data-shop-orders]');

        const load = async (page = 1) => {
            container.innerHTML = loading();
            try {
                const result = await api('GET', `account/orders?page=${page}&per_page=10`);
                if (!result.data.length) {
                    container.innerHTML = emptyState('ri-shopping-bag-3-line', t('You have no orders yet.'), `<a href="${esc(config.urls.shop)}" class="btn-primary mt-6">${esc(t('Start shopping'))}</a>`);
                    return;
                }
                const meta = result.meta;
                container.innerHTML = `
                    <div class="overflow-x-auto rounded-md bg-surface shadow dark:bg-night-surface">
                        <table class="w-full text-start text-sm">
                            <thead class="border-b border-line text-muted dark:border-night-line">
                                <tr>
                                    <th class="p-4 text-start font-medium">${esc(t('Order'))}</th>
                                    <th class="p-4 text-start font-medium">${esc(t('Date'))}</th>
                                    <th class="p-4 text-start font-medium">${esc(t('Status'))}</th>
                                    <th class="p-4 text-end font-medium">${esc(t('Products'))}</th>
                                    <th class="p-4 text-end font-medium">${esc(t('Total'))}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line dark:divide-night-line">
                                ${result.data.map((order) => `
                                    <tr>
                                        <td class="p-4"><a href="${esc(pageUrl('order', { number: order.number }))}" class="font-medium text-primary">${esc(order.number)}</a></td>
                                        <td class="p-4 whitespace-nowrap">${esc(date(order.placed_at))}</td>
                                        <td class="p-4">${badge(order.status)}</td>
                                        <td class="p-4 text-end">${order.item_count}</td>
                                        <td class="p-4 text-end whitespace-nowrap">${esc(money(order.grand_total, order.currency))}</td>
                                    </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>
                    ${meta.last_page > 1 ? `
                        <div class="mt-6 flex items-center justify-center gap-3">
                            <button type="button" class="rounded-md border border-line px-4 py-2 disabled:opacity-40 dark:border-night-line" data-page="${meta.page - 1}" ${meta.page <= 1 ? 'disabled' : ''}>${esc(t('Previous'))}</button>
                            <span class="text-sm text-muted">${esc(t('Page :page of :last', { page: meta.page, last: meta.last_page }))}</span>
                            <button type="button" class="rounded-md border border-line px-4 py-2 disabled:opacity-40 dark:border-night-line" data-page="${meta.page + 1}" ${meta.page >= meta.last_page ? 'disabled' : ''}>${esc(t('Next'))}</button>
                        </div>` : ''}`;
            }
            catch (error) {
                container.innerHTML = emptyState('ri-error-warning-line', error.message);
            }
        };

        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-page]');
            if (button && !button.disabled) {
                load(parseInt(button.dataset.page, 10));
            }
        });

        load();
    };

    // Account: address book

    const addressesPage = (root) => {
        const list = root.querySelector('[data-shop-addresses]');
        const form = root.querySelector('[data-shop-address-form]');
        const formTitle = root.querySelector('[data-shop-address-form-title]');
        const saveButton = root.querySelector('[data-shop-save-address]');
        let addresses = [];
        let editing = null;

        bindAddressType(form);

        const openForm = (address = null) => {
            editing = address;
            clearErrors(form);
            fillFields(form, address || { type: 'shipping' });
            form.querySelector('[data-field="type"]').dispatchEvent(new Event('change'));
            formTitle.textContent = address ? t('Edit address') : t('New address');
            form.classList.remove('hidden');
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        const closeForm = () => {
            editing = null;
            form.classList.add('hidden');
        };

        const render = () => {
            list.innerHTML = addresses.length
                ? addresses.map((address) => `
                    <div class="rounded-md bg-surface p-6 shadow dark:bg-night-surface" data-id="${address.id}">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">${esc(address.title || statusLabel(address.type === 'billing' ? 'billing_address' : 'shipping_address'))}</h2>
                            <span class="rounded-full bg-page px-2.5 py-0.5 text-xs dark:bg-night-page">${esc(statusLabel(address.type === 'billing' ? 'billing_address' : 'shipping_address'))}</span>
                            ${address.is_default ? `<span class="rounded-full bg-primary px-2.5 py-0.5 text-xs text-white">${esc(t('Default'))}</span>` : ''}
                        </div>
                        <p class="text-sm">${addressText(address)}</p>
                        <div class="mt-4 flex gap-4 text-sm">
                            <button type="button" class="text-primary" data-edit>${esc(t('Edit'))}</button>
                            <button type="button" class="text-danger" data-delete>${esc(t('Delete'))}</button>
                        </div>
                    </div>`).join('')
                : `<div class="md:col-span-2">${emptyState('ri-map-pin-line', t('You have no saved addresses yet.'))}</div>`;
        };

        const load = async () => {
            try {
                addresses = (await api('GET', 'account/addresses')).data;
                render();
            }
            catch (error) {
                list.innerHTML = emptyState('ri-error-warning-line', error.message);
            }
        };

        root.querySelector('[data-shop-address-new]').addEventListener('click', () => openForm());
        root.querySelector('[data-shop-address-cancel]').addEventListener('click', closeForm);

        saveButton.addEventListener('click', async () => {
            clearErrors(form);
            setBusy(saveButton, true);
            try {
                const payload = readFields(form);
                if (editing) {
                    await api('PUT', `account/addresses/${editing.id}`, payload);
                }
                else {
                    await api('POST', 'account/addresses', payload);
                }
                closeForm();
                notify(t('The address has been saved.'));
                await load();
            }
            catch (error) {
                if (!showErrors(form, error.errors)) {
                    notify(error.message, 'error');
                }
            }
            finally {
                setBusy(saveButton, false);
            }
        });

        list.addEventListener('click', async (event) => {
            const card = event.target.closest('[data-id]');
            if (!card) {
                return;
            }
            const address = addresses.find((item) => String(item.id) === card.dataset.id);
            if (event.target.closest('[data-edit]')) {
                openForm(address);
            }
            if (event.target.closest('[data-delete]') && window.confirm(t('Delete this address?'))) {
                try {
                    await api('DELETE', `account/addresses/${address.id}`);
                    notify(t('The address has been deleted.'));
                    await load();
                }
                catch (error) {
                    notify(error.message, 'error');
                }
            }
        });

        load();
    };

    // Account: favorites

    const favoritesPage = (root) => {
        const container = root.querySelector('[data-shop-favorites]');
        let items = [];

        const render = () => {
            container.innerHTML = items.length
                ? `<div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg_992:grid-cols-4">
                    ${items.map((item) => `
                        <article class="flex flex-col overflow-hidden rounded-md bg-surface shadow dark:bg-night-surface" data-product-id="${item.product_id}">
                            <a href="${esc(pageUrl('product', { slug: item.slug }))}" class="block aspect-square bg-page dark:bg-night-page">${item.image ? `<img src="${esc(item.image.thumb)}" alt="" loading="lazy" class="size-full object-cover" />` : ''}</a>
                            <div class="flex flex-1 flex-col p-4">
                                <a href="${esc(pageUrl('product', { slug: item.slug }))}" class="font-medium hover:text-primary">${esc(item.name)}</a>
                                ${variantText(item.variant) ? `<p class="text-sm text-muted">${variantText(item.variant)}</p>` : ''}
                                <p class="mt-2 font-medium">${item.price !== null ? esc(money(item.price, item.currency)) : ''}</p>
                                <div class="mt-auto flex items-center gap-2 pt-4">
                                    <button type="button" class="btn-primary flex-1 px-3 text-sm disabled:opacity-50" data-add ${item.stock_status === 'out_of_stock' || item.price === null ? 'disabled' : ''}>${esc(item.stock_status === 'out_of_stock' ? t('Out of stock') : t('Add to cart'))}</button>
                                    <button type="button" class="inline-flex size-10 items-center justify-center rounded-md border border-line text-danger dark:border-night-line" data-remove aria-label="${esc(t('Remove from favorites'))}"><i class="ri-heart-fill"></i></button>
                                </div>
                            </div>
                        </article>`).join('')}
                   </div>`
                : emptyState('ri-heart-line', t('You have no favorites yet.'), `<a href="${esc(config.urls.shop)}" class="btn-primary mt-6">${esc(t('Start shopping'))}</a>`);
        };

        container.addEventListener('click', async (event) => {
            const card = event.target.closest('[data-product-id]');
            if (!card) {
                return;
            }
            const productId = parseInt(card.dataset.productId, 10);
            const button = event.target.closest('[data-add], [data-remove]');
            if (!button) {
                return;
            }
            setBusy(button, true);
            try {
                if (button.matches('[data-add]')) {
                    await addToCart(productId);
                }
                else {
                    await api('DELETE', `account/favorites/${productId}`);
                    items = items.filter((item) => item.product_id !== productId);
                    render();
                    return;
                }
            }
            catch (error) {
                notify(cartErrorMessage(error), 'error');
            }
            setBusy(button, false);
        });

        (async () => {
            try {
                items = (await api('GET', 'account/favorites')).data;
                render();
            }
            catch (error) {
                container.innerHTML = emptyState('ri-error-warning-line', error.message);
            }
        })();
    };

    // Account: reviews

    const myReviewsPage = (root) => {
        const container = root.querySelector('[data-shop-reviews]');
        let reviews = [];

        const inputClass = 'w-full rounded-md border border-line bg-page px-3 py-2 text-body outline-none duration-300 focus:border-primary dark:border-night-line dark:bg-night-page dark:text-night-body';

        const render = () => {
            container.innerHTML = reviews.length
                ? reviews.map((review) => `
                    <article class="rounded-md bg-surface p-6 shadow dark:bg-night-surface" data-id="${review.id}">
                        <div class="mb-2 flex flex-wrap items-center gap-3">
                            <a href="${esc(pageUrl('product', { slug: review.product.slug }))}" class="font-medium hover:text-primary">${esc(review.product.name)}</a>
                            ${badge(review.status)}
                            <span class="text-sm text-muted">${esc(date(review.created_at))}</span>
                        </div>
                        <div data-view>
                            <p class="mb-1">${stars(review.rating)} ${review.title ? `<span class="ms-2 font-medium">${esc(review.title)}</span>` : ''}</p>
                            ${review.body ? `<p class="text-sm">${esc(review.body).replace(/\n/g, '<br>')}</p>` : ''}
                            <div class="mt-4 flex gap-4 text-sm">
                                <button type="button" class="text-primary" data-edit>${esc(t('Edit'))}</button>
                                <button type="button" class="text-danger" data-delete>${esc(t('Delete'))}</button>
                            </div>
                        </div>
                        <form class="hidden space-y-3" data-form>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium">${esc(t('Your rating'))}</span>
                                <select name="rating" class="${inputClass}">
                                    ${[5, 4, 3, 2, 1].map((value) => `<option value="${value}" ${value === review.rating ? 'selected' : ''}>${esc(t(':rating out of 5', { rating: value }))}</option>`).join('')}
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium">${esc(t('Title'))}</span>
                                <input type="text" name="title" maxlength="255" value="${esc(review.title)}" class="${inputClass}" />
                                <span class="mt-1 block text-sm text-danger" data-shop-error="title"></span>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium">${esc(t('Your review'))}</span>
                                <textarea name="body" rows="4" maxlength="5000" class="${inputClass}">${esc(review.body)}</textarea>
                                <span class="mt-1 block text-sm text-danger" data-shop-error="body"></span>
                            </label>
                            <p class="text-sm text-muted">${esc(t('Edited reviews are published again after approval.'))}</p>
                            <div class="flex gap-3">
                                <button type="submit" class="btn-primary">${esc(t('Save'))}</button>
                                <button type="button" class="rounded-md border border-line px-5 py-2 dark:border-night-line" data-cancel>${esc(t('Cancel'))}</button>
                            </div>
                        </form>
                    </article>`).join('')
                : emptyState('ri-star-line', t('You have not written any reviews yet.'));
        };

        const load = async () => {
            try {
                reviews = (await api('GET', 'account/reviews')).data;
                render();
            }
            catch (error) {
                container.innerHTML = emptyState('ri-error-warning-line', error.message);
            }
        };

        container.addEventListener('click', async (event) => {
            const card = event.target.closest('[data-id]');
            if (!card) {
                return;
            }
            const toggle = (editing) => {
                card.querySelector('[data-view]').classList.toggle('hidden', editing);
                card.querySelector('[data-form]').classList.toggle('hidden', !editing);
            };
            if (event.target.closest('[data-edit]')) {
                toggle(true);
            }
            if (event.target.closest('[data-cancel]')) {
                toggle(false);
            }
            if (event.target.closest('[data-delete]') && window.confirm(t('Delete this review?'))) {
                try {
                    await api('DELETE', `account/reviews/${card.dataset.id}`);
                    notify(t('The review has been deleted.'));
                    await load();
                }
                catch (error) {
                    notify(error.message, 'error');
                }
            }
        });

        container.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.target;
            const card = form.closest('[data-id]');
            const data = new FormData(form);
            clearErrors(form);
            try {
                await api('PUT', `account/reviews/${card.dataset.id}`, {
                    rating: parseInt(data.get('rating'), 10),
                    title: data.get('title') || null,
                    body: data.get('body') || null,
                });
                notify(t('Your review has been updated and will be published after approval.'));
                await load();
            }
            catch (error) {
                if (!showErrors(form, error.errors)) {
                    notify(error.message, 'error');
                }
            }
        });

        load();
    };

    const pages = {
        product: productPage,
        cart: cartPage,
        checkout: checkoutPage,
        order: orderPage,
        orders: ordersPage,
        addresses: addressesPage,
        favorites: favoritesPage,
        reviews: myReviewsPage,
    };

    const start = () => {
        document.querySelectorAll('[data-shop-page]').forEach((root) => pages[root.dataset.shopPage]?.(root));
        if (!document.querySelector('[data-shop-page="cart"], [data-shop-page="checkout"]')) {
            loadCartCount();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    }
    else {
        start();
    }
})();
