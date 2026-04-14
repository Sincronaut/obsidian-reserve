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
		var returnDateInput   = document.getElementById('obf-return-date');

		if (deliveryDateInput) {
			flatpickr(deliveryDateInput, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'M j, Y',
				minDate: 'today',
				onChange: function () { validateDelivery(); }
			});
		}

		if (returnDateInput) {
			flatpickr(returnDateInput, {
				dateFormat: 'Y-m-d',
				altInput: true,
				altFormat: 'M j, Y',
				minDate: 'today',
				onChange: function () { validateDelivery(); }
			});
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
		var valid = true;

		var requiredTexts = ['obf-first-name', 'obf-last-name', 'obf-address', 'obf-birth-date', 'obf-license-number'];
		if (customerType === 'local') requiredTexts.push('obf-phone');
		if (customerType === 'international') requiredTexts.push('obf-passport-number');

		requiredTexts.forEach(function (id) {
			var el = document.getElementById(id);
			if (el && !el.value.trim()) valid = false;
		});

		if (customerType === 'local') {
			['obf-gov-id-type', 'obf-gov-id-type-2'].forEach(function (id) {
				var el = document.getElementById(id);
				if (el && !el.value) valid = false;
			});
		}

		var requiredDocs = ['license'];
		if (customerType === 'local') requiredDocs.push('gov_id_front', 'gov_id_back');
		if (customerType === 'international') requiredDocs.push('passport', 'proof_of_arrival');

		requiredDocs.forEach(function (key) {
			if (!uploadedDocs[key]) valid = false;
		});

		if (nextBtn) nextBtn.disabled = !valid;
		return valid;
	}

	/* ── Delivery Validation ── */

	function initDeliveryValidation() {
		var inputs = deliveryStep.querySelectorAll('input, select, textarea');
		inputs.forEach(function (input) {
			input.addEventListener('change', validateDelivery);
			input.addEventListener('input', validateDelivery);
		});
	}

	function validateDelivery() {
		var valid = true;

		var requiredFields = [
			'obf-delivery-contact',
			'obf-delivery-dropoff',
			'obf-delivery-date',
			'obf-delivery-time',
			'obf-return-address',
			'obf-return-date',
			'obf-return-time'
		];

		requiredFields.forEach(function (id) {
			var el = document.getElementById(id);
			if (el && !el.value.trim()) valid = false;
		});

		// Checkboxes
		var checkboxes = deliveryStep.querySelectorAll('input[type="checkbox"][required]');
		checkboxes.forEach(function (cb) {
			if (!cb.checked) valid = false;
		});

		if (submitBtn) submitBtn.disabled = !valid;
		return valid;
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

		var payload = {
			car_id:         parseInt((document.getElementById('obf-car-id') || {}).value || '0', 10),
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
			location:       'main_office',
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
