<?php
namespace AgendaPro;

defined( 'ABSPATH' ) || exit;

class Installer {

	public static function activate(): void {
		self::create_tables();
		self::seed_options();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function create_tables(): void {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_services (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name          VARCHAR(200)    NOT NULL,
			description   TEXT,
			duration      SMALLINT UNSIGNED NOT NULL DEFAULT 60,
			price         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
			color         VARCHAR(7)      NOT NULL DEFAULT '#4F46E5',
			active        TINYINT(1)      NOT NULL DEFAULT 1,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_professionals (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name          VARCHAR(200)    NOT NULL,
			email         VARCHAR(200),
			phone         VARCHAR(30),
			whatsapp      VARCHAR(30),
			specialties   TEXT,
			bio           TEXT,
			photo_url     VARCHAR(500),
			active        TINYINT(1)      NOT NULL DEFAULT 1,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_professional_services (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			professional_id  BIGINT UNSIGNED NOT NULL,
			service_id       BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_pro_svc (professional_id, service_id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_working_hours (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			professional_id  BIGINT UNSIGNED NOT NULL,
			day_of_week      TINYINT(1)      NOT NULL,
			start_time       TIME            NOT NULL,
			end_time         TIME            NOT NULL,
			interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			active           TINYINT(1)      NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY idx_pro_day (professional_id, day_of_week)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_clients (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name          VARCHAR(200)    NOT NULL,
			email         VARCHAR(200),
			whatsapp      VARCHAR(30)     NOT NULL,
			birth_date    DATE,
			address       TEXT,
			notes         TEXT,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_whatsapp (whatsapp)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_appointments (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id        BIGINT UNSIGNED NOT NULL,
			professional_id  BIGINT UNSIGNED NOT NULL,
			service_id       BIGINT UNSIGNED NOT NULL,
			appointment_date DATE            NOT NULL,
			start_time       TIME            NOT NULL,
			end_time         TIME            NOT NULL,
			status           ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
			notes            TEXT,
			confirm_token    VARCHAR(64)     NOT NULL,
			cancel_token     VARCHAR(64)     NOT NULL,
			webhook_sent     TINYINT(1)      NOT NULL DEFAULT 0,
			package_id       BIGINT UNSIGNED DEFAULT NULL,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_date         (appointment_date),
			KEY idx_status       (status),
			KEY idx_client       (client_id),
			KEY idx_professional (professional_id),
			KEY idx_package      (package_id),
			UNIQUE KEY uq_confirm_token (confirm_token),
			UNIQUE KEY uq_cancel_token  (cancel_token)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_webhook_logs (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			direction        ENUM('inbound','outbound') NOT NULL,
			event            VARCHAR(100)    NOT NULL,
			payload          LONGTEXT,
			response_code    SMALLINT,
			response_body    LONGTEXT,
			appointment_id   BIGINT UNSIGNED,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_event (event),
			KEY idx_appt  (appointment_id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_packages (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name             VARCHAR(200)    NOT NULL,
			description      TEXT,
			sessions         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			price            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
			validity_days    SMALLINT UNSIGNED NOT NULL DEFAULT 365,
			active           TINYINT(1)      NOT NULL DEFAULT 1,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_package_services (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			package_id       BIGINT UNSIGNED NOT NULL,
			service_id       BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_pkg_svc (package_id, service_id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_package_professionals (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			package_id       BIGINT UNSIGNED NOT NULL,
			professional_id  BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_pkg_pro (package_id, professional_id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_client_packages (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id        BIGINT UNSIGNED NOT NULL,
			package_id       BIGINT UNSIGNED NOT NULL,
			purchase_date    DATE            NOT NULL,
			expiry_date      DATE            NOT NULL,
			status           ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
			price_paid       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
			notes            TEXT,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_client (client_id),
			KEY idx_package (package_id),
			KEY idx_status (status)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_financial_receivables (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			appointment_id   BIGINT UNSIGNED NOT NULL,
			client_id        BIGINT UNSIGNED NOT NULL,
			professional_id  BIGINT UNSIGNED NOT NULL,
			service_id       BIGINT UNSIGNED NOT NULL,
			appointment_date DATE            NOT NULL,
			amount           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
			payment_method   VARCHAR(50),
			status           ENUM('pending','paid','cancelled','overdue') NOT NULL DEFAULT 'pending',
			due_date         DATE            NOT NULL,
			payment_date     DATE,
			amount_received  DECIMAL(10,2),
			notes            TEXT,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_appointment (appointment_id),
			KEY idx_client (client_id),
			KEY idx_status (status),
			KEY idx_due (due_date)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_financial_expenses (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code             VARCHAR(50),
			expense_date     DATE            NOT NULL,
			due_date         DATE            NOT NULL,
			description      VARCHAR(500)    NOT NULL,
			category         VARCHAR(100),
			supplier         VARCHAR(200),
			amount           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
			payment_method   VARCHAR(50),
			status           ENUM('pending','paid','cancelled','overdue') NOT NULL DEFAULT 'pending',
			payment_date     DATE,
			notes            TEXT,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (status),
			KEY idx_category (category)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_client_history (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id        BIGINT UNSIGNED NOT NULL,
			event_type       VARCHAR(50)     NOT NULL,
			description      VARCHAR(500)    NOT NULL,
			user_id          BIGINT UNSIGNED,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_client (client_id),
			KEY idx_type (event_type)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_anamnesis_sections (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name             VARCHAR(200)    NOT NULL,
			description      TEXT,
			sort_order       INT             NOT NULL DEFAULT 0,
			active           TINYINT(1)      NOT NULL DEFAULT 1,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_anamnesis_fields (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			section_id       BIGINT UNSIGNED NOT NULL,
			name             VARCHAR(200)    NOT NULL,
			label            VARCHAR(300)    NOT NULL,
			description      TEXT,
			field_type       ENUM('text','textarea','number','date','time','checkbox','radio','select','multiselect','yesno','phone','email') NOT NULL DEFAULT 'text',
			placeholder      VARCHAR(300),
			default_value    TEXT,
			required         TINYINT(1)      NOT NULL DEFAULT 0,
			options          TEXT,
			sort_order       INT             NOT NULL DEFAULT 0,
			active           TINYINT(1)      NOT NULL DEFAULT 1,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_section (section_id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_anamnesis_values (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id        BIGINT UNSIGNED NOT NULL,
			field_id         BIGINT UNSIGNED NOT NULL,
			field_value      TEXT,
			professional_id  BIGINT UNSIGNED,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_client_field (client_id, field_id),
			KEY idx_client (client_id)
		) $c;" );

		dbDelta( "
		CREATE TABLE {$wpdb->prefix}agendapro_knowledge_base (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title            VARCHAR(300)    NOT NULL,
			content          LONGTEXT        NOT NULL,
			category         VARCHAR(100),
			active           TINYINT(1)      NOT NULL DEFAULT 1,
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_category (category)
		) $c;" );

		update_option( 'agendapro_db_version', AGENDAPRO_VERSION );
		self::normalize_appointment_statuses();
	}

	private static function normalize_appointment_statuses(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'agendapro_appointments';
		$wpdb->query( "UPDATE {$table} SET status='confirmed' WHERE status IN ('completed','no_show')" );
		$wpdb->query( "ALTER TABLE {$table} MODIFY status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending'" );
	}

	private static function seed_options(): void {
		$defaults = array(
			'agendapro_company_name'     => get_bloginfo( 'name' ),
			'agendapro_interval_minutes' => 30,
			'agendapro_webhook_url'      => '',
			'agendapro_webhook_secret'   => wp_generate_password( 32, false ),
			'agendapro_rescue_template'  => 'Olá {nome_cliente}, sentimos sua falta! Agende aqui: {link_agendamento}',
			'agendapro_logo_url'         => '',
			'agendapro_menu_dashboard'   => 'Painel',
			'agendapro_menu_services'    => 'Serviços',
			'agendapro_menu_professionals' => 'Profissionais',
			'agendapro_menu_clients'     => 'Clientes',
			'agendapro_menu_settings'    => 'Configurações',
			'agendapro_form_button_color' => '#93BBFF',
			'agendapro_form_button_text_color' => '#FFFFFF',
			'agendapro_form_text_color'  => '#071F41',
			'agendapro_form_muted_color' => '#52617A',
			'agendapro_menu_appointments' => 'Agendamentos',
			'agendapro_menu_financial'   => 'Financeiro',
		);
		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}
}
