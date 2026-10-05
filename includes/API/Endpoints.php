<?php
namespace AgendaPro\API;

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/models/Service.php';
require_once dirname( __DIR__ ) . '/models/Professional.php';
require_once dirname( __DIR__ ) . '/models/Client.php';
require_once dirname( __DIR__ ) . '/models/Appointment.php';
require_once dirname( __DIR__ ) . '/models/Package.php';
require_once dirname( __DIR__ ) . '/models/ClientPackage.php';
require_once dirname( __DIR__ ) . '/models/Receivable.php';
require_once dirname( __DIR__ ) . '/models/Expense.php';
require_once dirname( __DIR__ ) . '/models/ClientHistory.php';
require_once dirname( __DIR__ ) . '/models/Anamnesis.php';
require_once dirname( __DIR__ ) . '/models/KnowledgeBase.php';

use AgendaPro\Models\Appointment;
use AgendaPro\Models\Client;
use AgendaPro\Models\Professional;
use AgendaPro\Models\Service;
use AgendaPro\Models\Package;
use AgendaPro\Models\ClientPackage;
use AgendaPro\Models\Receivable;
use AgendaPro\Models\Expense;
use AgendaPro\Models\ClientHistory;
use AgendaPro\Models\Anamnesis;
use AgendaPro\Models\KnowledgeBase;
use AgendaPro\Webhook\Handler;

class Endpoints {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register(): void {
		$ns = 'agendapro/v1';

		$this->route( $ns, '/dashboard', 'GET', 'get_dashboard', true );
		$this->route( $ns, '/services', 'GET', 'get_services', false );
		$this->route( $ns, '/services', 'POST', 'create_service', true );
		$this->route( $ns, '/services/(?P<id>\d+)', 'GET', 'get_service', false );
		$this->route( $ns, '/services/(?P<id>\d+)', 'PUT,PATCH', 'update_service', true );
		$this->route( $ns, '/services/(?P<id>\d+)', 'DELETE', 'delete_service', true );
		$this->route( $ns, '/professionals', 'GET', 'get_professionals', false );
		$this->route( $ns, '/professionals', 'POST', 'create_professional', true );
		$this->route( $ns, '/professionals/(?P<id>\d+)', 'GET', 'get_professional', false );
		$this->route( $ns, '/professionals/(?P<id>\d+)', 'PUT,PATCH', 'update_professional', true );
		$this->route( $ns, '/professionals/(?P<id>\d+)', 'DELETE', 'delete_professional', true );
		$this->route( $ns, '/professionals/(?P<id>\d+)/hours', 'GET', 'get_working_hours', true );
		$this->route( $ns, '/professionals/(?P<id>\d+)/hours', 'POST', 'save_working_hours', true );
		$this->route( $ns, '/appointments', 'GET', 'get_appointments', true );
		$this->route( $ns, '/appointments', 'POST', 'create_appointment', false );
		$this->route( $ns, '/appointments/(?P<id>\d+)', 'GET', 'get_appointment', true );
		$this->route( $ns, '/appointments/(?P<id>\d+)', 'PUT,PATCH', 'update_appointment', true );
		$this->route( $ns, '/appointments/(?P<id>\d+)', 'DELETE', 'delete_appointment', true );
		$this->route( $ns, '/appointments/(?P<id>\d+)/status', 'PUT,PATCH', 'update_status', true );
		$this->route( $ns, '/availability', 'GET', 'get_availability', false );
		$this->route( $ns, '/clients', 'GET', 'get_clients', true );
		$this->route( $ns, '/clients', 'POST', 'create_client', true );
		$this->route( $ns, '/clients/(?P<id>\d+)', 'GET', 'get_client', true );
		$this->route( $ns, '/clients/(?P<id>\d+)', 'PUT,PATCH', 'update_client', true );
		$this->route( $ns, '/clients/lookup', 'GET', 'lookup_client', false );
		$this->route( $ns, '/reports/revenue', 'GET', 'get_revenue_report', true );
		$this->route( $ns, '/reports/inactive-clients', 'GET', 'get_inactive_clients', true );
		$this->route( $ns, '/notifications/rescue', 'POST', 'send_rescue_notifications', true );
		$this->route( $ns, '/appointments/confirm/(?P<token>[a-f0-9]{64})', 'GET', 'confirm_token', false );
		$this->route( $ns, '/appointments/cancel/(?P<token>[a-f0-9]{64})', 'GET', 'cancel_token', false );
		$this->route( $ns, '/settings', 'GET', 'get_settings', true );
		$this->route( $ns, '/settings', 'PUT,PATCH', 'update_settings', true );
		$this->route( $ns, '/webhook/inbound', 'POST', 'webhook_inbound', false );

		$this->route( $ns, '/packages', 'GET', 'get_packages', true );
		$this->route( $ns, '/packages', 'POST', 'create_package', true );
		$this->route( $ns, '/packages/(?P<id>\d+)', 'GET', 'get_package', true );
		$this->route( $ns, '/packages/(?P<id>\d+)', 'PUT,PATCH', 'update_package', true );
		$this->route( $ns, '/packages/(?P<id>\d+)', 'DELETE', 'delete_package', true );

		$this->route( $ns, '/client-packages', 'GET', 'get_client_packages', true );
		$this->route( $ns, '/client-packages', 'POST', 'create_client_package', true );
		$this->route( $ns, '/client-packages/(?P<id>\d+)', 'GET', 'get_client_package', true );
		$this->route( $ns, '/client-packages/(?P<id>\d+)', 'PUT,PATCH', 'update_client_package', true );
		$this->route( $ns, '/client-packages/(?P<id>\d+)', 'DELETE', 'delete_client_package', true );
		$this->route( $ns, '/client-packages/(?P<id>\d+)/appointments', 'GET', 'get_client_package_appointments', true );

		$this->route( $ns, '/financial/receivables', 'GET', 'get_receivables', true );
		$this->route( $ns, '/financial/receivables/(?P<id>\d+)', 'GET', 'get_receivable', true );
		$this->route( $ns, '/financial/receivables/(?P<id>\d+)/pay', 'POST', 'pay_receivable', true );
		$this->route( $ns, '/financial/receivables/(?P<id>\d+)', 'PUT,PATCH', 'update_receivable', true );

		$this->route( $ns, '/financial/expenses', 'GET', 'get_expenses', true );
		$this->route( $ns, '/financial/expenses', 'POST', 'create_expense', true );
		$this->route( $ns, '/financial/expenses/(?P<id>\d+)', 'GET', 'get_expense', true );
		$this->route( $ns, '/financial/expenses/(?P<id>\d+)', 'PUT,PATCH', 'update_expense', true );
		$this->route( $ns, '/financial/expenses/(?P<id>\d+)', 'DELETE', 'delete_expense', true );
		$this->route( $ns, '/financial/expenses/(?P<id>\d+)/pay', 'POST', 'pay_expense', true );

		$this->route( $ns, '/clients/(?P<id>\d+)/history', 'GET', 'get_client_history', true );
		$this->route( $ns, '/clients/(?P<id>\d+)/appointments', 'GET', 'get_client_appointments', true );
		$this->route( $ns, '/clients/(?P<id>\d+)/financial', 'GET', 'get_client_financial', true );
		$this->route( $ns, '/clients/(?P<id>\d+)/packages', 'GET', 'get_client_packages_list', true );
		$this->route( $ns, '/clients/(?P<id>\d+)/anamnesis', 'GET', 'get_client_anamnesis', true );
		$this->route( $ns, '/clients/(?P<id>\d+)/anamnesis', 'POST', 'save_client_anamnesis', true );

		$this->route( $ns, '/anamnesis/sections', 'GET', 'get_anamnesis_sections', true );
		$this->route( $ns, '/anamnesis/sections', 'POST', 'create_anamnesis_section', true );
		$this->route( $ns, '/anamnesis/sections/(?P<id>\d+)', 'PUT,PATCH', 'update_anamnesis_section', true );
		$this->route( $ns, '/anamnesis/sections/(?P<id>\d+)', 'DELETE', 'delete_anamnesis_section', true );
		$this->route( $ns, '/anamnesis/fields', 'GET', 'get_anamnesis_fields', true );
		$this->route( $ns, '/anamnesis/fields', 'POST', 'create_anamnesis_field', true );
		$this->route( $ns, '/anamnesis/fields/(?P<id>\d+)', 'PUT,PATCH', 'update_anamnesis_field', true );
		$this->route( $ns, '/anamnesis/fields/(?P<id>\d+)', 'DELETE', 'delete_anamnesis_field', true );

