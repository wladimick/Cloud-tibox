<?php
/**
 * Plugin Name: TIBOX Design Tools
 * Description: Mejora TIBOX Design Packages y agrega una capa SEO/GEO/AEO para metadatos, schema, auditoría semántica y páginas comerciales.
 * Version: 0.3.1
 * Author: TIBOX
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: tibox-design-tools
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TIBOX_Design_Tools
{
    private const POST_TYPE = 'tibox_design_package';
    private const OPTION_ASSIGNMENTS = 'tibox_design_assignments';
    private const STORAGE_DIR = 'tibox-design-packages';
    private const SEO_OPTION = 'tibox_design_seo_settings';
    private const SEO_NONCE = 'tibox_design_tools_save_seo';

    private const MAX_ZIP_BYTES = 26214400;
    private const MAX_FILES = 150;
    private const MAX_TOTAL_BYTES = 104857600;
    private const MAX_SINGLE_BYTES = 20971520;

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
        '.htaccess', '.user.ini', 'php.ini', 'web.config', 'wp-config.php',
    ];

    private const SITE_PAGES = [
        'inicio' => [
            'title' => 'Inicio',
            'front_page' => true,
            'parent' => '',
            'schema' => 'WebPage',
            'seo_title' => 'TIBOX Cloud | Sitios web y soluciones digitales para empresas',
            'meta_description' => 'TIBOX Cloud crea y administra sitios web, ecommerce, hosting y soluciones digitales a medida para empresas, con respaldo técnico de TIBOX.',
            'aeo_summary' => 'TIBOX Cloud ayuda a empresas a crear, administrar y escalar su presencia digital con sitios web, ecommerce, hosting y soluciones a medida respaldadas por TIBOX.',
            'service_name' => '',
            'service_type' => '',
        ],
        'sitios-web' => [
            'title' => 'Sitios Web',
            'front_page' => false,
            'parent' => 'inicio',
            'schema' => 'CollectionPage',
            'seo_title' => 'Sitios web para empresas | TIBOX Cloud',
            'meta_description' => 'Sitios web profesionales para empresas, administrados por TIBOX. Hosting, respaldos, mantenimiento y soporte en planes mensuales.',
            'aeo_summary' => 'TIBOX Cloud crea y administra sitios web para empresas e incluye alternativas de presencia digital, ecommerce y hosting con soporte y mantenimiento.',
            'service_name' => 'Sitios web para empresas',
            'service_type' => 'Diseño, desarrollo y administración de sitios web empresariales',
        ],
        'presencia-digital' => [
            'title' => 'Presencia Digital',
            'front_page' => false,
            'parent' => 'sitios-web',
            'schema' => 'Service',
            'seo_title' => 'Presencia Digital para empresas | TIBOX Cloud',
            'meta_description' => 'Plan Presencia Digital de TIBOX Cloud: sitio web profesional, hosting, respaldos, mantenimiento y soporte administrados por TIBOX.',
            'aeo_summary' => 'Presencia Digital es un servicio administrado de TIBOX Cloud para empresas que necesitan un sitio web profesional con hosting, respaldos, mantenimiento y soporte.',
            'service_name' => 'Presencia Digital',
            'service_type' => 'Sitio web administrado para empresas',
        ],
        'soluciones-a-medida' => [
            'title' => 'Soluciones a Medida',
            'front_page' => false,
            'parent' => 'inicio',
            'schema' => 'CollectionPage',
            'seo_title' => 'Soluciones digitales a medida | TIBOX Cloud',
            'meta_description' => 'Desarrollo web, intranet corporativa y aplicaciones web a medida para empresas que necesitan procesos, integraciones o funcionalidades específicas.',
            'aeo_summary' => 'TIBOX desarrolla soluciones digitales a medida cuando una empresa necesita funcionalidades, integraciones o procesos que una solución estándar no cubre.',
            'service_name' => 'Soluciones digitales a medida',
            'service_type' => 'Desarrollo web, intranet corporativa y aplicaciones web empresariales',
        ],
        'contacto' => [
            'title' => 'Contacto',
            'front_page' => false,
            'parent' => 'inicio',
            'schema' => 'ContactPage',
            'seo_title' => 'Contacto | TIBOX Cloud',
            'meta_description' => 'Cuéntanos qué necesitas resolver y te ayudamos a identificar la alternativa adecuada para tu empresa.',
            'aeo_summary' => 'Contacta a TIBOX Cloud para revisar necesidades de sitios web, ecommerce, hosting o soluciones digitales a medida para tu empresa.',
            'service_name' => '',
            'service_type' => '',
        ],
    ];

    private const LEGACY_PAGE_SLUGS = ['catalogo'];

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'admin_menu'], 100);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets'], 100);
        add_action('admin_notices', [self::class, 'admin_notices']);

        add_action('admin_post_tibox_design_tools_import', [self::class, 'handle_import']);
        add_action('admin_post_tibox_design_tools_save_version', [self::class, 'handle_save_version']);
        add_action('admin_post_tibox_design_tools_reassign', [self::class, 'handle_reassign']);
        add_action('admin_post_tibox_design_tools_save_assignments', [self::class, 'handle_save_assignments']);
        add_action('admin_post_tibox_design_tools_ensure_structure', [self::class, 'handle_ensure_site_structure']);
        add_action('admin_post_tibox_design_tools_draft_legacy', [self::class, 'handle_draft_legacy_page']);

        add_action('admin_post_tibox_design_tools_save_seo', [self::class, 'handle_save_seo']);
        add_action('add_meta_boxes', [self::class, 'add_seo_meta_boxes']);
        add_action('save_post_page', [self::class, 'save_seo_meta_box']);
        add_action('save_post_tibox_catalog_item', [self::class, 'save_seo_meta_box']);

        add_filter('pre_get_document_title', [self::class, 'filter_document_title'], 20);
        add_filter('wp_robots', [self::class, 'filter_robots'], 20);
        add_filter('robots_txt', [self::class, 'filter_robots_txt'], 20, 2);
        add_action('template_redirect', [self::class, 'maybe_redirect_catalog_archive'], 1);
        add_action('wp_head', [self::class, 'render_seo_head'], 2);
    }

    public static function admin_menu(): void
    {
        if (!class_exists('TIBOX_Design_Packages')) {
            return;
        }

        $hook = get_plugin_page_hookname('tibox-design-import', 'tibox-design');
        if ($hook) {
            remove_action($hook, ['TIBOX_Design_Packages', 'render_import_page']);
            add_action($hook, [self::class, 'render_import_page']);
        }

        $assignments_hook = get_plugin_page_hookname('tibox-design-assignments', 'tibox-design');
        if ($assignments_hook) {
            remove_action($assignments_hook, ['TIBOX_Design_Packages', 'render_assignments_page']);
            add_action($assignments_hook, [self::class, 'render_assignments_page']);
        }

        add_submenu_page('tibox-design','Editor de código','Editor de código','manage_options','tibox-design-editor',[self::class,'render_editor_page']);
        add_submenu_page('tibox-design','Estructura del sitio','Estructura del sitio','manage_options','tibox-design-structure',[self::class,'render_site_structure_page']);
        add_submenu_page('tibox-design','SEO · GEO · AEO','SEO · GEO · AEO','manage_options','tibox-design-seo',[self::class,'render_seo_page']);
        add_submenu_page('tibox-design','Entorno y rendimiento','Entorno y rendimiento','manage_options','tibox-design-environment',[self::class,'render_environment_page']);
    }

    public static function admin_assets(string $hook): void
    {
        if (strpos($hook, 'tibox-design') === false) { return; }
        wp_enqueue_style('dashicons');
        wp_enqueue_script('wp-dom-ready');
        $editor_html = wp_enqueue_code_editor(['type' => 'text/html']);
        $editor_css  = wp_enqueue_code_editor(['type' => 'text/css']);
        $editor_js   = wp_enqueue_code_editor(['type' => 'text/javascript']);
        $css = <<<'CSS'
.tbxdt-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:20px;align-items:start}.tbxdt-card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:20px;margin:18px 0}.tbxdt-field{margin:0 0 18px}.tbxdt-field>label{display:block;font-weight:700;margin-bottom:6px}.tbxdt-field small{display:block;color:#646970;margin-top:5px}.tbxdt-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.tbxdt-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.tbxdt-code-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 10px}.tbxdt-code-tabs button{border:1px solid #c3c4c7;background:#f6f7f7;border-radius:4px;padding:7px 12px;cursor:pointer}.tbxdt-code-tabs button.is-active{background:#2271b1;border-color:#2271b1;color:#fff}.tbxdt-code-panel{display:none}.tbxdt-code-panel.is-active{display:block}.tbxdt-code-panel textarea{width:100%;min-height:520px;font:13px/1.5 Consolas,Monaco,monospace}.tbxdt-code-panel .CodeMirror{height:520px;min-height:520px}.tbxdt-code-panel .CodeMirror-scroll{min-height:520px}.tbxdt-warning{padding:12px 14px;border-left:4px solid #dba617;background:#fcf9e8;margin:12px 0}.tbxdt-info{padding:12px 14px;border-left:4px solid #2271b1;background:#f0f6fc;margin:12px 0}.tbxdt-status{display:inline-flex;padding:4px 7px;border-radius:999px;background:#f0f0f1;font-size:12px;font-weight:700}.tbxdt-status.is-active{background:#edfaef;color:#008a20}.tbxdt-select-package{max-width:680px}.tbxdt-select-package select{min-width:360px}.toplevel_page_tibox-design .tbxdt-edit-link{margin-left:4px}.tbxdt-page-row[hidden]{display:none!important}.tbxdt-structure-table{width:100%;border-collapse:collapse}.tbxdt-structure-table th,.tbxdt-structure-table td{padding:12px;border-bottom:1px solid #e2e4e7;text-align:left;vertical-align:middle}.tbxdt-ok{color:#008a20;font-weight:700}.tbxdt-missing{color:#b32d2e;font-weight:700}.tbxdt-muted{color:#646970}.tbxdt-url{font-family:Consolas,Monaco,monospace;font-size:12px}.tbxdt-assignment-group{background:#fff;border:1px solid #dcdcde;border-radius:9px;overflow:hidden;margin:0 0 20px}.tbxdt-assignment-group__head{padding:18px 20px;background:#f6f7f7;border-bottom:1px solid #dcdcde}.tbxdt-assignment-group__head h2{margin:0 0 5px}.tbxdt-assignment-group__head p{margin:0;color:#646970}.tbxdt-assignment-row{display:grid;grid-template-columns:minmax(190px,.7fr) minmax(320px,1.3fr) minmax(160px,.55fr);gap:18px;align-items:center;padding:15px 20px;border-bottom:1px solid #e2e4e7}.tbxdt-assignment-row:last-child{border-bottom:0}.tbxdt-assignment-row select{width:100%;max-width:620px}.tbxdt-assignment-row__meta{font-size:12px;color:#646970}.tbxdt-assignment-row__meta code{font-size:11px}.tbxdt-seo-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.tbxdt-kpi{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px}.tbxdt-kpi strong{display:block;font-size:20px;margin-top:5px}.tbxdt-audit{width:100%;border-collapse:collapse}.tbxdt-audit th,.tbxdt-audit td{padding:10px 12px;border-bottom:1px solid #e2e4e7;text-align:left;vertical-align:top}.tbxdt-audit th{background:#f6f7f7;font-size:12px}.tbxdt-score{display:inline-flex;align-items:center;justify-content:center;min-width:52px;padding:5px 8px;border-radius:999px;font-weight:700;background:#f0f0f1}.tbxdt-score.is-good{background:#edfaef;color:#008a20}.tbxdt-score.is-warn{background:#fcf9e8;color:#8a6500}.tbxdt-score.is-bad{background:#fcf0f1;color:#b32d2e}.tbxdt-seo-page-card{border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:14px 0;background:#fff}.tbxdt-seo-page-card h3{margin-top:0}.tbxdt-char{font-size:11px;color:#646970}.tbxdt-checks{display:flex;flex-wrap:wrap;gap:6px}.tbxdt-check{padding:4px 7px;border-radius:999px;font-size:11px;background:#f0f0f1}.tbxdt-check.ok{background:#edfaef;color:#008a20}.tbxdt-check.warn{background:#fcf9e8;color:#8a6500}.tbxdt-check.bad{background:#fcf0f1;color:#b32d2e}.tbxdt-env-table{width:100%;border-collapse:collapse}.tbxdt-env-table th,.tbxdt-env-table td{padding:11px 12px;border-bottom:1px solid #e2e4e7;text-align:left;vertical-align:top}.tbxdt-env-table th{width:260px;background:#f6f7f7}.tbxdt-rec{border-left:4px solid #2271b1;background:#f0f6fc;padding:14px 16px;margin:12px 0}.tbxdt-rec.is-good{border-color:#00a32a;background:#edfaef}.tbxdt-rec.is-warn{border-color:#dba617;background:#fcf9e8}.tbxdt-rec.is-bad{border-color:#d63638;background:#fcf0f1}.tbxdt-pill{display:inline-flex;padding:4px 8px;border-radius:999px;background:#f0f0f1;font-size:12px;font-weight:700}.tbxdt-pill.ok{background:#edfaef;color:#008a20}.tbxdt-pill.warn{background:#fcf9e8;color:#8a6500}@media(max-width:900px){.tbxdt-grid,.tbxdt-row,.tbxdt-assignment-row,.tbxdt-seo-grid{grid-template-columns:1fr}.tbxdt-select-package select{min-width:0;width:100%}}
CSS;
        wp_add_inline_style('wp-admin', $css);
        $html_settings = is_array($editor_html) ? wp_json_encode($editor_html) : 'null';
        $css_settings  = is_array($editor_css) ? wp_json_encode($editor_css) : 'null';
        $js_settings   = is_array($editor_js) ? wp_json_encode($editor_js) : 'null';
        $js = "document.addEventListener('DOMContentLoaded',function(){function syncPageRow(s){if(!s)return;var r=s.getAttribute('data-page-row');var e=r?document.getElementById(r):null;if(!e)return;e.hidden=s.value!=='page_specific'}document.querySelectorAll('[data-tbxdt-target]').forEach(function(s){s.addEventListener('change',function(){syncPageRow(s)});syncPageRow(s)});});";
        wp_add_inline_script('wp-dom-ready', $js, 'after');
    }

    public static function render_environment_page(): void
    {
        self::require_core();
        $env = self::environment_report();
        ?>
        <div class="wrap tbx-design"><div class="tbx-design__hero"><div><h1>Entorno y rendimiento</h1><p>Diagnóstico ejecutado dentro del propio WordPress para elegir caché, object cache y optimizaciones compatibles con el servidor real.</p></div><span class="tbx-design__badge">SERVER CHECK · v0.3.1</span></div><div class="tbxdt-grid"><div><div class="tbxdt-card"><h2>Servidor y WordPress</h2><table class="tbxdt-env-table"><tbody>
        <?php self::env_row('Servidor detectado',$env['server_label']); self::env_row('SERVER_SOFTWARE',$env['server_software']?:'No expuesto'); self::env_row('Cabecera HTTP Server',$env['server_header']?:'No expuesta'); self::env_row('PHP',PHP_VERSION.' · '.PHP_SAPI); self::env_row('WordPress',get_bloginfo('version')); self::env_row('Memoria PHP',(string)ini_get('memory_limit')); self::env_row('WP_MEMORY_LIMIT',defined('WP_MEMORY_LIMIT')?(string)WP_MEMORY_LIMIT:'No definido'); self::env_row('OPcache',$env['opcache']?'Disponible':'No detectado'); self::env_row('Imagick',$env['imagick']?'Disponible':'No detectado'); self::env_row('Cloudflare / proxy',$env['cloudflare']?'Cloudflare detectado':'No detectado en cabeceras'); self::env_row('Loopback HTTP',$env['loopback']); ?>
        </tbody></table></div><div class="tbxdt-card"><h2>Caché y object cache</h2><table class="tbxdt-env-table"><tbody>
        <?php self::env_row('WP_CACHE',(defined('WP_CACHE')&&WP_CACHE)?'Activado':'No activado'); self::env_row('Object cache externo',$env['object_cache']?'Activo':'No activo'); self::env_row('Drop-in object-cache.php',$env['object_cache_dropin']?'Presente':'No presente'); self::env_row('Redis PHP',$env['redis_available']?'Disponible':'No detectado'); self::env_row('Memcached PHP',$env['memcached_available']?'Disponible':'No detectado'); self::env_row('Plugin de caché activo',$env['cache_plugin']?:'Ninguno detectado'); self::env_row('Plugin SEO activo',$env['seo_plugin']?:'Ninguno detectado'); self::env_row('Cabeceras de caché',$env['cache_headers']?:'Sin señales claras'); ?>
        </tbody></table></div><div class="tbxdt-card"><h2>Cabeceras de seguridad visibles</h2><table class="tbxdt-env-table"><tbody><?php foreach($env['security_headers'] as $label=>$value){self::env_row($label,$value);} ?></tbody></table></div></div><aside><div class="tbxdt-card"><h2>Recomendación automática</h2><?php foreach($env['recommendations'] as $rec): ?><div class="tbxdt-rec <?php echo esc_attr($rec['class']); ?>"><strong><?php echo esc_html($rec['title']); ?></strong><p><?php echo esc_html($rec['text']); ?></p></div><?php endforeach; ?></div><div class="tbxdt-card"><h2>Regla TIBOX</h2><p><strong>No instales dos plugins de page cache al mismo tiempo.</strong></p><p>LiteSpeed Cache se recomienda para LiteSpeed/OpenLiteSpeed. Para nginx/Apache, WP Rocket es la opción preferida salvo que ya exista caché de servidor gestionada por infraestructura.</p><p>Redis Object Cache solo se recomienda si Redis está disponible en el servidor o el equipo de infraestructura confirma el servicio.</p></div></aside></div></div>
        <?php
    }

    private static function env_row(string $label,string $value): void{echo '<tr><th>'.esc_html($label).'</th><td><code>'.esc_html($value).'</code></td></tr>';}

    private static function environment_report(): array
    {
        $software=isset($_SERVER['SERVER_SOFTWARE'])?sanitize_text_field((string)$_SERVER['SERVER_SOFTWARE']):'';$headers=[];$loopback='No probado';
        $response=wp_remote_get(home_url('/'),['timeout'=>8,'redirection'=>3,'user-agent'=>'TIBOX-Design-Tools/0.3.1 Environment Check']);
        if(is_wp_error($response)){$loopback='Error: '.$response->get_error_message();}else{$loopback='OK · HTTP '.(int)wp_remote_retrieve_response_code($response);$raw=wp_remote_retrieve_headers($response);if(is_object($raw)&&method_exists($raw,'getAll')){$raw=$raw->getAll();}if(is_array($raw)){foreach($raw as $k=>$v){$headers[strtolower((string)$k)]=is_array($v)?implode(', ',array_map('strval',$v)):(string)$v;}}}
        $server_header=trim((string)($headers['server']??''));$all=strtolower($software.' '.$server_header.' '.($headers['x-powered-by']??''));$is_litespeed=str_contains($all,'litespeed')||defined('LITESPEED_SERVER_TYPE')||function_exists('litespeed_finish_request');$is_nginx=str_contains($all,'nginx');$is_apache=str_contains($all,'apache');$server_label=$is_litespeed?'LiteSpeed / OpenLiteSpeed':($is_nginx?'nginx':($is_apache?'Apache':'No concluyente'));
        $cloudflare=isset($headers['cf-ray'])||str_contains(strtolower($server_header),'cloudflare')||isset($headers['cf-cache-status']);$signals=[];foreach(['x-litespeed-cache','x-cache','x-cache-status','cf-cache-status','x-proxy-cache','x-fastcgi-cache','x-varnish','age'] as $k){if(!empty($headers[$k])){$signals[]=$k.': '.$headers[$k];}}
        if(!function_exists('get_plugins')){require_once ABSPATH.'wp-admin/includes/plugin.php';}$active=array_map('strval',(array)get_option('active_plugins',[]));$map=['litespeed-cache/litespeed-cache.php'=>'LiteSpeed Cache','wp-rocket/wp-rocket.php'=>'WP Rocket','w3-total-cache/w3-total-cache.php'=>'W3 Total Cache','wp-super-cache/wp-cache.php'=>'WP Super Cache','sg-cachepress/sg-cachepress.php'=>'SiteGround Optimizer','autoptimize/autoptimize.php'=>'Autoptimize'];$cache_plugin='';foreach($map as $f=>$n){if(in_array($f,$active,true)){$cache_plugin=$n;break;}}
        $seo_plugin=self::detected_seo_plugin();$redis=extension_loaded('redis')||class_exists('Redis')||extension_loaded('relay');$memcached=extension_loaded('memcached')||class_exists('Memcached');$object_cache=function_exists('wp_using_ext_object_cache')&&wp_using_ext_object_cache();$drop=file_exists(WP_CONTENT_DIR.'/object-cache.php');$opcache=function_exists('opcache_get_status')||extension_loaded('Zend OPcache');$imagick=extension_loaded('imagick')||class_exists('Imagick');
        $security=['Strict-Transport-Security'=>!empty($headers['strict-transport-security'])?'Presente':'Ausente','Content-Security-Policy'=>!empty($headers['content-security-policy'])?'Presente':'Ausente','X-Content-Type-Options'=>!empty($headers['x-content-type-options'])?'Presente':'Ausente','X-Frame-Options'=>!empty($headers['x-frame-options'])?'Presente':'Ausente','Referrer-Policy'=>!empty($headers['referrer-policy'])?'Presente':'Ausente','Permissions-Policy'=>!empty($headers['permissions-policy'])?'Presente':'Ausente'];
        $rec=[];if($is_litespeed){$rec[]=['class'=>'is-good','title'=>'Page cache: LiteSpeed Cache','text'=>'El servidor fue identificado como LiteSpeed/OpenLiteSpeed. Usa LiteSpeed Cache y no instales WP Rocket simultáneamente.'];}elseif($is_nginx||$is_apache){$rec[]=['class'=>'is-good','title'=>'Page cache: WP Rocket','text'=>'El servidor parece nginx/Apache. WP Rocket es la opción preferida para TIBOX Cloud, salvo que infraestructura ya tenga FastCGI/Varnish o caché equivalente.'];}else{$rec[]=['class'=>'is-warn','title'=>'Page cache: confirmar servidor','text'=>'El software del servidor está oculto o no fue concluyente. No instales LiteSpeed Cache por su page cache hasta confirmar LiteSpeed; WP Rocket es la opción más neutral si no existe caché de servidor.'];}
        if($cache_plugin!==''){$rec[]=['class'=>'is-warn','title'=>'Ya existe un plugin de rendimiento','text'=>'Se detectó '.$cache_plugin.'. Configúralo o reemplázalo; no agregues un segundo page cache.'];}if($redis&&!$object_cache){$rec[]=['class'=>'is-good','title'=>'Redis Object Cache: candidato','text'=>'La extensión Redis/Relay está disponible y WordPress no está usando object cache externo. Redis Object Cache puede aprovecharse si el servicio Redis está configurado.'];}elseif($object_cache){$rec[]=['class'=>'is-good','title'=>'Object cache: ya activo','text'=>'WordPress ya informa un object cache externo activo. No instales otro plugin de object cache sin revisar el drop-in existente.'];}else{$rec[]=['class'=>'is-warn','title'=>'Redis: no asumir disponibilidad','text'=>'No se detectó la extensión Redis. Solo instala Redis Object Cache si infraestructura habilita Redis/Relay y entrega conexión.'];}
        if($seo_plugin===''){$rec[]=['class'=>'is-good','title'=>'SEO: Rank Math es compatible','text'=>'No se detectó un plugin SEO principal. Rank Math puede asumir titles, canonical, sitemap, redirects y schema estándar; TIBOX Design Tools seguirá como auditor GEO/AEO.'];}else{$rec[]=['class'=>'is-good','title'=>'SEO existente: '.$seo_plugin,'text'=>'TIBOX Design Tools desactiva su salida SEO duplicada cuando detecta un plugin SEO compatible. Mantén un solo plugin SEO principal.'];}if($cloudflare){$rec[]=['class'=>'is-good','title'=>'Cloudflare detectado','text'=>'Hay señales de Cloudflare. Mantén una sola estrategia de caché y revisa exclusiones para wp-admin, previews y formularios.'];}
        return ['server_label'=>$server_label,'server_software'=>$software,'server_header'=>$server_header,'cloudflare'=>$cloudflare,'loopback'=>$loopback,'cache_headers'=>implode(' | ',$signals),'cache_plugin'=>$cache_plugin,'seo_plugin'=>$seo_plugin,'redis_available'=>$redis,'memcached_available'=>$memcached,'object_cache'=>$object_cache,'object_cache_dropin'=>$drop,'opcache'=>$opcache,'imagick'=>$imagick,'security_headers'=>$security,'recommendations'=>$rec];
    }

    private static function detected_seo_plugin(): string
    {
        if (defined('RANK_MATH_VERSION')) { return 'Rank Math'; }
        if (defined('WPSEO_VERSION')) { return 'Yoast SEO'; }
        if (defined('SEOPRESS_VERSION')) { return 'SEOPress'; }
        if (defined('AIOSEO_VERSION')) { return 'All in One SEO'; }
        return '';
    }

    private static function require_core(): void
    {
        if (!class_exists('TIBOX_Design_Packages') || !post_type_exists(self::POST_TYPE)) { wp_die('TIBOX Design Tools requiere TIBOX Core v0.4 o superior activo.'); }
    }
}

add_action('plugins_loaded', static function (): void { TIBOX_Design_Tools::init(); });
