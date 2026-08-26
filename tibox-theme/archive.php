<?php
get_header();

if (tibox_theme_design_package_id('archive') > 0) {
    $items = tibox_theme_capture_loop();
    $tokens = tibox_theme_archive_tokens($items);
    ?>
    <main id="main-content" class="tibox-main">
        <?php tibox_theme_render_design_package('archive', $tokens); ?>
    </main>
    <?php
    get_footer();
    return;
}

if (tibox_theme_get_template_html('template_archive_html') !== '') {
    $items = tibox_theme_capture_loop();
    $tokens = tibox_theme_archive_tokens($items);
    ?>
    <main id="main-content" class="tibox-main">
        <?php tibox_theme_render_saved_template('template_archive_html', $tokens); ?>
    </main>
    <?php
    get_footer();
    return;
}
?>
<main id="main-content" class="tibox-main">
    <div class="tibox-container tibox-stack">
        <header class="tibox-page-header">
            <h1><?php the_archive_title(); ?></h1>
            <?php the_archive_description('<div class="tibox-archive-description">', '</div>'); ?>
        </header>
        <?php if (have_posts()) : ?>
            <div class="tibox-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <article <?php post_class('tibox-content-card'); ?>>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php the_excerpt(); ?>
                    </article>
                <?php endwhile; ?>
            </div>
            <?php the_posts_pagination(); ?>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