		$this->route( $ns, '/knowledge-base', 'GET', 'get_knowledge_base', false );
		$this->route( $ns, '/knowledge-base', 'POST', 'create_knowledge_base', true );
		$this->route( $ns, '/knowledge-base/(?P<id>\d+)', 'PUT,PATCH', 'update_knowledge_base', true );
		$this->route( $ns, '/knowledge-base/(?P<id>\d+)', 'DELETE', 'delete_knowledge_base', true );

		$this->route( $ns, '/dashboard/stats', 'GET', 'get_dashboard_stats', true );
	}

	private function route( string $ns, string $path, string $methods, string $cb, bool $auth ): void {
		foreach ( explode( ',', $methods ) as $method ) {
			register_rest_route(
				$ns,
				$path,
				array(
					'methods'             => strtoupper( trim( $method ) ),
					'callback'            => array( $this, $cb ),
					'permission_callback' => $auth ? array( $this, 'is_admin' ) : '__return_true',
				)
			);
		}
	}

	public function is_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_dashboard( \WP_REST_Request $r ): \WP_REST_Response {
		$month      = sanitize_text_field( (string) ( $r->get_param( 'month' ) ?: wp_date( 'Y-m' ) ) );
		$filter_day = $this->normalize_date( sanitize_text_field( (string) ( $r->get_param( 'date' ) ?: '' ) ) );
		$stats      = Appointment::dashboard_stats( $month );
		$calendar   = Appointment::query( array( 'month' => $month ) );
		$day_list   = $filter_day ? array_values( array_filter( $calendar, fn( $a ) => $a['appointment_date'] === $filter_day ) ) : array();

		return rest_ensure_response( compact( 'stats', 'calendar', 'day_list' ) );
	}

	public function get_services(): \WP_REST_Response {
		$services = Service::all();
		if ( ! current_user_can( 'manage_options' ) ) {
			$services = array_values( array_filter( $services, fn( $service ) => ! empty( $service['active'] ) ) );
		}
		return rest_ensure_response( $services );
	}

	public function get_service( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( Service::find( absint( $r['id'] ) ), __( 'Serviço', 'agendapro' ) );
	}

	public function create_service( $r ): \WP_REST_Response|\WP_Error {
		return $this->created( Service::create( $this->svc_data( $r ) ), 'Service' );
	}

	public function update_service( $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		Service::update( $id, $this->svc_data( $r ) );
		return rest_ensure_response( Service::find( $id ) );
	}

