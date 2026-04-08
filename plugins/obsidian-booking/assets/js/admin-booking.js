/**
 * Obsidian Booking — Admin JS
 * Color variant unit enforcement + image picker (WP Media Uploader).
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

		// Live validation on every keystroke / change
		$(document).on('input change', '.variant-units-input', function() {
			var $input = $(this);
			var val    = parseInt($input.val(), 10) || 0;

			if (val < 0) {
				$input.val(0);
			}

			// Calculate sum of OTHER inputs
			var othersSum = 0;
			$('.variant-units-input').not($input).each(function() {
				othersSum += parseInt($(this).val(), 10) || 0;
			});

			// Cap this input so total doesn't exceed limit
			var maxForThis = totalUnits - othersSum;
			if (val > maxForThis) {
				$input.val(Math.max(0, maxForThis));
			}

			recalcUnits();
		});

		// Initial count on page load
		if ($counter.length) {
			recalcUnits();
		}

		/* ── Color Variant Image Picker ── */

		$('.obsidian-upload-image').on('click', function(e) {
			e.preventDefault();

			var button    = $(this);
			var row       = button.closest('.obsidian-variant-row');
			var idInput   = row.find('.variant-image-id');
			var preview   = row.find('.variant-image-preview');
			var removeBtn = row.find('.obsidian-remove-image');

			var frame = wp.media({
				title: 'Select Color Variant Image',
				button: { text: 'Use This Image' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				var thumbUrl   = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;

				idInput.val(attachment.id);
				preview.html('<img src="' + thumbUrl + '" alt="" />');
				button.text('Change Image');

				if (removeBtn.length === 0) {
					button.after(
						'<button type="button" class="button obsidian-remove-image">Remove</button>'
					);
				} else {
					removeBtn.show();
				}
			});

			frame.open();
		});

		$(document).on('click', '.obsidian-remove-image', function(e) {
			e.preventDefault();

			var row = $(this).closest('.obsidian-variant-row');
			row.find('.variant-image-id').val('0');
			row.find('.variant-image-preview').html('');
			row.find('.obsidian-upload-image').text('Choose Image');
			$(this).remove();
		});

	});
})(jQuery);
