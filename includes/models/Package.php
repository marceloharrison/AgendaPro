<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Package {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_packages';
	}

	public static function all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' ORDER BY name', ARRAY_A ) ?: array();
		foreach ( $rows as &$row ) {
			$row['services']      = self::services_for( (int) $row['id'] );
			$row['professionals'] = self::professionals_for( (int) $row['id'] );
		}
		return $rows;
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id=%d', $id ), ARRAY_A );
		if ( ! $row ) return null;
		$row['services']      = self::services_for( $id );
		$row['professionals'] = self::professionals_for( $id );
		return $row;
	}

	public static function create( array $data, array $service_ids = array(), array $professional_ids = array() ): int|false {
		global $wpdb;
		if ( ! $wpdb->insert( self::t(), $data ) ) return false;
		$id = $wpdb->insert_id;
		self::sync_services( $id, $service_ids );
		self::sync_professionals( $id, $professional_ids );
		return $id;
	}

	public static function update( int $id, array $data, array $service_ids = array(), array $professional_ids = array() ): bool {
		global $wpdb;
		$ok = (bool) $wpdb->update( self::t(), $data, array( 'id' => $id ) );
		self::sync_services( $id, $service_ids );
		self::sync_professionals( $id, $professional_ids );
		return $ok;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'agendapro_package_services',      array( 'package_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'agendapro_package_professionals', array( 'package_id' => $id ), array( '%d' ) );
		return (bool) $wpdb->delete( self::t(), array( 'id' => $id ), array( '%d' ) );
	}

	private static function services_for( int $pid ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.* FROM {$wpdb->prefix}agendapro_services s
				 INNER JOIN {$wpdb->prefix}agendapro_package_services ps ON ps.service_id=s.id
				 WHERE ps.package_id=%d", $pid
			), ARRAY_A
		) ?: array();
	}

	private static function professionals_for( int $pid ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.* FROM {$wpdb->prefix}agendapro_professionals p
				 INNER JOIN {$wpdb->prefix}agendapro_package_professionals pp ON pp.professional_id=p.id
				 WHERE pp.package_id=%d", $pid
			), ARRAY_A
		) ?: array();
	}

	private static function sync_services( int $pid, array $ids ): void {
		global $wpdb;
		$pivot = $wpdb->prefix . 'agendapro_package_services';
		$wpdb->delete( $pivot, array( 'package_id' => $pid ), array( '%d' ) );
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $sid ) {
			$wpdb->insert( $pivot, array( 'package_id' => $pid, 'service_id' => $sid ), array( '%d', '%d' ) );
		}
	}

	private static function sync_professionals( int $pid, array $ids ): void {
		global $wpdb;
		$pivot = $wpdb->prefix . 'agendapro_package_professionals';
		$wpdb->delete( $pivot, array( 'package_id' => $pid ), array( '%d' ) );
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $pro_id ) {
			$wpdb->insert( $pivot, array( 'package_id' => $pid, 'professional_id' => $pro_id ), array( '%d', '%d' ) );
		}
	}
}
