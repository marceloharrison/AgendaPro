<?php
namespace AgendaPro\Models;

defined( 'ABSPATH' ) || exit;

class Anamnesis {

	public static function sections(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}agendapro_anamnesis_sections WHERE active=1 ORDER BY sort_order",
			ARRAY_A
		) ?: array();
	}

	public static function section_find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}agendapro_anamnesis_sections WHERE id=%d", $id
		), ARRAY_A ) ?: null;
	}

	public static function section_create( array $data ): int|false {
		global $wpdb;
		return $wpdb->insert( $wpdb->prefix . 'agendapro_anamnesis_sections', $data ) ? $wpdb->insert_id : false;
	}

	public static function section_update( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( $wpdb->prefix . 'agendapro_anamnesis_sections', $data, array( 'id' => $id ) );
	}

	public static function section_delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'agendapro_anamnesis_fields', array( 'section_id' => $id ), array( '%d' ) );
		return (bool) $wpdb->delete( $wpdb->prefix . 'agendapro_anamnesis_sections', array( 'id' => $id ), array( '%d' ) );
	}

	public static function fields( int $section_id = 0 ): array {
		global $wpdb;
		$where = 'WHERE active=1';
		$vals  = array();
		if ( $section_id ) {
			$where .= ' AND section_id=%d';
			$vals[]  = $section_id;
		}
		$sql = "SELECT * FROM {$wpdb->prefix}agendapro_anamnesis_fields {$where} ORDER BY sort_order";
		$sql = $vals ? $wpdb->prepare( $sql, $vals ) : $sql;
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function field_find( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}agendapro_anamnesis_fields WHERE id=%d", $id
		), ARRAY_A ) ?: null;
	}

	public static function field_create( array $data ): int|false {
		global $wpdb;
		return $wpdb->insert( $wpdb->prefix . 'agendapro_anamnesis_fields', $data ) ? $wpdb->insert_id : false;
	}

	public static function field_update( int $id, array $data ): bool {
		global $wpdb;
		return (bool) $wpdb->update( $wpdb->prefix . 'agendapro_anamnesis_fields', $data, array( 'id' => $id ) );
	}

	public static function field_delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'agendapro_anamnesis_values', array( 'field_id' => $id ), array( '%d' ) );
		return (bool) $wpdb->delete( $wpdb->prefix . 'agendapro_anamnesis_fields', array( 'id' => $id ), array( '%d' ) );
	}

	public static function get_values( int $client_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT av.*, af.label AS field_label, af.field_type, af.section_id, s.name AS section_name
			FROM {$wpdb->prefix}agendapro_anamnesis_values av
			LEFT JOIN {$wpdb->prefix}agendapro_anamnesis_fields af ON af.id = av.field_id
			LEFT JOIN {$wpdb->prefix}agendapro_anamnesis_sections s ON s.id = af.section_id
			WHERE av.client_id=%d ORDER BY s.sort_order, af.sort_order",
			$client_id
		), ARRAY_A ) ?: array();
	}

	public static function save_value( int $client_id, int $field_id, string $value, ?int $professional_id = null ): bool {
		global $wpdb;
		$table   = $wpdb->prefix . 'agendapro_anamnesis_values';
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE client_id=%d AND field_id=%d", $client_id, $field_id
		) );

		$data = array(
			'field_value'      => $value,
			'professional_id'  => $professional_id ?? get_current_user_id(),
		);

		if ( $existing ) {
			return (bool) $wpdb->update( $table, $data, array( 'id' => $existing ) );
		}

		$data['client_id'] = $client_id;
		$data['field_id']  = $field_id;
		return (bool) $wpdb->insert( $table, $data );
	}

	public static function section_with_fields(): array {
		$sections = self::sections();
		foreach ( $sections as &$section ) {
			$section['fields'] = self::fields( (int) $section['id'] );
		}
		return $sections;
	}
}
