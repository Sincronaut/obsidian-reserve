/**
 * Obsidian Booking — Booking Form JS
 * Handles file uploads, validation, birth-date picker, and form submission.
 */
(function () {
	'use strict';

	var cfg = window.obsidianBooking || {};
	var form, submitBtn, submitText, submitSpinner, messageEl;
	var uploadedDocs = {};

	function init() {
		form = document.getElementById('obsidian-booking-form');
		if (!form) return;

		submitBtn     = document.getElementById('obf-submit');
		submitText    = form.querySelector('.obf-submit-text');
		submitSpinner = form.querySelector('.obf-submit-spinner');
		messageEl     = document.getElementById('obf-message');

		initDocsToggle();
		initBirthDatePicker();
		initFileUploads();
		initValidation();

		form.addEventListener('submit', handleSubmit);
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
				validateForm();
			}
		});
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

					validateForm();
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

					var defaultLabel = placeholder.getAttribute('data-default') || placeholder.querySelector('span').textContent;
					placeholder.querySelector('span').textContent = defaultLabel;

					validateForm();
				});
			}
		});
	}

	function uploadFile(file, docKey) {
		var formData = new FormData();
		formData.append('document', file);

		return fetch(cfg.restUrl + 'upload-document', {
			method: 'POST',
			headers: {
				'X-WP-Nonce': cfg.nonce
			},
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

	/* ── Validation ── */

	function initValidation() {
		var inputs = form.querySelectorAll('input, select');
		inputs.forEach(function (input) {
			input.addEventListener('change', validateForm);
			input.addEventListener('input', validateForm);
		});
		validateForm();
	}

	function validateForm() {
		var customerType = (document.getElementById('obf-customer-type') || {}).value || 'local';
		var valid = true;

		// Required text fields
		var requiredTexts = ['obf-first-name', 'obf-last-name', 'obf-address', 'obf-birth-date', 'obf-license-number'];
		if (customerType === 'local') {
			requiredTexts.push('obf-phone');
		}
		if (customerType === 'international') {
			requiredTexts.push('obf-passport-number');
		}

		requiredTexts.forEach(function (id) {
			var el = document.getElementById(id);
			if (el && !el.value.trim()) {
				valid = false;
			}
		});

		// Required selects
		var requiredSelects = ['obf-location'];
		if (customerType === 'local') {
			requiredSelects.push('obf-gov-id-type', 'obf-gov-id-type-2');
		}

		requiredSelects.forEach(function (id) {
			var el = document.getElementById(id);
			if (el && !el.value) {
				valid = false;
			}
		});

		// Required uploads
		var requiredDocs = ['license'];
		if (customerType === 'local') {
			requiredDocs.push('gov_id_front', 'gov_id_back');
		}
		if (customerType === 'international') {
			requiredDocs.push('passport', 'proof_of_arrival');
		}

		requiredDocs.forEach(function (key) {
			if (!uploadedDocs[key]) {
				valid = false;
			}
		});

		// Checkboxes
		var checkboxes = form.querySelectorAll('input[type="checkbox"][required]');
		checkboxes.forEach(function (cb) {
			if (!cb.checked) valid = false;
		});

		submitBtn.disabled = !valid;
		return valid;
	}

	/* ── Form Submission ── */

	function handleSubmit(e) {
		e.preventDefault();
		if (submitBtn.disabled) return;

		if (!validateForm()) return;

		var customerType = (document.getElementById('obf-customer-type') || {}).value || 'local';

		setLoading(true);
		hideMessage();

		var payload = {
			car_id:         parseInt((document.getElementById('obf-car-id') || {}).value || '0', 10),
			start_date:     (document.getElementById('obf-start-date') || {}).value || '',
			end_date:       (document.getElementById('obf-end-date') || {}).value || '',
			color:          (document.getElementById('obf-color') || {}).value || '',
			customer_type:  customerType,
			first_name:     val('obf-first-name'),
			last_name:      val('obf-last-name'),
			address:        val('obf-address'),
			birth_date:     val('obf-birth-date'),
			license_number: val('obf-license-number'),
			location:       val('obf-location'),
			documents:      uploadedDocs
		};

		if (customerType === 'local') {
			payload.phone        = val('obf-phone');
			payload.gov_id_type  = val('obf-gov-id-type');
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
			form.querySelector('.obsidian-bf-form') && (submitBtn.disabled = true);

			submitText.textContent = 'Submitted';
			submitBtn.disabled = true;

			form.querySelectorAll('input, select, button').forEach(function (el) {
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
		submitText.style.display  = loading ? 'none' : '';
		submitSpinner.style.display = loading ? '' : 'none';
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
