<?php
/**
 * Plugin Name: TIBOX Core
 * Description: Núcleo de contenido para sitios TIBOX. Catálogo, Slider Home, Design Packages ZIP e importación/exportación.
 * Version: 0.4.0
 * Author: TIBOX
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: tibox-core
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TIBOX_Core
{
    private const POST_TYPE = 'tibox_catalog_item';
    private const TAXONOMY = 'tibox_catalog_cat';
    private const SLIDE_POST_TYPE = 'tibox_home_slide';
    private const NONCE_ACTION = 'tibox_catalog_save';
    private const NONCE_FIELD = 'tibox_catalog_nonce';

    public static function init(): void
    {
        add_action('init', [self::class, 'register_content']);
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save_meta']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'column_content'], 10, 2);

        add_action('admin_menu', [self::class, 'register_import_page']);
        add_action('admin_post_tibox_catalog_import_json', [self::class, 'handle_json_import']);
        add_action('admin_post_tibox_catalog_export_json', [self::class, 'handle_json_export']);
        add_action('admin_notices', [self::class, 'admin_notices']);

        /*
         * Slider Home.
         */
        add_action('add_meta_boxes', [self::class, 'add_slide_meta_box']);
        add_action('save_post_' . self::SLIDE_POST_TYPE, [self::class, 'save_slide_meta']);
        add_action('admin_enqueue_scripts', [self::class, 'slide_admin_assets']);
        add_filter('manage_' . self::SLIDE_POST_TYPE . '_posts_columns', [self::class, 'slide_columns']);
        add_action('manage_' . self::SLIDE_POST_TYPE . '_posts_custom_column', [self::class, 'slide_column_content'], 10, 2);

        /*
         * Herramienta de diagnóstico/reparación de miniaturas.
         */
        add_action('admin_menu', [self::class, 'register_media_repair_page']);
        add_action('admin_post_tibox_repair_media', [self::class, 'handle_media_repair']);
        add_action('admin_post_tibox_force_rebuild_media', [self::class, 'handle_force_rebuild_media']);
    }

    public static function activate(): void
    {
        self::register_content();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function register_content(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'               => 'Catálogo',
                'singular_name'      => 'Elemento del catálogo',
                'menu_name'          => 'Catálogo',
                'add_new'            => 'Añadir nuevo',
                'add_new_item'       => 'Añadir al catálogo',
                'edit_item'          => 'Editar elemento',
                'new_item'           => 'Nuevo elemento',
                'view_item'          => 'Ver elemento',
                'search_items'       => 'Buscar en catálogo',
                'not_found'          => 'No se encontraron elementos',
                'not_found_in_trash' => 'No hay elementos en la papelera',
                'all_items'          => 'Todo el catálogo',
            ],
            'public'             => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-screenoptions',
            'menu_position'      => 20,
            'has_archive'        => 'catalogo',
            'rewrite'            => [
                'slug'       => 'catalogo',
                'with_front' => false,
            ],
            'supports'           => [
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'revisions',
                'page-attributes',
            ],
            'taxonomies'         => [self::TAXONOMY],
        ]);

        /*
         * Slider principal del Home.
         *
         * El contenido vive en TIBOX Core para que sobreviva aunque
         * en el futuro se reemplace el theme.
         */
        register_post_type(self::SLIDE_POST_TYPE, [
            'labels' => [
                'name'               => 'Slider Home',
                'singular_name'      => 'Slide',
                'menu_name'          => 'Slider Home',
                'add_new'            => 'Añadir slide',
                'add_new_item'       => 'Añadir nuevo slide',
                'edit_item'          => 'Editar slide',
                'new_item'           => 'Nuevo slide',
                'view_item'          => 'Ver slide',
                'search_items'       => 'Buscar slides',
                'not_found'          => 'No se encontraron slides',
                'not_found_in_trash' => 'No hay slides en la papelera',
                'all_items'          => 'Todos los slides',
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'menu_icon'           => 'dashicons-images-alt2',
            'menu_position'       => 21,
            'supports'            => [
                'title',
                'revisions',
                'page-attributes',
            ],
            'exclude_from_search' => true,
        ]);

        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], [
            'labels' => [
                'name'          => 'Categorías',
                'singular_name' => 'Categoría',
                'search_items'  => 'Buscar categorías',
                'all_items'     => 'Todas las categorías',
                'edit_item'     => 'Editar categoría',
                'update_item'   => 'Actualizar categoría',
                'add_new_item'  => 'Añadir categoría',
                'new_item_name' => 'Nombre de la categoría',
                'menu_name'     => 'Categorías',
            ],
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'rewrite'           => [
                'slug'       => 'categoria-catalogo',
                'with_front' => false,
            ],
        ]);
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'tibox-catalog-commercial',
            'Información comercial',
            [self::class, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $type = (string) get_post_meta($post->ID, '_tibox_catalog_type', true);
        $summary = (string) get_post_meta($post->ID, '_tibox_catalog_summary', true);
        $price = (string) get_post_meta($post->ID, '_tibox_catalog_price', true);
        $badge = (string) get_post_meta($post->ID, '_tibox_catalog_badge', true);
        $promo = (string) get_post_meta($post->ID, '_tibox_catalog_promo', true);
        $value_prop = (string) get_post_meta($post->ID, '_tibox_catalog_value_prop', true);
        $platforms = get_post_meta($post->ID, '_tibox_catalog_platforms', true);
        $features = get_post_meta($post->ID, '_tibox_catalog_features', true);
        $cta_label = (string) get_post_meta($post->ID, '_tibox_catalog_cta_label', true);
        $cta_url = (string) get_post_meta($post->ID, '_tibox_catalog_cta_url', true);

        if (!is_array($platforms)) {
            $platforms = [];
        }
        if (!is_array($features)) {
            $features = [];
        }

        $platform_lines = [];
        foreach ($platforms as $platform) {
            if (is_array($platform)) {
                $name = isset($platform['name']) ? (string) $platform['name'] : '';
                $key = isset($platform['key']) ? (string) $platform['key'] : '';
                if ($name !== '') {
                    $platform_lines[] = $key !== '' ? $name . '|' . $key : $name;
                }
            } elseif (is_string($platform) && $platform !== '') {
                $platform_lines[] = $platform;
            }
        }
        ?>
        <style>
            .tibox-core-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px}
            .tibox-core-full{grid-column:1/-1}
            .tibox-core-help{padding:10px 12px;border-left:3px solid #2271b1;background:#f0f6fc}
            @media(max-width:782px){.tibox-core-grid{grid-template-columns:1fr}.tibox-core-full{grid-column:auto}}
        </style>

        <div class="tibox-core-grid">
            <p>
                <label for="tibox_catalog_type"><strong>Tipo</strong></label><br>
                <select id="tibox_catalog_type" name="tibox_catalog_type" class="widefat">
                    <?php
                    $types = [
                        'servicio' => 'Servicio',
                        'plan' => 'Plan',
                        'aplicacion' => 'Aplicación',
                        'producto-digital' => 'Producto digital',
                        'solucion' => 'Solución',
                    ];
                    foreach ($types as $value => $label) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($value),
                            selected($type, $value, false),
                            esc_html($label)
                        );
                    }
                    ?>
                </select>
            </p>

            <p>
                <label for="tibox_catalog_price"><strong>Precio / etiqueta comercial</strong></label><br>
                <input id="tibox_catalog_price" name="tibox_catalog_price" type="text" class="widefat"
                    value="<?php echo esc_attr($price); ?>"
                    placeholder="Ej.: 2 UF / mes">
            </p>

            <p class="tibox-core-full">
                <label for="tibox_catalog_summary"><strong>Descripción corta</strong></label><br>
                <textarea id="tibox_catalog_summary" name="tibox_catalog_summary" rows="3" class="widefat"><?php echo esc_textarea($summary); ?></textarea>
            </p>

            <p>
                <label for="tibox_catalog_badge"><strong>Etiqueta superior</strong></label><br>
                <input id="tibox_catalog_badge" name="tibox_catalog_badge" type="text" class="widefat"
                    value="<?php echo esc_attr($badge); ?>"
                    placeholder="Ej.: BASE DIGITAL">
            </p>

            <p>
                <label for="tibox_catalog_promo"><strong>Promoción / precio lanzamiento</strong></label><br>
                <input id="tibox_catalog_promo" name="tibox_catalog_promo" type="text" class="widefat"
                    value="<?php echo esc_attr($promo); ?>"
                    placeholder="Ej.: Lanzamiento: 1 UF / mes durante 3 meses">
            </p>

            <p class="tibox-core-full">
                <label for="tibox_catalog_value_prop"><strong>Propuesta principal</strong></label><br>
                <input id="tibox_catalog_value_prop" name="tibox_catalog_value_prop" type="text" class="widefat"
                    value="<?php echo esc_attr($value_prop); ?>"
                    placeholder="Ej.: Presencia online profesional, confiable y siempre operativa.">
            </p>

            <p>
                <label for="tibox_catalog_platforms"><strong>Plataformas / canales</strong></label><br>
                <textarea id="tibox_catalog_platforms" name="tibox_catalog_platforms" rows="7" class="widefat code"
                    placeholder="WordPress|wordpress&#10;Instagram|instagram&#10;SEO|seo"><?php echo esc_textarea(implode("\n", $platform_lines)); ?></textarea>
                <small>Una por línea. Formato recomendado: <code>Nombre|clave</code>.</small>
            </p>

            <p>
                <label for="tibox_catalog_features"><strong>Características / beneficios</strong></label><br>
                <textarea id="tibox_catalog_features" name="tibox_catalog_features" rows="7" class="widefat"
                    placeholder="Una característica por línea"><?php echo esc_textarea(implode("\n", array_map('strval', $features))); ?></textarea>
                <small>Una característica por línea.</small>
            </p>

            <p>
                <label for="tibox_catalog_cta_label"><strong>Texto CTA</strong></label><br>
                <input id="tibox_catalog_cta_label" name="tibox_catalog_cta_label" type="text" class="widefat"
                    value="<?php echo esc_attr($cta_label); ?>"
                    placeholder="Ej.: Explorar plan">
            </p>

            <p>
                <label for="tibox_catalog_cta_url"><strong>URL CTA</strong></label><br>
                <input id="tibox_catalog_cta_url" name="tibox_catalog_cta_url" type="url" class="widefat"
                    value="<?php echo esc_attr($cta_url); ?>"
                    placeholder="https://...">
            </p>
        </div>

        <p class="tibox-core-help">
            <strong>Contenido largo:</strong> se administra en el editor principal.
            Estos campos son datos estructurados para que el theme o Claude Design los presenten dinámicamente.
        </p>
        <?php
    }

    public static function save_meta(int $post_id): void
    {
        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])),
                self::NONCE_ACTION
            )
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }

        $allowed_types = ['servicio', 'plan', 'aplicacion', 'producto-digital', 'solucion'];
        $type = isset($_POST['tibox_catalog_type'])
            ? sanitize_key(wp_unslash($_POST['tibox_catalog_type']))
            : 'servicio';

        if (!in_array($type, $allowed_types, true)) {
            $type = 'servicio';
        }

        update_post_meta($post_id, '_tibox_catalog_type', $type);
        update_post_meta($post_id, '_tibox_catalog_summary', self::post_textarea('tibox_catalog_summary'));
        update_post_meta($post_id, '_tibox_catalog_price', self::post_text('tibox_catalog_price'));
        update_post_meta($post_id, '_tibox_catalog_badge', self::post_text('tibox_catalog_badge'));
        update_post_meta($post_id, '_tibox_catalog_promo', self::post_text('tibox_catalog_promo'));
        update_post_meta($post_id, '_tibox_catalog_value_prop', self::post_text('tibox_catalog_value_prop'));
        update_post_meta($post_id, '_tibox_catalog_platforms', self::parse_platform_lines(
            isset($_POST['tibox_catalog_platforms']) ? wp_unslash($_POST['tibox_catalog_platforms']) : ''
        ));
        update_post_meta($post_id, '_tibox_catalog_features', self::parse_simple_lines(
            isset($_POST['tibox_catalog_features']) ? wp_unslash($_POST['tibox_catalog_features']) : ''
        ));
        update_post_meta($post_id, '_tibox_catalog_cta_label', self::post_text('tibox_catalog_cta_label'));

        $cta_url = isset($_POST['tibox_catalog_cta_url'])
            ? esc_url_raw(wp_unslash($_POST['tibox_catalog_cta_url']))
            : '';
        update_post_meta($post_id, '_tibox_catalog_cta_url', $cta_url);
    }

    private static function post_text(string $key): string
    {
        return isset($_POST[$key])
            ? sanitize_text_field(wp_unslash($_POST[$key]))
            : '';
    }

    private static function post_textarea(string $key): string
    {
        return isset($_POST[$key])
            ? sanitize_textarea_field(wp_unslash($_POST[$key]))
            : '';
    }

    private static function parse_simple_lines(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!is_array($lines)) {
            return [];
        }

        $result = [];
        foreach ($lines as $line) {
            $line = sanitize_text_field(trim($line));
            if ($line !== '') {
                $result[] = $line;
            }
        }
        return array_values(array_unique($result));
    }

    private static function parse_platform_lines(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!is_array($lines)) {
            return [];
        }

        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $name = sanitize_text_field($parts[0] ?? '');
            $key = sanitize_key($parts[1] ?? sanitize_title($name));

            if ($name !== '') {
                $result[] = [
                    'name' => $name,
                    'key' => $key,
                ];
            }
        }
        return $result;
    }

    public static function columns(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result['tibox_type'] = 'Tipo';
                $result['tibox_price'] = 'Precio / etiqueta';
            }
        }
        return $result;
    }

    public static function column_content(string $column, int $post_id): void
    {
        if ($column === 'tibox_type') {
            echo esc_html((string) get_post_meta($post_id, '_tibox_catalog_type', true));
        }

        if ($column === 'tibox_price') {
            $price = (string) get_post_meta($post_id, '_tibox_catalog_price', true);
            echo $price !== ''
                ? esc_html($price)
                : '<span style="color:#777">—</span>';
        }
    }


    /**
     * -------------------------------------------------------------------------
     * Slider Home
     * -------------------------------------------------------------------------
     */

    public static function add_slide_meta_box(): void
    {
        add_meta_box(
            'tibox-home-slide',
            'Contenido del slide',
            [self::class, 'render_slide_meta_box'],
            self::SLIDE_POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_slide_meta_box(WP_Post $post): void
    {
        wp_nonce_field('tibox_home_slide_save', 'tibox_home_slide_nonce');

        $get = static function (string $key, string $default = '') use ($post): string {
            $value = get_post_meta($post->ID, $key, true);
            return $value === '' ? $default : (string) $value;
        };

        $active = $get('_tibox_slide_active', '1');
        $eyebrow = $get('_tibox_slide_eyebrow');
        $headline = $get('_tibox_slide_headline');
        $description = $get('_tibox_slide_description');
        $desktop_image_id = absint($get('_tibox_slide_desktop_image_id'));
        $mobile_image_id = absint($get('_tibox_slide_mobile_image_id'));
        $desktop_image_url = $get('_tibox_slide_desktop_image_url');
        $mobile_image_url = $get('_tibox_slide_mobile_image_url');
        $primary_label = $get('_tibox_slide_primary_label');
        $primary_url = $get('_tibox_slide_primary_url');
        $secondary_label = $get('_tibox_slide_secondary_label');
        $secondary_url = $get('_tibox_slide_secondary_url');
        $alignment = $get('_tibox_slide_alignment', 'left');
        $surface = $get('_tibox_slide_surface', 'dark');

        $desktop_preview = $desktop_image_id > 0
            ? wp_get_attachment_image_url($desktop_image_id, 'medium_large')
            : '';
        $mobile_preview = $mobile_image_id > 0
            ? wp_get_attachment_image_url($mobile_image_id, 'medium')
            : '';

        if (!$desktop_preview && $desktop_image_url !== '') {
            $desktop_preview = $desktop_image_url;
        }
        if (!$mobile_preview && $mobile_image_url !== '') {
            $mobile_preview = $mobile_image_url;
        }
        ?>
        <style>
            .tbx-slide-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px}
            .tbx-slide-full{grid-column:1/-1}
            .tbx-slide-media-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
            .tbx-slide-media{padding:14px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7}
            .tbx-slide-media__preview{display:flex;align-items:center;justify-content:center;min-height:150px;margin:10px 0;background:#fff;border:1px dashed #c3c4c7;border-radius:6px;overflow:hidden}
            .tbx-slide-media__preview img{display:block;max-width:100%;max-height:190px;width:auto;height:auto}
            .tbx-slide-note{padding:10px 12px;border-left:3px solid #2271b1;background:#f0f6fc}
            @media(max-width:782px){.tbx-slide-grid,.tbx-slide-media-grid{grid-template-columns:1fr}.tbx-slide-full{grid-column:auto}}
        </style>

        <p>
            <label>
                <input type="checkbox" name="tibox_slide_active" value="1" <?php checked($active, '1'); ?>>
                <strong>Slide activo</strong>
            </label>
        </p>

        <div class="tbx-slide-grid">
            <p>
                <label for="tibox_slide_eyebrow"><strong>Eyebrow / etiqueta</strong></label><br>
                <input id="tibox_slide_eyebrow" name="tibox_slide_eyebrow" type="text" class="widefat"
                    value="<?php echo esc_attr($eyebrow); ?>"
                    placeholder="Ej.: TIBOX CLOUD">
            </p>

            <p>
                <label for="menu_order"><strong>Orden</strong></label><br>
                <input id="menu_order" name="menu_order" type="number" class="small-text"
                    value="<?php echo esc_attr((string) $post->menu_order); ?>" min="0" step="1">
                <br><small>Menor número aparece primero.</small>
            </p>

            <p class="tbx-slide-full">
                <label for="tibox_slide_headline"><strong>Titular principal</strong></label><br>
                <input id="tibox_slide_headline" name="tibox_slide_headline" type="text" class="widefat"
                    value="<?php echo esc_attr($headline); ?>"
                    placeholder="Ej.: Todo lo digital que tu negocio necesita, en un solo lugar.">
            </p>

            <p class="tbx-slide-full">
                <label for="tibox_slide_description"><strong>Descripción</strong></label><br>
                <textarea id="tibox_slide_description" name="tibox_slide_description" rows="4" class="widefat"
                    placeholder="Texto de apoyo del hero."><?php echo esc_textarea($description); ?></textarea>
            </p>

            <p>
                <label for="tibox_slide_primary_label"><strong>CTA principal · texto</strong></label><br>
                <input id="tibox_slide_primary_label" name="tibox_slide_primary_label" type="text" class="widefat"
                    value="<?php echo esc_attr($primary_label); ?>"
                    placeholder="Explorar catálogo">
            </p>

            <p>
                <label for="tibox_slide_primary_url"><strong>CTA principal · URL</strong></label><br>
                <input id="tibox_slide_primary_url" name="tibox_slide_primary_url" type="url" class="widefat"
                    value="<?php echo esc_attr($primary_url); ?>"
                    placeholder="https://... o URL interna completa">
            </p>

            <p>
                <label for="tibox_slide_secondary_label"><strong>CTA secundario · texto</strong></label><br>
                <input id="tibox_slide_secondary_label" name="tibox_slide_secondary_label" type="text" class="widefat"
                    value="<?php echo esc_attr($secondary_label); ?>"
                    placeholder="Conversemos">
            </p>

            <p>
                <label for="tibox_slide_secondary_url"><strong>CTA secundario · URL</strong></label><br>
                <input id="tibox_slide_secondary_url" name="tibox_slide_secondary_url" type="url" class="widefat"
                    value="<?php echo esc_attr($secondary_url); ?>"
                    placeholder="https://... o URL interna completa">
            </p>

            <p>
                <label for="tibox_slide_alignment"><strong>Alineación</strong></label><br>
                <select id="tibox_slide_alignment" name="tibox_slide_alignment" class="widefat">
                    <option value="left" <?php selected($alignment, 'left'); ?>>Izquierda</option>
                    <option value="center" <?php selected($alignment, 'center'); ?>>Centro</option>
                </select>
            </p>

            <p>
                <label for="tibox_slide_surface"><strong>Superficie</strong></label><br>
                <select id="tibox_slide_surface" name="tibox_slide_surface" class="widefat">
                    <option value="dark" <?php selected($surface, 'dark'); ?>>Dark · azul profundo</option>
                    <option value="brand" <?php selected($surface, 'brand'); ?>>Brand · azul de marca</option>
                    <option value="photo" <?php selected($surface, 'photo'); ?>>Foto protagonista</option>
                </select>
            </p>
        </div>

        <div class="tbx-slide-media-grid">
            <?php
            self::render_slide_media_field(
                'desktop',
                'Imagen desktop',
                $desktop_image_id,
                $desktop_image_url,
                $desktop_preview,
                'Recomendada: 1600 × 900 o superior.'
            );
            self::render_slide_media_field(
                'mobile',
                'Imagen móvil',
                $mobile_image_id,
                $mobile_image_url,
                $mobile_preview,
                'Opcional. Recomendada: 900 × 1200. Si queda vacía se reutiliza la imagen desktop.'
            );
            ?>
        </div>

        <p class="tbx-slide-note">
            <strong>Comportamiento:</strong> con un solo slide activo se renderiza como hero estático.
            Con dos o más, TIBOX Theme activa automáticamente flechas y navegación por puntos.
            No hay autoplay por defecto.
        </p>
        <?php
    }

    private static function render_slide_media_field(
        string $key,
        string $label,
        int $attachment_id,
        string $attachment_url,
        string $preview_url,
        string $help
    ): void {
        ?>
        <div class="tbx-slide-media">
            <strong><?php echo esc_html($label); ?></strong>
            <input
                type="hidden"
                id="tibox_slide_<?php echo esc_attr($key); ?>_image_id"
                name="tibox_slide_<?php echo esc_attr($key); ?>_image_id"
                value="<?php echo esc_attr((string) $attachment_id); ?>"
            >
            <input
                type="hidden"
                id="tibox_slide_<?php echo esc_attr($key); ?>_image_url"
                name="tibox_slide_<?php echo esc_attr($key); ?>_image_url"
                value="<?php echo esc_attr($attachment_url); ?>"
            >
            <div
                class="tbx-slide-media__preview"
                id="tibox_slide_<?php echo esc_attr($key); ?>_preview"
            >
                <?php if ($preview_url !== '') : ?>
                    <img src="<?php echo esc_url($preview_url); ?>" alt="">
                <?php else : ?>
                    <span>Sin imagen</span>
                <?php endif; ?>
            </div>
            <p>
                <button
                    type="button"
                    class="button tbx-slide-select-media"
                    data-target="<?php echo esc_attr($key); ?>"
                >Seleccionar imagen</button>
                <button
                    type="button"
                    class="button-link-delete tbx-slide-remove-media"
                    data-target="<?php echo esc_attr($key); ?>"
                    style="margin-left:10px"
                >Quitar</button>
            </p>
            <small><?php echo esc_html($help); ?></small>
        </div>
        <?php
    }

    public static function save_slide_meta(int $post_id): void
    {
        if (
            !isset($_POST['tibox_home_slide_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['tibox_home_slide_nonce'])),
                'tibox_home_slide_save'
            )
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, '_tibox_slide_active', isset($_POST['tibox_slide_active']) ? '1' : '0');
        update_post_meta($post_id, '_tibox_slide_eyebrow', self::slide_text('tibox_slide_eyebrow'));
        update_post_meta($post_id, '_tibox_slide_headline', self::slide_text('tibox_slide_headline'));
        update_post_meta($post_id, '_tibox_slide_description', self::slide_textarea('tibox_slide_description'));
        $desktop_image_id = isset($_POST['tibox_slide_desktop_image_id']) ? absint(wp_unslash($_POST['tibox_slide_desktop_image_id'])) : 0;
        $mobile_image_id = isset($_POST['tibox_slide_mobile_image_id']) ? absint(wp_unslash($_POST['tibox_slide_mobile_image_id'])) : 0;

        $desktop_image_url = isset($_POST['tibox_slide_desktop_image_url'])
            ? esc_url_raw(wp_unslash($_POST['tibox_slide_desktop_image_url']))
            : '';
        $mobile_image_url = isset($_POST['tibox_slide_mobile_image_url'])
            ? esc_url_raw(wp_unslash($_POST['tibox_slide_mobile_image_url']))
            : '';

        /*
         * Si existe un ID válido, reconstruimos la URL desde WordPress.
         * Si por alguna razón no puede hacerlo, conservamos la URL enviada
         * por la biblioteca multimedia como respaldo.
         */
        if ($desktop_image_id > 0) {
            $resolved = wp_get_attachment_url($desktop_image_id);
            if ($resolved) {
                $desktop_image_url = $resolved;
            }
        }
        if ($mobile_image_id > 0) {
            $resolved = wp_get_attachment_url($mobile_image_id);
            if ($resolved) {
                $mobile_image_url = $resolved;
            }
        }

        update_post_meta($post_id, '_tibox_slide_desktop_image_id', $desktop_image_id);
        update_post_meta($post_id, '_tibox_slide_mobile_image_id', $mobile_image_id);
        update_post_meta($post_id, '_tibox_slide_desktop_image_url', $desktop_image_url);
        update_post_meta($post_id, '_tibox_slide_mobile_image_url', $mobile_image_url);

        /*
         * page-attributes expone menu_order, pero al usar este CPT minimalista
         * lo persistimos explícitamente para que el orden del slider sea fiable.
         */
        if (isset($_POST['menu_order'])) {
            $new_order = max(0, (int) wp_unslash($_POST['menu_order']));
            remove_action('save_post_' . self::SLIDE_POST_TYPE, [self::class, 'save_slide_meta']);
            wp_update_post([
                'ID' => $post_id,
                'menu_order' => $new_order,
            ]);
            add_action('save_post_' . self::SLIDE_POST_TYPE, [self::class, 'save_slide_meta']);
        }
        update_post_meta($post_id, '_tibox_slide_primary_label', self::slide_text('tibox_slide_primary_label'));
        update_post_meta($post_id, '_tibox_slide_primary_url', isset($_POST['tibox_slide_primary_url']) ? esc_url_raw(wp_unslash($_POST['tibox_slide_primary_url'])) : '');
        update_post_meta($post_id, '_tibox_slide_secondary_label', self::slide_text('tibox_slide_secondary_label'));
        update_post_meta($post_id, '_tibox_slide_secondary_url', isset($_POST['tibox_slide_secondary_url']) ? esc_url_raw(wp_unslash($_POST['tibox_slide_secondary_url'])) : '');

        $alignment = isset($_POST['tibox_slide_alignment'])
            ? sanitize_key(wp_unslash($_POST['tibox_slide_alignment']))
            : 'left';
        if (!in_array($alignment, ['left', 'center'], true)) {
            $alignment = 'left';
        }
        update_post_meta($post_id, '_tibox_slide_alignment', $alignment);

        $surface = isset($_POST['tibox_slide_surface'])
            ? sanitize_key(wp_unslash($_POST['tibox_slide_surface']))
            : 'dark';
        if (!in_array($surface, ['dark', 'brand', 'photo'], true)) {
            $surface = 'dark';
        }
        update_post_meta($post_id, '_tibox_slide_surface', $surface);
    }

    private static function slide_text(string $key): string
    {
        return isset($_POST[$key])
            ? sanitize_text_field(wp_unslash($_POST[$key]))
            : '';
    }

    private static function slide_textarea(string $key): string
    {
        return isset($_POST[$key])
            ? sanitize_textarea_field(wp_unslash($_POST[$key]))
            : '';
    }

    public static function slide_admin_assets(string $hook): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::SLIDE_POST_TYPE) {
            return;
        }

        wp_enqueue_media();

        $js = <<<'JS'
document.addEventListener('DOMContentLoaded', function(){
    function selectImage(target) {
        var frame = wp.media({
            title: 'Seleccionar imagen del slide',
            button: { text: 'Usar esta imagen' },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            var input = document.getElementById('tibox_slide_' + target + '_image_id');
            var urlInput = document.getElementById('tibox_slide_' + target + '_image_url');
            var preview = document.getElementById('tibox_slide_' + target + '_preview');
            if (!input || !preview) return;

            input.value = attachment.id || '';

            /*
             * Guardamos la URL original, no la miniatura, como respaldo.
             */
            var originalUrl = attachment.url || '';
            if (urlInput) urlInput.value = originalUrl;

            var url = attachment.sizes && attachment.sizes.medium_large
                ? attachment.sizes.medium_large.url
                : (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : originalUrl);
            preview.innerHTML = '<img src="' + String(url).replace(/"/g, '&quot;') + '" alt="">';
        });

        frame.open();
    }

    document.querySelectorAll('.tbx-slide-select-media').forEach(function(button){
        button.addEventListener('click', function(){
            selectImage(button.dataset.target);
        });
    });

    document.querySelectorAll('.tbx-slide-remove-media').forEach(function(button){
        button.addEventListener('click', function(){
            var target = button.dataset.target;
            var input = document.getElementById('tibox_slide_' + target + '_image_id');
            var urlInput = document.getElementById('tibox_slide_' + target + '_image_url');
            var preview = document.getElementById('tibox_slide_' + target + '_preview');
            if (input) input.value = '';
            if (urlInput) urlInput.value = '';
            if (preview) preview.innerHTML = '<span>Sin imagen</span>';
        });
    });
});
JS;

        wp_add_inline_script('media-editor', $js);
    }

    public static function slide_columns(array $columns): array
    {
        $result = [];

        foreach ($columns as $key => $label) {
            if ($key === 'date') {
                continue;
            }

            $result[$key] = $label;

            if ($key === 'title') {
                $result['tibox_slide_image'] = 'Imagen';
                $result['tibox_slide_active'] = 'Estado';
                $result['tibox_slide_order'] = 'Orden';
                $result['tibox_slide_headline'] = 'Titular';
            }
        }

        return $result;
    }

    public static function slide_column_content(string $column, int $post_id): void
    {
        if ($column === 'tibox_slide_image') {
            $image_id = (int) get_post_meta($post_id, '_tibox_slide_desktop_image_id', true);
            $image_url = (string) get_post_meta($post_id, '_tibox_slide_desktop_image_url', true);

            if ($image_id > 0) {
                $html = wp_get_attachment_image($image_id, [96, 54], false, [
                    'style' => 'width:96px;height:54px;object-fit:cover;border-radius:5px;display:block',
                    'alt' => '',
                ]);
                if ($html !== '') {
                    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    return;
                }
            }

            if ($image_url !== '') {
                echo '<img src="' . esc_url($image_url) . '" alt="" style="width:96px;height:54px;object-fit:cover;border-radius:5px;display:block">';
                return;
            }

            echo '<span style="color:#777">Sin imagen</span>';
        }

        if ($column === 'tibox_slide_active') {
            $active = get_post_meta($post_id, '_tibox_slide_active', true) !== '0';
            echo $active
                ? '<span style="color:#008a20;font-weight:700">Activo</span>'
                : '<span style="color:#646970">Inactivo</span>';
        }

        if ($column === 'tibox_slide_order') {
            echo esc_html((string) get_post_field('menu_order', $post_id));
        }

        if ($column === 'tibox_slide_headline') {
            $headline = (string) get_post_meta($post_id, '_tibox_slide_headline', true);
            echo $headline !== '' ? esc_html($headline) : '<span style="color:#777">—</span>';
        }
    }


    /**
     * -------------------------------------------------------------------------
     * Reparación de medios
     * -------------------------------------------------------------------------
     */

    public static function register_media_repair_page(): void
    {
        add_media_page(
            'Reparar miniaturas TIBOX',
            'Reparar miniaturas',
            'upload_files',
            'tibox-media-repair',
            [self::class, 'render_media_repair_page']
        );
    }

    public static function render_media_repair_page(): void
    {
        if (!current_user_can('upload_files')) {
            return;
        }

        $images = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $result = isset($_GET['tibox_media_result'])
            ? sanitize_key((string) $_GET['tibox_media_result'])
            : '';
        $ok = isset($_GET['tibox_media_ok']) ? absint($_GET['tibox_media_ok']) : 0;
        $errors = isset($_GET['tibox_media_errors']) ? absint($_GET['tibox_media_errors']) : 0;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        ?>
        <div class="wrap">
            <h1>Reparar miniaturas TIBOX</h1>

            <p>
                Diagnóstico de imágenes y reconstrucción de tamaños intermedios.
                La reconstrucción completa parte nuevamente desde el archivo original.
            </p>

            <?php if ($result === 'done') : ?>
                <div class="notice <?php echo $errors > 0 ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
                    <p>
                        <strong>Proceso terminado:</strong>
                        <?php echo esc_html((string) $ok); ?> imágenes procesadas correctamente ·
                        <?php echo esc_html((string) $errors); ?> con error.
                    </p>
                </div>
            <?php elseif ($result === 'force-done') : ?>
                <div class="notice <?php echo $errors > 0 ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
                    <p>
                        <strong>Reconstrucción completa terminada:</strong>
                        <?php echo esc_html((string) $ok); ?> imágenes reconstruidas ·
                        <?php echo esc_html((string) $errors); ?> con error.
                    </p>
                </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start">
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden">
                    <table class="widefat striped" style="border:0">
                        <thead>
                            <tr>
                                <th style="width:90px">Vista</th>
                                <th>Archivo</th>
                                <th style="width:250px">Diagnóstico</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($images)) : ?>
                                <tr><td colspan="3">No hay imágenes en la biblioteca.</td></tr>
                            <?php else : ?>
                                <?php foreach ($images as $image) :
                                    $meta = wp_get_attachment_metadata($image->ID);
                                    $original_url = wp_get_attachment_url($image->ID);
                                    $attached_file = get_attached_file($image->ID);
                                    $original_path = wp_get_original_image_path($image->ID);

                                    if (!$original_path) {
                                        $original_path = $attached_file;
                                    }

                                    $original_exists = is_string($original_path) && $original_path !== '' && file_exists($original_path);
                                    $original_readable = $original_exists && is_readable($original_path);

                                    $thumb_url = wp_get_attachment_image_url($image->ID, 'thumbnail');
                                    $sizes_count = is_array($meta) && isset($meta['sizes']) && is_array($meta['sizes'])
                                        ? count($meta['sizes'])
                                        : 0;

                                    $thumb_file = '';
                                    $thumb_exists = false;
                                    $thumb_readable = false;

                                    if (
                                        is_array($meta) &&
                                        isset($meta['sizes']['thumbnail']['file']) &&
                                        is_string($meta['sizes']['thumbnail']['file']) &&
                                        is_string($attached_file) &&
                                        $attached_file !== ''
                                    ) {
                                        $thumb_file = trailingslashit(dirname($attached_file)) . $meta['sizes']['thumbnail']['file'];
                                        $thumb_exists = file_exists($thumb_file);
                                        $thumb_readable = $thumb_exists && is_readable($thumb_file);
                                    }

                                    /*
                                     * Para la vista diagnóstica usamos primero la miniatura;
                                     * si no existe físicamente, mostramos el original.
                                     */
                                    $preview_url = $thumb_exists && $thumb_url ? $thumb_url : $original_url;
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($preview_url) : ?>
                                                <img
                                                    src="<?php echo esc_url($preview_url); ?>"
                                                    alt=""
                                                    style="width:72px;height:54px;object-fit:cover;border-radius:4px;display:block;background:#f0f0f1"
                                                >
                                            <?php else : ?>
                                                <span>—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <strong><?php echo esc_html(get_the_title($image)); ?></strong><br>

                                            <?php if ($original_url) : ?>
                                                <a href="<?php echo esc_url($original_url); ?>" target="_blank" rel="noopener">
                                                    Abrir original ↗
                                                </a>
                                            <?php endif; ?>

                                            <div style="margin-top:6px;color:#646970;font-size:12px;word-break:break-all">
                                                ID <?php echo esc_html((string) $image->ID); ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div style="margin-bottom:5px">
                                                Original:
                                                <?php if ($original_exists && $original_readable) : ?>
                                                    <strong style="color:#008a20">OK</strong>
                                                <?php elseif ($original_exists) : ?>
                                                    <strong style="color:#b32d2e">sin lectura</strong>
                                                <?php else : ?>
                                                    <strong style="color:#b32d2e">no existe</strong>
                                                <?php endif; ?>
                                            </div>

                                            <div style="margin-bottom:5px">
                                                Metadata:
                                                <?php if (is_array($meta)) : ?>
                                                    <strong style="color:#008a20">OK</strong>
                                                    · <?php echo esc_html((string) $sizes_count); ?> tamaños
                                                <?php else : ?>
                                                    <strong style="color:#b32d2e">faltante</strong>
                                                <?php endif; ?>
                                            </div>

                                            <div>
                                                Thumbnail físico:
                                                <?php if ($thumb_file === '') : ?>
                                                    <strong style="color:#b32d2e">sin registro</strong>
                                                <?php elseif ($thumb_exists && $thumb_readable) : ?>
                                                    <strong style="color:#008a20">OK</strong>
                                                <?php elseif ($thumb_exists) : ?>
                                                    <strong style="color:#b32d2e">sin lectura</strong>
                                                <?php else : ?>
                                                    <strong style="color:#b32d2e">NO EXISTE</strong>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-bottom:18px">
                        <h2 style="margin-top:0">1. Reparación normal</h2>
                        <p>
                            Crea únicamente los tamaños que WordPress considera
                            ausentes en sus metadatos.
                        </p>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="tibox_repair_media">
                            <?php wp_nonce_field('tibox_repair_media', 'tibox_repair_media_nonce'); ?>
                            <?php submit_button('Reparar tamaños faltantes', 'secondary', 'submit', false); ?>
                        </form>
                    </div>

                    <div style="background:#fff;border:2px solid #2271b1;border-radius:8px;padding:20px">
                        <h2 style="margin-top:0">2. Reconstrucción completa</h2>

                        <p>
                            <strong>Esta es la opción recomendada en tu caso.</strong>
                            Genera nuevamente los metadatos y todos los tamaños
                            desde el archivo original.
                        </p>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="tibox_force_rebuild_media">
                            <?php wp_nonce_field('tibox_force_rebuild_media', 'tibox_force_rebuild_media_nonce'); ?>
                            <?php submit_button('Forzar reconstrucción completa', 'primary', 'submit', false); ?>
                        </form>

                        <p style="margin:16px 0 0;color:#646970">
                            No elimina la imagen original. Los metadatos del adjunto
                            se reemplazan por los recién generados.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_media_repair(): void
    {
        if (!current_user_can('upload_files')) {
            wp_die('No tienes permisos para reparar medios.');
        }

        check_admin_referer('tibox_repair_media', 'tibox_repair_media_nonce');

        if (!function_exists('wp_update_image_subsizes')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $images = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => 100,
            'fields'         => 'ids',
        ]);

        $ok = 0;
        $errors = 0;

        foreach ($images as $attachment_id) {
            /*
             * wp_update_image_subsizes() crea metadatos completos cuando faltan,
             * o únicamente los subtamaños ausentes cuando los metadatos existen.
             */
            $result = wp_update_image_subsizes((int) $attachment_id);

            if (is_wp_error($result)) {
                $errors++;
            } else {
                $ok++;
            }
        }

        $url = add_query_arg([
            'page'               => 'tibox-media-repair',
            'tibox_media_result' => 'done',
            'tibox_media_ok'     => $ok,
            'tibox_media_errors' => $errors,
        ], admin_url('upload.php'));

        wp_safe_redirect($url);
        exit;
    }


    public static function handle_force_rebuild_media(): void
    {
        if (!current_user_can('upload_files')) {
            wp_die('No tienes permisos para reconstruir medios.');
        }

        check_admin_referer('tibox_force_rebuild_media', 'tibox_force_rebuild_media_nonce');

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $images = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => 100,
            'fields'         => 'ids',
        ]);

        $ok = 0;
        $errors = 0;

        foreach ($images as $attachment_id) {
            $attachment_id = (int) $attachment_id;

            /*
             * Preferimos el original real. Si WordPress no conserva una ruta
             * original separada, utilizamos el archivo adjunto actual.
             */
            $file = wp_get_original_image_path($attachment_id);
            if (!$file) {
                $file = get_attached_file($attachment_id);
            }

            if (
                !is_string($file) ||
                $file === '' ||
                !file_exists($file) ||
                !is_readable($file)
            ) {
                $errors++;
                continue;
            }

            /*
             * A diferencia de wp_update_image_subsizes(), esta función vuelve
             * a generar metadata y tamaños desde la imagen fuente.
             */
            $metadata = wp_generate_attachment_metadata($attachment_id, $file);

            if (!is_array($metadata) || empty($metadata)) {
                $errors++;
                continue;
            }

            $updated = wp_update_attachment_metadata($attachment_id, $metadata);

            /*
             * wp_update_attachment_metadata() puede devolver false si el valor
             * resultante es idéntico al existente. Eso no implica que la
             * generación haya fallado, por eso el criterio es $metadata.
             */
            $ok++;
        }

        $url = add_query_arg([
            'page'               => 'tibox-media-repair',
            'tibox_media_result' => 'force-done',
            'tibox_media_ok'     => $ok,
            'tibox_media_errors' => $errors,
        ], admin_url('upload.php'));

        wp_safe_redirect($url);
        exit;
    }

    public static function register_import_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            'Importar / Exportar JSON',
            'Importar JSON',
            'edit_posts',
            'tibox-catalog-json',
            [self::class, 'render_import_page']
        );
    }

    public static function render_import_page(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $example = [
            'version' => 1,
            'items' => [[
                'slug' => 'nombre-del-plan',
                'title' => 'Nombre del plan',
                'status' => 'draft',
                'order' => 10,
                'type' => 'plan',
                'categories' => ['Planes mensuales'],
                'summary' => 'Descripción corta.',
                'price' => '2 UF / mes',
                'badge' => 'BASE DIGITAL',
                'promo' => 'Lanzamiento: 1 UF / mes durante 3 meses',
                'platforms' => [
                    ['name' => 'WordPress', 'key' => 'wordpress'],
                    ['name' => 'SEO', 'key' => 'seo'],
                ],
                'value_proposition' => 'Propuesta principal.',
                'features' => [
                    'Beneficio uno.',
                    'Beneficio dos.',
                ],
                'cta_label' => 'Explorar plan',
                'cta_url' => '',
                'content' => '',
            ]],
        ];
        ?>
        <div class="wrap">
            <h1>Importar / Exportar Catálogo JSON</h1>
            <p>
                Crea o actualiza elementos del catálogo mediante un archivo JSON.
                Si el <code>slug</code> ya existe, el importador actualiza ese elemento en vez de duplicarlo.
            </p>

            <div style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:24px;align-items:start">
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px">
                    <h2 style="margin-top:0">Importar</h2>

                    <form
                        method="post"
                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                        enctype="multipart/form-data"
                    >
                        <?php wp_nonce_field('tibox_catalog_import_json', 'tibox_catalog_import_nonce'); ?>
                        <input type="hidden" name="action" value="tibox_catalog_import_json">

                        <p>
                            <label><strong>Archivo JSON</strong></label><br>
                            <input type="file" name="tibox_catalog_json_file" accept=".json,application/json">
                        </p>

                        <p><strong>o pega el JSON</strong></p>

                        <textarea
                            name="tibox_catalog_json_text"
                            rows="24"
                            class="large-text code"
                            spellcheck="false"
                            placeholder='{"version":1,"items":[...]}'
                        ></textarea>

                        <p>
                            <label>
                                <input type="checkbox" name="tibox_catalog_import_publish" value="1">
                                Forzar todos los elementos importados a <strong>Publicado</strong>
                            </label>
                        </p>

                        <?php submit_button('Importar catálogo'); ?>
                    </form>
                </div>

                <div>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;margin-bottom:20px">
                        <h2 style="margin-top:0">Exportar</h2>
                        <p>Descarga el catálogo actual como JSON reutilizable.</p>
                        <a
                            class="button button-secondary"
                            href="<?php echo esc_url(wp_nonce_url(
                                admin_url('admin-post.php?action=tibox_catalog_export_json'),
                                'tibox_catalog_export_json'
                            )); ?>"
                        >Descargar JSON</a>
                    </div>

                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px">
                        <h2 style="margin-top:0">Formato</h2>
                        <pre style="overflow:auto;max-height:520px;background:#1d2327;color:#f0f0f1;padding:14px;border-radius:5px"><?php
                            echo esc_html(wp_json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        ?></pre>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_json_import(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos para importar el catálogo.');
        }

        check_admin_referer('tibox_catalog_import_json', 'tibox_catalog_import_nonce');

        $raw = '';

        if (
            isset($_FILES['tibox_catalog_json_file']) &&
            is_array($_FILES['tibox_catalog_json_file']) &&
            (int) $_FILES['tibox_catalog_json_file']['error'] === UPLOAD_ERR_OK
        ) {
            $tmp = $_FILES['tibox_catalog_json_file']['tmp_name'];
            $raw = is_string($tmp) && is_readable($tmp)
                ? (string) file_get_contents($tmp)
                : '';
        }

        if ($raw === '' && isset($_POST['tibox_catalog_json_text'])) {
            $raw = trim((string) wp_unslash($_POST['tibox_catalog_json_text']));
        }

        if ($raw === '') {
            self::redirect_import_result(0, 0, 1, 'No se recibió contenido JSON.');
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            self::redirect_import_result(0, 0, 1, 'El JSON no es válido.');
        }

        $items = isset($data['items']) && is_array($data['items'])
            ? $data['items']
            : (array_is_list($data) ? $data : []);

        if ($items === []) {
            self::redirect_import_result(0, 0, 1, 'No se encontraron elementos en "items".');
        }

        $force_publish = !empty($_POST['tibox_catalog_import_publish']);

        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $errors++;
                continue;
            }

            $result = self::upsert_catalog_item($item, $force_publish);

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $errors++;
            }
        }

        self::redirect_import_result($created, $updated, $errors);
    }

    private static function upsert_catalog_item(array $item, bool $force_publish = false): string
    {
        $title = sanitize_text_field((string) ($item['title'] ?? ''));
        $slug = sanitize_title((string) ($item['slug'] ?? $title));

        if ($title === '' || $slug === '') {
            return 'error';
        }

        $existing = get_page_by_path($slug, OBJECT, self::POST_TYPE);

        $allowed_statuses = ['publish', 'draft', 'private', 'pending'];
        $status = $force_publish
            ? 'publish'
            : sanitize_key((string) ($item['status'] ?? 'draft'));

        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'draft';
        }

        $postarr = [
            'post_type' => self::POST_TYPE,
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => $status,
            'post_content' => wp_kses_post((string) ($item['content'] ?? '')),
            'post_excerpt' => sanitize_textarea_field((string) ($item['summary'] ?? '')),
            'menu_order' => isset($item['order']) ? (int) $item['order'] : 0,
        ];

        if ($existing instanceof WP_Post) {
            $postarr['ID'] = $existing->ID;
            $post_id = wp_update_post($postarr, true);
            $action = 'updated';
        } else {
            $post_id = wp_insert_post($postarr, true);
            $action = 'created';
        }

        if (is_wp_error($post_id) || !$post_id) {
            return 'error';
        }

        $allowed_types = ['servicio', 'plan', 'aplicacion', 'producto-digital', 'solucion'];
        $type = sanitize_key((string) ($item['type'] ?? 'servicio'));
        if (!in_array($type, $allowed_types, true)) {
            $type = 'servicio';
        }

        update_post_meta($post_id, '_tibox_catalog_type', $type);
        update_post_meta($post_id, '_tibox_catalog_summary', sanitize_textarea_field((string) ($item['summary'] ?? '')));
        update_post_meta($post_id, '_tibox_catalog_price', sanitize_text_field((string) ($item['price'] ?? '')));
        update_post_meta($post_id, '_tibox_catalog_badge', sanitize_text_field((string) ($item['badge'] ?? '')));
        update_post_meta($post_id, '_tibox_catalog_promo', sanitize_text_field((string) ($item['promo'] ?? '')));
        update_post_meta($post_id, '_tibox_catalog_value_prop', sanitize_text_field((string) ($item['value_proposition'] ?? '')));
        update_post_meta($post_id, '_tibox_catalog_cta_label', sanitize_text_field((string) ($item['cta_label'] ?? 'Explorar')));
        update_post_meta($post_id, '_tibox_catalog_cta_url', esc_url_raw((string) ($item['cta_url'] ?? '')));

        $features = [];
        if (isset($item['features']) && is_array($item['features'])) {
            foreach ($item['features'] as $feature) {
                $feature = sanitize_text_field((string) $feature);
                if ($feature !== '') {
                    $features[] = $feature;
                }
            }
        }
        update_post_meta($post_id, '_tibox_catalog_features', array_values($features));

        $platforms = [];
        if (isset($item['platforms']) && is_array($item['platforms'])) {
            foreach ($item['platforms'] as $platform) {
                if (is_string($platform)) {
                    $name = sanitize_text_field($platform);
                    $key = sanitize_key(sanitize_title($name));
                } elseif (is_array($platform)) {
                    $name = sanitize_text_field((string) ($platform['name'] ?? ''));
                    $key = sanitize_key((string) ($platform['key'] ?? sanitize_title($name)));
                } else {
                    continue;
                }

                if ($name !== '') {
                    $platforms[] = [
                        'name' => $name,
                        'key' => $key,
                    ];
                }
            }
        }
        update_post_meta($post_id, '_tibox_catalog_platforms', $platforms);

        if (isset($item['categories']) && is_array($item['categories'])) {
            $terms = [];
            foreach ($item['categories'] as $category) {
                $category = sanitize_text_field((string) $category);
                if ($category !== '') {
                    $terms[] = $category;
                }
            }

            if ($terms !== []) {
                wp_set_object_terms($post_id, $terms, self::TAXONOMY, false);
            }
        }

        return $action;
    }

    private static function redirect_import_result(
        int $created,
        int $updated,
        int $errors,
        string $message = ''
    ): void {
        $url = add_query_arg([
            'post_type' => self::POST_TYPE,
            'page' => 'tibox-catalog-json',
            'tibox_import_created' => $created,
            'tibox_import_updated' => $updated,
            'tibox_import_errors' => $errors,
            'tibox_import_message' => rawurlencode($message),
        ], admin_url('edit.php'));

        wp_safe_redirect($url);
        exit;
    }

    public static function admin_notices(): void
    {
        if (
            !isset($_GET['post_type'], $_GET['page']) ||
            sanitize_key((string) $_GET['post_type']) !== self::POST_TYPE ||
            sanitize_key((string) $_GET['page']) !== 'tibox-catalog-json'
        ) {
            return;
        }

        if (!isset($_GET['tibox_import_created'])) {
            return;
        }

        $created = absint($_GET['tibox_import_created']);
        $updated = absint($_GET['tibox_import_updated'] ?? 0);
        $errors = absint($_GET['tibox_import_errors'] ?? 0);
        $message = isset($_GET['tibox_import_message'])
            ? sanitize_text_field(rawurldecode((string) $_GET['tibox_import_message']))
            : '';

        $class = $errors > 0 && ($created + $updated) === 0
            ? 'notice notice-error'
            : ($errors > 0 ? 'notice notice-warning' : 'notice notice-success');

        echo '<div class="' . esc_attr($class) . '"><p>';
        echo '<strong>Importación TIBOX:</strong> ';
        echo esc_html(sprintf(
            '%d creados · %d actualizados · %d errores.',
            $created,
            $updated,
            $errors
        ));

        if ($message !== '') {
            echo ' ' . esc_html($message);
        }

        echo '</p></div>';
    }

    public static function handle_json_export(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos para exportar el catálogo.');
        }

        check_admin_referer('tibox_catalog_export_json');

        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
        ]);

        $items = [];

        foreach ($posts as $post) {
            $terms = wp_get_object_terms($post->ID, self::TAXONOMY, ['fields' => 'names']);
            if (is_wp_error($terms)) {
                $terms = [];
            }

            $platforms = get_post_meta($post->ID, '_tibox_catalog_platforms', true);
            $features = get_post_meta($post->ID, '_tibox_catalog_features', true);

            $items[] = [
                'slug' => $post->post_name,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'order' => (int) $post->menu_order,
                'type' => (string) get_post_meta($post->ID, '_tibox_catalog_type', true),
                'categories' => array_values((array) $terms),
                'summary' => (string) get_post_meta($post->ID, '_tibox_catalog_summary', true),
                'price' => (string) get_post_meta($post->ID, '_tibox_catalog_price', true),
                'badge' => (string) get_post_meta($post->ID, '_tibox_catalog_badge', true),
                'promo' => (string) get_post_meta($post->ID, '_tibox_catalog_promo', true),
                'platforms' => is_array($platforms) ? array_values($platforms) : [],
                'value_proposition' => (string) get_post_meta($post->ID, '_tibox_catalog_value_prop', true),
                'features' => is_array($features) ? array_values($features) : [],
                'cta_label' => (string) get_post_meta($post->ID, '_tibox_catalog_cta_label', true),
                'cta_url' => (string) get_post_meta($post->ID, '_tibox_catalog_cta_url', true),
                'content' => $post->post_content,
            ];
        }

        $payload = [
            'version' => 1,
            'exported_at' => wp_date(DATE_ATOM),
            'site' => home_url('/'),
            'items' => $items,
        ];

        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tibox-catalogo-' . wp_date('Y-m-d-His') . '.json"');

        echo wp_json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}


