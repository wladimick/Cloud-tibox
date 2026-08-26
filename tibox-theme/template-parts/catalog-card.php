<?php
$type = (string) get_post_meta(get_the_ID(), '_tibox_catalog_type', true);
$summary = (string) get_post_meta(get_the_ID(), '_tibox_catalog_summary', true);
$price = (string) get_post_meta(get_the_ID(), '_tibox_catalog_price', true);
$badge = (string) get_post_meta(get_the_ID(), '_tibox_catalog_badge', true);
?>
<article <?php post_class('tibox-catalog-card'); ?>>
    <?php if (has_post_thumbnail()) : ?>
        <a class="tibox-catalog-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
            <?php the_post_thumbnail('medium_large'); ?>
        </a>
    <?php endif; ?>
    <div class="tibox-catalog-card__body tibox-stack">
        <div class="tibox-catalog-card__meta">
            <?php if ($type !== '') : ?><span class="tibox-eyebrow"><?php echo esc_html(ucfirst($type)); ?></span><?php endif; ?>
            <?php if ($badge !== '') : ?><span class="tibox-badge"><?php echo esc_html($badge); ?></span><?php endif; ?>
        </div>
        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <?php if ($summary !== '') : ?><p><?php echo esc_html($summary); ?></p><?php endif; ?>
        <?php if ($price !== '') : ?><p class="tibox-price"><?php echo esc_html($price); ?></p><?php endif; ?>
        <p><a class="tibox-text-link" href="<?php the_permalink(); ?>">Ver detalle</a></p>
    </div>
</article>
