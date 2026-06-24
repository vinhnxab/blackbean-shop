<?php
/**
 * Shop / PayPal settings (Shop → Settings).
 *
 * @package Blackbean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLACKBEAN_SHOP_OPTION = 'blackbean_shop_settings';

/**
 * @return array<string, mixed>
 */
function blackbean_shop_default_settings() : array {
	return array(
		'paypal_enabled'      => false,
		'paypal_sandbox'      => true,
		'paypal_client_id'    => '',
		'paypal_secret'       => '',
		'paypal_webhook_id'   => '',
		'storefront_enabled'  => false,
		'theme_product_id'    => 0,
	);
}

/**
 * @return array<string, mixed>
 */
function blackbean_shop_get_settings() : array {
	$stored = get_option( BLACKBEAN_SHOP_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return array_merge( blackbean_shop_default_settings(), $stored );
}

/**
 * @param array<string, mixed> $settings Settings.
 */
function blackbean_shop_update_settings( array $settings ) : void {
	update_option( BLACKBEAN_SHOP_OPTION, array_merge( blackbean_shop_get_settings(), $settings ), false );
}

/**
 * PayPal checkout is configured and enabled.
 */
function blackbean_shop_paypal_enabled() : bool {
	$settings = blackbean_shop_get_settings();
	if ( empty( $settings['paypal_enabled'] ) ) {
		return false;
	}
	return '' !== trim( (string) $settings['paypal_client_id'] )
		&& '' !== trim( (string) $settings['paypal_secret'] );
}

/**
 * @deprecated Use blackbean_shop_paypal_enabled().
 */
function blackbean_shop_payment_enabled() : bool {
	return blackbean_shop_paypal_enabled();
}

/**
 * Use PayPal sandbox API.
 */
function blackbean_shop_paypal_is_sandbox() : bool {
	$settings = blackbean_shop_get_settings();
	return ! empty( $settings['paypal_sandbox'] );
}

/**
 * PayPal REST API base URL.
 */
function blackbean_shop_paypal_api_base() : string {
	return blackbean_shop_paypal_is_sandbox()
		? 'https://api-m.sandbox.paypal.com'
		: 'https://api-m.paypal.com';
}

/**
 * PayPal Client ID (for JS SDK if needed).
 */
function blackbean_shop_paypal_client_id() : string {
	$settings = blackbean_shop_get_settings();
	return trim( (string) $settings['paypal_client_id'] );
}

/**
 * PayPal Client Secret.
 */
function blackbean_shop_paypal_secret() : string {
	$settings = blackbean_shop_get_settings();
	return trim( (string) $settings['paypal_secret'] );
}

/**
 * PayPal Webhook ID (optional, for signature verification).
 */
function blackbean_shop_paypal_webhook_id() : string {
	$settings = blackbean_shop_get_settings();
	return trim( (string) $settings['paypal_webhook_id'] );
}

/**
 * Admin URL for shop settings screen.
 */
function blackbean_shop_settings_admin_url() : string {
	return admin_url( 'admin.php?page=blackbean-shop' );
}

/**
 * Register settings page under Shop menu.
 */
function blackbean_shop_register_settings_page() : void {
	add_submenu_page(
		'blackbean-shop-manager',
		__( 'Shop settings', 'blackbean' ),
		__( 'Settings', 'blackbean' ),
		'manage_options',
		'blackbean-shop',
		'blackbean_shop_render_settings_page'
	);
}
add_action( 'admin_menu', 'blackbean_shop_register_settings_page', 20 );

/**
 * Settings page markup.
 */
function blackbean_shop_render_settings_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved = false;
	if ( isset( $_POST['blackbean_shop_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['blackbean_shop_settings_nonce'] ) ), 'blackbean_shop_settings' ) ) {
		blackbean_shop_update_settings(
			array(
				'paypal_enabled'     => ! empty( $_POST['paypal_enabled'] ),
				'paypal_sandbox'       => ! empty( $_POST['paypal_sandbox'] ),
				'paypal_client_id'     => sanitize_text_field( wp_unslash( (string) ( $_POST['paypal_client_id'] ?? '' ) ) ),
				'paypal_secret'        => sanitize_text_field( wp_unslash( (string) ( $_POST['paypal_secret'] ?? '' ) ) ),
				'paypal_webhook_id'  => sanitize_text_field( wp_unslash( (string) ( $_POST['paypal_webhook_id'] ?? '' ) ) ),
				'storefront_enabled' => ! empty( $_POST['storefront_enabled'] ),
				'theme_product_id'   => max( 0, (int) wp_unslash( $_POST['theme_product_id'] ?? 0 ) ),
			)
		);
		$saved = true;
	}

	$s       = blackbean_shop_get_settings();
	$webhook = rest_url( 'blackbean/v1/shop/paypal-webhook' );
	$ui      = blackbean_admin_ui_classes();
	?>
	<div class="<?php echo esc_attr( $ui['settings_wrap'] ); ?>">
		<header class="bb-shop-page-header">
			<div>
				<h1 class="bb-shop-page-title"><?php esc_html_e( 'Shop settings', 'blackbean' ); ?></h1>
				<p class="bb-shop-page-desc"><?php esc_html_e( 'PayPal Checkout, webhooks, and payment configuration.', 'blackbean' ); ?></p>
			</div>
			<div class="<?php echo esc_attr( $ui['toolbar'] ); ?>">
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-shop-manager' ) ); ?>"><?php esc_html_e( 'Products', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-order-manager' ) ); ?>"><?php esc_html_e( 'Orders', 'blackbean' ); ?></a>
				<a class="<?php echo esc_attr( $ui['btn_sec'] ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=blackbean-license-manager' ) ); ?>"><?php esc_html_e( 'Licenses', 'blackbean' ); ?></a>
			</div>
		</header>

		<?php if ( $saved ) : ?>
			<?php blackbean_admin_render_notice( esc_html__( 'Shop settings saved.', 'blackbean' ), 'ok' ); ?>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'blackbean_shop_settings', 'blackbean_shop_settings_nonce' ); ?>

			<div class="<?php echo esc_attr( $ui['card'] ); ?>">
				<div class="<?php echo esc_attr( $ui['card_head'] ); ?>">
					<div>
						<p class="<?php echo esc_attr( $ui['card_title'] ); ?>"><?php esc_html_e( 'PayPal Checkout', 'blackbean' ); ?></p>
						<p class="<?php echo esc_attr( $ui['card_sub'] ); ?>"><?php esc_html_e( 'Accept payments on the storefront checkout.', 'blackbean' ); ?></p>
					</div>
				</div>

				<div class="<?php echo esc_attr( $ui['fields'] ); ?>">
					<p class="<?php echo esc_attr( $ui['check_row'] ); ?>">
						<input class="<?php echo esc_attr( $ui['check'] ); ?>" type="checkbox" name="paypal_enabled" id="paypal_enabled" value="1" <?php checked( ! empty( $s['paypal_enabled'] ) ); ?> />
						<label for="paypal_enabled"><?php esc_html_e( 'Enable PayPal Checkout', 'blackbean' ); ?></label>
					</p>
					<p class="<?php echo esc_attr( $ui['check_row'] ); ?>">
						<input class="<?php echo esc_attr( $ui['check'] ); ?>" type="checkbox" name="paypal_sandbox" id="paypal_sandbox" value="1" <?php checked( ! empty( $s['paypal_sandbox'] ) ); ?> />
						<label for="paypal_sandbox"><?php esc_html_e( 'Use PayPal sandbox (test mode)', 'blackbean' ); ?></label>
					</p>
					<p class="<?php echo esc_attr( $ui['hint'] ); ?>">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: PayPal Developer link */
								__( 'Create sandbox apps at %s.', 'blackbean' ),
								'<a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank" rel="noopener noreferrer">developer.paypal.com</a>'
							)
						);
						?>
					</p>

					<div class="<?php echo esc_attr( $ui['field'] ); ?>">
						<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="paypal_client_id"><?php esc_html_e( 'Client ID', 'blackbean' ); ?></label>
						<input type="text" class="<?php echo esc_attr( $ui['input'] ); ?>" name="paypal_client_id" id="paypal_client_id" value="<?php echo esc_attr( (string) $s['paypal_client_id'] ); ?>" autocomplete="off" />
					</div>

					<div class="<?php echo esc_attr( $ui['field'] ); ?>">
						<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="paypal_secret"><?php esc_html_e( 'Client secret', 'blackbean' ); ?></label>
						<input type="password" class="<?php echo esc_attr( $ui['input'] ); ?>" name="paypal_secret" id="paypal_secret" value="<?php echo esc_attr( (string) $s['paypal_secret'] ); ?>" autocomplete="off" />
					</div>

					<div class="<?php echo esc_attr( $ui['field'] ); ?>">
						<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="paypal_webhook_id"><?php esc_html_e( 'Webhook ID', 'blackbean' ); ?></label>
						<input type="text" class="<?php echo esc_attr( $ui['input'] ); ?>" name="paypal_webhook_id" id="paypal_webhook_id" value="<?php echo esc_attr( (string) $s['paypal_webhook_id'] ); ?>" autocomplete="off" />
						<p class="<?php echo esc_attr( $ui['hint'] ); ?>">
							<?php esc_html_e( 'PayPal webhook URL:', 'blackbean' ); ?>
							<code><?php echo esc_html( $webhook ); ?></code><br>
							<?php esc_html_e( 'Subscribe to CHECKOUT.ORDER.APPROVED and PAYMENT.CAPTURE.COMPLETED (optional but recommended).', 'blackbean' ); ?>
						</p>
					</div>
				</div>
			</div>

			<div class="<?php echo esc_attr( $ui['card'] ); ?> mt-6">
				<div class="<?php echo esc_attr( $ui['card_head'] ); ?>">
					<div>
						<p class="<?php echo esc_attr( $ui['card_title'] ); ?>"><?php esc_html_e( 'Sell Blackbean theme', 'blackbean' ); ?></p>
						<p class="<?php echo esc_attr( $ui['card_sub'] ); ?>"><?php esc_html_e( 'Homepage sales landing and release ZIP product ID.', 'blackbean' ); ?></p>
					</div>
				</div>

				<div class="<?php echo esc_attr( $ui['fields'] ); ?>">
					<p class="<?php echo esc_attr( $ui['check_row'] ); ?>">
						<input class="<?php echo esc_attr( $ui['check'] ); ?>" type="checkbox" name="storefront_enabled" id="storefront_enabled" value="1" <?php checked( ! empty( $s['storefront_enabled'] ) ); ?> />
						<label for="storefront_enabled"><?php esc_html_e( 'Show sales landing on homepage (when front page shows latest posts)', 'blackbean' ); ?></label>
					</p>

					<div class="<?php echo esc_attr( $ui['field'] ); ?>">
						<label class="<?php echo esc_attr( $ui['form_label'] ); ?>" for="theme_product_id"><?php esc_html_e( 'Theme product ID', 'blackbean' ); ?></label>
						<input type="number" min="0" step="1" class="<?php echo esc_attr( $ui['input'] . ' bb-shop-input--narrow' ); ?>" name="theme_product_id" id="theme_product_id" value="<?php echo esc_attr( (string) (int) ( $s['theme_product_id'] ?? 0 ) ); ?>" />
						<p class="<?php echo esc_attr( $ui['hint'] ); ?>">
							<?php esc_html_e( 'Shop product ID for Blackbean Theme. Used on the homepage and in npm run release -- --product-id=…', 'blackbean' ); ?>
						</p>
					</div>

					<p class="<?php echo esc_attr( $ui['hint'] ); ?>">
						<?php esc_html_e( 'Release ZIP:', 'blackbean' ); ?>
						<code>npm run build && npm run release -- --shop-url=<?php echo esc_html( untrailingslashit( home_url() ) ); ?> --product-id=ID</code>
					</p>
					<p class="<?php echo esc_attr( $ui['hint'] ); ?>">
						<a href="<?php echo esc_url( admin_url( 'themes.php?page=blackbean-theme-license' ) ); ?>"><?php esc_html_e( 'Theme license (server)', 'blackbean' ); ?></a>
					</p>
				</div>
			</div>

			<div class="bb-shop-settings-actions mt-4">
				<button type="submit" class="<?php echo esc_attr( $ui['btn_pri'] ); ?>"><?php esc_html_e( 'Save settings', 'blackbean' ); ?></button>
			</div>
		</form>
	</div>
	<?php
}
