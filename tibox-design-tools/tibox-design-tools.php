<?php
/**
 * Plugin Name: TIBOX Design Tools
 * Description: Mejora TIBOX Design Packages con destinos de página específicos, target_slug, reasignación segura y editor HTML/CSS/JS con versionado.
 * Version: 0.1.0
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

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'admin_menu'], 100);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets'], 100);
        add_action('admin_notices', [self::class, 'admin_notices']);
        add_action('admin_post_tibox_design_tools_import', [self::class, 'handle_import']);
        add_action('admin_post_tibox_design_tools_save_version', [self::class, 'handle_save_version']);
        add_action('admin_post_tibox_design_tools_reassign', [self::class, 'handle_reassign']);
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

        add_submenu_page(
            'tibox-design',
            'Editor de código',
            'Editor de código',
            'manage_options',
            'tibox-design-editor',
            [self::class, 'render_editor_page']
        );
    }

    public static function admin_assets(string $hook): void
    {
        if (strpos($hook, 'tibox-design') === false) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_script('wp-dom-ready');

        $editor_html = wp_enqueue_code_editor(['type' => 'text/html']);
        $editor_css  = wp_enqueue_code_editor(['type' => 'text/css']);
        $editor_js   = wp_enqueue_code_editor(['type' => 'text/javascript']);

        $css = <<<'CSS'
.tbxdt-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:20px;align-items:start}.tbxdt-card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:20px;margin:18px 0}.tbxdt-field{margin:0 0 18px}.tbxdt-field>label{display:block;font-weight:700;margin-bottom:6px}.tbxdt-field small{display:block;color:#646970;margin-top:5px}.tbxdt-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.tbxdt-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.tbxdt-code-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 10px}.tbxdt-code-tabs button{border:1px solid #c3c4c7;background:#f6f7f7;border-radius:4px;padding:7px 12px;cursor:pointer}.tbxdt-code-tabs button.is-active{background:#2271b1;border-color:#2271b1;color:#fff}.tbxdt-code-panel{display:none}.tbxdt-code-panel.is-active{display:block}.tbxdt-code-panel textarea{width:100%;min-height:520px;font:13px/1.5 Consolas,Monaco,monospace}.tbxdt-warning{padding:12px 14px;border-left:4px solid #dba617;background:#fcf9e8;margin:12px 0}.tbxdt-info{padding:12px 14px;border-left:4px solid #2271b1;background:#f0f6fc;margin:12px 0}.tbxdt-status{display:inline-flex;padding:4px 7px;border-radius:999px;background:#f0f0f1;font-size:12px;font-weight:700}.tbxdt-status.is-active{background:#edfaef;color:#008a20}.tbxdt-select-package{max-width:680px}.tbxdt-select-package select{min-width:360px}.toplevel_page_tibox-design .tbxdt-edit-link{margin-left:4px}.tbxdt-page-row[hidden]{display:none!important}@media(max-width:900px){.tbxdt-grid,.tbxdt-row{grid-template-columns:1fr}.tbxdt-select-package select{min-width:0;width:100%}}
CSS;
        wp_add_inline_style('wp-admin', $css);

        $html_settings = is_array($editor_html) ? wp_json_encode($editor_html) : 'null';
        $css_settings  = is_array($editor_css) ? wp_json_encode($editor_css) : 'null';
        $js_settings   = is_array($editor_js) ? wp_json_encode($editor_js) : 'null';

        $js = <<<JS
document.addEventListener('DOMContentLoaded', function(){
  function syncPageRow(select){
    if(!select) return;
    var rowId = select.getAttribute('data-page-row');
    var row = rowId ? document.getElementById(rowId) : null;
    if(!row) return;
    row.hidden = select.value !== 'page_specific';
  }
  document.querySelectorAll('[data-tbxdt-target]').forEach(function(select){
    select.addEventListener('change', function(){ syncPageRow(select); });
    syncPageRow(select);
  });
  document.querySelectorAll('[data-tbxdt-tab]').forEach(function(button){
    button.addEventListener('click', function(){
      var target = button.getAttribute('data-tbxdt-tab');
      document.querySelectorAll('[data-tbxdt-tab]').forEach(function(b){ b.classList.toggle('is-active', b === button); });
      document.querySelectorAll('[data-tbxdt-panel]').forEach(function(panel){ panel.classList.toggle('is-active', panel.getAttribute('data-tbxdt-panel') === target); });
    });
  });
  if(window.wp && wp.codeEditor){
    var settingsHtml = {$html_settings};
    var settingsCss = {$css_settings};
    var settingsJs = {$js_settings};
    if(document.getElementById('tbxdt-html') && settingsHtml){ wp.codeEditor.initialize('tbxdt-html', settingsHtml); }
    if(document.getElementById('tbxdt-css') && settingsCss){ wp.codeEditor.initialize('tbxdt-css', settingsCss); }
    if(document.getElementById('tbxdt-js') && settingsJs){ wp.codeEditor.initialize('tbxdt-js', settingsJs); }
  }
  if(document.body.classList.contains('toplevel_page_tibox-design')){
    document.querySelectorAll('.tbx-design__table tbody tr').forEach(function(row){
      var idNode = row.querySelector('td:first-child small');
      var actions = row.querySelector('.tbx-design__actions');
      if(!idNode || !actions || actions.querySelector('.tbxdt-edit-link')) return;
      var match = idNode.textContent.match(/ID\s+(\d+)/i);
      if(!match) return;
      var a = document.createElement('a');
      a.className = 'button button-small tbxdt-edit-link';
      a.href = 'admin.php?page=tibox-design-editor&package_id=' + encodeURIComponent(match[1]);
      a.textContent = 'Editar código';
      var first = actions.querySelector('a');
      if(first){ first.insertAdjacentElement('afterend', a); } else { actions.appendChild(a); }
    });
  }
});
JS;
        wp_add_inline_script('wp-dom-ready', $js, 'after');
    }

    public static function admin_notices(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || strpos((string) $screen->id, 'tibox-design') === false) {
            return;
        }
        $notice = isset($_GET['tbxdt_notice']) ? sanitize_key((string) $_GET['tbxdt_notice']) : '';
        if ($notice === 'imported') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Design Package importado.</strong> El destino quedó guardado correctamente.</p></div>';
        } elseif ($notice === 'saved_version') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Nueva versión creada.</strong> La versión anterior se conserva para rollback.</p></div>';
        } elseif ($notice === 'reassigned') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Destino corregido.</strong> El paquete ahora apunta a la página seleccionada.</p></div>';
        }
        $assignments = self::assignments();
        if (!empty($assignments['page'])) {
            $package_id = absint($assignments['page']);
            if ($package_id > 0) {
                $meta = self::package_meta($package_id);
                $name = $meta['name'] ?? ('Paquete #' . $package_id);
                $url = admin_url('admin.php?page=tibox-design-editor&package_id=' . $package_id);
                echo '<div class="notice notice-warning"><p><strong>Atención:</strong> hay una plantilla general de páginas activa (' . esc_html($name) . '). Esto puede afectar Contacto, Presencia Digital y otras páginas. <a href="' . esc_url($url) . '">Corregir destino ahora</a>.</p></div>';
            }
        }
    }

    public static function render_import_page(): void
    {
        self::require_core();
        ?>
        <div class="wrap tbx-design">
            <div class="tbx-design__hero">
                <div>
                    <h1>Importar Design Package</h1>
                    <p>Importa una plantilla integrada y asigna explícitamente su destino. Para páginas específicas puedes seleccionar la página en WordPress o declarar <code>target_slug</code> en manifest.json.</p>
                </div>
                <span class="tbx-design__badge">TIBOX DESIGN TOOLS · v0.1</span>
            </div>
            <?php self::render_error_from_query(); ?>
            <div class="tbxdt-grid">
                <div class="tbxdt-card">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="tibox_design_tools_import">
                        <?php wp_nonce_field('tibox_design_tools_import', 'tbxdt_nonce'); ?>
                        <div class="tbx-design__drop">
                            <div class="tbxdt-field">
                                <label for="tbxdt-zip">Archivo ZIP</label>
                                <input id="tbxdt-zip" type="file" name="design_zip" accept=".zip,application/zip" required>
                                <small>Máximo: 25 MB, 150 archivos y 100 MB descomprimidos.</small>
                            </div>
                        </div>
                        <div style="height:18px"></div>
                        <div class="tbxdt-row">
                            <div class="tbxdt-field"><label for="tbxdt-name">Nombre (opcional)</label><input id="tbxdt-name" class="widefat" type="text" name="design_name" placeholder="Ej.: TIBOX Cloud · Sitios Web"></div>
                            <div class="tbxdt-field"><label for="tbxdt-version">Versión (opcional)</label><input id="tbxdt-version" class="widefat" type="text" name="design_version" placeholder="Ej.: 1.0.1"></div>
                        </div>
                        <div class="tbxdt-field">
                            <label for="tbxdt-target">Destino</label>
                            <select id="tbxdt-target" name="design_target" data-tbxdt-target data-page-row="tbxdt-import-page-row">
                                <option value="auto">Detectar desde manifest.json</option>
                                <option value="home">Inicio</option>
                                <option value="page_specific">Página específica</option>
                                <option value="page_global">Plantilla general de páginas</option>
                                <option value="catalog_single">Single catálogo</option>
                                <option value="catalog_archive">Archivo catálogo</option>
                                <option value="single">Single general</option>
                                <option value="archive">Archivo general</option>
                                <option value="404">404</option>
                            </select>
                            <small>Usa “Página específica” para Sitios Web, Presencia Digital, Soluciones a Medida y Contacto.</small>
                        </div>
                        <div class="tbxdt-field tbxdt-page-row" id="tbxdt-import-page-row" hidden>
                            <label for="tbxdt-page">Página WordPress</label>
                            <?php wp_dropdown_pages(['name'=>'design_page_id','id'=>'tbxdt-page','show_option_none'=>'— Selecciona una página —','option_none_value'=>'0','post_status'=>'publish']); ?>
                        </div>
                        <p><label><input type="checkbox" name="design_activate" value="1"> Activar automáticamente después de importar</label></p>
                        <?php submit_button('Importar ZIP'); ?>
                    </form>
                </div>
                <aside>
                    <div class="tbxdt-card"><h2>Manifest recomendado</h2><pre class="tbx-design__manifest">{
  "name": "TIBOX Cloud · Sitios Web",
  "version": "1.0.1",
  "type": "template",
  "target": "page",
  "target_slug": "sitios-web",
  "entry": "index.html",
  "css": "style.css",
  "js": "script.js"
}</pre></div>
                    <div class="tbxdt-info"><strong>target_slug</strong> evita depender del ID interno de WordPress.</div>
                    <div class="tbxdt-warning"><strong>No uses “Plantilla general de páginas”</strong> para una página comercial individual.</div>
                </aside>
            </div>
        </div>
        <?php
    }

    public static function render_editor_page(): void
    {
        self::require_core();
        $package_id = isset($_GET['package_id']) ? absint($_GET['package_id']) : 0;
        $packages = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC']);
        ?>
        <div class="wrap tbx-design">
            <div class="tbx-design__hero"><div><h1>Editor de Design Packages</h1><p>Edita HTML, CSS y JavaScript sin modificar la versión original. El guardado crea una nueva versión y conserva el rollback.</p></div><span class="tbx-design__badge">EDITOR SEGURO · VERSIONADO</span></div>
            <?php self::render_error_from_query(); ?>
            <div class="tbxdt-card tbxdt-select-package"><form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>"><input type="hidden" name="page" value="tibox-design-editor"><label for="tbxdt-package-select"><strong>Paquete</strong></label><br><br><select id="tbxdt-package-select" name="package_id"><option value="0">— Selecciona un paquete —</option><?php foreach ($packages as $package) : $meta=self::package_meta($package->ID); $label=($meta['name']?:$package->post_title).' · v'.($meta['version']?:'1'); ?><option value="<?php echo esc_attr((string)$package->ID); ?>" <?php selected($package_id,$package->ID); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select> <button class="button">Abrir</button></form></div>
            <?php if ($package_id <= 0) { echo '</div>'; return; } $meta=self::package_meta($package_id); if(empty($meta)){ echo '<div class="notice notice-error"><p>El paquete seleccionado no existe.</p></div></div>'; return; }
            $dir=trailingslashit((string)$meta['package_dir']); $entry=self::safe_relative_path((string)$meta['entry']); $css_path=self::safe_relative_path((string)$meta['css']); $js_path=self::safe_relative_path((string)$meta['js']);
            $html=self::read_file($dir.$entry); $css=$css_path!==''?self::read_file($dir.$css_path):''; $js=$js_path!==''?self::read_file($dir.$js_path):'';
            $target=(string)$meta['target']; $object_id=(int)$meta['target_object_id']; $destination_mode=$target==='page'?($object_id>0?'page_specific':'page_global'):$target; $is_active=self::is_active($package_id); ?>
            <div class="tbxdt-card"><div class="tbxdt-actions"><h2 style="margin:0;flex:1"><?php echo esc_html($meta['name']?:('Paquete #'.$package_id)); ?> · v<?php echo esc_html($meta['version']?:'1'); ?></h2><span class="tbxdt-status <?php echo $is_active?'is-active':''; ?>"><?php echo $is_active?'ACTIVO':'DISPONIBLE'; ?></span><?php $preview=class_exists('TIBOX_Design_Packages')?TIBOX_Design_Packages::preview_url($package_id):''; if($preview): ?><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url($preview); ?>">Vista previa</a><?php endif; ?></div><?php if($is_active): ?><div class="tbxdt-warning"><strong>Esta versión está activa.</strong> Editar código no la sobrescribe: se creará una nueva versión.</div><?php endif; ?></div>
            <div class="tbxdt-grid"><div><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="tibox_design_tools_save_version"><input type="hidden" name="package_id" value="<?php echo esc_attr((string)$package_id); ?>"><?php wp_nonce_field('tibox_design_tools_save_version_'.$package_id,'tbxdt_nonce'); ?><div class="tbxdt-card"><div class="tbxdt-row"><div class="tbxdt-field"><label for="tbxdt-edit-name">Nombre</label><input class="widefat" id="tbxdt-edit-name" name="design_name" type="text" value="<?php echo esc_attr((string)$meta['name']); ?>"></div><div class="tbxdt-field"><label for="tbxdt-edit-version">Nueva versión</label><input class="widefat" id="tbxdt-edit-version" name="design_version" type="text" value="<?php echo esc_attr(self::next_version((string)$meta['version'])); ?>" required></div></div><?php self::render_destination_fields('edit',$destination_mode,$object_id); ?><div class="tbxdt-code-tabs" role="tablist"><button type="button" class="is-active" data-tbxdt-tab="html">HTML</button><button type="button" data-tbxdt-tab="css">CSS</button><button type="button" data-tbxdt-tab="js">JavaScript</button></div><div class="tbxdt-code-panel is-active" data-tbxdt-panel="html"><textarea id="tbxdt-html" name="design_html"><?php echo esc_textarea($html); ?></textarea></div><div class="tbxdt-code-panel" data-tbxdt-panel="css"><textarea id="tbxdt-css" name="design_css"><?php echo esc_textarea($css); ?></textarea></div><div class="tbxdt-code-panel" data-tbxdt-panel="js"><textarea id="tbxdt-js" name="design_js"><?php echo esc_textarea($js); ?></textarea></div><p><label><input type="checkbox" name="design_activate" value="1"> Activar esta nueva versión inmediatamente</label></p><div class="tbxdt-info">Los archivos de <code>assets/</code> se copian sin cambios a la nueva versión.</div><?php submit_button('Guardar como nueva versión'); ?></div></form></div>
                <aside><div class="tbxdt-card"><h2>Corregir solo el destino</h2><p>Úsalo para paquetes ya importados que aparecen simplemente como <strong>Página</strong>. No cambia código ni crea otra versión.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="tibox_design_tools_reassign"><input type="hidden" name="package_id" value="<?php echo esc_attr((string)$package_id); ?>"><?php wp_nonce_field('tibox_design_tools_reassign_'.$package_id,'tbxdt_nonce'); self::render_destination_fields('reassign',$destination_mode,$object_id); submit_button('Guardar destino','secondary'); ?></form></div><div class="tbxdt-card"><h2>Reglas del editor</h2><ul style="list-style:disc;padding-left:20px"><li>No se permite PHP.</li><li>HTML integrado sin <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code>, <code>&lt;body&gt;</code> ni <code>&lt;main&gt;</code>.</li><li>CSS permanece en style.css.</li><li>JavaScript permanece en script.js.</li><li>La versión anterior nunca se elimina al guardar una nueva.</li></ul></div></aside></div>
        </div>
        <?php
    }

    private static function render_destination_fields(string $prefix,string $mode,int $page_id): void
    {
        $select_id='tbxdt-'.$prefix.'-target'; $row_id='tbxdt-'.$prefix.'-page-row'; $page_select_id='tbxdt-'.$prefix.'-page'; ?>
        <div class="tbxdt-field"><label for="<?php echo esc_attr($select_id); ?>">Destino</label><select id="<?php echo esc_attr($select_id); ?>" name="design_target" data-tbxdt-target data-page-row="<?php echo esc_attr($row_id); ?>"><option value="home" <?php selected($mode,'home'); ?>>Inicio</option><option value="page_specific" <?php selected($mode,'page_specific'); ?>>Página específica</option><option value="page_global" <?php selected($mode,'page_global'); ?>>Plantilla general de páginas</option><option value="catalog_single" <?php selected($mode,'catalog_single'); ?>>Single catálogo</option><option value="catalog_archive" <?php selected($mode,'catalog_archive'); ?>>Archivo catálogo</option><option value="single" <?php selected($mode,'single'); ?>>Single general</option><option value="archive" <?php selected($mode,'archive'); ?>>Archivo general</option><option value="404" <?php selected($mode,'404'); ?>>404</option></select></div>
        <div class="tbxdt-field tbxdt-page-row" id="<?php echo esc_attr($row_id); ?>" <?php echo $mode==='page_specific'?'':'hidden'; ?>><label for="<?php echo esc_attr($page_select_id); ?>">Página WordPress</label><?php wp_dropdown_pages(['name'=>'design_page_id','id'=>$page_select_id,'selected'=>$page_id,'show_option_none'=>'— Selecciona una página —','option_none_value'=>'0','post_status'=>'publish']); ?></div>
        <?php
    }

    public static function handle_import(): void
    {
        self::require_admin(); self::require_core(); check_admin_referer('tibox_design_tools_import','tbxdt_nonce');
        if(empty($_FILES['design_zip'])||!is_array($_FILES['design_zip'])||(int)$_FILES['design_zip']['error']!==UPLOAD_ERR_OK){self::fail('No se recibió un ZIP válido.','tibox-design-import');}
        $tmp=(string)$_FILES['design_zip']['tmp_name']; $original_name=sanitize_file_name((string)$_FILES['design_zip']['name']); $size=(int)$_FILES['design_zip']['size'];
        if($size<=0||$size>self::MAX_ZIP_BYTES){self::fail('El ZIP supera el límite de 25 MB.','tibox-design-import');}
        if(strtolower(pathinfo($original_name,PATHINFO_EXTENSION))!=='zip'){self::fail('El archivo debe tener extensión .zip.','tibox-design-import');}
        if(!class_exists('ZipArchive')){self::fail('El servidor no tiene ZipArchive disponible.','tibox-design-import');}
        $zip=new ZipArchive(); if($zip->open($tmp)!==true){self::fail('No fue posible abrir el ZIP.','tibox-design-import');}
        try{
            $entries=self::inspect_zip($zip); $normalized=self::normalize_entries($entries,self::common_root($entries)); $manifest_raw=self::read_zip_text($zip,$normalized,'manifest.json'); $manifest=[];
            if($manifest_raw!==''){ $decoded=json_decode($manifest_raw,true); if(!is_array($decoded)){throw new RuntimeException('manifest.json no contiene JSON válido.');} $manifest=$decoded; }
            [$target,$page_id]=self::resolve_destination_from_request($manifest,true);
            $manifest_type=strtolower(trim((string)($manifest['type']??'template'))); if(!in_array($manifest_type,['template','integrated','integrated-template'],true)){throw new RuntimeException('Este importador solo acepta Design Packages integrados.');}
            $entry=self::safe_relative_path((string)($manifest['entry']??'index.html')); $css=self::safe_relative_path((string)($manifest['css']??'style.css')); $js=self::safe_relative_path((string)($manifest['js']??'script.js'));
            if($entry===''||!isset($normalized[$entry])){throw new RuntimeException('El paquete debe contener '.($entry?:'index.html').'.');} if(strtolower(pathinfo($entry,PATHINFO_EXTENSION))!=='html'){throw new RuntimeException('El entry debe ser un archivo .html.');}
            if($css!==''&&!isset($normalized[$css])){$css='';} if($js!==''&&!isset($normalized[$js])){$js='';}
            $html=self::read_zip_text($zip,$normalized,$entry); self::validate_integrated_html($html); self::validate_no_php($html,$entry); if($css!==''){self::validate_no_php(self::read_zip_text($zip,$normalized,$css),$css);} if($js!==''){self::validate_no_php(self::read_zip_text($zip,$normalized,$js),$js);}
            $name=isset($_POST['design_name'])?sanitize_text_field(wp_unslash($_POST['design_name'])):''; if($name===''){$name=sanitize_text_field((string)($manifest['name']??''));} if($name===''){$name=preg_replace('/\.zip$/i','',$original_name);}
            $version=isset($_POST['design_version'])?sanitize_text_field(wp_unslash($_POST['design_version'])):''; if($version===''){$version=sanitize_text_field((string)($manifest['version']??''));} if($version===''){$version=wp_date('Y.m.d-His');}
            $package_id=self::create_package_record($name,$version); [$base_dir,$base_url]=self::package_storage($package_id); self::extract_entries($zip,$normalized,$base_dir);
            $stored_manifest=self::build_manifest($manifest,$name,$version,$target,$page_id,$entry,$css,$js,$original_name); self::write_manifest_file($base_dir,$stored_manifest); self::store_package_meta($package_id,$stored_manifest,$base_dir,$base_url,self::count_files($base_dir)); if(!empty($_POST['design_activate'])){self::activate_package($package_id,false);}
            wp_safe_redirect(add_query_arg(['page'=>'tibox-design','tbxdt_notice'=>'imported','package_id'=>$package_id],admin_url('admin.php'))); exit;
        }catch(Throwable $e){self::fail($e->getMessage(),'tibox-design-import');}finally{$zip->close();}
    }

    public static function handle_save_version(): void
    {
        self::require_admin(); self::require_core(); $source_id=isset($_POST['package_id'])?absint($_POST['package_id']):0; check_admin_referer('tibox_design_tools_save_version_'.$source_id,'tbxdt_nonce'); $source=self::package_meta($source_id); if(empty($source)){self::fail('El paquete original no existe.','tibox-design-editor');}
        $name=isset($_POST['design_name'])?sanitize_text_field(wp_unslash($_POST['design_name'])):''; if($name===''){$name=(string)($source['name']??'Design Package');} $version=isset($_POST['design_version'])?sanitize_text_field(wp_unslash($_POST['design_version'])):''; if($version===''){$version=self::next_version((string)($source['version']??'1.0.0'));}
        [$target,$page_id]=self::resolve_destination_from_request([],false); $html=isset($_POST['design_html'])?(string)wp_unslash($_POST['design_html']):''; $css=isset($_POST['design_css'])?(string)wp_unslash($_POST['design_css']):''; $js=isset($_POST['design_js'])?(string)wp_unslash($_POST['design_js']):''; self::validate_integrated_html($html); self::validate_no_php($html,'index.html'); self::validate_no_php($css,'style.css'); self::validate_no_php($js,'script.js');
        $new_id=self::create_package_record($name,$version); [$base_dir,$base_url]=self::package_storage($new_id);
        try{ self::copy_dir((string)$source['package_dir'],$base_dir); $entry=self::safe_relative_path((string)($source['entry']?:'index.html'))?:'index.html'; $css_path=self::safe_relative_path((string)($source['css']?:'style.css'))?:'style.css'; $js_path=self::safe_relative_path((string)($source['js']?:'script.js'))?:'script.js'; self::write_file($base_dir.$entry,$html); self::write_file($base_dir.$css_path,$css); self::write_file($base_dir.$js_path,$js); $old_manifest=is_array($source['manifest']??null)?$source['manifest']:[]; $stored_manifest=self::build_manifest($old_manifest,$name,$version,$target,$page_id,$entry,$css_path,$js_path,'editor'); self::write_manifest_file($base_dir,$stored_manifest); self::store_package_meta($new_id,$stored_manifest,$base_dir,$base_url,self::count_files($base_dir)); if(!empty($_POST['design_activate'])){self::activate_package($new_id,true,$source_id);} }catch(Throwable $e){self::remove_dir($base_dir); wp_delete_post($new_id,true); self::fail($e->getMessage(),'tibox-design-editor',$source_id);}
        wp_safe_redirect(add_query_arg(['page'=>'tibox-design-editor','package_id'=>$new_id,'tbxdt_notice'=>'saved_version'],admin_url('admin.php'))); exit;
    }

    public static function handle_reassign(): void
    {
        self::require_admin(); self::require_core(); $package_id=isset($_POST['package_id'])?absint($_POST['package_id']):0; check_admin_referer('tibox_design_tools_reassign_'.$package_id,'tbxdt_nonce'); $meta=self::package_meta($package_id); if(empty($meta)){self::fail('El paquete seleccionado no existe.','tibox-design-editor');}
        [$target,$page_id]=self::resolve_destination_from_request([],false); $was_active=self::is_active($package_id); update_post_meta($package_id,'_tibox_design_target',$target); update_post_meta($package_id,'_tibox_design_target_object_id',$page_id); $manifest=is_array($meta['manifest']??null)?$meta['manifest']:[]; $manifest['target']=$target; $manifest['target_object_id']=$page_id; if($target==='page'&&$page_id>0){$page=get_post($page_id);$manifest['target_slug']=$page instanceof WP_Post?$page->post_name:'';}else{unset($manifest['target_slug']);} update_post_meta($package_id,'_tibox_design_manifest',$manifest); $dir=trailingslashit((string)$meta['package_dir']); if(is_dir($dir)){self::write_manifest_file($dir,$manifest);} if($was_active){$assignments=self::assignments();foreach($assignments as $key=>$value){if(absint($value)===$package_id){unset($assignments[$key]);}}$assignments[self::assignment_key($target,$page_id)]=$package_id;self::save_assignments($assignments);} wp_safe_redirect(add_query_arg(['page'=>'tibox-design-editor','package_id'=>$package_id,'tbxdt_notice'=>'reassigned'],admin_url('admin.php'))); exit;
    }

    private static function resolve_destination_from_request(array $manifest,bool $allow_auto): array
    {
        $raw=isset($_POST['design_target'])?sanitize_key((string)wp_unslash($_POST['design_target'])):($allow_auto?'auto':''); $page_id=isset($_POST['design_page_id'])?absint($_POST['design_page_id']):0;
        if($allow_auto&&($raw===''||$raw==='auto')){ $target=self::normalize_target((string)($manifest['target']??'')); if($target===''){throw new RuntimeException('No se pudo determinar el destino.');} if($target==='page'){ $slug=sanitize_title((string)($manifest['target_slug']??'')); if($slug!==''){ $page=get_page_by_path($slug,OBJECT,'page'); if(!$page instanceof WP_Post){throw new RuntimeException('manifest.json apunta a la página “'.$slug.'”, pero esa página no existe.');} return ['page',(int)$page->ID]; } $manifest_id=absint($manifest['target_object_id']??0); if($manifest_id>0&&get_post_type($manifest_id)==='page'){return ['page',$manifest_id];} throw new RuntimeException('El manifest usa target "page" pero no define target_slug. Elige “Página específica” o “Plantilla general de páginas”.'); } return [$target,0]; }
        if($raw==='page_specific'){if($page_id<=0||get_post_type($page_id)!=='page'){throw new RuntimeException('Debes seleccionar una página WordPress.');}return ['page',$page_id];} if($raw==='page_global'){return ['page',0];} $target=self::normalize_target($raw); if($target===''){throw new RuntimeException('Selecciona un destino válido.');} return [$target,0];
    }

    private static function normalize_target(string $target): string
    {
        $target=strtolower(trim($target));$target=str_replace(['-',' '],'_',$target);$aliases=['homepage'=>'home','front_page'=>'home','frontpage'=>'home','catalog'=>'catalog_archive','catalogue'=>'catalog_archive','single_catalog'=>'catalog_single','single_catalogo'=>'catalog_single','archive_catalog'=>'catalog_archive','archivo_catalogo'=>'catalog_archive'];if(isset($aliases[$target])){$target=$aliases[$target];}return isset(self::TARGETS[$target])?$target:'';
    }

    private static function package_meta(int $package_id): array { return class_exists('TIBOX_Design_Packages')?TIBOX_Design_Packages::package_meta($package_id):[]; }
    private static function is_active(int $package_id): bool { return in_array($package_id,array_values(self::assignments()),true); }
    private static function assignments(): array { $stored=get_option(self::OPTION_ASSIGNMENTS,[]);if(!is_array($stored)){return [];} $clean=[];foreach($stored as $key=>$value){$clean[sanitize_text_field((string)$key)]=absint($value);}return $clean; }
    private static function save_assignments(array $assignments): void { $clean=[];foreach($assignments as $key=>$value){$id=absint($value);if($id>0&&get_post_type($id)===self::POST_TYPE){$clean[sanitize_text_field((string)$key)]=$id;}}update_option(self::OPTION_ASSIGNMENTS,$clean,false); }
    private static function assignment_key(string $target,int $object_id=0): string { return $target==='page'&&$object_id>0?'page:'.$object_id:$target; }
    private static function activate_package(int $package_id,bool $replace_source=false,int $source_id=0): void { $meta=self::package_meta($package_id);if(empty($meta)){return;}$assignments=self::assignments();if($replace_source&&$source_id>0){foreach($assignments as $key=>$value){if(absint($value)===$source_id){unset($assignments[$key]);}}}$assignments[self::assignment_key((string)$meta['target'],(int)$meta['target_object_id'])]=$package_id;self::save_assignments($assignments); }

    private static function create_package_record(string $name,string $version): int { $package_id=wp_insert_post(['post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>$name.' · v'.$version],true);if(is_wp_error($package_id)||!$package_id){throw new RuntimeException('WordPress no pudo crear el Design Package.');}return (int)$package_id; }
    private static function package_storage(int $package_id): array { $uploads=wp_upload_dir();if(!empty($uploads['error'])){wp_delete_post($package_id,true);throw new RuntimeException('WordPress no puede utilizar uploads: '.$uploads['error']);}$dir=trailingslashit($uploads['basedir']).self::STORAGE_DIR.'/'.$package_id.'/';$url=trailingslashit($uploads['baseurl']).self::STORAGE_DIR.'/'.$package_id.'/';if(!wp_mkdir_p($dir)){wp_delete_post($package_id,true);throw new RuntimeException('No fue posible crear la carpeta del paquete.');}return [$dir,$url]; }
    private static function build_manifest(array $base,string $name,string $version,string $target,int $page_id,string $entry,string $css,string $js,string $source): array { $manifest=['name'=>$name,'version'=>$version,'type'=>'template','mode'=>'integrated','target'=>$target,'target_object_id'=>$page_id,'entry'=>$entry,'css'=>$css,'js'=>$js,'assets'=>(string)($base['assets']??'assets'),'author'=>sanitize_text_field((string)($base['author']??'TIBOX')),'source_filename'=>$source];if($target==='page'&&$page_id>0){$page=get_post($page_id);if($page instanceof WP_Post){$manifest['target_slug']=$page->post_name;}}return $manifest; }
    private static function store_package_meta(int $id,array $manifest,string $dir,string $url,int $file_count): void { update_post_meta($id,'_tibox_design_name',(string)$manifest['name']);update_post_meta($id,'_tibox_design_version',(string)$manifest['version']);update_post_meta($id,'_tibox_design_target',(string)$manifest['target']);update_post_meta($id,'_tibox_design_target_object_id',(int)$manifest['target_object_id']);update_post_meta($id,'_tibox_design_entry',(string)$manifest['entry']);update_post_meta($id,'_tibox_design_css',(string)$manifest['css']);update_post_meta($id,'_tibox_design_js',(string)$manifest['js']);update_post_meta($id,'_tibox_design_package_dir',$dir);update_post_meta($id,'_tibox_design_package_url',$url);update_post_meta($id,'_tibox_design_file_count',$file_count);update_post_meta($id,'_tibox_design_manifest',$manifest); }
    private static function write_manifest_file(string $base_dir,array $manifest): void { self::write_file(trailingslashit($base_dir).'manifest.json',wp_json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }

    private static function inspect_zip(ZipArchive $zip): array { if($zip->numFiles<=0){throw new RuntimeException('El ZIP está vacío.');}if($zip->numFiles>self::MAX_FILES+30){throw new RuntimeException('El ZIP contiene demasiadas entradas.');}$entries=[];$total=0;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);if(!is_array($stat)){throw new RuntimeException('No fue posible inspeccionar una entrada.');}$name=str_replace('\\','/',(string)($stat['name']??''));if($name===''||str_ends_with($name,'/')){continue;}self::validate_zip_path($name);$size=(int)($stat['size']??0);if($size<0||$size>self::MAX_SINGLE_BYTES){throw new RuntimeException('Un archivo supera 20 MB: '.$name);}$total+=$size;if($total>self::MAX_TOTAL_BYTES){throw new RuntimeException('El contenido descomprimido supera 100 MB.');}self::validate_extension($name);$entries[]=['index'=>$i,'name'=>$name,'size'=>$size];}if(count($entries)>self::MAX_FILES){throw new RuntimeException('El ZIP contiene más de 150 archivos.');}return $entries; }
    private static function common_root(array $entries): string { if(empty($entries)){return '';}$parts=explode('/',(string)$entries[0]['name']);if(count($parts)<2){return '';}$root=$parts[0].'/';foreach($entries as $entry){if(!str_starts_with((string)$entry['name'],$root)){return '';}}return $root; }
    private static function normalize_entries(array $entries,string $root): array { $normalized=[];foreach($entries as $entry){$relative=(string)$entry['name'];if($root!==''&&str_starts_with($relative,$root)){$relative=substr($relative,strlen($root));}$relative=self::safe_relative_path($relative);if($relative===''){continue;}if(isset($normalized[$relative])){throw new RuntimeException('El ZIP contiene rutas duplicadas: '.$relative);}$normalized[$relative]=$entry;}return $normalized; }
    private static function validate_zip_path(string $name): void { if(str_starts_with($name,'/')||preg_match('/^[A-Za-z]:\//',$name)||str_contains($name,"\0")){throw new RuntimeException('El ZIP contiene una ruta absoluta no permitida.');}foreach(explode('/',$name) as $part){if($part==='..'){throw new RuntimeException('El ZIP contiene una ruta insegura (../).');}} }
    private static function validate_extension(string $name): void { $basename=strtolower(basename($name));if(in_array($basename,self::BLOCKED_BASENAMES,true)||str_starts_with($basename,'.')){throw new RuntimeException('Archivo bloqueado: '.$name);}$ext=strtolower(pathinfo($basename,PATHINFO_EXTENSION));if(!in_array($ext,self::ALLOWED_EXTENSIONS,true)){throw new RuntimeException('Extensión no permitida: '.$name);} }
    private static function safe_relative_path(string $path): string { $path=str_replace('\\','/',trim($path));$path=preg_replace('#^\./+#','',$path);$path=ltrim((string)$path,'/');if($path===''||str_contains($path,"\0")||preg_match('#(^|/)\.\.(/|$)#',$path)){return '';}return $path; }
    private static function read_zip_text(ZipArchive $zip,array $normalized,string $relative): string { $relative=self::safe_relative_path($relative);if($relative===''||!isset($normalized[$relative])){return '';}$content=$zip->getFromIndex((int)$normalized[$relative]['index']);return is_string($content)?$content:''; }
    private static function validate_no_php(string $content,string $filename): void { if(preg_match('/<\?(?:php|=)?/i',$content)){throw new RuntimeException('Se detectó PHP dentro de '.$filename.'.');} }
    private static function validate_integrated_html(string $html): void { $blocked=['<!doctype'=>'DOCTYPE','<html'=>'<html>','<head'=>'<head>','<body'=>'<body>','<main'=>'<main>'];$lower=strtolower($html);foreach($blocked as $needle=>$label){if(str_contains($lower,$needle)){throw new RuntimeException('index.html contiene '.$label.'.');}}if(preg_match('/<script\b/i',$html)){throw new RuntimeException('index.html contiene <script>. Mueve JavaScript a script.js.');}if(preg_match('/<style\b/i',$html)){throw new RuntimeException('index.html contiene <style>. Mueve CSS a style.css.');} }
    private static function extract_entries(ZipArchive $zip,array $normalized,string $base_dir): void { $base=wp_normalize_path(trailingslashit($base_dir));foreach($normalized as $relative=>$entry){$relative=self::safe_relative_path($relative);if($relative===''){continue;}$dest=wp_normalize_path($base.$relative);if(!str_starts_with($dest,$base)){throw new RuntimeException('Ruta de extracción insegura.');}if(!wp_mkdir_p(dirname($dest))){throw new RuntimeException('No fue posible crear una carpeta para '.$relative);}$content=$zip->getFromIndex((int)$entry['index']);if(!is_string($content)||file_put_contents($dest,$content,LOCK_EX)===false){throw new RuntimeException('No fue posible guardar '.$relative.'.');}@chmod($dest,0644);} }
    private static function copy_dir(string $source,string $destination): void { $source=trailingslashit($source);$destination=trailingslashit($destination);if(!is_dir($source)){throw new RuntimeException('No se encuentra la carpeta original.');}if(!wp_mkdir_p($destination)){throw new RuntimeException('No fue posible crear la nueva carpeta.');}$items=scandir($source);if(!is_array($items)){throw new RuntimeException('No fue posible leer la carpeta original.');}foreach($items as $item){if($item==='.'||$item==='..'){continue;}$src=$source.$item;$dst=$destination.$item;if(is_dir($src)&&!is_link($src)){self::copy_dir($src,$dst);}else{if(!copy($src,$dst)){throw new RuntimeException('No fue posible copiar '.$item.'.');}@chmod($dst,0644);}} }
    private static function remove_dir(string $dir): void { $dir=trailingslashit($dir);if(!is_dir($dir)){return;}$items=scandir($dir);if(!is_array($items)){return;}foreach($items as $item){if($item==='.'||$item==='..'){continue;}$path=$dir.$item;if(is_dir($path)&&!is_link($path)){self::remove_dir($path);}else{@unlink($path);}}@rmdir($dir); }
    private static function count_files(string $dir): int { if(!is_dir($dir)){return 0;}$count=0;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $file){if($file->isFile()){$count++;}}return $count; }
    private static function read_file(string $path): string { if($path===''||!is_readable($path)){return '';}$content=file_get_contents($path);return is_string($content)?$content:''; }
    private static function write_file(string $path,string $content): void { if(!wp_mkdir_p(dirname($path))){throw new RuntimeException('No fue posible crear la carpeta para '.basename($path).'.');}if(file_put_contents($path,$content,LOCK_EX)===false){throw new RuntimeException('No fue posible guardar '.basename($path).'.');}@chmod($path,0644); }
    private static function next_version(string $version): string { if(preg_match('/^(\d+)\.(\d+)\.(\d+)$/',trim($version),$m)){return $m[1].'.'.$m[2].'.'.((int)$m[3]+1);}return wp_date('Y.m.d-His'); }
    private static function require_admin(): void { if(!current_user_can('manage_options')){wp_die('No tienes permisos para administrar TIBOX Design.');} }
    private static function require_core(): void { if(!class_exists('TIBOX_Design_Packages')||!post_type_exists(self::POST_TYPE)){wp_die('TIBOX Design Tools requiere TIBOX Core v0.4 o superior activo.');} }
    private static function fail(string $message,string $page,int $package_id=0): void { $args=['page'=>$page,'tbxdt_error'=>rawurlencode($message)];if($package_id>0){$args['package_id']=$package_id;}wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit; }
    private static function render_error_from_query(): void { $error=isset($_GET['tbxdt_error'])?sanitize_text_field(rawurldecode((string)$_GET['tbxdt_error'])):'';if($error!==''){echo '<div class="notice notice-error"><p><strong>Error:</strong> '.esc_html($error).'</p></div>';} }
}

add_action('plugins_loaded', static function (): void {
    TIBOX_Design_Tools::init();
});
