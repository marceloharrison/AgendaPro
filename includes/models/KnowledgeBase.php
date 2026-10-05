<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class KnowledgeBase {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_knowledge_base';
	}

	public static function all( string $category = '' ): array {
		global $wpdb;
		$where = 'WHERE active=1';
		$vals  = array();
		if ( $category ) {
			$where .= ' AND category=%s';
			$vals[] = $category;
		}
		$sql = 'SELECT * FROM ' . self::t() . " {$where} ORDER BY title";
		$sql = $vals ? $wpdb->prepare( $sql, $vals ) : $sql;
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
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

	public static function categories(): array {
		global $wpdb;
		return $wpdb->get_col( 'SELECT DISTINCT category FROM ' . self::t() . ' WHERE category IS NOT NULL AND category!="" AND active=1 ORDER BY category' ) ?: array();
	}

	public static function get_for_ai(): array {
		$items = self::all();
		return array_map( static function ( $item ) {
			return array(
				'id'       => (int) $item['id'],
				'title'    => $item['title'],
				'content'  => $item['content'],
				'category' => $item['category'] ?? '',
			);
		}, $items );
	}
}
