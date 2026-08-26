<?php get_header(); ?>
<main id="main-content" class="tibox-main">
    <?php while (have_posts()) : the_post(); ?>
        <?php
        $tokens = tibox_theme_current_post_tokens(get_post());

        if (tibox_theme_render_design_package('home', $tokens, get_the_ID())) {
            continue;
        }

        if (tibox_theme_render_saved_template('template_home_html', $tokens)) {
            continue;
        }
        ?>
        <article <?php post_class('tibox-page'); ?>>
            <?php the_content(); ?>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
