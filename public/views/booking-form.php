<?php
/**
 * Booking form shortcode view.
 *
 * @package AgendaPro
 */

defined( 'ABSPATH' ) || exit;

$company = esc_html( get_option( 'agendapro_company_name', get_bloginfo( 'name' ) ) );
$logo    = esc_url( get_option( 'agendapro_logo_url', '' ) );
?>
<div id="agendapro-booking-form" class="ap-pub-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-api="<?php echo esc_url( rest_url( 'agendapro/v1' ) ); ?>">
	<div class="ap-pub-header">
		<?php if ( $logo ) : ?>
			<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $company ); ?>" class="ap-pub-logo">
		<?php endif; ?>
		<div class="ap-pub-title"><?php echo esc_html( $company ); ?></div>
		<div class="ap-pub-subtitle"><?php esc_html_e( 'Agende sua consulta em poucos passos', 'agendapro' ); ?></div>
	</div>

	<div class="ap-steps" id="ap-steps">
		<div class="ap-step active" data-step="1"><div class="ap-step__circle">1</div><div class="ap-step__label"><?php esc_html_e( 'Serviço', 'agendapro' ); ?></div></div>
		<div class="ap-step" data-step="2"><div class="ap-step__circle">2</div><div class="ap-step__label"><?php esc_html_e( 'Profissional', 'agendapro' ); ?></div></div>
		<div class="ap-step" data-step="3"><div class="ap-step__circle">3</div><div class="ap-step__label"><?php esc_html_e( 'Data e hora', 'agendapro' ); ?></div></div>
		<div class="ap-step" data-step="4"><div class="ap-step__circle">4</div><div class="ap-step__label"><?php esc_html_e( 'Seus dados', 'agendapro' ); ?></div></div>
	</div>

	<div id="ap-alert" class="ap-pub-alert ap-pub-alert-error" style="display:none;"></div>

	<div class="ap-step-content" id="ap-step-1">
		<p class="ap-step-heading"><?php esc_html_e( 'Selecione o serviço', 'agendapro' ); ?></p>
		<div id="ap-services-list" class="ap-pub-spinner"></div>
		<div class="ap-pub-actions">
			<button type="button" class="ap-pub-btn ap-pub-btn-primary" id="ap-next-1" style="display:none;" disabled><?php esc_html_e( 'Próximo', 'agendapro' ); ?></button>
		</div>
	</div>

	<div class="ap-step-content" id="ap-step-2" style="display:none;">
		<p class="ap-step-heading"><?php esc_html_e( 'Escolha o profissional', 'agendapro' ); ?></p>
		<div id="ap-professionals-list"></div>
		<div class="ap-pub-actions">
			<button type="button" class="ap-pub-btn ap-pub-btn-secondary" id="ap-prev-2"><?php esc_html_e( 'Voltar', 'agendapro' ); ?></button>
			<button type="button" class="ap-pub-btn ap-pub-btn-primary" id="ap-next-2" disabled><?php esc_html_e( 'Próximo', 'agendapro' ); ?></button>
		</div>
	</div>

	<div class="ap-step-content" id="ap-step-3" style="display:none;">
		<p class="ap-step-heading"><?php esc_html_e( 'Selecione a data e o horário', 'agendapro' ); ?></p>
		<div class="ap-pub-form-group">
			<input type="hidden" id="ap-date">
			<div id="ap-calendar" class="ap-booking-calendar" aria-label="<?php esc_attr_e( 'Calendario de agendamento', 'agendapro' ); ?>"></div>
		</div>
		<div id="ap-slots-wrap">
			<p class="ap-slots-empty"><?php esc_html_e( 'Selecione uma data para ver os horários disponíveis.', 'agendapro' ); ?></p>
		</div>
		<div class="ap-pub-actions">
			<button type="button" class="ap-pub-btn ap-pub-btn-secondary" id="ap-prev-3"><?php esc_html_e( 'Voltar', 'agendapro' ); ?></button>
			<button type="button" class="ap-pub-btn ap-pub-btn-primary" id="ap-next-3" disabled><?php esc_html_e( 'Próximo', 'agendapro' ); ?></button>
		</div>
	</div>

	<div class="ap-step-content" id="ap-step-4" style="display:none;">
		<p class="ap-step-heading"><?php esc_html_e( 'Preencha seus dados', 'agendapro' ); ?></p>
		<div id="ap-booking-summary" class="ap-pub-summary"></div>
		<div class="ap-pub-form-group">
			<label class="ap-pub-label" for="ap-client-name"><?php esc_html_e( 'Nome completo *', 'agendapro' ); ?></label>
			<input type="text" id="ap-client-name" class="ap-pub-input" autocomplete="name">
		</div>
		<div class="ap-pub-form-group">
			<label class="ap-pub-label" for="ap-client-whatsapp"><?php esc_html_e( 'WhatsApp *', 'agendapro' ); ?></label>
			<input type="tel" id="ap-client-whatsapp" class="ap-pub-input ap-phone-mask" placeholder="<?php esc_attr_e( '(00) 99999-9999', 'agendapro' ); ?>" autocomplete="tel">
		</div>
		<div class="ap-pub-form-group">
			<label class="ap-pub-label" for="ap-client-email"><?php esc_html_e( 'E-mail', 'agendapro' ); ?></label>
			<input type="email" id="ap-client-email" class="ap-pub-input" autocomplete="email">
		</div>
		<div class="ap-pub-form-group">
			<label class="ap-pub-label" for="ap-notes"><?php esc_html_e( 'Observações', 'agendapro' ); ?></label>
			<textarea id="ap-notes" class="ap-pub-input" rows="3"></textarea>
		</div>
		<div class="ap-pub-actions">
			<button type="button" class="ap-pub-btn ap-pub-btn-secondary" id="ap-prev-4"><?php esc_html_e( 'Voltar', 'agendapro' ); ?></button>
			<button type="button" class="ap-pub-btn ap-pub-btn-primary" id="ap-submit"><?php esc_html_e( 'Confirmar agendamento', 'agendapro' ); ?></button>
		</div>
	</div>

	<div id="ap-success" style="display:none;" class="ap-pub-success">
		<div class="ap-pub-success__icon" aria-hidden="true">✓</div>
		<h2><?php esc_html_e( 'Agendamento realizado!', 'agendapro' ); ?></h2>
		<p><?php esc_html_e( 'Você receberá uma mensagem de confirmação em breve.', 'agendapro' ); ?></p>
		<div id="ap-success-details"></div>
		<button type="button" class="ap-pub-btn ap-pub-btn-primary" id="ap-new-booking"><?php esc_html_e( 'Fazer novo agendamento', 'agendapro' ); ?></button>
	</div>
</div>
