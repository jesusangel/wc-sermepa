<?php
/*  Copyright 2013  Jesús Ángel del Pozo Domínguez  (email : jesusangel.delpozo@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Plugin Name: WooCommerce sermepa payment gateway
 * Plugin URI: http://tel.abloque.com/sermepa_woocommerce.html
 * Description: sermepa payment gateway for WooCommerce
 * Version: 0.8
 * Author: Jesús Ángel del Pozo Domínguez
 * Author URI: http://tel.abloque.com
 * License: GPL3
 *
 * Text Domain: wc_sermepa_payment_gateway
 * Domain Path: /languages/
 *
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	add_action('plugins_loaded', 'init_wc_sermepa_payment_gateway', 0);
	
	function init_wc_sermepa_payment_gateway() {
	 
	    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
	    
		/**
		 * Sermepa Standard Payment Gateway
		 *
		 * Provides a Sermepa Standard Payment Gateway.
		 *
		 * @class 		WC_Sermepa
		 * @extends		WC_Payment_Gateway
		 * @version		0.7
		 * @package		
		 * @author 		Jesús Ángel del Pozo Domínguez
		 */
		   
		class WC_Sermepa extends WC_Payment_Gateway {
			
			var $notify_url;
			const merchant_data = 'sermepaNotification';
		
		    /**
		     * Constructor for the gateway.
		     *
		     * @access public
		     * @return void
		     */
			public function __construct() {
				global $woocommerce;
		
				$this->id			= 'sermepa';
				$this->icon 		= home_url() . '/wp-content/plugins/' . dirname( plugin_basename( __FILE__ ) ) . '/assets/images/icons/sermepa.png';
				$this->has_fields 	= false;
				$this->liveurl 		= 'https://sis.redsys.es/sis/realizarPago';
				$this->testurl 		= 'https://sis-t.redsys.es:25443/sis/realizarPago';
				$this->method_title     = __( 'Sermepa', 'wc_sermepa_payment_gateway' );
				$this->method_description = __( 'Pay with credit card using RedSys (Sermepa)', 'wc_sermepa_payment_gateway' );
				$this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Sermepa', home_url( '/' ) ) );
	
		        // Set up localisation
	            $this->load_plugin_textdomain();
	                
				// Load the form fields.
				$this->init_form_fields();
		
				// Load the settings.
				$this->init_settings();
		
				// Define user set variables
				$this->title                   = $this->settings['title'];
				$this->description             = $this->settings['description'];
				$this->owner_name              = $this->settings['owner_name'];
				$this->commerce_name           = $this->settings['commerce_name'];
				$this->testmode                = $this->settings['testmode'];
				$this->commerce_number         = $this->settings['commerce_number'];
				$this->terminal_number         = $this->settings['terminal_number'];
				$this->currency_id             = $this->settings['currency_id'];
				$this->secret_key              = $this->settings['secret_key'];
				$this->extended_sha1_algorithm = $this->settings['extended_sha1_algorithm'];
				//$this->form_submission_method  = ( isset( $this->settings['form_submission_method'] ) && $this->settings['form_submission_method'] == 'yes' ) ? true : false;
				$this->testmode                = $this->settings['testmode'];
				$this->debug                   = $this->settings['debug'];			
		
				// Logs
				if ( 'yes' == $this->debug ) {
					if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
						$this->log = $woocommerce->logger();
					} else {
						$this->log =  new WC_Logger();
					}
				}
		
				// Actions
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
					// Check for gateway messages using WC 1.X format
					add_action( 'init', array( $this, 'check_notification' ) );
					add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
				} else {
					// Payment listener/API hook (WC 2.X) 
					add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_notification' ) );
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				}
				add_action('valid-sermepa-standard-notification', array( $this, 'successful_request' ) );				
				add_action('woocommerce_receipt_sermepa', array( $this, 'receipt_page' ) );
				
						
				if ( !$this->is_valid_for_use() ) $this->enabled = false;
		    }
		    
			/**
	         * Localisation.
	         *
	         * @access public
	         * @return void
	         */
	        function load_plugin_textdomain() {
	                // Note: the first-loaded translation file overrides any following ones if the same translation is present
	                $locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce' );
	                $variable_lang = ( get_option( 'woocommerce_informal_localisation_type' ) == 'yes' ) ? 'informal' : 'formal';
	                load_textdomain( 'wc_sermepa_payment_gateway', WP_LANG_DIR.'/wc_sermepa_payment_gateway/wc_sermepa_payment_gateway-'.$locale.'.mo' );
	                load_plugin_textdomain( 'wc_sermepa_payment_gateway', false, dirname( plugin_basename( __FILE__ ) ).'/languages/'.$variable_lang );
	                load_plugin_textdomain( 'wc_sermepa_payment_gateway', false, dirname( plugin_basename( __FILE__ ) ).'/languages' );
	        }
		
		
		    /**
		     * Check if this gateway is enabled and available in the user's country
		     *
		     * @access public
		     * @return bool
		     */
		    function is_valid_for_use() {
		        //if (!in_array(get_woocommerce_currency(), array('AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB'))) return false;
		
		        return true;
		    }
		
			/**
			 * Admin Panel Options
			 * - Options for bits like 'title' and availability on a country-by-country basis
			 *
			 * @since 1.0.0
			 */
			public function admin_options() {
		
		    	?>
		    	<h3><?php _e('Sermepa', 'wc_sermepa_payment_gateway'); ?></h3>
		    	<p><?php _e('Sermepa works by sending the user to Sermepa to enter their payment information.', 'wc_sermepa_payment_gateway'); ?></p>
	
		    	<?php if ( $this->is_valid_for_use() ) : ?>
					<table class="form-table">
					<?php 
		    			// Generate the HTML For the settings form.
		    			$this->generate_settings_html();
		    		?>
		    		</table><!--/.form-table-->
				<?php else : ?>
		            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'wc_sermepa_payment_gateway' ); ?></strong>: <?php _e( 'Sermepa does not support your store currency.', 'wc_sermepa_payment_gateway' ); ?></p></div>
		        <?php
		        	endif;
		    }
		
		
		    /**
		     * Initialise Gateway Settings Form Fields
		     *
		     * @access public
		     * @return void
		     */
		    function init_form_fields() {
		
		    	$this->form_fields = array(
					'enabled' => array(
									'title' => __( 'Enable/Disable', 'wc_sermepa_payment_gateway' ),
									'type' => 'checkbox',
									'label' => __( 'Enable Sermepa', 'wc_sermepa_payment_gateway' ),
									'default' => 'yes'
								),
					'title' => array(
									'title' => __( 'Title', 'wc_sermepa_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'This controls the title which the user sees during checkout.', 'wc_sermepa_payment_gateway' ),
									'default' => __( 'Sermepa', 'wc_sermepa_payment_gateway' )
								),
					'owner_name' => array(
									'title' => __( 'Owner name', 'wc_sermepa_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Name and surname of the owner.', 'wc_sermepa_payment_gateway' ),
									'default' => __( 'Sermepa', 'wc_sermepa_payment_gateway' )
								),
					'commerce_name' => array(
									'title' => __( 'Commerce name', 'wc_sermepa_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'The commerce name.', 'wc_sermepa_payment_gateway' ),
									'default' => __( 'Sermepa', 'wc_sermepa_payment_gateway' )
								),
					'description' => array(
									'title' => __( 'Description', 'wc_sermepa_payment_gateway' ),
									'type' => 'textarea',
									'description' => __( 'This controls the description which the user sees during checkout.', 'wc_sermepa_payment_gateway' ),
									'default' => __("Pay with your credit card via Sermepa", 'wc_sermepa_payment_gateway')
								),
					'commerce_number' => array(
									'title' => __( 'Commerce number (FUC)', 'wc_sermepa_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Please enter your Sermepa commerce number (FUC); this is needed in order to take payment.', 'wc_sermepa_payment_gateway' ),
									'default' => ''
								),
					'terminal_number' => array(
									'title' => __( 'Terminal number', 'wc_sermepa_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Please enter your Sermepa terminal number; this is needed in order to take payment.', 'wc_sermepa_payment_gateway' ),
									'default' => '001'
								),
					'currency_id' => array(
									'title' => __( 'Currency identifier', 'wc_sermepa_payment_gateway' ),
									'type' => 'select',
									'description' => __( 'Please enter your Sermepa currency identifier; this is needed in order to take payment.', 'wc_sermepa_payment_gateway' ),
									'options' => array('978' => 'EUR (Euro)', '840' => 'USD (US Dollar)', '826' => 'GBP (British Pound)', '392' => 'JPY (Japanesse Yen)', '170' => 'Peso Colombiano', '32' => 'Peso Argentino', '124' => 'Dólar Canadiense', '152' => 'Peso Chileno', '356' => 'Rupia India', '484' => 'Nuevo peso Mexicano', '604' => 'Nuevos soles', '756' => 'Franco Suizo', '986' => 'Real Brasileño', '937' => 'Bolívar fuerte', '949' => 'Lira Turca'),
									'default' => '978'
								),
					'secret_key' => array(
									'title' => __( 'Secret key', 'wc_sermepa_payment_gateway' ),
									'type' => 'text',
									'description' => __( 'Please enter your Sermepa secret key; this is needed in order to take payment.', 'wc_sermepa_payment_gateway' ),
									'default' => ''
								),
					'extended_sha1_algorithm' => array(
									'title' => __( 'Enable extended SHA1', 'wc_sermepa_payment_gateway' ),
									'type' => 'checkbox',
									'description' => __( 'Enable extended SHA1 algorithm.', 'wc_sermepa_payment_gateway' ),
									'default' => 'Yes'
								),
//					'form_submission_method' => array(
//									'title' => __( 'Submission method', 'wc_sermepa_payment_gateway' ),
//									'type' => 'checkbox',
//									'label' => __( 'Use form submission method.', 'wc_sermepa_payment_gateway' ),
//									'description' => __( 'Enable this to post order data to Sermepa via a form instead of using a redirect/querystring.', 'wc_sermepa_payment_gateway' ),
//									'default' => 'no'
//								),
					'testing' => array(
									'title' => __( 'Gateway Testing', 'wc_sermepa_payment_gateway' ),
									'type' => 'title',
									'description' => '',
								),
					'testmode' => array(
									'title' => __( 'Sermepa sandbox', 'wc_sermepa_payment_gateway' ),
									'type' => 'checkbox',
									'label' => __( 'Enable Sermepa sandbox', 'wc_sermepa_payment_gateway' ),
									'default' => 'yes',
									'description' => sprintf( __( 'Sermepa sandbox can be used to test payments.', 'wc_sermepa_payment_gateway' ) ),
								),
					'debug' => array(
									'title' => __( 'Debug Log', 'wc_sermepa_payment_gateway' ),
									'type' => 'checkbox',
									'label' => __( 'Enable logging', 'wc_sermepa_payment_gateway' ),
									'default' => 'no',
									'description' => __( 'Log Sermepa events, inside <code>woocommerce/logs/sermepa.txt</code>' ),
								)
					);
		
		    }
		
		
			/**
			 * Get Sermepa Args for passing to the TPV server
			 *
			 * @access public
			 * @param mixed $order
			 * @return array
			 */
			function get_sermepa_args( $order ) {
				global $woocommerce;
		
				$order_id = $order->id;
		
				if ( 'yes' == $this->debug )
					$this->log->add( 'sermepa', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . $this->notify_url );
				
				$importe = $order->get_total();
				if ( $this->currency_id == 978 ) {
					$importe = $importe * 100;	// For Euros, last two digits are decimals
				}
			
				// Sermepa Args
				$sermepa_args = array(
					'Ds_Merchant_Amount'             => $importe,									// 12 / num
					'Ds_Merchant_Currency'           => $this->currency_id,							// 4 / num
					'Ds_Merchant_Order'              => str_pad($order_id, 4, '0', STR_PAD_LEFT),	// 12 / num{4}char{8}
					'Ds_Merchant_MerchantCode'       => $this->commerce_number,						// FUC code 9 / num
					'Ds_Merchant_Terminal'           => $this->terminal_number,						// 3 / num
					'Ds_Merchant_TransactionType'    => 0,											// Autorización
					'Ds_Merchant_Titular'            => $this->owner_name,							// Nombre y apellidos del titular 
					'Ds_Merchant_MerchantName'       => $this->commerce_name,						// Optional, commerce name
					'Ds_Merchant_MerchantURL'        => $this->notify_url,							// http://docs.woothemes.com/document/wc_api-the-woocommerce-api-callback/
					'Ds_Merchant_MerchantData'       => self::merchant_data,
					'Ds_Merchant_ProductDescription' => __('Online order', 'wc_sermepa_payment_gateway'),
					'Ds_Merchant_ConsumerLanguage'   => 0,											// Undefined
					'Ds_Merchant_UrlOK'              => $this->get_return_url($order),
					'Ds_Merchant_UrlKO'              => $order->get_cancel_order_url(),
					'Ds_Merchant_PayMethods'         => 'T',										// T = credit card, R = bank transfer, D = Domiciliacion				
				);
				
				$sermepa_args['Ds_Merchant_MerchantSignature'] = $this->get_sermepa_digest( $sermepa_args );
			
	/*
				// If prices include tax or have order discounts, send the whole order as a single item
				if ( get_option('woocommerce_prices_include_tax')=='yes' || $order->get_order_discount() > 0 ) :
		
					// Discount
					$sermepa_args['discount_amount_cart'] = $order->get_order_discount();
		
					// Don't pass items - sermepa borks tax due to prices including tax. Sermepa has no option for tax inclusive pricing sadly. Pass 1 item for the order items overall
					$item_names = array();
		
					if (sizeof($order->get_items())>0) : foreach ($order->get_items() as $item) :
						if ($item['qty']) $item_names[] = $item['name'] . ' x ' . $item['qty'];
					endforeach; endif;
		
					$sermepa_args['item_name_1'] 	= sprintf( __('Order %s' , 'wc_sermepa_payment_gateway'), $order->get_order_number() ) . " - " . implode(', ', $item_names);
					$sermepa_args['quantity_1'] 		= 1;
					$sermepa_args['amount_1'] 		= number_format($order->get_total() - $order->get_shipping() - $order->get_shipping_tax() + $order->get_order_discount(), 2, '.', '');
		
					// Shipping Cost
					// No longer using shipping_1 because
					//		a) sermepa ignore it if *any* shipping rules are within sermepa
					//		b) sermepa ignore anyhing over 5 digits, so 999.99 is the max
					// $sermepa_args['shipping_1']		= number_format( $order->get_shipping() + $order->get_shipping_tax() , 2, '.', '' );
		
					if ( ( $order->get_shipping() + $order->get_shipping_tax() ) > 0 ) :
						$sermepa_args['item_name_2'] = __( 'Shipping via', 'wc_sermepa_payment_gateway' ) . ' ' . ucwords( $order->shipping_method_title );
						$sermepa_args['quantity_2'] 	= '1';
						$sermepa_args['amount_2'] 	= number_format( $order->get_shipping() + $order->get_shipping_tax() , 2, '.', '' );
					endif;
		
				else :
		
					// Tax
					$sermepa_args['tax_cart'] = $order->get_total_tax();
		
					// Cart Contents
					$item_loop = 0;
					if (sizeof($order->get_items())>0) : foreach ($order->get_items() as $item) :
						if ($item['qty']) :
		
							$item_loop++;
		
							$product = $order->get_product_from_item($item);
		
							$item_name 	= $item['name'];
		
							$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );
							if ($meta = $item_meta->display( true, true )) :
								$item_name .= ' ('.$meta.')';
							endif;
		
							$sermepa_args['item_name_'.$item_loop] = $item_name;
							if ($product->get_sku()) $sermepa_args['item_number_'.$item_loop] = $product->get_sku();
							$sermepa_args['quantity_'.$item_loop] = $item['qty'];
							$sermepa_args['amount_'.$item_loop] = $order->get_item_total( $item, false );
		
						endif;
					endforeach; endif;
		
					// Shipping Cost item - sermepa only allows shipping per item, we want to send shipping for the order
					if ($order->get_shipping()>0) :
						$item_loop++;
						$sermepa_args['item_name_'.$item_loop] = __('Shipping via', 'wc_sermepa_payment_gateway') . ' ' . ucwords($order->shipping_method_title);
						$sermepa_args['quantity_'.$item_loop] = '1';
						$sermepa_args['amount_'.$item_loop] = number_format($order->get_shipping(), 2, '.', '');
					endif;
		
				endif;
	*/			
		
				$sermepa_args = apply_filters( 'woocommerce_sermepa_args', $sermepa_args );
		
				return $sermepa_args;
			}
			
			function get_sermepa_digest($sermepa_args) {			
				if ( $this->extended_sha1_algorithm == 'yes' ) {
					$string = "{$sermepa_args['Ds_Merchant_Amount']}{$sermepa_args['Ds_Merchant_Order']}{$sermepa_args['Ds_Merchant_MerchantCode']}{$sermepa_args['Ds_Merchant_Currency']}{$sermepa_args['Ds_Merchant_TransactionType']}{$sermepa_args['Ds_Merchant_MerchantURL']}{$this->secret_key}";
					$digest = sha1( $string );
				} else {
					$string = "{$sermepa_args['Ds_Merchant_Amount']}{$sermepa_args['Ds_Merchant_Order']}{$sermepa_args['Ds_Merchant_MerchantCode']}{$sermepa_args['Ds_Merchant_Currency']}{$this->secret_key}";
					$digest = sha1( $string );
				}
				
				if ( 'yes' == $this->debug )
					$this->log->add('sermepa', sprintf( __( 'Digest calculation for %s is %s.', 'wc_sermepa_payment_gateway' ), $string, $digest ) );
				
				return $digest;
			}
		
		
		    /**
			 * Generate the sermepa button link
		     *
		     * @access public
		     * @param mixed $order_id
		     * @return string
		     */
		    function generate_sermepa_form( $order_id ) {
				global $woocommerce;
		
				$order = new WC_Order( $order_id );
		
				if ( $this->testmode == 'yes' ):
					$sermepa_adr = $this->testurl . '?test=1&';
				else :
					$sermepa_adr = $this->liveurl . '?';
				endif;
		
				$sermepa_args = $this->get_sermepa_args( $order );
				
				if ( 'yes' == $this->debug )
					$this->log->add( 'sermepa', 'Sending data to Sermepa ' . print_r( $sermepa_args, true ));
		
				$sermepa_args_array = array();
		
				foreach ($sermepa_args as $key => $value) {
					$sermepa_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
				}
		
				$wc_enqueue_js('
					jQuery("body").block({
							message: "<img src=\"' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif' ) ) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Sermepa to make payment.', 'wc_sermepa_payment_gateway').'",
							overlayCSS:
							{
								background: "#fff",
								opacity: 0.6
							},
							css: {
						        padding:        20,
						        textAlign:      "center",
						        color:          "#555",
						        border:         "3px solid #aaa",
						        backgroundColor:"#fff",
						        cursor:         "wait",
						        lineHeight:		"32px"
						    }
						});
					setTimeout(function () { jQuery("#submit_sermepa_payment_form").click(); }, 5000);
				');
		
				return '<form action="'.esc_url( $sermepa_adr ).'" method="post" id="sermepa_payment_form" target="_top">
						' . implode('', $sermepa_args_array) . '
						<input type="submit" class="button-alt" id="submit_sermepa_payment_form" value="'.__('Pay via Sermepa', 'wc_sermepa_payment_gateway').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'wc_sermepa_payment_gateway').'</a>
					</form>';
		
			}
		
		
		    /**
		     * Process the payment and return the result
		     *
		     * @access public
		     * @param int $order_id
		     * @return array
		     */
			function process_payment( $order_id ) {
		
				$order = new WC_Order( $order_id );
		
				/*
				if ( ! $this->form_submission_method ) {
		
					$sermepa_args = $this->get_sermepa_args( $order );
		
					$sermepa_args = http_build_query( $sermepa_args, '', '&' );
		
					if ( $this->testmode == 'yes' ):
						$sermepa_adr = $this->testurl . '?test=1&';
					else :
						$sermepa_adr = $this->liveurl . '?';
					endif;
		
					return array(
						'result' 	=> 'success',
						'redirect'	=> $sermepa_adr . $sermepa_args
					);
		
				} else {
				*/
		
					return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
					);
		/*
				}
				*/
		
			}
		
		
		    /**
		     * Output for the order received page.
		     *
		     * @access public
		     * @return void
		     */
			function receipt_page( $order ) {
		
				echo '<p>'.__('Thank you for your order, please click the button below to pay with Sermepa.', 'wc_sermepa_payment_gateway').'</p>';
		
				echo $this->generate_sermepa_form( $order );
		
			}
		
			/**
			 * Check Sermepa notification
			 **/
			function check_notification_is_valid() {
				global $woocommerce;
		
				if ( 'yes' == $this->debug )
					$this->log->add( 'sermepa', 'Checking notification is valid...' );
		
		    	// Get received values from post data
				$received_values = (array) stripslashes_deep( $_POST );
			
		        if ( 'yes' == $this->debug )
		        	$this->log->add( 'sermepa', 'Received data: ' . print_r($received_values, true) );
		        
		        $string = "{$received_values['Ds_Amount']}{$received_values['Ds_Order']}{$received_values['Ds_MerchantCode']}{$received_values['Ds_Currency']}{$received_values['Ds_Response']}{$this->secret_key}";
		        $digest = sha1( $string ); 
		
		        // check to see if the response is valid
		        if ( strcasecmp ( $digest, $received_values['Ds_Signature'] ) == 0 ) {
		            if ( 'yes' == $this->debug )
		            	$this->log->add( 'sermepa', 'Received valid notification from Sermepa' );
		            return true;
		        }
		
		        if ( 'yes' == $this->debug )
		        	$this->log->add( 'sermepa', "Received invalid notification from Sermepa.\nString: {$string}\nDigest: {$digest}\nDs_Signature: {$received_values['Ds_Signature']}" );
		
		        return false;
		    }
		
		
			/**
			 * Check for Sermepa notification
			 *
			 * @access public
			 * @return void
			 */
			function check_notification() {			
		
				if (isset($_POST['Ds_MerchantData']) && $_POST['Ds_MerchantData'] == self::merchant_data):
		
					@ob_clean();
		
		        	$_POST = stripslashes_deep($_POST);
		        		
		        	if ($this->check_notification_is_valid()) :
		
		        		header('HTTP/1.1 200 OK');
		
		            	do_action("valid-sermepa-standard-notification", $_POST);
		
					else :
		
						wp_die("Sermepa notification Failure");
		
		       		endif;
		
		       	endif;
		
			}
		
		
			/**
			 * Successful Payment!
			 *
			 * @access public
			 * @param array $posted
			 * @return void
			 */
			function successful_request( $posted ) {
				global $woocommerce;
		
				// Ds_Order holds post ID
			    if ( !empty($posted['Ds_Order']) && !empty($posted['Ds_Response']) ) {
		
					$order_id = (int) $posted['Ds_Order'];
					$order = new WC_Order( $order_id );
		
			        if ( $order->id != $order_id ) {
			        	if ( 'yes' == $this->debug ) $this->log->add( 'sermepa', "Error: received order id {$order_id} does not match {$order->id}." );
			        	exit;
			        }
			        
			        if ( $this->currency_id != $posted['Ds_Currency'] ) {
			        	if ( 'yes' == $this->debug ) $this->log->add( 'sermepa', 'Error: Currency id does not match sent data.' );
			        	exit;
			        }
			        
			    	if ( $posted['Ds_TransactionType'] != 0 ) {
			        	if ( 'yes' == $this->debug ) $this->log->add( 'sermepa', 'Error: transaction type does not match.' );
			        	exit;
			        }
		
			        if ( 'yes' == $this->debug )
			        	$this->log->add( 'sermepa', 'Payment status: ' . $posted['Ds_Response'] );
				      
			        // We are here so lets check status and do actions
			        $response = (int) $posted['Ds_Response'];
			        if ( $response >= 0 && $response <= 99 ) {	// Completed
		
			            	// Check order not already completed
			            	if ($order->status == 'completed') :
			            		 if ( 'yes' == $this->debug ) $this->log->add( 'sermepa', 'Aborting, Order #' . $order_id . ' is already complete.' );
			            		 exit;
			            	endif;
			            	
							
			            	// Validate Amount
			            	$order_amount = $order->get_total(); 
			            	$received_amount = $posted['Ds_Amount'];
							if ( $this->currency_id == 978 ) {
								$received_amount = $received_amount / 100;	// For Euros, sermepa assumes that last two digits are decimals
							}
														
						    if ( $order_amount != $received_amount ) {
						    	
						    	if ( $this->debug == 'yes' ) 
						    		$this->log->add( 'sermepa', "Payment error: Order's ammount {$order_amount} do not match received amount {$received_amount}" );
						    
						    	// Put this order on-hold for manual checking
						    	$order->update_status( 'on-hold', sprintf( __( 'Validation error: Sermepa amounts do not match (amount %s).', 'wc_sermepa_payment_gateway' ), $posted['Ds_Amount'] ) );
						    	
						    	exit;
						    }
		
							 // Store payment Details
			                if ( ! empty( $posted['Ds_Date'] ) )
			                	update_post_meta( $order_id, 'Payment date', $posted['Ds_Date'] );
			                if ( ! empty( $posted['Ds_Hour'] ) )
			                	update_post_meta( $order_id, 'Payment hour', $posted['Ds_Hour'] );	
			                if ( ! empty( $posted['Ds_AuthorisationCode'] ) )
			                	update_post_meta( $order_id, 'Authorisation code', $posted['Ds_AuthorisationCode'] );
			                if ( ! empty( $posted['Ds_Card_Country'] ) )
			                	update_post_meta( $order_id, 'Card country', $posted['Ds_Card_Country'] );
			                if ( ! empty( $posted['last_name'] ) )
			                	update_post_meta( $order_id, 'Consumer language', $posted['Ds_ConsumerLanguage'] );
			                if ( ! empty( $posted['Ds_Card_Type'] ) )
			                	update_post_meta( $order_id, 'Card type', $posted['Ds_Card_Type'] == 'C' ? 'Credit' : 'Debit' );
		
			            	// Payment completed
			                $order->add_order_note( __('Sermepa payment completed', 'wc_sermepa_payment_gateway') );
			                $order->payment_complete();
		
			                if ( 'yes' == $this->debug )
			                	$this->log->add( 'sermepa', 'Payment complete.' );
		
			        } else if ( $response == 900 ) {
			        	// Transacción autorizada para devoluciones y confirmaciones
			        	/*
			        	 	// Only handle full refunds, not partial
			            	if ($order->get_total() == ($posted['mc_gross']*-1)) {
		
				            	// Mark order as refunded
				            	$order->update_status('refunded', sprintf(__('Payment %s via IPN.', 'wc_sermepa_payment_gateway'), strtolower($posted['payment_status']) ) );
		
				            	$mailer = $woocommerce->mailer();
		
				            	$mailer->wrap_message(
				            		__('Order refunded/reversed', 'wc_sermepa_payment_gateway'),
				            		sprintf(__('Order %s has been marked as refunded - Sermepa reason code: %s', 'wc_sermepa_payment_gateway'), $order->get_order_number(), $posted['reason_code'] )
								);
		
								$mailer->send( get_option('woocommerce_new_order_email_recipient'), sprintf( __('Payment for order %s refunded/reversed', 'wc_sermepa_payment_gateway'), $order->get_order_number() ), $message );
		
							}
			        	 */
			        } else if ( $response >= 101 && $response <= 202 ) {
						// Order failed
						$message = sprintf( __('Payment error: code: %s.', 'wc_sermepa_payment_gateway'), $response);
						$order->update_status('failed', $message );
						if ( $this->debug == 'yes' ) 
						    $this->log->add( 'sermepa', "{$message}" );		        	
			        } else if ( $response == 912 || $response == 9912 ) {
						// Order failed
						$message = sprintf( __('Payment error: bank unavailable.', 'wc_sermepa_payment_gateway' ) );
						$order->update_status('failed', $message );
						if ( $this->debug == 'yes' ) 
						    $this->log->add( 'sermepa', "{$message}" );
			        } else {
			        	// Order failed
			        	$message = sprintf( __('Payment error: code: %s.', 'wc_sermepa_payment_gateway'), $response );
						$order->update_status('failed', $message );
						if ( $this->debug == 'yes' ) 
						    $this->log->add( 'sermepa', "{$message}" );
			        }
			               
	/*
			            case "reversed" :
			            case "chargeback" :
		
			            	// Mark order as refunded
			            	$order->update_status('refunded', sprintf( __('Payment %s via IPN.', 'wc_sermepa_payment_gateway'), strtolower( $posted['payment_status'] ) ) );
		
			            	$mailer = $woocommerce->mailer();
		
			            	$mailer->wrap_message(
			            		__('Order refunded/reversed', 'wc_sermepa_payment_gateway'),
			            		sprintf(__('Order %s has been marked as refunded - Sermepa reason code: %s', 'wc_sermepa_payment_gateway'), $order->get_order_number(), $posted['reason_code'] )
							);
		
							$mailer->send( get_option('woocommerce_new_order_email_recipient'), sprintf( __('Payment for order %s refunded/reversed', 'wc_sermepa_payment_gateway'), $order->get_order_number() ), $message );
	*/						
		
		
					exit;
		
			    }
		
			}
		}
	    
	    /**
		 * Add the gateway to WooCommerce
		 *
		 * @access public
		 * @param array $methods
		 * @package
		 * @return array
		 */
		function add_sermepa_gateway( $methods ) {
			$methods[] = 'WC_Sermepa';
			return $methods;
		}
		
		add_filter('woocommerce_payment_gateways', 'add_sermepa_gateway' );
	}
}
