<?php
namespace AgendaPro;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->load_textdomain();
		$this->init();
	}

	private function load_textdomain(): void {
		load_plugin_textdomain( 'agendapro', false, dirname( AGENDAPRO_PLUGIN_BASENAME ) . '/languages' );
	}

	private function init(): void {
		if ( get_option( 'agendapro_db_version' ) !== AGENDAPRO_VERSION ) {
			Installer::create_tables();
		}

		new Assets();
		new Admin\Menu();
		new Shortcodes();
		new API\Endpoints();
		new Webhook\Handler();
	}

	public function __clone() {}

	public function __wakeup() {}
}
