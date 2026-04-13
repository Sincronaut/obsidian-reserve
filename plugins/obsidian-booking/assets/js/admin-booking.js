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

	});
})(jQuery);
