/* Comfino web frontend SDK — PrestaShop checkout init */
(function () {
    'use strict';

    // Comfino payment method configuration
    const config = window.comfinoSettings || {};

    if (!config.sdkScriptUrl || !config.authToken) {
        return;
    }

    // productTypes: null = no filter active, [] = all filtered, [...] = filtered subset to pass to bootstrapPaywall().
    if (Array.isArray(config.productTypes) && config.productTypes.length === 0) {
        // All product types filtered out for this cart — don't load the paywall SDK.
        return;
    }

    /* All paywall bootstrap options assigned directly from comfinoSettings — Smarty + json_encode preserves scalar
       types and the insertion order of associative arrays (the paywall renderer relies on creditors map ordering). */
    const comfinoPaywallData = {
        authToken: config.authToken,
        loggingToken: config.loggingToken || '',
        trackId: config.trackId || '',
        loanAmount: config.loanAmount,
        platform: 'prestashop',
        environment: config.environment,
        productTypes: config.productTypes,
        allowedProductsConfig: config.allowedProductsConfig,
        cart: config.cart,
        paywallSettings: config.paywallSettings,
        shopEnvironment: config.shopEnvironment,
        directRedirect: config.directRedirect,
        creditors: config.creditors,
        productTypeNames: config.productTypeNames,
        paymentMethodItem: { auth: config.paymentMethodAuth || '' }
    };

    /* Resolve visible paywall container — guards against page-builder previews rendering a hidden duplicate of the
       checkout payment block. */
    function isInVisibleContext(element)
    {
        let node = element;

        while (node && node !== document.body) {
            const computedStyle = window.getComputedStyle(node);

            if (computedStyle.display === 'none' || computedStyle.visibility === 'hidden') {
                return false;
            }

            node = node.parentElement;
        }

        return true;
    }

    function resolvePaywallContainer()
    {
        const candidates = document.querySelectorAll('[id="comfino-paywall-container"]');

        /* Single container — return it regardless of current visibility. The PrestaShop payment option is hidden by the
           CSS gate (views/css/front/comfino-item-gate.css) until the SDK signals readiness, and certain OPC modules
           pre-render the block before another method is selected. Filtering by visibility here would silently abort
           bootstrap when another payment method is the default. */
        if (candidates.length <= 1) {
            return candidates[0] || null;
        }

        /* Multiple containers — typical of builder-preview rendering a duplicate hidden checkout. Pick the one in a
           visible ancestor chain so the visible checkout drives the paywall. */
        for (let i = 0; i < candidates.length; i++) {
            if (isInVisibleContext(candidates[i])) {
                return candidates[i];
            }
        }

        return null;
    }

    /* Load the Comfino web frontend SDK. The bundle is shipped as a UMD module — when RequireJS's global define()
       is present (some PS themes ship it), the UMD wrapper would take the AMD branch and never populate window.Comfino.
       We temporarily clear window.define for the duration of the script load to force the global-assignment branch and
       restore it in both onload and onerror. */
    function loadSdk(cfg)
    {
        // Idempotency guard — bypass injection if a previous mount already resolved the SDK on this page.
        if (window.Comfino && typeof window.Comfino.bootstrapPaywall === 'function') {
            return Promise.resolve(window.Comfino);
        }

        if (window.__comfinoSdkPromise) {
            return window.__comfinoSdkPromise;
        }

        window.__comfinoSdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = cfg.sdkScriptUrl;
            script.setAttribute('data-comfino-sdk', '1');
            script.setAttribute('data-cfasync', 'false');

            if (cfg.scriptNonce) {
                script.setAttribute('nonce', cfg.scriptNonce);
            }

            /* Scope the define-clear to this single script load. Restore window.define on both load AND error so a
               failed script tag never leaves AMD-aware modules broken. */
            const savedDefine = window.define;
            window.define = undefined;

            script.onload = () => {
                window.define = savedDefine;

                resolve(window.Comfino);
            };
            script.onerror = (error) => {
                window.define = savedDefine;
                window.__comfinoSdkPromise = null;

                reject(error);
            };

            document.head.appendChild(script);
        });

        return window.__comfinoSdkPromise;
    }

    function bootstrap()
    {
        /* One-shot bootstrap guard. OPC modules (TheCheckout, SuperCheckout, OPC) re-emit this <script> tag inside
           their AJAX payment-block re-render — the browser re-executes the IIFE on every cart/shipping/step change.
           The bootstrapPaywall() must run exactly once per page; subsequent rebuilds (new #comfino-paywall-container
           element identity) are owned by PrestaShopPaywallController.startSpaObserver inside the SDK, which sees the
           DOM mutation and runs destroy() + init() with its cached paywallData.

           NOTE: the cached paywallData does NOT pick up a fresh authToken from a re-emitted window.comfinoSettings.
           The HMAC token has a 15-minute server-enforced lifetime; a single checkout session is well within that
           budget. Long-running OPC sessions that outlive the token are an SDK-side follow-up (no public method exists
           today to push a refreshed token into a live PaywallManager). */
        if (window.__comfinoPaywallBootstrapped) {
            return;
        }

        loadSdk(config).then((sdk) => {
            if (!sdk || typeof sdk.bootstrapPaywall !== 'function') {
                return;
            }

            const container = resolvePaywallContainer();

            if (!container) {
                return;
            }

            comfinoPaywallData.container = container;

            window.__comfinoPaywallBootstrapped = true;

            sdk.bootstrapPaywall(comfinoPaywallData);
        }).catch(() => {
            /* Script load failed — leave the checkout unaffected (Comfino tile won't render). The shop's server-side
               checkout will still place the order via other payment methods. */
        });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        bootstrap();
    } else {
        document.addEventListener('DOMContentLoaded', bootstrap);
    }

    /* Cart-refresh on `updatePaymentMethods` / OPC events is owned by the SDK's PrestaShopPaywallController — it reads
       the server-authoritative #comfino-loan-amount input and drives the paywall reload. Plugin-side cart handling
       stays out of the way. */
}());
