<?php
namespace AgendaPro\Webhook;

defined( 'ABSPATH' ) || exit;

use AgendaPro\Models\Appointment;
use AgendaPro\Models\Professional;
use AgendaPro\Models\Service;

class Handler {

	public function __construct() {}

	/* ── Inbound (AI agent → plugin) ─────────────────────────────────────── */

	public static function handle_inbound( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$secret   = get_option( 'agendapro_webhook_secret', '' );
		$provided = sanitize_text_field( (string) $request->get_param( 'secret' ) );

		if ( ! hash_equals( $secret, $provided ) ) {
			self::log( 'inbound', 'auth_failed', $request->get_body(), null );
			return new \WP_Error( 'forbidden', 'Acesso negado.', array( 'status' => 403 ) );
		}

		$action = sanitize_text_field( (string) $request->get_param( 'action' ) );
		self::log( 'inbound', $action, $request->get_body(), null );

		switch ( $action ) {
			case 'check_availability': return self::ai_availability( $request );
			case 'book':               return self::ai_book( $request );
			case 'reschedule':         return self::ai_reschedule( $request );
			case 'cancel':             return self::ai_cancel( $request );
			case 'send_notification':  return self::ai_notify( $request );
			case 'list_professionals': return rest_ensure_response( Professional::all_with_services() );
			case 'list_services':      return rest_ensure_response( Service::all() );
			default:
				return new \WP_Error( 'invalid_action', 'Ação inválida.', array( 'status' => 400 ) );
		}
	}

	private static function ai_availability( \WP_REST_Request $r ): \WP_REST_Response {
		$pid  = absint( $r->get_param( 'professional_id' ) );
		$date = sanitize_text_field( (string) $r->get_param( 'date' ) );
		$sid  = absint( $r->get_param( 'service_id' ) );

		if ( $pid ) {
			return rest_ensure_response( array(
				'professional_id' => $pid,
				'date'            => $date,
				'slots'           => Appointment::available_slots( $pid, $date, $sid ),
			) );
		}

		$result = array();
		foreach ( Professional::all_with_services() as $pro ) {
			$result[] = array(
				'professional' => $pro,
				'date'         => $date,
				'slots'        => Appointment::available_slots( (int) $pro['id'], $date, $sid ),
			);
		}
		return rest_ensure_response( $result );
	}

	private static function ai_book( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$endpoints = new \AgendaPro\API\Endpoints();
		return $endpoints->create_appointment( $r );
	}

	private static function ai_reschedule( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r->get_param( 'appointment_id' ) );
		if ( ! $id ) return new \WP_Error( 'missing', 'appointment_id obrigatório.', array( 'status' => 400 ) );
		$r->set_url_params( array( 'id' => $id ) );
		$endpoints = new \AgendaPro\API\Endpoints();
		return $endpoints->update_appointment( $r );
	}

	private static function ai_cancel( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r->get_param( 'appointment_id' ) );
		if ( ! $id ) return new \WP_Error( 'missing', 'appointment_id obrigatório.', array( 'status' => 400 ) );
		Appointment::update( $id, array( 'status' => 'cancelled' ) );
		$a = Appointment::find( $id );
		self::dispatch_outbound( 'appointment.cancelled', $a );
		return rest_ensure_response( array( 'success' => true, 'appointment' => $a ) );
	}

	private static function ai_notify( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r->get_param( 'appointment_id' ) );
		$a  = Appointment::find( $id );
		if ( ! $a ) return new \WP_Error( 'not_found', 'Agendamento não encontrado.', array( 'status' => 404 ) );
		self::dispatch_outbound( 'appointment.notification', $a );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/* ── Outbound (plugin → AI agent) ────────────────────────────────────── */

	public static function dispatch_outbound( string $event, ?array $appointment ): void {
		$url = get_option( 'agendapro_webhook_url', '' );
		if ( ! $url ) return;

		$payload = wp_json_encode( array(
			'event'       => $event,
			'appointment' => $appointment,
			'timestamp'   => current_time( 'c' ),
			'site_url'    => get_site_url(),
		) );

		$sig = hash_hmac( 'sha256', $payload, get_option( 'agendapro_webhook_secret', '' ) );

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Content-Type'          => 'application/json',
				'X-AgendaPro-Event'     => $event,
				'X-AgendaPro-Signature' => $sig,
			),
			'body'    => $payload,
			'timeout' => 15,
		) );

		self::log( 'outbound', $event, $payload, $appointment['id'] ?? null,
			is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response ),
			is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response )
		);
	}

	/* ── Logging ─────────────────────────────────────────────────────────── */

	private static function log( string $dir, string $event, string $payload, ?int $appt_id,
		int $code = 0, string $body = '' ): void {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'agendapro_webhook_logs', array(
			'direction'      => $dir,
			'event'          => $event,
			'payload'        => $payload,
			'response_code'  => $code ?: null,
			'response_body'  => $body ?: null,
			'appointment_id' => $appt_id,
		) );
	}
}