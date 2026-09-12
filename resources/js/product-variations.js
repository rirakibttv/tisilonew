const variationForm = document.querySelector('[data-product-variation-form]');
const variationData = document.getElementById('product-variation-data');

if (variationForm && variationData) {
    const data = JSON.parse(variationData.textContent);
    const select = variationForm.elements.product_variation_id;
    const cards = variationForm.querySelector('[data-product-variation-options]');
    const gallery = document.querySelector('[data-product-gallery-options]');
    const label = variationForm.querySelector('[data-product-variation-label]');
    const quantity = variationForm.querySelector('[data-product-quantity]');
    const cartButtons = variationForm.querySelectorAll('[data-product-cart-button]');
    const stockLeft = variationForm.querySelector('[data-product-stock-left]');
    const mainImage = document.querySelector('[data-product-main-image]');
    const imagePlaceholder = document.querySelector('[data-product-image-placeholder]');
    const price = document.querySelector('[data-product-price]');
    const regularPrice = document.querySelector('[data-product-regular-price]');
    const discount = document.querySelector('[data-product-discount]');
    const sku = document.querySelector('[data-product-sku]');
    const stock = document.querySelector('[data-product-stock]');
    const money = value => `৳${Number(value).toLocaleString('bn-BD', {maximumFractionDigits: 0})}`;
    const selected = () => data.variations.find(item => String(item.id) === select.value);

    const setGallerySelection = (type, variationId = null) => {
        gallery?.querySelector('[data-product-gallery-master]')?.setAttribute('aria-pressed', String(type === 'master'));
        gallery?.querySelectorAll('[data-product-gallery-variation]').forEach(card => {
            const isSelectedVariation = type === 'variation'
                && card.dataset.productGalleryVariation === String(variationId);
            card.setAttribute('aria-pressed', String(isSelectedVariation));
        });
    };

    const showImage = source => {
        if (source) {
            mainImage.src = source;
            mainImage.classList.remove('hidden');
            imagePlaceholder?.classList.add('hidden');
            imagePlaceholder?.classList.remove('grid');
        } else {
            mainImage.removeAttribute('src');
            mainImage.classList.add('hidden');
            imagePlaceholder?.classList.remove('hidden');
            imagePlaceholder?.classList.add('grid');
        }
    };

    const apply = (option, changeImage = false) => {
        if (!option) return;
        cards?.querySelectorAll('[data-product-variation-option]').forEach(card => {
            card.setAttribute('aria-pressed', String(card.dataset.productVariationOption === String(option.id)));
        });
        if (label) label.textContent = option.label;
        if (price) price.textContent = money(option.price);
        const hasDiscount = Number(option.regular_price) > Number(option.price);
        if (regularPrice) {
            regularPrice.textContent = money(option.regular_price);
            regularPrice.classList.toggle('hidden', !hasDiscount);
        }
        if (discount) {
            const percent = hasDiscount ? Math.round(((Number(option.regular_price) - Number(option.price)) / Number(option.regular_price)) * 100) : 0;
            discount.textContent = `${percent}% ছাড়`;
            discount.classList.toggle('hidden', percent < 1);
        }
        if (sku) sku.textContent = option.sku || 'N/A';
        if (stock) {
            const available = Number(option.available) > 0;
            stock.textContent = available ? 'স্টকে আছে' : 'স্টক নেই';
            stock.classList.toggle('text-emerald-600', available);
            stock.classList.toggle('text-amber-600', !available);
        }
        quantity.max = String(Math.max(1, Number(option.available)));
        quantity.value = String(Math.min(Number(quantity.value || 1), Number(quantity.max)));
        if (stockLeft) stockLeft.textContent = `Only ${Number(option.available)} left`;
        cartButtons.forEach(button => { button.disabled = Number(option.available) < 1; });
        if (changeImage) {
            showImage(option.image || data.masterImage);
            setGallerySelection(option.image ? 'variation' : (data.masterImage ? 'master' : null), option.id);
        }
    };

    const chooseVariation = card => {
        if (!card || card.disabled) return;
        select.value = card.dataset.productVariationOption || card.dataset.productGalleryVariation;
        apply(selected(), true);
    };
    cards?.addEventListener('click', event => {
        chooseVariation(event.target.closest('[data-product-variation-option]'));
    });
    gallery?.addEventListener('click', event => {
        if (event.target.closest('[data-product-gallery-master]')) {
            showImage(data.masterImage);
            setGallerySelection('master');

            return;
        }

        chooseVariation(event.target.closest('[data-product-gallery-variation]'));
    });
    select.addEventListener('change', () => apply(selected(), true));
    variationForm.querySelectorAll('[data-product-quantity-step]').forEach(button => {
        button.addEventListener('click', () => {
            quantity.value = String(Math.max(1, Math.min(Number(quantity.max), Number(quantity.value || 1) + Number(button.dataset.productQuantityStep))));
        });
    });
    mainImage?.addEventListener('error', () => {
        if (mainImage.getAttribute('src') !== data.masterImage && data.masterImage) {
            showImage(data.masterImage);
            setGallerySelection('master');
        }
    });
    const initialVariation = selected();
    apply(initialVariation);
    setGallerySelection(data.masterImage ? 'master' : (initialVariation?.image ? 'variation' : null), initialVariation?.id);
}
