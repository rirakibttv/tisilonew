<script>
(function (window, document) {
    'use strict';

    var endpoint = @json(route('visitor.analytics.track'));
    var csrfToken = @json(csrf_token());
    var googleAnalyticsEnabled = @json(filter_var($googleAnalyticsSettings['enabled'] ?? false, FILTER_VALIDATE_BOOL));
    var googleMeasurementId = @json($googleAnalyticsSettings['measurement_id'] ?? null);
    var googleAnonymizeIp = @json(filter_var($googleAnalyticsSettings['anonymize_ip'] ?? true, FILTER_VALIDATE_BOOL));
    var metaPixelEnabled = @json(filter_var($metaPixelSettings['enabled'] ?? false, FILTER_VALIDATE_BOOL));
    var metaPixelId = @json($metaPixelSettings['pixel_id'] ?? null);
    var metaPixelEvents = @json(is_array($metaPixelSettings['events'] ?? null) ? $metaPixelSettings['events'] : []);
    var metaEventNames = {
        page_view: 'PageView',
        product_view: 'ViewContent',
        add_to_cart: 'AddToCart',
        initiate_checkout: 'InitiateCheckout',
        add_payment_info: 'AddPaymentInfo',
        purchase: 'Purchase'
    };
    var storageKey = 'tisilo_visitor_id';
    var attributionKey = 'tisilo_traffic_attribution';

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
            var random = Math.random() * 16 | 0;
            return (character === 'x' ? random : (random & 3 | 8)).toString(16);
        });
    }

    function visitorId() {
        var value;
        try { value = window.localStorage.getItem(storageKey); } catch (error) {}

        if (!value || !/^[0-9a-f-]{36}$/i.test(value)) {
            value = uuid();
            try { window.localStorage.setItem(storageKey, value); } catch (error) {}
        }

        document.cookie = 'tisilo_vid=' + encodeURIComponent(value) + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        return value;
    }

    function attribution() {
        var params = new URLSearchParams(window.location.search);
        var current = {};

        ['source', 'medium', 'campaign', 'content', 'term'].forEach(function (key) {
            var value = params.get('utm_' + key);
            if (value) current[key] = value.substring(0, key === 'source' || key === 'medium' ? 100 : 191);
        });

        if (params.get('fbclid')) {
            current.source = current.source || 'facebook';
            current.medium = current.medium || 'paid_social';
            current.click_source = 'facebook';
            var facebookClickId = params.get('fbclid').replace(/[^A-Za-z0-9_-]/g, '').substring(0, 250);
            if (facebookClickId) {
                document.cookie = '_fbc=' + encodeURIComponent('fb.1.' + Date.now() + '.' + facebookClickId) + '; Max-Age=7776000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            }
        } else if (params.get('gclid')) {
            current.source = current.source || 'google';
            current.medium = current.medium || 'cpc';
            current.click_source = 'google';
        }

        if (Object.keys(current).length) {
            try { window.localStorage.setItem(attributionKey, JSON.stringify(current)); } catch (error) {}
            document.cookie = 'tisilo_attr=' + encodeURIComponent(JSON.stringify(current)) + '; Max-Age=7776000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            return current;
        }

        try {
            var stored = JSON.parse(window.localStorage.getItem(attributionKey) || '{}');
            return stored && typeof stored === 'object' ? stored : {};
        } catch (error) {
            return {};
        }
    }

    function initializeGoogleAnalytics(id) {
        if (!googleAnalyticsEnabled || !/^G-[A-Z0-9]{4,20}$/.test(googleMeasurementId || '')) return;

        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', googleMeasurementId, {
            client_id: id,
            send_page_view: false,
            anonymize_ip: googleAnonymizeIp
        });

        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(googleMeasurementId);
        document.head.appendChild(script);
        document.cookie = 'tisilo_ga_cid=' + encodeURIComponent(id) + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }

    function initializeMetaPixel() {
        if (!metaPixelEnabled || !/^[0-9]{5,32}$/.test(metaPixelId || '')) return;

        if (!window.fbq) {
            var fbq = window.fbq = function () {
                fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments);
            };
            if (!window._fbq) window._fbq = fbq;
            fbq.push = fbq;
            fbq.loaded = true;
            fbq.version = '2.0';
            fbq.queue = [];

            var script = document.createElement('script');
            script.async = true;
            script.src = 'https://connect.facebook.net/en_US/fbevents.js';
            var firstScript = document.getElementsByTagName('script')[0];
            if (firstScript && firstScript.parentNode) {
                firstScript.parentNode.insertBefore(script, firstScript);
            } else {
                document.head.appendChild(script);
            }
        }

        window.fbq('init', metaPixelId);
    }

    function trackMetaEvent(eventType, details, eventId) {
        var eventName = metaEventNames[eventType];
        if (!window.fbq || !eventName || metaPixelEvents.indexOf(eventName) === -1) return;

        var metadata = details.metadata && typeof details.metadata === 'object' ? details.metadata : {};
        var parameters = {};
        var value = Number(details.value);
        var quantity = Number(metadata.quantity);

        if (Number.isFinite(value)) {
            parameters.value = value;
            parameters.currency = metadata.currency || 'BDT';
        }
        if (details.product_id) {
            parameters.content_ids = [String(details.product_id)];
            parameters.content_type = 'product';
        }
        if (metadata.product_name) parameters.content_name = String(metadata.product_name);
        if (Number.isFinite(quantity) && quantity > 0) parameters.num_items = quantity;

        window.fbq('track', eventName, parameters, { eventID: eventId });
    }

    function track(eventType, details) {
        details = details || {};
        var id = visitorId();
        var eventId = details.event_id || [eventType, id, Date.now(), Math.random().toString(16).slice(2)].join('_');
        var payload = Object.assign({}, details, {
            _token: csrfToken,
            visitor_id: id,
            event_type: eventType,
            event_id: eventId,
            path: window.location.pathname,
            referrer: document.referrer || null,
            attribution: attribution()
        });

        trackMetaEvent(eventType, payload, eventId);

        return window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        }).catch(function () {});
    }

    var currentVisitorId = visitorId();
    initializeGoogleAnalytics(currentVisitorId);
    initializeMetaPixel();
    window.TisiloAnalytics = { track: track, visitorId: visitorId };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { track('page_view'); }, { once: true });
    } else {
        track('page_view');
    }
})(window, document);
</script>
