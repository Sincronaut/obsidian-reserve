/**
 * Confirmation Page — Final step before payment is charged.
 *
 * Reads payment data from sessionStorage (set by payment-form.js),
 * populates the payment info section, and on "Confirm Reservation"
 * attaches the Payment Method to the Payment Intent to process the charge.
 */
(function () {
	'use strict';

	if ( typeof obsidianPayment === 'undefined' ) return;

	const { publicKey, confirmationUrl } = obsidianPayment;
	const PAYMONGO_API = 'https://api.paymongo.com/v1';

	const wrap      = document.getElementById( 'obsidian-confirmation-wrap' );
	const confirmBtn = document.getElementById( 'obc-confirm-btn' );

	if ( ! wrap || ! confirmBtn ) return;

	const paymentInfoEl = document.getElementById( 'obc-payment-info' );
	const messageEl     = document.getElementById( 'obc-message' );
	const submitTextEl  = confirmBtn.querySelector( '.obf-submit-text' );

	/* ── Read session data ── */

	const raw = sessionStorage.getItem( 'obPayment' );
	if ( ! raw ) {
		if ( paymentInfoEl ) {
			paymentInfoEl.innerHTML = '<p style="color:#cc4444;">Payment data not found. Please go back to the payment page.</p>';
		}
		confirmBtn.disabled = true;
		return;
	}

	const pd = JSON.parse( raw );

	/* ── Populate payment info ── */

	if ( paymentInfoEl ) {
		let html = '';

		if ( pd.method === 'card' ) {
			html += row( 'Bank :', pd.bankName || 'Credit / Debit Card' );
			html += row( 'Cardholder Name:', pd.cardName );
			html += row( 'Card Number:', pd.cardNumber );
			html += row( 'Expiration Date:', pd.cardExpiry );
			html += row( 'CVV/CVC:', pd.cardCvc );
		} else {
			html += row( 'Payment Method:', pd.methodLabel );
			if ( pd.bankName ) {
				html += row( 'Bank :', pd.bankName );
			}
		}

		paymentInfoEl.innerHTML = html;
	}

	function row( label, value ) {
		return '<div class="obc-info-row"><span class="obc-info-label">' + escHtml( label ) + '</span><span class="obc-info-value">' + escHtml( value ) + '</span></div>';
	}

	function escHtml( str ) {
		const div = document.createElement( 'div' );
		div.textContent = str || '';
		return div.innerHTML;
	}

	/* ── Update total display to show charge amount ── */

	const totalDisplayEl = document.getElementById( 'obc-total-display' );
	if ( totalDisplayEl && pd.rentalCharge !== undefined ) {
		const chargeTotal = pd.rentalCharge + pd.deposit;
		totalDisplayEl.textContent = '₱' + Math.round( chargeTotal ).toLocaleString( 'en-PH' ) + '.00';
	}

	/* ── PayMongo helpers ── */

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

	/* ── UI helpers ── */

	function showMessage( text, type ) {
		if ( ! messageEl ) return;
		messageEl.textContent   = text;
		messageEl.className     = 'obsidian-bf-message ' + ( type === 'error' ? 'obf-error' : 'obf-success' );
		messageEl.style.display = '';
	}

	function setLoading( loading ) {
		const spinnerEl = confirmBtn.querySelector( '.obf-submit-spinner' );
		confirmBtn.disabled = loading;
		if ( submitTextEl ) submitTextEl.style.display = loading ? 'none' : '';
		if ( spinnerEl )    spinnerEl.style.display    = loading ? 'inline-block' : 'none';
	}

	/* ── Show success state ── */

	function showSuccess() {
		const titleEl    = document.getElementById( 'obc-title' );
		const subtitleEl = document.getElementById( 'obc-subtitle' );

		if ( titleEl )    titleEl.innerHTML    = '<span class="text-gold">Confirmed!</span>';
		if ( subtitleEl ) subtitleEl.textContent = 'Your reservation has been confirmed. We\'ll see you soon.';

		confirmBtn.style.display = 'none';
		if ( messageEl ) messageEl.style.display = 'none';

		const actionsEl = confirmBtn.parentElement;
		if ( actionsEl ) {
			const link = document.createElement( 'a' );
			link.href      = '/fleet/';
			link.className = 'obsidian-bf-submit';
			link.textContent = 'Back to Fleet';
			actionsEl.appendChild( link );
		}

		sessionStorage.removeItem( 'obPayment' );
	}

	/* ── Handle attach result ── */

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
			showMessage( 'Payment successful!', 'success' );
			sessionStorage.removeItem( 'obPayment' );
			setTimeout( showSuccess, 800 );
			return true;
		}

		return false;
	}

	/* ── Confirm Reservation click ── */

	confirmBtn.addEventListener( 'click', async function () {
		setLoading( true );
		if ( messageEl ) messageEl.style.display = 'none';

		try {
			const returnUrl = confirmationUrl + '?booking_id=' + pd.bookingId;

			const attachRes = await paymongoRequest( '/payment_intents/' + pd.intentId + '/attach', {
				data: {
					attributes: {
						payment_method: pd.pmId,
						client_key:     pd.clientKey,
						return_url:     returnUrl,
					},
				},
			});

			if ( ! handleAttachResult( attachRes ) ) {
				showMessage( 'Payment is being processed. You will receive a confirmation email shortly.', 'success' );
				sessionStorage.removeItem( 'obPayment' );
			}

		} catch ( err ) {
			showMessage( err.message || 'An error occurred. Please try again.', 'error' );
			setLoading( false );
		}
	});

})();
