/**
 * Obsidian Booking — Admin JS
 * Color variant image picker (WP Media Uploader) + future approve/deny AJAX.
 */
(function($) {
	'use strict';

	$(document).ready(function() {

		/* ── Color Variant Image Picker ── */

		$('.obsidian-upload-image').on('click', function(e) {
			e.preventDefault();

			var button  = $(this);
			var row     = button.closest('.obsidian-variant-row');
			var idInput = row.find('.variant-image-id');
			var preview = row.find('.variant-image-preview');
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
