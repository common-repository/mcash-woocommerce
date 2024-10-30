<?php
/**
 * Plugin Name: WooCommerce - mCASH Gateway
 * Plugin URI: http://www.mcash.no
 * Description: Extends WooCommerce by Adding mCASH Gateway.
 * Version: 1.3.6
 * Author: Klapp Media AS
 * Author URI: http://www.klapp.no
 * License: The MIT License (MIT)
 * Text Domain: woocommerce-mcash-plugin
 * Domain Path: /languages
 */	

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	define('MCASH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	
	/*******************************************************
	* Register text domain
	*******************************************************/
	
	add_action( 'plugins_loaded', function(){
		load_plugin_textdomain('mcash-woocommerce-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages/' );
	});
	
	/*******************************************************
	* Register payment gateway with woocommerce
	*******************************************************/
	
	add_action('plugins_loaded', function(){
		
		// Include the mCASH PHP SDK and the WooCommerce Payment Gateway classes
		$includes = array(
			dirname(__FILE__) . '/mcash/mcash.php',
			dirname(__FILE__) . '/classes/mCASH_Gateway.class.php',
			dirname(__FILE__) . '/classes/mCASH_Shipping.class.php',
			dirname(__FILE__) . '/classes/mCASH_Utilities.class.php',
			dirname(__FILE__) . '/classes/mCASH_Callback.class.php',
			dirname(__FILE__) . '/classes/mCASH_Orders.class.php',
			dirname(__FILE__) . '/classes/mCASH_Layout.class.php',
			dirname(__FILE__) . '/patch.php'
		);
		
		function errorHandler($error_level, $error_message, $error_file, $error_line, $error_context){
			$err = new mCASH_Gateway();
			$err->log( 'An PHP error occured: - ' . $error_message . '. In file: ' . $error_file . '. On line: ' . $error_line );
		}
		
		set_error_handler("errorHandler");
		
		foreach( $includes AS $include ){
			require_once( $include );
		}	
		
		// Make sure that the PHP SDK is loaded
		if( !class_exists( 'mCASH\\mCASH' ) ) return;
		
		// Register the payment options with woocommerce
		add_filter('woocommerce_payment_gateways', function( $methods ){
			$methods[] = 'mCASH_Gateway';
			return $methods;
		});
		
		// Add actions to the order view
		add_action('woocommerce_order_actions', function( $actions ){
			$actions['mcash_capture'] = __('Manually capture mCASH payment', 'mcash-woocommerce-plugin');
			return $actions;
		});
		
		// This will run when the user clicks the manually capture in order view
		add_action('woocommerce_order_action_mcash_capture', function( $order ){
			return mCASH_Orders::capture( $order );
		});
		
		// Register the custom mCASH shipping method
		add_action( 'woocommerce_shipping_init', function( $methods ){
			$methods[] = "mCASH_Shipping";
			return $methods;
		});
		
		// Place buttons in the store
		add_action('woocommerce_before_cart', array( 'mCASH_Layout', 'before_cart' ));
		add_action('woocommerce_proceed_to_checkout', array( 'mCASH_Layout', 'before_checkout_button' ));
		add_action('woocommerce_after_add_to_cart_button', array( 'mCASH_Layout', 'after_add_to_cart_button' ) ); 		
		
		// This hooks on to the payment completed status and captures the payment
		add_action('woocommerce_order_status_completed', array( 'mCASH_Orders', 'order_status_completed' ) );	
		// This hooks on to the payment processing status and captures the payment
		add_action('woocommerce_order_status_processing', array( 'mCASH_Orders', 'order_status_processing' ) ); 	
		// This hooks on to the payment cancelled status and releases the payment
		add_action('woocommerce_order_status_cancelled', array( 'mCASH_Orders', 'order_status_cancelled' ) );
		
		// When adding an item to cart, check if it was added with the mCASH Direct button. In that case, we will run our own sequence	
		add_action('woocommerce_add_to_cart', function($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data){
			$express = filter_input(INPUT_POST, 'mcash_direct');
			if( $express ){
				mCASH_Orders::create_direct_purchase( $product_id, $quantity, $variation_id, $variation );
			}
		}, 10, 6);	
		
	}, 0);
	
	/*******************************************************
	* Add custom action links to the plugins page
	*******************************************************/
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), function( $links ){
	    $links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_mcash') . '">' . __('Settings', 'mcash-woocommerce-plugin') . '</a>';
		return $links;
	});
	
	// Enqueing scripts for frontend
	add_action('wp_enqueue_scripts', function(){
			wp_enqueue_style( 'mCash-buttons', plugins_url( '/css/btn.css' , __FILE__ ) );
	},100);
	
	// Enqueing scripts for the admin panel
	add_action('admin_enqueue_scripts', function(){
		wp_enqueue_script('mCash-settings', plugins_url( '/js/settings.js' , __FILE__ ), array( 'jquery' ));
	});	

	/*******************************************************
	* Reauthentication CronJob
	*******************************************************/	
	
	register_activation_hook(__FILE__, 'reauth_orders');
	
	function reauth_orders() {
	    if (! wp_next_scheduled ( 'reauthenticate_orders' )) {
			wp_schedule_event(time(), 'daily', 'reauthenticate_orders');
	    }
	}
	
	add_action('reauthenticate_orders', 'reauthMcash');
	
	function reauthMcash() {

		$Gateway = new mCASH_Gateway();
		$Gateway->log('Running reauthenticate cronjob');
		
		$args = array(
			'post_type' => 'shop_order',
			'post_status' => 'wc-processing',
			'meta_query' => array(
				array(
					'key'		=>	'_payment_status',
					'value' 	=> 	'auth',
					'compare'	=>	'='	
				),
				array(
					'key'     => '_payment_method',
					'value'   => $Gateway->id,
					'compare' => '='
				),
			),			
			'posts_per_page' => '-1'
		);
		
		$orders_query = new WP_Query($args);
		
		$customer_orders = $orders_query->posts;
		
		$Gateway->log(count($customer_orders) . ' Orders needs reauthenticating');

		foreach ($customer_orders AS $order_post) {

			$order = new WC_Order( $order_post->ID );
			
		    $mcash_tid = get_post_meta($order->id, '_mcash_tid', true);	
		    $Gateway->log('Trying to reauthenticate order ' . $mcash_tid);	
		    
		    try {
			    $payment_request = mCASH\PaymentRequest::retrieve($mcash_tid);
			   	$res = $payment_request->reauthorize();	 
		    } catch( \Exception $e ){
			    $Gateway->log('Reauth failed: ' . $e->getMessage()); 
		    }
		    		
		}	

	}	
	
	register_deactivation_hook(__FILE__, 'reauth_deactivate');
	
	function reauth_deactivate() {
		wp_clear_scheduled_hook('reauthenticate_orders');
	}	

}

?>
