<?php
/**
 * TIBOX Design Packages
 *
 * Importa ZIP de diseño integrados al theme:
 * - index.html
 * - style.css
 * - script.js
 * - assets/
 * - manifest.json opcional
 *
 * Seguridad:
 * - admins only
 * - sin extractTo()
 * - traversal bloqueado
 * - extensiones permitidas
 * - límites de archivos/tamaño
 * - PHP y configuración de servidor bloqueados
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TIBOX_Design_Packages
{
    public const POST_TYPE = 'tibox_design_package';
    private const OPTION_ASSIGNMENTS = 'tibox_design_assignments';
    private const STORAGE_DIR = 'tibox-design-packages';

    private const MAX_ZIP_BYTES = 26214400;      // 25 MB
    private const MAX_FILES = 150;
    private const MAX_TOTAL_BYTES = 104857600;   // 100 MB descomprimidos
    private const MAX_SINGLE_BYTES = 20971520;   // 20 MB por archivo

    private const TARGETS = [
        'home'            => 'Inicio',
        'page'            => 'Página',
        'catalog_single'  => 'Single catálogo',
        'catalog_archive' => 'Archivo catálogo',
        'single'          => 'Single general',
        'archive'         => 'Archivo general',
        '404'             => '404',
    ];

    private const ALLOWED_EXTENSIONS = [
        'html', 'css', 'js', 'json',
        'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'svg',
        'woff2',
    ];

    private const BLOCKED_BASENAMES = [
        '.htaccess',
        '.user.ini',
        'php.ini',
        'web.config',
        'wp-config.php',
    ];

    public static function init(): void
    {
        add_action('init', [self::class, 'register_post_type'], 5);
        add_action('admin_menu', [self::class, 'admin_menu'], 22);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);

        add_action('admin_post_tibox_design_import', [self::class, 'handle_import']);
        add_action('admin_post_tibox_design_activate', [self::class, 'handle_activate']);
        add_action('admin_post_tibox_design_deactivate', [self::class, 'handle_deactivate']);
        add_action('admin_post_tibox_design_delete', [self::class, 'handle_delete']);
        add_action('admin_post_tibox_design_save_assignments', [self::class, 'handle_save_assignments']);

        add_action('wp_enqueue_scripts', [self::class, 'enqueue_current_package_assets'], 30);
    }

    public static function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => 'Design Packages',
                'singular_name' => 'Design Package',
            ],
            'public'              => false,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'supports'            => ['title'],
        ]);
    }

    public static function admin_menu(): void
    {
        add_menu_page(
            'TIBOX Design',
            'TIBOX Design',
            'manage_options',
            'tibox-design',
            [self::class, 'render_packages_page'],
            'dashicons-layout',
            22
        );

        add_submenu_page(
            'tibox-design',
            'Paquetes',
            'Paquetes',
            'manage_options',
            'tibox-design',
            [self::class, 'render_packages_page']
        );

        add_submenu_page(
            'tibox-design',
            'Importar ZIP',
            'Importar ZIP',
            'manage_options',
            'tibox-design-import',
            [self::class, 'render_import_page']
        );

        add_submenu_page(
            'tibox-design',
            'Asignaciones',
            'Asignaciones',
            'manage_options',
            'tibox-design-assignments',
            [self::class, 'render_assignments_page']
        );
    }

    public static function admin_assets(string $hook): void
    {
        if (strpos($hook, 'tibox-design') === false) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_script('wp-dom-ready');

        $css = <<<'CSS'
.tbx-design{max-width:1440px}.tbx-design *{box-sizing:border-box}.tbx-design__hero{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;background:#101522;color:#fff;border:1px solid #2c3443;border-radius:12px;padding:24px;margin:18px 0}.tbx-design__hero h1{color:#fff;margin:0 0 7px}.tbx-design__hero p{color:#aab5ce;margin:0;max-width:850px}.tbx-design__badge{font:600 12px/1 Consolas,monospace;color:#8be9fd;border:1px solid #3d4b60;border-radius:999px;padding:8px 10px;white-space:nowrap}.tbx-design__grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:20px;align-items:start}.tbx-design__card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:20px;margin-bottom:18px}.tbx-design__card h2{margin-top:0}.tbx-design__drop{padding:28px;border:2px dashed #a7aaad;border-radius:9px;background:#f6f7f7}.tbx-design__field{margin-bottom:17px}.tbx-design__field label{display:block;font-weight:700;margin-bottom:6px}.tbx-design__field small{display:block;color:#646970;margin-top:5px}.tbx-design__table{background:#fff;border:1px solid #dcdcde;border-radius:9px;overflow:auto}.tbx-design__table table{width:100%;border-collapse:collapse}.tbx-design__table th,.tbx-design__table td{padding:12px 14px;text-align:left;vertical-align:top;border-bottom:1px solid #e2e4e7}.tbx-design__table th{background:#f6f7f7;font-size:12px}.tbx-design__table tr:last-child td{border-bottom:0}.tbx-design__target{display:inline-flex;padding:4px 7px;border-radius:5px;background:#edf5ff;color:#135e96;font-size:12px;font-weight:700}.tbx-design__active{display:inline-flex;align-items:center;gap:6px;color:#008a20;font-weight:700}.tbx-design__active:before{content:"";width:7px;height:7px;border-radius:50%;background:#00a32a}.tbx-design__actions{display:flex;flex-wrap:wrap;gap:6px}.tbx-design__manifest{overflow:auto;max-height:390px;background:#20242d;color:#f8f8f2;border-radius:7px;padding:14px;font:12px/1.5 Consolas,monospace;white-space:pre-wrap}.tbx-design__note{padding:12px 14px;border-left:4px solid #2271b1;background:#f0f6fc;margin:12px 0}.tbx-design__warn{border-left-color:#dba617;background:#fcf9e8}.tbx-design__assignment{display:grid;grid-template-columns:230px minmax(260px,1fr);gap:16px;align-items:center;padding:13px 0;border-bottom:1px solid #e2e4e7}.tbx-design__assignment:last-child{border-bottom:0}@media(max-width:900px){.tbx-design__grid{grid-template-columns:1fr}.tbx-design__assignment{grid-template-columns:1fr}.tbx-design__hero{display:block}.tbx-design__badge{display:inline-flex;margin-top:14px}}
CSS;
        wp_add_inline_style('wp-admin', $css);

        $js = <<<'JS'
document.addEventListener('DOMContentLoaded', function(){
  var target = document.getElementById('tbx-design-target');
  var pageRow = document.getElementById('tbx-design-page-row');
  function sync(){
    if(!target || !pageRow) return;
    pageRow.style.display = target.value === 'page' ? 'block' : 'none';
  }
  if(target){ target.addEventListener('change', sync); sync(); }
});
JS;
        wp_add_inline_script('wp-dom-ready', $js, 'after');
    }

    private static function targets(): array
    {
        return self::TARGETS;
    }

    private static function normalize_target(string $target): string
    {
        $target = strtolower(trim($target));
        $target = str_replace(['-', ' '], '_', $target);

        $aliases = [
            'homepage'        => 'home',
            'front_page'      => 'home',
            'frontpage'       => 'home',
            'catalog'         => 'catalog_archive',
            'catalogue'       => 'catalog_archive',
            'catalog_single'  => 'catalog_single',
            'single_catalog'  => 'catalog_single',
            'single_catalogo' => 'catalog_single',
            'catalog_archive' => 'catalog_archive',
            'archive_catalog' => 'catalog_archive',
            'archivo_catalogo'=> 'catalog_archive',
        ];

        if (isset($aliases[$target])) {
            $target = $aliases[$target];
        }

        return isset(self::TARGETS[$target]) ? $target : '';
    }

    private static function assignment_key(string $target, int $object_id = 0): string
    {
        if ($target === 'page' && $object_id > 0) {
            return 'page:' . $object_id;
        }

        return $target;
    }

    private static function assignments(): array
    {
        $stored = get_option(self::OPTION_ASSIGNMENTS, []);
        if (!is_array($stored)) {
            return [];
        }

        $clean = [];
        foreach ($stored as $key => $package_id) {
            $clean[sanitize_text_field((string) $key)] = absint($package_id);
        }
        return $clean;
    }

    private static function save_assignments(array $assignments): void
    {
        $clean = [];
        foreach ($assignments as $key => $package_id) {
            $package_id = absint($package_id);
            if ($package_id > 0 && get_post_type($package_id) === self::POST_TYPE) {
                $clean[sanitize_text_field((string) $key)] = $package_id;
            }
        }

        update_option(self::OPTION_ASSIGNMENTS, $clean, false);
    }

    public static function package_meta(int $package_id): array
    {
        if ($package_id <= 0 || get_post_type($package_id) !== self::POST_TYPE) {
            return [];
        }

        return [
            'id'               => $package_id,
            'name'             => (string) get_post_meta($package_id, '_tibox_design_name', true),
            'version'          => (string) get_post_meta($package_id, '_tibox_design_version', true),
            'target'           => (string) get_post_meta($package_id, '_tibox_design_target', true),
            'target_object_id' => (int) get_post_meta($package_id, '_tibox_design_target_object_id', true),
            'entry'            => (string) get_post_meta($package_id, '_tibox_design_entry', true),
            'css'              => (string) get_post_meta($package_id, '_tibox_design_css', true),
            'js'               => (string) get_post_meta($package_id, '_tibox_design_js', true),
            'package_dir'      => (string) get_post_meta($package_id, '_tibox_design_package_dir', true),
            'package_url'      => (string) get_post_meta($package_id, '_tibox_design_package_url', true),
            'file_count'       => (int) get_post_meta($package_id, '_tibox_design_file_count', true),
            'manifest'         => get_post_meta($package_id, '_tibox_design_manifest', true),
        ];
    }

    public static function is_active(int $package_id): bool
    {
        return in_array($package_id, array_values(self::assignments()), true);
    }

    public static function preview_package_id(): int
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return 0;
        }

        $package_id = isset($_GET['tibox_design_preview'])
            ? absint($_GET['tibox_design_preview'])
            : 0;

        $nonce = isset($_GET['_tbxnonce'])
            ? sanitize_text_field(wp_unslash($_GET['_tbxnonce']))
            : '';

        if (
            $package_id <= 0 ||
            get_post_type($package_id) !== self::POST_TYPE ||
            !wp_verify_nonce($nonce, 'tibox_design_preview_' . $package_id)
        ) {
            return 0;
        }

        return $package_id;
    }

    public static function get_package_for_target(string $target, int $object_id = 0): int
    {
        $target = self::normalize_target($target);
        if ($target === '') {
            return 0;
        }

        $preview_id = self::preview_package_id();
        if ($preview_id > 0) {
            $meta = self::package_meta($preview_id);
            $package_target = (string) ($meta['target'] ?? '');
            $package_object = (int) ($meta['target_object_id'] ?? 0);

            if (
                $package_target === $target &&
                (
                    $package_target !== 'page' ||
                    $package_object === 0 ||
                    $object_id === 0 ||
                    $package_object === $object_id
                )
            ) {
                return $preview_id;
            }
        }

        $assignments = self::assignments();

        if ($target === 'page' && $object_id > 0) {
            $specific_key = self::assignment_key('page', $object_id);
            if (!empty($assignments[$specific_key])) {
                return absint($assignments[$specific_key]);
            }
        }

        return !empty($assignments[$target])
            ? absint($assignments[$target])
            : 0;
    }

    public static function current_target(): array
    {
        if (is_404()) {
            return ['target' => '404', 'object_id' => 0];
        }

        if (is_front_page()) {
            return ['target' => 'home', 'object_id' => (int) get_queried_object_id()];
        }

        if (is_singular('tibox_catalog_item')) {
            return ['target' => 'catalog_single', 'object_id' => (int) get_queried_object_id()];
        }

        if (is_post_type_archive('tibox_catalog_item') || is_tax('tibox_catalog_cat')) {
            return ['target' => 'catalog_archive', 'object_id' => 0];
        }

        if (is_page()) {
            return ['target' => 'page', 'object_id' => (int) get_queried_object_id()];
        }

        if (is_singular()) {
            return ['target' => 'single', 'object_id' => (int) get_queried_object_id()];
        }

        if (is_archive() || is_home() || is_search()) {
            return ['target' => 'archive', 'object_id' => 0];
        }

        return ['target' => '', 'object_id' => 0];
    }

    public static function enqueue_current_package_assets(): void
    {
        $context = self::current_target();
        $target = (string) ($context['target'] ?? '');
        $object_id = (int) ($context['object_id'] ?? 0);

        if ($target === '') {
            return;
        }

        $package_id = self::get_package_for_target($target, $object_id);
        if ($package_id <= 0) {
            return;
        }

        $meta = self::package_meta($package_id);
        if (empty($meta)) {
            return;
        }

        $dir = trailingslashit((string) ($meta['package_dir'] ?? ''));
        $url = trailingslashit((string) ($meta['package_url'] ?? ''));
        $version = (string) ($meta['version'] ?? '1');

        $css = self::safe_relative_path((string) ($meta['css'] ?? ''));
        if ($css !== '' && file_exists($dir . $css) && filesize($dir . $css) > 0) {
            wp_enqueue_style(
                'tibox-design-package-' . $package_id,
                $url . $css,
                [],
                $version
            );
        }

        $js = self::safe_relative_path((string) ($meta['js'] ?? ''));
        if ($js !== '' && file_exists($dir . $js) && filesize($dir . $js) > 0) {
            wp_enqueue_script(
                'tibox-design-package-' . $package_id,
                $url . $js,
                [],
                $version,
                true
            );
        }
    }

    public static function render_package(int $package_id, array $tokens = []): string
    {
        $meta = self::package_meta($package_id);
        if (empty($meta)) {
            return '';
        }

        $dir = trailingslashit((string) $meta['package_dir']);
        $url = trailingslashit((string) $meta['package_url']);
        $entry = self::safe_relative_path((string) $meta['entry']);

        if ($entry === '' || !is_readable($dir . $entry)) {
            return '';
        }

        $html = (string) file_get_contents($dir . $entry);
        if ($html === '') {
            return '';
        }

        $package_tokens = [
            '{{PACKAGE_URL}}'        => esc_url($url),
            '{{PACKAGE_ASSETS_URL}}' => esc_url($url . 'assets/'),
        ];

        $html = strtr($html, array_merge($package_tokens, $tokens));
        $html = self::rewrite_relative_assets($html, $url);

        return $html;
    }

    private static function rewrite_relative_assets(string $html, string $base_url): string
    {
        $base_url = trailingslashit($base_url);

        $html = preg_replace_callback(
            '/\b(src|href|poster)\s*=\s*(["\'])(\.\/)?(assets\/[^"\']+)\2/i',
            static function (array $match) use ($base_url): string {
                return $match[1] . '=' . $match[2] . esc_url($base_url . $match[4]) . $match[2];
            },
            $html
        );

        $html = preg_replace_callback(
            '/\bsrcset\s*=\s*(["\'])([^"\']+)\1/i',
            static function (array $match) use ($base_url): string {
                $parts = array_map('trim', explode(',', $match[2]));
                $fixed = [];

                foreach ($parts as $part) {
                    if ($part === '') {
                        continue;
                    }

                    $bits = preg_split('/\s+/', $part, 2);
                    $src = (string) ($bits[0] ?? '');
                    $descriptor = (string) ($bits[1] ?? '');

                    if (preg_match('#^(?:\./)?assets/#i', $src)) {
                        $src = $base_url . preg_replace('#^\./#', '', $src);
                    }

                    $fixed[] = trim($src . ' ' . $descriptor);
                }

                return 'srcset=' . $match[1] . esc_attr(implode(', ', $fixed)) . $match[1];
            },
            $html
        );

        return is_string($html) ? $html : '';
    }

    public static function preview_url(int $package_id): string
    {
        $meta = self::package_meta($package_id);
        if (empty($meta)) {
            return '';
        }

        $target = (string) $meta['target'];
        $object_id = (int) $meta['target_object_id'];
        $url = '';

        if ($target === 'home') {
            $url = home_url('/');
        } elseif ($target === 'page') {
            if ($object_id > 0) {
                $url = get_permalink($object_id);
            } else {
                $page = get_posts([
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'post__not_in' => [(int) get_option('page_on_front')],
                ]);
                if (!empty($page)) {
                    $url = get_permalink($page[0]);
                }
            }
        } elseif ($target === 'catalog_single') {
            $item = get_posts([
                'post_type' => 'tibox_catalog_item',
                'post_status' => 'publish',
                'posts_per_page' => 1,
            ]);
            if (!empty($item)) {
                $url = get_permalink($item[0]);
            }
        } elseif ($target === 'catalog_archive') {
            $url = get_post_type_archive_link('tibox_catalog_item');
        } elseif ($target === 'single') {
            $post = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 1,
            ]);
            if (!empty($post)) {
                $url = get_permalink($post[0]);
            }
        } elseif ($target === 'archive') {
            $posts_page = (int) get_option('page_for_posts');
            $url = $posts_page > 0 ? get_permalink($posts_page) : home_url('/');
        } elseif ($target === '404') {
            $url = home_url('/tibox-design-preview-404-not-found/');
        }

        if (!$url) {
            return '';
        }

        return add_query_arg([
            'tibox_design_preview' => $package_id,
            '_tbxnonce' => wp_create_nonce('tibox_design_preview_' . $package_id),
        ], $url);
    }

    public static function render_packages_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $packages = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        self::render_header(
            'Design Packages',
            'Versiones importadas desde Claude Design u otras herramientas. Activar una versión no elimina las anteriores.'
        );

        self::render_notice_from_query();
        ?>
        <div class="tbx-design__card">
            <p style="margin-top:0">
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tibox-design-import')); ?>">
                    Importar nuevo ZIP
                </a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tibox-design-assignments')); ?>">
                    Ver asignaciones
                </a>
            </p>
        </div>

        <div class="tbx-design__table">
            <table>
                <thead>
                    <tr>
                        <th>Paquete</th>
                        <th>Destino</th>
                        <th>Versión</th>
                        <th>Archivos</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($packages)) : ?>
                    <tr><td colspan="7">Todavía no hay Design Packages importados.</td></tr>
                <?php else : ?>
                    <?php foreach ($packages as $package) :
                        $meta = self::package_meta($package->ID);
                        $target_label = self::target_label((string) $meta['target'], (int) $meta['target_object_id']);
                        $preview = self::preview_url($package->ID);
                        $activate_url = wp_nonce_url(
                            admin_url('admin-post.php?action=tibox_design_activate&package_id=' . $package->ID),
                            'tibox_design_activate_' . $package->ID
                        );
                        $delete_url = wp_nonce_url(
                            admin_url('admin-post.php?action=tibox_design_delete&package_id=' . $package->ID),
                            'tibox_design_delete_' . $package->ID
                        );
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($meta['name'] ?: $package->post_title); ?></strong><br>
                                <small>ID <?php echo esc_html((string) $package->ID); ?></small>
                            </td>
                            <td><span class="tbx-design__target"><?php echo esc_html($target_label); ?></span></td>
                            <td><code><?php echo esc_html($meta['version'] ?: '1.0.0'); ?></code></td>
                            <td><?php echo esc_html((string) $meta['file_count']); ?></td>
                            <td>
                                <?php if (self::is_active($package->ID)) : ?>
                                    <span class="tbx-design__active">Activo</span>
                                <?php else : ?>
                                    <span style="color:#646970">Disponible</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(get_the_date('d-m-Y H:i', $package)); ?></td>
                            <td>
                                <div class="tbx-design__actions">
                                    <?php if ($preview !== '') : ?>
                                        <a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url($preview); ?>">Vista previa</a>
                                    <?php endif; ?>
                                    <a class="button button-small" href="<?php echo esc_url($activate_url); ?>">Activar</a>
                                    <a
                                        class="button button-small button-link-delete"
                                        onclick="return confirm('¿Eliminar este paquete y sus archivos?');"
                                        href="<?php echo esc_url($delete_url); ?>"
                                    >Eliminar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        self::render_footer();
    }

    public static function render_import_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::render_header(
            'Importar Design Package',
            'Sube un ZIP generado por Claude Design. WordPress conserva Header, Footer, SEO y hooks; el ZIP reemplaza solo la plantilla de contenido asignada.'
        );
        ?>
        <div class="tbx-design__grid">

            <div class="tbx-design__card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="tibox_design_import">
                    <?php wp_nonce_field('tibox_design_import', 'tibox_design_import_nonce'); ?>

                    <div class="tbx-design__drop">
                        <div class="tbx-design__field">
                            <label for="tbx-design-zip">Archivo ZIP</label>
                            <input
                                id="tbx-design-zip"
                                type="file"
                                name="design_zip"
                                accept=".zip,application/zip"
                                required
                            >
                            <small>Máximo del importador TIBOX: 25 MB, 150 archivos y 100 MB descomprimidos.</small>
                        </div>
                    </div>

                    <div style="height:20px"></div>

                    <div class="tbx-design__field">
                        <label for="tbx-design-name">Nombre (opcional)</label>
                        <input id="tbx-design-name" class="regular-text" type="text" name="design_name" placeholder="Ej.: Tibox Cloud Home">
                        <small>Si el manifest contiene name, se utiliza automáticamente salvo que completes este campo.</small>
                    </div>

                    <div class="tbx-design__field">
                        <label for="tbx-design-version">Versión (opcional)</label>
                        <input id="tbx-design-version" class="regular-text" type="text" name="design_version" placeholder="Ej.: 1.2.0">
                    </div>

                    <div class="tbx-design__field">
                        <label for="tbx-design-target">Destino</label>
                        <select id="tbx-design-target" name="design_target">
                            <option value="">Detectar desde manifest.json</option>
                            <?php foreach (self::TARGETS as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tbx-design__field" id="tbx-design-page-row" style="display:none">
                        <label for="tbx-design-page">Página específica (opcional)</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'design_page_id',
                            'id' => 'tbx-design-page',
                            'show_option_none' => 'Todas las páginas normales',
                            'option_none_value' => '0',
                            'post_status' => 'publish',
                        ]);
                        ?>
                        <small>Si seleccionas una página, el paquete tendrá prioridad solo en ella.</small>
                    </div>

                    <p>
                        <label>
                            <input type="checkbox" name="design_activate" value="1" checked>
                            Activar automáticamente después de importar
                        </label>
                    </p>

                    <?php submit_button('Importar ZIP'); ?>
                </form>
            </div>

            <aside>
                <div class="tbx-design__card">
                    <h2>Estructura recomendada</h2>
                    <pre class="tbx-design__manifest">mi-diseno.zip
├── manifest.json
├── index.html
├── style.css
├── script.js
└── assets/
    ├── hero.webp
    └── icono.svg</pre>
                </div>

                <div class="tbx-design__note">
                    <strong>ZIP integrado:</strong> index.html no debe incluir
                    <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code>,
                    <code>&lt;body&gt;</code> ni <code>&lt;main&gt;</code>.
                    Header y Footer siguen siendo los globales de TIBOX Theme.
                </div>

                <div class="tbx-design__note tbx-design__warn">
                    Los ZIP de landing aislada siguen usando el importador de Landings.
                    Esta pantalla está diseñada para plantillas integradas al sitio.
                </div>
            </aside>

        </div>
        <?php
        self::render_footer();
    }

    public static function render_assignments_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $packages = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $assignments = self::assignments();

        self::render_header(
            'Asignaciones',
            'El paquete activo tiene prioridad sobre las plantillas HTML manuales del TIBOX Theme. Cambiar una asignación funciona como rollback.'
        );

        self::render_notice_from_query();
        ?>
        <form class="tbx-design__card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="tibox_design_save_assignments">
            <?php wp_nonce_field('tibox_design_save_assignments', 'tibox_design_assignments_nonce'); ?>

            <?php foreach (self::TARGETS as $target => $label) :
                if ($target === 'page') {
                    $key = 'page';
                } else {
                    $key = $target;
                }
                $current = absint($assignments[$key] ?? 0);
            ?>
                <div class="tbx-design__assignment">
                    <strong><?php echo esc_html($label); ?></strong>

                    <select name="assignment[<?php echo esc_attr($key); ?>]" class="widefat">
                        <option value="0">— Usar plantilla manual del Theme —</option>
                        <?php foreach ($packages as $package) :
                            $meta = self::package_meta($package->ID);
                            if ((string) $meta['target'] !== $target || (int) $meta['target_object_id'] > 0) {
                                continue;
                            }
                        ?>
                            <option value="<?php echo esc_attr((string) $package->ID); ?>" <?php selected($current, $package->ID); ?>>
                                <?php echo esc_html(($meta['name'] ?: $package->post_title) . ' · v' . ($meta['version'] ?: '1')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <?php submit_button('Guardar asignaciones'); ?>
        </form>

        <?php
        $specific = array_filter(
            $assignments,
            static fn($package_id, $key) => str_starts_with((string) $key, 'page:') && absint($package_id) > 0,
            ARRAY_FILTER_USE_BOTH
        );
        ?>

        <?php if (!empty($specific)) : ?>
            <div class="tbx-design__card">
                <h2>Asignaciones de páginas específicas</h2>
                <div class="tbx-design__table">
                    <table>
                        <thead><tr><th>Página</th><th>Paquete</th><th>Acción</th></tr></thead>
                        <tbody>
                        <?php foreach ($specific as $key => $package_id) :
                            $page_id = absint(substr((string) $key, 5));
                            $meta = self::package_meta(absint($package_id));
                            $deactivate = wp_nonce_url(
                                admin_url('admin-post.php?action=tibox_design_deactivate&assignment_key=' . rawurlencode((string) $key)),
                                'tibox_design_deactivate_' . $key
                            );
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(get_permalink($page_id)); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html(get_the_title($page_id) ?: ('Página #' . $page_id)); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html(($meta['name'] ?? '') . ' · v' . ($meta['version'] ?? '')); ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url($deactivate); ?>">Desactivar</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php
        self::render_footer();
    }

    private static function render_header(string $title, string $description): void
    {
        ?>
        <div class="wrap tbx-design">
            <div class="tbx-design__hero">
                <div>
                    <h1><?php echo esc_html($title); ?></h1>
                    <p><?php echo esc_html($description); ?></p>
                </div>
                <span class="tbx-design__badge">TIBOX DESIGN PACKAGES · v1</span>
            </div>
        <?php
    }

    private static function render_footer(): void
    {
        echo '</div>';
    }

    private static function render_notice_from_query(): void
    {
        $notice = isset($_GET['tibox_design_notice'])
            ? sanitize_key((string) $_GET['tibox_design_notice'])
            : '';

        if ($notice === 'imported') {
            $created = isset($_GET['package_id']) ? absint($_GET['package_id']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p><strong>Design Package importado correctamente.</strong> ID ' . esc_html((string) $created) . '.</p></div>';
        } elseif ($notice === 'activated') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Paquete activado.</strong> El cambio ya está aplicado en su destino.</p></div>';
        } elseif ($notice === 'assignments') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Asignaciones actualizadas.</strong></p></div>';
        } elseif ($notice === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Paquete eliminado.</strong></p></div>';
        } elseif ($notice === 'deactivated') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Asignación desactivada.</strong></p></div>';
        }
    }

    public static function handle_import(): void
    {
        self::require_admin();
        check_admin_referer('tibox_design_import', 'tibox_design_import_nonce');

        if (
            empty($_FILES['design_zip']) ||
            !is_array($_FILES['design_zip']) ||
            (int) $_FILES['design_zip']['error'] !== UPLOAD_ERR_OK
        ) {
            self::fail('No se recibió un ZIP válido.');
        }

        $tmp = (string) $_FILES['design_zip']['tmp_name'];
        $original_name = sanitize_file_name((string) $_FILES['design_zip']['name']);
        $size = (int) $_FILES['design_zip']['size'];

        if ($size <= 0 || $size > self::MAX_ZIP_BYTES) {
            self::fail('El ZIP supera el límite de 25 MB del importador TIBOX.');
        }

        if (strtolower(pathinfo($original_name, PATHINFO_EXTENSION)) !== 'zip') {
            self::fail('El archivo debe tener extensión .zip.');
        }

        if (!class_exists('ZipArchive')) {
            self::fail('El servidor no tiene ZipArchive disponible.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            self::fail('No fue posible abrir el ZIP.');
        }

        try {
            $entries = self::inspect_zip($zip);
            $common_root = self::common_root($entries);
            $normalized = self::normalize_entries($entries, $common_root);

            $manifest_raw = self::read_zip_text($zip, $normalized, 'manifest.json');
            $manifest = [];

            if ($manifest_raw !== '') {
                $decoded = json_decode($manifest_raw, true);
                if (!is_array($decoded)) {
                    self::fail('manifest.json existe, pero no contiene JSON válido.');
                }
                $manifest = $decoded;
            }

            $form_name = isset($_POST['design_name'])
                ? sanitize_text_field(wp_unslash($_POST['design_name']))
                : '';
            $form_version = isset($_POST['design_version'])
                ? sanitize_text_field(wp_unslash($_POST['design_version']))
                : '';
            $form_target = isset($_POST['design_target'])
                ? self::normalize_target(sanitize_key(wp_unslash($_POST['design_target'])))
                : '';
            $page_id = isset($_POST['design_page_id'])
                ? absint($_POST['design_page_id'])
                : 0;

            $manifest_target = self::normalize_target((string) ($manifest['target'] ?? ''));
            $target = $form_target !== '' ? $form_target : $manifest_target;

            if ($target === '') {
                self::fail('No se pudo determinar el destino. Selecciónalo en WordPress o inclúyelo en manifest.json.');
            }

            $manifest_type = strtolower(trim((string) ($manifest['type'] ?? 'template')));
            if (!in_array($manifest_type, ['template', 'integrated', 'integrated-template'], true)) {
                self::fail('Este importador solo acepta paquetes de plantilla integrada. Para landings aisladas utiliza el importador de Landings.');
            }

            if ($target !== 'page') {
                $page_id = 0;
            } elseif ($page_id <= 0 && !empty($manifest['target_object_id'])) {
                $page_id = absint($manifest['target_object_id']);
            }

            $entry = self::safe_relative_path((string) ($manifest['entry'] ?? 'index.html'));
            $css = self::safe_relative_path((string) ($manifest['css'] ?? 'style.css'));
            $js = self::safe_relative_path((string) ($manifest['js'] ?? 'script.js'));

            if ($entry === '' || !isset($normalized[$entry])) {
                self::fail('El paquete debe contener el archivo de entrada ' . ($entry ?: 'index.html') . '.');
            }

            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'html') {
                self::fail('El entry del paquete debe ser un archivo .html.');
            }

            if ($css !== '' && !isset($normalized[$css])) {
                $css = '';
            }

            if ($js !== '' && !isset($normalized[$js])) {
                $js = '';
            }

            $html = (string) self::read_zip_text($zip, $normalized, $entry);
            self::validate_integrated_html($html);
            self::validate_no_php($html, $entry);

            if ($css !== '') {
                self::validate_no_php(self::read_zip_text($zip, $normalized, $css), $css);
            }
            if ($js !== '') {
                self::validate_no_php(self::read_zip_text($zip, $normalized, $js), $js);
            }

            $name = $form_name !== ''
                ? $form_name
                : sanitize_text_field((string) ($manifest['name'] ?? ''));

            if ($name === '') {
                $name = preg_replace('/\.zip$/i', '', $original_name);
            }
            $name = sanitize_text_field((string) $name);

            $version = $form_version !== ''
                ? $form_version
                : sanitize_text_field((string) ($manifest['version'] ?? ''));

            if ($version === '') {
                $version = wp_date('Y.m.d-His');
            }

            $package_id = wp_insert_post([
                'post_type'   => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $name . ' · v' . $version,
            ], true);

            if (is_wp_error($package_id) || !$package_id) {
                self::fail('WordPress no pudo crear el registro del Design Package.');
            }

            $uploads = wp_upload_dir();
            if (!empty($uploads['error'])) {
                wp_delete_post($package_id, true);
                self::fail('WordPress no puede utilizar la carpeta uploads: ' . $uploads['error']);
            }

            $base_dir = trailingslashit($uploads['basedir']) . self::STORAGE_DIR . '/' . $package_id . '/';
            $base_url = trailingslashit($uploads['baseurl']) . self::STORAGE_DIR . '/' . $package_id . '/';

            if (!wp_mkdir_p($base_dir)) {
                wp_delete_post($package_id, true);
                self::fail('No fue posible crear la carpeta del paquete en uploads.');
            }

            try {
                self::extract_entries($zip, $normalized, $base_dir);
            } catch (Throwable $e) {
                self::remove_dir($base_dir);
                wp_delete_post($package_id, true);
                throw $e;
            }

            $stored_manifest = [
                'name'             => $name,
                'version'          => $version,
                'type'             => 'template',
                'mode'             => 'integrated',
                'target'           => $target,
                'target_object_id' => $page_id,
                'entry'            => $entry,
                'css'              => $css,
                'js'               => $js,
                'assets'           => (string) ($manifest['assets'] ?? 'assets'),
                'author'           => sanitize_text_field((string) ($manifest['author'] ?? '')),
                'source_filename'  => $original_name,
            ];

            update_post_meta($package_id, '_tibox_design_name', $name);
            update_post_meta($package_id, '_tibox_design_version', $version);
            update_post_meta($package_id, '_tibox_design_target', $target);
            update_post_meta($package_id, '_tibox_design_target_object_id', $page_id);
            update_post_meta($package_id, '_tibox_design_entry', $entry);
            update_post_meta($package_id, '_tibox_design_css', $css);
            update_post_meta($package_id, '_tibox_design_js', $js);
            update_post_meta($package_id, '_tibox_design_package_dir', $base_dir);
            update_post_meta($package_id, '_tibox_design_package_url', $base_url);
            update_post_meta($package_id, '_tibox_design_file_count', count($normalized));
            update_post_meta($package_id, '_tibox_design_manifest', $stored_manifest);

            if (!empty($_POST['design_activate'])) {
                self::activate_package((int) $package_id);
            }

            wp_safe_redirect(add_query_arg([
                'page'                => 'tibox-design',
                'tibox_design_notice' => 'imported',
                'package_id'          => $package_id,
            ], admin_url('admin.php')));
            exit;

        } catch (Throwable $e) {
            self::fail($e->getMessage());
        } finally {
            $zip->close();
        }
    }

    public static function handle_activate(): void
    {
        self::require_admin();

        $package_id = isset($_GET['package_id']) ? absint($_GET['package_id']) : 0;
        check_admin_referer('tibox_design_activate_' . $package_id);

        if ($package_id <= 0 || get_post_type($package_id) !== self::POST_TYPE) {
            self::fail('El paquete seleccionado no existe.');
        }

        self::activate_package($package_id);

        wp_safe_redirect(add_query_arg([
            'page'                => 'tibox-design',
            'tibox_design_notice' => 'activated',
        ], admin_url('admin.php')));
        exit;
    }

    private static function activate_package(int $package_id): void
    {
        $meta = self::package_meta($package_id);
        if (empty($meta)) {
            return;
        }

        $target = (string) $meta['target'];
        $object_id = (int) $meta['target_object_id'];
        $key = self::assignment_key($target, $object_id);

        $assignments = self::assignments();
        $assignments[$key] = $package_id;
        self::save_assignments($assignments);
    }

    public static function handle_deactivate(): void
    {
        self::require_admin();

        $key = isset($_GET['assignment_key'])
            ? sanitize_text_field(wp_unslash($_GET['assignment_key']))
            : '';

        check_admin_referer('tibox_design_deactivate_' . $key);

        $assignments = self::assignments();
        unset($assignments[$key]);
        self::save_assignments($assignments);

        wp_safe_redirect(add_query_arg([
            'page'                => 'tibox-design-assignments',
            'tibox_design_notice' => 'deactivated',
        ], admin_url('admin.php')));
        exit;
    }

    public static function handle_save_assignments(): void
    {
        self::require_admin();
        check_admin_referer('tibox_design_save_assignments', 'tibox_design_assignments_nonce');

        $incoming = isset($_POST['assignment']) && is_array($_POST['assignment'])
            ? wp_unslash($_POST['assignment'])
            : [];

        $assignments = self::assignments();

        foreach (array_keys(self::TARGETS) as $target) {
            $key = $target;
            $package_id = isset($incoming[$key]) ? absint($incoming[$key]) : 0;

            if ($package_id <= 0) {
                unset($assignments[$key]);
                continue;
            }

            $meta = self::package_meta($package_id);
            if (
                !empty($meta) &&
                (string) $meta['target'] === $target &&
                (int) $meta['target_object_id'] === 0
            ) {
                $assignments[$key] = $package_id;
            }
        }

        self::save_assignments($assignments);

        wp_safe_redirect(add_query_arg([
            'page'                => 'tibox-design-assignments',
            'tibox_design_notice' => 'assignments',
        ], admin_url('admin.php')));
        exit;
    }

    public static function handle_delete(): void
    {
        self::require_admin();

        $package_id = isset($_GET['package_id']) ? absint($_GET['package_id']) : 0;
        check_admin_referer('tibox_design_delete_' . $package_id);

        if ($package_id <= 0 || get_post_type($package_id) !== self::POST_TYPE) {
            self::fail('El paquete seleccionado no existe.');
        }

        $meta = self::package_meta($package_id);
        $dir = (string) ($meta['package_dir'] ?? '');

        $assignments = self::assignments();
        foreach ($assignments as $key => $value) {
            if (absint($value) === $package_id) {
                unset($assignments[$key]);
            }
        }
        self::save_assignments($assignments);

        if ($dir !== '') {
            self::remove_dir($dir);
        }

        wp_delete_post($package_id, true);

        wp_safe_redirect(add_query_arg([
            'page'                => 'tibox-design',
            'tibox_design_notice' => 'deleted',
        ], admin_url('admin.php')));
        exit;
    }

    private static function inspect_zip(ZipArchive $zip): array
    {
        if ($zip->numFiles <= 0) {
            self::fail('El ZIP está vacío.');
        }

        if ($zip->numFiles > self::MAX_FILES + 30) {
            self::fail('El ZIP contiene demasiadas entradas.');
        }

        $entries = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) {
                self::fail('No fue posible inspeccionar una entrada del ZIP.');
            }

            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            self::validate_zip_path($name);

            $size = (int) ($stat['size'] ?? 0);
            if ($size < 0 || $size > self::MAX_SINGLE_BYTES) {
                self::fail('Un archivo del ZIP supera el máximo permitido de 20 MB: ' . esc_html($name));
            }

            $total += $size;
            if ($total > self::MAX_TOTAL_BYTES) {
                self::fail('El contenido descomprimido supera 100 MB.');
            }

            self::validate_extension($name);

            $entries[] = [
                'index' => $i,
                'name'  => $name,
                'size'  => $size,
            ];
        }

        if (count($entries) > self::MAX_FILES) {
            self::fail('El ZIP contiene más de 150 archivos.');
        }

        return $entries;
    }

    private static function common_root(array $entries): string
    {
        if (empty($entries)) {
            return '';
        }

        $first_parts = explode('/', (string) $entries[0]['name']);
        if (count($first_parts) < 2) {
            return '';
        }

        $root = $first_parts[0] . '/';

        foreach ($entries as $entry) {
            if (!str_starts_with((string) $entry['name'], $root)) {
                return '';
            }
        }

        return $root;
    }

    private static function normalize_entries(array $entries, string $common_root): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            $relative = (string) $entry['name'];
            if ($common_root !== '' && str_starts_with($relative, $common_root)) {
                $relative = substr($relative, strlen($common_root));
            }

            $relative = self::safe_relative_path($relative);
            if ($relative === '') {
                continue;
            }

            if (isset($normalized[$relative])) {
                self::fail('El ZIP contiene archivos duplicados después de normalizar rutas: ' . $relative);
            }

            $normalized[$relative] = $entry;
        }

        return $normalized;
    }

    private static function validate_zip_path(string $name): void
    {
        if (
            str_starts_with($name, '/') ||
            preg_match('/^[A-Za-z]:\//', $name) ||
            str_contains($name, "\0")
        ) {
            self::fail('El ZIP contiene una ruta absoluta no permitida.');
        }

        $parts = explode('/', $name);
        foreach ($parts as $part) {
            if ($part === '..') {
                self::fail('El ZIP contiene una ruta insegura (../).');
            }
        }
    }

    private static function validate_extension(string $name): void
    {
        $basename = strtolower(basename($name));

        if (in_array($basename, self::BLOCKED_BASENAMES, true) || str_starts_with($basename, '.')) {
            self::fail('Archivo bloqueado dentro del ZIP: ' . $name);
        }

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            self::fail('Extensión no permitida dentro del ZIP: ' . $name);
        }
    }

    public static function safe_relative_path(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^\./+#', '', $path);
        $path = ltrim((string) $path, '/');

        if (
            $path === '' ||
            str_contains($path, "\0") ||
            preg_match('#(^|/)\.\.(/|$)#', $path)
        ) {
            return '';
        }

        return $path;
    }

    private static function read_zip_text(ZipArchive $zip, array $normalized, string $relative): string
    {
        $relative = self::safe_relative_path($relative);
        if ($relative === '' || !isset($normalized[$relative])) {
            return '';
        }

        $index = (int) $normalized[$relative]['index'];
        $content = $zip->getFromIndex($index);
        return is_string($content) ? $content : '';
    }

    private static function validate_no_php(string $content, string $filename): void
    {
        if (preg_match('/<\?(?:php|=)?/i', $content)) {
            self::fail('Se detectó código PHP dentro de ' . $filename . '. Los Design Packages no permiten PHP.');
        }
    }

    private static function validate_integrated_html(string $html): void
    {
        $blocked = [
            '<!doctype' => 'DOCTYPE',
            '<html'     => '<html>',
            '<head'     => '<head>',
            '<body'     => '<body>',
            '<main'     => '<main>',
        ];

        $lower = strtolower($html);
        foreach ($blocked as $needle => $label) {
            if (str_contains($lower, $needle)) {
                self::fail('index.html contiene ' . $label . '. Un Design Package integrado debe contener solo las secciones internas de la plantilla.');
            }
        }

        if (preg_match('/<script\b/i', $html)) {
            self::fail('index.html contiene <script>. Mueve el JavaScript a script.js.');
        }

        if (preg_match('/<style\b/i', $html)) {
            self::fail('index.html contiene <style>. Mueve el CSS a style.css.');
        }
    }

    private static function extract_entries(ZipArchive $zip, array $normalized, string $base_dir): void
    {
        $base_real = wp_normalize_path(trailingslashit($base_dir));

        foreach ($normalized as $relative => $entry) {
            $relative = self::safe_relative_path($relative);
            if ($relative === '') {
                continue;
            }

            $destination = wp_normalize_path($base_real . $relative);
            if (!str_starts_with($destination, $base_real)) {
                throw new RuntimeException('Ruta de extracción insegura detectada.');
            }

            $parent = dirname($destination);
            if (!wp_mkdir_p($parent)) {
                throw new RuntimeException('No fue posible crear una carpeta para ' . $relative);
            }

            $content = $zip->getFromIndex((int) $entry['index']);
            if (!is_string($content)) {
                throw new RuntimeException('No fue posible leer ' . $relative . ' desde el ZIP.');
            }

            if (file_put_contents($destination, $content, LOCK_EX) === false) {
                throw new RuntimeException('No fue posible guardar ' . $relative . '.');
            }

            @chmod($destination, 0644);
        }
    }

    private static function remove_dir(string $dir): void
    {
        $uploads = wp_upload_dir();
        $allowed_root = wp_normalize_path(
            trailingslashit($uploads['basedir']) . self::STORAGE_DIR . '/'
        );
        $dir = wp_normalize_path(trailingslashit($dir));

        if ($dir === $allowed_root || !str_starts_with($dir, $allowed_root) || !is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . $item;
            if (is_dir($path) && !is_link($path)) {
                self::remove_dir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private static function target_label(string $target, int $object_id = 0): string
    {
        $label = self::TARGETS[$target] ?? $target;

        if ($target === 'page' && $object_id > 0) {
            $page_title = get_the_title($object_id);
            if ($page_title !== '') {
                return $label . ': ' . $page_title;
            }
        }

        return $label;
    }

    private static function require_admin(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para administrar TIBOX Design.');
        }
    }

    private static function fail(string $message): void
    {
        wp_die(
            '<h1>TIBOX Design</h1><p>' . esc_html($message) . '</p><p><a href="' .
            esc_url(admin_url('admin.php?page=tibox-design-import')) .
            '">Volver al importador</a></p>',
            'TIBOX Design',
            ['response' => 400]
        );
    }
}

/*
 * API pública para TIBOX Theme.
 */
function tibox_design_get_package_for_target(string $target, int $object_id = 0): int
{
    return TIBOX_Design_Packages::get_package_for_target($target, $object_id);
}

function tibox_design_render_package(int $package_id, array $tokens = []): string
{
    return TIBOX_Design_Packages::render_package($package_id, $tokens);
}

function tibox_design_get_current_target(): array
{
    return TIBOX_Design_Packages::current_target();
}

function tibox_design_package_meta(int $package_id): array
{
    return TIBOX_Design_Packages::package_meta($package_id);
}

TIBOX_Design_Packages::init();
