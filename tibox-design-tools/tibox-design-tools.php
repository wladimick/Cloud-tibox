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

// Restore branch source placeholder; production package is distributed as ZIP from the project artifact.
// Full implementation is intentionally maintained in the packaged release until the PR is refreshed.
