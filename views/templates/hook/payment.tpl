{*
* 2007-2016 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2021 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<p class="payment_module">
	<a id="pay-with-comperia">
		{if $presentation_type == 'only_icon' || $presentation_type == 'icon_and_text'}
			<img src="{$logo_url|escape:'htmlall':'UTF-8'}" alt="{l s='Pay with comfino' mod='comfino'}" width="86" height="49"/>
		{/if}
		{if $presentation_type == 'only_text' || $presentation_type == 'icon_and_text'}
			{$pay_with_comfino_text|escape:'htmlall':'UTF-8'}
		{/if}
	</a>
	<div id="comfino-offer-items" style="display: flex; column-gap: 15px;">

	</div>
	<a href="{$go_to_payment_url|escape:'htmlall':'UTF-8'}" id="go-to-payment" style="display: none;">
		{l s='Go to payment' mod='comfino'}
	</a>
</p>


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

	#go-to-payment {
		padding: 10px;
		font-size: 23px;
		background: rgb(26, 129, 150);
		color: white;
		margin: 20px 20px;
	}
</style>

<script>
	  window.onload = function () {
        /**
         * Get data about offer from API
         */
        document.querySelector('#pay-with-comperia').addEventListener('click', function () {
            let offerWrapper = document.querySelector('#comfino-offer-items');
            offerWrapper.innerHTML = '<p>{l s='Loading...' mod='comfino'}</p>'

            fetch('{$set_info_url|escape:'htmlall':'UTF-8'}?type=data')
                .then(response => response.json())
                .then(function (data) {
                    offerWrapper.innerHTML = '';
                    let offerList = putDataIntoSection(data);

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
                              item_sec.style.border = '0px';
                            })

                            item.style.border = '1px solid {$main_color|escape:'htmlall':'UTF-8'}';
                            document.querySelector('#go-to-payment').style.display = 'inline-block';
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

            let content = `
                        <div class="icon-hidden" style="display: none">`+item.icon+`</div>
                            <div class="comfino-icon" style="margin-bottom: 10px;">`+item.icon+`</div>
                            <div class="name" style="margin-bottom: 10px;"><strong>`+item.name+`</strong></div>
                            <div class="offer" style="margin-bottom: 10px;">
                                <div><strong>`+item.loanTerm+` rat x `+item.instalmentAmount+` zł</strong></div>
                                <div>Całkowita kwota do spłaty: `+item.sumAmount+` zł, RRSO: `+item.rrso+` %</div>
                            </div>
                            <div class="description" style="margin-bottom: 10px;">`+item.description+`</div>
                            <div><a data-modal="modal-`+item.type+`">Przykład reprezentatywny</a></div>
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
