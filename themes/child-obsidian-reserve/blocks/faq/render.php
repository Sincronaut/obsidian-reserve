<?php
/**
 * FAQ Block Template.
 *
 * @param   array $attributes - A clean associative array of block attributes.
 * @param   array $block - All details about the block.
 * @param   string $content - The block inner HTML (empty).
 */

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'obsidian-faq-block alignfull',
] );

// Fallback logic in case attributes are somehow empty
$titlePart1   = $attributes['titlePart1'] ?? 'Frequently Asked ';
$titlePart2   = $attributes['titlePart2'] ?? 'Questions';
$sidebarTitle = $attributes['sidebarTitle'] ?? 'Need Common Question?';
$sidebarDesc  = $attributes['sidebarDesc'] ?? 'Find the Answer to Frequently asked question here.';
$sidebarSupport = $attributes['sidebarSupport'] ?? 'Need Support?';
$buttonText   = $attributes['buttonText'] ?? 'Contact Us';
$buttonLink   = $attributes['buttonLink'] ?? '/contact-us/';

// Default FAQs
$default_faqs = [
	[
		'q' => 'Can I cancel or change my reservation after confirming?',
		'a' => 'Yes, you can modify or cancel your reservation. Please refer to our cancellation policy for more details.'
	],
	[
		'q' => 'What if my flight is delayed and I miss my vehicle delivery?',
		'a' => 'We monitor flight statuses. Please provide your flight number when booking. Your driver will wait or adjust schedules accordingly.'
	],
	[
		'q' => 'Can I drive the car outside Metro Manila?',
		'a' => 'Yes, our vehicles can be driven outside Metro Manila subject to prior agreement and additional terms.'
	]
];

$faqs = !empty($attributes['faqs']) ? $attributes['faqs'] : $default_faqs;

?>
<div <?php echo $wrapper_attributes; ?>>
	<!-- Gold smudges -->
	<div class="faq-smudge faq-smudge-left"></div>
	<div class="faq-smudge faq-smudge-right"></div>

	<div class="faq-container">
		<h1 class="faq-main-title wp-block-heading">
			<?php echo esc_html( $titlePart1 ); ?><span class="gold-text"><?php echo esc_html( $titlePart2 ); ?></span>
		</h1>

		<div class="faq-grid">
			<!-- Left side info -->
			<div class="faq-sidebar">
				<h3 class="wp-block-heading"><?php echo esc_html( $sidebarTitle ); ?></h3>
				<p class="faq-sidebar-desc wp-block-paragraph"><?php echo esc_html( $sidebarDesc ); ?></p>
				
				<div class="faq-support-row">
					<p class="wp-block-paragraph"><?php echo esc_html( $sidebarSupport ); ?></p>
					<div class="wp-block-button">
						<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $buttonLink ); ?>">
							<?php echo esc_html( $buttonText ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- Right side accordions -->
			<div class="faq-accordion-container">
				<?php foreach ( $faqs as $index => $faq ) : ?>
					<div class="faq-item">
						<button class="faq-question" aria-expanded="false" aria-controls="faq-answer-<?php echo $index; ?>">
							<span><?php echo esc_html( $faq['q'] ); ?></span>
							<span class="faq-icon">+</span>
						</button>
						<div id="faq-answer-<?php echo $index; ?>" class="faq-answer" aria-hidden="true">
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
