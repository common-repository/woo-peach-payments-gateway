<?php
/**
 * Plugin Name: WooCommerce Advanced Peach Payments Gateway * 
 * Description: A payment gateway for <a href="http://www.peachpayments.com/">Peach Payments</a>.
 * Version: 1.1.1
 * Author: Atlogys
 * Author URI: http://atlogys.com/
 * Requires at least: 3.8
 * Tested up to: 4.3.1
 */

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    // Put your plugin code here


add_action( 'plugins_loaded', 'advanced_woocommerce_peach_payments_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */

function advanced_woocommerce_peach_payments_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) )
		return;

	include_once( plugin_basename( 'classes/class-wc-peach-payments.php' ) );

	load_plugin_textdomain( 'woocommerce-gateway-advanced-peach-payments', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

	
	if ( class_exists( 'WC_Subscriptions_Order' ) ) {
		include_once( 'classes/class-wc-peach-payments-subscriptions.php' );
		//include_once( plugin_basename( 'deprecated/class-wc-peach-payments-subscriptions-deprecated.php' ) );
	}

	

	add_filter('woocommerce_payment_gateways', 'advanced_woocommerce_peach_payments_add_gateway' );

	/**
	 * Delete card
	 * @return [type]
	 */
	function woocommerce_peach_handle_delete_card() {
		global $woocommerce;

		if ( ! isset( $_POST['peach_delete_card'] ) || ! is_account_page() ) {
			return;
		}

		if ( ! is_user_logged_in() || ! wp_verify_nonce( $_POST['_wpnonce'], "peach_del_card" ) ) {
			wp_die( __( 'Unable to verify deletion, please try again', 'woocommerce-gateway-advanced-peach-payments' ) );
		}

		$credit_cards = get_user_meta( get_current_user_id(), '_peach_payment_id', false );
		$credit_card = $credit_cards[ (int) $_POST['peach_delete_card'] ];
		
		delete_user_meta( get_current_user_id(), '_peach_payment_id', $credit_card );

		wc_add_notice( __('Card deleted.', 'woocommerce-gateway-advanced-peach-payments'), 'success' );
		wp_safe_redirect( get_permalink( woocommerce_get_page_id( 'myaccount' ) ) );
		exit;
	}

	add_action( 'wp', 'woocommerce_peach_handle_delete_card' );

	/**
	 * account_cc function.
	 *
	 * @access public
	 * @return void
	 */
	function woocommerce_peach_saved_cards() {
		$credit_cards = get_user_meta( get_current_user_id(), '_peach_payment_id', false );

		if ( ! $credit_cards )
			return;

		$credit_cards = get_user_meta( get_current_user_id(), '_peach_payment_id', false );

		if ( ! $credit_cards )
			return;
		?>
			<h2 id="saved-cards" style="margin-top:40px;"><?php _e('Saved cards', 'woocommerce-gateway-advanced-peach-payments' ); ?></h2>
			<table class="shop_table">
				<thead>
					<tr>
						<th><?php _e('Brand', 'woocommerce-gateway-advanced-peach-payments'); ?></th>
						<th><?php _e('Card Number','woocommerce-gateway-advanced-peach-payments'); ?></th>
						<th><?php _e('Expires','woocommerce-gateway-advanced-peach-payments'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $credit_cards as $i => $credit_card ) : ?>
					<tr>
						<td><?php echo get_card_brand_image( $credit_card['brand'] ); ?></td>
                        <td><?php echo '**** **** **** ' . esc_html($credit_card['active_card'] ); ?></td>
                        <td><?php echo esc_html( $credit_card['exp_month'] ) . '/' . esc_html( $credit_card['exp_year'] ); ?></td>
						<td>
                            <form action="" method="POST">
                                <?php wp_nonce_field ( 'peach_del_card' ); ?>
                                <input type="hidden" name="peach_delete_card" value="<?php echo esc_attr($i); ?>">
                                <input type="submit" class="button" value="<?php _e( 'Delete card', 'woocommerce-gateway-advanced-peach-payments' ); ?>">
                            </form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php
	}

	add_action( 'woocommerce_after_my_account', 'woocommerce_peach_saved_cards' );

	function get_card_brand_image( $brand ) {
		switch ( $brand ) {
			case 'MASTER':
				$html = '<img src="' . plugins_url( '/assets/images/mastercard.png', __FILE__ ) . '" title="Mastercard" alt="Mastercard" />';
				break;
			case 'VISA':
				$html = '<img src="' . plugins_url( '/assets/images/visa.png', __FILE__ ) . '" title="VISA" alt="VISA" />';
				break;
			case 'DINERS':
				$html = '<img src="' . plugins_url( '/assets/images/diners.png', __FILE__ ) . '" title="DINERS" alt="DINERS" />';
				break;
			case 'AMEX':
				$html = '<img src="' . plugins_url( '/assets/images/amex.png', __FILE__ ) . '" title="AMEX" alt="AMEX" />';
				break;									
			default:
				$html = '';
				break;
		}

		return $html;
	}



} // End advanced_woocommerce_peach_payments_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function advanced_woocommerce_peach_payments_add_gateway( $methods ) {
	if ( class_exists( 'WC_Subscriptions_Order' ) ) {

		if ( ! function_exists( 'wcs_create_renewal_order' ) ) { // Subscriptions < 2.0
			$methods[] = 'WC_Peach_Payments_Subscriptions_Deprecated';
		} else {
			$methods[] = 'WC_Peach_Payments_Subscriptions';
		}		
		
	}else {
		$methods[] = 'WC_Peach_Payments';
	}
	return $methods;
} // End advanced_woocommerce_peach_payments_add_gateway()

}