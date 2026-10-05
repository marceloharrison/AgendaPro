<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Appointment {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_appointments';
	}

	private static function base_select(): string {
		global $wpdb;
		$confirm_base = rest_url( 'agendapro/v1/appointments/confirm/' );
		$cancel_base  = rest_url( 'agendapro/v1/appointments/cancel/' );
		return "SELECT a.*,
			c.name     AS client_name,
			c.whatsapp AS client_whatsapp,
			c.email    AS client_email,
			s.name     AS service_name,
			s.duration AS service_duration,
			s.color    AS service_color,
			p.name     AS professional_name,
			CONCAT('{$confirm_base}', a.confirm_token) AS confirm_url,
			CONCAT('{$cancel_base}',  a.cancel_token)  AS cancel_url
		FROM {$wpdb->prefix}agendapro_appointments a
		LEFT JOIN {$wpdb->prefix}agendapro_clients c        ON c.id = a.client_id
		LEFT JOIN {$wpdb->prefix}agendapro_services s       ON s.id = a.service_id
		LEFT JOIN {$wpdb->prefix}agendapro_professionals p  ON p.id = a.professional_id";
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( self::base_select() . ' WHERE a.id=%d', $id ), ARRAY_A ) ?: null;
	}

	public static function find_by_token( string $col, string $token ): ?array {
		global $wpdb;
		$col = in_array( $col, array( 'confirm_token', 'cancel_token' ), true ) ? $col : 'confirm_token';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( self::base_select() . " WHERE a.{$col}=%s", $token ), ARRAY_A ) ?: null;
	}

	public static function query( array $args = array() ): array {
		global $wpdb;
		$where = array( '1=1' ); $vals = array();

		if ( ! empty( $args['status'] ) )          { $where[] = 'a.status=%s';            $vals[] = $args['status']; }
		if ( ! empty( $args['professional_id'] ) )  { $where[] = 'a.professional_id=%d';   $vals[] = $args['professional_id']; }
		if ( ! empty( $args['client_id'] ) )        { $where[] = 'a.client_id=%d';         $vals[] = $args['client_id']; }
		if ( ! empty( $args['date_from'] ) )        { $where[] = 'a.appointment_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )          { $where[] = 'a.appointment_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['month'] ) )            { $where[] = "DATE_FORMAT(a.appointment_date,'%%Y-%%m')=%s"; $vals[] = $args['month']; }
		if ( ! empty( $args['search'] ) )           { $where[] = '(c.name LIKE %s OR p.name LIKE %s OR s.name LIKE %s)';
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $vals[] = $s; $vals[] = $s; $vals[] = $s; }

		$order = 'a.appointment_date, a.start_time';
		if ( ! empty( $args['orderby'] ) ) {
			$allowed = array( 'id', 'appointment_date', 'start_time', 'client_name', 'professional_name', 'status' );
			if ( in_array( $args['orderby'], $allowed, true ) ) {
				$dir = ( 'DESC' === strtoupper( $args['order'] ?? 'ASC' ) ) ? 'DESC' : 'ASC';
				$order = "a.{$args['orderby']} {$dir}";
			}
		}

		$sql = self::base_select() . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY {$order}";
		if ( $vals ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $vals );
		}

		if ( ! empty( $args['per_page'] ) ) {
			$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
			$offset = ( $page - 1 ) * (int) $args['per_page'];
			$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['per_page'], $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function count( array $args = array() ): int {
		global $wpdb;
		$where = array( '1=1' ); $vals = array();

		if ( ! empty( $args['status'] ) )          { $where[] = 'a.status=%s';            $vals[] = $args['status']; }
		if ( ! empty( $args['professional_id'] ) )  { $where[] = 'a.professional_id=%d';   $vals[] = $args['professional_id']; }
		if ( ! empty( $args['client_id'] ) )        { $where[] = 'a.client_id=%d';         $vals[] = $args['client_id']; }
		if ( ! empty( $args['date_from'] ) )        { $where[] = 'a.appointment_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )          { $where[] = 'a.appointment_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['search'] ) )           { $where[] = '(c.name LIKE %s OR p.name LIKE %s OR s.name LIKE %s)';
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $vals[] = $s; $vals[] = $s; $vals[] = $s; }

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_appointments a
			LEFT JOIN {$wpdb->prefix}agendapro_clients c ON c.id = a.client_id
			LEFT JOIN {$wpdb->prefix}agendapro_professionals p ON p.id = a.professional_id
			LEFT JOIN {$wpdb->prefix}agendapro_services s ON s.id = a.service_id
			WHERE " . implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $vals ? $wpdb->prepare( $sql, $vals ) : $sql;

		return (int) $wpdb->get_var( $sql );
	}

	public static function create( array $data ): int|false {
		global $wpdb;
		return $wpdb->insert( self::t(), $data ) ? $wpdb->insert_id : false;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( self::t(), $data, array( 'id' => $id ) );
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::t(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function has_conflict( int $professional_id, string $date, string $start, string $end, int $exclude = 0, int $buffer_minutes = 0 ): bool {
		global $wpdb;
		$buffered_end = wp_date( 'H:i:s', strtotime( "1970-01-01 {$end} UTC" ) + max( 0, $buffer_minutes ) * MINUTE_IN_SECONDS, new \DateTimeZone( 'UTC' ) );
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::t() . "
			 WHERE professional_id=%d AND appointment_date=%s AND status != 'cancelled'
			   AND id!=%d AND start_time<%s AND ADDTIME(end_time, SEC_TO_TIME(%d))>%s",
			$professional_id, $date, $exclude, $buffered_end, max( 0, $buffer_minutes ) * MINUTE_IN_SECONDS, $start
		) );
	}

	public static function available_slots( int $professional_id, string $date, int $service_id, int $exclude = 0 ): array {
		global $wpdb;
		$timezone = wp_timezone();
		$dow = (int) ( new \DateTimeImmutable( $date . ' 12:00:00', $timezone ) )->format( 'w' );

		$hours = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}agendapro_working_hours WHERE professional_id=%d AND day_of_week=%d AND active=1 ORDER BY start_time",
			$professional_id, $dow
		), ARRAY_A );
		if ( ! $hours ) return array();

		$svc      = Service::find( $service_id );
		$duration = $svc ? (int) $svc['duration'] : (int) get_option( 'agendapro_interval_minutes', 60 );

		$booked = $wpdb->get_results( $wpdb->prepare(
			"SELECT start_time, end_time FROM " . self::t() . "
			 WHERE professional_id=%d AND appointment_date=%s AND status NOT IN ('cancelled','no_show')",
			$professional_id, $date
		), ARRAY_A );

		$slots = array();
		foreach ( $hours as $period ) {
			$gap    = max( (int) $period['interval_minutes'], (int) get_option( 'agendapro_interval_minutes', 0 ) );
			$step   = $duration + $gap;
			$cursor = ( new \DateTimeImmutable( $date . ' ' . $period['start_time'], $timezone ) )->getTimestamp();
			$end_ts = ( new \DateTimeImmutable( $date . ' ' . $period['end_time'], $timezone ) )->getTimestamp();

			while ( $cursor + $duration * 60 <= $end_ts ) {
				$s_str = wp_date( 'H:i', $cursor );
				$e_str = wp_date( 'H:i', $cursor + $duration * MINUTE_IN_SECONDS );
				$busy  = self::has_conflict( $professional_id, $date, $s_str, $e_str, $exclude, $gap );
				foreach ( $booked as $b ) {
					if ( $s_str < $b['end_time'] && $e_str > $b['start_time'] ) { $busy = true; break; }
				}
				$slots[] = array( 'start' => $s_str, 'end' => $e_str, 'available' => ! $busy );
				$cursor += $step * 60;
			}
		}

		return $slots;
	}

	/** Verifies that a time was actually offered for the service and professional. */
	public static function is_bookable_slot( int $professional_id, string $date, int $service_id, string $start, int $exclude = 0 ): bool {
		foreach ( self::available_slots( $professional_id, $date, $service_id, $exclude ) as $slot ) {
			if ( $slot['available'] && $slot['start'] === substr( $start, 0, 5 ) ) {
				return true;
			}
		}
		return false;
	}

	public static function dashboard_stats( string $month ): array {
		global $wpdb;
		return (array) $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) total,
				SUM(status='pending')   pending,
				SUM(status='confirmed') confirmed,
				SUM(status='cancelled') cancelled
			 FROM " . self::t() . " WHERE DATE_FORMAT(appointment_date,'%%Y-%%m')=%s",
			$month
		), ARRAY_A );
	}
}
