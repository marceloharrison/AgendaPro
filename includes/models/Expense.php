<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Expense {

	private static function t(): string {
		global $wpdb; return $wpdb->prefix . 'agendapro_financial_expenses';
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id=%d', $id ), ARRAY_A ) ?: null;
	}

	public static function query( array $args = array() ): array {
		global $wpdb;
		$where = array( '1=1' ); $vals = array();

		if ( ! empty( $args['status'] ) )    { $where[] = 'status=%s';   $vals[] = $args['status']; }
		if ( ! empty( $args['category'] ) )  { $where[] = 'category=%s'; $vals[] = $args['category']; }
		if ( ! empty( $args['date_from'] ) ) { $where[] = 'expense_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )   { $where[] = 'expense_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['search'] ) )    { $where[] = '(description LIKE %s OR supplier LIKE %s)';
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $vals[] = $s; $vals[] = $s; }

		$order = 'expense_date DESC, created_at DESC';
		if ( ! empty( $args['orderby'] ) ) {
			$allowed = array( 'id', 'expense_date', 'description', 'category', 'amount', 'status', 'due_date' );
			if ( in_array( $args['orderby'], $allowed, true ) ) {
				$dir = ( 'DESC' === strtoupper( $args['order'] ?? 'DESC' ) ) ? 'DESC' : 'ASC';
				$order = "{$args['orderby']} {$dir}";
			}
		}

		$sql = 'SELECT * FROM ' . self::t() . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY {$order}";
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

		if ( ! empty( $args['status'] ) )    { $where[] = 'status=%s';   $vals[] = $args['status']; }
		if ( ! empty( $args['category'] ) )  { $where[] = 'category=%s'; $vals[] = $args['category']; }
		if ( ! empty( $args['date_from'] ) ) { $where[] = 'expense_date>=%s'; $vals[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )   { $where[] = 'expense_date<=%s'; $vals[] = $args['date_to']; }
		if ( ! empty( $args['search'] ) )    { $where[] = '(description LIKE %s OR supplier LIKE %s)';
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $vals[] = $s; $vals[] = $s; }

		$sql = 'SELECT COUNT(*) FROM ' . self::t() . ' WHERE ' . implode( ' AND ', $where );
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

	public static function dashboard_stats( string $date_from, string $date_to ): array {
		global $wpdb;
		return (array) $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid,
				COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) AS pending,
				COALESCE(SUM(CASE WHEN status='overdue' THEN amount ELSE 0 END),0) AS overdue,
				COUNT(*) AS total
			FROM " . self::t() . "
			WHERE expense_date BETWEEN %s AND %s",
			$date_from, $date_to
		), ARRAY_A );
	}
}
