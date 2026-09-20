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
        const categoryFilter = form.querySelector('[data-category-filter]');
        const target = categoryFilter?.selectedOptions[0]?.dataset.url;

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
                url.searchParams.set(key, normalizedValue);
            }
        });

        window.location.assign(url.toString());
    });
});
