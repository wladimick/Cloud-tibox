<?php
get_header();

if (tibox_theme_design_package_id('catalog_archive') > 0) {
    $items = tibox_theme_capture_loop('template-parts/catalog-card');
    $tokens = tibox_theme_archive_tokens($items);
    $tokens['{{ARCHIVE_TITLE}}'] = 'Catálogo';
    if (trim(wp_strip_all_tags($tokens['{{ARCHIVE_DESCRIPTION}}'])) === '') {
        $tokens['{{ARCHIVE_DESCRIPTION}}'] = 'Servicios, planes, aplicaciones, productos digitales y soluciones.';
    }
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
    $tokens['{{ARCHIVE_TITLE}}'] = 'Catálogo';
    if (trim(wp_strip_all_tags($tokens['{{ARCHIVE_DESCRIPTION}}'])) === '') {
        $tokens['{{ARCHIVE_DESCRIPTION}}'] = 'Servicios, planes, aplicaciones, productos digitales y soluciones.';
    }
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
    <section class="tibox-archive-hero">
        <div class="tibox-container">
            <p class="tibox-eyebrow">TIBOX Cloud</p>
            <h1>Catálogo</h1>
            <p class="tibox-lead">Servicios, planes, aplicaciones, productos digitales y soluciones.</p>
        </div>
    </section>
    <section class="tibox-container tibox-catalog-grid-section">
        <?php if (have_posts()) : ?>
            <div class="tibox-grid tibox-grid--catalog">
                <?php while (have_posts()) : the_post(); get_template_part('template-parts/catalog-card'); endwhile; ?>
            </div>
            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p>El catálogo todavía no tiene elementos publicados.</p>
        <?php endif; ?>
    </section>
</main>
<?php get_footer(); ?>
