/**
 * Payment Form — PayMongo PIPM Flow
 *
 * Card flow:
 *   1. Create Payment Intent via our REST API
 *   2. Create Payment Method directly with PayMongo
 *   3. Attach Payment Method to Payment Intent
 *   4. Handle 3D Secure if needed
 *   5. Redirect to confirmation
 *
 * Bank / e-wallet flow:
 *   1. Create Payment Intent via our REST API (with method type)
 *   2. Create Payment Method with PayMongo (type: dob/dob_ubp/grab_pay)
 *   3. Attach → PayMongo returns redirect URL
 *   4. User authorizes on bank/wallet portal
 *   5. Redirected back to confirmation
 */
(function () {
	'use strict';

	if ( typeof obsidianPayment === 'undefined' ) return;

	const { restUrl, nonce, publicKey, confirmationUrl } = obsidianPayment;
	const PAYMONGO_API = 'https://api.paymongo.com/v1';

	const form    = document.getElementById( 'obsidian-payment-form' );
	const wrap    = document.getElementById( 'obsidian-payment-wrap' );
	if ( ! form || ! wrap ) return;

	const bookingId  = document.getElementById( 'obp-booking-id' )?.value;
	const token      = document.getElementById( 'obp-token' )?.value;
	const totalVal   = parseFloat( document.getElementById( 'obp-total' )?.value || 0 );
	const depositVal = parseFloat( document.getElementById( 'obp-deposit' )?.value || 0 );

	const cardFieldsEl = document.getElementById( 'obp-card-fields' );
	const bankNoticeEl = document.getElementById( 'obp-bank-notice' );
	const bankNameEl   = document.getElementById( 'obp-bank-name' );

	const cardNumberInput = document.getElementById( 'obp-card-number' );
	const cardExpiryInput = document.getElementById( 'obp-card-expiry' );
	const cardCvcInput    = document.getElementById( 'obp-card-cvc' );
	const cardNameInput   = document.getElementById( 'obp-card-name' );
	const submitBtn       = document.getElementById( 'obp-submit' );
	const submitTextEl    = submitBtn?.querySelector( '.obf-submit-text' );
	const messageEl       = document.getElementById( 'obp-message' );

	const rentalAmountEl  = document.getElementById( 'obp-rental-amount' );
	const chargeTotalEl   = document.getElementById( 'obp-charge-total' );
	const paymentLabelEl  = document.getElementById( 'obp-payment-label' );
	const balanceAmountEl = document.getElementById( 'obp-balance-amount' );
	const balanceLineEl   = document.getElementById( 'obp-balance-line' );

	let selectedMethod = 'card';

	const METHOD_LABELS = {
		card:     'Visa / Mastercard',
		dob_ubp:  'BPI Online',
		dob:      'BDO Online',
		grab_pay: 'GrabPay',
	};

	/* ── Format helpers ── */

	function formatCurrency( num ) {
		return '₱' + Math.round( num ).toLocaleString( 'en-PH' );
	}

	/* ── Card number formatting ── */

	cardNumberInput?.addEventListener( 'input', function () {
		let v = this.value.replace( /\D/g, '' ).substring( 0, 16 );
		this.value = v.replace( /(.{4})/g, '$1 ' ).trim();
	});

	/* ── Expiry formatting ── */

	cardExpiryInput?.addEventListener( 'input', function () {
		let v = this.value.replace( /\D/g, '' ).substring( 0, 4 );
		if ( v.length >= 3 ) {
			v = v.substring( 0, 2 ) + ' / ' + v.substring( 2 );
		}
		this.value = v;
	});

	/* ── CVC ── */

	cardCvcInput?.addEventListener( 'input', function () {
		this.value = this.value.replace( /\D/g, '' ).substring( 0, 4 );
	});

	/* ── Payment method selector ── */

	const methodBtns = document.querySelectorAll( '.obp-method-btn' );
	methodBtns.forEach( btn => {
		btn.addEventListener( 'click', function () {
			methodBtns.forEach( b => b.classList.remove( 'active' ) );
			this.classList.add( 'active' );
			selectedMethod = this.dataset.method;
			updateMethodUI();
		});
	});

	function updateMethodUI() {
		const isCard = selectedMethod === 'card';

		if ( cardFieldsEl ) cardFieldsEl.style.display = isCard ? '' : 'none';
		if ( bankNoticeEl ) bankNoticeEl.style.display  = isCard ? 'none' : '';
		if ( bankNameEl )   bankNameEl.textContent       = METHOD_LABELS[ selectedMethod ] || 'your provider';
		if ( submitTextEl ) submitTextEl.textContent      = isCard ? 'Complete Payment' : 'Pay with ' + ( METHOD_LABELS[ selectedMethod ] || 'Bank' );
	}

	/* ── Payment option toggle ── */

	const paymentRadios = form.querySelectorAll( 'input[name="payment_option"]' );
	paymentRadios.forEach( radio => {
		radio.addEventListener( 'change', updateChargeSummary );
	});

	function getSelectedOption() {
		const checked = form.querySelector( 'input[name="payment_option"]:checked' );
		return checked ? checked.value : 'down';
	}

	function updateChargeSummary() {
		const option = getSelectedOption();
		let rentalCharge, balance;

		if ( option === 'full' ) {
			rentalCharge = totalVal;
			balance      = 0;
			if ( paymentLabelEl ) paymentLabelEl.textContent = '100% full prepayment';
		} else {
			rentalCharge = Math.round( totalVal * 0.50 );
			balance      = totalVal - rentalCharge;
			if ( paymentLabelEl ) paymentLabelEl.textContent = '50% down payment';
		}

		const chargeTotal = rentalCharge + depositVal;

		if ( rentalAmountEl )  rentalAmountEl.textContent  = formatCurrency( rentalCharge );
		if ( chargeTotalEl )   chargeTotalEl.textContent    = formatCurrency( chargeTotal );
		if ( balanceAmountEl ) balanceAmountEl.textContent  = formatCurrency( balance );

		if ( balanceLineEl ) {
			balanceLineEl.style.display = balance > 0 ? '' : 'none';
		}
	}

	/* ── Validation ── */

	function validateCard() {
		const num    = ( cardNumberInput?.value || '' ).replace( /\s/g, '' );
		const expiry = ( cardExpiryInput?.value || '' ).replace( /\s/g, '' );
		const cvc    = ( cardCvcInput?.value || '' ).trim();
		const name   = ( cardNameInput?.value || '' ).trim();

		if ( num.length < 13 || num.length > 16 ) return false;
		if ( ! /^\d{2}\/\d{2}$/.test( expiry ) )  return false;
		if ( cvc.length < 3 )                      return false;
		if ( name.length < 2 )                     return false;
		return true;
	}

	/* ── Show message ── */

	function showMessage( text, type ) {
		if ( ! messageEl ) return;
		messageEl.textContent = text;
		messageEl.className   = 'obsidian-bf-message ' + ( type === 'error' ? 'obf-error' : 'obf-success' );
		messageEl.style.display = '';
	}

	function setLoading( loading ) {
		if ( ! submitBtn ) return;
		const spinnerEl = submitBtn.querySelector( '.obf-submit-spinner' );
		submitBtn.disabled = loading;
		if ( submitTextEl ) submitTextEl.style.display = loading ? 'none' : '';
		if ( spinnerEl )    spinnerEl.style.display    = loading ? 'inline-block' : 'none';
	}

	/* ── PayMongo API helpers ── */

	async function paymongoRequest( endpoint, body ) {
		const res = await fetch( PAYMONGO_API + endpoint, {
			method: 'POST',
			headers: {
				'Content-Type':  'application/json',
				'Authorization': 'Basic ' + btoa( publicKey + ':' ),
			},
			body: JSON.stringify( body ),
		});
		const data = await res.json();
		if ( ! res.ok ) {
			const msg = data?.errors?.[0]?.detail || 'Payment failed. Please try again.';
			throw new Error( msg );
		}
		return data;
	}

	/* ── Create Payment Intent via our server ── */

	async function createPaymentIntent() {
		const res = await fetch( restUrl + 'create-payment-intent', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   nonce,
			},
			body: JSON.stringify({
				booking_id:     bookingId,
				token:          token,
				payment_option: getSelectedOption(),
				payment_method: selectedMethod,
			}),
		});

		const data = await res.json();
		if ( ! res.ok ) {
			throw new Error( data?.message || 'Failed to create payment intent.' );
		}
		return data;
	}

	/* ── Handle redirect after attach ── */

	function handleAttachResult( attachRes ) {
		const status = attachRes.data?.attributes?.status;

		if ( status === 'awaiting_next_action' ) {
			const nextAction = attachRes.data.attributes.next_action;
			if ( nextAction?.type === 'redirect' && nextAction?.redirect?.url ) {
				window.location.href = nextAction.redirect.url;
				return true;
			}
		}

		if ( status === 'succeeded' ) {
			showMessage( 'Payment successful! Redirecting...', 'success' );
			setTimeout( () => {
				window.location.href = confirmationUrl + '?booking_id=' + bookingId;
			}, 1500 );
			return true;
		}

		return false;
	}

	/* ── CARD payment flow ── */

	async function processCardPayment() {
		if ( ! validateCard() ) {
			showMessage( 'Please fill in all card details correctly.', 'error' );
			return;
		}

		setLoading( true );
		messageEl.style.display = 'none';

		try {
			const intentData = await createPaymentIntent();
			const { client_key: clientKey, intent_id: intentId } = intentData;

			const expRaw   = cardExpiryInput.value.replace( /\s/g, '' ).split( '/' );
			const expMonth = parseInt( expRaw[0], 10 );
			const expYear  = parseInt( '20' + expRaw[1], 10 );

			const pmRes = await paymongoRequest( '/payment_methods', {
				data: {
					attributes: {
						type: 'card',
						details: {
							card_number: cardNumberInput.value.replace( /\s/g, '' ),
							exp_month:   expMonth,
							exp_year:    expYear,
							cvc:         cardCvcInput.value.trim(),
						},
						billing: {
							name: cardNameInput.value.trim(),
						},
					},
				},
			});

			const attachRes = await paymongoRequest( '/payment_intents/' + intentId + '/attach', {
				data: {
					attributes: {
						payment_method: pmRes.data.id,
						client_key:     clientKey,
						return_url:     confirmationUrl + '?booking_id=' + bookingId,
					},
				},
			});

			if ( ! handleAttachResult( attachRes ) ) {
				showMessage( 'Payment is being processed. You will receive a confirmation email shortly.', 'success' );
			}

		} catch ( err ) {
			showMessage( err.message || 'An error occurred. Please try again.', 'error' );
			setLoading( false );
		}
	}

	/* ── BANK / E-WALLET payment flow ── */

	async function processBankPayment() {
		setLoading( true );
		messageEl.style.display = 'none';

		try {
			const intentData = await createPaymentIntent();
			const { client_key: clientKey, intent_id: intentId } = intentData;

			const pmRes = await paymongoRequest( '/payment_methods', {
				data: {
					attributes: {
						type: selectedMethod,
					},
				},
			});

			const attachRes = await paymongoRequest( '/payment_intents/' + intentId + '/attach', {
				data: {
					attributes: {
						payment_method: pmRes.data.id,
						client_key:     clientKey,
						return_url:     confirmationUrl + '?booking_id=' + bookingId,
					},
				},
			});

			if ( ! handleAttachResult( attachRes ) ) {
				showMessage( 'Redirecting to ' + ( METHOD_LABELS[ selectedMethod ] || 'payment portal' ) + '...', 'success' );
			}

		} catch ( err ) {
			showMessage( err.message || 'An error occurred. Please try again.', 'error' );
			setLoading( false );
		}
	}

	/* ── Form submit ── */

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		if ( selectedMethod === 'card' ) {
			processCardPayment();
		} else {
			processBankPayment();
		}
	});

})();
