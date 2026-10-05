<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class ClientPackage {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_client_packages';
	}

	private static function base_select(): string {
		global $wpdb;
		return "SELECT cp.*,
			c.name     AS client_name,
			c.whatsapp AS client_whatsapp,
			pkg.name   AS package_name,
			pkg.sessions AS package_sessions,
			(SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_appointments a WHERE a.package_id = cp.id AND a.status != 'cancelled') AS used_sessions
		FROM {$wpdb->prefix}agendapro_client_packages cp
		LEFT JOIN {$wpdb->prefix}agendapro_clients c ON c.id = cp.client_id
		LEFT JOIN {$wpdb->prefix}agendapro_packages pkg ON pkg.id = cp.package_id";
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( self::base_select() . ' WHERE cp.id=%d', $id ), ARRAY_A ) ?: null;
	}

	public static function query( array $args = array() ): array {
		global $wpdb;
		$where = array( '1=1' ); $vals = array();

		if ( ! empty( $args['status'] ) )     { $where[] = 'cp.status=%s';  $vals[] = $args['status']; }
		if ( ! empty( $args['client_id'] ) )  { $where[] = 'cp.client_id=%d'; $vals[] = $args['client_id']; }
		if ( ! empty( $args['package_id'] ) ) { $where[] = 'cp.package_id=%d'; $vals[] = $args['package_id']; }
		if ( ! empty( $args['date_from'] ) )  { $where[] = 'cp.purchase_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )    { $where[] = 'cp.purchase_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['search'] ) )     { $where[] = '(c.name LIKE %s OR pkg.name LIKE %s)';
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $vals[] = $s; $vals[] = $s; }

		$order = 'cp.purchase_date DESC';
		if ( ! empty( $args['orderby'] ) ) {
			$allowed = array( 'id', 'purchase_date', 'client_name', 'package_name', 'status' );
			if ( in_array( $args['orderby'], $allowed, true ) ) {
				$dir = ( 'DESC' === strtoupper( $args['order'] ?? 'DESC' ) ) ? 'DESC' : 'ASC';
				$order = "cp.{$args['orderby']} {$dir}";
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

		if ( ! empty( $args['status'] ) )     { $where[] = 'cp.status=%s';  $vals[] = $args['status']; }
		if ( ! empty( $args['client_id'] ) )  { $where[] = 'cp.client_id=%d'; $vals[] = $args['client_id']; }
		if ( ! empty( $args['package_id'] ) ) { $where[] = 'cp.package_id=%d'; $vals[] = $args['package_id']; }
		if ( ! empty( $args['date_from'] ) )  { $where[] = 'cp.purchase_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )    { $where[] = 'cp.purchase_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['search'] ) )     { $where[] = '(c.name LIKE %s OR pkg.name LIKE %s)';
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $vals[] = $s; $vals[] = $s; }

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_client_packages cp
			LEFT JOIN {$wpdb->prefix}agendapro_clients c ON c.id = cp.client_id
			LEFT JOIN {$wpdb->prefix}agendapro_packages pkg ON pkg.id = cp.package_id
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

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::t(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function get_package_appointments( int $client_package_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, s.name AS service_name, p.name AS professional_name
			FROM {$wpdb->prefix}agendapro_appointments a
			LEFT JOIN {$wpdb->prefix}agendapro_services s ON s.id = a.service_id
			LEFT JOIN {$wpdb->prefix}agendapro_professionals p ON p.id = a.professional_id
			WHERE a.package_id=%d ORDER BY a.appointment_date DESC, a.start_time DESC",
			$client_package_id
		), ARRAY_A ) ?: array();
	}

	public static function get_package_professionals( int $client_package_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT p.id, p.name
			FROM {$wpdb->prefix}agendapro_appointments a
			LEFT JOIN {$wpdb->prefix}agendapro_professionals p ON p.id = a.professional_id
			WHERE a.package_id=%d",
			$client_package_id
		), ARRAY_A ) ?: array();
	}

	public static function by_client( int $client_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			self::base_select() . ' WHERE cp.client_id=%d ORDER BY cp.purchase_date DESC',
			$client_id
		), ARRAY_A ) ?: array();
	}

	public static function used_sessions( int $id, int $exclude_appointment = 0 ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}agendapro_appointments WHERE package_id=%d AND status != 'cancelled' AND id != %d",
			$id,
			$exclude_appointment
		) );
	}

	public static function count_for_package( int $package_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE package_id=%d', $package_id ) );
	}
}
