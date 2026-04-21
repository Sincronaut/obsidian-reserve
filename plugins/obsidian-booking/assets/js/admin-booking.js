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

		var $inventory = $('.obsidian-inventory');

		// Recalculate the per-branch units total in a tab's footer row.
		// Only colors whose "stocked here?" checkbox is ticked count toward
		// the total — an unticked row is a color this branch doesn't carry.
		function recalcBranchTotal($panel) {
			var sum = 0;
			$panel.find('.branch-color-row.is-stocked .branch-units-input').each(function() {
				sum += parseInt($(this).val(), 10) || 0;
			});
			$panel.find('.branch-units-total').text(sum);
		}

		// Sync a single row's UI to its checkbox state — toggles the
		// .is-stocked class and the disabled attribute on the units input.
		function syncStockedRow($row) {
			var checked = $row.find('.branch-stocked-toggle').is(':checked');
			$row.toggleClass('is-stocked', checked);
			var $units = $row.find('.branch-units-input');
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
			var $row = $(this).closest('.branch-color-row');
			syncStockedRow($row);
			recalcBranchTotal($row.closest('.obsidian-tab-panel'));
		});

		// Live-update the total as the admin types into a units input.
		$(document).on('input change', '.branch-units-input', function() {
			var $input = $(this);
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
			var branchId = $(this).data('branch-id');

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

			var $tab     = $(this).closest('.obsidian-tab');
			var branchId = $tab.data('branch-id');
			var $tabs    = $tab.parent();
			var $panels  = $tab.closest('.obsidian-inventory').find('.obsidian-tab-panels');
			var $panel   = $panels.find('.obsidian-tab-panel[data-branch-id="' + branchId + '"]');
			var label    = $tab.find('.tab-label').text();

			// Block removal if it's the last branch — every car needs at least one.
			if ($tabs.find('.obsidian-tab').length <= 1) {
				window.alert('A car must be assigned to at least one branch. Add another branch first, then remove this one.');
				return;
			}

			if (!window.confirm('Remove "' + label + '" from this car? Units stocked here will be cleared on save.')) {
				return;
			}

			var wasActive = $tab.hasClass('is-active');
			$tab.remove();
			$panel.remove();

			// Put the freed branch back into the "+ Add branch" dropdown.
			var $addSelect = $('#obsidian-add-branch');
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
			var $select  = $(this);
			var branchId = $select.val();
			if (!branchId) {
				return;
			}
			var $option = $select.find('option[value="' + branchId + '"]');
			var name    = $option.data('name') || $option.text();

			var $template = $('#obsidian-branch-panel-template');
			if (!$template.length) {
				return;
			}

			// Clone the template and rewrite all __BRANCH_ID__ placeholders.
			var html = $template.html().replace(/__BRANCH_ID__/g, branchId);
			var $newPanel = $(html);

			// Build the matching tab.
			var $newTab = $(
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
