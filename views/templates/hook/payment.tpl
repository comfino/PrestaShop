{*
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<style>
    .comfino {
        max-width: 700px;
        margin: 0 auto;
        padding: 10px;
        font-family: Lato, sans-serif;
        background-color: #fff;
        display: none;
    }

    .header {
        display: flex;
        flex-direction: column;
    }

    .comfino-logo {
        max-height: 40px;
        width: 260px;
        margin: 0 auto;
    }

    .comfino-title {
        text-align: center;
        font-weight: bold;
        font-size: 1.4em;
        margin: 0.875em 0;
        color: #232323;
    }

    .comfino-single-payment {
        border-bottom: 1px solid #ddd;
        display: flex;
        align-items: center;
    }

    .comfino-single-payment label {
        display: flex;
        align-items: center;
        width: 100%;
    }

    .comfino-single-payment__text {
        padding-left: 5px;
        font-size: 1.1em;
        font-weight: normal;
    }

    .comfino-single-payment input {
        width: 25px;
        height: 25px;
    }

    .comfino-input[type="radio"]:checked, .comfino-input[type="radio"]:not(:checked) {
        position: absolute;
        left: -9999px;
    }

    .comfino-input[type="radio"]:checked+label, .comfino-input[type="radio"]:not(:checked)+label {
        position: relative;
        padding-left: 28px;
        cursor: pointer;
        line-height: 20px;
        color: #666;
    }

    .comfino-input[type="radio"]:checked+label:before, .comfino-input[type="radio"]:not(:checked)+label:before {
        content: '';
        position: absolute;
        left: 2px;
        width: 18px;
        height: 18px;
        border: 2px solid #ddd;
        border-radius: 100%;
        background: #fff;
    }

    .comfino-input[type="radio"]:checked+label:after, .comfino-input[type="radio"]:not(:checked)+label:after {
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

    .comfino-input[type="radio"]:not(:checked)+label:after {
        opacity: 0;
        -webkit-transform: scale(0);
        transform: scale(0);
    }

    .comfino-input[type="radio"]:checked+label:after {
        opacity: 1;
        -webkit-transform: scale(1);
        transform: scale(1);
    }

    .comfino-payment-box {
        background-color: rgb(221, 221, 221);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1em 0;
        font-size: 1.2em;
        margin-top: 1em;
    }

    .comfino-payment-title {
        font-size: 1.2em;
        color: #666;
    }

    .comfino-total-payment {
        font-size: 1.2em;
        font-weight: bold;
        color: #232323;
    }

    .comfino-installments-box {
        display: flex;
        flex-direction: column;
    }

    .comfino-installments-title {
        font-size: 1.3em;
        font-weight: bold;
        color: #232323;
        text-align: center;
        padding: 20px 0;
    }

    .comfino-quantity-select {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        max-width: 90%;
        justify-content: center;
        margin: 0 auto;
    }

    .comfino-select-box {
        display: flex;
    }

    .comfino-installments-quantity {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: rgb(221, 221, 221);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.5em;
        margin: 10px 20px;
        cursor: pointer;
    }

    .comfino-active {
        background-color: #599e33;;
    }

    .comfino-monthly-box {
        background-color: #599e33;;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1em 0;
        font-size: 1.2em;
        color: #fff;
        margin-top: 1em;
    }

    .comfino-monthly-title {
        font-size: 1.2em;
        text-align: center;
    }

    .comfino-monthly-installment {
        font-weight: bold;
    }

    .comfino-summary-box {
        text-align: center;
        font-size: .98em;
        color: gray;
    }

    .comfino-summary-total {
        padding: .5em 0;
    }

    .comfino-rrso {
    }

    .comfino-footer-link {
        display: block;
        text-align: center;
        padding: .5em 0;
        color: gray;
        font-size: .87em;
        margin: 1em 0 2em 0;
    }

    .comfino-payment-delay {
        padding: 1em;
    }

    .comfino-payment-delay__title {
        text-align: center;
        font-weight: bold;
        font-size: 1.2em;
    }

    .comfino-payment-delay__title span {
        display: block;
        color: #599e33;
        padding: .3em 0;
    }

    .comfino-payment-delay__box {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
    }

    .comfino-helper-box {
        display: flex;
        margin-top: 10px;
    }

    .comfino-payment-delay__single-instruction {
        width: 140px;
        margin: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .comfin-single-instruction__text {
        text-align: center;
        font-size: .9em;
        padding-top: 1em;
        color: #666;
    }

    .single-instruction-img__background {
        width: 50px;
        height: 50px;
        background-color: #599e33;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .5em;
    }

    .single-instruction-img {
        width: 100px;
        filter: invert(100%)
    }

    .comfino-modal {
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

    .comfino-modal.open {
        visibility: visible;
        opacity: 1;
        transition-delay: 0s;
    }

    .comfino-modal-bg {
        position: absolute;
        background: rgba(0,0,0,0.2);
        width: 100%;
        height: 100%;
    }

    .comfino-modal-container {
        width: 50%;
        border-radius: 10px;
        background: #fff;
        position: relative;
        padding: 30px;
        max-height: 90%;
        overflow: auto;
    }

    .comfino-modal-close {
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

    a.representative {
        cursor: pointer;
    }

    a.representative:hover {
        filter: brightness(150%);
    }

    a.comfino-payment-method {
        padding: 25px 20px !important;
        cursor: pointer;
    }

    a.comfino-payment-method:after {
        width: 14px;
        height: 22px;
        display: block;
        content: "\f078";
        font-family: "FontAwesome";
        font-size: 25px;
        color: #777777;
        position: absolute;
        right: 25px;
        margin-top: -11px;
        top: 50%;
    }

    .comfino-icon {
        width: 32px;
        height: 32px;
        padding-top: 2px;
    }

    .comfino-payment-btn {
        display: block;
        width: fit-content;
        background-color: #599e33;
        color: #fff;
        padding: 1em 2em;
        text-decoration: none;
        text-transform: uppercase;
    }

    .comfino-payment-btn:hover {
        color: #ff0;
    }

    .comfino-bottom-bar {
        margin-top: 10px;
    }

    main.comfino-subbox {
        background-color: #fff;
        box-shadow: none;
        -moz-box-shadow: none;
        -webkit-box-shadow: none;
    }

    #comfino-installments {
        display: none;
    }

    #comfino-payment-delay {
        display: none;
    }
</style>

<div class="row">
    <div class="col-xs-12 col-md-12">
        <p class="payment_module">
            <a id="pay-with-comperia" class="comfino-payment-method">
                {if $presentation_type == 'only_icon' || $presentation_type == 'icon_and_text'}
                    <img style="height: 49px" src="/modules/comfino/views/img/comfino_logo.svg" alt="{l s='Pay with comfino' mod='comfino'}" />
                {/if}
                {if $presentation_type == 'only_text' || $presentation_type == 'icon_and_text'}
                    {$pay_with_comfino_text|escape:'htmlall':'UTF-8'}
                {/if}
            </a>
        </p>
    </div>
</div>

<div id="comfino-box" class="comfino">
    <div class="comfino-box">
        <div class="header">
            <img src="/modules/comfino/views/img/comfino_logo.svg" alt="" class="comfino-logo" />
            <div class="comfino-title">{l s='Choose payment method' mod='comfino'}</div>
        </div>
        <main class="comfino-subbox">
            <section id="comfino-offer-items" class="comfino-select-payment"></section>
            <section class="comfino-payment-box">
                <div class="comfino-payment-title">{l s='Value of purchase' mod='comfino'}:</div>
                <div id="comfino-total-payment" class="comfino-total-payment"></div>
            </section>
            <section id="comfino-installments">
                <section class="comfino-installments-box">
                    <div class="comfino-installments-title">{l s='Choose number of instalments' mod='comfino'}</div>
                    <div id="comfino-quantity-select" class="comfino-quantity-select"></div>
                </section>
                <section class="comfino-monthly-box">
                    <div class="comfino-monthly-title">{l s='Monthly instalment' mod='comfino'}:</div>
                    <div id="comfino-monthly-installment" class="comfino-monthly-installment"></div>
                </section>
                <section class="comfino-summary-box">
                    <div class="comfino-summary-total">{l s='Total amount to pay' mod='comfino'}: <span id="comfino-summary-total"></span></div>
                    <div class="comfino-rrso">RRSO <span id="comfino-rrso"></span></div>
                    <div id="comfino-description-box" class="comfino-description-box"></div>
                </section>
                <footer>
                    <a id="comfino-repr-example-link" class="representative comfino-footer-link">{l s='Representative example' mod='comfino'}</a>
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
                <div class="comfino-payment-delay__title">{l s='Buy now, pay in 30 days' mod='comfino'} <span>{l s='How it\'s working?' mod='comfino'}</span></div>
                <div class="comfino-payment-delay__box">
                    <div class="comfino-helper-box">
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="/modules/comfino/views/img/icons/cart.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s='Put the product in the basket' mod='comfino'}</div>
                        </div>
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="/modules/comfino/views/img/icons/twisto.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s='Choose Twisto payment' mod='comfino'}</div>
                        </div>
                    </div>
                    <div class="comfino-helper-box">
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="/modules/comfino/views/img/icons/check.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s='Check the products at home' mod='comfino'}</div>
                        </div>
                        <div class="comfino-payment-delay__single-instruction">
                            <div class="single-instruction-img__background">
                                <img src="/modules/comfino/views/img/icons/wallet.svg" alt="" class="single-instruction-img" />
                            </div>
                            <div class="comfin-single-instruction__text">{l s='Pay in 30 days' mod='comfino'}</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <div class="comfino-bottom-bar">
        <a id="go-to-payment" href="{$go_to_payment_url|escape:'htmlall':'UTF-8'}" class="comfino-payment-btn">
            {l s='Go to payment' mod='comfino'}
        </a>
    </div>
</div>

<script>
    window.onload = function () {
        let offerList = null;
        let selectedOffer = 0;

        let selectTerm = function (loanTermBox, termElement)
        {
            loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach(function (item) {
                item.classList.remove('comfino-active');
            });

            if (termElement !== null) {
                termElement.classList.add('comfino-active');

                for (let loanParams of offerList.data[selectedOffer].loanParameters) {
                    if (loanParams.loanTerm === parseInt(termElement.dataset.term)) {
                        document.getElementById('comfino-total-payment').innerHTML = loanParams.sumAmountFormatted;
                        document.getElementById('comfino-monthly-installment').innerHTML = loanParams.instalmentAmountFormatted;
                        document.getElementById('comfino-summary-total').innerHTML = loanParams.toPayFormatted;
                        document.getElementById('comfino-rrso').innerHTML = loanParams.rrso + '%';
                        document.getElementById('comfino-description-box').innerHTML = offerList.data[selectedOffer].description;
                        document.getElementById('comfino-repr-example').innerHTML = offerList.data[selectedOffer].representativeExample;

                        offerList.elements[selectedOffer].dataset.sumamount = loanParams.sumAmount;
                        offerList.elements[selectedOffer].dataset.term = loanParams.loanTerm;

                        fetch(
                            getModuleApiUrl({
                                loan_type: offerList.data[selectedOffer].type,
                                loan_amount: loanParams.sumAmount,
                                loan_term: loanParams.loanTerm
                            }),
                            { method: 'POST', data: '' }
                        );

                        break;
                    }
                }
            } else {
                document.getElementById('comfino-total-payment').innerHTML = offerList.data[selectedOffer].sumAmountFormatted;

                fetch(
                    getModuleApiUrl({
                        loan_type: offerList.data[selectedOffer].type,
                        loan_amount: offerList.data[selectedOffer].sumAmount,
                        loan_term: 1
                    }),
                    { method: 'POST', data: '' }
                );
            }
        }

        let selectCurrentTerm = function (loanTermBox, term)
        {
            let termElement = loanTermBox.querySelector('div > div[data-term="' + term + '"]');

            if (termElement !== null) {
                loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach(function (item) {
                    item.classList.remove('comfino-active');
                });

                termElement.classList.add('comfino-active');

                for (let loanParams of offerList.data[selectedOffer].loanParameters) {
                    if (loanParams.loanTerm === parseInt(term)) {
                        document.getElementById('comfino-total-payment').innerHTML = loanParams.sumAmountFormatted;
                        document.getElementById('comfino-monthly-installment').innerHTML = loanParams.instalmentAmountFormatted;
                        document.getElementById('comfino-summary-total').innerHTML = loanParams.toPayFormatted;
                        document.getElementById('comfino-rrso').innerHTML = loanParams.rrso + '%';
                        document.getElementById('comfino-description-box').innerHTML = offerList.data[selectedOffer].description;
                        document.getElementById('comfino-repr-example').innerHTML = offerList.data[selectedOffer].representativeExample;

                        fetch(
                            getModuleApiUrl({
                                loan_type: offerList.data[selectedOffer].type,
                                loan_amount: loanParams.sumAmount,
                                loan_term: loanParams.loanTerm
                            }),
                            { method: 'POST', data: '' }
                        );

                        break;
                    } else {
                        document.getElementById('comfino-total-payment').innerHTML = offerList.data[selectedOffer].sumAmountFormatted;

                        fetch(
                            getModuleApiUrl({
                                loan_type: offerList.data[selectedOffer].type,
                                loan_amount: offerList.data[selectedOffer].sumAmount,
                                loan_term: 1
                            }),
                            { method: 'POST', data: '' }
                        );
                    }
                }
            }
        }

        let fetchProductDetails = function (offerData)
        {
            if (offerData.type === 'PAY_LATER') {
                document.getElementById('comfino-payment-delay').style.display = 'block';
                document.getElementById('comfino-installments').style.display = 'none';
            } else {
                let loanTermBox = document.getElementById('comfino-quantity-select');
                let loanTermBoxContents = ``;

                offerData.loanParameters.forEach(function (item, index) {
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

                loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach(function (item) {
                    item.addEventListener('click', function (event) {
                        event.preventDefault();
                        selectTerm(loanTermBox, event.target);
                    });
                });

                document.getElementById('comfino-payment-delay').style.display = 'none';
                document.getElementById('comfino-installments').style.display = 'block';
            }
        }

        let putDataIntoSection = function (data)
        {
            let offerElements = [];
            let offerData = [];

            data.forEach(function (item, index) {
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

                    fetchProductDetails(item);
                }

                offerData[index] = item;
                offerElements[index] = document.getElementById('comfino-offer-items').appendChild(comfinoOffer);
            });

            return { elements: offerElements, data: offerData };
        }

        let getModuleApiUrl = function (queryStringParams)
        {
            let moduleApiUrl = '{$set_info_url|escape:'htmlall':'UTF-8'}'.replace(/&amp;/g, '&');
            let urlParams = new URLSearchParams(queryStringParams);

            if (queryStringParams) {
                moduleApiUrl += (moduleApiUrl.indexOf('?') > 0 ? '&' : '?') + urlParams.toString();
            }

            return moduleApiUrl;
        }

        /**
         * Get offers from API.
         */
        document.getElementById('pay-with-comperia').addEventListener('click', function () {
            let offerWrapper = document.getElementById('comfino-offer-items');

            document.getElementById('comfino-box').style.display = 'block';

            offerWrapper.innerHTML = '<p>{l s='Loading...' mod='comfino'}</p>';

            fetch(getModuleApiUrl({ldelim}type: 'data'{rdelim}))
                .then(response => response.json())
                .then(function (data) {
                    if (!data.length) {
                        offerWrapper.innerHTML = `<p class="alert alert-danger">{l s='No offers available.' mod='comfino'}</p>`;

                        return;
                    }

                    let loanTermBox = document.getElementById('comfino-quantity-select');

                    offerWrapper.innerHTML = '';
                    offerList = putDataIntoSection(data);

                    selectTerm(loanTermBox, loanTermBox.querySelector('div > div[data-term="' + offerList.data[selectedOffer].loanTerm + '"]'));

                    offerList.elements.forEach(function (item, index) {
                        item.querySelector('label').addEventListener('click', function () {
                            selectedOffer = index;

                            fetchProductDetails(offerList.data[selectedOffer]);

                            offerList.elements.forEach(function () {
                                item.classList.remove('comfino-selected');
                            });

                            item.classList.add('comfino-selected');

                            selectCurrentTerm(loanTermBox, offerList.elements[selectedOffer].dataset.term);
                        });
                    });

                    document.getElementById('comfino-repr-example-link').addEventListener('click', function (event) {
                        event.preventDefault();
                        document.getElementById('modal-repr-example').classList.add('open');
                    });

                    document.getElementById('modal-repr-example').querySelector('button.comfino-modal-exit').addEventListener('click', function (event) {
                        event.preventDefault();
                        document.getElementById('modal-repr-example').classList.remove('open');
                    });

                    document.getElementById('modal-repr-example').querySelector('div.comfino-modal-exit').addEventListener('click', function (event) {
                        event.preventDefault();
                        document.getElementById('modal-repr-example').classList.remove('open');
                    });
                }).catch(function (error) {
                    offerWrapper.innerHTML = `<p class="alert alert-danger">{l s='There was an error while performing this operation' mod='comfino'}: ` + error + `</p>`;
                });
            });
    }
</script>
