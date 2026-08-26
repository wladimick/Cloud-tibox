<footer class="tibox-default-footer">
    <div class="tibox-container tibox-default-footer__inner">
        <div><?php echo tibox_theme_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <nav aria-label="Navegación del footer">
            <?php echo tibox_theme_menu_html('footer'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </nav>
        <small>© <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>.</small>
    </div>
</footer>
