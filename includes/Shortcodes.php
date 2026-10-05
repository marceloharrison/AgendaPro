<?php
namespace AgendaPro;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	public function __construct() {
		add_shortcode( 'agendapro_booking',          array( $this, 'booking_form' ) );
		add_shortcode( 'agendapro_my_appointments',  array( $this, 'my_appointments' ) );
	}

	public function booking_form( array $atts ): string {
		ob_start();
		include AGENDAPRO_PLUGIN_DIR . 'public/views/booking-form.php';
		return ob_get_clean();
	}

	public function my_appointments( array $atts ): string {
		ob_start();
		include AGENDAPRO_PLUGIN_DIR . 'public/views/my-appointments.php';
		return ob_get_clean();
	}
}