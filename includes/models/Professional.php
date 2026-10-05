<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Professional {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_professionals';
	}

	public static function all_with_services(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' ORDER BY name', ARRAY_A ) ?: array();
		foreach ( $rows as &$row ) {
			$row['services'] = self::services_for( (int) $row['id'] );
			$row['hours']    = self::working_hours( (int) $row['id'] );
		}
		return $rows;
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id=%d', $id ), ARRAY_A );
		if ( ! $row ) return null;
		$row['services'] = self::services_for( $id );
		$row['hours']    = self::working_hours( $id );
		return $row;
	}

	public static function create( array $data, array $service_ids = array() ): int|false {
		global $wpdb;
		if ( ! $wpdb->insert( self::t(), $data ) ) return false;
		$id = $wpdb->insert_id;
		self::sync_services( $id, $service_ids );
		return $id;
	}

	public static function update( int $id, array $data, array $service_ids = array() ): bool {
		global $wpdb;
		$ok = (bool) $wpdb->update( self::t(), $data, array( 'id' => $id ) );
		self::sync_services( $id, $service_ids );
		return $ok;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'agendapro_professional_services', array( 'professional_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'agendapro_working_hours',         array( 'professional_id' => $id ), array( '%d' ) );
		return (bool) $wpdb->delete( self::t(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function working_hours( int $professional_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . 'agendapro_working_hours WHERE professional_id=%d ORDER BY day_of_week',
				$professional_id
			), ARRAY_A
		) ?: array();
	}

	private static function services_for( int $pid ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.* FROM {$wpdb->prefix}agendapro_services s
				 INNER JOIN {$wpdb->prefix}agendapro_professional_services ps ON ps.service_id=s.id
				 WHERE ps.professional_id=%d", $pid
			), ARRAY_A
		) ?: array();
	}

	private static function sync_services( int $pid, array $ids ): void {
		global $wpdb;
		$pivot = $wpdb->prefix . 'agendapro_professional_services';
		$wpdb->delete( $pivot, array( 'professional_id' => $pid ), array( '%d' ) );
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $sid ) {
			$wpdb->insert( $pivot, array( 'professional_id' => $pid, 'service_id' => $sid ), array( '%d', '%d' ) );
		}
	}
}
