<?php
/**
 * FAQ Block Template.
 *
 * @param   array $attributes - A clean associative array of block attributes.
 * @param   array $block - All details about the block.
 * @param   string $content - The block inner HTML (empty).
 *
 * @package child-obsidian-reserve
 */

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'obsidian-faq-block alignfull',
	)
);

// Fallback logic in case attributes are somehow empty.
$title_part1     = $attributes['titlePart1'] ?? 'Frequently Asked ';
$title_part2     = $attributes['titlePart2'] ?? 'Questions';
$sidebar_title   = $attributes['sidebarTitle'] ?? 'Need Common Question?';
$sidebar_desc    = $attributes['sidebarDesc'] ?? 'Find the Answer to Frequently asked question here.';
$sidebar_support = $attributes['sidebarSupport'] ?? 'Need Support?';
$button_text     = $attributes['buttonText'] ?? 'Contact Us';
$button_link     = $attributes['buttonLink'] ?? '/contact-us/';

// Default FAQs.
$default_faqs = array(
	array(
		'q' => 'Can I cancel or change my reservation after confirming?',
		'a' => 'Yes, you can modify or cancel your reservation. Please refer to our cancellation policy for more details.',
	),
	array(
		'q' => 'What if my flight is delayed and I miss my vehicle delivery?',
		'a' => 'We monitor flight statuses. Please provide your flight number when booking. Your driver will wait or adjust schedules accordingly.',
	),
	array(
		'q' => 'Can I drive the car outside Metro Manila?',
		'a' => 'Yes, our vehicles can be driven outside Metro Manila subject to prior agreement and additional terms.',
	),
);

$faqs = ! empty( $attributes['faqs'] ) ? $attributes['faqs'] : $default_faqs;

?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<!-- Gold smudges -->
	<div class="faq-smudge faq-smudge-left"></div>
	<div class="faq-smudge faq-smudge-right"></div>

	<div class="faq-container">
		<h1 class="faq-main-title wp-block-heading">
			<?php echo esc_html( $title_part1 ); ?><span class="gold-text"><?php echo esc_html( $title_part2 ); ?></span>
		</h1>

		<div class="faq-grid">
			<!-- Left side info -->
			<div class="faq-sidebar">
				<h3 class="wp-block-heading"><?php echo esc_html( $sidebar_title ); ?></h3>
				<p class="faq-sidebar-desc wp-block-paragraph"><?php echo esc_html( $sidebar_desc ); ?></p>
				
				<div class="faq-support-row">
					<p class="wp-block-paragraph"><?php echo esc_html( $sidebar_support ); ?></p>
					<div class="wp-block-button is-style-solid-gold">
						<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $button_link ); ?>">
							<?php echo esc_html( $button_text ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- Right side accordions -->
			<div class="faq-accordion-container">
				<?php foreach ( $faqs as $index => $faq ) : ?>
					<div class="faq-item">
						<button class="faq-question" aria-expanded="false" aria-controls="faq-answer-<?php echo esc_attr( $index ); ?>">
							<span><?php echo esc_html( $faq['q'] ); ?></span>
							<span class="faq-icon">+</span>
						</button>
						<div id="faq-answer-<?php echo esc_attr( $index ); ?>" class="faq-answer" aria-hidden="true">
							<div class="faq-answer-inner wp-block-paragraph">
								<p><?php echo esc_html( $faq['a'] ); ?></p>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
