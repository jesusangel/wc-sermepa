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
 * Plugin Name: WooCommerce Redsys payment gateway
 * Plugin URI: http://tel.abloque.com/sermepa_woocommerce.html
 * Description: Redsys payment gateway for WooCommerce
 * Version: 1.2.10
 * Author: Jesús Ángel del Pozo Domínguez
 * Author URI: http://tel.abloque.com
 * License: GPL3
 *
 * Text Domain: wc_redsys_payment_gateway
 * Domain Path: /languages
 *
 */

	add_action('plugins_loaded', 'init_wc_myredsys_payment_gateway', 0);
		
	add_action( 'admin_notices', 'wc_myredsys_payment_gateway_admin_notice_mcrypt' );
	function wc_myredsys_payment_gateway_admin_notice_mcrypt() {
	
		if (! function_exists( 'mcrypt_encrypt' ) && version_compare(phpversion(), '7.1', '<') ) {
			$class = "error";
			$message = sprintf ( __ ( 'Mcrypt extension is missing. Please, ask your hosting provider to enable it.', 'wc_redsys_payment_gateway' ), '?ignore_redsys_sha256_notice=0' );
			echo "<div class=\"$class\"> <p>$message</p></div>";
		} else if ( !function_exists( 'openssl_encrypt' ) && version_compare(phpversion(), '7.1', '>=' ) ) {
			$class = "error";
			$message = sprintf ( __ ( 'php_openssl extension is missing. Please, ask your hosting provider to enable it.', 'wc_redsys_payment_gateway' ), '?ignore_redsys_sha256_notice=0' );
			echo "<div class=\"$class\"> <p>$message</p></div>";
        }
	}
	
	add_action( 'admin_notices', 'wc_myredsys_payment_gateway_admin_notice' );
	function wc_myredsys_payment_gateway_admin_notice() {
		global $current_user;
		$user_id = $current_user->ID;
		
		if (! get_user_meta ( $user_id, 'ignore_redsys_sha256_notice' )) {
			$class = "updated";
			$message = sprintf ( __ ( 'Please, get a new SHA256 key from your TPV and enter it in the plugin configuration. | <a href="%1$s">Hide Notice</a>', 'wc_redsys_payment_gateway' ), '?ignore_redsys_sha256_notice=0' );
			echo "<div class=\"$class\"> <p>$message</p></div>";
		}
	}
	
	add_action( 'admin_init', 'wc_myredsys_payment_gateway_ignore_notice' );
	function wc_myredsys_payment_gateway_ignore_notice() {
		global $current_user;
		$user_id = $current_user->ID;
		if (isset ( $_GET ['ignore_redsys_sha256_notice'] ) && '0' == $_GET ['ignore_redsys_sha256_notice']) {
			add_user_meta ( $user_id, 'ignore_redsys_sha256_notice', 'true', true );
		}
	}
	
	function init_wc_myredsys_payment_gateway() {
	 
	    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
	    
		/**
		 * Redsys Standard Payment Gateway
		 *
		 * Provides a Redsys Standard Payment Gateway.
		 *
		 * @class 		WC_MyRedsys
		 * @extends		WC_Payment_Gateway
		 * @version		1.0
		 * @package		
		 * @author 		Jesús Ángel del Pozo Domínguez
		 */
		   
		class WC_MyRedsys extends WC_Payment_Gateway {
			
		    /**
		     * Constructor for the gateway.
		     *
		     * @access public
		     * @return void
		     */
			public function __construct() {
				global $woocommerce;

				$this->currencies = array(
					'978' => 'EUR (Euro)', 
					'840' => 'USD (US Dollar)', 
					'826' => 'GBP (British Pound)', 
					'392' => 'JPY (Japanesse Yen)', 
					'170' => 'Peso Colombiano', 
					'32' => 'Peso Argentino',
					'124' => 'Dólar Canadiense', 
					'152' => 'Peso Chileno', 
					'356' => 'Rupia India', 
					'484' => 'Nuevo peso Mexicano', 
					'604' => 'Nuevos soles', 
					'756' => 'Franco Suizo', 
					'986' => 'Real Brasileño', 
					'937' => 'Bolívar fuerte', 
					'949' => 'Lira Turca'
				);
		
				$this->id			= 'myredsys';
				// Thank you @oscarestepa for this line
				$this->icon 		= apply_filters( 'wc_redsys_icon',  plugins_url('/assets/images/icons/redsys.png', __FILE__ ));
				$this->has_fields 	= false;
				$this->method_title     = __( 'Credit card (TPV Redsys)', 'wc_redsys_payment_gateway' );
				$this->method_description = __( 'Pay with credit card using Redsys TPV', 'wc_redsys_payment_gateway' );
	
		        // Set up localisation
	            $this->load_plugin_textdomain();
	                
				// Load the form fields.
				$this->init_form_fields();
		
				// Load the settings.
				$this->init_settings();
		
				// Define user set variables
				$this->title			= $this->settings['title'];
				$this->description		= $this->settings['description'];
				//$this->owner_name		= $this->settings['owner_name'];
				$this->commerce_name	= $this->settings['commerce_name'];
				$this->mode				= $this->settings['mode'];
				$this->protocol			= $this->settings['protocol'];
				$this->commerce_number	= $this->settings['commerce_number'];
				$this->terminal_number	= $this->settings['terminal_number'];
				$this->currency_id		= $this->settings['currency_id'];
				$this->secret_key		= $this->settings['secret_key'];
				$this->payment_method	= $this->settings['payment_method'];
				$this->language			= $this->settings['language'];
				$this->set_completed	= $this->settings['set_completed'];
				$this->debug			= $this->settings['debug'];

				switch ( $this->protocol ) {
					case 'HTTP':
						$this->notify_url   = str_ireplace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_MyRedsys', home_url( '/' ) ) );
					break;
					case 'HTTPS':
						$this->notify_url   = str_ireplace( 'http:', 'https:', add_query_arg( 'wc-api', 'WC_MyRedsys', home_url( '/' ) ) );
					break;
					default:
						$this->notify_url = add_query_arg( 'wc-api', 'WC_MyRedsys', home_url( '/' ) );
					break;
				}
				
		
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
				add_action( 'woocommerce_receipt_myredsys', array( $this, 'receipt_page' ) );
						
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
                load_textdomain( 'wc_redsys_payment_gateway', WP_LANG_DIR.'/wc_redsys_payment_gateway/wc_redsys_payment_gateway-'.$locale.'.mo' );
                load_plugin_textdomain( 'wc_redsys_payment_gateway', false, dirname( plugin_basename( __FILE__ ) ).'/languages/'.$variable_lang );
                load_plugin_textdomain( 'wc_redsys_payment_gateway', false, dirname( plugin_basename( __FILE__ ) ).'/languages' );
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
<h3><?php _e('Redsys', 'wc_redsys_payment_gateway'); ?></h3>
<p><?php _e('Redsys works by sending the user to Redsys to enter their payment information.', 'wc_redsys_payment_gateway'); ?></p>
<p><?php _e( 'You\'ll find previous version (SHA1) <a href="https://github.com/jesusangel/wc-sermepa/archive/88112586ed7b4a90fe55d20f70fcea169c046c0c.zip" target="_blank">here</a>', 'wc_redsys_payment_gateway' ); ?></p>

<?php if ( $this->is_valid_for_use() ) : ?>
<table class="form-table">
					<?php 
		    			// Generate the HTML For the settings form.
		    			$this->generate_settings_html();
		    		?>
		    		</table>
<!--/.form-table-->
<?php else : ?>
<div class="inline error">
	<p>
		<strong><?php _e( 'Gateway Disabled', 'wc_redsys_payment_gateway' ); ?></strong>: <?php _e( 'Redsys does not support your store currency.', 'wc_redsys_payment_gateway' ); ?></p>
</div>
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
						'title' => __( 'Enable/Disable', 'wc_redsys_payment_gateway' ),
						'type' => 'checkbox',
						'description' => __( 'Enable/Disable payment method.', 'wc_redsys_payment_gateway' ),
		    			'desc_tip'    => true,
						'label' => __( 'Enable Redsys', 'wc_redsys_payment_gateway' ),
						'default' => 'yes'
					),
	    			'mode' => array(
	    					'title' => __( 'Mode', 'wc_redsys_payment_gateway' ),
	    					'type' => 'select',
	    					'label' => __( 'Mode', 'wc_redsys_payment_gateway' ),
	    					'options'     => array(
	    							'P' => __( 'Production', 'wc_redsys_payment_gateway' ),
	    							'T' => __( 'Test sis-t', 'wc_redsys_payment_gateway' ),
	    							'D' => __( 'Test sis-d', 'wc_redsys_payment_gateway' ),
	    							'I' => __( 'Test sis-i', 'wc_redsys_payment_gateway' ),
	    							
	    					),
	    					'description' => __( 'TVP mode: production or test', 'wc_redsys_payment_gateway'),
	    					'desc_tip'    => true,
	    					'default'     => 'T'
	    			),
	    			'protocol' => array(
	    					'title' => __( 'Notifications protocol', 'wc_redsys_payment_gateway' ),
	    					'type' => 'select',
	    					'label' => __( 'Protocol', 'wc_redsys_payment_gateway' ),
	    					'options'     => array(
	    							'Auto' => __( 'Auto', 'wc_redsys_payment_gateway' ),
	    							'HTTP' => __( 'HTTP', 'wc_redsys_payment_gateway' ),
	    							'HTTPS' => __( 'HTTPS', 'wc_redsys_payment_gateway' )
	    			
	    					),
	    					'description' => __( 'Protocol to use by RedSYS notififications. HTTPS works only for sites with SSL and dedicated IP. Redsys doesn\'t work with SNI (22/05/2016)', 'wc_redsys_payment_gateway'),
	    					'desc_tip'    => true,
	    					'default'     => 'Auto'
	    			),
					'title' => array(
						'title' => __( 'Title', 'wc_redsys_payment_gateway' ),
						'type' => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'default' => __( 'Redsys', 'wc_redsys_payment_gateway' )
					),
	    			'description' => array(
    					'title' => __( 'Description', 'wc_redsys_payment_gateway' ),
    					'type' => 'textarea',
    					'description' => __( 'This controls the description which the user sees during checkout.', 'wc_redsys_payment_gateway' ),
	    				'desc_tip'    => true,
    					'default' => __( 'Payment gateway with Redsys credit card.', 'wc_redsys_payment_gateway' )
	    			),
	    			'payment_method' => array(
    					'title'       => __( 'Allowed payment methods', 'wc_redsys_payment_gateway' ),
    					'type'        => 'select',
    					'description' => __( 'Allowed payment methods.', 'wc_redsys_payment_gateway' ),
	    				'desc_tip'    => true,
    					'options'     => array(
    							' ' => __( 'All', 'wc_redsys_payment_gateway' ),
    							'C' => __( 'Only credit card', 'wc_redsys_payment_gateway' ),
    							'T' => __( 'Credit card and Iupay', 'wc_redsys_payment_gateway' )
    					),
	    				'default'     => 'T'
	    			),
/*
					'owner_name' => array(
						'title' => __( 'Owner name', 'wc_redsys_payment_gateway' ),
						'type' => 'text',
						'description' => __( 'Name and surname of the owner.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'default' => __( 'Redsys', 'wc_redsys_payment_gateway' )
					),
*/
					'commerce_name' => array(
						'title' => __( 'Commerce name', 'wc_redsys_payment_gateway' ),
						'type' => 'text',
						'description' => __( 'The commerce name.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'default' => __( 'Redsys', 'wc_redsys_payment_gateway' )
					),
					'commerce_number' => array(
						'title' => __( 'Commerce number (FUC)', 'wc_redsys_payment_gateway' ),
						'type' => 'text',
						'description' => __( 'Please enter your Redsys commerce number (FUC); this is needed in order to take payment.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'default' => ''
					),
					'terminal_number' => array(
						'title' => __( 'Terminal number', 'wc_redsys_payment_gateway' ),
						'type' => 'text',
						'description' => __( 'Please enter your Redsys terminal number; this is needed in order to take payment.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'default' => '001'
					),
					'currency_id' => array(
						'title' => __( 'Currency identifier', 'wc_redsys_payment_gateway' ),
						'type' => 'select',
						'description' => __( 'Please enter your Redsys currency identifier; this is needed in order to take payment.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'options' => $this->currencies,
						'default' => '978'
					),
					'secret_key' => array(
						'title' => __( 'Secret key (SHA-256)', 'wc_redsys_payment_gateway' ),
						'type' => 'text',
						'description' => __( 'Please enter your secret key; this is needed in order to take payment.', 'wc_redsys_payment_gateway' ),
						'default' => ''
					),
					'language' => array(
						'title' => __( 'Enable languages', 'wc_redsys_payment_gateway' ),
						'type' => 'checkbox',
						'description' => __( 'Shows TPV with customer\'s language.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'default' => 'no'
					),
                    'skip_checkout_form' => array(
                        'title' => __( 'Skip checkout form', 'wc_redsys_payment_gateway' ),
						'type' => 'checkbox',
						'description' => __( 'Skip the last form of the checkout process and redirect into the payment gateway (requires Javascript).', 'wc_redsys_payment_gateway' ),
						'default' => 'yes'
                    ),
	    			'set_completed' => array(
	    					'title'       => __( 'Set order as completed after payment?', 'wc_redsys_payment_gateway' ),
	    					'type'        => 'select',
	    					'description' => __( 'After payment, should the order be set as completed? Default is "processing".', 'wc_redsys_payment_gateway' ),
	    					'desc_tip'    => false,
	    					'options'     => array(
	    							'N' => __( 'No', 'wc_redsys_payment_gateway' ),
	    							'Y' => __( 'Yes', 'wc_redsys_payment_gateway' ),
	    					),
	    					'default'     => 'N'
	    			),
					'marketing' => array(
						'title' => __( 'Track marketing campaings', 'wc_redsys_payment_gateway' ),
						'type' => 'title',
						'description' => __( 'Add code for tracking marketing campaings. Currently only Facebook, ask for others (requires javascript)', 'wc_redsys_payment_gateway' )
					),
					'track_fbq_purchase' => array(
						'title' => __( 'Facebook', 'wc_redsys_payment_gateway' ),
						'label' => __( 'Track Facebook purchase event with Facebook\'s pixel', 'wc_redsys_payment_gateway' ),
						'type' => 'checkbox',
						'description' => __( 'Tracks purchase event with Facebook pixel.', 'wc_redsys_payment_gateway' ),
						'desc_tip'    => true,
						'default' => 'no'
					),
					'testing' => array(
						'title' => __( 'Gateway Testing', 'wc_redsys_payment_gateway' ),
						'type' => 'title',
						'description' => ''
					),
					'debug' => array(
						'title' => __( 'Debug Log', 'wc_redsys_payment_gateway' ),
						'type' => 'checkbox',
						'label' => __( 'Enable logging', 'wc_redsys_payment_gateway' ),
						'description' => sprintf( __( 'Log Redsys events, inside %s', 'wc_redsys_payment_gateway' ), wc_get_log_file_path( 'redsys' ) ),
						'default' => 'no'
					)
				);
		
		    }
		
		
			/**
			 * Get Redsys Args for passing to the TPV server
			 *
			 * @access public
			 * @param mixed $order
			 * @return array
			 */
			function get_redsys_args( $order ) {
				// FIX by SUGO
				$order_id = version_compare( WC_VERSION, '2.7', '<' ) ? $order->id : $order->get_id();
				$unique_order_id = str_pad( $order_id, 8, '0', STR_PAD_LEFT ) . date( 'is' );

				// Customize order code for TPV (@enbata)
				$unique_order_id = apply_filters( 'wc_myredsys_merchant_order_encode', $unique_order_id, $order_id );
		
				if ( 'yes' == $this->debug ) {
					$this->log->add( 'redsys', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . $this->notify_url );
				}
				
				$products = '';
				if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>' ) ) {
					if ( is_array( $cart_contents = WC()->cart->cart_contents ) ) {
						foreach ( $cart_contents as $cart_content ) {
							if ( !empty( $products ) ) {
								$separator = '/';
							} else {
								$separator = '';
							}
							$product_title = version_compare( WC_VERSION, '2.7', '<' ) ? $cart_content['data']->post->post_title : $cart_content['data']->get_title();
							$products .= $separator . $cart_content['quantity'] . 'x' . $product_title;
						}
					}
				} else {
					$products = __('Online order', 'wc_redsys_payment_gateway');
				}
				
				$importe = $order->get_total();
				if ( $this->currency_id == 978 ) {
					$importe = $importe * 100;	// For Euros, last two digits are decimals
				}
				
				// Language
				if( $this->language == 'no' ) {
					$language = '0';
				} else {
					$customer_language = substr( get_bloginfo("language"), 0, 2 );
					switch ( $customer_language ) {
						case 'es':
							$language = '001';
						break;
						case 'en':
							$language = '002';
						break;
						case 'ca':
							$language = '003';
						break;
						case 'fr':
							$language = '004';
						break;
						case 'de':
							$language = '005';
						break;
						case 'nl':
							$language = '006';
						break;
						case 'it':
							$language = '007';
						break;
						case 'sv':
							$language = '008';
						break;
						case 'pt':
							$language = '009';
						break;
						case 'pl':
							$language = '011';
						break;
						case 'gl':
							$language = '012';
						break;
						case 'eu':
							$language = '013';
						break;
						default:
							$language = '001';
					}
				}
			
				// TPV data
				$tpv_data = array(
					'DS_MERCHANT_AMOUNT'             => (string)$importe,					// 12 / num
					'DS_MERCHANT_ORDER'              => $unique_order_id,					// 12 / num{4}char{8}
					'DS_MERCHANT_MERCHANTCODE'       => $this->commerce_number,				// FUC code 9 / num
					'DS_MERCHANT_CURRENCY'           => $this->currency_id,					// 4 / num
					'DS_MERCHANT_TRANSACTIONTYPE'    => "0",								// Autorización
					'DS_MERCHANT_TERMINAL'           => $this->terminal_number,				// 3 / num
					'DS_MERCHANT_MERCHANTURL'        => $this->notify_url,					// http://docs.woothemes.com/document/wc_api-the-woocommerce-api-callback/
					'DS_MERCHANT_URLOK'              => $this->get_return_url($order),
					'DS_MERCHANT_URLKO'              => $order->get_cancel_order_url(),
					'Ds_Merchant_ConsumerLanguage'   => $language,
					'Ds_Merchant_ProductDescription' => $products,
					//'Ds_Merchant_Titular'            => $this->owner_name,					// Nombre y apellidos del titular
					'Ds_Merchant_MerchantData'       => sha1( $this->notify_url ),
					'Ds_Merchant_MerchantName'       => $this->commerce_name,				// Optional, commerce name
					'Ds_Merchant_PayMethods'         => $this->payment_method,				// T = credit card and iUpay, C = only credit card
					'Ds_Merchant_Module'             => 'woocommerce'				
				);
				
				$tpv_data_encoded = $this->encodeMerchantData( $tpv_data );
				$signature = $this->generateMerchantSignature( $this->secret_key, $tpv_data_encoded, $unique_order_id);
				
				$redsys_args = array(
						'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
						'Ds_MerchantParameters' => $tpv_data_encoded,
						'Ds_Signature' => $signature
				);
				
				$redsys_args = apply_filters( 'woocommerce_redsys_args', $redsys_args );
		
				return $redsys_args;
			}
		
		    /**
			 * Generate the redsys button link
		     *
		     * @access public
		     * @param mixed $order_id
		     * @return string
		     */
		    function generate_redsys_form( $order_id ) {
				global $woocommerce;
		
				$order = new WC_Order( $order_id );
		
				switch ( $this->mode ) {
					case 'T' :
						$redsys_addr = 'https://sis-t.redsys.es:25443/sis/realizarPago/utf-8';
					break;
					case 'D':
						$redsys_addr = 'http://sis-d.redsys.es/sis/realizarPago/utf-8';
					break;
					case 'I':
						$redsys_addr = 'https://sis-i.redsys.es:25443/sis/realizarPago/utf-8';
					break;
					case 'P':
					default:
						$redsys_addr = 'https://sis.redsys.es/sis/realizarPago/utf-8';
					break;	
				}
		
				try {
					$redsys_args = $this->get_redsys_args( $order );
				} catch ( Exception $e ) {
					if ( 'yes' == $this->debug ) {
						$this->log->add( 'redsys', 'Error generating payment form ' . $e->getMessage() );
					}
					return $e->getMessage();
				}
				
				if ( 'yes' == $this->debug ) {
					$this->log->add( 'redsys', 'Sending data to Redsys ' . print_r( $redsys_args, true ));
				}
		
				$redsys_fields_array = array();
		
				foreach ($redsys_args as $key => $value) {
					$redsys_fields_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
				}

				/*
				 * Track marketing campaings (Facebook pixel, etc.)
				 */
				if ( 'yes' == $this->settings['track_fbq_purchase'] ) {
					$script = '
						jQuery(document).ready(function() {
							jQuery("#redsys_payment_form").submit( function() {
								if ( typeof fbq ===  "function" ) {
									fbq("track", "Purchase", {value: "'.$order->get_total().'", currency: "'.$this->currencies[$this->currency_id].'"});
								}
							} );
						})
					';
					if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
						$woocommerce->add_inline_js( $script );
					} else {
						wc_enqueue_js( $script );
					}
				}
					
                if ( empty( $this->settings['skip_checkout_form'] ) || $this->settings['skip_checkout_form'] != 'no' ) {
                    
                    if ( version_compare( WOOCOMMERCE_VERSION, '2.2.3', '<' ) ) {
                        $loader_html = '<img src="' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif' ) ) . '" alt="' . __( 'Redirecting&hellip;', 'wc_redsys_payment_gateway') . '" style="float: left; margin-right: 10px;" />';
                    }
                    else {                    
                        $loader_html = '<div class="woocommerce" style="width: 2em; height: 2em; position: relative; float: left; margin-right: 15px;"><div class="loader"></div></div>';
                    }
				
                    $script = '
                        if (jQuery.fn.block) {
                            jQuery("body").block({
                                message: \'' . $loader_html . esc_js( __( 'Thank you for your order. You are being redirected to the payment gateway.', 'wc_redsys_payment_gateway' ) ) . '\',
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
                        }
                        jQuery(document).ready(function(){
                            jQuery("#redsys_payment_form").submit();
                        });
                    ';

                    if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
                        $woocommerce->add_inline_js( $script );
                    } else {
                        wc_enqueue_js( $script );
                    }
                
                }

		
				return '<form action="' . esc_url( $redsys_addr ) . '" method="post" id="redsys_payment_form" target="_top">
						' . implode( '', $redsys_fields_array ) . '
						<input type="submit" class="button button-alt" id="submit_redsys_payment_form" value="' . __('Pay via Redsys', 'wc_redsys_payment_gateway') . '" /> 
						<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'wc_redsys_payment_gateway' ) . '</a>
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

				if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
					$redirect_url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))));
				} else {
					$redirect_url = $order->get_checkout_payment_url( true );
				}
		
				return array(
					'result' 	=> 'success',
					'redirect'	=> $redirect_url
				);
			}
		
		    /**
		     * Output for the order received page.
		     *
		     * @access public
		     * @return void
		     */
			function receipt_page( $order ) {
		
				echo '<p>'.__('Thank you for your order, please click the button below to pay with Redsys.', 'wc_redsys_payment_gateway').'</p>';
		
				echo $this->generate_redsys_form( $order );
		
			}
		
			/**
			 * Check for Redsys notification
			 *
			 * @access public
			 * @return void
			 */
			function check_notification() {
				global $woocommerce;

				if ( 'yes' == $this->debug ) {
					$this->log->add( 'redsys', 'Checking notification is valid...' );
				}

				if ( !empty( $_REQUEST ) ) {
					if ( !empty( $_POST ) && array_key_exists( 'ds_signature', array_change_key_case( $_POST, CASE_LOWER ) ) ) {
		
						@ob_clean();
			
				    	// Get received values from post data
						$received_values = (array) stripslashes_deep( $_POST );
					
				        if ( 'yes' == $this->debug ) {
				        	$this->log->add( 'redsys', 'Received data: ' . print_r($received_values, true) );
				        }
				        
				        $received_signature	= $_POST['Ds_Signature'];
				        $version			= $_POST['Ds_SignatureVersion'];
				        $encoded_data		= $_POST['Ds_MerchantParameters'];
				        
				        $data = base64_decode( strtr( $encoded_data, '-_', '+/' ) );
				        $data = json_decode( $data, true);
				        
				        try {
				        	$calculated_signature = $this->generateResponseSignature( $this->secret_key, $encoded_data );
				        } catch ( Exception $e ) {
				        	if ( 'yes' == $this->debug ) {
				        		$this->log->add( 'redsys', 'Error while validating notification from Redsys: ' . $e->getMessage() );
				        	}
				        	wp_die();
				        }
				        
				        $received_amount	= $data['Ds_Amount'];
				        $order_id	= substr( $data['Ds_Order'], 0, 8 );
				        $fuc		= $data['Ds_MerchantCode'];
				        $currency	= $data['Ds_Currency'];
				        $response	= $data['Ds_Response'];
				        $auth_code	= $data['Ds_AuthorisationCode'];

				        // Reverse order code customization (@enbata)
				        $order_id = apply_filters( 'wc_myredsys_merchant_order_decode', $order_id, $data['Ds_Order'] );
				        
				        // check to see if the response is valid
				        if ( $received_signature === $calculated_signature
				        					&& $this->checkResponse( $response )
				        					&& $this->checkAmount( $received_amount )
				        					&& $this->checkOrderId( $order_id )
											&& $this->checkCurrency( $currency )
											&& $this->checkFuc( $fuc )
						) {
				            if ( 'yes' == $this->debug ) {
				            	$this->log->add( 'redsys', 'Received valid notification from Redsys. Payment status: ' . $response );
				            }

				            $order = new WC_Order( $order_id );
				      
					        // We are here so lets check status and do actions
					        $response = (int) $response;
					        if ( $response < 101 && $this->checkAuthorisationCode( $auth_code ) ) {	// Completed
				
					            	// Check order not already completed
							$order_status = method_exists($order, 'get_status' ) ? $order->get_status() : $order->status;
					            	if ( $order_status == 'completed' ) {
					            		 if ( 'yes' == $this->debug ) {
					            		 	$this->log->add( 'redsys', 'Aborting, Order #' . $order_id . ' is already complete.' );
					            		 }
					            		 wp_die();
					            	}
					            	
									
					            	// Validate Amount
					            	$order_amount = $order->get_total(); 
									if ( $this->currency_id == 978 ) {
										$received_amount = $received_amount / 100;	// For Euros, redsys assumes that last two digits are decimals
									}
																
								    if ( $order_amount != $received_amount ) {
								    	
								    	if ( $this->debug == 'yes' ) { 
								    		$this->log->add( 'redsys', "Payment error: Order's ammount {$order_amount} do not match received amount {$received_amount}" );
								    	}
								    
								    	// Put this order on-hold for manual checking
								    	$order->update_status( 'on-hold', sprintf( __( 'Validation error: Redsys amounts do not match (amount %s).', 'wc_redsys_payment_gateway' ), $received_amount ) );
								    	
								    	wp_die();
								    }
				
									 // Store payment Details
					                if ( ! empty( $data['Ds_Date'] ) )
					                	update_post_meta( $order_id, 'Payment date', $data['Ds_Date'] );
					                if ( ! empty( $data['Ds_Hour'] ) )
					                	update_post_meta( $order_id, 'Payment hour', $data['Ds_Hour'] );	
					                if ( ! empty( $data['Ds_AuthorisationCode'] ) )
					                	update_post_meta( $order_id, 'Authorisation code', $data['Ds_AuthorisationCode'] );
					                if ( ! empty( $data['Ds_Card_Country'] ) )
					                	update_post_meta( $order_id, 'Card country', $data['Ds_Card_Country'] );
					                if ( ! empty( $data['last_name'] ) )
					                	update_post_meta( $order_id, 'Consumer language', $data['Ds_ConsumerLanguage'] );
					                if ( ! empty( $data['Ds_Card_Type'] ) )
					                	update_post_meta( $order_id, 'Card type', $data['Ds_Card_Type'] == 'C' ? 'Credit' : 'Debit' );
				
					            	// Payment completed
					                $order->add_order_note( __('Redsys payment completed', 'wc_redsys_payment_gateway') );
					                $order->payment_complete();
					                
					                // Set order as completed if user did set up it
					                if ( 'Y' == $this->set_completed ) {
					                	$order->update_status( 'completed' );
					                }
				
					                if ( 'yes' == $this->debug ) {
					                	$this->log->add( 'redsys', 'Payment complete.' );
					                }
					        } else if ( $response >= 101 && $response <= 202 ) {
								// Order failed
								$message = sprintf( __('Payment error: code: %s.', 'wc_redsys_payment_gateway'), $response);
								$order->update_status('failed', $message );
								if ( $this->debug == 'yes' ) 
								    $this->log->add( 'redsys', "{$message}" );
							} else if ( $response == 900 ) {
								// Transacción autorizada para devoluciones y confirmaciones
								/*
								// Only handle full refunds, not partial
								if ($order->get_total() == ($posted['mc_gross']*-1)) {
							
									// Mark order as refunded
									$order->update_status('refunded', sprintf(__('Payment %s via IPN.', 'wc_redsys_payment_gateway'), strtolower($posted['payment_status']) ) );
							
									$mailer = $woocommerce->mailer();
							
									$mailer->wrap_message(
											__('Order refunded/reversed', 'wc_redsys_payment_gateway'),
											sprintf(__('Order %s has been marked as refunded - Redsys reason code: %s', 'wc_redsys_payment_gateway'), $order->get_order_number(), $posted['reason_code'] )
									);
							
									$mailer->send( get_option('woocommerce_new_order_email_recipient'), sprintf( __('Payment for order %s refunded/reversed', 'wc_redsys_payment_gateway'), $order->get_order_number() ), $message );
							
									}
									*/
					        } else if ( $response == 912 || $response == 9912 ) {
								// Order failed
								$message = sprintf( __('Payment error: bank unavailable.', 'wc_redsys_payment_gateway' ) );
								$order->update_status('failed', $message );
								if ( $this->debug == 'yes' ) 
								    $this->log->add( 'redsys', "{$message}" );
					        } else {
					        	// Order failed
					        	$message = sprintf( __('Payment error: code: %s.', 'wc_redsys_payment_gateway'), $response );
								$order->update_status('failed', $message );
								if ( $this->debug == 'yes' ) 
								    $this->log->add( 'redsys', "{$message}" );
					        }	
				        } else {
					        if ( 'yes' == $this->debug ) {
					        	$this->log->add( 'redsys', "Received invalid notification from Redsys.\nSignature: {$received_signature}\nVersion: {$version}\nData: " . print_r( $data, true ) );
					        }
					        
					        //$order->update_status('cancelled', __( 'Awaiting REDSYS payment', 'wc_redsys_payment_gateway' ));
					        
					        if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
					        	$woocommerce->cart->empty_cart();
					        } else {
					        	WC()->cart->empty_cart();
					        }
				        }
		
					}
				}
			}
			
			/**
			 * Converts array to JSON and encodes string to base64
			 *
			 * @param array $data Merchant data
			 * @return string B64(json($data))
			 */
			function encodeMerchantData( $data ) {	
				return base64_encode( json_encode( $data ) );
			}
			
			function generateMerchantSignature( $key, $b64_parameters, $order_id ) {
				$key = base64_decode( $key );
				$key = $this->encrypt_3DES( $order_id, $key );
				$mac256 = $this->mac256( $b64_parameters, $key );
				return base64_encode( $mac256 );
			}
			
			function generateResponseSignature( $key, $b64_data ) {
				$key = base64_decode( $key );
				$data_string = base64_decode( strtr( $b64_data, '-_', '+/' ) );
				$data = json_decode( $data_string, true);
				$key = $this->encrypt_3DES( $this->getOrderNotified( $data ), $key);
				$mac256 = $this->mac256( $b64_data, $key );
				return strtr( base64_encode( $mac256 ), '+/', '-_' );
			}
			
			function getOrderNotified( $data ) {
				$order_id = "";
				if( empty( $data['Ds_Order'] ) ) {
					$order_id = $data['DS_ORDER'];
				} else {
					$order_id = $data['Ds_Order'];
				}
				return $order_id;
			}
			
			function mac256( $b64_data, $key ){
				return hash_hmac('sha256', $b64_data, $key, true);
			}

            /**
             *
             * @link https://github.com/eusonlito/redsys-TPV/issues/14
             *
             * @param $message
             * @param $key
             * @return bool|null|string
             *
             *
             * @throws Exception
             */
			function encrypt_3DES( $message, $key ) {
                $ciphertext = null;

				if ( function_exists( 'mcrypt_encrypt' ) && version_compare(phpversion(), '7.1', '<')  ) {

                    $bytes = array(0,0,0,0,0,0,0,0); //byte [] IV = {0, 0, 0, 0, 0, 0, 0, 0}
                    $iv = implode(array_map("chr", $bytes));
					$ciphertext = mcrypt_encrypt(MCRYPT_3DES, $key, $message, MCRYPT_MODE_CBC, $iv);

				} else if ( function_exists( 'openssl_encrypt' ) && version_compare( phpversion(), '7.1', '>=' ) ) {

					$l = ceil(strlen($message) / 8) * 8;
					$ciphertext = substr(openssl_encrypt($message . str_repeat("\0", $l - strlen($message)), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"), 0, $l);

                } else if ( !function_exists( 'openssl_encrypt' ) && version_compare( phpversion(), '7.1', '>=' ) ) {

					throw new Exception( __( 'php_openssl extension is not available in this server', 'wc_redsys_payment_gateway' ) );
                } else if ( !function_exists( 'mcrypt_encrypt' ) && version_compare( phpversion(), '7.1', '<' ) ) {

					throw new Exception( __( 'Mcrypt extension is not available in this server', 'wc_redsys_payment_gateway' ) );
				}

				return $ciphertext;
			}
			
			function checkAmount( $amount ) {
				return preg_match( '/^\d+$/', $amount);
			}
			
			function checkOrderId( $order_id ) {
				return preg_match( '/^\d{1,12}$/', $order_id );
			}
			
			function checkFuc( $codigo ) {
				$retVal = preg_match('/^\d{2,9}$/', $codigo);
				if ( $retVal ) {
					$codigo = str_pad( $codigo, 9, '0', STR_PAD_LEFT);
					$fuc = intval( $codigo );
					$check = substr( $codigo, -1);
					$fucTemp = substr( $codigo, 0, -1);
					$acumulador = 0;
					$tempo = 0;
			
					for ( $i = strlen($fucTemp)-1; $i >= 0; $i-=2 ) {
						$temp = intval( substr( $fucTemp, $i, 1 ) ) * 2;
						$acumulador += intval( $temp / 10 ) + ( $temp % 10 );
						if ( $i > 0 ) {
							$acumulador += intval( substr( $fucTemp, $i-1, 1 ) );
						}
					}
					$ultimaCifra = $acumulador % 10;
					$resultado = 0;
					if($ultimaCifra != 0) {
						$resultado = 10 - $ultimaCifra;
					}
					$retVal = $resultado == $check;
				}
				return $retVal;
			}
			
			function checkCurrency( $currency ) {
				return preg_match("/^\d{1,3}$/", $currency);
			}
			
			function checkResponse( $response ) {
				return preg_match("/^\d{1,4}$/", $response);
			}
			
			function checkAuthorisationCode( $auth_code ) {
				return preg_match("/^\w{1,6}$/", $auth_code);
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
		function add_myredsys_gateway( $methods ) {
			$methods[] = 'WC_MyRedsys';
			return $methods;
		}
		
		add_filter('woocommerce_payment_gateways', 'add_myredsys_gateway' );
	}
