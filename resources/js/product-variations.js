const variationForm = document.querySelector('[data-product-variation-form]');
const variationData = document.getElementById('product-variation-data');
const productGallery = document.querySelector('[data-product-gallery-options]');
const productMainImage = document.querySelector('[data-product-main-image]');
const productImagePlaceholder = document.querySelector('[data-product-image-placeholder]');

const showProductImage = source => {
    if (!source || !productMainImage) return;

    productMainImage.src = source;
    productMainImage.classList.remove('hidden');
    productImagePlaceholder?.classList.add('hidden');
    productImagePlaceholder?.classList.remove('grid');
};

const selectProductGalleryImage = selectedButton => {
    productGallery?.querySelectorAll('[data-product-gallery-image]').forEach(button => {
        button.setAttribute('aria-pressed', String(button === selectedButton));
    });
};

if (!variationData && productGallery) {
    productGallery.addEventListener('click', event => {
        const button = event.target.closest('[data-product-gallery-image]');
        if (!button) return;

        showProductImage(button.dataset.productGalleryImage);
        selectProductGalleryImage(button);
    });
}

if (variationForm && variationData) {
    const data = JSON.parse(variationData.textContent);
    const messages = data.messages || {};
    const locale = data.locale === 'bn' ? 'bn-BD' : 'en-US';
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
    const money = value => `৳${Number(value).toLocaleString(locale, {maximumFractionDigits: 0})}`;
    const selected = () => data.variations.find(item => String(item.id) === select.value);

    const setGallerySelection = (type, variationId = null, imageButton = null) => {
        gallery?.querySelectorAll('[data-product-gallery-image]').forEach(button => {
            const isSelectedMaster = type === 'master' && button.hasAttribute('data-product-gallery-master');
            const isSelectedVariation = type === 'variation'
                && button.dataset.productGalleryVariation === String(variationId);
            button.setAttribute('aria-pressed', String(isSelectedMaster || isSelectedVariation || button === imageButton));
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
            discount.textContent = (messages.discount || '__AMOUNT__% off').replace('__AMOUNT__', new Intl.NumberFormat(locale).format(percent));
            discount.classList.toggle('hidden', percent < 1);
        }
        if (sku) sku.textContent = option.sku || messages.notAvailable || 'N/A';
        if (stock) {
            const available = Number(option.available) > 0;
            stock.textContent = available ? (messages.inStock || 'In Stock') : (messages.outOfStock || 'Out of Stock');
            stock.classList.toggle('text-emerald-600', available);
            stock.classList.toggle('text-amber-600', !available);
        }
        quantity.max = String(Math.max(1, Number(option.available)));
        quantity.value = String(Math.min(Number(quantity.value || 1), Number(quantity.max)));
        if (stockLeft) stockLeft.textContent = (messages.onlyLeft || 'Only __COUNT__ left').replace('__COUNT__', new Intl.NumberFormat(locale).format(Number(option.available)));
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

        const variationButton = event.target.closest('[data-product-gallery-variation]');
        if (variationButton) {
            chooseVariation(variationButton);

            return;
        }

        const galleryButton = event.target.closest('[data-product-gallery-image]');
        if (galleryButton) {
            showImage(galleryButton.dataset.productGalleryImage);
            setGallerySelection('image', null, galleryButton);
        }
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
