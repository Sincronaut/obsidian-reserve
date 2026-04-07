<?php
/**
 * Standard Block Template.
 */

$theme_uri = get_stylesheet_directory_uri();

$cards = [
    [
        'title'    => $attributes['card1Title'] ?? '',
        'desc'     => $attributes['card1Desc'] ?? '',
        'btn_text' => $attributes['card1BtnText'] ?? '',
        'btn_url  '=> $attributes['card1BtnUrl'] ?? '#',
        'img'      => $attributes['card1Img'] ?? '',
        'class'    => 'standard-card standard-card-full'
    ],
    [
        'title'    => $attributes['card2Title'] ?? '',
        'desc'     => $attributes['card2Desc'] ?? '',
        'btn_text' => $attributes['card2BtnText'] ?? '',
        'btn_url  '=> $attributes['card2BtnUrl'] ?? '#',
        'img'      => $attributes['card2Img'] ?? '',
        'class'    => 'standard-card standard-card-half'
    ],
    [
        'title'    => $attributes['card3Title'] ?? '',
        'desc'     => $attributes['card3Desc'] ?? '',
        'btn_text' => $attributes['card3BtnText'] ?? '',
        'btn_url  '=> $attributes['card3BtnUrl'] ?? '#',
        'img'      => $attributes['card3Img'] ?? '',
        'class'    => 'standard-card standard-card-half'
    ]
];
?>

<section <?php echo get_block_wrapper_attributes(['class' => 'obsidian-standard-block']); ?>>
    <div class="standard-cards-container">
        
        <?php foreach ( $cards as $card ) : 
            $img_src = '';
            if ( ! empty( $card['img'] ) ) {
                $img_src = strpos($card['img'], 'http') === 0 ? $card['img'] : $theme_uri . $card['img'];
            }
        ?>
            <div class="<?php echo esc_attr( $card['class'] ); ?>">
                
                <?php if ( $img_src ) : ?>
                    <img class="standard-card-bg-img" src="<?php echo esc_url($img_src); ?>" alt="" aria-hidden="true" loading="lazy">
                <?php endif; ?>
                
                <div class="standard-card-content">
                    <?php if ( $card['title'] ) : ?>
                        <h2 class="standard-card-title"><?php echo wp_kses_post( $card['title'] ); ?></h2>
                    <?php endif; ?>
                    
                    <?php if ( $card['desc'] ) : ?>
                        <p class="standard-card-desc"><?php echo wp_kses_post( $card['desc'] ); ?></p>
                    <?php endif; ?>
                    
                    <?php if ( $card['btn_text'] ) : ?>
                        <div class="wp-block-buttons">
                            <div class="wp-block-button is-style-outline-gold">
                                <a href="<?php echo esc_url($card['btn_url  ']); ?>" class="wp-block-button__link wp-element-button">
                                    <?php echo esc_html( $card['btn_text'] ); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>

    </div>
</section>
