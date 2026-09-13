@if($activePopupOffer?->image_url)
    @php
        $popupStorageKey = 'tisilo_popup_offer_'.$activePopupOffer->uuid;
        $hasPopupCopy = filled($activePopupOffer->title)
            || filled($activePopupOffer->description)
            || filled($activePopupOffer->button_text)
            || filled($activePopupOffer->footer_text);
    @endphp

    <div
        class="tisilo-popup-offer"
        data-popup-offer
        data-storage-key="{{ $popupStorageKey }}"
        data-delay="{{ max(0, $activePopupOffer->display_delay_seconds) * 1000 }}"
        hidden
    >
        <button class="tisilo-popup-offer__backdrop" type="button" data-popup-dismiss aria-label="Close offer"></button>

        <section
            class="tisilo-popup-offer__dialog"
            role="dialog"
            aria-modal="true"
            @if(filled($activePopupOffer->title)) aria-labelledby="tisilo-popup-title-{{ $activePopupOffer->id }}" @else aria-label="Special offer" @endif
        >
            <button class="tisilo-popup-offer__close" type="button" data-popup-dismiss aria-label="Close offer">&times;</button>

            @if(filled($activePopupOffer->link_url))
                <a class="tisilo-popup-offer__artwork-link" href="{{ $activePopupOffer->link_url }}" data-popup-accept>
                    <img class="tisilo-popup-offer__artwork" src="{{ $activePopupOffer->image_url }}" alt="{{ $activePopupOffer->title ?: 'Special offer' }}">
                </a>
            @else
                <img class="tisilo-popup-offer__artwork" src="{{ $activePopupOffer->image_url }}" alt="{{ $activePopupOffer->title ?: 'Special offer' }}">
            @endif

            @if($hasPopupCopy)
                <div class="tisilo-popup-offer__content">
                    @if(filled($activePopupOffer->title))
                        <h2 id="tisilo-popup-title-{{ $activePopupOffer->id }}">{{ $activePopupOffer->title }}</h2>
                    @endif
                    @if(filled($activePopupOffer->description))
                        <p>{{ $activePopupOffer->description }}</p>
                    @endif
                    @if(filled($activePopupOffer->button_text) && filled($activePopupOffer->link_url))
                        <a class="tisilo-popup-offer__button" href="{{ $activePopupOffer->link_url }}" data-popup-accept>{{ $activePopupOffer->button_text }}</a>
                    @endif
                    @if(filled($activePopupOffer->footer_text))
                        <small>{{ $activePopupOffer->footer_text }}</small>
                    @endif
                </div>
            @endif
        </section>
    </div>

    <script>
        (() => {
            const popup = document.querySelector('[data-popup-offer]');
            if (!popup) return;

            const key = popup.dataset.storageKey;
            let seen = false;

            try {
                seen = window.localStorage.getItem(key) === 'seen';
            } catch (_) {
                seen = document.cookie.split('; ').some((cookie) => cookie.startsWith(`${key}=`));
            }

            if (seen) return;

            const remember = () => {
                try {
                    window.localStorage.setItem(key, 'seen');
                } catch (_) {
                    document.cookie = `${key}=seen; Max-Age=31536000; Path=/; SameSite=Lax`;
                }
            };

            const close = () => {
                popup.hidden = true;
                document.body.classList.remove('tisilo-popup-open');
                remember();
            };

            popup.querySelectorAll('[data-popup-dismiss]').forEach((button) => button.addEventListener('click', close));
            popup.querySelectorAll('[data-popup-accept]').forEach((link) => link.addEventListener('click', remember));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !popup.hidden) close();
            });

            window.setTimeout(() => {
                popup.hidden = false;
                document.body.classList.add('tisilo-popup-open');
                popup.querySelector('[data-popup-dismiss]')?.focus();
                remember();
            }, Number.parseInt(popup.dataset.delay || '0', 10));
        })();
    </script>
@endif
