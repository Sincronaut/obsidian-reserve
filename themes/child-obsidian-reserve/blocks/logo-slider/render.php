<?php
/**
 * Render template for the Obsidian Logo Slider block.
 *
 * @package child-obsidian-reserve
 */

$attributes = isset($attributes) ? $attributes : array();
$logos = !empty($attributes['logos']) ? $attributes['logos'] : array();

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class' => 'obsidian-logo-slider-section',
) );

// Helper function to resolve URLs safely
function obsidian_resolve_logo_url($url) {
    if (empty($url) || $url === '#') {
        return '#';
    }
    // If it's a relative path starting with '/', prepend the theme directory URI
    if (strpos($url, '/') === 0) {
        return get_stylesheet_directory_uri() . $url;
    }
    return $url;
}

?>

<section <?php echo $wrapper_attributes; ?>>
    <div class="logo-slider-container">
        <!-- We duplicate the logo track twice to create a seamless CSS infinite scroll loop -->
        <div class="logo-slider-track">
            
            <?php if (!empty($logos)) : ?>
                <!-- Set 1 (Original) -->
                <?php foreach ($logos as $logo) : ?>
                    <div class="logo-slide-item">
                        <img src="<?php echo esc_url( obsidian_resolve_logo_url($logo['url']) ); ?>" alt="<?php echo esc_attr($logo['alt']); ?>">
                    </div>
                <?php endforeach; ?>
                
                <!-- Set 2 (Duplicated for the seamless loop) -->
                <?php foreach ($logos as $logo) : ?>
                    <div class="logo-slide-item">
                        <img src="<?php echo esc_url( obsidian_resolve_logo_url($logo['url']) ); ?>" alt="<?php echo esc_attr($logo['alt']); ?>">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback if no logos are provided -->
                <p style="color: white; padding: 20px;">No logos added.</p>
            <?php endif; ?>

        </div>
    </div>
</section>
