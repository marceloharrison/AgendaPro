<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class ClientHistory {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_client_history';
	}

	public static function for_client( int $client_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT ch.*, u.display_name AS user_name
			FROM " . self::t() . " ch
			LEFT JOIN {$wpdb->users} u ON u.ID = ch.user_id
			WHERE ch.client_id=%d ORDER BY ch.created_at DESC",
			$client_id
		), ARRAY_A ) ?: array();
	}

	public static function add( int $client_id, string $event_type, string $description, ?int $user_id = null ): int|false {
		global $wpdb;
		$data = array(
			'client_id'   => $client_id,
			'event_type'  => $event_type,
			'description' => $description,
			'user_id'     => $user_id ?? get_current_user_id(),
		);
		return $wpdb->insert( self::t(), $data ) ? $wpdb->insert_id : false;
	}
}
