{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
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
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}

<section id="comfino-offer-items" style="display: flex; column-gap: 15px;">

</section>

<style>
    .comfino-order {
        position: relative;
        max-width: 50%;
        background: #f5f5f5;
        margin: 0 20px;
        padding: 22px 30px;
        text-align: center;
        cursor: pointer;
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

    }

    .comfino-modal-close {
        position: absolute;
        right: 15px;
        top: 15px;
        outline: none;
        appearance: none;
        color: red;
        background: none;
        border: 0px;
        font-weight: bold;
        cursor: pointer;
    }


    a.representative:hover {
        filter: brightness(150%);
    }
</style>

<script>
    window.onload = function () {
        let offer_list = '';

        /**
         * Get data about offer from API
         */
        document.querySelectorAll('.ps-shown-by-js').forEach(function (item, index) {
            let offerWrapper = document.querySelector('#comfino-offer-items');

            if(item.dataset.moduleName === 'comfino') {
                item.parentNode.parentNode.querySelector('label').style.display = 'inline-flex';
                item.parentNode.parentNode.querySelector('label').style.flexDirection = 'row-reverse';
                item.parentNode.parentNode.querySelector('label span').style.paddingLeft = '10px';

                item.addEventListener('click', function() {
                    offerWrapper.innerHTML = '<p>{l s='Loading...' mod='comfino'}</p>'
                    fetch('{$set_info_url|escape:'htmlall':'UTF-8'}?type=data')
                        .then(response => response.json())
                        .then(function(data) {
                            offerWrapper.innerHTML = '';
                            offerList = putDataIntoSection(data);

                            offerList.forEach(function (item, index) {
                                item.addEventListener('click', function () {
                                    let data = {
                                        loan_type: item.dataset.type,
                                        loan_amount: Math.round(Number.parseFloat(item.dataset.sumamount) * 100),
                                        loan_term: item.dataset.term
                                    };

                                    fetch('{$set_info_url|escape:'htmlall':'UTF-8'}?loan_type='+data.loan_type+'&loan_amount='+data.loan_amount+'&loan_term='+data.loan_term, {
                                        method: 'POST',
                                        data: ''
                                    }).then(response => response.json()).then(data => console.log(data))

                                    offerList.forEach(function (item_sec, index_sec) {
                                        item.classList.remove('selected');
                                        item_sec.style.border = '0px';
                                    })

                                    item.style.border = '1px solid {$main_color|escape:'htmlall':'UTF-8'}';
                                    item.classList.add('selected');


                                })

                                let modals = item.querySelectorAll('[data-modal]');

                                modals.forEach(function(trigger) {
                                    trigger.addEventListener('click', function(event) {
                                        event.preventDefault();
                                        let modal = document.getElementById(trigger.dataset.modal);
                                        modal.classList.add('open');
                                        let exits = modal.querySelectorAll('.comfino-modal-exit');
                                        exits.forEach(function(exit) {
                                            exit.addEventListener('click', function(event) {
                                                event.preventDefault();
                                                modal.classList.remove('open');
                                            });
                                        });
                                    });
                                });
                            });
                        })
                        .catch(function(error) {
                            offerWrapper.innerHTML = `
                            <p class="alert alert-danger">{l s='There was an error while performing this operation: ' mod='comfino'} `+error+`</p>
                            `;
                        })
                });
            }
        });



        let putDataIntoSection = function(data) {
            let offerList = [];
            let i = 0;
            data.forEach(function (item, index) {
                let comfino_offer = document.createElement('div');
                comfino_offer.dataset.type = item.type;
                comfino_offer.dataset.sumamount = item.sumAmount;
                comfino_offer.dataset.term = item.loanTerm;
                comfino_offer.dataset.type = item.type;
                comfino_offer.classList.add('comfino-order');

                if(index === 0) {
                    comfino_offer.classList.add('selected');
                    comfino_offer.style.border = '1px solid {$main_color|escape:'htmlall':'UTF-8'}';
                }

                let content = `
                    <div class="icon-hidden" style="display: none">`+item.icon+`</div>
                        <div class="comfino-icon" style="margin-bottom: 10px;">`+item.icon+`</div>
                        <div class="name" style="margin-bottom: 10px;"><strong>`+item.name+`</strong></div>
                        <div class="offer" style="margin-bottom: 10px;">
                            <div><strong>`+item.loanTerm+` rat x `+item.instalmentAmount+` zł</strong></div>
                            <div>Całkowita kwota do spłaty: <strong>`+item.sumAmount+` zł</strong>, RRSO: `+item.rrso+` %</div>
                        </div>
                        <div class="description" style="margin-bottom: 10px;">`+item.description+`</div>
                        <div><a data-modal="modal-`+item.type+`" class="representative" style="color:#1a8196">Przykład reprezentatywny</a></div>
                        <div class="comfino-modal" id="modal-`+item.type+`">
						  <div class="comfino-modal-bg comfino-modal-exit"></div>
						  <div class="comfino-modal-container">
							<span>`+item.representativeExample+`</span>
							<button class="comfino-modal-close comfino-modal-exit">X</button>
						  </div>
						</div>
                    </div>
                `;

                comfino_offer.innerHTML = content;
                offerList[i] = comfino_offer;
                i++;
                document.querySelector('#comfino-offer-items').appendChild(comfino_offer);
            });
            return offerList;
        }
    }
</script>
