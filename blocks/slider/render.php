<?php
/**
 * Render template for the Obsidian Slider block.
 *
 * @package child-obsidian-reserve
 */

$attributes = isset($attributes) ? $attributes : array();
$title = !empty($attributes['title']) ? $attributes['title'] : '<span class="highlight-gold">In Distinguished</span> Company.';
$subtitle = !empty($attributes['subtitle']) ? $attributes['subtitle'] : 'Trusted by industry leaders, visionaries, and those who demand absolute perfection in every journey.';
$slides = !empty($attributes['slides']) ? $attributes['slides'] : array();

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class' => 'obsidian-slider-section',
) );

$padding_style = "padding-top: var(--wp--preset--spacing--section-padding-v); padding-bottom: var(--wp--preset--spacing--section-padding-v); padding-left: var(--wp--preset--spacing--section-padding-h); padding-right: var(--wp--preset--spacing--section-padding-h);";

?>

<section <?php echo $wrapper_attributes; ?> style="background-color: #0B0B0B; <?php echo esc_attr($padding_style); ?>">
    <div class="obsidian-slider-header">
        <h2><?php echo wp_kses_post($title); ?></h2>
        <p><?php echo wp_kses_post($subtitle); ?></p>
    </div>

    <div class="obsidian-slider-wrapper">
        <button class="slider-nav prev-slide" aria-label="Previous slide">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <div class="slider-track-container">
            <div class="slider-track">
                <?php if (!empty($slides)) : ?>
                    <?php foreach ($slides as $index => $slide) : ?>
                        <div class="slider-slide">
                            <div class="slide-inner">
                                <div class="slide-image">
                                    <img src="<?php echo esc_url( get_stylesheet_directory_uri() . $slide['imageUrl'] ); ?>" alt="<?php echo esc_attr($slide['name']); ?>">
                                    
                                    <div class="slide-dots">
                                        <?php for ($i = 0; $i < count($slides); $i++) : ?>
                                            <span class="dot <?php echo $i === 0 ? 'active' : ''; ?>"></span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="slide-content">
                                    <div class="quote-icon">“</div>
                                    <p class="quote-text"><?php echo esc_html($slide['quote']); ?></p>
                                    <div class="quote-divider"></div>
                                    <h3 class="quote-name"><?php echo esc_html($slide['name']); ?></h3>
                                    <p class="quote-title"><?php echo esc_html($slide['title']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <button class="slider-nav next-slide" aria-label="Next slide">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
    </div>
</section>
