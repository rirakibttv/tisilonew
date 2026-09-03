const campaignForm = document.querySelector('[data-campaign-checkout]');
const campaignData = document.getElementById('campaign-checkout-data');

if (campaignForm && campaignData) {
    const data = JSON.parse(campaignData.textContent);
    const product = campaignForm.elements.product_id;
    const variation = campaignForm.elements.product_variation_id;
    const quantity = campaignForm.elements.quantity;
    const region = campaignForm.elements.shipping_region_id;
    const submit = campaignForm.querySelector('[data-campaign-submit]');
    const status = campaignForm.querySelector('[data-quote-status]');
    const retry = campaignForm.querySelector('[data-quote-retry]');
    const preview = campaignForm.dataset.preview === '1';
    const money = value => `৳${Number(value).toLocaleString('bn-BD', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
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
    const updateTotals = () => {
        const quote = regions.find(item => String(item.region_id) === region.value);
        campaignForm.querySelector('[data-campaign-subtotal]').textContent = money(subtotal);
        campaignForm.querySelector('[data-campaign-shipping]').textContent = ready && quote ? money(quote.amount) : 'উপজেলা/থানা নির্বাচন করুন';
        campaignForm.querySelector('[data-campaign-total]').textContent = ready && quote ? money(subtotal + Number(quote.amount)) : 'এলাকা নির্বাচন করুন';
        submit.disabled = preview || !ready || !quote || submitting;
    };
    const updateProduct = (resetVariation = false) => {
        const selected = selectedProduct();
        if (!selected) return;
        if (resetVariation) {
            variation.replaceChildren();
            selected.variations.forEach(item => {
                const option = new Option(`${item.label} — ${money(item.price)}${item.available < 1 ? ' (স্টক নেই)' : ''}`, item.id);
                option.disabled = item.available < 1;
                variation.add(option);
            });
            const firstAvailable = selected.variations.find(item => item.available > 0);
            if (firstAvailable) variation.value = String(firstAvailable.id);
        }
        variation.disabled = !selected.variable;
        variation.required = selected.variable;
        campaignForm.querySelector('[data-variation-field]').classList.toggle('hidden', !selected.variable);
        const option = selected.variable ? selectedOption() : selected;
        campaignForm.querySelector('[data-campaign-name]').textContent = selected.name;
        const image = campaignForm.querySelector('[data-campaign-image]');
        image.classList.toggle('hidden', !selected.image);
        if (selected.image) image.src = selected.image;
        image.alt = selected.name;
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
                throw new Error(errors[0] || 'হিসাব যাচাই করা যায়নি। আবার চেষ্টা করুন।');
            }
            subtotal = Number(payload.subtotal);
            const option = selectedProduct().variable ? selectedOption() : selectedProduct();
            option.price = Number(payload.unit_price);
            campaignForm.querySelector('[data-unit-price]').textContent = money(option.price);
            regions = payload.regions;
            const previousRegion = region.value;
            region.replaceChildren(new Option('বিভাগ › জেলা › উপজেলা/থানা নির্বাচন করুন', ''));
            regions.forEach(item => region.add(new Option(`${item.name} — ${money(item.amount)}`, item.region_id)));
            if (regions.some(item => String(item.region_id) === previousRegion)) region.value = previousRegion;
            ready = regions.length > 0;
            status.textContent = ready ? '' : 'নির্বাচিত পণ্যের জন্য কোনো সক্রিয় ডেলিভারি রেট নেই।';
            retry.hidden = ready;
            updateTotals();
        } catch (error) {
            if (error.name === 'AbortError' || currentSequence !== sequence) return;
            ready = false;
            status.textContent = error instanceof SyntaxError ? 'সংযোগে সমস্যা হয়েছে। আবার চেষ্টা করুন বা পেজ রিফ্রেশ করুন।' : error.message;
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
        status.textContent = 'মূল্য ও ডেলিভারি চার্জ যাচাই হচ্ছে…';
        updateProduct();
        timer = setTimeout(() => refreshQuote(current), 200);
    };
    product.addEventListener('change', () => { updateProduct(true); queueQuote(); });
    variation.addEventListener('change', queueQuote);
    quantity.addEventListener('input', queueQuote);
    region.addEventListener('change', updateTotals);
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
        submit.textContent = 'অর্ডার জমা হচ্ছে…';
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        submit.textContent = 'অর্ডার নিশ্চিত করুন';
        updateTotals();
    });
    updateProduct();
    updateTotals();
    const stickyCta = document.querySelector('[data-campaign-sticky-cta]');
    if (stickyCta && 'IntersectionObserver' in window) {
        new IntersectionObserver(entries => {
            stickyCta.classList.toggle('hidden', entries[0].isIntersecting);
        }).observe(campaignForm);
    }
}
