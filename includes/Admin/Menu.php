<?php
namespace AgendaPro\Admin;

defined( 'ABSPATH' ) || exit;

class Menu {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	public function register(): void {
		add_menu_page(
			__( 'AgendaPro', 'agendapro' ),
			__( 'AgendaPro', 'agendapro' ),
			'manage_options',
			'agendapro',
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			30
		);

		$subs = array(
			'agendapro'               => $this->menu_label( 'agendapro_menu_dashboard', __( 'Painel', 'agendapro' ) ),
			'agendapro-appointments'  => $this->menu_label( 'agendapro_menu_appointments', __( 'Agendamentos', 'agendapro' ) ),
			'agendapro-services'      => $this->menu_label( 'agendapro_menu_services', __( 'Serviços', 'agendapro' ) ),
			'agendapro-professionals' => $this->menu_label( 'agendapro_menu_professionals', __( 'Profissionais', 'agendapro' ) ),
			'agendapro-clients'       => $this->menu_label( 'agendapro_menu_clients', __( 'Clientes', 'agendapro' ) ),
			'agendapro-financial'     => $this->menu_label( 'agendapro_menu_financial', __( 'Financeiro', 'agendapro' ) ),
			'agendapro-settings'      => $this->menu_label( 'agendapro_menu_settings', __( 'Configurações', 'agendapro' ) ),
		);

		foreach ( $subs as $slug => $title ) {
			add_submenu_page( 'agendapro', $title, $title, 'manage_options', $slug, array( $this, 'render' ) );
		}
	}

	public function render(): void {
		echo '<div class="wrap"><div id="agendapro-root"></div></div>';
	}

	private function menu_label( string $option, string $fallback ): string {
		$label = trim( (string) get_option( $option, $fallback ) );
		return '' === $label ? $fallback : $label;
	}
}
