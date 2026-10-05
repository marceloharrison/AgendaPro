<?php
/**
 * Client appointment lookup shortcode view.
 *
 * @package AgendaPro
 */

defined( 'ABSPATH' ) || exit;

$company = esc_html( get_option( 'agendapro_company_name', get_bloginfo( 'name' ) ) );
?>
<div id="agendapro-my-appointments" class="ap-pub-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-api="<?php echo esc_url( rest_url( 'agendapro/v1' ) ); ?>">
	<div class="ap-pub-header">
		<div class="ap-pub-title"><?php echo esc_html( $company ); ?></div>
		<div class="ap-pub-subtitle"><?php esc_html_e( 'Consulte seus agendamentos', 'agendapro' ); ?></div>
	</div>
	<div id="ap-lookup-alert" class="ap-pub-alert ap-pub-alert-error" style="display:none;"></div>
	<div class="ap-pub-form-group">
		<label class="ap-pub-label" for="ap-lookup-whatsapp"><?php esc_html_e( 'WhatsApp', 'agendapro' ); ?></label>
		<input type="tel" id="ap-lookup-whatsapp" class="ap-pub-input ap-phone-mask" placeholder="<?php esc_attr_e( '(00) 99999-9999', 'agendapro' ); ?>" autocomplete="tel">
	</div>
	<button type="button" class="ap-pub-btn ap-pub-btn-primary" id="ap-lookup-submit"><?php esc_html_e( 'Buscar agendamentos', 'agendapro' ); ?></button>
	<div id="ap-appointments-result" style="margin-top:24px;"></div>
</div>
