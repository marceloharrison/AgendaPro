<?php
/**
 * Plugin Name: AgendaPro – Sistema de Agendamento
 * Plugin URI:  https://harritec.com.br
 * Description: Sistema completo de agendamento com integração via webhook e IA.
 * Version:     2.0.0
 * Author:      AgendaPro
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: agendapro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package AgendaPro
 */

defined( 'ABSPATH' ) || exit;

define( 'AGENDAPRO_VERSION',       '2.0.0' );
define( 'AGENDAPRO_PLUGIN_FILE',   __FILE__ );
define( 'AGENDAPRO_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'AGENDAPRO_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'AGENDAPRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/* ── Autoloader ─────────────────────────────────────────────────────────── */
spl_autoload_register( function ( string $class_name ): void {
	if ( strpos( $class_name, 'AgendaPro\\' ) !== 0 ) {
		return;
	}
	$relative = str_replace( array( 'AgendaPro\\', '\\' ), array( '', DIRECTORY_SEPARATOR ), $class_name );
	$file     = AGENDAPRO_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/* ── Bootstrap ──────────────────────────────────────────────────────────── */
function agendapro(): AgendaPro\Plugin {
	return AgendaPro\Plugin::instance();
}
add_action( 'plugins_loaded', 'agendapro' );

register_activation_hook( __FILE__,   array( 'AgendaPro\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AgendaPro\\Installer', 'deactivate' ) );
