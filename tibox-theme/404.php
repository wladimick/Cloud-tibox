<?php get_header(); ?>
<main id="main-content" class="tibox-main">
    <?php
    if (!tibox_theme_render_design_package('404') && !tibox_theme_render_saved_template('template_404_html')) :
    ?>
        <div class="tibox-container tibox-empty-state">
            <p class="tibox-eyebrow">404</p>
            <h1>Página no encontrada</h1>
            <p>La dirección puede haber cambiado o el contenido ya no está disponible.</p>
            <a class="tibox-button" href="<?php echo esc_url(home_url('/')); ?>">Volver al inicio</a>
        </div>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