/**
 * API interna para el theme.
 *
 * Devuelve los slides activos y publicados en orden.
 */
function tibox_core_get_home_slides(): array
{
    if (!post_type_exists('tibox_home_slide')) {
        return [];
    }

    $posts = get_posts([
        'post_type'      => 'tibox_home_slide',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'ASC',
        ],
        'meta_query'     => [
            [
                'key'     => '_tibox_slide_active',
                'value'   => '1',
                'compare' => '=',
            ],
        ],
    ]);

    $slides = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $slides[] = [
            'id'                 => $post->ID,
            'admin_title'        => $post->post_title,
            'eyebrow'            => (string) get_post_meta($post->ID, '_tibox_slide_eyebrow', true),
            'headline'           => (string) get_post_meta($post->ID, '_tibox_slide_headline', true),
            'description'        => (string) get_post_meta($post->ID, '_tibox_slide_description', true),
            'desktop_image_id'   => (int) get_post_meta($post->ID, '_tibox_slide_desktop_image_id', true),
            'mobile_image_id'    => (int) get_post_meta($post->ID, '_tibox_slide_mobile_image_id', true),
            'desktop_image_url'  => (string) get_post_meta($post->ID, '_tibox_slide_desktop_image_url', true),
            'mobile_image_url'   => (string) get_post_meta($post->ID, '_tibox_slide_mobile_image_url', true),
            'primary_label'      => (string) get_post_meta($post->ID, '_tibox_slide_primary_label', true),
            'primary_url'        => (string) get_post_meta($post->ID, '_tibox_slide_primary_url', true),
            'secondary_label'    => (string) get_post_meta($post->ID, '_tibox_slide_secondary_label', true),
            'secondary_url'      => (string) get_post_meta($post->ID, '_tibox_slide_secondary_url', true),
            'alignment'          => (string) get_post_meta($post->ID, '_tibox_slide_alignment', true),
            'surface'            => (string) get_post_meta($post->ID, '_tibox_slide_surface', true),
            'order'              => (int) $post->menu_order,
        ];
    }

    return $slides;
}

require_once __DIR__ . '/includes/design-packages.php';

register_activation_hook(__FILE__, ['TIBOX_Core', 'activate']);
register_deactivation_hook(__FILE__, ['TIBOX_Core', 'deactivate']);
TIBOX_Core::init();
