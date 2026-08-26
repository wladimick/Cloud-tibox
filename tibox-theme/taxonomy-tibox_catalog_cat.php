<?php
get_header();

if (tibox_theme_design_package_id('catalog_archive') > 0) {
    $items = tibox_theme_capture_loop('template-parts/catalog-card');
    $tokens = tibox_theme_archive_tokens($items);
    ?>
    <main id="main-content" class="tibox-main tibox-catalog-archive">
        <?php tibox_theme_render_design_package('catalog_archive', $tokens); ?>
    </main>
    <?php
    get_footer();
    return;
}

if (tibox_theme_get_template_html('template_catalog_archive_html') !== '') {
    $items = tibox_theme_capture_loop('template-parts/catalog-card');
    $tokens = tibox_theme_archive_tokens($items);
    ?>
    <main id="main-content" class="tibox-main tibox-catalog-archive">
        <?php tibox_theme_render_saved_template('template_catalog_archive_html', $tokens); ?>
    </main>
    <?php
    get_footer();
    return;
}
?>
<main id="main-content" class="tibox-main tibox-catalog-archive">
    <section class="tibox-container tibox-catalog-grid-section">
        <header class="tibox-page-header">
            <h1><?php single_term_title(); ?></h1>
            <?php the_archive_description('<div class="tibox-archive-description">', '</div>'); ?>
        </header>
        <?php if (have_posts()) : ?>
            <div class="tibox-grid tibox-grid--catalog">
                <?php while (have_posts()) : the_post(); get_template_part('template-parts/catalog-card'); endwhile; ?>
            </div>
            <?php the_posts_pagination(); ?>
        <?php endif; ?>
    </section>
</main>
<?php get_footer(); ?>
