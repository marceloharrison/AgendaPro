<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Client {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_clients';
	}

	private static function normalize_whatsapp( string $whatsapp ): string {
		return preg_replace( '/\D+/', '', $whatsapp ) ?: $whatsapp;
	}

	public static function find_by_whatsapp( string $whatsapp ): ?array {
		global $wpdb;
		$whatsapp = self::normalize_whatsapp( $whatsapp );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::t() . " WHERE whatsapp=%s OR REPLACE(REPLACE(REPLACE(REPLACE(whatsapp,'(',''),')',''),'-',''),' ','')=%s",
				$whatsapp,
				$whatsapp
			),
			ARRAY_A
		) ?: null;
	}

	public static function all_with_stats(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT c.*,
				COUNT(a.id) AS appointments_count,
				SUM(CASE WHEN a.status='cancelled' THEN 1 ELSE 0 END) AS cancellations_count,
				MAX(CASE WHEN a.status IN ('confirmed','completed') THEN a.appointment_date END) AS last_appointment
			 FROM " . self::t() . " c
			 LEFT JOIN {$wpdb->prefix}agendapro_appointments a ON a.client_id = c.id
			 GROUP BY c.id
			 ORDER BY c.name",
			ARRAY_A
		) ?: array();
	}

	public static function inactive( string $search = '' ): array {
		global $wpdb;
		$where = "HAVING last_appointment IS NOT NULL AND last_appointment < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
		$params = array();
		if ( '' !== $search ) {
			$where .= " AND (c.name LIKE %s OR c.whatsapp LIKE %s)";
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$params = array( $like, $like );
		}
		$sql = "SELECT c.*, MAX(CASE WHEN a.status IN ('confirmed','completed') THEN a.appointment_date END) AS last_appointment, COUNT(a.id) AS appointments_count
			FROM " . self::t() . " c LEFT JOIN {$wpdb->prefix}agendapro_appointments a ON a.client_id=c.id
			GROUP BY c.id {$where} ORDER BY last_appointment ASC";
		if ( $params ) { $sql = $wpdb->prepare( $sql, $params ); }
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id=%d', $id ), ARRAY_A ) ?: null;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		unset( $data['whatsapp'] );
		return (bool) $wpdb->update( self::t(), $data, array( 'id' => $id ) );
	}

	public static function upsert( array $data ): int|false {
		global $wpdb;
		$data['whatsapp'] = self::normalize_whatsapp( (string) $data['whatsapp'] );
		$existing = self::find_by_whatsapp( $data['whatsapp'] );
		if ( $existing ) {
			$merged = array_filter(
				$data,
				static fn( $value ) => '' !== $value && null !== $value
			);
			$wpdb->update( self::t(), $merged, array( 'id' => $existing['id'] ) );
			return (int) $existing['id'];
		}
		return $wpdb->insert( self::t(), $data ) ? $wpdb->insert_id : false;
	}
}
