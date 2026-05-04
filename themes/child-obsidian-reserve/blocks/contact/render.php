<?php
/**
 * Contact Block Template.
 *
 * @package child-obsidian-reserve
 */

$attributes = $attributes ?? array();

$header_title = $attributes['headerTitle'] ?? '';
$header_desc  = $attributes['headerDesc'] ?? '';

$cards = array(
	array(
		'icon'   => $attributes['card1Icon'] ?? '',
		'title'  => $attributes['card1Title'] ?? '',
		'desc'   => $attributes['card1Desc'] ?? '',
		'detail' => $attributes['card1Detail'] ?? '',
	),
	array(
		'icon'   => $attributes['card2Icon'] ?? '',
		'title'  => $attributes['card2Title'] ?? '',
		'desc'   => $attributes['card2Desc'] ?? '',
		'detail' => $attributes['card2Detail'] ?? '',
	),
	array(
		'icon'   => $attributes['card3Icon'] ?? '',
		'title'  => $attributes['card3Title'] ?? '',
		'desc'   => $attributes['card3Desc'] ?? '',
		'detail' => $attributes['card3Detail'] ?? '',
	),
);

$form_title    = $attributes['formTitle'] ?? '';
$form_desc     = $attributes['formDesc'] ?? '';
$form_btn_text = $attributes['formBtnText'] ?? 'Submit Inquiry';

$footer_title = $attributes['footerTitle'] ?? '';
$footer_desc  = $attributes['footerDesc'] ?? '';

$theme_uri = get_stylesheet_directory_uri();
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'obsidian-contact-block' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="contact-container">
		
		<!-- Header -->
		<div class="contact-header">
			<?php if ( $header_title ) : ?>
				<h1 class="contact-header-title"><?php echo wp_kses_post( $header_title ); ?></h1>
			<?php endif; ?>
			<?php if ( $header_desc ) : ?>
				<div class="contact-header-desc"><?php echo wp_kses_post( $header_desc ); ?></div>
			<?php endif; ?>
		</div>
		
		<!-- Cards Grid -->
		<div class="contact-cards">
			<?php
			foreach ( $cards as $card ) :
				$img_src = '';
				if ( ! empty( $card['icon'] ) ) {
					$img_src = 0 === strpos( $card['icon'], 'http' ) ? $card['icon'] : $theme_uri . $card['icon'];
				}
				?>
				<div class="contact-card">
					<?php if ( $img_src ) : ?>
						<img src="<?php echo esc_url( $img_src ); ?>" class="contact-card-icon" alt="" loading="lazy"/>
					<?php endif; ?>
					<?php if ( $card['title'] ) : ?>
						<h3 class="contact-card-title"><?php echo wp_kses_post( $card['title'] ); ?></h3>
					<?php endif; ?>
					<?php if ( $card['desc'] ) : ?>
						<div class="contact-card-desc"><?php echo wp_kses_post( $card['desc'] ); ?></div>
					<?php endif; ?>
					<?php if ( $card['detail'] ) : ?>
						<div class="contact-card-detail"><?php echo wp_kses_post( $card['detail'] ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Form Container -->
		<div class="contact-form-container">
			<?php if ( $form_title ) : ?>
				<h2 class="contact-form-title"><?php echo wp_kses_post( $form_title ); ?></h2>
			<?php endif; ?>
			<?php if ( $form_desc ) : ?>
				<div class="contact-form-desc"><?php echo wp_kses_post( $form_desc ); ?></div>
			<?php endif; ?>

			<form class="obsidian-contact-form" action="#" method="POST">
				
				<div class="form-row">
					<div class="form-group">
						<label for="fname">First Name:</label>
						<input type="text" id="fname" name="fname" required>
					</div>
					<div class="form-group">
						<label for="lname">Last Name:</label>
						<input type="text" id="lname" name="lname" required>
					</div>
				</div>
				
				<div class="form-row">
					<div class="form-group">
						<label for="email">Email Address:</label>
						<input type="email" id="email" name="email" required>
					</div>
					<div class="form-group">
						<label for="phone">Contact Number:</label>
						<input type="tel" id="phone" name="phone" required>
					</div>
				</div>
				
				<div class="form-group-full">
					<textarea id="message" name="message" rows="5" placeholder="Message:"></textarea>
				</div>
				
				<div class="form-row form-row-center">
					<div class="form-select-group">
						<select id="concern" name="concern" required>
							<option value="" disabled selected>Select Concern</option>
							<option value="reservation">Vehicle Reservation</option>
							<option value="concierge">Concierge Support</option>
							<option value="fleet">Fleet Viewing</option>
							<option value="other">Other Inquiry</option>
						</select>
					</div>
				</div>
				
				<div class="form-submit-wrapper">
					<div class="wp-block-buttons">
						<div class="wp-block-button is-style-solid-gold">
							<button type="submit" class="wp-block-button__link wp-element-button"><?php echo esc_html( $form_btn_text ); ?></button>
						</div>
					</div>
				</div>
				
			</form>
		</div>
		
		<!-- Footer Ext -->
		<div class="contact-footer">
			<?php if ( $footer_title ) : ?>
				<h2 class="contact-footer-title"><?php echo wp_kses_post( $footer_title ); ?></h2>
			<?php endif; ?>
			<?php if ( $footer_desc ) : ?>
				<div class="contact-footer-desc"><?php echo wp_kses_post( $footer_desc ); ?></div>
			<?php endif; ?>
		</div>

	</div>
</section>