	public function delete_service( $r ): \WP_REST_Response {
		Service::delete( absint( $r['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	private function svc_data( $r ): array {
		return array(
			'name'        => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'description' => sanitize_textarea_field( (string) $r->get_param( 'description' ) ),
			'duration'    => min( 1440, max( 5, absint( $r->get_param( 'duration' ) ?: 60 ) ) ),
			'price'       => max( 0, (float) $r->get_param( 'price' ) ),
			'color'       => sanitize_hex_color( (string) $r->get_param( 'color' ) ) ?: '#4F46E5',
			'active'      => null === $r->get_param( 'active' ) ? 1 : (int) (bool) $r->get_param( 'active' ),
		);
	}

	public function get_professionals(): \WP_REST_Response {
		$professionals = Professional::all_with_services();
		if ( current_user_can( 'manage_options' ) ) {
			return rest_ensure_response( $professionals );
		}
		return rest_ensure_response( array_values( array_map( fn( $professional ) => $this->compact_professional( $professional ), array_filter( $professionals, fn( $professional ) => ! empty( $professional['active'] ) ) ) ) );
	}

	public function get_professional( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( Professional::find( absint( $r['id'] ) ), __( 'Profissional', 'agendapro' ) );
	}

	public function create_professional( $r ): \WP_REST_Response|\WP_Error {
		$id = Professional::create( $this->pro_data( $r ), $this->svc_ids( $r ) );
		$this->save_hours_from_request( (int) $id, $r );
		return $this->created( $id, 'Professional' );
	}

	public function update_professional( $r ): \WP_REST_Response {
		$id = absint( $r['id'] );
		Professional::update( $id, $this->pro_data( $r ), $this->svc_ids( $r ) );
		$this->save_hours_from_request( $id, $r );
		return rest_ensure_response( Professional::find( $id ) );
	}

	public function delete_professional( $r ): \WP_REST_Response {
		Professional::delete( absint( $r['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function get_working_hours( $r ): \WP_REST_Response {
		return rest_ensure_response( Professional::working_hours( absint( $r['id'] ) ) );
	}

	public function save_working_hours( $r ): \WP_REST_Response {
		$pid = absint( $r['id'] );
		$this->replace_hours( $pid, (array) $r->get_param( 'hours' ) );
		return rest_ensure_response( Professional::working_hours( $pid ) );
	}

	private function pro_data( $r ): array {
		return array(
			'name'        => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'email'       => sanitize_email( (string) $r->get_param( 'email' ) ),
			'phone'       => sanitize_text_field( (string) $r->get_param( 'phone' ) ),
			'whatsapp'    => sanitize_text_field( (string) $r->get_param( 'whatsapp' ) ),
			'specialties' => sanitize_textarea_field( (string) $r->get_param( 'specialties' ) ),
			'bio'         => sanitize_textarea_field( (string) $r->get_param( 'bio' ) ),
			'photo_url'   => esc_url_raw( (string) $r->get_param( 'photo_url' ) ),
			'active'      => null === $r->get_param( 'active' ) ? 1 : (int) (bool) $r->get_param( 'active' ),
		);
	}

	private function svc_ids( $r ): array {
		return array_values( array_filter( array_map( 'absint', (array) $r->get_param( 'service_ids' ) ) ) );
	}

	private function save_hours_from_request( int $pid, $r ): void {
		if ( $pid && null !== $r->get_param( 'hours' ) ) {
			$this->replace_hours( $pid, (array) $r->get_param( 'hours' ) );
		}
	}

	private function replace_hours( int $pid, array $hours ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'agendapro_working_hours';
		$clean_hours = array();

		foreach ( $hours as $h ) {
			if ( empty( $h['active'] ) ) {
				continue;
			}
			$day = absint( $h['day_of_week'] ?? 7 );
			$start = $this->normalize_time( sanitize_text_field( (string) ( $h['start_time'] ?? '' ) ) );
			$end = $this->normalize_time( sanitize_text_field( (string) ( $h['end_time'] ?? '' ) ) );
			if ( $day > 6 || ! $start || ! $end || $start >= $end ) {
				continue;
			}
			$clean_hours[] = array( 'day' => $day, 'start' => $start, 'end' => $end, 'interval' => min( 720, absint( $h['interval_minutes'] ?? 0 ) ) );
		}

		$wpdb->delete( $table, array( 'professional_id' => $pid ), array( '%d' ) );
		foreach ( $clean_hours as $h ) {

			$wpdb->insert(
				$table,
				array(
					'professional_id'  => $pid,
					'day_of_week'      => $h['day'],
					'start_time'       => $h['start'],
					'end_time'         => $h['end'],
					'interval_minutes' => $h['interval'],
					'active'           => 1,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%d' )
			);
		}
	}

	public function get_appointments( $r ): \WP_REST_Response {
		$args = array(
			'status'          => sanitize_text_field( (string) $r->get_param( 'status' ) ),
			'professional_id' => absint( $r->get_param( 'professional_id' ) ),
			'client_id'       => absint( $r->get_param( 'client_id' ) ),
			'date_from'       => sanitize_text_field( (string) $r->get_param( 'date_from' ) ),
			'date_to'         => sanitize_text_field( (string) $r->get_param( 'date_to' ) ),
			'search'          => sanitize_text_field( (string) $r->get_param( 'search' ) ),
			'orderby'         => sanitize_text_field( (string) $r->get_param( 'orderby' ) ),
			'order'           => sanitize_text_field( (string) $r->get_param( 'order' ) ),
			'per_page'        => absint( $r->get_param( 'per_page' ) ?: 0 ),
			'page'            => absint( $r->get_param( 'page' ) ?: 1 ),
		);

		if ( $args['per_page'] > 0 ) {
			return rest_ensure_response( array(
				'items' => Appointment::query( $args ),
				'total' => Appointment::count( $args ),
			) );
		}

		return rest_ensure_response( Appointment::query( $args ) );
	}

	public function get_appointment( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( Appointment::find( absint( $r['id'] ) ), __( 'Agendamento', 'agendapro' ) );
	}

	public function create_appointment( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$pid   = absint( $r->get_param( 'professional_id' ) );
		$sid   = absint( $r->get_param( 'service_id' ) );
		$date  = $this->normalize_date( sanitize_text_field( (string) $r->get_param( 'appointment_date' ) ) );
		$start = $this->normalize_time( sanitize_text_field( (string) $r->get_param( 'start_time' ) ) );
		$svc   = Service::find( $sid );

		if ( ! $pid || ! $sid || ! $date || ! $start ) {
			return new \WP_Error( 'missing_fields', __( 'Preencha serviço, profissional, data e horário.', 'agendapro' ), array( 'status' => 400 ) );
		}

		$booking_error = $this->validate_booking_slot( $pid, $sid, $date, $start );
		if ( $booking_error ) {
			return $booking_error;
		}

		$client_id = absint( $r->get_param( 'client_id' ) );
		if ( ! $client_id ) {
			$client_name = sanitize_text_field( (string) $r->get_param( 'client_name' ) );
			$whatsapp = sanitize_text_field( (string) $r->get_param( 'whatsapp' ) );
			if ( '' === $client_name || '' === preg_replace( '/\D+/', '', $whatsapp ) ) {
				return new \WP_Error( 'client_required', __( 'Informe nome e WhatsApp do cliente.', 'agendapro' ), array( 'status' => 400 ) );
			}
			$client_id = Client::upsert( array( 'name' => $client_name, 'email' => sanitize_email( (string) $r->get_param( 'client_email' ) ), 'whatsapp' => $whatsapp, 'notes' => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ) ) );
		}

		if ( ! $client_id || ! Client::find( $client_id ) ) {
			return new \WP_Error( 'client_error', __( 'Cliente inválido ou não pôde ser registrado.', 'agendapro' ), array( 'status' => 400 ) );
		}

		$client_package_id = absint( $r->get_param( 'package_id' ) );
		$package_error = $this->validate_client_package( $client_package_id, $client_id, $sid, $pid );
		if ( $package_error ) {
			return $package_error;
		}

		$end = wp_date( 'H:i:s', strtotime( "1970-01-01 {$start} UTC" ) + (int) $svc['duration'] * MINUTE_IN_SECONDS, new \DateTimeZone( 'UTC' ) );

		$status = 'pending';
		if ( current_user_can( 'manage_options' ) ) {
			$requested_status = sanitize_text_field( (string) $r->get_param( 'status' ) );
			if ( in_array( $requested_status, array( 'pending', 'confirmed', 'cancelled' ), true ) ) {
				$status = $requested_status;
			}
		}

		$id = Appointment::create(
			array(
				'client_id'        => $client_id,
				'professional_id'  => $pid,
				'service_id'       => $sid,
				'appointment_date' => $date,
				'start_time'       => $start,
				'end_time'         => $end,
				'notes'            => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
				'status'           => $status,
				'confirm_token'    => bin2hex( random_bytes( 32 ) ),
				'cancel_token'     => bin2hex( random_bytes( 32 ) ),
				'package_id'       => $client_package_id ?: null,
			)
		);

		if ( ! $id ) {
			return new \WP_Error( 'db_error', __( 'Erro ao criar agendamento.', 'agendapro' ), array( 'status' => 500 ) );
		}

		$this->create_receivable_from_appointment( $id, $client_id, $pid, $sid, $date, $client_package_id ? 0.0 : (float) $svc['price'] );
		ClientHistory::add( $client_id, 'appointment_created', sprintf( 'Agendamento #%d criado para %s.', $id, $date ) );

		$appointment = Appointment::find( $id );
		Handler::dispatch_outbound( 'appointment.created', $appointment );

		return new \WP_REST_Response( $appointment, 201 );
	}

	public function update_appointment( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		$a  = Appointment::find( $id );

		if ( ! $a ) {
			return new \WP_Error( 'not_found', __( 'Agendamento não encontrado.', 'agendapro' ), array( 'status' => 404 ) );
		}

		$date  = $this->normalize_date( sanitize_text_field( (string) $r->get_param( 'appointment_date' ) ) ) ?: (string) $a['appointment_date'];
		$start = $this->normalize_time( sanitize_text_field( (string) $r->get_param( 'start_time' ) ) ) ?: (string) $a['start_time'];
		$sid   = absint( $r->get_param( 'service_id' ) ) ?: (int) $a['service_id'];
		$pid   = absint( $r->get_param( 'professional_id' ) ) ?: (int) $a['professional_id'];

		$svc = Service::find( $sid );
		$booking_error = $this->validate_booking_slot( $pid, $sid, $date, $start, $id );
		if ( $booking_error ) {
			return $booking_error;
		}

		$client_id   = (int) $a['client_id'];
		$client_name = sanitize_text_field( (string) $r->get_param( 'client_name' ) );
		$whatsapp    = sanitize_text_field( (string) $r->get_param( 'whatsapp' ) );
		if ( '' !== $client_name || '' !== $whatsapp ) {
			$upserted = Client::upsert(
				array(
					'name'     => $client_name,
					'whatsapp' => $whatsapp,
				)
			);
			if ( $upserted ) {
				$client_id = (int) $upserted;
			}
		}
		$package_error = $this->validate_client_package( (int) $a['package_id'], $client_id, $sid, $pid, $id );
		if ( $package_error ) {
			return $package_error;
		}

		$data = array(
			'client_id'        => $client_id,
			'professional_id'  => $pid,
			'service_id'       => $sid,
			'appointment_date' => $date,
			'start_time'       => $start,
			'end_time'         => wp_date( 'H:i:s', strtotime( "1970-01-01 {$start} UTC" ) + (int) $svc['duration'] * MINUTE_IN_SECONDS, new \DateTimeZone( 'UTC' ) ),
			'notes'            => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
		);

		$status = sanitize_text_field( (string) $r->get_param( 'status' ) );
		if ( in_array( $status, array( 'pending', 'confirmed', 'cancelled' ), true ) ) {
			$data['status'] = $status;
		}

		Appointment::update( $id, $data );

		$updated = Appointment::find( $id );
		Handler::dispatch_outbound( 'appointment.rescheduled', $updated );

		return rest_ensure_response( $updated );
	}

	public function delete_appointment( $r ): \WP_REST_Response {
		Appointment::delete( absint( $r['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function update_status( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id     = absint( $r['id'] );
		$status = sanitize_text_field( (string) $r->get_param( 'status' ) );

		if ( ! in_array( $status, array( 'pending', 'confirmed', 'cancelled' ), true ) ) {
			return new \WP_Error( 'invalid', __( 'Status inválido.', 'agendapro' ), array( 'status' => 400 ) );
		}

		Appointment::update( $id, array( 'status' => $status ) );
		$a = Appointment::find( $id );
		Handler::dispatch_outbound( 'appointment.status_changed', $a );

		return rest_ensure_response( $a );
	}

	public function get_availability( $r ): \WP_REST_Response {
		$service_id      = absint( $r->get_param( 'service_id' ) );
		$professional_id = absint( $r->get_param( 'professional_id' ) );
		$include_busy    = $this->truthy_param( $r->get_param( 'include_unavailable' ) );
		$service         = Service::find( $service_id );
		$dates           = $this->availability_dates( $r );

		if ( ! $service_id || ! $service || ! $dates ) {
			return new \WP_Error(
				'invalid_availability_query',
				__( 'Informe service_id e date para consultar disponibilidade.', 'agendapro' ),
				array( 'status' => 400 )
			);
		}

		$professionals = $this->availability_professionals( $professional_id, $service_id );
		$date_rows     = array();
		$options       = array();
		$pro_index     = array();

		foreach ( $professionals as $professional ) {
			$pro_index[ (int) $professional['id'] ] = $this->compact_professional( $professional );
		}

		foreach ( $dates as $date ) {
			$date_pros = array();

			foreach ( $professionals as $professional ) {
				$slots = Appointment::available_slots( (int) $professional['id'], $date, $service_id );
				if ( ! $include_busy ) {
					$slots = array_values( array_filter( $slots, fn( $slot ) => false !== $slot['available'] ) );
				}

				$slots = array_map( array( $this, 'compact_slot' ), $slots );
				foreach ( $slots as $slot ) {
					if ( empty( $slot['available'] ) ) {
						continue;
					}
					$options[] = array(
						'professional_id'   => (int) $professional['id'],
						'professional_name' => $professional['name'],
						'service_id'        => $service_id,
						'service_name'      => $service['name'],
						'date'              => $date,
						'start_time'        => $slot['start'],
						'end_time'          => $slot['end'],
						'label'             => "{$professional['name']} - {$date} {$slot['start']}",
					);
				}

				$date_pros[] = array_merge(
					$pro_index[ (int) $professional['id'] ],
					array(
						'has_availability' => ! empty( $slots ),
						'first_slot'       => $slots[0]['start'] ?? null,
						'slots'            => $slots,
					)
				);
			}

			$date_rows[] = array(
				'date'          => $date,
				'display_date'  => date_i18n( 'd/m/Y', strtotime( $date ) ),
				'weekday'       => (int) wp_date( 'w', strtotime( $date . ' 12:00:00' ) ),
				'professionals' => $date_pros,
				'total_slots'   => count(
					array_filter(
						$options,
						fn( $option ) => $option['date'] === $date
					)
				),
			);
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'query'         => array(
					'service_id'            => $service_id,
					'professional_id'       => $professional_id ?: null,
					'date_from'             => $dates[0],
					'date_to'               => $dates[ count( $dates ) - 1 ],
					'include_unavailable'   => $include_busy,
				),
				'service'       => $this->compact_service( $service ),
				'professionals' => array_values( $pro_index ),
				'dates'         => $date_rows,
				'options'       => $options,
				'summary'       => array(
					'total_professionals'           => count( $professionals ),
					'professionals_with_availability' => count( array_unique( array_column( $options, 'professional_id' ) ) ),
					'total_available_slots'         => count( $options ),
					'first_available'               => $options[0] ?? null,
				),
			)
		);
	}

	private function availability_dates( \WP_REST_Request $r ): array {
		$date = $this->normalize_date( sanitize_text_field( (string) $r->get_param( 'date' ) ) );
		if ( $date ) {
			return array( $date );
		}

		$from = $this->normalize_date( sanitize_text_field( (string) $r->get_param( 'date_from' ) ) );
		$days = max( 1, min( 31, absint( $r->get_param( 'days' ) ?: 7 ) ) );
		if ( ! $from ) {
			return array();
		}

		$dates = array();
		$cursor = strtotime( $from );
		for ( $i = 0; $i < $days; $i++ ) {
			$dates[] = wp_date( 'Y-m-d', strtotime( "+{$i} days", $cursor ) );
		}
		return $dates;
	}

	private function availability_professionals( int $professional_id, int $service_id ): array {
		$professionals = $professional_id ? array_filter( array( Professional::find( $professional_id ) ) ) : Professional::all_with_services();
		return array_values(
			array_filter(
				$professionals,
				function ( array $professional ) use ( $service_id ): bool {
					if ( empty( $professional['active'] ) ) {
						return false;
					}
					foreach ( (array) ( $professional['services'] ?? array() ) as $service ) {
						if ( (int) $service['id'] === $service_id ) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}

	private function compact_professional( array $professional ): array {
		return array(
			'id'          => (int) $professional['id'],
			'name'        => $professional['name'],
			'specialties' => $professional['specialties'] ?? '',
			'photo_url'   => $professional['photo_url'] ?? '',
		);
	}

	private function compact_service( array $service ): array {
		return array(
			'id'       => (int) $service['id'],
			'name'     => $service['name'],
			'duration' => (int) $service['duration'],
			'price'    => (float) $service['price'],
		);
	}

	private function compact_slot( array $slot ): array {
		return array(
			'start'     => substr( (string) $slot['start'], 0, 5 ),
			'end'       => substr( (string) $slot['end'], 0, 5 ),
			'available' => false !== $slot['available'],
		);
	}

	private function truthy_param( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'sim' ), true );
	}

	public function lookup_client( $r ): \WP_REST_Response {
		$whatsapp = sanitize_text_field( (string) $r->get_param( 'whatsapp' ) );
		$client   = Client::find_by_whatsapp( $whatsapp );

		if ( ! $client ) {
			return rest_ensure_response( array( 'appointments' => array() ) );
		}

		return rest_ensure_response(
			array(
				'client'       => $client,
				'appointments' => Appointment::query( array( 'client_id' => (int) $client['id'] ) ),
			)
		);
	}

	public function get_clients(): \WP_REST_Response {
		return rest_ensure_response( Client::all_with_stats() );
	}

	public function create_client( \WP_REST_Request $r ): \WP_REST_Response {
		$data = array(
			'name'      => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'whatsapp'  => sanitize_text_field( (string) $r->get_param( 'whatsapp' ) ),
			'email'     => sanitize_email( (string) $r->get_param( 'email' ) ),
			'birth_date'=> sanitize_text_field( (string) $r->get_param( 'birth_date' ) ),
			'address'   => sanitize_textarea_field( (string) $r->get_param( 'address' ) ),
			'notes'     => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
		);
		$id = Client::upsert( $data );
		if ( ! $id ) {
			return new \WP_Error( 'create_failed', __( 'Erro ao criar cliente.', 'agendapro' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array_merge( array( 'id' => (int) $id ), $data ) );
	}

	public function get_revenue_report( \WP_REST_Request $r ): \WP_REST_Response {
		global $wpdb;
		$where = array( "a.status = 'confirmed'" ); $values = array();
		if ( $r->get_param( 'date_from' ) ) { $where[] = 'a.appointment_date >= %s'; $values[] = sanitize_text_field( (string) $r->get_param( 'date_from' ) ); }
		if ( $r->get_param( 'date_to' ) ) { $where[] = 'a.appointment_date <= %s'; $values[] = sanitize_text_field( (string) $r->get_param( 'date_to' ) ); }
		if ( $r->get_param( 'professional_id' ) ) { $where[] = 'a.professional_id=%d'; $values[] = absint( $r->get_param( 'professional_id' ) ); }
		if ( $r->get_param( 'service_id' ) ) { $where[] = 'a.service_id=%d'; $values[] = absint( $r->get_param( 'service_id' ) ); }
		$base = " FROM {$wpdb->prefix}agendapro_appointments a LEFT JOIN {$wpdb->prefix}agendapro_services s ON s.id=a.service_id LEFT JOIN {$wpdb->prefix}agendapro_professionals p ON p.id=a.professional_id WHERE " . implode( ' AND ', $where );
		$prepare = static fn( string $sql ) => $values ? $wpdb->prepare( $sql, $values ) : $sql;
		$total = (float) $wpdb->get_var( $prepare( 'SELECT COALESCE(SUM(s.price),0)' . $base ) );
		$by_professional = $wpdb->get_results( $prepare( 'SELECT p.name, COALESCE(SUM(s.price),0) total' . $base . ' GROUP BY a.professional_id ORDER BY total DESC' ), ARRAY_A ) ?: array();
		$by_service = $wpdb->get_results( $prepare( 'SELECT s.name, COALESCE(SUM(s.price),0) total' . $base . ' GROUP BY a.service_id ORDER BY total DESC' ), ARRAY_A ) ?: array();
		return rest_ensure_response( compact( 'total', 'by_professional', 'by_service' ) );
	}

	public function get_inactive_clients( \WP_REST_Request $r ): \WP_REST_Response {
		return rest_ensure_response( Client::inactive( sanitize_text_field( (string) $r->get_param( 'search' ) ) ) );
	}

	public function send_rescue_notifications( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$ids = array_values( array_filter( array_map( 'absint', (array) $r->get_param( 'client_ids' ) ) ) );
		$url = get_option( 'agendapro_webhook_url', '' );
		if ( ! $ids ) return new \WP_Error( 'missing_clients', __( 'Selecione ao menos um cliente.', 'agendapro' ), array( 'status' => 400 ) );
		if ( ! $url ) return new \WP_Error( 'missing_webhook', __( 'Configure a URL do webhook em Configurações.', 'agendapro' ), array( 'status' => 400 ) );
		$template = get_option( 'agendapro_rescue_template', 'Olá {nome_cliente}, sentimos sua falta! Agende aqui: {link_agendamento}' ); $sent = 0;
		foreach ( $ids as $id ) { $client = Client::find( $id ); if ( ! $client ) continue; global $wpdb; $last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(appointment_date) FROM {$wpdb->prefix}agendapro_appointments WHERE client_id=%d AND status IN ('confirmed','completed')", $id ) ); $tags = array( '{nome_cliente}' => $client['name'], '{nome_empresa}' => get_option( 'agendapro_company_name', get_bloginfo( 'name' ) ), '{data_ultima_consulta}' => $last ? date_i18n( 'd/m/Y', strtotime( $last ) ) : '', '{link_agendamento}' => home_url( '/' ), '{telefone_cliente}' => $client['whatsapp'], '{email_cliente}' => $client['email'] ?? '', '{observacoes_cliente}' => $client['notes'] ?? '', '{url_site}' => get_site_url() ); $payload = array( 'event' => 'client.rescue_notification', 'client' => $client, 'message' => strtr( $template, $tags ), 'last_appointment' => $last, 'timestamp' => current_time( 'c' ) ); $response = wp_remote_post( $url, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ), 'timeout' => 15 ) ); $sent += ! is_wp_error( $response ) ? 1 : 0; }
		return rest_ensure_response( array( 'sent' => $sent ) );
	}

	public function get_client( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( Client::find( absint( $r['id'] ) ), __( 'Cliente', 'agendapro' ) );
	}

	public function update_client( $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		$client = Client::find( $id );

		if ( ! $client ) {
			return new \WP_Error( 'not_found', __( 'Cliente não encontrado.', 'agendapro' ), array( 'status' => 404 ) );
		}

		Client::update( $id, array(
			'name'       => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'email'      => sanitize_email( (string) $r->get_param( 'email' ) ),
			'birth_date' => $this->normalize_date( sanitize_text_field( (string) $r->get_param( 'birth_date' ) ) ) ?: null,
			'address'    => sanitize_textarea_field( (string) $r->get_param( 'address' ) ),
			'notes'      => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
		) );

		return rest_ensure_response( Client::find( $id ) );
	}

	public function confirm_token( $r ): \WP_REST_Response|\WP_Error {
		return $this->token_action( $r['token'], 'confirm_token', 'confirmed', 'appointment.confirmed', true );
	}

	public function cancel_token( $r ): \WP_REST_Response|\WP_Error {
		return $this->token_action( $r['token'], 'cancel_token', 'cancelled', 'appointment.cancelled', true );
	}

	private function token_action( string $token, string $col, string $status, string $event, bool $render_page = false ): \WP_REST_Response|\WP_Error {
		$a = Appointment::find_by_token( $col, sanitize_text_field( $token ) );

		if ( ! $a ) {
			if ( $render_page ) {
				$this->render_token_page( false, $status, null );
			}
			return new \WP_Error( 'not_found', __( 'Token inválido.', 'agendapro' ), array( 'status' => 404 ) );
		}

		Appointment::update( (int) $a['id'], array( 'status' => $status ) );
		$updated = Appointment::find( (int) $a['id'] );
		Handler::dispatch_outbound( $event, $updated );

		if ( $render_page ) {
			$this->render_token_page( true, $status, $updated );
		}

		return rest_ensure_response( array( 'success' => true, 'appointment' => $updated ) );
	}

	public function get_settings(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'company_name'     => get_option( 'agendapro_company_name', '' ),
				'logo_url'         => get_option( 'agendapro_logo_url', '' ),
				'interval_minutes' => (int) get_option( 'agendapro_interval_minutes', 30 ),
				'webhook_url'      => get_option( 'agendapro_webhook_url', '' ),
				'webhook_secret'   => get_option( 'agendapro_webhook_secret', '' ),
				'rescue_template'  => get_option( 'agendapro_rescue_template', 'Olá {nome_cliente}, sentimos sua falta! Agende aqui: {link_agendamento}' ),
				'menu_dashboard'   => get_option( 'agendapro_menu_dashboard', 'Painel' ),
				'menu_services'    => get_option( 'agendapro_menu_services', 'Serviços' ),
				'menu_professionals' => get_option( 'agendapro_menu_professionals', 'Profissionais' ),
				'menu_clients'     => get_option( 'agendapro_menu_clients', 'Clientes' ),
				'menu_settings'    => get_option( 'agendapro_menu_settings', 'Configurações' ),
				'form_button_color' => get_option( 'agendapro_form_button_color', '#93BBFF' ),
				'form_button_text_color' => get_option( 'agendapro_form_button_text_color', '#FFFFFF' ),
				'form_text_color'  => get_option( 'agendapro_form_text_color', '#071F41' ),
				'form_muted_color' => get_option( 'agendapro_form_muted_color', '#52617A' ),
			)
		);
	}

	public function update_settings( $r ): \WP_REST_Response {
		update_option( 'agendapro_company_name', sanitize_text_field( (string) $r->get_param( 'company_name' ) ) );
		update_option( 'agendapro_logo_url', esc_url_raw( (string) $r->get_param( 'logo_url' ) ) );
		update_option( 'agendapro_interval_minutes', min( 720, max( 0, absint( $r->get_param( 'interval_minutes' ) ) ) ) );
		update_option( 'agendapro_webhook_url', esc_url_raw( (string) $r->get_param( 'webhook_url' ) ) );
		update_option( 'agendapro_rescue_template', sanitize_textarea_field( (string) $r->get_param( 'rescue_template' ) ) );
		update_option( 'agendapro_menu_dashboard', sanitize_text_field( (string) $r->get_param( 'menu_dashboard' ) ) );
		update_option( 'agendapro_menu_services', sanitize_text_field( (string) $r->get_param( 'menu_services' ) ) );
		update_option( 'agendapro_menu_professionals', sanitize_text_field( (string) $r->get_param( 'menu_professionals' ) ) );
		update_option( 'agendapro_menu_clients', sanitize_text_field( (string) $r->get_param( 'menu_clients' ) ) );
		update_option( 'agendapro_menu_settings', sanitize_text_field( (string) $r->get_param( 'menu_settings' ) ) );
		update_option( 'agendapro_form_button_color', sanitize_hex_color( (string) $r->get_param( 'form_button_color' ) ) ?: '#93BBFF' );
		update_option( 'agendapro_form_button_text_color', sanitize_hex_color( (string) $r->get_param( 'form_button_text_color' ) ) ?: '#FFFFFF' );
		update_option( 'agendapro_form_text_color', sanitize_hex_color( (string) $r->get_param( 'form_text_color' ) ) ?: '#071F41' );
		update_option( 'agendapro_form_muted_color', sanitize_hex_color( (string) $r->get_param( 'form_muted_color' ) ) ?: '#52617A' );

		return rest_ensure_response( array( 'updated' => true ) );
	}

	public function webhook_inbound( $r ): \WP_REST_Response|\WP_Error {
		return Handler::handle_inbound( $r );
	}

	/* ── Dashboard Stats ─────────────────────────────────────────────── */

	public function get_dashboard_stats( \WP_REST_Request $r ): \WP_REST_Response {
		$date_from = sanitize_text_field( (string) $r->get_param( 'date_from' ) );
		$date_to   = sanitize_text_field( (string) $r->get_param( 'date_to' ) );
		$today     = wp_date( 'Y-m-d' );
		$month_start = wp_date( 'Y-m-01' );
		$month_end   = wp_date( 'Y-m-t' );

		$period_from = $date_from ?: $month_start;
		$period_to   = $date_to ?: $today;

		$appointments_count = $this->get_period_appointment_counts( $period_from, $period_to );
		$revenue            = Receivable::dashboard_stats( $period_from, $period_to );
		$expenses           = Expense::dashboard_stats( $period_from, $period_to );
		$today_count        = $this->get_today_appointments( $today );
		$occupancy          = $this->get_occupancy_rate( $period_from, $period_to );
		$month_confirmed    = $this->get_month_confirmed( $period_from, $period_to );
		$month_cancelled    = $this->get_month_cancelled( $period_from, $period_to );
		$inactive_clients   = count( Client::inactive() );

		return rest_ensure_response( compact(
			'appointments_count', 'revenue', 'expenses', 'today_count',
			'occupancy', 'month_confirmed', 'month_cancelled', 'inactive_clients',
			'period_from', 'period_to'
		) );
	}

	private function get_period_appointment_counts( string $from, string $to ): array {
		global $wpdb;
		return (array) $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) total,
				SUM(status='pending')   pending,
				SUM(status='confirmed') confirmed,
				SUM(status='cancelled') cancelled
			FROM {$wpdb->prefix}agendapro_appointments
			WHERE appointment_date BETWEEN %s AND %s",
			$from, $to
		), ARRAY_A );
	}

	private function get_today_appointments( string $today ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_appointments WHERE appointment_date=%s AND status!='cancelled'",
			$today
		) );
	}

	private function get_occupancy_rate( string $from, string $to ): array {
		global $wpdb;
		$occupied = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_appointments WHERE appointment_date BETWEEN %s AND %s AND status!='cancelled'",
			$from, $to
		) );

		$available = 0;
		$working_hours = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}agendapro_working_hours WHERE active=1", ARRAY_A
		);

		$start_ts = strtotime( $from );
		$end_ts   = strtotime( $to );
		for ( $ts = $start_ts; $ts <= $end_ts; $ts += DAY_IN_SECONDS ) {
			$dow = (int) wp_date( 'w', $ts );
			foreach ( $working_hours as $wh ) {
				if ( (int) $wh['day_of_week'] !== $dow ) continue;
				$start = strtotime( $wh['start_time'] ) - strtotime( 'today' );
				$end   = strtotime( $wh['end_time'] ) - strtotime( 'today' );
				$interval = (int) get_option( 'agendapro_interval_minutes', 30 );
				$available += max( 0, floor( ( $end - $start ) / ( ( $interval + 60 ) * 60 ) ) );
			}
		}

		$rate = $available > 0 ? round( ( $occupied / $available ) * 100 ) : 0;
		return array( 'rate' => $rate, 'occupied' => $occupied, 'available' => $available );
	}

	private function get_month_confirmed( string $from, string $to ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_appointments WHERE appointment_date BETWEEN %s AND %s AND status='confirmed'",
			$from, $to
		) );
	}

