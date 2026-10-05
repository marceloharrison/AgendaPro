<?php
/**
 * Remove AgendaPro data on plugin uninstall.
 *
 * @package AgendaPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	'agendapro_knowledge_base',
	'agendapro_anamnesis_values',
	'agendapro_anamnesis_fields',
	'agendapro_anamnesis_sections',
	'agendapro_client_history',
	'agendapro_financial_expenses',
	'agendapro_financial_receivables',
	'agendapro_client_packages',
	'agendapro_package_professionals',
	'agendapro_package_services',
	'agendapro_packages',
	'agendapro_webhook_logs',
	'agendapro_appointments',
	'agendapro_clients',
	'agendapro_working_hours',
	'agendapro_professional_services',
	'agendapro_professionals',
	'agendapro_services',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$options = array(
	'agendapro_db_version',
	'agendapro_company_name',
	'agendapro_interval_minutes',
	'agendapro_webhook_url',
	'agendapro_webhook_secret',
	'agendapro_rescue_template',
	'agendapro_logo_url',
	'agendapro_menu_dashboard',
	'agendapro_menu_services',
	'agendapro_menu_professionals',
	'agendapro_menu_clients',
	'agendapro_menu_settings',
	'agendapro_menu_appointments',
	'agendapro_menu_financial',
	'agendapro_form_button_color',
	'agendapro_form_button_text_color',
	'agendapro_form_text_color',
	'agendapro_form_muted_color',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
