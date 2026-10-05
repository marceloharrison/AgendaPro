<?php
namespace AgendaPro;

defined( 'ABSPATH' ) || exit;

class Assets {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'public_assets' ) );
	}

	public function admin_assets( string $hook ): void {
		if ( strpos( $hook, 'agendapro' ) === false ) {
			return;
		}

		wp_enqueue_script( 'agendapro-fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js',
			array(), '6.1.11', true );

		wp_enqueue_style( 'agendapro-admin',
			AGENDAPRO_PLUGIN_URL . 'admin/css/admin.css', array(), $this->asset_version( 'admin/css/admin.css' ) );
		wp_enqueue_script( 'agendapro-admin',
			AGENDAPRO_PLUGIN_URL . 'admin/js/admin.js',
			array( 'agendapro-fullcalendar' ),
			$this->asset_version( 'admin/js/admin.js' ), true );

		wp_localize_script( 'agendapro-admin', 'agendaproAdmin', array(
			'apiUrl'    => esc_url_raw( rest_url( 'agendapro/v1' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pluginUrl' => AGENDAPRO_PLUGIN_URL,
		) );
	}

	public function public_assets(): void {
		global $post;
		$content = is_a( $post, 'WP_Post' ) ? $post->post_content : '';
		$has_shortcode = has_shortcode( $content, 'agendapro_booking' )
		              || has_shortcode( $content, 'agendapro_my_appointments' );
		if ( ! $has_shortcode ) {
			return;
		}

		wp_enqueue_style( 'agendapro-public',
			AGENDAPRO_PLUGIN_URL . 'public/css/public.css', array(), $this->asset_version( 'public/css/public.css' ) );
		wp_add_inline_style( 'agendapro-public', $this->public_custom_css() );
		wp_enqueue_script( 'agendapro-public',
			AGENDAPRO_PLUGIN_URL . 'public/js/public.js',
			array( 'jquery' ), $this->asset_version( 'public/js/public.js' ), true );
		wp_localize_script( 'agendapro-public', 'agendaproPublic', array(
			'apiUrl' => esc_url_raw( rest_url( 'agendapro/v1' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
		) );
	}

	private function asset_version( string $relative_path ): string {
		$file = AGENDAPRO_PLUGIN_DIR . $relative_path;

		return file_exists( $file ) ? (string) filemtime( $file ) : AGENDAPRO_VERSION;
	}

	private function public_custom_css(): string {
		$button = sanitize_hex_color( get_option( 'agendapro_form_button_color', '#93BBFF' ) ) ?: '#93BBFF';
		$button_text = sanitize_hex_color( get_option( 'agendapro_form_button_text_color', '#FFFFFF' ) ) ?: '#FFFFFF';
		$text = sanitize_hex_color( get_option( 'agendapro_form_text_color', '#071F41' ) ) ?: '#071F41';
		$muted = sanitize_hex_color( get_option( 'agendapro_form_muted_color', '#52617A' ) ) ?: '#52617A';

		return "
			#agendapro-booking-form{--ap-custom-button:{$button};--ap-custom-button-text:{$button_text};--ap-custom-text:{$text};--ap-custom-muted:{$muted};}
			#agendapro-booking-form .ap-step-heading,
			#agendapro-booking-form .ap-pub-card__name{color:var(--ap-custom-text);}
			#agendapro-booking-form .ap-pub-card__sub{color:var(--ap-custom-muted);}
			#agendapro-booking-form .ap-pub-btn-primary{background:var(--ap-custom-button);border-color:var(--ap-custom-button);color:var(--ap-custom-button-text);}
			#agendapro-booking-form .ap-pub-btn-primary:hover:not(:disabled){background:var(--ap-custom-button);border-color:var(--ap-custom-button);filter:brightness(.95);}
		";
	}
}
