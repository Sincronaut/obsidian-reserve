/**
 * Obsidian Booking — Admin JS
 * Color variant unit enforcement + per-slot image picker (WP Media Uploader).
 */
(function($) {
	'use strict';

	$(document).ready(function() {

		/* ══════════════════════════════════════════════════════════
		   INVENTORY META BOX (Phase 11 — tabbed by branch)
		   ══════════════════════════════════════════════════════════ */

		const $inventory = $('.obsidian-inventory');

		// Recalculate the per-branch units total in a tab's footer row.
		// Only colors whose "stocked here?" checkbox is ticked count toward
		// the total — an unticked row is a color this branch doesn't carry.
		function recalcBranchTotal($panel) {
			let sum = 0;
			$panel.find('.branch-color-row.is-stocked .branch-units-input').each(function() {
				sum += parseInt($(this).val(), 10) || 0;
			});
			$panel.find('.branch-units-total').text(sum);
		}

		// Sync a single row's UI to its checkbox state — toggles the
		// .is-stocked class and the disabled attribute on the units input.
		function syncStockedRow($row) {
			const checked = $row.find('.branch-stocked-toggle').is(':checked');
			$row.toggleClass('is-stocked', checked);
			const $units = $row.find('.branch-units-input');
			$units.prop('disabled', !checked);
			if (!checked) {
				$units.val(0);
			} else if ((parseInt($units.val(), 10) || 0) === 0) {
				// First-time check: pre-fill 1 so admins don't ship 0-unit colors by accident.
				$units.val(1);
			}
		}

		// Initialise totals + row states on first render.
		$('.obsidian-tab-panel').each(function() {
			$(this).find('.branch-color-row').each(function() { syncStockedRow($(this)); });
			recalcBranchTotal($(this));
		});

		// "Stocked here?" checkbox toggle.
		$(document).on('change', '.branch-stocked-toggle', function() {
			const $row = $(this).closest('.branch-color-row');
			syncStockedRow($row);
			recalcBranchTotal($row.closest('.obsidian-tab-panel'));
		});

		// Live-update the total as the admin types into a units input.
		$(document).on('input change', '.branch-units-input', function() {
			const $input = $(this);
			if ((parseInt($input.val(), 10) || 0) < 0) {
				$input.val(0);
			}
			recalcBranchTotal($input.closest('.obsidian-tab-panel'));
		});

		// Tab switching.
		$(document).on('click', '.obsidian-tab', function(e) {
			// Ignore the × inside the tab — handled separately below.
			if ($(e.target).hasClass('tab-remove')) {
				return;
			}
			const branchId = $(this).data('branch-id');

			$(this).siblings('.obsidian-tab')
				.removeClass('is-active')
				.attr('aria-selected', 'false');
			$(this).addClass('is-active').attr('aria-selected', 'true');

			$(this).closest('.obsidian-inventory')
				.find('.obsidian-tab-panel')
				.removeClass('is-active');
			$(this).closest('.obsidian-inventory')
				.find('.obsidian-tab-panel[data-branch-id="' + branchId + '"]')
				.addClass('is-active');
		});

		// Remove a branch from this car.
		$(document).on('click', '.tab-remove', function(e) {
			e.stopPropagation();

			const $tab     = $(this).closest('.obsidian-tab');
			const branchId = $tab.data('branch-id');
			const $tabs    = $tab.parent();
			const $panels  = $tab.closest('.obsidian-inventory').find('.obsidian-tab-panels');
			const $panel   = $panels.find('.obsidian-tab-panel[data-branch-id="' + branchId + '"]');
			const label    = $tab.find('.tab-label').text();

			// Block removal if it's the last branch — every car needs at least one.
			if ($tabs.find('.obsidian-tab').length <= 1) {
				window.alert('A car must be assigned to at least one branch. Add another branch first, then remove this one.');
				return;
			}

			if (!window.confirm('Remove "' + label + '" from this car? Units stocked here will be cleared on save.')) {
				return;
			}

			const wasActive = $tab.hasClass('is-active');
			$tab.remove();
			$panel.remove();

			// Put the freed branch back into the "+ Add branch" dropdown.
			const $addSelect = $('#obsidian-add-branch');
			$addSelect.append(
				$('<option/>', {
					value: branchId,
					'data-name': label,
					text: label
				})
			);

			// If we just removed the active tab, activate the first remaining tab.
			if (wasActive) {
				$tabs.find('.obsidian-tab').first().trigger('click');
			}
		});

		// "+ Add branch" — clone the hidden template and inject a new tab + panel.
		$(document).on('change', '#obsidian-add-branch', function() {
			const $select  = $(this);
			const branchId = $select.val();
			if (!branchId) {
				return;
			}
			const $option = $select.find('option[value="' + branchId + '"]');
			const name    = $option.data('name') || $option.text();

			const $template = $('#obsidian-branch-panel-template');
			if (!$template.length) {
				return;
			}

			// Clone the template and rewrite all __BRANCH_ID__ placeholders.
			const html = $template.html().replace(/__BRANCH_ID__/g, branchId);
			const $newPanel = $(html);

			// Build the matching tab.
			const $newTab = $(
				'<button type="button" class="obsidian-tab" role="tab" aria-selected="false">' +
					'<span class="tab-label"></span>' +
					'<span class="tab-remove" title="Remove branch" aria-label="Remove branch">&times;</span>' +
				'</button>'
			);
			$newTab.attr('data-branch-id', branchId);
			$newTab.find('.tab-label').text(name);

			$inventory.find('.obsidian-tabs').append($newTab);
			$inventory.find('.obsidian-tab-panels').append($newPanel);

			// Remove this branch from the dropdown and reset the picker.
			$option.remove();
			$select.val('');

			// Activate the new tab immediately so the admin lands on it.
			$newTab.trigger('click');
		});

		/* ── Per-Slot Image Picker ── */

		$(document).on('click', '.obsidian-upload-image', function(e) {
			e.preventDefault();

			const $button  = $(this);
			const $slot    = $button.closest('.variant-image-slot');
			const $idInput = $slot.find('.variant-image-id');
			const $preview = $slot.find('.variant-image-preview');

			const frame = wp.media({
				title: 'Select Image',
				button: { text: 'Use This Image' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function() {
				const attachment = frame.state().get('selection').first().toJSON();
				const thumbUrl   = attachment.sizes && attachment.sizes.thumbnail
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

			const $slot = $(this).closest('.variant-image-slot');
			$slot.find('.variant-image-id').val('0');
			$slot.find('.variant-image-preview').html('');
			$slot.find('.obsidian-upload-image').text('Upload');
			$(this).remove();
		});

		/* ── Booking Meta Box: Approve / Deny / Status Actions ── */

		function bookingAction(action, bookingId, extraData) {
			const data = $.extend({
				action: 'obsidian_booking_action',
				nonce: obsidianAdmin.nonce,
				booking_id: bookingId,
				booking_action: action
			}, extraData || {});

			return $.post(obsidianAdmin.ajaxUrl, data);
		}

		function showFeedback(msg, type) {
			$('.obm-feedback').remove();
			const cls = type === 'error' ? 'obm-feedback-error' : 'obm-feedback-success';
			const $el = $('<div class="obm-feedback ' + cls + '">' + msg + '</div>');
			$('.obm-actions-section').last().append($el);
		}

		$(document).on('click', '#obm-approve', function() {
			const $btn = $(this);
			const bookingId = $btn.data('booking-id');
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ob-spin"></span> Approving...');

			bookingAction('approve', bookingId).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Approve Documents');
				}
			}).fail(function() {
				showFeedback('Request failed. Please try again.', 'error');
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Approve Documents');
			});
		});

		$(document).on('input', '#obm-denial-reason', function() {
			$('#obm-deny').prop('disabled', !$(this).val().trim());
		});

		$(document).on('click', '#obm-deny', function() {
			const $btn = $(this);
			const bookingId = $btn.data('booking-id');
			const reason = $('#obm-denial-reason').val().trim();

			if (!reason) return;

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ob-spin"></span> Denying...');

			bookingAction('deny', bookingId, { reason: reason }).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Deny');
				}
			}).fail(function() {
				showFeedback('Request failed. Please try again.', 'error');
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Deny');
			});
		});

		$(document).on('click', '#obm-mark-active', function() {
			const $btn = $(this);
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ob-spin"></span> Updating...');
			bookingAction('mark_active', $btn.data('booking-id')).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Mark as Active');
				}
			});
		});

		$(document).on('click', '#obm-mark-completed', function() {
			const $btn = $(this);
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ob-spin"></span> Updating...');
			bookingAction('mark_completed', $btn.data('booking-id')).done(function(res) {
				if (res.success) {
					showFeedback(res.data.message, 'success');
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showFeedback(res.data.message, 'error');
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Mark as Completed');
				}
			});
		});

		$(document).on('click', '#obm-save-notes', function() {
			const $btn = $(this);
			const bookingId = $btn.data('booking-id');
			const notes = $('#obm-admin-notes').val();

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
