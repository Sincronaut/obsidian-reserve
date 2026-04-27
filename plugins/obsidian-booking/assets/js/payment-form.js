/**
 * Payment Form — PayMongo PIPM Flow (Step 2: Collect Details)
 *
 * Creates a Payment Intent (server-side) and a Payment Method (PayMongo)
 * but does NOT attach them. Stores IDs + masked card info in sessionStorage
 * and redirects to the confirmation page where the user reviews everything
 * before the charge is finalized.
 */
(function () {
	'use strict';

	if ( typeof obsidianPayment === 'undefined' ) return;

	const { restUrl, nonce, publicKey, confirmationUrl, userEmail } = obsidianPayment;
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

	const BANK_NAMES = {
		card:     '',
		dob_ubp:  'BPI - Unibank',
		dob:      'BDO - Unibank',
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
		if ( submitTextEl ) submitTextEl.textContent      = 'Continue to Review';
	}

	function getSelectedOption() {
		return 'full';
	}

	function updateChargeSummary() {
		const rentalCharge = totalVal;
		const chargeTotal  = rentalCharge + depositVal;

		if ( rentalAmountEl )  rentalAmountEl.textContent  = formatCurrency( rentalCharge );
		if ( chargeTotalEl )   chargeTotalEl.textContent    = formatCurrency( chargeTotal );

		if ( balanceLineEl ) {
			balanceLineEl.style.display = 'none';
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

	/* ── Mask helpers ── */

	function maskCardNumber( num ) {
		const clean = num.replace( /\s/g, '' );
		return '****  ****' + clean.slice( -4 );
	}

	function maskCvc( cvc ) {
		return '*'.repeat( cvc.length );
	}

	/* ── CARD flow: create PI + PM, store, redirect ── */

	async function processCardPayment() {
		if ( ! validateCard() ) {
			showMessage( 'Please fill in all card details correctly.', 'error' );
			return;
		}

		setLoading( true );
		messageEl.style.display = 'none';

		try {
			const intentData = await createPaymentIntent();
			const { client_key: clientKey, intent_id: intentId, rental_charge, deposit, rental_total } = intentData;

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
							name:  cardNameInput.value.trim(),
							email: userEmail,
						},
					},
				},
			});

			sessionStorage.setItem( 'obPayment', JSON.stringify({
				bookingId:      bookingId,
				token:          token,
				intentId:       intentId,
				clientKey:      clientKey,
				pmId:           pmRes.data.id,
				method:         'card',
				methodLabel:    METHOD_LABELS.card,
				bankName:       '',
				cardName:       cardNameInput.value.trim(),
				cardNumber:     maskCardNumber( cardNumberInput.value ),
				cardExpiry:     cardExpiryInput.value,
				cardCvc:        maskCvc( cardCvcInput.value.trim() ),
				paymentOption:  getSelectedOption(),
				rentalCharge:   rental_charge,
				deposit:        deposit,
				rentalTotal:    rental_total,
			}));

			window.location.href = confirmationUrl + '?booking_id=' + bookingId;

		} catch ( err ) {
			showMessage( err.message || 'An error occurred. Please try again.', 'error' );
			setLoading( false );
		}
	}

	/* ── BANK / E-WALLET flow: create PI + PM, store, redirect ── */

	async function processBankPayment() {
		setLoading( true );
		messageEl.style.display = 'none';

		try {
			const intentData = await createPaymentIntent();
			const { client_key: clientKey, intent_id: intentId, rental_charge, deposit, rental_total } = intentData;

			const pmRes = await paymongoRequest( '/payment_methods', {
				data: {
					attributes: {
						type: selectedMethod,
						billing: {
							name:  'Customer',
							email: userEmail,
						},
					},
				},
			});

			sessionStorage.setItem( 'obPayment', JSON.stringify({
				bookingId:      bookingId,
				token:          token,
				intentId:       intentId,
				clientKey:      clientKey,
				pmId:           pmRes.data.id,
				method:         selectedMethod,
				methodLabel:    METHOD_LABELS[ selectedMethod ] || selectedMethod,
				bankName:       BANK_NAMES[ selectedMethod ] || '',
				cardName:       '',
				cardNumber:     '',
				cardExpiry:     '',
				cardCvc:        '',
				paymentOption:  getSelectedOption(),
				rentalCharge:   rental_charge,
				deposit:        deposit,
				rentalTotal:    rental_total,
			}));

			window.location.href = confirmationUrl + '?booking_id=' + bookingId;

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
