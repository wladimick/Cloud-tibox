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
}
