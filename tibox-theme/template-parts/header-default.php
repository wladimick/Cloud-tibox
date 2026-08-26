<header class="tibox-default-header">
    <div class="tibox-container tibox-default-header__inner">
        <a class="tibox-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> — Inicio">
            <?php echo tibox_theme_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
        <nav aria-label="Navegación principal">
            <?php echo tibox_theme_menu_html('primary'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </nav>
    </div>
</header>
