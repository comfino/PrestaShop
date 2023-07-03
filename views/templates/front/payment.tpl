{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 *}

<style>
    div#comfino-box.comfino {
        max-width: 700px;
        margin: 0 auto;
        padding: 10px;
        font-family: Lato, sans-serif;
        background-color: #fff;
        display: none;
    }

    div#comfino-box.comfino div.comfino-box .header {
        display: flex;
        flex-direction: column;
    }

    div#comfino-box.comfino div.comfino-box .header .comfino-logo {
        max-height: 40px;
        width: 260px;
        margin: 0 auto;
    }

    div#comfino-box.comfino div.comfino-box .header .comfino-title {
        text-align: center;
        font-weight: bold;
        font-size: 1.2rem;
        margin: 0.875rem 0;
        color: #232323;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox {
        background-color: #fff;
        box-shadow: none;
        -moz-box-shadow: none;
        -webkit-box-shadow: none;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment {
        border-bottom: 1px solid #ddd;
        display: flex;
        align-items: center;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment label {
        display: flex;
        align-items: center;
        width: 100%;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment label .comfino-single-payment__text {
        padding-left: 5px;
        font-weight: normal;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment input {
        width: 25px;
        height: 25px;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:checked, div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:not(:checked) {
        position: absolute;
        left: -9999px;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:checked+label, div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:not(:checked)+label {
        position: relative;
        padding-left: 28px;
        cursor: pointer;
        line-height: 20px;
        color: #666;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:checked+label:before, div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:not(:checked)+label:before {
        content: '';
        position: absolute;
        left: 2px;
        width: 18px;
        height: 18px;
        border: 2px solid #ddd;
        border-radius: 100%;
        background: #fff;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:checked+label:after, div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:not(:checked)+label:after {
        content: '';
        width: 8px;
        height: 8px;
        background: #599e33;;
        position: absolute;
        left: 7px;
        border-radius: 100%;
        -webkit-transition: all 0.2s ease;
        transition: all 0.2s ease;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:not(:checked)+label:after {
        opacity: 0;
        -webkit-transform: scale(0);
        transform: scale(0);
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-select-payment div.comfino-order .comfino-single-payment .comfino-input[type="radio"]:checked+label:after {
        opacity: 1;
        -webkit-transform: scale(1);
        transform: scale(1);
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-payment-box {
        background-color: rgb(221, 221, 221);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem 0;
        font-size: 1.2rem;
        margin-top: 1rem;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-payment-box .comfino-payment-title {
        color: #666;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox section.comfino-payment-box .comfino-total-payment {
        font-weight: bold;
        color: #232323;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-installments-box {
        display: flex;
        flex-direction: column;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-installments-box .comfino-installments-title {
        font-size: 1.2rem;
        font-weight: bold;
        color: #232323;
        text-align: center;
        padding: 20px 0;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-installments-box .comfino-quantity-select {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        max-width: 90%;
        justify-content: center;
        margin: 0 auto;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-installments-box .comfino-quantity-select .comfino-select-box {
        display: flex;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-installments-box .comfino-quantity-select .comfino-select-box .comfino-installments-quantity {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: rgb(221, 221, 221);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.4rem;
        margin: 10px 20px;
        cursor: pointer;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-installments-box .comfino-quantity-select .comfino-select-box .comfino-active {
        background-color: #599e33;;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-monthly-box {
        background-color: #599e33;;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem 0;
        font-size: 1.2rem;
        color: #fff;
        margin-top: 1rem;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-monthly-box .comfino-monthly-title {
        font-size: 1.2rem;
        text-align: center;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-monthly-box .comfino-monthly-installment {
        font-weight: bold;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-summary-box {
        text-align: center;
        font-size: .8rem;
        color: gray;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-summary-box .comfino-summary-total {
        padding: .5rem 0;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-summary-box .comfino-rrso {
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer .comfino-footer-link {
        display: block;
        text-align: center;
        padding: .5rem 0;
        color: gray;
        font-size: .73rem;
        margin: 1rem 0 2rem 0;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay {
        padding: 1rem;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__title {
        text-align: center;
        font-weight: bold;
        font-size: 1.2rem;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__title span {
        display: block;
        color: #599e33;
        padding: .3rem 0;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__box {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__box .comfino-helper-box {
        display: flex;
        margin-top: 10px;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__box .comfino-helper-box .comfino-payment-delay__single-instruction {
        width: 140px;
        margin: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__box .comfino-helper-box .comfino-payment-delay__single-instruction .comfin-single-instruction__text {
        text-align: center;
        font-size: .9rem;
        padding-top: 1rem;
        color: #666;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__box .comfino-helper-box .comfino-payment-delay__single-instruction .single-instruction-img__background {
        width: 50px;
        height: 50px;
        background-color: #599e33;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .5rem;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox .comfino-payment-delay .comfino-payment-delay__box .comfino-helper-box .comfino-payment-delay__single-instruction .single-instruction-img__background .single-instruction-img {
        width: 100px;
        filter: invert(100%)
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer .comfino-modal {
        position: fixed;
        width: 100vw;
        height: 100vh;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        top: 0;
        left: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer .comfino-modal.open {
        visibility: visible;
        opacity: 1;
        transition-delay: 0s;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer .comfino-modal-bg {
        position: absolute;
        background: rgba(0,0,0,0.2);
        width: 100%;
        height: 100%;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer .comfino-modal-container {
        width: 50%;
        border-radius: 10px;
        background: #fff;
        position: relative;
        padding: 30px;
        max-height: 90%;
        overflow: auto;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer .comfino-modal-close {
        position: absolute;
        right: 15px;
        top: 15px;
        outline: none;
        appearance: none;
        color: red;
        background: none;
        border: 0;
        font-weight: bold;
        cursor: pointer;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer a.representative {
        cursor: pointer;
    }

    div#comfino-box.comfino div.comfino-box main.comfino-subbox footer a.representative:hover {
        filter: brightness(150%);
    }

    div#comfino-box.comfino .comfino-icon {
        width: 32px;
        height: 32px;
        padding-top: 2px;
        margin-top: 3px;
        margin-bottom: 3px;
    }

    div#comfino-box.comfino #comfino-installments {
        display: none;
    }

    div#comfino-box.comfino #comfino-payment-delay {
        display: none;
    }
</style>

<div id="comfino-box" class="comfino">
    <div class="comfino-box">
        <div class="header">
            <img src="//widget.comfino.pl/image/comfino/ecommerce/prestashop/comfino_logo.svg" alt="" class="comfino-logo" />
            <div class="comfino-title">{l s="Choose payment method" mod="comfino"}</div>
        </div>
        <main class="comfino-subbox">
            <section id="comfino-offer-items" class="comfino-select-payment"></section>
            <section class="comfino-payment-box">
                <div class="comfino-payment-title">{l s="Value of purchase" mod="comfino"}:</div>
                <div id="comfino-total-payment" class="comfino-total-payment"></div>
            </section>
            <section id="comfino-installments">
                <section class="comfino-installments-box">
                    <div class="comfino-installments-title">{l s="Choose number of instalments" mod="comfino"}</div>
                    <div id="comfino-quantity-select" class="comfino-quantity-select"></div>
                </section>
                <section class="comfino-monthly-box">
                    <div class="comfino-monthly-title">{l s="Monthly instalment" mod="comfino"}:</div>
                    <div id="comfino-monthly-installment" class="comfino-monthly-installment"></div>
                </section>
                <section class="comfino-summary-box">
                    <div class="comfino-summary-total">{l s="Total amount to pay" mod="comfino"}: <span id="comfino-summary-total"></span></div>
                    <div class="comfino-rrso">RRSO <span id="comfino-rrso"></span></div>
                    <div id="comfino-description-box" class="comfino-description-box"></div>
                </section>
                <footer>
                    <a id="comfino-repr-example-link" class="representative comfino-footer-link">{l s="Representative example" mod="comfino"}</a>
                    <div id="modal-repr-example" class="comfino-modal">
                        <div class="comfino-modal-bg comfino-modal-exit"></div>
                        <div class="comfino-modal-container">
                            <span id="comfino-repr-example"></span>
                            <button class="comfino-modal-close comfino-modal-exit">&times;</button>
                        </div>
                    </div>
                </footer>
            </section>
            <section id="comfino-payment-delay" class="comfino-payment-delay">
                <div class="comfino-payment-delay__title">{l s="Buy now, pay in 30 days" mod="comfino"} <span>{l s="How it's working?" mod="comfino"}</span></div>
                <div class="comfino-payment-delay__box">
                    <div class="comfino-helper-box">
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="//widget.comfino.pl/image/comfino/ecommerce/prestashop/icons/cart.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s="Put the product in the basket" mod="comfino"}</div>
                        </div>
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="//widget.comfino.pl/image/comfino/ecommerce/prestashop/icons/twisto.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s="Choose Twisto payment" mod="comfino"}</div>
                        </div>
                    </div>
                    <div class="comfino-helper-box">
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="//widget.comfino.pl/image/comfino/ecommerce/prestashop/icons/check.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s="Check the products at home" mod="comfino"}</div>
                        </div>
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="//widget.comfino.pl/image/comfino/ecommerce/prestashop/icons/wallet.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s="Pay in 30 days" mod="comfino"}</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
    window.Comfino = {
        offerList: { data: null, elements: null },
        selectedOffer: 0,
        debugMode: false,

        consoleMsg(message, level)
        {
            let callArgs = [];

            if (level !== 'error' && level !== 'warn') {
                callArgs.push('%cComfino plugin:%c ' + message);
                callArgs.push('color: white; background-color: #227b34; font-weight: bold; line-height: 18px');
                callArgs.push('color: black; background-color: #cae8c9; font-weight: normal; line-height: 18px');
            } else {
                callArgs.push('Comfino plugin: ' + message);
            }

            if (arguments.length > 2) {
                callArgs.push(...Array.from(arguments).slice(2, arguments.length));
            }

            switch (level) {
                case 'error':
                    console.error(...callArgs);
                    break;

                case 'warn':
                    console.warn(...callArgs);
                    break;

                case 'info':
                    console.info(...callArgs);
                    break;

                case 'debug':
                    console.debug(...callArgs);
                    break;

                case 'log':
                default:
                    console.log(...callArgs);
            }
        },

        selectTerm(loanTermBox, termElement)
        {
            if (Comfino.offerList.data === null || Comfino.offerList.elements === null) {
                let offers = JSON.parse(sessionStorage.getItem('Comfino.offers'));
                let offerData = [];
                let offerElements = [];

                offers.forEach((item, index) => {
                    offerData[index] = item;
                    offerElements[index] = document.getElementById('comfino-opt-' + item.type);
                });

                Comfino.offerList.data = offerData;
                Comfino.offerList.elements = offerElements;
            }

            loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach((item) => {
                item.classList.remove('comfino-active');
            });

            if (termElement !== null) {
                termElement.classList.add('comfino-active');

                for (let loanParams of Comfino.offerList.data[Comfino.selectedOffer].loanParameters) {
                    if (loanParams.loanTerm === parseInt(termElement.dataset.term)) {
                        document.getElementById('comfino-total-payment').innerHTML = loanParams.sumAmountFormatted;
                        document.getElementById('comfino-monthly-installment').innerHTML = loanParams.instalmentAmountFormatted;
                        document.getElementById('comfino-summary-total').innerHTML = loanParams.toPayFormatted;
                        document.getElementById('comfino-rrso').innerHTML = loanParams.rrso + '%';
                        document.getElementById('comfino-description-box').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].description;
                        document.getElementById('comfino-repr-example').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].representativeExample;

                        Comfino.offerList.elements[Comfino.selectedOffer].dataset.sumamount = loanParams.sumAmount;
                        Comfino.offerList.elements[Comfino.selectedOffer].dataset.term = loanParams.loanTerm;

                        fetch(
                            Comfino.getModuleApiUrl({
                                loan_type: Comfino.offerList.data[Comfino.selectedOffer].type,
                                loan_amount: loanParams.sumAmount,
                                loan_term: loanParams.loanTerm
                            }),
                            { method: 'POST', data: '' }
                        );

                        break;
                    }
                }
            } else {
                document.getElementById('comfino-total-payment').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].sumAmountFormatted;

                fetch(
                    Comfino.getModuleApiUrl({
                        loan_type: Comfino.offerList.data[Comfino.selectedOffer].type,
                        loan_amount: Comfino.offerList.data[Comfino.selectedOffer].sumAmount,
                        loan_term: 1
                    }),
                    { method: 'POST', data: '' }
                );
            }
        },

        selectCurrentTerm(loanTermBox, term)
        {
            let termElement = loanTermBox.querySelector('div > div[data-term="' + term + '"]');

            if (termElement !== null) {
                loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach((item) => {
                    item.classList.remove('comfino-active');
                });

                termElement.classList.add('comfino-active');

                for (let loanParams of Comfino.offerList.data[Comfino.selectedOffer].loanParameters) {
                    if (loanParams.loanTerm === parseInt(term)) {
                        document.getElementById('comfino-total-payment').innerHTML = loanParams.sumAmountFormatted;
                        document.getElementById('comfino-monthly-installment').innerHTML = loanParams.instalmentAmountFormatted;
                        document.getElementById('comfino-summary-total').innerHTML = loanParams.toPayFormatted;
                        document.getElementById('comfino-rrso').innerHTML = loanParams.rrso + '%';
                        document.getElementById('comfino-description-box').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].description;
                        document.getElementById('comfino-repr-example').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].representativeExample;

                        fetch(
                            Comfino.getModuleApiUrl({
                                loan_type: Comfino.offerList.data[Comfino.selectedOffer].type,
                                loan_amount: loanParams.sumAmount,
                                loan_term: loanParams.loanTerm
                            }),
                            { method: 'POST', data: '' }
                        );

                        break;
                    }
                }
            } else {
                document.getElementById('comfino-total-payment').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].sumAmountFormatted;

                fetch(
                    Comfino.getModuleApiUrl({
                        loan_type: Comfino.offerList.data[Comfino.selectedOffer].type,
                        loan_amount: Comfino.offerList.data[Comfino.selectedOffer].sumAmount,
                        loan_term: 1
                    }),
                    { method: 'POST', data: '' }
                );
            }
        },

        fetchProductDetails(offerData)
        {
            if (offerData.type === 'PAY_LATER') {
                document.getElementById('comfino-payment-delay').style.display = 'block';
                document.getElementById('comfino-installments').style.display = 'none';
            } else {
                let loanTermBox = document.getElementById('comfino-quantity-select');
                let loanTermBoxContents = ``;

                offerData.loanParameters.forEach((item, index) => {
                    if (index === 0) {
                        loanTermBoxContents += `<div class="comfino-select-box">`;
                    } else if (index % 3 === 0) {
                        loanTermBoxContents += `</div><div class="comfino-select-box">`;
                    }

                    loanTermBoxContents += `<div data-term="` + item.loanTerm + `" class="comfino-installments-quantity">` + item.loanTerm + `</div>`;

                    if (index === offerData.loanParameters.length - 1) {
                        loanTermBoxContents += `</div>`;
                    }
                });

                loanTermBox.innerHTML = loanTermBoxContents;

                loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach((item) => {
                    item.addEventListener('click', (event) => {
                        event.preventDefault();
                        Comfino.selectTerm(loanTermBox, event.target);
                    });
                });

                document.getElementById('comfino-payment-delay').style.display = 'none';

                let installmentsElement = document.getElementById('comfino-installments');

                installmentsElement.style.display = 'block';

                if (offerData.type === 'BLIK') {
                    installmentsElement.querySelector('section.comfino-installments-box').style.display = 'none';
                    installmentsElement.querySelector('section.comfino-monthly-box').style.display = 'none';
                    installmentsElement.querySelector('section.comfino-summary-box').querySelector('div.comfino-summary-total').style.display = 'none';
                    installmentsElement.querySelector('section.comfino-summary-box').querySelector('div.comfino-rrso').style.display = 'none';
                    installmentsElement.querySelector('footer').style.display = 'none';
                } else {
                    installmentsElement.querySelector('section.comfino-installments-box').style.display = 'flex';
                    installmentsElement.querySelector('section.comfino-monthly-box').style.display = 'flex';
                    installmentsElement.querySelector('section.comfino-summary-box').querySelector('div.comfino-summary-total').style.display = 'block';
                    installmentsElement.querySelector('section.comfino-summary-box').querySelector('div.comfino-rrso').style.display = 'block';
                    installmentsElement.querySelector('footer').style.display = 'block';
                }
            }
        },

        putDataIntoSection(data)
        {
            let offerData = [];
            let offerElements = [];

            data.forEach((item, index) => {
                let comfinoOffer = document.createElement('div');

                comfinoOffer.dataset.type = item.type;
                comfinoOffer.dataset.sumamount = item.sumAmount;
                comfinoOffer.dataset.term = item.loanTerm;

                comfinoOffer.classList.add('comfino-order');

                let comfinoOptId = 'comfino-opt-' + item.type;

                comfinoOffer.innerHTML = `
                    <div class="comfino-single-payment">
                        <input type="radio" id="` + comfinoOptId + `" class="comfino-input" name="comfino" />
                        <label for="` + comfinoOptId + `">
                            <div class="comfino-icon">` + item.icon + `</div>
                            <span class="comfino-single-payment__text">` + item.name + `</span>
                        </label>
                    </div>
                `;

                if (index === 0) {
                    let paymentOption = comfinoOffer.querySelector('#' + comfinoOptId);

                    comfinoOffer.classList.add('comfino-selected');
                    paymentOption.setAttribute('checked', 'checked');

                    Comfino.fetchProductDetails(item);
                }

                offerData[index] = item;
                offerElements[index] = document.getElementById('comfino-offer-items').appendChild(comfinoOffer);
            });

            return { data: offerData, elements: offerElements };
        },

        getModuleApiUrl(queryStringParams)
        {
            let moduleApiUrl = '{$set_info_url|escape:"javascript":"UTF-8"}'.replace(/&amp;/g, '&');
            let urlParams = new URLSearchParams(queryStringParams);

            if (queryStringParams) {
                moduleApiUrl += (moduleApiUrl.indexOf('?') > 0 ? '&' : '?') + urlParams.toString();
            }

            return moduleApiUrl;
        },

        /**
         * Get offers from API.
         */
        initPayments()
        {
            if (window.location.hash && window.location.hash.substring(1) === 'comfino_debug') {
                Comfino.debugMode = true;

                Comfino.consoleMsg('Debug mode activated.', 'info');
            }

            let comfinoPaywallItem = document.querySelector('input[data-module-name="comfino"]');

            if (comfinoPaywallItem) {
                if (Comfino.debugMode) {
                    Comfino.consoleMsg('Paywall item found.', 'debug', comfinoPaywallItem);
                }

                if (comfinoPaywallItem.parentNode.parentNode.querySelector('label span')) {
                    comfinoPaywallItem.parentNode.parentNode.querySelector('label').style.display = 'inline-flex';
                    comfinoPaywallItem.parentNode.parentNode.querySelector('label').style.flexDirection = 'row-reverse';
                    comfinoPaywallItem.parentNode.parentNode.querySelector('label span').style.paddingLeft = '10px';
                }

                comfinoPaywallItem.addEventListener('click', () => {
                    let offerWrapper = document.getElementById('comfino-offer-items');

                    document.getElementById('comfino-box').style.display = 'block';

                    offerWrapper.innerHTML = '<p>{l s="Loading..." mod="comfino"}</p>';

                    if (Comfino.debugMode) {
                        Comfino.consoleMsg('comfinoPaywallItem[click]', 'debug', offerWrapper);
                    }

                    fetch(Comfino.getModuleApiUrl({ldelim}type: 'data'{rdelim}))
                        .then((response) => response.json())
                        .then((data) => {
                            sessionStorage.setItem('Comfino.offers', JSON.stringify(data));

                            if (Comfino.debugMode) {
                                Comfino.consoleMsg('Offer fetched.', 'debug', data);
                            }

                            if (!data.length) {
                                offerWrapper.innerHTML = `<p class="alert alert-danger">{l s="No offers available." mod="comfino"}</p>`;

                                return;
                            }

                            let loanTermBox = document.getElementById('comfino-quantity-select');

                            offerWrapper.innerHTML = '';
                            Comfino.offerList = Comfino.putDataIntoSection(data);

                            if (Comfino.debugMode) {
                                Comfino.consoleMsg('Comfino.offerList', 'debug', Comfino.offerList);
                            }

                            Comfino.selectTerm(loanTermBox, loanTermBox.querySelector('div > div[data-term="' + Comfino.offerList.data[Comfino.selectedOffer].loanTerm + '"]'));

                            Comfino.offerList.elements.forEach((item, index) => {
                                item.querySelector('label').addEventListener('click', () => {
                                    Comfino.selectedOffer = index;

                                    Comfino.fetchProductDetails(Comfino.offerList.data[Comfino.selectedOffer]);
                                    Comfino.offerList.elements.forEach(() => { item.classList.remove('comfino-selected'); });

                                    item.classList.add('comfino-selected');

                                    Comfino.selectCurrentTerm(loanTermBox, Comfino.offerList.elements[Comfino.selectedOffer].dataset.term);
                                });
                            });

                            document.getElementById('comfino-repr-example-link').addEventListener('click', (event) => {
                                event.preventDefault();
                                document.getElementById('modal-repr-example').classList.add('open');
                            });

                            document.getElementById('modal-repr-example').querySelector('button.comfino-modal-exit').addEventListener('click', (event) => {
                                event.preventDefault();
                                document.getElementById('modal-repr-example').classList.remove('open');
                            });

                            document.getElementById('modal-repr-example').querySelector('div.comfino-modal-exit').addEventListener('click', (event) => {
                                event.preventDefault();
                                document.getElementById('modal-repr-example').classList.remove('open');
                            });
                        }).catch((error) => {
                            offerWrapper.innerHTML = `<p class="alert alert-danger">{l s="There was an error while performing this operation" mod="comfino"}: ` + error + `</p>`;
                        });
                });
            } else {
                Comfino.consoleMsg('Comfino paywall section not found. Plugin not initialized.', 'warn');
            }
        }
    };

    Comfino.initPayments();
</script>