	private function get_month_cancelled( string $from, string $to ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_appointments WHERE appointment_date BETWEEN %s AND %s AND status='cancelled'",
			$from, $to
		) );
	}

	/* ── Packages ───────────────────────────────────────────────────── */

	public function get_packages(): \WP_REST_Response {
		return rest_ensure_response( Package::all() );
	}

	public function get_package( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( Package::find( absint( $r['id'] ) ), __( 'Pacote', 'agendapro' ) );
	}

	public function create_package( $r ): \WP_REST_Response|\WP_Error {
		$id = Package::create(
			$this->package_data( $r ),
			array_map( 'absint', (array) $r->get_param( 'service_ids' ) ),
			array_map( 'absint', (array) $r->get_param( 'professional_ids' ) )
		);
		return $this->created( $id, 'Package' );
	}

	public function update_package( $r ): \WP_REST_Response {
		$id = absint( $r['id'] );
		Package::update(
			$id,
			$this->package_data( $r ),
			array_map( 'absint', (array) $r->get_param( 'service_ids' ) ),
			array_map( 'absint', (array) $r->get_param( 'professional_ids' ) )
		);
		return rest_ensure_response( Package::find( $id ) );
	}

	public function delete_package( $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		if ( ClientPackage::count_for_package( $id ) > 0 ) {
			return new \WP_Error( 'package_in_use', __( 'Não é possível excluir um pacote já vinculado a clientes.', 'agendapro' ), array( 'status' => 409 ) );
		}
		Package::delete( $id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	private function package_data( $r ): array {
		return array(
			'name'          => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'description'   => sanitize_textarea_field( (string) $r->get_param( 'description' ) ),
			'sessions'      => max( 1, absint( $r->get_param( 'sessions' ) ?: 1 ) ),
			'price'         => (float) $r->get_param( 'price' ),
			'validity_days' => max( 1, absint( $r->get_param( 'validity_days' ) ?: 365 ) ),
			'active'        => null === $r->get_param( 'active' ) ? 1 : (int) (bool) $r->get_param( 'active' ),
		);
	}

	/* ── Client Packages ────────────────────────────────────────────── */

	public function get_client_packages( \WP_REST_Request $r ): \WP_REST_Response {
		$args = array(
			'status'    => sanitize_text_field( (string) $r->get_param( 'status' ) ),
			'client_id' => absint( $r->get_param( 'client_id' ) ),
			'date_from' => sanitize_text_field( (string) $r->get_param( 'date_from' ) ),
			'date_to'   => sanitize_text_field( (string) $r->get_param( 'date_to' ) ),
			'search'    => sanitize_text_field( (string) $r->get_param( 'search' ) ),
			'orderby'   => sanitize_text_field( (string) $r->get_param( 'orderby' ) ),
			'order'     => sanitize_text_field( (string) $r->get_param( 'order' ) ),
			'per_page'  => absint( $r->get_param( 'per_page' ) ?: 25 ),
			'page'      => absint( $r->get_param( 'page' ) ?: 1 ),
		);
		return rest_ensure_response( array(
			'items' => ClientPackage::query( $args ),
			'total' => ClientPackage::count( $args ),
		) );
	}

	public function get_client_package( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( ClientPackage::find( absint( $r['id'] ) ), __( 'Pacote do cliente', 'agendapro' ) );
	}

	public function create_client_package( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$package_id = absint( $r->get_param( 'package_id' ) );
		$client_id  = absint( $r->get_param( 'client_id' ) );
		$pkg        = Package::find( $package_id );

		if ( ! $pkg || ! $client_id || ! Client::find( $client_id ) || empty( $pkg['active'] ) ) {
			return new \WP_Error( 'invalid', __( 'Pacote e cliente obrigatórios.', 'agendapro' ), array( 'status' => 400 ) );
		}

		$purchase_date = $this->normalize_date( sanitize_text_field( (string) $r->get_param( 'purchase_date' ) ) ) ?: wp_date( 'Y-m-d' );
		if ( ! $this->valid_date( $purchase_date ) ) {
			return new \WP_Error( 'invalid_date', __( 'Data de compra inválida.', 'agendapro' ), array( 'status' => 400 ) );
		}
		$expiry_date   = wp_date( 'Y-m-d', strtotime( $purchase_date . ' + ' . (int) $pkg['validity_days'] . ' days' ) );

		$id = ClientPackage::create( array(
			'client_id'     => $client_id,
			'package_id'    => $package_id,
			'purchase_date' => $purchase_date,
			'expiry_date'   => $expiry_date,
			'price_paid'    => null === $r->get_param( 'price_paid' ) || '' === $r->get_param( 'price_paid' ) ? (float) $pkg['price'] : max( 0, (float) $r->get_param( 'price_paid' ) ),
			'notes'         => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
		) );

		if ( $id ) {
			ClientHistory::add( $client_id, 'package_purchased', sprintf( 'Pacote "%s" adquirido.', $pkg['name'] ) );
		}

		return $this->created( $id, 'ClientPackage' );
	}

	public function update_client_package( $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		$status = sanitize_text_field( (string) $r->get_param( 'status' ) );
		if ( ! in_array( $status, array( 'active', 'cancelled', 'expired' ), true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Status de pacote inválido.', 'agendapro' ), array( 'status' => 400 ) );
		}
		ClientPackage::update( $id, array(
			'status'  => $status,
			'notes'   => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
		) );
		return rest_ensure_response( ClientPackage::find( $id ) );
	}

	public function delete_client_package( $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		if ( ClientPackage::used_sessions( $id ) > 0 ) {
			return new \WP_Error( 'package_in_use', __( 'Não é possível excluir um pacote com agendamentos vinculados.', 'agendapro' ), array( 'status' => 409 ) );
		}
		ClientPackage::delete( $id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function get_client_package_appointments( $r ): \WP_REST_Response {
		$id              = absint( $r['id'] );
		$appointments    = ClientPackage::get_package_appointments( $id );
		$professionals   = ClientPackage::get_package_professionals( $id );
		return rest_ensure_response( compact( 'appointments', 'professionals' ) );
	}

	/* ── Financial Receivables ──────────────────────────────────────── */

	public function get_receivables( \WP_REST_Request $r ): \WP_REST_Response {
		$args = array(
			'status'    => sanitize_text_field( (string) $r->get_param( 'status' ) ),
			'client_id' => absint( $r->get_param( 'client_id' ) ),
			'date_from' => sanitize_text_field( (string) $r->get_param( 'date_from' ) ),
			'date_to'   => sanitize_text_field( (string) $r->get_param( 'date_to' ) ),
			'search'    => sanitize_text_field( (string) $r->get_param( 'search' ) ),
			'orderby'   => sanitize_text_field( (string) $r->get_param( 'orderby' ) ),
			'order'     => sanitize_text_field( (string) $r->get_param( 'order' ) ),
			'per_page'  => absint( $r->get_param( 'per_page' ) ?: 25 ),
			'page'      => absint( $r->get_param( 'page' ) ?: 1 ),
		);
		return rest_ensure_response( array(
			'items' => Receivable::query( $args ),
			'total' => Receivable::count( $args ),
		) );
	}

	public function get_receivable( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( Receivable::find( absint( $r['id'] ) ), __( 'Conta a receber', 'agendapro' ) );
	}

	public function update_receivable( $r ): \WP_REST_Response {
		$id = absint( $r['id'] );
		$data = array(
			'amount'         => (float) $r->get_param( 'amount' ),
			'payment_method' => sanitize_text_field( (string) $r->get_param( 'payment_method' ) ),
			'notes'          => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
		);
		if ( $r->get_param( 'status' ) ) {
			$data['status'] = sanitize_text_field( (string) $r->get_param( 'status' ) );
		}
		Receivable::update( $id, $data );
		return rest_ensure_response( Receivable::find( $id ) );
	}

	public function pay_receivable( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		$receivable = Receivable::find( $id );
		if ( ! $receivable ) {
			return new \WP_Error( 'not_found', __( 'Conta não encontrada.', 'agendapro' ), array( 'status' => 404 ) );
		}

		$data = array(
			'status'          => 'paid',
			'payment_date'    => sanitize_text_field( (string) $r->get_param( 'payment_date' ) ) ?: gmdate( 'Y-m-d' ),
			'payment_method'  => sanitize_text_field( (string) $r->get_param( 'payment_method' ) ),
			'amount_received' => (float) ( $r->get_param( 'amount_received' ) ?: $receivable['amount'] ),
		);

		Receivable::update( $id, $data );
		ClientHistory::add(
			(int) $receivable['client_id'],
			'payment',
			sprintf( 'Pagamento de %s referente ao agendamento #%d registrado.', number_format( (float) $data['amount_received'], 2, ',', '.' ), (int) $receivable['appointment_id'] )
		);

		return rest_ensure_response( Receivable::find( $id ) );
	}

	/* ── Financial Expenses ─────────────────────────────────────────── */

	public function get_expenses( \WP_REST_Request $r ): \WP_REST_Response {
		$args = array(
			'status'    => sanitize_text_field( (string) $r->get_param( 'status' ) ),
			'category'  => sanitize_text_field( (string) $r->get_param( 'category' ) ),
			'date_from' => sanitize_text_field( (string) $r->get_param( 'date_from' ) ),
			'date_to'   => sanitize_text_field( (string) $r->get_param( 'date_to' ) ),
			'search'    => sanitize_text_field( (string) $r->get_param( 'search' ) ),
			'orderby'   => sanitize_text_field( (string) $r->get_param( 'orderby' ) ),
			'order'     => sanitize_text_field( (string) $r->get_param( 'order' ) ),
			'per_page'  => absint( $r->get_param( 'per_page' ) ?: 25 ),
			'page'      => absint( $r->get_param( 'page' ) ?: 1 ),
		);
		return rest_ensure_response( array(
			'items' => Expense::query( $args ),
			'total' => Expense::count( $args ),
		) );
	}

	public function get_expense( $r ): \WP_REST_Response|\WP_Error {
		return $this->find_or_404( Expense::find( absint( $r['id'] ) ), __( 'Despesa', 'agendapro' ) );
	}

	public function create_expense( $r ): \WP_REST_Response|\WP_Error {
		$id = Expense::create( $this->expense_data( $r ) );
		return $this->created( $id, 'Expense' );
	}

	public function update_expense( $r ): \WP_REST_Response {
		$id = absint( $r['id'] );
		Expense::update( $id, $this->expense_data( $r ) );
		return rest_ensure_response( Expense::find( $id ) );
	}

	public function delete_expense( $r ): \WP_REST_Response {
		Expense::delete( absint( $r['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function pay_expense( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error {
		$id = absint( $r['id'] );
		$expense = Expense::find( $id );
		if ( ! $expense ) {
			return new \WP_Error( 'not_found', __( 'Despesa não encontrada.', 'agendapro' ), array( 'status' => 404 ) );
		}

		Expense::update( $id, array(
			'status'         => 'paid',
			'payment_date'   => sanitize_text_field( (string) $r->get_param( 'payment_date' ) ) ?: gmdate( 'Y-m-d' ),
			'payment_method' => sanitize_text_field( (string) $r->get_param( 'payment_method' ) ),
		) );

		return rest_ensure_response( Expense::find( $id ) );
	}

	private function expense_data( $r ): array {
		return array(
			'code'           => sanitize_text_field( (string) $r->get_param( 'code' ) ),
			'expense_date'   => sanitize_text_field( (string) $r->get_param( 'expense_date' ) ) ?: gmdate( 'Y-m-d' ),
			'due_date'       => sanitize_text_field( (string) $r->get_param( 'due_date' ) ) ?: gmdate( 'Y-m-d' ),
			'description'    => sanitize_text_field( (string) $r->get_param( 'description' ) ),
			'category'       => sanitize_text_field( (string) $r->get_param( 'category' ) ),
			'supplier'       => sanitize_text_field( (string) $r->get_param( 'supplier' ) ),
			'amount'         => (float) $r->get_param( 'amount' ),
			'payment_method' => sanitize_text_field( (string) $r->get_param( 'payment_method' ) ),
			'notes'          => sanitize_textarea_field( (string) $r->get_param( 'notes' ) ),
		);
	}

	/* ── Client Detail ──────────────────────────────────────────────── */

	public function get_client_history( $r ): \WP_REST_Response {
		return rest_ensure_response( ClientHistory::for_client( absint( $r['id'] ) ) );
	}

	public function get_client_appointments( $r ): \WP_REST_Response {
		return rest_ensure_response(
			Appointment::query( array( 'client_id' => absint( $r['id'] ) ) )
		);
	}

	public function get_client_financial( $r ): \WP_REST_Response {
		$receivables = Receivable::query( array( 'client_id' => absint( $r['id'] ) ) );
		return rest_ensure_response( $receivables );
	}

	public function get_client_packages_list( $r ): \WP_REST_Response {
		return rest_ensure_response( ClientPackage::by_client( absint( $r['id'] ) ) );
	}

	public function get_client_anamnesis( $r ): \WP_REST_Response {
		$client_id = absint( $r['id'] );
		return rest_ensure_response( array(
			'sections' => Anamnesis::section_with_fields(),
			'values'   => Anamnesis::get_values( $client_id ),
		) );
	}

	public function save_client_anamnesis( \WP_REST_Request $r ): \WP_REST_Response {
		$client_id       = absint( $r['id'] );
		$fields          = (array) $r->get_param( 'fields' );
		$professional_id = absint( $r->get_param( 'professional_id' ) ) ?: get_current_user_id();

		foreach ( $fields as $field_id => $value ) {
			Anamnesis::save_value( $client_id, (int) $field_id, (string) $value, $professional_id );
		}

		ClientHistory::add( $client_id, 'anamnesis_updated', __( 'Ficha de anamnese atualizada.', 'agendapro' ) );
		return rest_ensure_response( array( 'saved' => true ) );
	}

	/* ── Anamnesis Configuration ────────────────────────────────────── */

	public function get_anamnesis_sections(): \WP_REST_Response {
		return rest_ensure_response( Anamnesis::section_with_fields() );
	}

	public function create_anamnesis_section( $r ): \WP_REST_Response|\WP_Error {
		$data = array(
			'name'        => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'description' => sanitize_textarea_field( (string) $r->get_param( 'description' ) ),
			'sort_order'  => absint( $r->get_param( 'sort_order' ) ?: 0 ),
			'active'      => null === $r->get_param( 'active' ) ? 1 : (int) (bool) $r->get_param( 'active' ),
		);
		$id = Anamnesis::section_create( $data );
		return $this->created_response( $id, 'section' );
	}

	public function update_anamnesis_section( $r ): \WP_REST_Response {
		$id = absint( $r['id'] );
		Anamnesis::section_update( $id, array(
			'name'        => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'description' => sanitize_textarea_field( (string) $r->get_param( 'description' ) ),
			'sort_order'  => absint( $r->get_param( 'sort_order' ) ?: 0 ),
			'active'      => (int) (bool) $r->get_param( 'active' ),
		) );
		return rest_ensure_response( Anamnesis::section_find( $id ) );
	}

	public function delete_anamnesis_section( $r ): \WP_REST_Response {
		Anamnesis::section_delete( absint( $r['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function get_anamnesis_fields( \WP_REST_Request $r ): \WP_REST_Response {
		return rest_ensure_response( Anamnesis::fields( absint( $r->get_param( 'section_id' ) ) ) );
	}

	public function create_anamnesis_field( $r ): \WP_REST_Response|\WP_Error {
		$data = $this->anamnesis_field_data( $r );
		$id   = Anamnesis::field_create( $data );
		return $this->created_response( $id, 'field' );
	}

	public function update_anamnesis_field( $r ): \WP_REST_Response {
		$id = absint( $r['id'] );
		Anamnesis::field_update( $id, $this->anamnesis_field_data( $r ) );
		return rest_ensure_response( Anamnesis::field_find( $id ) );
	}

	public function delete_anamnesis_field( $r ): \WP_REST_Response {
		Anamnesis::field_delete( absint( $r['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	private function anamnesis_field_data( $r ): array {
		return array(
			'section_id'    => absint( $r->get_param( 'section_id' ) ),
			'name'          => sanitize_text_field( (string) $r->get_param( 'name' ) ),
			'label'         => sanitize_text_field( (string) $r->get_param( 'label' ) ),
			'description'   => sanitize_textarea_field( (string) $r->get_param( 'description' ) ),
			'field_type'    => sanitize_text_field( (string) $r->get_param( 'field_type' ) ) ?: 'text',
			'placeholder'   => sanitize_text_field( (string) $r->get_param( 'placeholder' ) ),
			'default_value' => sanitize_text_field( (string) $r->get_param( 'default_value' ) ),
			'required'      => (int) (bool) $r->get_param( 'required' ),
			'options'       => sanitize_textarea_field( (string) $r->get_param( 'options' ) ),
			'sort_order'    => absint( $r->get_param( 'sort_order' ) ?: 0 ),
			'active'        => null === $r->get_param( 'active' ) ? 1 : (int) (bool) $r->get_param( 'active' ),
		);
	}

	/* ── Knowledge Base ─────────────────────────────────────────────── */

	public function get_knowledge_base( \WP_REST_Request $r ): \WP_REST_Response {
		$category = sanitize_text_field( (string) $r->get_param( 'category' ) );
		return rest_ensure_response( KnowledgeBase::all( $category ) );
	}

	public function create_knowledge_base( $r ): \WP_REST_Response|\WP_Error {
		$id = KnowledgeBase::create( array(
			'title'    => sanitize_text_field( (string) $r->get_param( 'title' ) ),
			'content'  => wp_kses_post( (string) $r->get_param( 'content' ) ),
			'category' => sanitize_text_field( (string) $r->get_param( 'category' ) ),
			'active'   => null === $r->get_param( 'active' ) ? 1 : (int) (bool) $r->get_param( 'active' ),
		) );
		return $this->created( $id, 'KnowledgeBase' );
	}

	public function update_knowledge_base( $r ): \WP_REST_Response {
		$id = absint( $r['id'] );
		KnowledgeBase::update( $id, array(
			'title'    => sanitize_text_field( (string) $r->get_param( 'title' ) ),
			'content'  => wp_kses_post( (string) $r->get_param( 'content' ) ),
			'category' => sanitize_text_field( (string) $r->get_param( 'category' ) ),
			'active'   => (int) (bool) $r->get_param( 'active' ),
		) );
		return rest_ensure_response( KnowledgeBase::find( $id ) );
	}

	public function delete_knowledge_base( $r ): \WP_REST_Response {
		KnowledgeBase::delete( absint( $r['id'] ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	private function created_response( int|false $id, string $model ): \WP_REST_Response|\WP_Error {
		if ( ! $id ) {
			return new \WP_Error( 'db_error', __( 'Erro ao salvar.', 'agendapro' ), array( 'status' => 500 ) );
		}
		return new \WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/* ── Helpers ────────────────────────────────────────────────────── */
	private function find_or_404( ?array $row, string $label ): \WP_REST_Response|\WP_Error {
		return $this->single_or_error( $row, $label );
	}

	private function single_or_error( $row, string $label ): \WP_REST_Response|\WP_Error {
		return $row ? rest_ensure_response( $row ) : new \WP_Error( 'not_found', sprintf( '%s não encontrado.', $label ), array( 'status' => 404 ) );
	}

	private function created( int|false $id, string $model ): \WP_REST_Response|\WP_Error {
		if ( ! $id ) {
			return new \WP_Error( 'db_error', __( 'Erro ao salvar.', 'agendapro' ), array( 'status' => 500 ) );
		}

		$class = "AgendaPro\\Models\\{$model}";

		return new \WP_REST_Response( $class::find( $id ), 201 );
	}

	private function create_receivable_from_appointment( int $appointment_id, int $client_id, int $professional_id, int $service_id, string $date, float $amount ): void {
		Receivable::create( array(
			'appointment_id'   => $appointment_id,
			'client_id'        => $client_id,
			'professional_id'  => $professional_id,
			'service_id'       => $service_id,
			'appointment_date' => $date,
			'amount'           => $amount,
			'due_date'         => $date,
			'status'           => 'pending',
		) );
	}

	private function validate_booking_slot( int $professional_id, int $service_id, string $date, string $start, int $exclude = 0 ): ?\WP_Error {
		$service = Service::find( $service_id );
		$professional = Professional::find( $professional_id );
		if ( ! $service || empty( $service['active'] ) || ! $professional || empty( $professional['active'] ) ) {
			return new \WP_Error( 'invalid_booking', __( 'Serviço ou profissional indisponível.', 'agendapro' ), array( 'status' => 400 ) );
		}

		$offers_service = false;
		foreach ( (array) $professional['services'] as $professional_service ) {
			if ( (int) $professional_service['id'] === $service_id ) {
				$offers_service = true;
				break;
			}
		}
		if ( ! $offers_service || ! $this->valid_date( $date ) || ! $start || ! Appointment::is_bookable_slot( $professional_id, $date, $service_id, $start, $exclude ) ) {
			return new \WP_Error( 'conflict', __( 'Horário não disponível para este profissional e serviço.', 'agendapro' ), array( 'status' => 409 ) );
		}

		return null;
	}

	private function validate_client_package( int $client_package_id, int $client_id, int $service_id, int $professional_id, int $exclude_appointment = 0 ): ?\WP_Error {
		if ( ! $client_package_id ) {
			return null;
		}
		$client_package = ClientPackage::find( $client_package_id );
		if ( ! $client_package || (int) $client_package['client_id'] !== $client_id || 'active' !== $client_package['status'] || $client_package['expiry_date'] < wp_date( 'Y-m-d' ) ) {
			return new \WP_Error( 'invalid_package', __( 'O pacote do cliente não está ativo ou não é válido.', 'agendapro' ), array( 'status' => 400 ) );
		}
		$package = Package::find( (int) $client_package['package_id'] );
		if ( ! $package || empty( $package['active'] ) || ClientPackage::used_sessions( $client_package_id, $exclude_appointment ) >= (int) $package['sessions'] ) {
			return new \WP_Error( 'package_exhausted', __( 'Este pacote não possui sessões disponíveis.', 'agendapro' ), array( 'status' => 409 ) );
		}
		$services = array_map( 'intval', array_column( (array) $package['services'], 'id' ) );
		$professionals = array_map( 'intval', array_column( (array) $package['professionals'], 'id' ) );
		if ( ! in_array( $service_id, $services, true ) || ! in_array( $professional_id, $professionals, true ) ) {
			return new \WP_Error( 'package_scope', __( 'O serviço ou profissional não pertence a este pacote.', 'agendapro' ), array( 'status' => 400 ) );
		}
		return null;
	}

	private function normalize_time( string $time ): string {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time ) ? substr( $time, 0, 5 ) : '';
	}

	private function valid_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	private function normalize_date( string $date ): string {
		if ( preg_match( '/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $date, $matches ) ) {
			return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
		}

		return $date;
	}

	private function render_token_page( bool $success, string $status, ?array $appointment ): void {
		$is_cancel = 'cancelled' === $status;
		$title     = $success
			? ( $is_cancel ? __( 'Agendamento cancelado', 'agendapro' ) : __( 'Agendamento confirmado', 'agendapro' ) )
			: __( 'Link inválido', 'agendapro' );
		$message   = $success
			? ( $is_cancel ? __( 'Seu horário foi cancelado com sucesso.', 'agendapro' ) : __( 'Seu horário foi confirmado com sucesso.', 'agendapro' ) )
			: __( 'Não encontramos um agendamento válido para este link.', 'agendapro' );
		$icon      = $success ? ( $is_cancel ? '×' : '✓' ) : '!';
		$color     = $success ? ( $is_cancel ? '#ef4444' : '#16a34a' ) : '#f59e0b';
		$date      = $appointment ? date_i18n( 'd-m-Y', strtotime( $appointment['appointment_date'] ) ) : '';
		$time      = $appointment ? substr( (string) $appointment['start_time'], 0, 5 ) : '';

		status_header( $success ? 200 : 404 );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				body{margin:0;background:#f6f8fb;color:#17233c;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}
				.ap-token-card{width:min(520px,100%);background:#fff;border:1px solid #e6ebf3;border-radius:20px;box-shadow:0 24px 70px rgba(20,35,65,.12);padding:36px;text-align:center}
				.ap-token-icon{width:74px;height:74px;border-radius:24px;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;background:<?php echo esc_attr( $color ); ?>;color:#fff;font-size:44px;font-weight:800;line-height:1}
				h1{font-size:26px;margin:0 0 8px;letter-spacing:0}
				p{color:#64748b;font-size:16px;line-height:1.6;margin:0 0 22px}
				.ap-token-summary{background:#f8fafc;border:1px solid #e6ebf3;border-radius:14px;text-align:left;padding:16px;margin-top:18px}
				.ap-token-summary strong{display:block;color:#17233c;margin-bottom:6px}
				.ap-token-row{color:#64748b;font-size:14px;margin-top:4px}
				.ap-token-btn{display:inline-flex;align-items:center;justify-content:center;margin-top:22px;background:#2563eb;color:#fff;border-radius:12px;padding:12px 18px;text-decoration:none;font-weight:700}
			</style>
		</head>
		<body>
			<main class="ap-token-card">
				<div class="ap-token-icon"><?php echo esc_html( $icon ); ?></div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $message ); ?></p>
				<?php if ( $appointment ) : ?>
					<div class="ap-token-summary">
						<strong><?php echo esc_html( $appointment['service_name'] ?? '' ); ?></strong>
						<div class="ap-token-row"><?php echo esc_html( $appointment['professional_name'] ?? '' ); ?></div>
						<div class="ap-token-row"><?php echo esc_html( $date . ' às ' . $time ); ?></div>
					</div>
				<?php endif; ?>
				<a class="ap-token-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Voltar ao site', 'agendapro' ); ?></a>
			</main>
		</body>
		</html>
		<?php
		exit;
	}
}
