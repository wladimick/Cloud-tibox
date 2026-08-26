<?php
get_header();

while (have_posts()) :
    the_post();

    $post = get_post();
    $tokens = array_merge(
        tibox_theme_current_post_tokens($post),
        tibox_theme_catalog_tokens($post)
    );

    if (tibox_theme_design_package_id('catalog_single', get_the_ID()) > 0) :
        ?>
        <main id="main-content" class="tibox-main tibox-catalog-single">
            <?php tibox_theme_render_design_package('catalog_single', $tokens, get_the_ID()); ?>
        </main>
        <?php
        continue;
    endif;

    if (tibox_theme_get_template_html('template_catalog_single_html') !== '') :
        ?>
        <main id="main-content" class="tibox-main tibox-catalog-single">
            <?php tibox_theme_render_saved_template('template_catalog_single_html', $tokens); ?>
        </main>
        <?php
        continue;
    endif;

    $type = (string) get_post_meta(get_the_ID(), '_tibox_catalog_type', true);
    $summary = (string) get_post_meta(get_the_ID(), '_tibox_catalog_summary', true);
    $price = (string) get_post_meta(get_the_ID(), '_tibox_catalog_price', true);
    $badge = (string) get_post_meta(get_the_ID(), '_tibox_catalog_badge', true);
    $cta_label = (string) get_post_meta(get_the_ID(), '_tibox_catalog_cta_label', true);
    $cta_url = (string) get_post_meta(get_the_ID(), '_tibox_catalog_cta_url', true);
?>
<main id="main-content" class="tibox-main tibox-catalog-single">
    <section class="tibox-catalog-hero">
        <div class="tibox-container tibox-catalog-hero__grid">
            <div class="tibox-stack">
                <?php if ($badge !== '') : ?><span class="tibox-badge"><?php echo esc_html($badge); ?></span><?php endif; ?>
                <?php if ($type !== '') : ?><p class="tibox-eyebrow"><?php echo esc_html(ucfirst($type)); ?></p><?php endif; ?>
                <h1><?php the_title(); ?></h1>
                <?php if ($summary !== '') : ?><p class="tibox-lead"><?php echo esc_html($summary); ?></p><?php endif; ?>
                <?php if ($price !== '') : ?><p class="tibox-price"><?php echo esc_html($price); ?></p><?php endif; ?>
                <?php if ($cta_label !== '' && $cta_url !== '') : ?>
                    <p><a class="tibox-button" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($cta_label); ?></a></p>
                <?php endif; ?>
            </div>
            <?php if (has_post_thumbnail()) : ?>
                <figure class="tibox-catalog-hero__media"><?php the_post_thumbnail('large'); ?></figure>
            <?php endif; ?>
        </div>
    </section>
    <section class="tibox-catalog-body">
        <div class="tibox-container tibox-entry-content"><?php the_content(); ?></div>
    </section>
</main>
<?php endwhile; get_footer(); ?>
