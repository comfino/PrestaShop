window.ComfinoPaywall = {
    init: (frontendInitElement, paywallStateUrl, paywallOptions) => {
        window.Comfino = {
            paywallOptions: paywallOptions,
            init: () => {
                const iframe = document.getElementById('comfino-paywall-container');

                if ('priceModifier' in frontendInitElement.dataset) {
                    let priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

                    if (!Number.isNaN(priceModifier)) {
                        iframe.src += ('&priceModifier=' + priceModifier);
                    }
                }

                ComfinoPaywallFrontend.init(frontendInitElement, iframe, Comfino.paywallOptions);
            }
        }

        if (ComfinoPaywallFrontend.isInitialized()) {
            Comfino.init();
        } else {
            Comfino.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', loanParams);

                const url = new URL(paywallStateUrl);
                const urlSearchParams = new FormData();
                const urlParams = {
                    loan_amount: loanParams.loanAmount,
                    loan_type: loanParams.loanType,
                    loan_term: loanParams.loanTerm
                };

                for (let paramName in urlParams) {
                    urlSearchParams.append(paramName, urlParams[paramName]);
                }

                const paymentStateUrl = url.toString();

                fetch(paymentStateUrl, { method: 'POST', body: urlSearchParams }).then(response => {
                    ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', paymentStateUrl, response);
                });
            }

            if (document.readyState === 'complete') {
                Comfino.init();
            } else {
                document.addEventListener('readystatechange', () => {
                    if (document.readyState === 'complete') {
                        Comfino.init();
                    }
                });
            }
        }
    }
}
