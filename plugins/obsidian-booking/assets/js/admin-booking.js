/**
 * Obsidian Booking — Admin JS
 * Color variant unit enforcement + per-slot image picker (WP Media Uploader).
 */
(function($) {
	'use strict';

	$(document).ready(function() {

		/* ── Unit allocation enforcement ── */

		var $counter   = $('.obsidian-units-counter');
		var $allocated = $counter.find('.counter-allocated');
		var totalUnits = parseInt($counter.data('total'), 10) || 0;

		function recalcUnits() {
			var sum = 0;
			$('.variant-units-input').each(function() {
				sum += parseInt($(this).val(), 10) || 0;
			});

			$allocated.text(sum);

			if (sum > totalUnits) {
				$counter.addClass('over-limit');
			} else {
				$counter.removeClass('over-limit');
			}
		}

		$(document).on('input change', '.variant-units-input', function() {
			var $input = $(this);
			var val    = parseInt($input.val(), 10) || 0;

			if (val < 0) {
				$input.val(0);
			}

			var othersSum = 0;
			$('.variant-units-input').not($input).each(function() {
				othersSum += parseInt($(this).val(), 10) || 0;
			});

			var maxForThis = totalUnits - othersSum;
			if (val > maxForThis) {
				$input.val(Math.max(0, maxForThis));
			}

			recalcUnits();
		});

		if ($counter.length) {
			recalcUnits();
		}

		/* ── Per-Slot Image Picker ── */

		$(document).on('click', '.obsidian-upload-image', function(e) {
			e.preventDefault();

			var $button  = $(this);
			var $slot    = $button.closest('.variant-image-slot');
			var $idInput = $slot.find('.variant-image-id');
			var $preview = $slot.find('.variant-image-preview');

			var frame = wp.media({
				title: 'Select Image',
				button: { text: 'Use This Image' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				var thumbUrl   = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$idInput.val(attachment.id);
				$preview.html('<img src="' + thumbUrl + '" alt="" />');
				$button.text('Change');

				if ($slot.find('.obsidian-remove-image').length === 0) {
					$button.after(
						'<button type="button" class="button button-small obsidian-remove-image">&times;</button>'
					);
				}
			});

			frame.open();
		});

		$(document).on('click', '.obsidian-remove-image', function(e) {
			e.preventDefault();

			var $slot = $(this).closest('.variant-image-slot');
			$slot.find('.variant-image-id').val('0');
			$slot.find('.variant-image-preview').html('');
			$slot.find('.obsidian-upload-image').text('Upload');
			$(this).remove();
		});

		/* ── Booking Meta Box: Approve / Deny / Status Actions ── */

		function bookingAction(action, bookingId, extraData) {
			var data = $.extend({
				action: 'obsidian_booking_action',
				nonce: obsidianAdmin.nonce,
				booking_id: bookingId,
				booking_action: action
			}, extraData || {});

			return $.post(obsidianAdmin.ajaxUrl, data);
		}

		function showFeedback(msg, type) {
			$('.obm-feedback').remove();
			var cls = type === 'error' ? 'obm-feedback-error' : 'obm-feedback-success';
			var $el = $('<div class="obm-feedback ' + cls + '">' + msg + '</div>');
			$('.obm-actions-section').last().append($el);
		}

		$(document).on('click', '#obm-approve', function() {
			var $btn = $(this);
			var bookingId = $btn.data('booking-id');
			$btn.prop('disabled', true).text('Approving...');

			bookingAction('approve', bookingId).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).text('Approve Documents');
				}
			}).fail(function() {
				showFeedback('Request failed. Please try again.', 'error');
				$btn.prop('disabled', false).text('Approve Documents');
			});
		});

		$(document).on('input', '#obm-denial-reason', function() {
			$('#obm-deny').prop('disabled', !$(this).val().trim());
		});

		$(document).on('click', '#obm-deny', function() {
			var $btn = $(this);
			var bookingId = $btn.data('booking-id');
			var reason = $('#obm-denial-reason').val().trim();

			if (!reason) return;

			$btn.prop('disabled', true).text('Denying...');

			bookingAction('deny', bookingId, { reason: reason }).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).text('Deny');
				}
			}).fail(function() {
				showFeedback('Request failed. Please try again.', 'error');
				$btn.prop('disabled', false).text('Deny');
			});
		});

		$(document).on('click', '#obm-mark-active', function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Updating...');
			bookingAction('mark_active', $btn.data('booking-id')).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).text('Mark as Active');
				}
			});
		});

		$(document).on('click', '#obm-mark-completed', function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Updating...');
			bookingAction('mark_completed', $btn.data('booking-id')).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).text('Mark as Completed');
				}
			});
		});

		$(document).on('click', '#obm-save-notes', function() {
			var $btn = $(this);
			var bookingId = $btn.data('booking-id');
			var notes = $('#obm-admin-notes').val();

			$btn.prop('disabled', true).text('Saving...');

			bookingAction('save_notes', bookingId, { notes: notes }).done(function(res) {
				$btn.prop('disabled', false).text('Save Notes');
				if (res.success) {
					$btn.text('Saved!');
					setTimeout(function() { $btn.text('Save Notes'); }, 1500);
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Save Notes');
			});
		});

	});
})(jQuery);
