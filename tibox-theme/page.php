<?php get_header(); ?>
<main id="main-content" class="tibox-main">
    <?php while (have_posts()) : the_post(); ?>
        <?php
        $tokens = tibox_theme_current_post_tokens(get_post());

        if (tibox_theme_render_design_package('page', $tokens, get_the_ID())) {
            continue;
        }

        if (tibox_theme_render_saved_template('template_page_html', $tokens)) {
            continue;
        }
        ?>
        <div class="tibox-container">
            <article <?php post_class('tibox-page'); ?>>
                <header class="tibox-page-header">
                    <h1><?php the_title(); ?></h1>
                </header>
                <div class="tibox-entry-content"><?php the_content(); ?></div>
            </article>
        </div>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
