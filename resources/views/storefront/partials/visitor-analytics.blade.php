<script>
(function (window, document) {
    'use strict';

    var endpoint = @json(route('visitor.analytics.track'));
    var csrfToken = @json(csrf_token());
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

    function track(eventType, details) {
        details = details || {};
        var id = visitorId();
        var payload = Object.assign({}, details, {
            _token: csrfToken,
            visitor_id: id,
            event_type: eventType,
            event_id: details.event_id || [eventType, id, Date.now(), Math.random().toString(16).slice(2)].join('_'),
            path: window.location.pathname,
            referrer: document.referrer || null,
            attribution: attribution()
        });

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

    window.TisiloAnalytics = { track: track, visitorId: visitorId };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { track('page_view'); }, { once: true });
    } else {
        track('page_view');
    }
})(window, document);
</script>
