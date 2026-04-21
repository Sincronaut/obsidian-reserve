/**
 * Obsidian Booking — Booking Form JS
 *
 * Two sub-steps within Step 1:
 *   1a. Renter form (local / international) → click "Next"
 *   1b. Delivery form → click "Submit for Review" → creates booking
 *
 * Handles file uploads, validation, date/time pickers, sub-step
 * navigation, and form submission.
 */
(function () {
	'use strict';

	var cfg = window.obsidianBooking || {};
	var form, nextBtn, backBtn, submitBtn, submitText, submitSpinner, messageEl;
	var renterStep, deliveryStep, titleEl, subtitleEl;
	var uploadedDocs = {};
	var currentStep = 'renter';

	function init() {
		form = document.getElementById('obsidian-booking-form');
		if (!form) return;

		renterStep   = document.getElementById('obf-step-renter');
		deliveryStep = document.getElementById('obf-step-delivery');
		titleEl      = document.getElementById('obf-title');
		subtitleEl   = document.getElementById('obf-subtitle');

		nextBtn       = document.getElementById('obf-next');
		backBtn       = document.getElementById('obf-back');
		submitBtn     = document.getElementById('obf-submit');
		submitText    = submitBtn ? submitBtn.querySelector('.obf-submit-text') : null;
		submitSpinner = submitBtn ? submitBtn.querySelector('.obf-submit-spinner') : null;
		messageEl     = document.getElementById('obf-message');

		initDocsToggle();
		initBirthDatePicker();
		initFileUploads();
		initGovIdMirror();
		initRenterValidation();

		if (nextBtn) nextBtn.addEventListener('click', goToDelivery);
		if (backBtn) backBtn.addEventListener('click', goToRenter);

		form.addEventListener('submit', handleSubmit);
	}

	/* ── Sub-step Navigation ── */

	function goToDelivery() {
		if (!validateRenter()) return;

		renterStep.style.display   = 'none';
		deliveryStep.style.display = '';
		currentStep = 'delivery';

		titleEl.innerHTML    = '<span class="text-gold">Delivery</span> Form';
		subtitleEl.textContent = 'Land and drive. Fill in the details below.';

		// Pre-fill contact number from renter phone if available
		var phoneInput   = document.getElementById('obf-phone');
		var contactInput = document.getElementById('obf-delivery-contact');
		if (phoneInput && contactInput && phoneInput.value && !contactInput.value) {
			contactInput.value = phoneInput.value;
		}

		initDeliveryDatePickers();
		initDeliveryValidation();
		validateDelivery();

		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	function goToRenter() {
		deliveryStep.style.display = 'none';
		renterStep.style.display   = '';
		currentStep = 'renter';

		var customerType = (document.getElementById('obf-customer-type') || {}).value || 'local';
		if (customerType === 'international') {
			titleEl.innerHTML    = '<span class="text-gold">International</span> Renters Form';
			subtitleEl.textContent = 'Land and drive. Fill in the details below.';
		} else {
			titleEl.innerHTML    = '<span class="text-gold">Local</span> Renters Form';
			subtitleEl.textContent = 'Your exact vehicle starts with this form.';
		}

		hideMessage();
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	/* ── Documents Requirements Toggle ── */

	function initDocsToggle() {
		var toggle = document.getElementById('obf-docs-toggle');
		var info   = document.getElementById('obf-docs-info');
		if (!toggle || !info) return;

		toggle.addEventListener('click', function () {
			var visible = info.style.display !== 'none';
			info.style.display = visible ? 'none' : '';
		});
	}

	/* ── Birth Date Picker (Flatpickr) ── */

	function initBirthDatePicker() {
		var birthInput = document.getElementById('obf-birth-date');
		if (!birthInput || typeof flatpickr === 'undefined') return;

		var twentyOneYearsAgo = new Date();
		twentyOneYearsAgo.setFullYear(twentyOneYearsAgo.getFullYear() - 21);

		flatpickr(birthInput, {
			dateFormat: 'Y-m-d',
			altInput: true,
			altFormat: 'F j, Y',
			maxDate: twentyOneYearsAgo,
			defaultDate: null,
			onChange: function () {
				validateRenter();
			}
		});
	}

	/* ── Delivery Date Pickers ── */

	var deliveryPickersInitialized = false;

	function initDeliveryDatePickers() {
		if (deliveryPickersInitialized || typeof flatpickr === 'undefined') return;
		deliveryPickersInitialized = true;

		var deliveryDateInput = document.getElementById('obf-delivery-date');

		if (deliveryDateInput) {
			flatpickr(deliveryDateInput, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'M j, Y',
				minDate: 'today',
				onChange: function () { validateDelivery(); }
			});
		}

		// Return date is no longer a user input — it's auto-set to the
		// booking end date (rendered server-side into the hidden input).
		// We just mirror the user's delivery time into the hidden return
		// time and update the read-only display so they can see what
		// we'll be using.
		var deliveryTimeInput = document.getElementById('obf-delivery-time');
		var returnTimeHidden  = document.getElementById('obf-return-time');
		var returnTimeDisplay = document.getElementById('obf-return-time-display');

		if (deliveryTimeInput && returnTimeHidden) {
			var syncReturnTime = function () {
				var t = (deliveryTimeInput.value || '').trim();
				returnTimeHidden.value = t;
				if (returnTimeDisplay) {
					returnTimeDisplay.textContent = t || '—';
				}
			};
			deliveryTimeInput.addEventListener('input', syncReturnTime);
			deliveryTimeInput.addEventListener('change', syncReturnTime);
		}
	}

	/* ── File Uploads ── */

	function initFileUploads() {
		var zones = form.querySelectorAll('.obsidian-bf-upload-zone');

		zones.forEach(function (zone) {
			var fileInput   = zone.querySelector('.obf-file-input');
			var placeholder = zone.querySelector('.obf-upload-placeholder');
			var preview     = zone.querySelector('.obf-upload-preview');
			var previewImg  = preview ? preview.querySelector('img') : null;
			var removeBtn   = zone.querySelector('.obf-upload-remove');
			var docKey      = zone.getAttribute('data-doc-key');
			// Remember the original placeholder caption so that:
			//   - on Remove we restore it (was previously stuck on the file name)
			//   - the gov-id mirror logic can rewrite this baseline as the
			//     user changes their ID type.
			var labelSpan   = placeholder ? placeholder.querySelector('span') : null;
			if (labelSpan && !zone.dataset.defaultLabel) {
				zone.dataset.defaultLabel = labelSpan.textContent;
			}

			fileInput.addEventListener('change', function () {
				var file = fileInput.files[0];
				if (!file) return;

				var maxSize = 5 * 1024 * 1024;
				if (file.size > maxSize) {
					showMessage('File must be under 5MB.', 'error');
					fileInput.value = '';
					return;
				}

				var allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
				if (allowed.indexOf(file.type) === -1) {
					showMessage('Only JPG, PNG, WebP, and PDF files are allowed.', 'error');
					fileInput.value = '';
					return;
				}

				zone.classList.add('obf-uploading');

				uploadFile(file, docKey).then(function (result) {
					uploadedDocs[docKey] = result.attachment_id;
					zone.classList.remove('obf-uploading');

					if (file.type.startsWith('image/') && previewImg) {
						var reader = new FileReader();
						reader.onload = function (e) {
							previewImg.src = e.target.result;
							placeholder.style.display = 'none';
							preview.style.display = '';
						};
						reader.readAsDataURL(file);
					} else {
						placeholder.querySelector('span').textContent = file.name;
						placeholder.style.display = '';
						preview.style.display = 'none';
					}

					validateRenter();
				}).catch(function (err) {
					zone.classList.remove('obf-uploading');
					showMessage('Upload failed: ' + err.message, 'error');
					fileInput.value = '';
				});
			});

			if (removeBtn) {
				removeBtn.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopPropagation();
					delete uploadedDocs[docKey];
					fileInput.value = '';
					preview.style.display = 'none';
					placeholder.style.display = '';
					// Restore the contextual caption (e.g. "Upload SSS ID")
					// rather than leaving the previous file name behind.
					if (labelSpan && zone.dataset.defaultLabel) {
						labelSpan.textContent = zone.dataset.defaultLabel;
					}
					validateRenter();
				});
			}
		});
	}

	function uploadFile(file, docKey) {
		var formData = new FormData();
		formData.append('document', file);

		return fetch(cfg.restUrl + 'upload-document', {
			method: 'POST',
			headers: { 'X-WP-Nonce': cfg.nonce },
			body: formData
		}).then(function (r) {
			if (!r.ok) {
				return r.text().then(function (body) {
					throw new Error(body.substring(0, 200));
				});
			}
			return r.json();
		});
	}

	/* ── Gov ID type → upload-zone label mirror ──
	   Each gov-ID upload zone has a `data-mirror-from="<select-id>"`
	   attribute pointing at its <select>. When the user picks a value
	   in that select (e.g. "SSS ID"), the corresponding upload zone's
	   placeholder caption becomes "Upload SSS ID". The contextual label
	   is also stored in `zone.dataset.defaultLabel` so the file-Remove
	   handler restores the right caption. */
	function initGovIdMirror() {
		var zones = form.querySelectorAll('.obsidian-bf-upload-zone[data-mirror-from]');

		zones.forEach(function (zone) {
			var sourceId  = zone.getAttribute('data-mirror-from');
			var sourceSel = sourceId ? document.getElementById(sourceId) : null;
			if (!sourceSel) return;

			var labelSpan = zone.querySelector('.obf-upload-label')
				|| (zone.querySelector('.obf-upload-placeholder') || zone).querySelector('span');
			if (!labelSpan) return;

			var sync = function () {
				var optText = '';
				if (sourceSel.selectedIndex >= 0) {
					var opt = sourceSel.options[sourceSel.selectedIndex];
					// Skip the empty "Select Government ID" placeholder option.
					if (opt && opt.value) optText = opt.text || '';
				}
				var newLabel = optText ? ('Upload ' + optText) : 'Upload ID';

				// If a file is already chosen, don't stomp the file name —
				// just rewrite the default so a future Remove restores it
				// with the new ID type.
				zone.dataset.defaultLabel = newLabel;

				var hasFile = (zone.querySelector('.obf-upload-preview') || {}).style;
				var fileShown = hasFile && hasFile.display !== 'none';
				if (!fileShown) {
					labelSpan.textContent = newLabel;
				}
			};

			sourceSel.addEventListener('change', sync);
			sync(); // run once so initial state is correct
		});
	}

	/* ── Renter Validation ── */

	function initRenterValidation() {
		var inputs = renterStep.querySelectorAll('input, select');
		inputs.forEach(function (input) {
			input.addEventListener('change', validateRenter);
			input.addEventListener('input', validateRenter);
		});
		validateRenter();
	}

	function validateRenter() {
		var customerType = (document.getElementById('obf-customer-type') || {}).value || 'local';

		// Build an ordered checklist of every required item in the order
		// they appear on the page. The first item that fails becomes the
		// status hint text, so the user always knows the *next* thing to
		// fix instead of being told nothing.
		var checks = [
			{ id: 'obf-first-name',     label: 'First Name' },
			{ id: 'obf-last-name',      label: 'Last Name' },
			{ id: 'obf-address',        label: 'Address' },
			{ id: 'obf-birth-date',     label: 'Birth Date' }
		];

		if (customerType === 'local') {
			checks.push({ id: 'obf-phone', label: 'Mobile Number' });
		}

		checks.push(
			{ id: 'obf-license-number', label: "Driver's License Number" },
			{ doc: 'license',           label: "Driver's License upload" }
		);

		if (customerType === 'international') {
			checks.push(
				{ id: 'obf-passport-number', label: 'Passport Number' },
				{ doc: 'passport',           label: 'Passport upload' },
				{ doc: 'proof_of_arrival',   label: 'Proof of Arrival upload' }
			);
		}

		if (customerType === 'local') {
			checks.push(
				{ id: 'obf-gov-id-type',   label: 'Government ID #1 type' },
				{ id: 'obf-gov-id-type-2', label: 'Government ID #2 type' },
				{ doc: 'gov_id_1',         label: 'Government ID #1 photo' },
				{ doc: 'gov_id_2',         label: 'Government ID #2 photo' }
			);
		}

		var firstMissing = null;
		for (var i = 0; i < checks.length; i++) {
			var c = checks[i];
			if (c.doc) {
				if (!uploadedDocs[c.doc]) { firstMissing = c.label; break; }
			} else {
				var el = document.getElementById(c.id);
				if (!el) continue;
				var v = (el.value || '').trim();
				if (!v) { firstMissing = c.label; break; }
			}
		}

		var valid = (firstMissing === null);
		if (nextBtn) nextBtn.disabled = !valid;
		updateRenterStatus(firstMissing);
		return valid;
	}

	function updateRenterStatus(missing) {
		var box  = document.getElementById('obf-renter-status');
		var text = document.getElementById('obf-renter-status-text');
		if (!box || !text) return;

		box.hidden = false;
		box.classList.remove('is-info', 'is-warn', 'is-success');

		if (missing) {
			box.classList.add('is-warn');
			text.textContent = 'Missing: ' + missing;
		} else {
			box.classList.add('is-success');
			text.textContent = 'Looks good — click Next to continue.';
		}
	}

	/* ── Delivery Validation ── */

	var deliveryValidationInitialized = false;
	function initDeliveryValidation() {
		if (deliveryValidationInitialized) {
			validateDelivery();
			return;
		}
		deliveryValidationInitialized = true;

		var inputs = deliveryStep.querySelectorAll('input, select, textarea');
		inputs.forEach(function (input) {
			input.addEventListener('change', validateDelivery);
			input.addEventListener('input', validateDelivery);
		});
		// Run once on entry so the status hint shows the first missing
		// field immediately instead of staying hidden until the user
		// touches something.
		validateDelivery();
	}

	function validateDelivery() {
		// Same approach as validateRenter — ordered checklist that surfaces
		// the *first* missing item to the status hint.
		// Return date/time are auto-set from the end date + delivery time,
		// so they're intentionally not in this list.
		var checks = [
			{ id: 'obf-pickup-location',  label: 'Pickup Location' },
			{ id: 'obf-delivery-contact', label: 'Contact Number' },
			{ id: 'obf-delivery-dropoff', label: 'Delivery Type' },
			{ id: 'obf-delivery-date',    label: 'Delivery Date' },
			{ id: 'obf-delivery-time',    label: 'Delivery Time' },
			{ id: 'obf-return-address',   label: 'Return Pickup Address' }
		];

		var firstMissing = null;
		for (var i = 0; i < checks.length; i++) {
			var c  = checks[i];
			var el = document.getElementById(c.id);
			if (!el) continue;
			if (!(el.value || '').trim()) { firstMissing = c.label; break; }
		}

		// Agreements come last in the form so we check them after fields.
		if (!firstMissing) {
			var checkboxes = deliveryStep.querySelectorAll('input[type="checkbox"][required]');
			for (var j = 0; j < checkboxes.length; j++) {
				if (!checkboxes[j].checked) {
					firstMissing = (j === 0) ? 'Terms and Conditions agreement' : 'Privacy Policy agreement';
					break;
				}
			}
		}

		var valid = (firstMissing === null);
		if (submitBtn) submitBtn.disabled = !valid;
		updateDeliveryStatus(firstMissing);
		return valid;
	}

	function updateDeliveryStatus(missing) {
		var box  = document.getElementById('obf-delivery-status');
		var text = document.getElementById('obf-delivery-status-text');
		if (!box || !text) return;

		box.hidden = false;
		box.classList.remove('is-info', 'is-warn', 'is-success');

		if (missing) {
			box.classList.add('is-warn');
			text.textContent = 'Missing: ' + missing;
		} else {
			box.classList.add('is-success');
			text.textContent = 'All set — click Submit for Review to send your booking.';
		}
	}

	/* ── Form Submission (from delivery step) ── */

	function handleSubmit(e) {
		e.preventDefault();
		if (currentStep !== 'delivery') return;
		if (submitBtn.disabled) return;
		if (!validateDelivery()) return;

		var customerType = (document.getElementById('obf-customer-type') || {}).value || 'local';

		setLoading(true);
		hideMessage();

		// Phase 11.14: Pull branch ID from the dropdown OR the locked
		// hidden input. The PHP renders one or the other under the same
		// `id="obf-pickup-location"` so this single lookup handles both.
		var locationId = parseInt(val('obf-pickup-location') || '0', 10);

		var payload = {
			car_id:         parseInt((document.getElementById('obf-car-id') || {}).value || '0', 10),
			location_id:    locationId,
			start_date:     (document.getElementById('obf-start-date') || {}).value || '',
			end_date:       (document.getElementById('obf-end-date') || {}).value || '',
			color:          (document.getElementById('obf-color') || {}).value || '',
			customer_type:  customerType,

			// Renter fields
			first_name:     val('obf-first-name'),
			last_name:      val('obf-last-name'),
			address:        val('obf-address'),
			birth_date:     val('obf-birth-date'),
			license_number: val('obf-license-number'),
			documents:      uploadedDocs,

			// Delivery fields
			delivery_contact:  val('obf-delivery-contact'),
			delivery_dropoff:  val('obf-delivery-dropoff'),
			delivery_date:     val('obf-delivery-date'),
			delivery_time:     val('obf-delivery-time'),
			return_address:    val('obf-return-address'),
			return_date:       val('obf-return-date'),
			return_time:       val('obf-return-time'),
			special_requests:  val('obf-special-requests')
		};

		if (customerType === 'local') {
			payload.phone         = val('obf-phone');
			payload.gov_id_type   = val('obf-gov-id-type');
			payload.gov_id_type_2 = val('obf-gov-id-type-2');
		}

		if (customerType === 'international') {
			payload.passport_number = val('obf-passport-number');
		}

		fetch(cfg.restUrl + 'bookings', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify(payload)
		})
		.then(function (r) {
			if (!r.ok) {
				return r.json().then(function (err) {
					throw new Error(err.message || 'Booking failed.');
				});
			}
			return r.json();
		})
		.then(function (result) {
			setLoading(false);
			showMessage(result.message || 'Your documents have been submitted for review!', 'success');

			submitText.textContent = 'Submitted';
			submitBtn.disabled = true;

			form.querySelectorAll('input, select, textarea, button').forEach(function (el) {
				el.disabled = true;
			});
		})
		.catch(function (err) {
			setLoading(false);
			showMessage(err.message, 'error');
		});
	}

	/* ── Helpers ── */

	function val(id) {
		var el = document.getElementById(id);
		return el ? el.value.trim() : '';
	}

	function setLoading(loading) {
		submitBtn.disabled = loading;
		if (submitText)    submitText.style.display    = loading ? 'none' : '';
		if (submitSpinner) submitSpinner.style.display = loading ? '' : 'none';
	}

	function showMessage(text, type) {
		messageEl.textContent = text;
		messageEl.className = 'obsidian-bf-message ' + (type === 'error' ? 'obf-error' : 'obf-success');
		messageEl.style.display = '';
	}

	function hideMessage() {
		messageEl.style.display = 'none';
		messageEl.className = 'obsidian-bf-message';
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
