<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Service {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_services';
	}

	public static function all(): array {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::t() . ' ORDER BY name', ARRAY_A ) ?: array();
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id=%d', $id ), ARRAY_A ) ?: null;
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
}
