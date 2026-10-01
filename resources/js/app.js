import './bootstrap';
import './landing-checkout';
import './product-variations';

const mobileMenuButton = document.querySelector('[data-mobile-menu-button]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

mobileMenuButton?.addEventListener('click', () => {
    const isOpen = mobileMenuButton.getAttribute('aria-expanded') === 'true';

    mobileMenuButton.setAttribute('aria-expanded', String(! isOpen));
    mobileMenu?.classList.toggle('hidden', isOpen);
});

document.querySelectorAll('[data-catalog-filter]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const categoryFilter = form.querySelector('[data-category-filter]:checked')
            || form.querySelector('[data-category-filter]');
        const target = categoryFilter?.selectedOptions?.[0]?.dataset.url || categoryFilter?.dataset.url;

        if (! target) {
            return;
        }

        event.preventDefault();

        const url = new URL(target, window.location.origin);
        const formData = new FormData(form);

        formData.delete('category');
        formData.forEach((value, key) => {
            const normalizedValue = String(value).trim();

            if (normalizedValue !== '' && ! (key === 'sort' && normalizedValue === 'latest')) {
                url.searchParams.append(key, normalizedValue);
            }
        });

        window.location.assign(url.toString());
    });
});

document.querySelectorAll('[data-catalog-page]').forEach((catalogPage) => {
    const panel = catalogPage.querySelector('[data-catalog-filter-panel]');
    const overlay = catalogPage.querySelector('[data-catalog-filter-overlay]');
    const openButton = catalogPage.querySelector('[data-catalog-filter-open]');
    const closeButton = catalogPage.querySelector('[data-catalog-filter-close]');

    if (! panel || ! overlay) {
        return;
    }

    const setPanelOpen = (isOpen) => {
        panel.classList.toggle('-translate-x-full', ! isOpen);
        panel.classList.toggle('translate-x-0', isOpen);
        overlay.classList.toggle('hidden', ! isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen);
        openButton?.setAttribute('aria-expanded', String(isOpen));
    };

    openButton?.setAttribute('aria-expanded', 'false');
    openButton?.addEventListener('click', () => setPanelOpen(true));
    closeButton?.addEventListener('click', () => setPanelOpen(false));
    overlay.addEventListener('click', () => setPanelOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! overlay.classList.contains('hidden')) {
            setPanelOpen(false);
        }
    });
});

document.querySelectorAll('[data-catalog-sort]').forEach((sortSelect) => {
    sortSelect.addEventListener('change', () => {
        document.getElementById(sortSelect.getAttribute('form'))?.requestSubmit();
    });
});

document.querySelectorAll('[data-price-range]').forEach((priceRange) => {
    const minimumInput = priceRange.querySelector('[data-price-min-input]');
    const maximumInput = priceRange.querySelector('[data-price-max-input]');
    const minimumRange = priceRange.querySelector('[data-price-min-range]');
    const maximumRange = priceRange.querySelector('[data-price-max-range]');
    const lowerBound = Number(priceRange.dataset.minBound || 0);
    const upperBound = Number(priceRange.dataset.maxBound || lowerBound);

    if (! minimumInput || ! maximumInput || ! minimumRange || ! maximumRange || upperBound <= lowerBound) {
        return;
    }

    const clamp = (value) => Math.min(upperBound, Math.max(lowerBound, Number(value)));
    const updateTrack = () => {
        const minimum = clamp(minimumRange.value);
        const maximum = clamp(maximumRange.value);
        const span = upperBound - lowerBound;

        priceRange.style.setProperty('--price-start', `${((minimum - lowerBound) / span) * 100}%`);
        priceRange.style.setProperty('--price-end', `${((maximum - lowerBound) / span) * 100}%`);
    };

    minimumRange.addEventListener('input', () => {
        const value = Math.min(clamp(minimumRange.value), clamp(maximumRange.value));
        minimumRange.value = String(value);
        minimumInput.value = String(value);
        updateTrack();
    });

    maximumRange.addEventListener('input', () => {
        const value = Math.max(clamp(maximumRange.value), clamp(minimumRange.value));
        maximumRange.value = String(value);
        maximumInput.value = String(value);
        updateTrack();
    });

    minimumInput.addEventListener('input', () => {
        if (minimumInput.value !== '') {
            minimumRange.value = String(Math.min(clamp(minimumInput.value), clamp(maximumRange.value)));
            updateTrack();
        }
    });

    maximumInput.addEventListener('input', () => {
        if (maximumInput.value !== '') {
            maximumRange.value = String(Math.max(clamp(maximumInput.value), clamp(minimumRange.value)));
            updateTrack();
        }
    });

    updateTrack();
});

document.querySelectorAll('[data-catalog-infinite]').forEach((catalog) => {
    const grid = catalog.querySelector('[data-product-grid]');
    const sentinel = catalog.querySelector('[data-catalog-load-sentinel]');
    const loadingIndicator = catalog.querySelector('[data-catalog-loading]');
    const endMessage = catalog.querySelector('[data-catalog-end]');
    const retryButton = catalog.querySelector('[data-catalog-retry]');
    const pagination = document.querySelector('[data-catalog-pagination]');

    if (! grid || ! sentinel || ! ('IntersectionObserver' in window)) {
        return;
    }

    pagination?.classList.add('hidden');

    let nextPageUrl = catalog.dataset.nextPageUrl || '';
    let loading = false;

    const setLoading = (state) => {
        loading = state;
        loadingIndicator?.classList.toggle('hidden', ! state);
        loadingIndicator?.classList.toggle('flex', state);
    };

    const finish = () => {
        observer.disconnect();
        endMessage?.classList.remove('hidden');
        retryButton?.classList.add('hidden');

        const loadedProducts = grid.querySelectorAll('[data-product-card]').length;
        const totalProducts = Number(catalog.dataset.totalProducts || loadedProducts);

        if (endMessage) {
            const formattedCount = new Intl.NumberFormat(catalog.dataset.numberLocale || 'en-US').format(totalProducts);
            endMessage.textContent = (catalog.dataset.allLoadedTemplate || 'All __COUNT__ products loaded').replace('__COUNT__', formattedCount);
        }
    };

    const loadMore = async () => {
        if (loading || ! nextPageUrl) {
            if (! nextPageUrl) {
                finish();
            }

            return;
        }

        setLoading(true);
        endMessage?.classList.add('hidden');
        retryButton?.classList.add('hidden');

        try {
            const url = new URL(nextPageUrl, window.location.origin);
            url.searchParams.set('catalog_fragment', '1');

            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                throw new Error(`Catalog request failed with ${response.status}`);
            }

            const payload = await response.json();
            const template = document.createElement('template');
            template.innerHTML = String(payload.html || '').trim();
            grid.append(template.content);

            nextPageUrl = payload.next_page_url || '';
            catalog.dataset.nextPageUrl = nextPageUrl;

            if (! nextPageUrl) {
                finish();
            }
        } catch (error) {
            observer.unobserve(sentinel);
            retryButton?.classList.remove('hidden');
        } finally {
            setLoading(false);
        }
    };

    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            loadMore();
        }
    }, { rootMargin: '700px 0px' });

    retryButton?.addEventListener('click', () => {
        observer.observe(sentinel);
        loadMore();
    });

    if (nextPageUrl) {
        observer.observe(sentinel);
    } else {
        finish();
    }
});
