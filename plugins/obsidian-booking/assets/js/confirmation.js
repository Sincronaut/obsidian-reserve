/**
 * Confirmation Page — Final step before payment is charged.
 *
 * Reads payment data from sessionStorage (set by payment-form.js),
 * populates the payment info section, and on "Confirm Reservation"
 * attaches the Payment Method to the Payment Intent to process the charge.
 *
 * Also handles the 3D Secure / bank redirect return flow:
 * When the user returns from a bank authentication page, sessionStorage
 * is empty (cleared during the cross-domain redirect). In that case,
 * we check the PayMongo payment_intent_id URL parameter, verify the
 * intent status, confirm server-side, and reload to show "RESERVED".
 */
(function () {
	'use strict';

	if ( typeof obsidianPayment === 'undefined' ) return;

	const { publicKey, confirmationUrl, restUrl, nonce } = obsidianPayment;
	const PAYMONGO_API = 'https://api.paymongo.com/v1';

	const wrap       = document.getElementById( 'obsidian-confirmation-wrap' );
	const confirmBtn = document.getElementById( 'obc-confirm-btn' );

	if ( ! wrap || ! confirmBtn ) return;

	const paymentInfoEl = document.getElementById( 'obc-payment-info' );
	const messageEl     = document.getElementById( 'obc-message' );
	const submitTextEl  = confirmBtn.querySelector( '.obf-submit-text' );

	/* ── Read session data ── */

	const raw = sessionStorage.getItem( 'obPayment' );

	if ( ! raw ) {
		// After a 3D Secure or bank redirect, sessionStorage is cleared
		// because the browser left this origin. Check the payment intent
		// status via the URL query parameter that PayMongo appends on return.
		const urlParams = new URLSearchParams( window.location.search );
		const intentId  = urlParams.get( 'payment_intent_id' );

		if ( intentId ) {
			// User just returned from 3D Secure / bank authentication.
			// Verify the intent status and confirm server-side.
			(async function () {
				try {
					const verifyRes = await fetch( PAYMONGO_API + '/payment_intents/' + intentId, {
						headers: { 'Authorization': 'Basic ' + btoa( publicKey + ':' ) },
					});
					const verifyData = await verifyRes.json();
					const status     = verifyData?.data?.attributes?.status;

					if ( status === 'succeeded' ) {
						// Extract booking ID from the intent metadata.
						const bookingId = verifyData?.data?.attributes?.metadata?.booking_id;

						if ( bookingId ) {
							await fetch( restUrl + 'confirm-payment', {
								method: 'POST',
								headers: {
									'Content-Type': 'application/json',
									'X-WP-Nonce':   nonce,
								},
								body: JSON.stringify({
									booking_id: parseInt( bookingId, 10 ),
									intent_id:  intentId,
								}),
							});
						}

						// Reload without query params to show the "RESERVED" success page.
						window.location.replace( window.location.pathname );
						return;
					}

					if ( paymentInfoEl ) {
						paymentInfoEl.innerHTML = '<p style="color:#cc4444;">Payment was not completed. Please try again from the payment page.</p>';
					}
					confirmBtn.disabled = true;
				} catch ( e ) {
					if ( paymentInfoEl ) {
						paymentInfoEl.innerHTML = '<p style="color:#cc4444;">Could not verify payment status. Please check your email for confirmation or contact support.</p>';
					}
					confirmBtn.disabled = true;
				}
			})();
			return;
		}

		// No session data and no intent ID — genuinely missing.
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

	/* ── Show "Reserved" success page ── */

	function showSuccess() {
		sessionStorage.removeItem( 'obPayment' );
		window.location.reload();
	}

	/* ── Confirm payment server-side ── */

	async function confirmPaymentOnServer() {
		try {
			await fetch( restUrl + 'confirm-payment', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
				body: JSON.stringify({
					booking_id: pd.bookingId,
					intent_id:  pd.intentId,
				}),
			});
		} catch (e) {
			// Non-blocking — webhook will catch it if this fails.
		}
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
			confirmPaymentOnServer();
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
			const returnUrl = confirmationUrl + ( pd.paymentSessionId || '' ) + '/';

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
