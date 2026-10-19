/**
 * Copyright © 2026 Ruslan Moskalenko. All rights reserved.
 * See LICENSE.txt for license details.
 */
(function () {
    'use strict';

    var script = document.currentScript,
        config,
        nonce,
        gtm;

    if (!script || !script.getAttribute('data-webramos-gtm')) {
        return;
    }
    try {
        config = JSON.parse(script.getAttribute('data-webramos-gtm'));
    } catch (e) {
        return;
    }

    window.dataLayer = window.dataLayer || [];

    // Google's consent API reads the arguments object, not an array.
    function gtag() {
        window.dataLayer.push(arguments);
    }

    function signals(state) {
        return {
            'ad_storage': state,
            'ad_user_data': state,
            'ad_personalization': state,
            'analytics_storage': state
        };
    }

    function withWait(state, extra) {
        var command = signals(state),
            key;

        command['wait_for_update'] = config.consent.waitForUpdate;
        for (key in extra) {
            if (Object.prototype.hasOwnProperty.call(extra, key)) {
                command[key] = extra[key];
            }
        }

        return command;
    }

    // Grants consent once Magento's own cookie notice allows cookies for this website.
    function followMagentoNotice(consent) {
        var granted = false;

        function isAllowed() {
            var match = document.cookie.match(new RegExp('(?:^|; )' + consent.cookie + '=([^;]*)')),
                websites;

            if (!match) {
                return false;
            }
            try {
                websites = JSON.parse(decodeURIComponent(match[1]));
            } catch (e) {
                return false;
            }

            return !!(websites && websites[consent.websiteId]);
        }

        function grant() {
            if (!granted && isAllowed()) {
                granted = true;
                gtag('consent', 'update', signals('granted'));
            }
        }

        grant();
        if (granted) {
            return;
        }
        // Hyvä dispatches this after it writes the cookie.
        window.addEventListener('user-allowed-save-cookie', grant);
        // Luma writes the cookie in a click handler on the button itself, which runs before
        // this listener on the document; the timeout is a margin, not a requirement.
        document.addEventListener('click', function (event) {
            if (event.target && event.target.closest && event.target.closest('#btn-cookie-allow')) {
                setTimeout(grant, 0);
            }
        });
    }

    if (config.consent) {
        gtag('consent', 'default', withWait('denied', {region: config.consent.regions}));
        gtag('consent', 'default', withWait(config.consent.elsewhere, {}));
        if (config.consent.adsDataRedaction) {
            gtag('set', 'ads_data_redaction', true);
        }
        if (config.consent.urlPassthrough) {
            gtag('set', 'url_passthrough', true);
        }
        if (config.consent.mode === 'magento') {
            followMagentoNotice(config.consent);
        }
    }

    // Present only where Magento runs CSP with inline scripts off (the checkout). GTM copies
    // the nonce of its own script to the tag scripts it injects; Custom HTML tags read it
    // from the dataLayer.
    nonce = script.nonce || script.getAttribute('nonce') || '';
    if (nonce && config.exposeNonce) {
        window.dataLayer.push({cspNonce: nonce});
    }

    window.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
    gtm = document.createElement('script');
    gtm.async = true;
    gtm.src = config.src;
    if (nonce) {
        gtm.setAttribute('nonce', nonce);
    }
    (document.head || document.documentElement).appendChild(gtm);
})();
