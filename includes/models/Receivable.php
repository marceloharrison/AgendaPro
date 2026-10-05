<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Receivable {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_financial_receivables';
	}

	private static function base_select(): string {
		global $wpdb;
		return "SELECT fr.*,
			c.name     AS client_name,
			c.whatsapp AS client_whatsapp,
			p.name     AS professional_name,
			s.name     AS service_name
		FROM {$wpdb->prefix}agendapro_financial_receivables fr
		LEFT JOIN {$wpdb->prefix}agendapro_clients c ON c.id = fr.client_id
		LEFT JOIN {$wpdb->prefix}agendapro_professionals p ON p.id = fr.professional_id
		LEFT JOIN {$wpdb->prefix}agendapro_services s ON s.id = fr.service_id";
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( self::base_select() . ' WHERE fr.id=%d', $id ), ARRAY_A ) ?: null;
	}

	public static function query( array $args = array() ): array {
		global $wpdb;
		$where = array( '1=1' ); $vals = array();

		if ( ! empty( $args['status'] ) )       { $where[] = 'fr.status=%s';   $vals[] = $args['status']; }
		if ( ! empty( $args['client_id'] ) )    { $where[] = 'fr.client_id=%d'; $vals[] = $args['client_id']; }
		if ( ! empty( $args['date_from'] ) )    { $where[] = 'fr.appointment_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )      { $where[] = 'fr.appointment_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['search'] ) )       { $where[] = 'c.name LIKE %s'; $vals[] = '%' . $wpdb->esc_like( $args['search'] ) . '%'; }

		$order = 'fr.appointment_date DESC, fr.created_at DESC';
		if ( ! empty( $args['orderby'] ) ) {
			$allowed = array( 'id', 'appointment_date', 'client_name', 'amount', 'status', 'due_date' );
			if ( in_array( $args['orderby'], $allowed, true ) ) {
				$dir = ( 'DESC' === strtoupper( $args['order'] ?? 'DESC' ) ) ? 'DESC' : 'ASC';
				$order = "fr.{$args['orderby']} {$dir}";
			}
		}

		$sql = self::base_select() . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY {$order}";
		$sql = $vals ? $wpdb->prepare( $sql, $vals ) : $sql;

		if ( ! empty( $args['per_page'] ) ) {
			$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
			$offset = ( $page - 1 ) * (int) $args['per_page'];
			$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['per_page'], $offset );
		}

		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function count( array $args = array() ): int {
		global $wpdb;
		$where = array( '1=1' ); $vals = array();

		if ( ! empty( $args['status'] ) )    { $where[] = 'fr.status=%s';   $vals[] = $args['status']; }
		if ( ! empty( $args['client_id'] ) ) { $where[] = 'fr.client_id=%d'; $vals[] = $args['client_id']; }
		if ( ! empty( $args['date_from'] ) ) { $where[] = 'fr.appointment_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )   { $where[] = 'fr.appointment_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['search'] ) )    { $where[] = 'c.name LIKE %s'; $vals[] = '%' . $wpdb->esc_like( $args['search'] ) . '%'; }

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_financial_receivables fr
			LEFT JOIN {$wpdb->prefix}agendapro_clients c ON c.id = fr.client_id
			WHERE " . implode( ' AND ', $where );
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

	public static function dashboard_stats( string $date_from, string $date_to ): array {
		global $wpdb;
		return (array) $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COALESCE(SUM(CASE WHEN status='paid' THEN amount_received ELSE 0 END),0) AS received,
				COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) AS pending,
				COALESCE(SUM(CASE WHEN status='overdue' THEN amount ELSE 0 END),0) AS overdue,
				COUNT(*) AS total,
				SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
				SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count
			FROM " . self::t() . "
			WHERE appointment_date BETWEEN %s AND %s",
			$date_from, $date_to
		), ARRAY_A );
	}
}
