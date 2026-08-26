<?php get_header(); ?>
<main id="main-content" class="tibox-main">
    <div class="tibox-container tibox-stack">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('tibox-content-card'); ?>>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_excerpt(); ?>
                </article>
            <?php endwhile; ?>
            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p>No hay contenido publicado.</p>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
