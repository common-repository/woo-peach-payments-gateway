<?php
/**
 * Peach Payments Gateway
 *
 * Provides an Peach Payments WPF Gateway
 *
 * @class 		WC_Peach_Payments
 * @extends		WC_Payment_Gateway
 * @version		1.6.7
 * @package		WooCommerce/Classes/Payment
 * @author 		Nitin Sharma
 */

class WC_Peach_Payments extends WC_Payment_Gateway {

	public $payment = '';
	/**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
	public function __construct() {
		global $woocommerce;
		

		$this->id 			= 'peach-payments';
		$this->method_title = __( 'Peach Payments', 'woocommerce-gateway-peach-payments' );
		$this->icon 		= '';
		$this->transaction_mode 		= '';
		$this->has_fields 	= true;
		$this->supports 			= array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
			'pre-orders'
		);
		$this->definePeachPaymentConstants();

		$this->available_currencies = array( 'ZAR' );

		// Load the form fields.
		$this->init_form_fields();

		$this->order_button_text = __( 'Proceed to payment', 'woocommerce-gateway-peach-payment' );

		// Load the settings.
		$this->init_settings();

		// Get setting values
		foreach ( $this->settings as $key => $val ) $this->$key = $val;

		// Switch the Gateway to the Live url if it is set to live.		
		if ( $this->transaction_mode == 'LIVE' ) {
			$this->gateway_url = PAYMENT_GATEWAY_URL.'v1/checkouts';				
			$this->post_query_url = PAYMENT_GATEWAY_URL.'v1/payment';	
			$this->registration_url = PAYMENT_GATEWAY_URL.'v1/registrations';
			$this->refund_url = PAYMENT_GATEWAY_URL.'v1/payments';		
		} else {
			$this->gateway_url = PAYMENT_GATEWAY_URL.'v1/checkouts';
			$this->post_query_url = PAYMENT_GATEWAY_URL.'v1/payment';
			$this->registration_url = PAYMENT_GATEWAY_URL.'v1/registrations';
			$this->refund_url = PAYMENT_GATEWAY_URL.'v1/payments';			
		}

		$this->base_request = array(	     			      	
		      	'authentication.userId'			=> $this->username,
		      	'authentication.password'		=> $this->password,
		      	'authentication.entityId'		=> $this->sender    	
		      	
				);
				
		// Hooks
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );  // WC >= 2.0
		add_action( 'admin_notices', array( $this, 'ecommerce_ssl_check' ) );
		
		// Add Copy and Pay form to receipt_page
		add_action( 'woocommerce_receipt_peach-payments', array( $this, 'receipt_page' ) );

		// API Handler
		add_action( 'woocommerce_api_wc_peach_payments', array( $this, 'process_payment_status') );

		//Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		
		//add_action( 'woocommerce_order_status_refunded',  array( $this, 'my_change_status_function'), 10, 2 );
		add_action( 'woocommerce_order_status_refunded',  array( $this, 'process_refund'), 10, 2 );

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment12' ) );
			//add_action( 'wc_pre_orders_process_pre_order_completion_payment', array( $this, 'process_pre_order_release_payment12' ), 10, 2  );


		}

	}

	/**
	 * Define WC Constants.
	 */
	private function definePeachPaymentConstants() {
		if ( $this->transaction_mode == 'LIVE' ) {
			define('PAYMENT_GATEWAY_URL','https://oppwa.com/');	
		}else{
			define('PAYMENT_GATEWAY_URL','https://test.oppwa.com/');
		}
			define('REGISTRATION_NOT_VALID','100.150.203');
			define('REGISTRATION_DEREGISTERED','100.150.202');
			define('REQUEST_SUCCESSFULLY_PROCESSED','000.100.110');
			define('SUCCESSFULLY_REQUEST','000.000.100');
			define('TRANSACTION_SUCCEEDED','000.000.000');

	}


	
   /*
    *   Function Name : init_form_fields
    *   Description   : Initialize Gateway Settings form fields
    *   Author        : Nitin Sharma
    *   Created On    : 12-Sept-2016
    *   Parameters    : VOID 
    *   Return Value  : @return void
    */
	function init_form_fields() {
		$this->form_fields = array(
				'enabled'     	=> array(
			        'title'       	=> __( 'Enable/Disable', 'woocommerce-gateway-peach-payments' ),
			        'label'       	=> __( 'Enable Peach Payments', 'woocommerce-gateway-peach-payments' ),
			        'type'        	=> 'checkbox',
			        'description' 	=> '',
			        'default'     	=> 'no'
		        ),

				'card_storage'     	=> array(
			        'title'       	=> __( 'Card Storage', 'woocommerce-gateway-peach-payments' ),
			        'label'       	=> __( 'Enable Card Storage', 'woocommerce-gateway-peach-payments' ),
			        'type'        	=> 'checkbox',
			        'description' 	=> __( 'Allow customers to store cards against their account. Required for subscriptions.', 'woocommerce-gateway-peach-payments' ),
			        'default'     	=> 'yes'
		        ),

				'title'       	=> array(
					'title'       	=> __( 'Title', 'woocommerce-gateway-peach-payments' ),
					'type'        	=> 'text',
					'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-peach-payments' ),
					'default'     	=> __( 'Credit Card', 'woocommerce-gateway-peach-payments' )
				),

				'description' 	=> array(
					'title'       	=> __( 'Description', 'woocommerce-gateway-peach-payments' ),
					'type'        	=> 'textarea',
					'description'	=> __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-peach-payments' ),
					'default'	    => 'Pay with your credit card via Peach Payments.'
				),

				'cards' 		=> array(
					'title'			=> __( 'Supported Cards', 'woocommerce-gateway-peach-payments'),
					'type'			=> 'multiselect',
					'description'	=> __( 'Choose the cards you wish to accept.', 'woocommerce-gateway-peach-payments'),
					'options'		=> array( 
										'VISA' => 'VISA', 
										'MASTER' => 'Master Card', 
										'AMEX' => 'American Express',
										'DINERS' => 'Diners Club',
									),
					'default'		=> array( 'VISA', 'MASTER' ),
					'class'         => 'chosen_select',
                    'css'           => 'width: 450px;'
				),

				'username'    => array(
					'title'		    => __( 'User Login', 'woocommerce-gateway-peach-payments' ),
					'type'        	=> 'text',
					'description' 	=> __( 'This is the API username generated within the Peach Payments Commerce gateway.', 'woocommerce-gateway-peach-payments' ),
					'default'     	=> ''
				),

				'password'    => array(
					'title'       	=> __( 'User Password', 'woocommerce-gateway-peach-payments' ),
					'type'        	=> 'password',
					'description' 	=> __( 'This is the API user password generated within the Peach Payments Commerce gateway.', 'woocommerce-gateway-peach-payments' ),
					'default'     	=> ''
				),

				'sender'	=> array(
					'title'       => __( 'Entity ID', 'woocommerce-gateway-peach-payments' ),
					'type'        => 'text',
					'description' => __( '', 'woocommerce-gateway-peach-payments' ),
					'default'     => ''
				),

				'channel'    => array(
					'title'       => __( 'Recurring Channel ID', 'woocommerce-gateway-peach-payments' ),
					'type'        => 'text',
					'description' => __( '', 'woocommerce-gateway-peach-payments' ),
					'default'     => ''
				),	

				'transaction_mode'   => array(
					'title'       => __( 'Transaction Mode', 'woocommerce-gateway-peach-payments' ),
					'type'        => 'select',
					'description' => __( 'Set your gateway to live when you are ready.', 'woocommerce-gateway-peach-payments' ),
					'default'     => 'INTEGRATOR_TEST',
					'options'     => array(
							'INTEGRATOR_TEST'	      => 'Test Mode',
							
							'LIVE'		     		  => 'Live'
					)
				)
		);
	}	
	
	/*
    *   Function Name : enqueue_scripts
    *   Description   : Register and enqueue specific JavaScript.
    *   Author        : Nitin Sharma
    *   Created On    : 12-Sept-2016
    *   Parameters    : VOID 
    *   Return Value  : Return early if no settings page is registered.
    */
	public function enqueue_scripts() {
	
		if ( is_checkout_pay_page() && !isset($_GET['registered_payment']) )  {					
			wp_enqueue_style( 'peach-payments-widget-css', plugins_url( 'assets/css/cc-form.css', dirname(__FILE__) ) );
		}
	
	}	
	
	
	/*
    *   Function Name : ecommerce_ssl_check
    *   Description   : Check if SSL is enabled.
    *   Author        : Nitin Sharma
    *   Created On    : 13-Sept-2016
    *   Parameters    : VOID 
    *   Return Value  : VOID
    */
	function ecommerce_ssl_check() {
		if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
			echo '<div class="error"><p>We have detected that you currently don\'t have SSL enabled. Peach Payments recommends using SSL on your website. Please enable SSL and ensure your server has a valid SSL certificate.</p></div>';
		}
	}	

	/*
    *   Function Name : payment_fields
    *   Description   : Adds option for registering or using existing Peach Payments details
    *   Author        : Nitin Sharma
    *   Created On    : 13-Sept-2016
    *   Parameters    : VOID 
    *   Return Value  : VOID
    */
	function payment_fields() {

		?>
		<fieldset>

        <?php if ( is_user_logged_in() && $this->card_storage == 'yes' ) : ?>

				<p class="form-row form-row-wide">

					<?php if ( $credit_cards = get_user_meta( get_current_user_id(), '_peach_payment_id', false ) ) : ?>

						<?php foreach ( $credit_cards as $i => $credit_card ) : ?>
							<input type="radio" id="peach_card_<?php echo $i; ?>" name="peach_payment_id" style="width:auto;" value="<?php echo $i; ?>" />
							<label style="display:inline;" for="peach_card_<?php echo $i; ?>"><?php echo get_card_brand_image( $credit_card['brand'] ); ?> <?php echo '**** **** **** ' . $credit_card['active_card']; ?> (<?php echo $credit_card['exp_month'] . '/' . $credit_card['exp_year'] ?>)</label><br />
						<?php endforeach; ?>

						<br /> <a class="button" style="float:right;" href="<?php echo get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ); ?>#saved-cards"><?php _e( 'Manage cards', 'woocommerce-gateway-peach-payments' ); ?></a>

					<?php endif; ?>

					<input type="radio" id="saveinfo" name="peach_payment_id" style="width:auto;" value="saveinfo"/> <label style="display:inline;" for="saveinfo"><?php _e( 'Use a new credit card and store method for future use.', 'woocommerce-gateway-peach-payments' ); ?></label><br />

					<input type="radio" id="dontsave" name="peach_payment_id" style="width:auto;" value="dontsave"/> <label style="display:inline;" for="dontsave"><?php _e( 'Use a new credit card without storing.', 'woocommerce-gateway-peach-payments' ); ?></label>

				</p>
				<div class="clear"></div>

		<?php else: ?>

				<p><?php _e( 'Pay using a credit card', 'woocommerce-gateway-peach-payments' ); ?></p>

		<?php endif; ?>

		</fieldset>
		<?php
	}

	/*
    *   Function Name : payment_fields
    *   Description   : Display admin options
    *   Author        : Nitin Sharma
    *   Created On    : 14-Sept-2016
    *   Parameters    : VOID 
    *   Return Value  : VOID
    */
	function admin_options() {
		?>
		<h3><?php _e( 'Peach Payments', 'woocommerce-gateway-peach-payments' ); ?></h3>

		<?php if ( 'ZAR' == get_option( 'woocommerce_currency' ) ) { ?>
	    	<table class="form-table">
	    	<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			?>
			</table><!--/.form-table-->
		<?php } else { ?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce-gateway-peach-payments' ); ?></strong> <?php echo sprintf( __( 'Choose South African Rands as your store currency in <a href="%s">General Options</a> to enable the Peach Payments Gateway.', 'woocommerce-gateway-peach-payments' ), admin_url( '?page=woocommerce_settings&tab=general' ) ); ?></p></div>
		<?php }
	}
	
	/*
    *   Function Name : process_payment
    *   Description   : Process the payment and return the result
    *   Author        : Nitin Sharma
    *   Created On    : 14-Sept-2016
    *   Parameters    : int $order_id 
    *   Return Value  : array
    */

    function process_payment( $order_id ) {
     	global $woocommerce;

     	$order = new WC_Order( $order_id );    
     	
     	if (   class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order->id ) ) {
     	
				// perform a simple authorization/void (or whatever method your gateway requires)
				// to get the payment token that may be used later to charged the customer's payment method

				// mark order as pre-ordered, this will also save meta that indicates a payment token
				// exists for the pre-order so that it may be charged upon release		
          
				return $this->process_pre_order( $order_id );

				// here you should empty the cart and perform whatever other tasks a successful purchase requires

     		
		}
	
     	try {
     		//This is Called when customer used the saved card.
     		if ( isset( $_POST['peach_payment_id'] ) && ctype_digit( $_POST['peach_payment_id'] ) ) {
				
				$payment_ids = get_user_meta( $order->user_id, '_peach_payment_id', false );
				$payment_id = $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'];
				
				//throw exception if payment method does not exist
				if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
					throw new Exception( __( 'Invalid Payment Method', 'woocommerce-gateway-peach-payments' ) );
				}



				$redirect_url = $this->execute_post_payment_request( $order, $order->order_total, $payment_id );				
				//throw exception if payment is not accepted
				if ( is_wp_error( $redirect_url ) ) {
					throw new Exception( $redirect_url->get_error_message() );
				}

				return array(
				          'result'   => 'success',
				          'redirect' => $redirect_url
				        );
			}
			else {

				$order_request = array(
			     		'merchantTransactionId'				=> $order_id,
			     		'customer.merchantCustomerId'		=> $order->user_id,
			     		'customer.givenName'				=> $order->billing_first_name." ".$order->billing_last_name,				     	       		
				     	'billing.street1'					=> $order->billing_address_1,        		
				        'billing.postcode'					=> $order->billing_postcode,
				        'billing.city'						=> $order->billing_city,        		
				        'billing.state'						=> $order->billing_state,
				        'billing.country'					=> $order->billing_country,				        
				        'customer.email'					=> $order->billing_email,
				        'customer.ip'						=> $_SERVER['REMOTE_ADDR']
			     		);

				if ( $_POST['peach_payment_id'] == 'saveinfo' ) {
					$payment_request = array(
						'paymentType'						=> 'DB',
						'createRegistration'				=> true
				      	);

					if ( $this->transaction_mode == 'CONNECTOR_TEST' || 'LIVE' ) {
						$payment_request['currency'] = 'ZAR';
						$payment_request['amount'] = $order->order_total;
					}

					
				} 
				else {
					$payment_request = array(
						'paymentType'						=> 'DB',
						'merchantInvoiceId'					=> 'Order ' . $order->get_order_number(),
			     		'amount'							=> $order->order_total,
				      	'currency'							=> 'ZAR' 
				      	);					
				}

				$order_request = array_merge( $order_request, $this->base_request );
				$request = array_merge( $payment_request, $order_request );
				$json_token_response = $this->generate_token( $request );
				/*echo "<pre>";
				print_r($json_token_response);
				die();*/
				if ( is_wp_error( $json_token_response ) ) {
					throw new Exception( $json_token_response->get_error_message() );
				}

				//token received - offload payment processing to copyandpay form
				return array(
		          'result'   => 'success',
		          'redirect' => $order->get_checkout_payment_url( true )
		        );

			}

     	} catch( Exception $e ) {
				wc_add_notice( __('Error:', 'woocommerce-gateway-peach-payments') . ' "' . $e->getMessage() . '"' , 'error' );
				return;
		}
		
     }
		
	/*
    *   Function Name : receipt_page
    *   Description   : Trigger the payment form for the payment page shortcode.
    *   Author        : Nitin Sharma
    *   Created On    : 14-Sept-2016
    *   Parameters    : object $order
    *   Return Value  : null
    */	
	function receipt_page( $order_id ) {

		if ( isset( $_GET['registered_payment'] ) ) {
			$status = $_GET['registered_payment'];
			$this->process_registered_payment_status( $order_id, $status );
		} else {
			echo $this->generate_peach_payments_form( $order_id );
		}
	}
	
	/*
    *   Function Name : generate_peach_payments_form
    *   Description   : Generate the Peach Payments Copy and Pay form
    *   Author        : Nitin Sharma
    *   Created On    : 15-Sept-2016
    *   Parameters    : mixed $order_id
    *   Return Value  : string
    */
    function generate_peach_payments_form( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		$payment_token = get_post_meta( $order_id, '_peach_payment_token', true );
		
		$merchant_endpoint = add_query_arg( 'wc-api', 'WC_Peach_Payments', home_url( '/' ) );

		$supported_cards = implode( ' ', $this->cards );
		
		$checkoutCode = '<script src="' . PAYMENT_GATEWAY_URL . 'v1/paymentWidgets.js?checkoutId='. $payment_token .'"></script>';
		$checkoutCode .='<form action="' . $merchant_endpoint . '" class="paymentWidgets">' . $supported_cards . '</form>';
		return $checkoutCode;
	}

	/*
    *   Function Name : process_payment_status
    *   Description   : WC API endpoint for Copy and Pay response, call it when customer using the new card
    *   Author        : Nitin Sharma
    *   Created On    : 15-Sept-2016
    *   Parameters    : void
    *   Return Value  : void
    */
	function process_payment_status() {
		global $woocommerce;

		$parsed_response='';
		 $token = $_GET['id'];		
		 $parsed_response = $this->get_token_status( $token );	
		//echo "<pre>";
		//print_r($parsed_response);
		//die();	
		if ( is_wp_error( $parsed_response ) ) {
			$order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway server - Peach Payments', 'woocommerce-gateway-peach-payments') );
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}	

		$order_id = $parsed_response->merchantTransactionId;
		$order = new WC_Order( $order_id );
		/*echo "<pre>";
		print_r($parsed_response);
		echo "ORDER DETAILS:";
		print_r($order);
		die();*/
		$preOrderStatus = get_post_meta( $order_id, '_wc_pre_orders_is_pre_order', true );

		//If you are using a Stored card,  or not storing a card at all this will process the completion of the order. 
		if ( $parsed_response->paymentType  == 'DB' || $parsed_response->paymentType  == 'PA' ) {
				if($parsed_response->registrationId!=''){
						//handle failed registration					
						switch ($parsed_response->result->code) {
							 case REGISTRATION_NOT_VALID:
							 	$order->update_status('pending', __('Registration Failed: Card registration was not accpeted - Peach Payments', 'woocommerce-gateway-peach-payments') );
								wp_safe_redirect( $order->get_checkout_payment_url( true ) );
								exit;
							 break;
							 case REGISTRATION_DEREGISTERED:
							 	$order->update_status('pending', __('Registration Failed: Card registration is already deregistered - Peach Payments', 'woocommerce-gateway-peach-payments') );
								wp_safe_redirect( $order->get_checkout_payment_url( true ) );								
								exit;
							 break;
							 default:
                             break;

						
						
					}					
				}

			if ( $parsed_response->result->code == REQUEST_SUCCESSFULLY_PROCESSED || $parsed_response->result->code == TRANSACTION_SUCCEEDED ) {
				if($parsed_response->registrationId!=''){
					$this->add_customer( $parsed_response );
				}
				update_post_meta( $order_id, '_peach_payment_id', $parsed_response->id );
				if($preOrderStatus==1){						
							WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );							
						
				}else{
						$order->payment_complete();
				}
				$order->add_order_note( sprintf(__('Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;
			} 
			else {
				$order->update_status('failed');
				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;
			}
			
		}
		
		
	}

	/*
    *   Function Name : process_registered_payment_status
    *   Description   : Process response from registered payment request on POST api
    *   Author        : Nitin Sharma
    *   Created On    : 7-Sept-2016
    *   Parameters    : string $order_id
    *   Return Value  : void
    */
	
	function process_registered_payment_status( $order_id, $status ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		if ( $status == 'NOK' ) {
			$order->update_status('failed');
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}
		elseif ( $status == 'ACK' ) {
			$order->payment_complete();
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}
	}


	/*
    *   Function Name : generate_token
    *   Description   : Generate token for Copy and Pay API 
    *   Author        : Nitin Sharma
    *   Created On    : 7-Sept-2016
    *   Parameters    : object $response
    *   Return Value  : void
    */

	
	function generate_token( $request ) {
		global $woocommerce;		
		$response = wp_remote_post( $this->gateway_url, array(
			'method'		=> 'POST', 
			'body' 			=> $request,
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));
		//print_r($response);die();

		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );
		//echo "<pre>";print_r($parsed_response);
		//echo "<pre>";echo $parsed_response->id;die();
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {

			return new WP_Error( 'peach_error', $parsed_response->error->message );

		} else {
			update_post_meta( $request['merchantTransactionId'], '_peach_payment_token', $parsed_response->id );
		}

		return $parsed_response;
		
	}

	
	/*
    *   Function Name : get_token_status
    *   Description   : Get status of token after Copy and Pay API 
    *   Author        : Nitin Sharma     
    *   Parameters    : string $token
    *   Return Value  : object
    */
	function get_token_status( $token ) {
		global $woocommerce;
		
		 $url = $this->gateway_url . "/" . $token."/payment";
		
		$response = wp_remote_post( $url, array(
			'method'		=> 'GET', 
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));
		//echo "<pre>";
		//print_r($response);
		//die();
		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$get_response = json_decode( $response['body'] );
		//echo "<pre>";
		//print_r($get_response);
		//die();
		return $get_response;
	}

	

	/*
    *   Function Name : execute_post_payment_request
    *   Description   : Execute payment request through POST endpoint and returns redirect URL
    *   Author        : Nitin Sharma    
    *   Parameters    : object $order, string $amount ,string $payment_method_id
    *   Return Value  : string
    */
	function execute_post_payment_request( $order, $amount, $payment_method_id ,$payment_type='DB') {
		global $woocommerce;
		$merchant_endpoint = add_query_arg( 'wc-api', 'WC_Peach_Payments', home_url( '/' ) );
		$this->save_base_request = array(	     			      	
		      	'authentication.userId'			=> $this->username,
		      	'authentication.password'		=> $this->password,
		      	'authentication.entityId'		=> $this->channel  //Replace it For Save Card  	
		      	
				);
				

		$payment_request = array(
							      	'paymentType'					=> $payment_type,
							      	'merchantTransactionId'			=> $order->id,
						     		'customer.merchantCustomerId'	=> $order->user_id,  
							      	'merchantInvoiceId'				=> 'Order #' . $order->get_order_number(),
						     		'amount'						=> $amount,
							      	'currency'						=> 'ZAR',
							      	'shopperResultUrl'				=> $merchant_endpoint,
							      	'authentication.entityId'		=> $this->channel 	      	
							      );

		
		$request = array_merge( $payment_request, $this->save_base_request );
		//echo "<pre>";
		//echo "Request:".print_r($request)."<br>";
		
        $response = wp_remote_post( $this->registration_url."/".$payment_method_id."/payments", array(
			'method'		=> 'POST', 
			'body'			=> $request,
			'timeout' 		=> 70,
			'sslverify' 	=> true,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));

        //print_r($response);
       // die();
		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );

	    //create redirect link
	    $redirect_url = $this->get_return_url( $order );
	    $order_id = $parsed_response->merchantTransactionId;
	    $preOrderStatus = get_post_meta( $order_id, '_wc_pre_orders_is_pre_order', true );

	    if ( $parsed_response->result->code == REQUEST_SUCCESSFULLY_PROCESSED || $parsed_response->result->code == TRANSACTION_SUCCEEDED ) {
	    		update_post_meta( $order_id, '_peach_payment_id', $parsed_response->id );
	    		if($preOrderStatus=='1'){
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
				$order->add_order_note( sprintf(__('Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				}else{
					$order->payment_complete();
					$order->add_order_note( sprintf(__('Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				}
				return add_query_arg( 'registered_payment', 'ACK', $redirect_url );
			} 
			else {
				$order->update_status('failed', sprintf(__('Payment Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				return add_query_arg ('registered_payment', 'NOK', $redirect_url );
			}
		
	}

	/*
    *   Function Name : add_customer 
    *   Description   : Add Customer Detial
    *   Author        : Nitin Sharma    
    *   Parameters    : object $response
    *   Return Value  : void
    */
	function add_customer( $response ) {
		$user_id = $response->customer->merchantCustomerId;

		if ( isset( $response->card->last4Digits ) )
			add_user_meta( $user_id, '_peach_payment_id', array(
				'payment_id' 	=> $response->registrationId,
				'active_card' 	=> $response->card->last4Digits,
				'brand'			=> $response->paymentBrand,
				'exp_year'		=> $response->card->expiryYear,
				'exp_month'		=> $response->card->expiryMonth,
			) );
	
	}
	
	/*
    *   Function Name : process_refund 
    *   Description   : Process for full and partial refund 
    *   Author        : Nitin Sharma    
    *   Parameters    : order_id
    *   Return Value  : void
    */
	
	public function process_refund( $order_id,$amount = NULL, $reason = '' ) {
		global $woocommerce;
		//echo "OrderId->".$order_id;
		$totalRefundAmount=0;	 
		
		$order = new WC_Order( $order_id );
		//echo "<pre>";
		//print_r($_POST);
		 $payment_id = get_post_meta( $order_id, '_peach_payment_id', true );	
		//die();
		if(sanitize_text_field( $_POST['refund_amount'] )==''){
			$refundId=$_POST['order_refund_id'];
			if(is_array($refundId) && !empty($refundId)){
				foreach ($refundId as $key => $value) {
			 		$totalRefundAmount +=  get_post_meta( $value, '_refund_amount', true );
			 	}
			 }
			 $amount  =  $order->get_total() - $totalRefundAmount;
		}else{
			 $amount= wc_format_decimal( sanitize_text_field( $_POST['refund_amount'] ), wc_get_price_decimals());
			
     		 $max_refund  = wc_format_decimal( $order->get_total() - $order->get_total_refunded(), wc_get_price_decimals() );

		     if ( ! $amount || $max_refund < $amount) {
		       // throw new exception( __( 'Invalid refund amount', 'woocommerce' ) );
		        $order->add_order_note( sprintf(__('Payment refund Failed due to amount format', 'woocommerce-gateway-peach-payments'),''  ) );
		        return false;
		      }
			
		}
		//echo $totalRefundAmount;
		//echo "Final Amount:->".$amount;
		//die();
		$parsed_response=$this->execute_refund_payment_status( $order, $amount, $payment_id );

		//echo "<pre>";
		//print_r($parsed_response);
		//die();
		if ( $parsed_response->result->code == REQUEST_SUCCESSFULLY_PROCESSED || $parsed_response->result->code == TRANSACTION_SUCCEEDED ) {			
			$order->add_order_note( sprintf(__('Payment refund successfully: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean( $parsed_response->result->description ) ) );
			return true;
			
		} 
		else {
			$order->update_status('processing', sprintf(__('Refund Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean( $parsed_response->result->description ) ) );
			return false;
		}

	}
	/*
    *   Function Name : execute_refund_payment_status
    *   Description   : Execute payment request through POST endpoint and returns redirect URL
    *   Author        : Nitin Sharma    
    *   Parameters    : object $order, string $amount ,string $payment_method_id
    *   Return Value  : string
    */
	function execute_refund_payment_status( $order, $amount, $payment_id ) {
		global $woocommerce;		
		$this->save_base_request = array(	     			      	
							      	'authentication.userId'			=> $this->username,
							      	'authentication.password'		=> $this->password,
							      	'authentication.entityId'		=> $this->sender  	
		      	
				                   );				

		$payment_request = 		   array(
							      	'paymentType'					=> 'RF',							      	
						     		'amount'						=> number_format($amount,2),
							      	'currency'						=> 'ZAR',
							           	
							      );

		
		$request = array_merge( $payment_request, $this->save_base_request );		
        $response = wp_remote_post( $this->refund_url."/".$payment_id, array(
			'method'		=> 'POST', 
			'body'			=> $request,
			'timeout' 		=> 70,
			'sslverify' 	=> true,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));

		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );
		return $parsed_response ;
	    		
		
	}
 

 /*
    *   Function Name : process_pre_order
    *   Description   : Process the pre orders payment and return the result
    *   Author        : Nitin Sharma
    *   Created On    : 27-Oct-2016
    *   Parameters    : int $order_id 
    *   Return Value  : array
    */

    function process_pre_order( $order_id ) {
     	global $woocommerce;

     	$order = new WC_Order( $order_id );
     	
     	//echo "<pre>";
     	//print_r($order);
     	//$order_id=$order->id;
		$payment_id = get_post_meta( $order_id, '_peach_payment_id', true );		
     	//die();
     	
     	try {
     		//If pre order convert to normal
     		if($payment_id!=''){

     		
     			
     			$this->process_pre_order_release_payment12( $order );
     			exit;
     		}
     		// if pre-order type will be charged upon release
    			if (WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)) {
        			$PreOrderType='DB';
        			// get pre-order amount
        			$order_items = $order->get_items();
    				$product = $order->get_product_from_item( array_shift( $order_items ) );
					$preOrderAmount = WC_Pre_Orders_Product::get_pre_order_fee( $product );
					if ( $preOrderAmount <= 0 ){
						$preOrderAmount=1;
						$PreOrderType='PA';
					}

    			}else{
    				$PreOrderType='DB';
    				$preOrderAmount=$order->order_total;
    			}
     		if ( isset( $_POST['peach_payment_id'] ) && ctype_digit( $_POST['peach_payment_id'] ) ) {
				
				$payment_ids = get_user_meta( $order->user_id, '_peach_payment_id', false );
				$payment_id = $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'];

				
				//throw exception if payment method does not exist
				if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
					throw new Exception( __( 'Invalid Payment Method', 'woocommerce-gateway-peach-payments' ) );
				}
				
				$redirect_url = $this->execute_post_payment_request( $order, $preOrderAmount, $payment_id,$PreOrderType );				
				//throw exception if payment is not accepted
				if ( is_wp_error( $redirect_url ) ) {
					throw new Exception( $redirect_url->get_error_message() );
				}

				return array(
				          'result'   => 'success',
				          'redirect' => $redirect_url
				        );
			}
			elseif ( isset( $_POST['peach_payment_id'] ) && ( $_POST['peach_payment_id'] == 'dontsave' ) ) {
    					throw new Exception( __( 'You need to store your payment method in order to purchase a pre-order.', 'woocommerce-gateway-peach-payments' ) );    		
			}else {
				//echo "<pre>";
				//print_r($_POST);
				//die();

				$order_request = array(
			     		'merchantTransactionId'				=> $order_id,
			     		'customer.merchantCustomerId'		=> $order->user_id,
			     		'customer.givenName'				=> $order->billing_first_name." ".$order->billing_last_name,				     	       		
				     	'billing.street1'					=> $order->billing_address_1,        		
				        'billing.postcode'					=> $order->billing_postcode,
				        'billing.city'						=> $order->billing_city,        		
				        'billing.state'						=> $order->billing_state,
				        'billing.country'					=> $order->billing_country,				        
				        'customer.email'					=> $order->billing_email,
				        'customer.ip'						=> $_SERVER['REMOTE_ADDR']
			     		);

				if ( $_POST['peach_payment_id'] == 'saveinfo' ) {
					$payment_request = array(
						'paymentType'						=> $PreOrderType,
						'createRegistration'				=> true
				      	);

					if ( $this->transaction_mode == 'CONNECTOR_TEST' || 'LIVE' ) {
						$payment_request['currency'] = 'ZAR';
						$payment_request['amount'] = $preOrderAmount;
					}

					
				} 
				else {
					$payment_request = array(
						'paymentType'						=> $PreOrderType,
						'merchantInvoiceId'					=> 'Order ' . $order->get_order_number(),
			     		'amount'							=> $order->order_total,
				      	'currency'							=> 'ZAR' 
				      	);					
				}

				$order_request = array_merge( $order_request, $this->base_request );
				$request = array_merge( $payment_request, $order_request );
				$json_token_response = $this->generate_token( $request );
				/*echo "<pre>";
				print_r($json_token_response);
				die();*/
				if ( is_wp_error( $json_token_response ) ) {
					throw new Exception( $json_token_response->get_error_message() );
				}

				//token received - offload payment processing to copyandpay form
				return array(
		          'result'   => 'success',
		          'redirect' => $order->get_checkout_payment_url( true )
		        );

			}

     	} catch( Exception $e ) {
				wc_add_notice( __('Error:', 'woocommerce-gateway-peach-payments') . ' "' . $e->getMessage() . '"' , 'error' );
				return;
		}
		
     }
 	/*
    *   Function Name : process_pre_order_release_payment12
    *   Description   : Process the pre orders payment and complete the payment 
    *   Author        : Nitin Sharma
    *   Created On    : 27-Oct-2016
    *   Parameters    : int $order 
    *   Return Value  : array
    */

    	function process_pre_order_release_payment12( $order ) {
    	global $woocommerce;
    	$order_id=$order->id;
		$payment_id = get_post_meta( $order_id, '_peach_payment_id', true );
				
		//throw exception if payment method does not exist
		/*if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
			throw new Exception( __( 'Invalid Payment Method', 'woocommerce-gateway-peach-payments' ) );
		}
		*/
		// get pre-order fee amount
		if (WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)) {
			$order_items = $order->get_items();
    		$product = $order->get_product_from_item( array_shift( $order_items ) );
			$preOrderAmount = WC_Pre_Orders_Product::get_pre_order_fee( $product );
			$chargeAmount=$order->order_total-$preOrderAmount;
		}else{
			$chargeAmount=$order->order_total;
		}
        

		$redirect_url = $this->execute_pre_payment_request( $order, $chargeAmount, $payment_id,'DB' );				
		//throw exception if payment is not accepted
		if ( is_wp_error( $redirect_url ) ) {
			throw new Exception( $redirect_url->get_error_message() );
		}

		return array(
		          'result'   => 'success',
		          'redirect' => $redirect_url
		        );

	}


	/*
    *   Function Name : execute_pre_payment_request
    *   Description   : Execute payment request through POST endpoint and returns redirect URL
    *   Author        : Nitin Sharma    
    *   Parameters    : object $order, string $amount ,string $payment_method_id
    *   Return Value  : string
    */
	function execute_pre_payment_request( $order, $amount, $payment_id ,$payment_type='DB') {
		global $woocommerce;
		$merchant_endpoint = add_query_arg( 'wc-api', 'WC_Peach_Payments', home_url( '/' ) );
		$this->save_base_request = array(	     			      	
		      	'authentication.userId'			=> $this->username,
		      	'authentication.password'		=> $this->password,
		      	'authentication.entityId'		=> $this->channel  //Replace it For Save Card  	
		      	
				);
				

		$payment_request = array(
							      	'paymentType'					=> $payment_type,
							      	'merchantTransactionId'			=> $order->id,
						     		
							      	'merchantInvoiceId'				=> 'Order #' . $order->get_order_number(),
						     		'amount'						=> $amount,
							      	'currency'						=> 'ZAR',
							      	'shopperResultUrl'				=> $merchant_endpoint,
							      	'authentication.entityId'		=> $this->channel 	      	
							      );

		
		$request = array_merge( $payment_request, $this->save_base_request );
		//echo "<pre>";
		//echo "Request:".print_r($request)."<br>";
		/*fwrite($fp,"----------- Request process For pre order by Cron ------------- \n ");
        fwrite($fp,print_r($request, TRUE)."\n");*/
		
        $response = wp_remote_post( $this->refund_url."/".$payment_id, array(
			'method'		=> 'POST', 
			'body'			=> $request,
			'timeout' 		=> 70,
			'sslverify' 	=> true,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));

		

           /* fwrite($fp,"----------- Response process For pre order by Cron ------------- \n ");
            fwrite($fp,print_r($response, TRUE)."\n");*/

       // print_r($response);
       // die();
		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );

	    //create redirect link
	    $redirect_url = $this->get_return_url( $order );
	    $order_id = $parsed_response->merchantTransactionId;


	    if ( $parsed_response->result->code == REQUEST_SUCCESSFULLY_PROCESSED || $parsed_response->result->code == TRANSACTION_SUCCEEDED ) {
	    	update_post_meta( $order_id, '_peach_payment_id', $parsed_response->id );
				$order->payment_complete();
				$order->add_order_note( sprintf(__('Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				return add_query_arg( 'registered_payment', 'ACK', $redirect_url );
			} 
			else {
				$order->update_status('failed', sprintf(__('Payment Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				return add_query_arg ('registered_payment', 'NOK', $redirect_url );
			}
		
	}



}