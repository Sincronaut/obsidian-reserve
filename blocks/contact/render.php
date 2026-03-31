<?php
/**
 * Contact Block Template.
 */

$attributes = $attributes ?? [];

$headerTitle = $attributes['headerTitle'] ?? '';
$headerDesc = $attributes['headerDesc'] ?? '';

$cards = [
	[
		'icon'   => $attributes['card1Icon'] ?? '',
		'title'  => $attributes['card1Title'] ?? '',
		'desc'   => $attributes['card1Desc'] ?? '',
		'detail' => $attributes['card1Detail'] ?? '',
	],
	[
		'icon'   => $attributes['card2Icon'] ?? '',
		'title'  => $attributes['card2Title'] ?? '',
		'desc'   => $attributes['card2Desc'] ?? '',
		'detail' => $attributes['card2Detail'] ?? '',
	],
	[
		'icon'   => $attributes['card3Icon'] ?? '',
		'title'  => $attributes['card3Title'] ?? '',
		'desc'   => $attributes['card3Desc'] ?? '',
		'detail' => $attributes['card3Detail'] ?? '',
	]
];

$formTitle = $attributes['formTitle'] ?? '';
$formDesc = $attributes['formDesc'] ?? '';
$formBtnText = $attributes['formBtnText'] ?? 'Submit Inquiry';

$footerTitle = $attributes['footerTitle'] ?? '';
$footerDesc = $attributes['footerDesc'] ?? '';

$theme_uri = get_stylesheet_directory_uri();
?>

<section <?php echo get_block_wrapper_attributes(['class' => 'obsidian-contact-block']); ?>>
    <div class="contact-container">
        
        <!-- Header -->
        <div class="contact-header">
            <?php if ($headerTitle) : ?>
                <h1 class="contact-header-title"><?php echo wp_kses_post($headerTitle); ?></h1>
            <?php endif; ?>
            <?php if ($headerDesc) : ?>
                <div class="contact-header-desc"><?php echo wp_kses_post($headerDesc); ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Cards Grid -->
        <div class="contact-cards">
            <?php foreach ($cards as $card) : 
                $img_src = '';
                if ( ! empty( $card['icon'] ) ) {
                    $img_src = strpos($card['icon'], 'http') === 0 ? $card['icon'] : $theme_uri . $card['icon'];
                }
            ?>
                <div class="contact-card">
                    <?php if ($img_src) : ?>
                        <img src="<?php echo esc_url($img_src); ?>" class="contact-card-icon" alt="" loading="lazy"/>
                    <?php endif; ?>
                    <?php if ($card['title']) : ?>
                        <h3 class="contact-card-title"><?php echo wp_kses_post($card['title']); ?></h3>
                    <?php endif; ?>
                    <?php if ($card['desc']) : ?>
                        <div class="contact-card-desc"><?php echo wp_kses_post($card['desc']); ?></div>
                    <?php endif; ?>
                    <?php if ($card['detail']) : ?>
                        <div class="contact-card-detail"><?php echo wp_kses_post($card['detail']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Form Container -->
        <div class="contact-form-container">
            <?php if ($formTitle) : ?>
                <h2 class="contact-form-title"><?php echo wp_kses_post($formTitle); ?></h2>
            <?php endif; ?>
            <?php if ($formDesc) : ?>
                <div class="contact-form-desc"><?php echo wp_kses_post($formDesc); ?></div>
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
                            <button type="submit" class="wp-block-button__link wp-element-button"><?php echo esc_html($formBtnText); ?></button>
                        </div>
                    </div>
                </div>
                
            </form>
        </div>
        
        <!-- Footer Ext -->
        <div class="contact-footer">
            <?php if ($footerTitle) : ?>
                <h2 class="contact-footer-title"><?php echo wp_kses_post($footerTitle); ?></h2>
            <?php endif; ?>
            <?php if ($footerDesc) : ?>
                <div class="contact-footer-desc"><?php echo wp_kses_post($footerDesc); ?></div>
            <?php endif; ?>
        </div>

    </div>
</section>
