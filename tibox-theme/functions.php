<?php
/**
 * TIBOX Theme v0.6.0
 *
 * Theme ultraliviano, sin page builder.
 * - Header/footer globales editables.
 * - Plantillas HTML editables con variables.
 * - Editor de código nativo de WordPress (CodeMirror), con apariencia oscura.
 * - La lógica comercial permanece en plugins (TIBOX Core).
 */
if (!defined('ABSPATH')) {
    exit;
}

define('TIBOX_THEME_VERSION', '0.6.0');
define('TIBOX_THEME_OPTION', 'tibox_theme_settings');

function tibox_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 260,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    register_nav_menus([
        'primary' => __('Menú principal', 'tibox-theme'),
        'footer'  => __('Menú footer', 'tibox-theme'),
    ]);
}
add_action('after_setup_theme', 'tibox_theme_setup');

function tibox_theme_enqueue_assets(): void
{
    wp_enqueue_style(
        'tibox-theme-app',
        get_template_directory_uri() . '/assets/css/app.css',
        [],
        TIBOX_THEME_VERSION
    );

    wp_enqueue_script(
        'tibox-theme-app',
        get_template_directory_uri() . '/assets/js/app.js',
        [],
        TIBOX_THEME_VERSION,
        true
    );

    /*
     * Slider Home: assets condicionales.
     * Si no existen slides activos, no se carga CSS/JS del componente.
     */
    if (is_front_page() && function_exists('tibox_core_get_home_slides')) {
        $slides = tibox_core_get_home_slides();

        if (!empty($slides)) {
            wp_enqueue_style(
                'tibox-theme-home-slider',
                get_template_directory_uri() . '/assets/css/home-slider.css',
                ['tibox-theme-app'],
                TIBOX_THEME_VERSION
            );

            if (count($slides) > 1) {
                wp_enqueue_script(
                    'tibox-theme-home-slider',
                    get_template_directory_uri() . '/assets/js/home-slider.js',
                    [],
                    TIBOX_THEME_VERSION,
                    true
                );
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'tibox_theme_enqueue_assets');

function tibox_theme_get_settings(): array
{
    $defaults = [
        'header_html'                   => '',
        'footer_html'                   => '',
        /*
         * CSS separado por responsabilidad.
         *
         * global_css se conserva por compatibilidad con v0.1-v0.3.
         * Puede utilizarse como CSS base/global mientras se migra el código
         * existente hacia los bloques específicos.
         */
        'global_css'                    => '',
        'header_css'                    => '',
        'footer_css'                    => '',
        'home_css'                      => '',
        'home_slider_css'               => '',
        'page_css'                      => '',
        'single_css'                    => '',
        'archive_css'                   => '',
        'catalog_single_css'            => '',
        'catalog_archive_css'           => '',
        '404_css'                       => '',
        'global_js'                     => '',
        'template_home_html'            => '',
        'template_page_html'            => '',
        'template_single_html'          => '',
        'template_archive_html'         => '',
        'template_catalog_single_html'  => '',
        'template_catalog_archive_html' => '',
        'template_404_html'             => '',
    ];

    $stored = get_option(TIBOX_THEME_OPTION, []);
    return wp_parse_args(is_array($stored) ? $stored : [], $defaults);
}

/**
 * Elimina solo tags PHP reales. Mantiene SVG/XML, HTML, CSS y JS.
 */
function tibox_theme_strip_php(string $content): string
{
    $content = preg_replace('/<\?php\b[\s\S]*?\?>/i', '', $content);
    $content = preg_replace('/<\?=[\s\S]*?\?>/i', '', $content);
    return is_string($content) ? $content : '';
}

function tibox_theme_menu_html(string $location): string
{
    if (!has_nav_menu($location)) {
        return '';
    }

    return (string) wp_nav_menu([
        'theme_location' => $location,
        'container'      => false,
        'echo'           => false,
        'fallback_cb'    => false,
        'menu_class'     => 'tibox-menu tibox-menu--' . sanitize_html_class($location),
    ]);
}

function tibox_theme_logo_html(): string
{
    $logo_id = (int) get_theme_mod('custom_logo');
    if ($logo_id <= 0) {
        return esc_html(get_bloginfo('name'));
    }

    return (string) wp_get_attachment_image($logo_id, 'full', false, [
        'class'   => 'tibox-site-logo',
        'loading' => 'eager',
        'alt'     => get_bloginfo('name'),
    ]);
}

function tibox_theme_global_tokens(): array
{
    return [
        '{{SITE_URL}}'     => esc_url(home_url('/')),
        '{{HOME_URL}}'     => esc_url(home_url('/')),
        '{{THEME_URL}}'    => esc_url(get_template_directory_uri()),
        '{{SITE_NAME}}'    => esc_html(get_bloginfo('name')),
        '{{CURRENT_YEAR}}' => esc_html(wp_date('Y')),
        '{{CUSTOM_LOGO}}'  => tibox_theme_logo_html(),
        '{{MENU_PRIMARY}}' => tibox_theme_menu_html('primary'),
        '{{MENU_FOOTER}}'  => tibox_theme_menu_html('footer'),
    ];
}

/**
 * Variables simples para fragmentos globales pegados desde Claude/IA.
 */
function tibox_theme_replace_tokens(string $html, array $extra_tokens = []): string
{
    return strtr($html, array_merge(tibox_theme_global_tokens(), $extra_tokens));
}

function tibox_theme_render_fragment(string $key): void
{
    $settings = tibox_theme_get_settings();
    $html = isset($settings[$key]) ? (string) $settings[$key] : '';

    if (trim($html) !== '') {
        echo tibox_theme_replace_tokens($html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    get_template_part('template-parts/' . str_replace('_html', '', $key) . '-default');
}

function tibox_theme_get_template_html(string $key): string
{
    $settings = tibox_theme_get_settings();
    return isset($settings[$key]) ? trim((string) $settings[$key]) : '';
}

/**
 * Renderiza el Slider Home administrado por TIBOX Core.
 *
 * Un slide = hero estático.
 * Dos o más = controles de navegación sin autoplay.
 */
function tibox_theme_home_slider_html(): string
{
    if (!function_exists('tibox_core_get_home_slides')) {
        return '';
    }

    $slides = tibox_core_get_home_slides();
    if (empty($slides)) {
        return '';
    }

    $count = count($slides);
    $has_multiple = $count > 1;

    ob_start();
    ?>
    <section
        class="tbx-home-slider <?php echo $has_multiple ? 'has-multiple' : 'is-static'; ?>"
        aria-label="Destacados principales"
        <?php echo $has_multiple ? 'aria-roledescription="carousel"' : ''; ?>
        data-tbx-home-slider
    >
        <div class="tbx-home-slider__viewport">
            <?php foreach ($slides as $index => $slide) :
                $surface = in_array(($slide['surface'] ?? ''), ['dark', 'brand', 'photo'], true)
                    ? $slide['surface']
                    : 'dark';
                $alignment = ($slide['alignment'] ?? '') === 'center' ? 'center' : 'left';
                $desktop_id = absint($slide['desktop_image_id'] ?? 0);
                $mobile_id = absint($slide['mobile_image_id'] ?? 0);
                $desktop_url = esc_url((string) ($slide['desktop_image_url'] ?? ''));
                $mobile_url = esc_url((string) ($slide['mobile_image_url'] ?? ''));

                /*
                 * Compatibilidad con slides creados antes de v0.3.1:
                 * si aún no existe la URL guardada, intentamos resolverla por ID.
                 */
                if ($desktop_url === '' && $desktop_id > 0) {
                    $resolved = wp_get_attachment_image_url($desktop_id, 'full');
                    $desktop_url = $resolved ? esc_url($resolved) : '';
                }
                if ($mobile_url === '' && $mobile_id > 0) {
                    $resolved = wp_get_attachment_image_url($mobile_id, 'full');
                    $mobile_url = $resolved ? esc_url($resolved) : '';
                }
                $headline = trim((string) ($slide['headline'] ?? ''));
                $eyebrow = trim((string) ($slide['eyebrow'] ?? ''));
                $description = trim((string) ($slide['description'] ?? ''));
                $primary_label = trim((string) ($slide['primary_label'] ?? ''));
                $primary_url = trim((string) ($slide['primary_url'] ?? ''));
                $secondary_label = trim((string) ($slide['secondary_label'] ?? ''));
                $secondary_url = trim((string) ($slide['secondary_url'] ?? ''));
            ?>
                <article
                    class="tbx-home-slider__slide is-<?php echo esc_attr($surface); ?> align-<?php echo esc_attr($alignment); ?> <?php echo $index === 0 ? 'is-active' : ''; ?>"
                    data-tbx-slide
                    data-surface="<?php echo esc_attr($surface === 'photo' ? 'dark' : $surface); ?>"
                    role="group"
                    aria-roledescription="slide"
                    aria-label="<?php echo esc_attr(($index + 1) . ' de ' . $count); ?>"
                    <?php echo $index === 0 ? '' : 'hidden'; ?>
                >
                    <div class="tbx-home-slider__inner">

                        <div class="tbx-home-slider__content">
                            <?php if ($eyebrow !== '') : ?>
                                <span class="tbx-home-slider__eyebrow"><?php echo esc_html($eyebrow); ?></span>
                            <?php endif; ?>

                            <?php if ($headline !== '') : ?>
                                <h1 class="tbx-home-slider__title"><?php echo esc_html($headline); ?></h1>
                            <?php endif; ?>

                            <?php if ($description !== '') : ?>
                                <p class="tbx-home-slider__description"><?php echo esc_html($description); ?></p>
                            <?php endif; ?>

                            <?php if (($primary_label !== '' && $primary_url !== '') || ($secondary_label !== '' && $secondary_url !== '')) : ?>
                                <div class="tbx-home-slider__actions">
                                    <?php if ($primary_label !== '' && $primary_url !== '') : ?>
                                        <a class="tbx-home-slider__cta tbx-home-slider__cta--primary" href="<?php echo esc_url($primary_url); ?>">
                                            <?php echo esc_html($primary_label); ?>
                                            <span aria-hidden="true">→</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($secondary_label !== '' && $secondary_url !== '') : ?>
                                        <a class="tbx-home-slider__cta tbx-home-slider__cta--secondary" href="<?php echo esc_url($secondary_url); ?>">
                                            <?php echo esc_html($secondary_label); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($desktop_id > 0 || $mobile_id > 0 || $desktop_url !== '' || $mobile_url !== '') : ?>
                            <figure class="tbx-home-slider__media" aria-hidden="true">
                                <picture>
                                    <?php
                                    /*
                                     * Mobile: preferimos el attachment para que WordPress
                                     * pueda generar tamaño responsivo; URL directa como fallback.
                                     */
                                    if ($mobile_id > 0) {
                                        $mobile_src = wp_get_attachment_image_url($mobile_id, 'large');
                                    } else {
                                        $mobile_src = $mobile_url;
                                    }
                                    ?>
                                    <?php if ($mobile_src) : ?>
                                        <source media="(max-width: 767px)" srcset="<?php echo esc_url($mobile_src); ?>">
                                    <?php endif; ?>

                                    <?php
                                    $render_id = $desktop_id > 0 ? $desktop_id : $mobile_id;
                                    $fallback_url = $desktop_url !== '' ? $desktop_url : $mobile_url;
                                    $image_html = '';

                                    if ($render_id > 0) {
                                        $image_html = wp_get_attachment_image(
                                            $render_id,
                                            'full',
                                            false,
                                            [
                                                'class' => 'tbx-home-slider__image',
                                                'loading' => $index === 0 ? 'eager' : 'lazy',
                                                'fetchpriority' => $index === 0 ? 'high' : 'auto',
                                                'decoding' => 'async',
                                                'alt' => '',
                                            ]
                                        );
                                    }

                                    if ($image_html !== '') {
                                        echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    } elseif ($fallback_url !== '') {
                                        ?>
                                        <img
                                            class="tbx-home-slider__image"
                                            src="<?php echo esc_url($fallback_url); ?>"
                                            alt=""
                                            <?php echo $index === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"'; ?>
                                            decoding="async"
                                        >
                                        <?php
                                    }
                                    ?>
                                </picture>
                            </figure>
                        <?php endif; ?>

                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($has_multiple) : ?>
            <div class="tbx-home-slider__controls">
                <div class="tbx-home-slider__arrows">
                    <button type="button" class="tbx-home-slider__arrow" data-tbx-slider-prev aria-label="Slide anterior">←</button>
                    <button type="button" class="tbx-home-slider__arrow" data-tbx-slider-next aria-label="Siguiente slide">→</button>
                </div>

                <div class="tbx-home-slider__dots" aria-label="Seleccionar slide">
                    <?php foreach ($slides as $index => $unused) : ?>
                        <button
                            type="button"
                            class="tbx-home-slider__dot <?php echo $index === 0 ? 'is-active' : ''; ?>"
                            data-tbx-slider-dot="<?php echo esc_attr((string) $index); ?>"
                            aria-label="Ir al slide <?php echo esc_attr((string) ($index + 1)); ?>"
                            aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                        ></button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
}

/**
 * Tokens del contenido actual (página, post o catálogo individual).
 */
function tibox_theme_current_post_tokens(?WP_Post $post = null): array
{
    $post = $post instanceof WP_Post ? $post : get_post();
    if (!$post instanceof WP_Post) {
        return [];
    }

    $featured_image = get_the_post_thumbnail($post, 'large', [
        'class'   => 'tibox-template-featured-image',
        'loading' => 'eager',
    ]);

    return [
        '{{PAGE_ID}}'        => (string) $post->ID,
        '{{PAGE_TITLE}}'     => esc_html(get_the_title($post)),
        '{{PAGE_URL}}'       => esc_url(get_permalink($post)),
        '{{PAGE_EXCERPT}}'   => wp_kses_post(get_the_excerpt($post)),
        '{{PAGE_CONTENT}}'   => apply_filters('the_content', $post->post_content),
        '{{FEATURED_IMAGE}}'    => $featured_image ?: '',
        '{{HOME_HERO_SLIDER}}' => is_front_page() ? tibox_theme_home_slider_html() : '',
    ];
}

/**
 * Devuelve HTML seguro para las plataformas/canales del Catálogo.
 */
function tibox_theme_catalog_platform_chips_html(array $platforms): string
{
    if (empty($platforms)) {
        return '';
    }

    ob_start();
    ?>
    <div class="tbx-catalog-platforms" aria-label="Plataformas y canales">
        <?php foreach ($platforms as $platform) :
            if (is_string($platform)) {
                $name = trim($platform);
                $key = sanitize_title($name);
            } elseif (is_array($platform)) {
                $name = trim((string) ($platform['name'] ?? ''));
                $key = sanitize_key((string) ($platform['key'] ?? sanitize_title($name)));
            } else {
                continue;
            }

            if ($name === '') {
                continue;
            }
        ?>
            <span
                class="tbx-catalog-platform tbx-catalog-platform--<?php echo esc_attr($key); ?>"
                data-platform="<?php echo esc_attr($key); ?>"
            >
                <span class="tbx-catalog-platform__mark" aria-hidden="true"></span>
                <?php echo esc_html($name); ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

/**
 * Devuelve HTML seguro para la lista de beneficios/características.
 */
function tibox_theme_catalog_feature_list_html(array $features): string
{
    $features = array_values(array_filter(array_map(
        static fn($feature) => trim((string) $feature),
        $features
    )));

    if (empty($features)) {
        return '';
    }

    ob_start();
    ?>
    <ul class="tbx-catalog-feature-list">
        <?php foreach ($features as $feature) : ?>
            <li><?php echo esc_html($feature); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php
    return trim((string) ob_get_clean());
}

/**
 * Variables dinámicas específicas de un elemento del Catálogo.
 */
function tibox_theme_catalog_tokens(?WP_Post $post = null): array
{
    $post = $post instanceof WP_Post ? $post : get_post();
    if (!$post instanceof WP_Post) {
        return [];
    }

    $type        = (string) get_post_meta($post->ID, '_tibox_catalog_type', true);
    $summary     = (string) get_post_meta($post->ID, '_tibox_catalog_summary', true);
    $price       = (string) get_post_meta($post->ID, '_tibox_catalog_price', true);
    $badge       = (string) get_post_meta($post->ID, '_tibox_catalog_badge', true);
    $promo       = (string) get_post_meta($post->ID, '_tibox_catalog_promo', true);
    $value_prop  = (string) get_post_meta($post->ID, '_tibox_catalog_value_prop', true);
    $platforms   = get_post_meta($post->ID, '_tibox_catalog_platforms', true);
    $features    = get_post_meta($post->ID, '_tibox_catalog_features', true);
    $cta_label   = (string) get_post_meta($post->ID, '_tibox_catalog_cta_label', true);
    $cta_url     = (string) get_post_meta($post->ID, '_tibox_catalog_cta_url', true);

    if (!is_array($platforms)) {
        $platforms = [];
    }

    if (!is_array($features)) {
        $features = [];
    }

    $terms = wp_get_object_terms($post->ID, 'tibox_catalog_cat', ['fields' => 'names']);
    if (is_wp_error($terms)) {
        $terms = [];
    }

    $category_text = implode(' · ', array_map('strval', (array) $terms));

    return [
        '{{CATALOG_TYPE}}'              => esc_html($type !== '' ? ucfirst(str_replace('-', ' ', $type)) : ''),
        '{{CATALOG_SUMMARY}}'           => esc_html($summary),
        '{{CATALOG_PRICE}}'             => esc_html($price),
        '{{CATALOG_BADGE}}'             => esc_html($badge),
        '{{CATALOG_PROMO}}'             => esc_html($promo),
        '{{CATALOG_VALUE_PROPOSITION}}' => esc_html($value_prop),
        '{{CATALOG_PLATFORM_CHIPS}}'    => tibox_theme_catalog_platform_chips_html($platforms),
        '{{CATALOG_FEATURE_LIST}}'      => tibox_theme_catalog_feature_list_html($features),
        '{{CATALOG_CATEGORIES}}'        => esc_html($category_text),
        '{{CTA_LABEL}}'                 => esc_html($cta_label),
        '{{CTA_URL}}'                   => esc_url($cta_url),
    ];
}

/**
 * Genera las cards del loop actual para poder insertarlas en plantillas HTML.
 */
function tibox_theme_capture_loop(string $template_part = ''): string
{
    ob_start();

    if (have_posts()) {
        while (have_posts()) {
            the_post();
            if ($template_part !== '') {
                get_template_part($template_part);
            } else {
                ?>
                <article <?php post_class('tibox-content-card'); ?>>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_excerpt(); ?>
                </article>
                <?php
            }
        }
    }

    return (string) ob_get_clean();
}

function tibox_theme_pagination_html(): string
{
    ob_start();
    the_posts_pagination();
    return (string) ob_get_clean();
}

function tibox_theme_archive_tokens(string $items_html = ''): array
{
    return [
        '{{ARCHIVE_TITLE}}'       => wp_kses_post(get_the_archive_title()),
        '{{ARCHIVE_DESCRIPTION}}' => wp_kses_post(get_the_archive_description()),
        '{{ARCHIVE_ITEMS}}'       => $items_html,
        '{{PAGINATION}}'          => tibox_theme_pagination_html(),
    ];
}

/**
 * Imprime una plantilla HTML guardada en el admin dentro del theme normal.
 */
function tibox_theme_render_saved_template(string $key, array $tokens = []): bool
{
    $html = tibox_theme_get_template_html($key);
    if ($html === '') {
        return false;
    }

    echo tibox_theme_replace_tokens($html, $tokens); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return true;
}

/**
 * Design Packages ZIP.
 */
function tibox_theme_design_package_id(string $target, int $object_id = 0): int
{
    if (!function_exists('tibox_design_get_package_for_target')) {
        return 0;
    }

    return absint(tibox_design_get_package_for_target($target, $object_id));
}

function tibox_theme_render_design_package(
    string $target,
    array $tokens = [],
    int $object_id = 0
): bool {
    if (!function_exists('tibox_design_render_package')) {
        return false;
    }

    $package_id = tibox_theme_design_package_id($target, $object_id);
    if ($package_id <= 0) {
        return false;
    }

    $html = tibox_design_render_package(
        $package_id,
        array_merge(tibox_theme_global_tokens(), $tokens)
    );

    if (trim($html) === '') {
        return false;
    }

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return true;
}

function tibox_theme_has_design_package(string $target, int $object_id = 0): bool
{
    return tibox_theme_design_package_id($target, $object_id) > 0;
}

/**
 * Imprime un bloque CSS guardado en WordPress.
 */
function tibox_theme_print_css_block(string $key, string $id): void
{
    $settings = tibox_theme_get_settings();
    $css = isset($settings[$key]) ? trim((string) $settings[$key]) : '';

    if ($css === '') {
        return;
    }

    echo '<style id="' . esc_attr($id) . '">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Carga solo el CSS que corresponde a la vista actual.
 *
 * Siempre:
 * - Base/global.
 * - Header.
 * - Footer.
 *
 * Condicional:
 * - Inicio.
 * - Página.
 * - Single catálogo.
 * - Archivo catálogo.
 * - Single general.
 * - Archivo general.
 * - 404.
 */
function tibox_theme_print_scoped_css(): void
{
    tibox_theme_print_css_block('global_css', 'tibox-css-base');
    tibox_theme_print_css_block('header_css', 'tibox-css-header');
    tibox_theme_print_css_block('footer_css', 'tibox-css-footer');

    if (is_404()) {
        tibox_theme_print_css_block('404_css', 'tibox-css-404');
        return;
    }

    if (is_front_page()) {
        if (!tibox_theme_has_design_package('home', (int) get_queried_object_id())) {
            tibox_theme_print_css_block('home_css', 'tibox-css-home');
        }
        tibox_theme_print_css_block('home_slider_css', 'tibox-css-home-slider-custom');
        return;
    }

    if (is_singular('tibox_catalog_item')) {
        if (!tibox_theme_has_design_package('catalog_single', (int) get_queried_object_id())) {
            tibox_theme_print_css_block('catalog_single_css', 'tibox-css-catalog-single');
        }
        return;
    }

    if (is_post_type_archive('tibox_catalog_item') || is_tax('tibox_catalog_cat')) {
        if (!tibox_theme_has_design_package('catalog_archive')) {
            tibox_theme_print_css_block('catalog_archive_css', 'tibox-css-catalog-archive');
        }
        return;
    }

    if (is_page()) {
        if (!tibox_theme_has_design_package('page', (int) get_queried_object_id())) {
            tibox_theme_print_css_block('page_css', 'tibox-css-page');
        }
        return;
    }

    if (is_singular()) {
        if (!tibox_theme_has_design_package('single', (int) get_queried_object_id())) {
            tibox_theme_print_css_block('single_css', 'tibox-css-single');
        }
        return;
    }

    if (is_archive() || is_home() || is_search()) {
        if (!tibox_theme_has_design_package('archive')) {
            tibox_theme_print_css_block('archive_css', 'tibox-css-archive');
        }
    }
}
add_action('wp_head', 'tibox_theme_print_scoped_css', 99);

function tibox_theme_print_global_js(): void
{
    $settings = tibox_theme_get_settings();
    if (trim((string) $settings['global_js']) === '') {
        return;
    }

    echo '<script id="tibox-global-js">' . $settings['global_js'] . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('wp_footer', 'tibox_theme_print_global_js', 99);

function tibox_theme_admin_menu(): void
{
    add_theme_page(
        'TIBOX Theme',
        'TIBOX Theme',
        'manage_options',
        'tibox-theme',
        'tibox_theme_settings_page'
    );
}
add_action('admin_menu', 'tibox_theme_admin_menu');

function tibox_theme_save_settings(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para realizar esta acción.', 'tibox-theme'));
    }

    check_admin_referer('tibox_theme_save', 'tibox_theme_nonce');

    $fields = [
        'header_html',
        'footer_html',
        'global_css',
        'header_css',
        'footer_css',
        'home_css',
        'home_slider_css',
        'page_css',
        'single_css',
        'archive_css',
        'catalog_single_css',
        'catalog_archive_css',
        '404_css',
        'global_js',
        'template_home_html',
        'template_page_html',
        'template_single_html',
        'template_archive_html',
        'template_catalog_single_html',
        'template_catalog_archive_html',
        'template_404_html',
    ];

    $settings = [];
    foreach ($fields as $field) {
        $settings[$field] = isset($_POST[$field])
            ? tibox_theme_strip_php(wp_unslash($_POST[$field]))
            : '';
    }

    update_option(TIBOX_THEME_OPTION, $settings, false);

    $tab = isset($_POST['tibox_theme_active_tab'])
        ? sanitize_key(wp_unslash($_POST['tibox_theme_active_tab']))
        : 'global';

    wp_safe_redirect(add_query_arg([
        'page'    => 'tibox-theme',
        'updated' => '1',
        'tab'     => $tab,
    ], admin_url('themes.php')));
    exit;
}
add_action('admin_post_tibox_theme_save', 'tibox_theme_save_settings');

function tibox_theme_admin_assets(string $hook): void
{
    if ($hook !== 'appearance_page_tibox-theme') {
        return;
    }

    wp_enqueue_style('dashicons');

    $html_editor = wp_enqueue_code_editor(['type' => 'text/html']);
    $css_editor  = wp_enqueue_code_editor(['type' => 'text/css']);
    $js_editor   = wp_enqueue_code_editor(['type' => 'text/javascript']);

    wp_enqueue_script('wp-theme-plugin-editor');
    wp_enqueue_style('wp-codemirror');

    $config = [
        'html' => is_array($html_editor) ? $html_editor : false,
        'css'  => is_array($css_editor) ? $css_editor : false,
        'js'   => is_array($js_editor) ? $js_editor : false,
    ];

    wp_add_inline_script(
        'wp-theme-plugin-editor',
        'window.TiboxThemeEditorConfig = ' . wp_json_encode($config) . ';',
        'before'
    );

    $css = <<<'CSS'
.tibox-admin-shell{max-width:1480px;margin-top:18px}.tibox-admin-intro{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:18px}.tibox-admin-intro p{max-width:850px}.tibox-admin-tabs{display:flex;gap:4px;margin:0 0 20px;border-bottom:1px solid #c3c4c7}.tibox-admin-tab{appearance:none;border:0;border-bottom:3px solid transparent;background:transparent;padding:12px 18px;margin-bottom:-1px;font-size:14px;font-weight:600;color:#50575e;cursor:pointer}.tibox-admin-tab:hover{color:#135e96}.tibox-admin-tab.is-active{color:#1d2327;border-bottom-color:#2271b1;background:#fff}.tibox-admin-panel{display:none}.tibox-admin-panel.is-active{display:block}.tibox-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;margin:0 0 18px;overflow:hidden}.tibox-admin-card__head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:15px 18px;border-bottom:1px solid #e2e4e7;background:#f9f9f9}.tibox-admin-card__head h2{margin:0;font-size:15px}.tibox-admin-card__head p{margin:2px 0 0;color:#646970}.tibox-admin-card__body{padding:0}.tibox-token-bar{display:flex;flex-wrap:wrap;gap:6px;padding:10px 14px;border-bottom:1px solid #2f3542;background:#20242d;color:#d7dae0}.tibox-token-bar code{cursor:pointer;background:#343b47;color:#8be9fd;border:1px solid #454d5b;border-radius:4px;padding:3px 7px}.tibox-token-bar code:hover{background:#404957}.tibox-codearea{width:100%;min-height:420px;border:0!important;border-radius:0!important;margin:0!important;font-family:Consolas,Monaco,"Andale Mono",monospace!important;font-size:13px!important}.tibox-admin-card--short .tibox-codearea{min-height:320px}.tibox-template-tabs{display:flex;flex-wrap:wrap;gap:8px;padding:14px 14px 0;background:#fff}.tibox-template-tab{border:1px solid #dcdcde;background:#f6f7f7;border-radius:6px;padding:8px 12px;cursor:pointer;font-weight:600;color:#50575e}.tibox-template-tab.is-active{background:#2271b1;border-color:#2271b1;color:#fff}.tibox-template-panel{display:none;padding-top:14px}.tibox-template-panel.is-active{display:block}.tibox-code-tabs{display:flex;flex-wrap:wrap;gap:8px;padding:14px;background:#fff;border:1px solid #dcdcde;border-radius:8px;margin-bottom:14px}.tibox-code-tab{border:1px solid #dcdcde;background:#f6f7f7;border-radius:6px;padding:8px 12px;cursor:pointer;font-weight:600;color:#50575e}.tibox-code-tab.is-active{background:#1d2327;border-color:#1d2327;color:#fff}.tibox-code-panel{display:none}.tibox-code-panel.is-active{display:block}.tibox-template-panel .tibox-admin-card{border-left:0;border-right:0;border-bottom:0;border-radius:0;margin:0}.tibox-template-help{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}.tibox-help-box{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.tibox-help-box h3{margin-top:0}.tibox-help-box code{word-break:break-word}.tibox-savebar{position:sticky;bottom:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px;padding:12px 16px;background:rgba(255,255,255,.96);border:1px solid #c3c4c7;border-radius:8px;box-shadow:0 -4px 18px rgba(0,0,0,.06);backdrop-filter:blur(8px)}.tibox-savebar .submit{margin:0;padding:0}.tibox-editor-note{font-size:12px;color:#646970}.CodeMirror{height:460px!important;background:#20242d!important;color:#f8f8f2!important;font-family:Consolas,Monaco,"Andale Mono",monospace!important;font-size:13px!important;line-height:1.55}.CodeMirror-gutters{background:#1b1f27!important;border-right:1px solid #343b47!important}.CodeMirror-linenumber{color:#6c7480!important}.CodeMirror-cursor{border-left-color:#f8f8f2!important}.CodeMirror-selected{background:#3e4451!important}.cm-s-default .cm-tag,.cm-s-default .cm-keyword{color:#ff79c6!important}.cm-s-default .cm-attribute,.cm-s-default .cm-property{color:#50fa7b!important}.cm-s-default .cm-string,.cm-s-default .cm-string-2{color:#f1fa8c!important}.cm-s-default .cm-number,.cm-s-default .cm-atom{color:#bd93f9!important}.cm-s-default .cm-def,.cm-s-default .cm-variable,.cm-s-default .cm-variable-2{color:#8be9fd!important}.cm-s-default .cm-comment{color:#6272a4!important;font-style:italic}.cm-s-default .cm-qualifier,.cm-s-default .cm-builtin{color:#ffb86c!important}.cm-s-default .cm-bracket{color:#f8f8f2!important}@media(max-width:900px){.tibox-admin-intro{display:block}.tibox-template-help{grid-template-columns:1fr}.tibox-admin-tabs{overflow:auto}.tibox-admin-tab{white-space:nowrap}}
CSS;
    wp_add_inline_style('wp-admin', $css);

    $js = <<<'JS'
(function(){
    function initCodeEditor(textarea){
        if(!textarea || !window.wp || !wp.codeEditor){ return; }
        var type = textarea.dataset.editorType || 'html';
        var cfg = window.TiboxThemeEditorConfig ? window.TiboxThemeEditorConfig[type] : false;
        if(!cfg){ return; }
        var instance = wp.codeEditor.initialize(textarea, cfg);
        if(instance && instance.codemirror){
            instance.codemirror.setOption('lineNumbers', true);
            instance.codemirror.setOption('lineWrapping', true);
            instance.codemirror.setOption('indentUnit', 2);
            instance.codemirror.setOption('tabSize', 2);
        }
    }
    function activateTab(tab){
        document.querySelectorAll('.tibox-admin-tab').forEach(function(btn){ btn.classList.toggle('is-active', btn.dataset.tab === tab); });
        document.querySelectorAll('.tibox-admin-panel').forEach(function(panel){ panel.classList.toggle('is-active', panel.dataset.panel === tab); });
        var hidden = document.getElementById('tibox-theme-active-tab');
        if(hidden){ hidden.value = tab; }
        if(window.history && history.replaceState){
            var url = new URL(window.location.href); url.searchParams.set('tab', tab); history.replaceState({}, '', url.toString());
        }
        setTimeout(function(){ document.querySelectorAll('.CodeMirror').forEach(function(cm){ if(cm.CodeMirror){ cm.CodeMirror.refresh(); } }); }, 30);
    }
    function activateTemplate(name){
        document.querySelectorAll('.tibox-template-tab').forEach(function(btn){ btn.classList.toggle('is-active', btn.dataset.template === name); });
        document.querySelectorAll('.tibox-template-panel').forEach(function(panel){ panel.classList.toggle('is-active', panel.dataset.templatePanel === name); });
        setTimeout(function(){ document.querySelectorAll('.CodeMirror').forEach(function(cm){ if(cm.CodeMirror){ cm.CodeMirror.refresh(); } }); }, 30);
    }
    function activateCodePanel(name){
        document.querySelectorAll('.tibox-code-tab').forEach(function(btn){ btn.classList.toggle('is-active', btn.dataset.code === name); });
        document.querySelectorAll('.tibox-code-panel').forEach(function(panel){ panel.classList.toggle('is-active', panel.dataset.codePanel === name); });
        setTimeout(function(){ document.querySelectorAll('.CodeMirror').forEach(function(cm){ if(cm.CodeMirror){ cm.CodeMirror.refresh(); } }); }, 30);
    }
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('textarea.tibox-codearea').forEach(initCodeEditor);
        document.querySelectorAll('.tibox-admin-tab').forEach(function(btn){ btn.addEventListener('click', function(){ activateTab(btn.dataset.tab); }); });
        document.querySelectorAll('.tibox-template-tab').forEach(function(btn){ btn.addEventListener('click', function(){ activateTemplate(btn.dataset.template); }); });
        document.querySelectorAll('.tibox-code-tab').forEach(function(btn){ btn.addEventListener('click', function(){ activateCodePanel(btn.dataset.code); }); });
        document.querySelectorAll('.tibox-token-bar code').forEach(function(token){ token.addEventListener('click', function(){
            var card = token.closest('.tibox-admin-card');
            var textarea = card ? card.querySelector('textarea') : null;
            if(!textarea){ return; }
            var wrapper = textarea.nextElementSibling;
            var cm = wrapper && wrapper.CodeMirror ? wrapper.CodeMirror : null;
            if(cm){ cm.replaceSelection(token.textContent); cm.focus(); }
            else { var start=textarea.selectionStart||0,end=textarea.selectionEnd||0; textarea.value=textarea.value.slice(0,start)+token.textContent+textarea.value.slice(end); textarea.focus(); }
        }); });
        var initial = document.querySelector('.tibox-admin-tab.is-active');
        if(initial){ activateTab(initial.dataset.tab); }
        var tInitial = document.querySelector('.tibox-template-tab.is-active');
        if(tInitial){ activateTemplate(tInitial.dataset.template); }
        var cInitial = document.querySelector('.tibox-code-tab.is-active');
        if(cInitial){ activateCodePanel(cInitial.dataset.code); }
    });
})();
JS;
    wp_add_inline_script('wp-theme-plugin-editor', $js, 'after');
}
add_action('admin_enqueue_scripts', 'tibox_theme_admin_assets');

function tibox_theme_editor_card(
    string $field,
    string $title,
    string $description,
    string $value,
    string $type = 'html',
    array $tokens = [],
    bool $short = false
): void {
    ?>
    <section class="tibox-admin-card<?php echo $short ? ' tibox-admin-card--short' : ''; ?>">
        <div class="tibox-admin-card__head">
            <div>
                <h2><?php echo esc_html($title); ?></h2>
                <?php if ($description !== '') : ?><p><?php echo esc_html($description); ?></p><?php endif; ?>
            </div>
        </div>
        <?php if (!empty($tokens)) : ?>
            <div class="tibox-token-bar">
                <span>Variables:</span>
                <?php foreach ($tokens as $token) : ?><code title="Clic para insertar"><?php echo esc_html($token); ?></code><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="tibox-admin-card__body">
            <textarea
                id="<?php echo esc_attr($field); ?>"
                name="<?php echo esc_attr($field); ?>"
                class="tibox-codearea large-text code"
                data-editor-type="<?php echo esc_attr($type); ?>"
                spellcheck="false"
            ><?php echo esc_textarea($value); ?></textarea>
        </div>
    </section>
    <?php
}

function tibox_theme_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = tibox_theme_get_settings();
    $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'global';
    if (!in_array($active_tab, ['global', 'templates', 'code', 'help'], true)) {
        $active_tab = 'global';
    }

    $global_tokens = ['{{SITE_URL}}', '{{HOME_URL}}', '{{THEME_URL}}', '{{SITE_NAME}}', '{{CURRENT_YEAR}}', '{{CUSTOM_LOGO}}', '{{MENU_PRIMARY}}', '{{MENU_FOOTER}}'];
    $content_tokens = array_merge($global_tokens, ['{{PAGE_TITLE}}', '{{PAGE_URL}}', '{{PAGE_EXCERPT}}', '{{PAGE_CONTENT}}', '{{FEATURED_IMAGE}}']);
    $home_tokens = array_merge($content_tokens, ['{{HOME_HERO_SLIDER}}']);
    $catalog_tokens = array_merge($content_tokens, [
        '{{CATALOG_TYPE}}',
        '{{CATALOG_SUMMARY}}',
        '{{CATALOG_PRICE}}',
        '{{CATALOG_BADGE}}',
        '{{CATALOG_PROMO}}',
        '{{CATALOG_VALUE_PROPOSITION}}',
        '{{CATALOG_PLATFORM_CHIPS}}',
        '{{CATALOG_FEATURE_LIST}}',
        '{{CATALOG_CATEGORIES}}',
        '{{CTA_LABEL}}',
        '{{CTA_URL}}',
    ]);
    $archive_tokens = array_merge($global_tokens, ['{{ARCHIVE_TITLE}}', '{{ARCHIVE_DESCRIPTION}}', '{{ARCHIVE_ITEMS}}', '{{PAGINATION}}']);

    $front_page_id = (int) get_option('page_on_front');
    $front_page_title = $front_page_id > 0 ? get_the_title($front_page_id) : '';
    ?>
    <div class="wrap tibox-admin-shell">
        <div class="tibox-admin-intro">
            <div>
                <h1>TIBOX Theme <small style="font-size:13px;color:#646970;">v<?php echo esc_html(TIBOX_THEME_VERSION); ?></small></h1>
                <p>Editor visual del theme sin Elementor. El código sigue siendo HTML/CSS/JavaScript; WordPress administra el contenido y las variables dinámicas.</p>
            </div>
            <?php if ($front_page_title !== '') : ?>
                <p><strong>Inicio actual:</strong> <a href="<?php echo esc_url(get_edit_post_link($front_page_id)); ?>"><?php echo esc_html($front_page_title); ?></a></p>
            <?php else : ?>
                <p><strong>Inicio:</strong> todavía no hay una página estática asignada.</p>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>
        <?php endif; ?>

        <div class="notice notice-warning inline">
            <p><strong>Solo administradores.</strong> Los editores aceptan HTML/CSS/JavaScript sin filtrar. No pegues PHP, API keys, tokens ni credenciales.</p>
        </div>

        <nav class="tibox-admin-tabs" aria-label="Secciones de TIBOX Theme">
            <?php
            $tabs = [
                'global'    => 'Diseño global',
                'templates' => 'Plantillas',
                'code'      => 'CSS / JavaScript',
                'help'      => 'Ayuda',
            ];
            foreach ($tabs as $tab_key => $tab_label) :
            ?>
                <button type="button" class="tibox-admin-tab <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>" data-tab="<?php echo esc_attr($tab_key); ?>"><?php echo esc_html($tab_label); ?></button>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="tibox_theme_save">
            <input type="hidden" id="tibox-theme-active-tab" name="tibox_theme_active_tab" value="<?php echo esc_attr($active_tab); ?>">
            <?php wp_nonce_field('tibox_theme_save', 'tibox_theme_nonce'); ?>

            <div class="tibox-admin-panel <?php echo $active_tab === 'global' ? 'is-active' : ''; ?>" data-panel="global">
                <?php
                tibox_theme_editor_card('header_html', 'Header global', 'Se imprime en todo el sitio mediante header.php.', (string) $settings['header_html'], 'html', $global_tokens);
                tibox_theme_editor_card('footer_html', 'Footer global', 'Se imprime en todo el sitio mediante footer.php.', (string) $settings['footer_html'], 'html', $global_tokens);
                ?>
            </div>

            <div class="tibox-admin-panel <?php echo $active_tab === 'templates' ? 'is-active' : ''; ?>" data-panel="templates">
                <div class="tibox-template-tabs">
                    <button type="button" class="tibox-template-tab is-active" data-template="home">Inicio</button>
                    <button type="button" class="tibox-template-tab" data-template="page">Página</button>
                    <button type="button" class="tibox-template-tab" data-template="catalog-single">Single catálogo</button>
                    <button type="button" class="tibox-template-tab" data-template="catalog-archive">Archivo catálogo</button>
                    <button type="button" class="tibox-template-tab" data-template="single">Single general</button>
                    <button type="button" class="tibox-template-tab" data-template="archive">Archivo general</button>
                    <button type="button" class="tibox-template-tab" data-template="404">404</button>
                </div>

                <div class="tibox-template-panel is-active" data-template-panel="home">
                    <?php tibox_theme_editor_card('template_home_html', 'Plantilla de Inicio', 'Usa {{HOME_HERO_SLIDER}} para insertar el Slider Home administrado por TIBOX Core. Si la plantilla queda vacía, Inicio usa el contenido normal de la página asignada como portada.', (string) $settings['template_home_html'], 'html', $home_tokens); ?>
                </div>
                <div class="tibox-template-panel" data-template-panel="page">
                    <?php tibox_theme_editor_card('template_page_html', 'Plantilla de Página', 'Diseño global para páginas normales. {{PAGE_CONTENT}} inserta el contenido de WordPress.', (string) $settings['template_page_html'], 'html', $content_tokens); ?>
                </div>
                <div class="tibox-template-panel" data-template-panel="catalog-single">
                    <?php tibox_theme_editor_card('template_catalog_single_html', 'Single de Catálogo', 'Diseño global para cada producto, servicio, plan, aplicación o solución del Catálogo.', (string) $settings['template_catalog_single_html'], 'html', $catalog_tokens); ?>
                </div>
                <div class="tibox-template-panel" data-template-panel="catalog-archive">
                    <?php tibox_theme_editor_card('template_catalog_archive_html', 'Archivo de Catálogo', 'Diseño de /catalogo/. {{ARCHIVE_ITEMS}} inserta las cards generadas por WordPress.', (string) $settings['template_catalog_archive_html'], 'html', $archive_tokens); ?>
                </div>
                <div class="tibox-template-panel" data-template-panel="single">
                    <?php tibox_theme_editor_card('template_single_html', 'Single general', 'Plantilla para entradas/contenidos individuales que no pertenecen al Catálogo.', (string) $settings['template_single_html'], 'html', $content_tokens); ?>
                </div>
                <div class="tibox-template-panel" data-template-panel="archive">
                    <?php tibox_theme_editor_card('template_archive_html', 'Archivo general', 'Plantilla para archivos generales. {{ARCHIVE_ITEMS}} inserta el loop.', (string) $settings['template_archive_html'], 'html', $archive_tokens); ?>
                </div>
                <div class="tibox-template-panel" data-template-panel="404">
                    <?php tibox_theme_editor_card('template_404_html', 'Página 404', 'Diseño de página no encontrada.', (string) $settings['template_404_html'], 'html', $global_tokens); ?>
                </div>
            </div>

            <div class="tibox-admin-panel <?php echo $active_tab === 'code' ? 'is-active' : ''; ?>" data-panel="code">

                <div class="notice notice-info inline" style="margin:0 0 14px;">
                    <p>
                        <strong>CSS separado por responsabilidad.</strong>
                        Base, Header y Footer se cargan donde corresponde. Los estilos de Inicio, Página, Catálogo, etc. se imprimen solamente en esas vistas.
                        El antiguo <strong>CSS global</strong> se conserva como <strong>Base / global</strong> para no romper el sitio al actualizar.
                    </p>
                </div>

                <div class="tibox-code-tabs" aria-label="Bloques de CSS y JavaScript">
                    <button type="button" class="tibox-code-tab is-active" data-code="base">Base / global</button>
                    <button type="button" class="tibox-code-tab" data-code="header">Header</button>
                    <button type="button" class="tibox-code-tab" data-code="footer">Footer</button>
                    <button type="button" class="tibox-code-tab" data-code="home">Inicio</button>
                    <button type="button" class="tibox-code-tab" data-code="home-slider">Slider Home</button>
                    <button type="button" class="tibox-code-tab" data-code="page">Página</button>
                    <button type="button" class="tibox-code-tab" data-code="catalog-single">Single catálogo</button>
                    <button type="button" class="tibox-code-tab" data-code="catalog-archive">Archivo catálogo</button>
                    <button type="button" class="tibox-code-tab" data-code="single">Single general</button>
                    <button type="button" class="tibox-code-tab" data-code="archive">Archivo general</button>
                    <button type="button" class="tibox-code-tab" data-code="404">404</button>
                    <button type="button" class="tibox-code-tab" data-code="js">JavaScript</button>
                </div>

                <div class="tibox-code-panel is-active" data-code-panel="base">
                    <?php tibox_theme_editor_card(
                        'global_css',
                        'CSS base / global',
                        'Tokens, reset, tipografía, botones y componentes realmente compartidos por todo el sitio. Aquí permanece automáticamente el CSS que ya tenías en versiones anteriores.',
                        (string) $settings['global_css'],
                        'css',
                        [],
                        false
                    ); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="header">
                    <?php tibox_theme_editor_card('header_css', 'CSS del Header', 'Se carga en todo el sitio junto al Header global.', (string) $settings['header_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="footer">
                    <?php tibox_theme_editor_card('footer_css', 'CSS del Footer', 'Se carga en todo el sitio junto al Footer global.', (string) $settings['footer_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="home">
                    <?php tibox_theme_editor_card('home_css', 'CSS de Inicio', 'Solo se imprime en la portada definida en Ajustes → Lectura.', (string) $settings['home_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="home-slider">
                    <?php tibox_theme_editor_card(
                        'home_slider_css',
                        'CSS del Slider Home',
                        'Solo se imprime en Inicio y después del CSS estructural del slider. Úsalo para rediseñar el hero sin tocar el CSS del resto de la portada.',
                        (string) $settings['home_slider_css'],
                        'css',
                        [],
                        false
                    ); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="page">
                    <?php tibox_theme_editor_card('page_css', 'CSS de Página', 'Solo se imprime en páginas normales. Inicio utiliza su bloque propio.', (string) $settings['page_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="catalog-single">
                    <?php tibox_theme_editor_card('catalog_single_css', 'CSS de Single Catálogo', 'Solo se imprime en fichas individuales del Catálogo.', (string) $settings['catalog_single_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="catalog-archive">
                    <?php tibox_theme_editor_card('catalog_archive_css', 'CSS de Archivo Catálogo', 'Solo se imprime en /catalogo/ y categorías del catálogo.', (string) $settings['catalog_archive_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="single">
                    <?php tibox_theme_editor_card('single_css', 'CSS de Single general', 'Para entradas y otros contenidos individuales que no sean Catálogo.', (string) $settings['single_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="archive">
                    <?php tibox_theme_editor_card('archive_css', 'CSS de Archivo general', 'Para archivos, blog, categorías generales y búsqueda.', (string) $settings['archive_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="404">
                    <?php tibox_theme_editor_card('404_css', 'CSS de 404', 'Solo se imprime cuando WordPress muestra una página no encontrada.', (string) $settings['404_css'], 'css', [], false); ?>
                </div>

                <div class="tibox-code-panel" data-code-panel="js">
                    <?php tibox_theme_editor_card('global_js', 'JavaScript global', 'Se carga al final del documento mediante wp_footer(). Por ahora sigue siendo un único bloque.', (string) $settings['global_js'], 'js', [], true); ?>
                </div>

            </div>

            <div class="tibox-admin-panel <?php echo $active_tab === 'help' ? 'is-active' : ''; ?>" data-panel="help">
                <div class="tibox-template-help">
                    <div class="tibox-help-box">
                        <h3>¿Dónde edito Inicio?</h3>
                        <p>El contenido vive en <strong>Páginas → Inicio</strong>. Para convertirla en portada: <strong>Ajustes → Lectura → Una página estática</strong>.</p>
                        <p>La pestaña <strong>Plantillas → Inicio</strong> controla su estructura visual global.</p>
                    </div>
                    <div class="tibox-help-box">
                        <h3>Plantilla vs contenido</h3>
                        <p>La plantilla define el diseño. El contenido sigue viviendo en WordPress.</p>
                        <p>Usa <code>{{PAGE_CONTENT}}</code> dentro de la plantilla para decidir dónde aparece.</p>
                    </div>
                    <div class="tibox-help-box">
                        <h3>Claude Design</h3>
                        <p>Puedes generar HTML con IA y pegarlo aquí. Reemplaza las partes dinámicas por variables como <code>{{PAGE_TITLE}}</code>, <code>{{HOME_HERO_SLIDER}}</code>, <code>{{CATALOG_PRICE}}</code> o <code>{{MENU_PRIMARY}}</code>.</p>
                    </div>
                </div>
                <div class="tibox-help-box">
                    <h3>Archivos reales del theme</h3>
                    <p><code>front-page.php</code>, <code>page.php</code>, <code>single.php</code>, <code>archive.php</code>, <code>single-tibox_catalog_item.php</code>, <code>archive-tibox_catalog_item.php</code> y <code>404.php</code> siguen existiendo como motor y fallback. No necesitas editarlos para cambiar el diseño si utilizas las plantillas del panel.</p>
                </div>
            </div>

            <div class="tibox-savebar">
                <span class="tibox-editor-note">Los cambios se aplican al guardar. Si una plantilla está vacía, se usa el PHP predeterminado del theme.</span>
                <?php submit_button('Guardar TIBOX Theme', 'primary', 'submit', false); ?>
            </div>
        </form>
    </div>
    <?php
}

function tibox_theme_body_classes(array $classes): array
{
    $classes[] = 'tibox-theme';
    return $classes;
}
add_filter('body_class', 'tibox_theme_body_classes');

/**
 * -----------------------------------------------------------------------------
 * Guía interna para Claude Design / IA
 * -----------------------------------------------------------------------------
 * Página de documentación para que diseño/desarrollo entienda el contrato del
 * theme sin tener que abrir los archivos PHP.
 */
function tibox_theme_claude_guide_menu(): void
{
    add_theme_page(
        'TIBOX · Guía Claude Design',
        'Guía Claude Design',
        'manage_options',
        'tibox-claude-guide',
        'tibox_theme_claude_guide_page'
    );
}
add_action('admin_menu', 'tibox_theme_claude_guide_menu', 61);

function tibox_theme_claude_guide_assets(string $hook): void
{
    if ($hook !== 'appearance_page_tibox-claude-guide') {
        return;
    }

    wp_enqueue_style('dashicons');
    wp_enqueue_script('wp-dom-ready');

    $css = <<<'CSS'
.tbx-guide{max-width:1480px;margin-top:18px}.tbx-guide *{box-sizing:border-box}.tbx-guide__hero{display:flex;justify-content:space-between;gap:30px;align-items:flex-start;padding:24px 26px;background:#101522;color:#f5f7ff;border-radius:12px;margin-bottom:18px;border:1px solid #252d3c}.tbx-guide__hero h1{color:#fff;margin:0 0 8px;font-size:26px}.tbx-guide__hero p{color:#aab5ce;max-width:850px;font-size:14px;line-height:1.6;margin:0}.tbx-guide__version{white-space:nowrap;font:600 12px/1.2 Consolas,Monaco,monospace;color:#8be9fd;border:1px solid #354158;background:#1d2431;padding:8px 10px;border-radius:999px}.tbx-guide__nav{position:sticky;top:32px;z-index:20;display:flex;gap:5px;overflow-x:auto;padding:8px;background:rgba(240,240,241,.96);backdrop-filter:blur(10px);border:1px solid #c3c4c7;border-radius:9px;margin-bottom:18px}.tbx-guide__nav a{display:inline-flex;align-items:center;min-height:36px;padding:0 12px;border-radius:6px;color:#3c434a;text-decoration:none;font-weight:600;white-space:nowrap}.tbx-guide__nav a:hover{background:#fff;color:#135e96}.tbx-guide__section{scroll-margin-top:95px;margin-bottom:22px}.tbx-guide__section>h2{font-size:20px;margin:0 0 12px}.tbx-guide__section>p{max-width:1000px;color:#50575e;line-height:1.65}.tbx-guide__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.tbx-guide__grid--2{grid-template-columns:repeat(2,minmax(0,1fr))}.tbx-guide__card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:18px}.tbx-guide__card h3{margin:0 0 7px;font-size:15px}.tbx-guide__card p{margin:0;color:#646970;line-height:1.55}.tbx-guide__card code{word-break:break-word}.tbx-guide__status{display:flex;align-items:center;gap:8px;margin-top:11px;font-weight:600}.tbx-guide__dot{width:8px;height:8px;border-radius:50%;background:#d63638}.tbx-guide__dot.is-ok{background:#00a32a}.tbx-guide__architecture{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;align-items:stretch}.tbx-guide__architecture-card{position:relative;display:flex;flex-direction:column;justify-content:center;min-height:130px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:9px}.tbx-guide__architecture-card:not(:last-child)::after{content:'→';position:absolute;right:-17px;top:50%;transform:translateY(-50%);z-index:2;color:#2271b1;font-size:20px;font-weight:700}.tbx-guide__architecture-card span{font:600 11px/1 Consolas,Monaco,monospace;color:#2271b1;text-transform:uppercase;letter-spacing:.08em}.tbx-guide__architecture-card strong{display:block;margin:9px 0 5px;font-size:15px}.tbx-guide__architecture-card small{color:#646970;line-height:1.4}.tbx-guide__table-wrap{overflow:auto;background:#fff;border:1px solid #dcdcde;border-radius:9px}.tbx-guide table{width:100%;border-collapse:collapse}.tbx-guide th,.tbx-guide td{text-align:left;vertical-align:top;padding:12px 14px;border-bottom:1px solid #e2e4e7}.tbx-guide th{background:#f6f7f7;font-size:12px}.tbx-guide tr:last-child td{border-bottom:0}.tbx-guide td code{font-size:12px}.tbx-guide__token-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.tbx-guide__tokens{background:#20242d;border:1px solid #353d4b;border-radius:9px;overflow:hidden}.tbx-guide__tokens h3{color:#f7f7f7;margin:0;padding:14px 16px;border-bottom:1px solid #353d4b;font-size:14px}.tbx-guide__token-list{display:flex;flex-wrap:wrap;gap:7px;padding:14px}.tbx-guide__token-list code{background:#343b47;color:#8be9fd;border:1px solid #454d5b;border-radius:5px;padding:5px 8px}.tbx-guide__rule{display:flex;gap:11px;padding:13px 14px;border-bottom:1px solid #e2e4e7}.tbx-guide__rule:last-child{border-bottom:0}.tbx-guide__rule-icon{font-weight:700;width:18px;flex:0 0 18px}.tbx-guide__rule.is-do .tbx-guide__rule-icon{color:#008a20}.tbx-guide__rule.is-dont .tbx-guide__rule-icon{color:#d63638}.tbx-guide__prompt{overflow:hidden;background:#20242d;border:1px solid #353d4b;border-radius:9px;margin-bottom:14px}.tbx-guide__prompt-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:12px 14px;border-bottom:1px solid #353d4b}.tbx-guide__prompt-head strong{color:#f7f7f7}.tbx-guide__copy{appearance:none;border:1px solid #536076;background:#343b47;color:#8be9fd;border-radius:5px;padding:6px 10px;cursor:pointer;font-weight:600}.tbx-guide__copy:hover{background:#404957}.tbx-guide__prompt pre{max-height:480px;overflow:auto;margin:0;padding:18px;color:#f8f8f2;background:#20242d;white-space:pre-wrap;font:13px/1.55 Consolas,Monaco,"Andale Mono",monospace}.tbx-guide__note{padding:14px 16px;border-left:4px solid #2271b1;background:#f0f6fc;border-radius:5px;margin:14px 0}.tbx-guide__warning{border-left-color:#dba617;background:#fcf9e8}.tbx-guide__workflow{counter-reset:step;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.tbx-guide__step{counter-increment:step;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:9px}.tbx-guide__step::before{content:'0' counter(step);display:block;margin-bottom:18px;color:#2271b1;font:700 12px/1 Consolas,Monaco,monospace}.tbx-guide__step strong{display:block;margin-bottom:6px}.tbx-guide__step span{color:#646970;line-height:1.4;font-size:13px}@media(max-width:1100px){.tbx-guide__architecture,.tbx-guide__workflow{grid-template-columns:1fr 1fr}.tbx-guide__architecture-card:not(:last-child)::after{display:none}.tbx-guide__grid{grid-template-columns:1fr 1fr}}@media(max-width:782px){.tbx-guide__nav{top:46px}.tbx-guide__hero{display:block}.tbx-guide__version{display:inline-flex;margin-top:18px}.tbx-guide__grid,.tbx-guide__grid--2,.tbx-guide__token-groups,.tbx-guide__architecture,.tbx-guide__workflow{grid-template-columns:1fr}}
CSS;
    wp_add_inline_style('wp-admin', $css);

    $js = <<<'JS'
wp.domReady(function(){
    document.querySelectorAll('.tbx-guide__copy').forEach(function(button){
        button.addEventListener('click', function(){
            var target = document.getElementById(button.getAttribute('data-copy-target'));
            if(!target){ return; }
            var text = target.textContent || '';
            function done(){
                var old = button.textContent;
                button.textContent = 'Copiado';
                setTimeout(function(){ button.textContent = old; }, 1300);
            }
            if(navigator.clipboard && window.isSecureContext){
                navigator.clipboard.writeText(text).then(done);
            } else {
                var area = document.createElement('textarea');
                area.value = text; area.style.position='fixed'; area.style.opacity='0';
                document.body.appendChild(area); area.select();
                try { document.execCommand('copy'); done(); } catch(e) {}
                document.body.removeChild(area);
            }
        });
    });
});
JS;
    wp_add_inline_script('wp-dom-ready', $js, 'after');
}
add_action('admin_enqueue_scripts', 'tibox_theme_claude_guide_assets');

function tibox_theme_claude_prompt_box(string $id, string $title, string $prompt): void
{
    ?>
    <div class="tbx-guide__prompt">
        <div class="tbx-guide__prompt-head">
            <strong><?php echo esc_html($title); ?></strong>
            <button type="button" class="tbx-guide__copy" data-copy-target="<?php echo esc_attr($id); ?>">Copiar prompt</button>
        </div>
        <pre id="<?php echo esc_attr($id); ?>"><?php echo esc_html(trim($prompt)); ?></pre>
    </div>
    <?php
}

function tibox_theme_claude_guide_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $front_page_id = (int) get_option('page_on_front');
    $front_ok = $front_page_id > 0;
    $primary_ok = has_nav_menu('primary');
    $footer_ok = has_nav_menu('footer');
    $catalog_ok = post_type_exists('tibox_catalog_item');

    $prompt_master = <<<'PROMPT'
Estoy diseñando una pieza para un sitio WordPress que usa TIBOX Theme, un theme propio ultraliviano sin Elementor ni page builder.

REGLAS TÉCNICAS OBLIGATORIAS
1. Entrega HTML semántico, CSS nativo y JavaScript nativo solo cuando sea necesario.
2. No uses React, JSX, Tailwind, Bootstrap, jQuery, npm, bundlers ni frameworks salvo que yo lo solicite expresamente.
3. No incluyas PHP, API keys, credenciales, tokens ni lógica de servidor.
4. No generes <!DOCTYPE>, <html>, <head> ni <body>.
5. Para PLANTILLAS de contenido tampoco generes <main>: TIBOX Theme ya imprime el <main id="main-content">. Devuelve únicamente las secciones internas.
6. Header y Footer son fragmentos globales: el Header puede comenzar con <header> y el Footer con <footer>.
7. Conserva literalmente las variables dinámicas {{...}} que te entregue. No cambies su nombre ni las reemplaces por contenido ficticio.
8. No hardcodees el dominio si existe una variable TIBOX equivalente.
9. La solución debe ser responsive, accesible por teclado, con focus visible y compatible con prefers-reduced-motion.
10. Usa clases con prefijo propio para evitar colisiones (por ejemplo tbxc- para Tibox Cloud).
11. No uses estilos inline salvo necesidad excepcional. Devuelve el CSS separado del HTML e indica en qué bloque CSS de TIBOX Theme debe pegarse.
12. Si necesitas imágenes dinámicas, utiliza {{FEATURED_IMAGE}} cuando corresponda. No inventes URLs de assets.

DISEÑO
Trabaja con el sistema visual TIBOX que te adjunto como documento de diseño. Respeta sus tokens, tipografías, jerarquía, accesibilidad y reglas de color. No inventes una nueva identidad visual.

FORMATO DE RESPUESTA
Entrega exactamente:
A) HTML para pegar en la plantilla indicada.
B) CSS para pegar en el bloque CSS correspondiente de TIBOX Theme (Header, Footer, Inicio, Página, Single Catálogo, Archivo Catálogo, etc.).
C) JavaScript únicamente si la interacción lo requiere.
D) Una lista corta de cualquier supuesto o asset que todavía deba proporcionar.
PROMPT;

    $prompt_package = <<<'PROMPT'
Crea un TIBOX DESIGN PACKAGE integrado para WordPress.

OBJETIVO
El ZIP se importará directamente desde WordPress → TIBOX Design → Importar ZIP.
WordPress conserva Header, Footer, SEO, wp_head(), wp_footer() y la lógica dinámica. Tu ZIP define solamente el diseño del contenido.

ESTRUCTURA EXACTA
manifest.json
index.html
style.css
script.js
assets/

REGLAS
- HTML, CSS y JavaScript nativo.
- No React, JSX, Tailwind, Bootstrap ni jQuery.
- No PHP.
- No npm, bundlers ni node_modules.
- No credenciales ni API keys.
- No .htaccess, php.ini, .user.ini ni archivos ejecutables.

INDEX.HTML
- No <!DOCTYPE>.
- No <html>, <head>, <body> ni <main>.
- No <script> ni <style>.
- Devuelve solo las secciones internas.
- Conserva literalmente las variables TIBOX {{...}}.
- Para assets usa {{PACKAGE_URL}}assets/archivo.webp o assets/archivo.webp.

STYLE.CSS
- Solo CSS de esta plantilla.
- Clases con prefijo propio.
- Responsive, focus visible y prefers-reduced-motion.
- Las URLs CSS pueden ser relativas: url("assets/fondo.webp").

SCRIPT.JS
- JavaScript nativo y breve.
- Si no hace falta, entrega el archivo vacío.

MANIFEST.JSON
{
  "name": "Nombre del diseño",
  "version": "1.0.0",
  "type": "template",
  "target": "home",
  "entry": "index.html",
  "css": "style.css",
  "js": "script.js",
  "assets": "assets",
  "author": "TIBOX / Braulio"
}

TARGETS VÁLIDOS
home
page
catalog_single
catalog_archive
single
archive
404

HOME
Si el hero administrado por WordPress debe mantenerse, inserta exactamente:
{{HOME_HERO_SLIDER}}

CATÁLOGO
Para catalog_single usa variables TIBOX dinámicas. No escribas producto, precio ni beneficios fijos.

ENTREGA
Entrégame el ZIP completo listo para subir a TIBOX Design. No incluyas pasos de compilación.
PROMPT;

    $prompt_header = <<<'PROMPT'
Usando el contrato TIBOX Theme anterior, diseña el HEADER GLOBAL de Tibox Cloud.

VARIABLES DISPONIBLES
{{HOME_URL}}
{{SITE_URL}}
{{SITE_NAME}}
{{CUSTOM_LOGO}}
{{MENU_PRIMARY}}

CONDICIONES
- Devuelve un fragmento <header>...</header>.
- No incluyas <main>, <html>, <head> ni <body>.
- {{CUSTOM_LOGO}} debe ser el logo global administrado por WordPress.
- {{MENU_PRIMARY}} debe renderizar el único menú principal administrado desde WordPress; no escribas manualmente sus enlaces.
- Debe incluir navegación escritorio y control móvil accesible.
- Si utilizas JavaScript para abrir/cerrar el menú, debe ser nativo y breve.
- El último enlace no debe asumirse como CTA por posición salvo que yo lo solicite.
- Usa el sistema de diseño TIBOX adjunto.

Entrega HTML, CSS y JavaScript por separado.
PROMPT;

    $prompt_footer = <<<'PROMPT'
Usando el contrato TIBOX Theme anterior, diseña el FOOTER GLOBAL de Tibox Cloud.

VARIABLES DISPONIBLES
{{HOME_URL}}
{{SITE_URL}}
{{SITE_NAME}}
{{CURRENT_YEAR}}
{{CUSTOM_LOGO}}
{{MENU_FOOTER}}
{{MENU_PRIMARY}}

CONDICIONES
- Devuelve un fragmento <footer>...</footer>.
- No incluyas <main>, <html>, <head> ni <body>.
- Prioriza {{MENU_FOOTER}}. Si todavía no existe menú footer, puedo sustituirlo temporalmente por {{MENU_PRIMARY}}.
- No hardcodees el año.
- Mantén el footer sobrio, responsive y consistente con el sistema de diseño TIBOX.

Entrega HTML y CSS por separado. JavaScript solo si es imprescindible.
PROMPT;

    $prompt_home = <<<'PROMPT'
Usando el contrato TIBOX Theme anterior, diseña la PLANTILLA DE INICIO de Tibox Cloud.

IMPORTANTE
TIBOX Theme ya imprime:
<header global>
<main id="main-content" class="tibox-main">
    [AQUÍ SE INSERTA TU HTML]
</main>
<footer global>

Por lo tanto NO generes otro <main>.

VARIABLES DISPONIBLES
{{PAGE_ID}}
{{PAGE_TITLE}}
{{PAGE_URL}}
{{PAGE_EXCERPT}}
{{PAGE_CONTENT}}
{{FEATURED_IMAGE}}
{{HOME_HERO_SLIDER}}
{{SITE_URL}}
{{HOME_URL}}
{{SITE_NAME}}

SLIDER HOME DINÁMICO
- Inserta {{HOME_HERO_SLIDER}} exactamente donde debe aparecer el hero principal.
- El contenido de cada slide se administra en WordPress → Slider Home.
- No escribas manualmente los textos, imágenes ni CTAs de los slides dentro de la plantilla.
- Si quieres cambiar la apariencia del slider, entrega CSS para el bloque TIBOX Theme → CSS / JavaScript → Slider Home, usando las clases .tbx-home-slider* existentes.

OBJETIVO DE LA HOME
Tibox Cloud es una plataforma comercial que puede incluir servicios, planes, aplicaciones, productos digitales y soluciones. El diseño debe permitir que el catálogo crezca sin quedar limitado solo a servicios de marketing.

ESTRUCTURA DE REFERENCIA
- Hero claro y comercial.
- Soluciones destacadas.
- Vista previa del catálogo.
- Ecosistema / categorías.
- CTA final.

No copies literalmente sitios de referencia. Usa solo su lógica de jerarquía y composición.

CONTENIDO
Si quiero que contenido editorial administrado desde Páginas → Inicio aparezca dentro del diseño, inserta {{PAGE_CONTENT}} exactamente en el lugar correspondiente.

Entrega HTML de las secciones internas, CSS global necesario y JavaScript solo si aporta una interacción real.
PROMPT;

    $prompt_catalog_single = <<<'PROMPT'
Usando el contrato TIBOX Theme anterior, diseña el SINGLE GLOBAL DEL CATÁLOGO.

Esta plantilla se diseña UNA SOLA VEZ y WordPress la reutiliza para servicios, planes, aplicaciones, productos digitales y soluciones. No generes una página independiente por producto.

TIBOX Theme ya imprime el <main id="main-content">. No incluyas otro <main>.

VARIABLES DEL ÍTEM
{{PAGE_TITLE}}
{{PAGE_URL}}
{{PAGE_EXCERPT}}
{{PAGE_CONTENT}}
{{FEATURED_IMAGE}}
{{CATALOG_TYPE}}
{{CATALOG_SUMMARY}}
{{CATALOG_PRICE}}
{{CATALOG_BADGE}}
{{CTA_LABEL}}
{{CTA_URL}}

VARIABLES GLOBALES DISPONIBLES
{{SITE_URL}}
{{HOME_URL}}
{{SITE_NAME}}

REGLAS
- El layout debe funcionar aunque precio, badge, imagen o CTA estén vacíos.
- No escribas precios, nombres ni tipos fijos dentro del diseño.
- {{PAGE_CONTENT}} representa el contenido detallado administrado en WordPress.
- {{FEATURED_IMAGE}} ya devuelve el HTML de la imagen destacada cuando existe.
- El CTA debe usar {{CTA_URL}} y {{CTA_LABEL}}.
- Evita claims comerciales inventados; la plantilla es estructural.

Entrega HTML y CSS separados.
PROMPT;

    $prompt_catalog_archive = <<<'PROMPT'
Usando el contrato TIBOX Theme anterior, diseña el ARCHIVO GLOBAL DEL CATÁLOGO en /catalogo/.

TIBOX Theme ya imprime el <main id="main-content">. No incluyas otro <main>.

VARIABLES DISPONIBLES
{{ARCHIVE_TITLE}}
{{ARCHIVE_DESCRIPTION}}
{{ARCHIVE_ITEMS}}
{{PAGINATION}}
{{SITE_URL}}
{{HOME_URL}}
{{SITE_NAME}}

IMPORTANTE
- {{ARCHIVE_ITEMS}} es el punto exacto donde WordPress inserta las cards de los elementos publicados.
- No escribas manualmente una lista fija de productos dentro de la plantilla.
- {{PAGINATION}} debe quedar en una zona lógica después del listado.
- El diseño debe admitir crecimiento del catálogo y distintos tipos de ítems.

Entrega HTML y CSS separados.
PROMPT;

    $prompt_page = <<<'PROMPT'
Usando el contrato TIBOX Theme anterior, diseña la PLANTILLA GLOBAL DE PÁGINA.

Esta plantilla se reutiliza en páginas WordPress normales como Contacto, Nosotros u otras páginas editoriales.
TIBOX Theme ya imprime el <main id="main-content">. No incluyas otro <main>.

VARIABLES DISPONIBLES
{{PAGE_ID}}
{{PAGE_TITLE}}
{{PAGE_URL}}
{{PAGE_EXCERPT}}
{{PAGE_CONTENT}}
{{FEATURED_IMAGE}}
{{SITE_URL}}
{{HOME_URL}}
{{SITE_NAME}}

La plantilla debe tener una estructura suficientemente neutra para reutilizarse. Usa {{PAGE_CONTENT}} para insertar el contenido administrado desde WordPress. No hardcodees el contenido de una página específica.

Entrega HTML y CSS separados.
PROMPT;
    ?>
    <div class="wrap tbx-guide">
        <section class="tbx-guide__hero">
            <div>
                <h1>TIBOX · Guía Claude Design</h1>
                <p>Contrato técnico y flujo de trabajo para diseñar con IA sin Elementor. Esta página describe lo que WordPress controla, dónde se pega cada pieza y qué debe pedirle el equipo a Claude Design para que el resultado sea compatible con TIBOX Theme.</p>
            </div>
            <span class="tbx-guide__version">TIBOX THEME v<?php echo esc_html(TIBOX_THEME_VERSION); ?></span>
        </section>

        <nav class="tbx-guide__nav" aria-label="Contenido de la guía">
            <a href="#estado">Estado</a>
            <a href="#arquitectura">Arquitectura</a>
            <a href="#plantillas">Plantillas</a>
            <a href="#variables">Variables</a>
            <a href="#reglas">Reglas Claude</a>
            <a href="#flujo">Flujo</a>
            <a href="#zip">ZIP / Design Packages</a>
            <a href="#prompts">Prompts</a>
        </nav>

        <section class="tbx-guide__section" id="estado">
            <h2>Estado rápido del sitio</h2>
            <div class="tbx-guide__grid">
                <div class="tbx-guide__card">
                    <h3>Portada estática</h3>
                    <p><?php echo $front_ok ? esc_html(get_the_title($front_page_id)) : 'Aún no está asignada en Ajustes → Lectura.'; ?></p>
                    <div class="tbx-guide__status"><i class="tbx-guide__dot <?php echo $front_ok ? 'is-ok' : ''; ?>"></i><?php echo $front_ok ? 'Configurada' : 'Pendiente'; ?></div>
                </div>
                <div class="tbx-guide__card">
                    <h3>Menú principal</h3>
                    <p>Ubicación <code>primary</code> usada por <code>{{MENU_PRIMARY}}</code>.</p>
                    <div class="tbx-guide__status"><i class="tbx-guide__dot <?php echo $primary_ok ? 'is-ok' : ''; ?>"></i><?php echo $primary_ok ? 'Asignado' : 'Sin asignar'; ?></div>
                </div>
                <div class="tbx-guide__card">
                    <h3>TIBOX Core / Catálogo</h3>
                    <p>Custom Post Type <code>tibox_catalog_item</code>.</p>
                    <div class="tbx-guide__status"><i class="tbx-guide__dot <?php echo $catalog_ok ? 'is-ok' : ''; ?>"></i><?php echo $catalog_ok ? 'Activo' : 'No detectado'; ?></div>
                </div>
            </div>
        </section>

        <section class="tbx-guide__section" id="arquitectura">
            <h2>Cómo está estructurado</h2>
            <p>WordPress administra contenido y datos. TIBOX Core administra la lógica comercial. TIBOX Theme decide cómo se presenta todo. Claude Design se usa para producir la capa visual, no para reemplazar el backend.</p>
            <div class="tbx-guide__architecture">
                <div class="tbx-guide__architecture-card"><span>01</span><strong>WordPress Admin</strong><small>Páginas, medios, menús y contenido.</small></div>
                <div class="tbx-guide__architecture-card"><span>02</span><strong>TIBOX Core</strong><small>Catálogo y lógica que debe sobrevivir al cambio de theme.</small></div>
                <div class="tbx-guide__architecture-card"><span>03</span><strong>TIBOX Theme</strong><small>Header, footer, templates, CSS, JS y tokens.</small></div>
                <div class="tbx-guide__architecture-card"><span>04</span><strong>Variables {{...}}</strong><small>Conectan el HTML diseñado con datos de WordPress.</small></div>
                <div class="tbx-guide__architecture-card"><span>05</span><strong>Frontend</strong><small>HTML/CSS/JS liviano servido al visitante.</small></div>
            </div>
            <div class="tbx-guide__note"><strong>Claude Design no administra contenido.</strong> Diseña una estructura reutilizable y deja los lugares dinámicos representados por variables TIBOX.</div>
        </section>

        <section class="tbx-guide__section" id="plantillas">
            <h2>Mapa de plantillas</h2>
            <div class="tbx-guide__table-wrap">
                <table>
                    <thead><tr><th>Qué se diseña</th><th>Dónde se edita</th><th>Motor PHP</th><th>Qué debe entregar Claude</th></tr></thead>
                    <tbody>
                        <tr><td>Header global</td><td>Apariencia → TIBOX Theme → Diseño global</td><td><code>header.php</code></td><td><code>&lt;header&gt;...&lt;/header&gt;</code></td></tr>
                        <tr><td>Footer global</td><td>Apariencia → TIBOX Theme → Diseño global</td><td><code>footer.php</code></td><td><code>&lt;footer&gt;...&lt;/footer&gt;</code></td></tr>
                        <tr><td>Inicio</td><td>TIBOX Theme → Plantillas → Inicio</td><td><code>front-page.php</code></td><td>Secciones internas, <strong>sin &lt;main&gt;</strong>; usar <code>{{HOME_HERO_SLIDER}}</code></td></tr>
                        <tr><td>Slider Home</td><td>WordPress → Slider Home</td><td>TIBOX Core + Theme</td><td>No hardcodear slides; el contenido se administra en WordPress</td></tr>
                        <tr><td>Página normal</td><td>TIBOX Theme → Plantillas → Página</td><td><code>page.php</code></td><td>Secciones internas, <strong>sin &lt;main&gt;</strong></td></tr>
                        <tr><td>Ficha de catálogo</td><td>TIBOX Theme → Plantillas → Single catálogo</td><td><code>single-tibox_catalog_item.php</code></td><td>Una plantilla reutilizable, sin datos fijos</td></tr>
                        <tr><td>Listado /catalogo/</td><td>TIBOX Theme → Plantillas → Archivo catálogo</td><td><code>archive-tibox_catalog_item.php</code></td><td>Layout que contenga <code>{{ARCHIVE_ITEMS}}</code></td></tr>
                        <tr><td>CSS del sitio</td><td>TIBOX Theme → CSS / JavaScript</td><td><code>wp_head()</code></td><td>CSS nativo sin etiquetas <code>&lt;style&gt;</code></td></tr>
                        <tr><td>JS del sitio</td><td>TIBOX Theme → CSS / JavaScript</td><td><code>wp_footer()</code></td><td>JS nativo sin etiquetas <code>&lt;script&gt;</code></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="tbx-guide__note tbx-guide__warning"><strong>Regla v0.3:</strong> todas las plantillas de contenido se insertan dentro del <code>&lt;main id="main-content"&gt;</code> generado por el theme. Claude no debe devolver otro <code>&lt;main&gt;</code>.</div>
        </section>

        <section class="tbx-guide__section" id="variables">
            <h2>Variables que Claude puede usar</h2>
            <div class="tbx-guide__token-groups">
                <div class="tbx-guide__tokens"><h3>Globales</h3><div class="tbx-guide__token-list"><code>{{SITE_URL}}</code><code>{{HOME_URL}}</code><code>{{THEME_URL}}</code><code>{{SITE_NAME}}</code><code>{{CURRENT_YEAR}}</code><code>{{CUSTOM_LOGO}}</code><code>{{MENU_PRIMARY}}</code><code>{{MENU_FOOTER}}</code></div></div>
                <div class="tbx-guide__tokens"><h3>Páginas / contenido</h3><div class="tbx-guide__token-list"><code>{{PAGE_ID}}</code><code>{{PAGE_TITLE}}</code><code>{{PAGE_URL}}</code><code>{{PAGE_EXCERPT}}</code><code>{{PAGE_CONTENT}}</code><code>{{FEATURED_IMAGE}}</code></div></div>
                <div class="tbx-guide__tokens"><h3>Inicio</h3><div class="tbx-guide__token-list"><code>{{HOME_HERO_SLIDER}}</code></div></div>
                <div class="tbx-guide__tokens"><h3>Single Catálogo</h3><div class="tbx-guide__token-list"><code>{{CATALOG_TYPE}}</code><code>{{CATALOG_SUMMARY}}</code><code>{{CATALOG_PRICE}}</code><code>{{CATALOG_BADGE}}</code><code>{{CATALOG_PROMO}}</code><code>{{CATALOG_VALUE_PROPOSITION}}</code><code>{{CATALOG_PLATFORM_CHIPS}}</code><code>{{CATALOG_FEATURE_LIST}}</code><code>{{CATALOG_CATEGORIES}}</code><code>{{CTA_LABEL}}</code><code>{{CTA_URL}}</code></div></div>
                <div class="tbx-guide__tokens"><h3>Archivos</h3><div class="tbx-guide__token-list"><code>{{ARCHIVE_TITLE}}</code><code>{{ARCHIVE_DESCRIPTION}}</code><code>{{ARCHIVE_ITEMS}}</code><code>{{PAGINATION}}</code></div></div>
            </div>
            <div class="tbx-guide__note"><strong>No inventar variables.</strong> Si Claude necesita un dato que no aparece aquí, primero se define técnicamente en TIBOX Theme/Core y después se agrega al contrato.</div>
        </section>

        <section class="tbx-guide__section" id="reglas">
            <h2>Reglas para pedir diseños a Claude</h2>
            <div class="tbx-guide__grid tbx-guide__grid--2">
                <div class="tbx-guide__card" style="padding:0;overflow:hidden">
                    <div class="tbx-guide__rule is-do"><span class="tbx-guide__rule-icon">✓</span><span>HTML semántico + CSS nativo + JS nativo si hace falta.</span></div>
                    <div class="tbx-guide__rule is-do"><span class="tbx-guide__rule-icon">✓</span><span>Mantener literalmente las variables <code>{{...}}</code>.</span></div>
                    <div class="tbx-guide__rule is-do"><span class="tbx-guide__rule-icon">✓</span><span>Clases con prefijo propio para evitar colisiones.</span></div>
                    <div class="tbx-guide__rule is-do"><span class="tbx-guide__rule-icon">✓</span><span>Responsive, navegación por teclado, focus visible y reduced motion.</span></div>
                    <div class="tbx-guide__rule is-do"><span class="tbx-guide__rule-icon">✓</span><span>Adjuntar el sistema de diseño TIBOX al proyecto de Claude.</span></div>
                </div>
                <div class="tbx-guide__card" style="padding:0;overflow:hidden">
                    <div class="tbx-guide__rule is-dont"><span class="tbx-guide__rule-icon">×</span><span>No PHP, credenciales, API keys ni secretos.</span></div>
                    <div class="tbx-guide__rule is-dont"><span class="tbx-guide__rule-icon">×</span><span>No <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code>, <code>&lt;body&gt;</code> ni <code>&lt;main&gt;</code> en templates.</span></div>
                    <div class="tbx-guide__rule is-dont"><span class="tbx-guide__rule-icon">×</span><span>No React/JSX/Tailwind/Bootstrap/bundlers salvo decisión explícita.</span></div>
                    <div class="tbx-guide__rule is-dont"><span class="tbx-guide__rule-icon">×</span><span>No crear una página estática distinta para cada ítem del catálogo.</span></div>
                    <div class="tbx-guide__rule is-dont"><span class="tbx-guide__rule-icon">×</span><span>No hardcodear URLs, año, menú o datos cuando exista una variable.</span></div>
                </div>
            </div>
        </section>

        <section class="tbx-guide__section" id="flujo">
            <h2>Flujo de trabajo recomendado</h2>
            <div class="tbx-guide__workflow">
                <div class="tbx-guide__step"><strong>Elegir pieza</strong><span>Header, Home, Single Catálogo, etc.</span></div>
                <div class="tbx-guide__step"><strong>Copiar prompt</strong><span>Usar el prompt específico de esta guía.</span></div>
                <div class="tbx-guide__step"><strong>Diseñar en Claude</strong><span>Adjuntar sistema de diseño y referencia visual.</span></div>
                <div class="tbx-guide__step"><strong>Subir o pegar</strong><span>Preferir ZIP para diseños completos; editor manual para ajustes pequeños.</span></div>
                <div class="tbx-guide__step"><strong>Validar</strong><span>Desktop, móvil, enlaces, contenido dinámico y accesibilidad.</span></div>
            </div>
        </section>

        <section class="tbx-guide__section" id="zip">
            <h2>Design Packages ZIP</h2>
            <p>Para una plantilla completa, Braulio puede pedirle a Claude un ZIP y subirlo directamente en <strong>TIBOX Design → Importar ZIP</strong>, sin copiar HTML/CSS a mano.</p>

            <div class="tbx-guide__grid">
                <div class="tbx-guide__card"><h3>1. Claude diseña</h3><p><code>manifest.json</code>, <code>index.html</code>, <code>style.css</code>, <code>script.js</code> y <code>assets/</code>.</p></div>
                <div class="tbx-guide__card"><h3>2. WordPress valida</h3><p>Bloquea PHP, rutas inseguras y extensiones no autorizadas antes de guardar el paquete.</p></div>
                <div class="tbx-guide__card"><h3>3. Preview / activar</h3><p>Cada importación queda como una versión. Una versión anterior puede reactivarse como rollback.</p></div>
            </div>

            <div class="tbx-guide__note"><strong>Prioridad:</strong> ZIP activo → plantilla HTML manual → PHP por defecto. Header y Footer globales se conservan.</div>

            <div class="tbx-guide__token-groups">
                <div class="tbx-guide__tokens">
                    <h3>Variables propias del ZIP</h3>
                    <div class="tbx-guide__token-list"><code>{{PACKAGE_URL}}</code><code>{{PACKAGE_ASSETS_URL}}</code></div>
                </div>
            </div>

            <?php tibox_theme_claude_prompt_box('tbx-prompt-package', 'Prompt · TIBOX Design Package ZIP', $prompt_package); ?>
        </section>

        <section class="tbx-guide__section" id="prompts">
            <h2>Prompts listos para Claude Design</h2>
            <p>El <strong>Prompt maestro</strong> puede ir al comienzo de la conversación o quedar como instrucción del proyecto. Luego agrega el prompt de la pieza específica.</p>
            <?php
            tibox_theme_claude_prompt_box('tbx-prompt-master', '01 · Prompt maestro / contrato técnico', $prompt_master);
            tibox_theme_claude_prompt_box('tbx-prompt-header', '02 · Header global', $prompt_header);
            tibox_theme_claude_prompt_box('tbx-prompt-footer', '03 · Footer global', $prompt_footer);
            tibox_theme_claude_prompt_box('tbx-prompt-home', '04 · Inicio', $prompt_home);
            tibox_theme_claude_prompt_box('tbx-prompt-page', '05 · Página normal', $prompt_page);
            tibox_theme_claude_prompt_box('tbx-prompt-catalog-single', '06 · Single del Catálogo', $prompt_catalog_single);
            tibox_theme_claude_prompt_box('tbx-prompt-catalog-archive', '07 · Archivo del Catálogo', $prompt_catalog_archive);
            ?>
        </section>
    </div>
    <?php
}
