const campaignForm = document.querySelector('[data-campaign-checkout]');
const campaignData = document.getElementById('campaign-checkout-data');

if (campaignForm && campaignData) {
    const data = JSON.parse(campaignData.textContent);
    const messages = data.messages || {};
    const locale = data.locale === 'bn' ? 'bn-BD' : 'en-US';
    const product = campaignForm.elements.product_id;
    const variation = campaignForm.elements.product_variation_id;
    const quantity = campaignForm.elements.quantity;
    const region = campaignForm.elements.shipping_region_id;
    const districtSearch = campaignForm.querySelector('[data-district-search]');
    const districtOptions = campaignForm.querySelector('[data-district-options]');
    const variationOptions = campaignForm.querySelector('[data-variation-options]');
    const selectedVariationLabel = campaignForm.querySelector('[data-selected-variation-label]');
    const submit = campaignForm.querySelector('[data-campaign-submit]');
    const status = campaignForm.querySelector('[data-quote-status]');
    const retry = campaignForm.querySelector('[data-quote-retry]');
    const preview = campaignForm.dataset.preview === '1';
    const money = value => `৳${Number(value).toLocaleString(locale, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    let subtotal = Number(data.subtotal);
    let regions = data.regions;
    let ready = !data.error && regions.length > 0;
    let submitting = false;
    let pending;
    let timer;
    let sequence = 0;
    let tracked = false;

    const selectedProduct = () => data.products.find(item => String(item.id) === product.value);
    const selectedOption = () => selectedProduct()?.variations.find(item => String(item.id) === variation.value);
    const districtLabel = item => String(item?.district || item?.name || '').trim();
    const districtQuotes = () => {
        const seen = new Set();

        return regions.filter(item => {
            const key = districtLabel(item).toLocaleLowerCase(locale);
            if (!key || seen.has(key)) return false;
            seen.add(key);

            return true;
        });
    };
    const closeDistrictOptions = () => {
        districtOptions?.classList.add('hidden');
        districtSearch?.setAttribute('aria-expanded', 'false');
    };
    const renderDistrictOptions = (filter = '') => {
        if (!districtOptions) return;
        const query = filter.trim().toLocaleLowerCase(locale);
        const matches = districtQuotes().filter(item => districtLabel(item).toLocaleLowerCase(locale).includes(query));
        districtOptions.replaceChildren();
        matches.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.role = 'option';
            button.dataset.regionOption = '';
            button.dataset.regionId = item.region_id;
            button.dataset.regionDistrict = districtLabel(item);
            button.className = 'flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-green-50';

            const label = document.createElement('span');
            label.className = 'font-semibold';
            label.textContent = districtLabel(item);
            const price = document.createElement('span');
            price.className = 'text-xs text-slate-500';
            price.textContent = money(item.amount);
            button.append(label, price);
            districtOptions.append(button);
        });
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'px-3 py-3 text-xs text-rose-600';
            empty.textContent = messages.districtUnavailable || 'No delivery rate was found for this district.';
            districtOptions.append(empty);
        }
    };
    const selectDistrict = item => {
        if (!item) return;
        region.value = String(item.region_id);
        districtSearch.value = districtLabel(item);
        closeDistrictOptions();
        updateTotals();
    };
    const syncVariationOptions = () => {
        const option = selectedOption();
        variationOptions?.querySelectorAll('[data-variation-option]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.variationOption === variation.value));
        });
        if (selectedVariationLabel) selectedVariationLabel.textContent = option?.label || '';
    };
    const variationButton = item => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.variationOption = item.id;
        button.disabled = item.available < 1;
        button.setAttribute('aria-pressed', 'false');
        button.className = 'campaign-variation-option relative flex w-full items-center gap-3 overflow-hidden rounded-2xl border-2 border-slate-200 bg-white p-3 text-left transition hover:border-slate-400 disabled:cursor-not-allowed disabled:opacity-45';

        const check = document.createElement('span');
        check.className = 'campaign-variation-check absolute right-1.5 top-1.5 hidden size-6 place-items-center rounded-full text-xs font-black text-white';
        check.textContent = '✓';
        button.append(check);

        if (item.image) {
            const image = document.createElement('img');
            image.src = item.image;
            image.alt = item.label;
            image.loading = 'lazy';
            image.className = 'size-16 shrink-0 rounded-xl bg-slate-50 object-cover';
            button.append(image);
        }

        const label = document.createElement('span');
        label.className = 'min-w-0 flex-1 break-words text-xs font-bold leading-5';
        label.textContent = item.label;
        button.append(label);

        const price = document.createElement('span');
        price.className = 'campaign-text shrink-0 pr-8 text-sm font-black';
        price.textContent = money(item.price);
        button.append(price);

        if (item.available < 1) {
            const stock = document.createElement('span');
            stock.className = 'mt-1 block text-[10px] font-bold text-rose-600';
            stock.textContent = messages.outOfStock || 'Out of Stock';
            button.append(stock);
        }

        return button;
    };
    const renderVariationOptions = selected => {
        const previousValue = variation.value;
        variation.replaceChildren();
        variationOptions?.replaceChildren();
        selected.variations.forEach(item => {
            const option = new Option(`${item.label} — ${money(item.price)}${item.available < 1 ? ` (${messages.outOfStock || 'Out of Stock'})` : ''}`, item.id);
            option.disabled = item.available < 1;
            variation.add(option);
            variationOptions?.append(variationButton(item));
        });
        const preferred = selected.variations.find(item => String(item.id) === previousValue && item.available > 0);
        const firstAvailable = selected.variations.find(item => item.available > 0);
        variation.value = preferred ? String(preferred.id) : (firstAvailable ? String(firstAvailable.id) : '');
        syncVariationOptions();
    };
    const updateTotals = () => {
        const quote = regions.find(item => String(item.region_id) === region.value);
        campaignForm.querySelector('[data-campaign-subtotal]').textContent = money(subtotal);
        campaignForm.querySelector('[data-campaign-shipping]').textContent = ready && quote ? money(quote.amount) : (messages.selectDistrict || 'Select District');
        campaignForm.querySelector('[data-campaign-total]').textContent = ready && quote ? money(subtotal + Number(quote.amount)) : (messages.selectDistrict || 'Select District');
        submit.disabled = preview || !ready || !quote || submitting;
    };
    const updateProduct = (resetVariation = false) => {
        const selected = selectedProduct();
        if (!selected) return;
        if (resetVariation) renderVariationOptions(selected);
        variation.disabled = !selected.variable;
        variation.required = selected.variable;
        campaignForm.querySelector('[data-variation-field]').classList.toggle('hidden', !selected.variable);
        const option = selected.variable ? selectedOption() : selected;
        syncVariationOptions();
        campaignForm.querySelector('[data-campaign-name]').textContent = selected.name;
        const image = campaignForm.querySelector('[data-campaign-image]');
        const selectedImage = option?.image || selected.image;
        image.classList.toggle('hidden', !selectedImage);
        if (selectedImage) image.src = selectedImage;
        image.alt = option?.label ? `${selected.name} — ${option.label}` : selected.name;
        campaignForm.querySelector('[data-unit-price]').textContent = money(option?.price || 0);
        quantity.max = String(Math.max(1, option?.available || 1));
        subtotal = Number(option?.price || 0) * Number(quantity.value || 0);
        updateTotals();
    };
    const refreshQuote = async (currentSequence) => {
        if (preview) return;
        const controller = new AbortController();
        pending = controller;
        try {
            const response = await fetch(campaignForm.dataset.quoteUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': campaignForm.elements._token.value},
                body: JSON.stringify({product_id: product.value, product_variation_id: variation.disabled ? null : variation.value, quantity: quantity.value}),
            });
            const payload = await response.json();
            if (currentSequence !== sequence) return;
            if (!response.ok) {
                const errors = Object.values(payload.errors || {}).flat();
                throw new Error(errors[0] || messages.quoteFailed || 'The calculation could not be verified. Please try again.');
            }
            subtotal = Number(payload.subtotal);
            const option = selectedProduct().variable ? selectedOption() : selectedProduct();
            option.price = Number(payload.unit_price);
            campaignForm.querySelector('[data-unit-price]').textContent = money(option.price);
            const optionPrice = variationOptions?.querySelector(`[data-variation-option="${variation.value}"] .campaign-text`);
            if (optionPrice) optionPrice.textContent = money(option.price);
            regions = payload.regions;
            const previousRegion = region.value;
            const previousDistrict = districtSearch.value;
            renderDistrictOptions(previousDistrict);
            const previousQuote = regions.find(item => String(item.region_id) === previousRegion);
            const matchingDistrict = districtQuotes().find(item => districtLabel(item).toLocaleLowerCase(locale) === previousDistrict.trim().toLocaleLowerCase(locale));
            if (previousQuote) {
                region.value = previousRegion;
                districtSearch.value = districtLabel(previousQuote);
            } else if (matchingDistrict) {
                region.value = String(matchingDistrict.region_id);
                districtSearch.value = districtLabel(matchingDistrict);
            } else {
                region.value = '';
            }
            ready = regions.length > 0;
            status.textContent = ready ? '' : (messages.noActiveRate || 'No active delivery rate is available for the selected product.');
            retry.hidden = ready;
            updateTotals();
        } catch (error) {
            if (error.name === 'AbortError' || currentSequence !== sequence) return;
            ready = false;
            status.textContent = error instanceof SyntaxError ? (messages.connectionError || 'There was a connection problem. Please try again or refresh the page.') : error.message;
            retry.hidden = false;
            updateTotals();
        }
    };
    const queueQuote = () => {
        sequence++;
        const current = sequence;
        pending?.abort();
        clearTimeout(timer);
        ready = false;
        retry.hidden = true;
        status.textContent = messages.checking || 'Checking price and delivery charge…';
        updateProduct();
        timer = setTimeout(() => refreshQuote(current), 200);
    };
    product.addEventListener('change', () => { updateProduct(true); queueQuote(); });
    variation.addEventListener('change', queueQuote);
    variationOptions?.addEventListener('click', event => {
        const button = event.target.closest('[data-variation-option]');
        if (!button || button.disabled || button.dataset.variationOption === variation.value) return;
        variation.value = button.dataset.variationOption;
        syncVariationOptions();
        queueQuote();
    });
    quantity.addEventListener('input', queueQuote);
    districtSearch?.addEventListener('focus', () => {
        renderDistrictOptions(districtSearch.value);
        districtOptions?.classList.remove('hidden');
        districtSearch.setAttribute('aria-expanded', 'true');
    });
    districtSearch?.addEventListener('input', () => {
        const exact = districtQuotes().find(item => districtLabel(item).toLocaleLowerCase(locale) === districtSearch.value.trim().toLocaleLowerCase(locale));
        region.value = exact ? String(exact.region_id) : '';
        renderDistrictOptions(districtSearch.value);
        districtOptions?.classList.remove('hidden');
        districtSearch.setAttribute('aria-expanded', 'true');
        updateTotals();
    });
    districtSearch?.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeDistrictOptions();
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            districtOptions?.querySelector('[data-region-option]')?.focus();
        }
        if (event.key === 'Enter') {
            const first = districtOptions?.querySelector('[data-region-option]');
            if (first && !districtOptions.classList.contains('hidden')) {
                event.preventDefault();
                selectDistrict(regions.find(item => String(item.region_id) === first.dataset.regionId));
            }
        }
    });
    districtOptions?.addEventListener('click', event => {
        const option = event.target.closest('[data-region-option]');
        if (!option) return;
        selectDistrict(regions.find(item => String(item.region_id) === option.dataset.regionId));
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('[data-district-combobox]')) closeDistrictOptions();
    });
    retry.addEventListener('click', queueQuote);
    campaignForm.querySelectorAll('[data-quantity-step]').forEach(button => button.addEventListener('click', () => {
        quantity.value = String(Math.max(1, Math.min(Number(quantity.max), Number(quantity.value || 1) + Number(button.dataset.quantityStep))));
        queueQuote();
    }));
    campaignForm.addEventListener('change', () => {
        if (!tracked && !preview && window.TisiloAnalytics) {
            tracked = true;
            window.TisiloAnalytics.track('initiate_checkout', {product_id: Number(product.value), value: subtotal, metadata: {currency: 'BDT'}});
        }
    });
    campaignForm.addEventListener('submit', event => {
        if (preview || !ready || submitting || !region.value) {
            event.preventDefault();
            return;
        }
        submitting = true;
        submit.disabled = true;
        submit.textContent = messages.submitting || 'Submitting Order…';
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        submit.textContent = messages.confirmOrder || 'Confirm Order';
        updateTotals();
    });
    updateProduct();
    renderDistrictOptions();
    updateTotals();
    const stickyCta = document.querySelector('[data-campaign-sticky-cta]');
    if (stickyCta && 'IntersectionObserver' in window) {
        new IntersectionObserver(entries => {
            stickyCta.classList.toggle('hidden', entries[0].isIntersecting);
        }).observe(campaignForm);
    }
}
