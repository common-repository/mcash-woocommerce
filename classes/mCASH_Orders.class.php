<?php
/*
  Description: Extends mCASH_WooCommerce. This class handles orders
  Author: Klapp Media AS
  License: The MIT License (MIT)
*/	

interface mCASH_Orders_Interface {
	
	// Hooks on to updates of order statuses and releases authenticated transactions on cancelled orders
	public function order_status_cancelled( $order_id );
	
	// Hooks on to updates of orders and captures payments if capture is set to happend on processing-status
	public function order_status_processing( $order_id );
	
	// Hooks on to updates of orders and captures payments if capture is set to happend on completed-status
	public function order_status_completed( $order_id );
	
	// Creates a payment request to mCASH with the current products in the cart. Redirects user to mCASH portal if successfull
	public function create_express_purchase();
	
	// Creates a payment request to mCASH for a specific product. Redirects the user to mCASH portal if successfull
	public static function create_direct_purchase( $product_id, $quantity, $variation_id, $variation );
	
	// Releases an authenticated payment 
	public static function release( $order );
	
	// Captures an authenticated payment
	public static function capture( $order );
	
}
	
class mCASH_Orders extends mCASH_Gateway implements mCASH_Orders_Interface {
	
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	function __construct(){
		parent::__construct();
	}
	
    /**
     * order_status_cancelled function.
     *
     * Hooks on to updates of order, where the new status in "cancelled". 
     * Checks if a mCASH payment have been captured and refunds the amount, or releases it if no capture have been made
     * 
     * @access public
     * @param mixed $order_id
     * @return void
     */
    public function order_status_cancelled( $order_id ){
	    // TODO: Check if the payment have already been captured. In that case, do a refund, if not, release
		$order = wc_get_order( $order_id );
		self::release( $order );	    
    }
    
    /**
     * order_status_processing function.
     *
     * Hooks on to updates of order, where the new status is "processing".
     * If payments are set to be captured on change to this status, we run a capture on the payment request
     * 
     * @access public
     * @param mixed $order_id
     * @return void
     */
    public function order_status_processing( $order_id ){
	    $gateway = new static();
	    $gateway->log( 'Checking if payment should be captured on processing' );
	    // Check if the option for capture is set to status processing. If not, abort.
	    if( $gateway->capture_on != "processing" ) return;
		$order = wc_get_order( $order_id );
		$gateway->log( 'Capturing payment on processing' );
		self::capture( $order );	    
    }
    
    /**
     * order_status_completed function.
     *
     * Hooks on to updates of order, where the new status is "completed".
     * If payments are set to be captured on change to this status, we run a capture on the payment request     
     * 
     * @access public
     * @param mixed $order_id
     * @return void
     */
    public function order_status_completed( $order_id ){
	    $gateway = new static();
	    $gateway->log( 'Checking if payment should be captured on completed order' );
	    // Check if the option for capture is set to status completed. If not, abort.
	    if( $gateway->capture_on != "completed" ) return;
		$order = wc_get_order( $order_id );
		$gateway->log( 'Capturing payment on completion of order' );
		self::capture( $order );	    
    }
    
    /**
     * get_shipping_price function.
     * 
     * Calculcates the shipping price based on the gateway settings and returns an integer with the cost
     *
     * @access private
     * @param integer $order_total
     * @return void
     */
    private static function get_shipping_price( $order_total ){
	    
	    $static = new static();
	    
	    $static->log( 'Calculating shipping price for order' );
	    
	    if( empty ( $static->shipping_price ) ) return 0;
	    
	    if( !empty( $static->shipping_free_limit ) ){
		    if( $order_total > $static->shipping_free_limit ) {
			    return 0;
		    } else {
			    return $static->shipping_price;
		    }
	    } 
	    
	    return $static->shipping_price;
	    
    }
    
    /**
     * create_express_purchase function.
     * 
     * @access public
     * @return void
     */
    public function create_express_purchase(){
	    
	    $static = new static();
	    
	    $static->log( 'Creating an mCASH Express checkout' );
	    
	    global $woocommerce;
	    
	    // Define checkout since we are running the process now
	    if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) define( 'WOOCOMMERCE_CHECKOUT', true );
	    WC()->cart->calculate_totals();
	   
	    $order_id = WC()->checkout()->create_order();
	    $order = wc_get_order( $order_id );
	    
	    $order->calculate_totals();
	    
	    $shipping_rate = new WC_Shipping_Rate( 'mcash_shipping', __( 'mCASH Fraktkostnad', 'mcash-woocommerce-plugin' ), self::get_shipping_price( $order->get_total() ), array(0), 'mcash_shipping');
	    $shipping_id = $order->add_shipping( $shipping_rate );

        update_post_meta($order_id, '_payment_method',   $static->id);
        update_post_meta($order_id, '_payment_method_title',  $static->title);	
		
		$order->calculate_totals();
        
        try {
			
			$static->log( 'Trying to create a payment request in the mCASH API' );
			
		    $result = mCASH\PaymentRequest::create(array(
				'success_return_uri' => html_entity_decode($static->get_return_url($order)),
				'failure_return_uri' => html_entity_decode($order->get_cancel_order_url()),
				'allow_credit'		 => true,
				'pos_id'			 => $static->id,
				'pos_tid' 			 => strval($order->id),
				'action'			 => 'auth',
				'amount'			 => $order->order_total,
				'text'				 => mCASH_Utilities::order_description($order),
				'currency'			 => get_woocommerce_currency(),  
				'callback_uri'		 => add_query_arg('wc-api', 'mcash_woocommerce', home_url('/')),
				'required_scope'	 => 'openid phone email shipping_address'
		    ));		    
		    
		    if( isset( $result->uri ) ){
			    
			    $static->log( 'Redirecting customer to the mCASH Payment portal' );
		        
		        // Set the transaction ID returned by mCASH
		        update_post_meta($order->id, '_mcash_tid', $result->id);
		        
		        // Set the mCASH transaction type to direct
		        update_post_meta( $order->id, '_mcash_transaction_type', 'express' );

				// Reduce stock levels
				$order->reduce_order_stock();
		
				// Remove cart
				$woocommerce->cart->empty_cart();
				
				wp_redirect( $result->uri, 302 );
				exit; // The action ends here, now we go over to mCASH to complete the order
						    
		    }
		    
		    $static->log( 'Redirection of the customer to the mCASH Payment Portal failed' );
	        
        } catch( Exception $e ){
	        $static->log( 'Failure while trying to create a payment request to the mCASH API. Error: ' . $e->getMessage() );
	        $order->update_status('failed', __( 'mCASH Direct transaction failed. Error: ' . $e->getMessage() ) );
	        wp_redirect(get_permalink(wc_get_page_id('cart')));
	        exit;	
        }
         
    }
    
    /**
     * create_direct_purchase function.
     * 
     * @access public
     * @static
     * @param mixed $product_id
     * @param mixed $quantity
     * @param mixed $variation_id
     * @param mixed $variation
     * @return void
     */
    public static function create_direct_purchase( $product_id, $quantity, $variation_id, $variation ){
	   
	    global $woocommerce;
	    
	    $gateway = new static();
	   
		$gateway->log( 'Creating a mCASH Direct purchase' );
	   
	    // Create a new order
	    $order = wc_create_order();
	    $order->update_status('pending', __( 'mCASH Direct purchase. Pending payment.', 'mcash-woocommerce-plugin' ));
	    
	    // Get the product from the product id
	    $product = new WC_Product( $product_id );
	    
	    // Add the selected product and quantity to the order
	    $order->add_product( $product, $quantity );
	    
	    // Set mCASH as the payment method
	    $order->set_payment_method( $gateway );
   
	    // Calculate the prices
	    $order->calculate_totals();	
		
	    $shipping_rate = new WC_Shipping_Rate( 'mcash_shipping', __( 'mCASH Fraktkostnad', 'mcash-woocommerce-plugin' ), self::get_shipping_price( $order->get_total() ), array(0), 'mcash_shipping');
	    $shipping_id = $order->add_shipping( $shipping_rate );
	    $order->calculate_totals();	    
	    
	    try {
		    
		    $gateway->log( 'Trying to create a payment request to the mCASH API' );
		    
		    $result = mCASH\PaymentRequest::create(array(
				'success_return_uri' => html_entity_decode($gateway->get_return_url($order)),
				'failure_return_uri' => html_entity_decode($order->get_cancel_order_url()),
				'allow_credit'		 => true,
				'pos_id'			 => $gateway->id,
				'pos_tid' 			 => strval($order->id),
				'action'			 => 'auth',
				'amount'			 => $order->order_total,
				'text'				 => mCASH_Utilities::order_description($order),
				'currency'			 => get_woocommerce_currency(),  
				'callback_uri'		 => add_query_arg('wc-api', 'mcash_woocommerce', home_url('/')),
				'required_scope'	 => 'openid phone email shipping_address'
		    ));		    
		    
		    if( isset( $result->uri ) ){
			    
			    $gateway->log( 'Redirecting the customer to the mCASH Payment Portal' );
		        
		        // Set the transaction ID returned by mCASH
		        update_post_meta($order->id, '_mcash_tid', $result->id);
		        
		        // Set the mCASH transaction type to direct
		        update_post_meta( $order->id, '_mcash_transaction_type', 'direct' );

				// Reduce stock levels
				$order->reduce_order_stock();
		
				// Remove cart
				$woocommerce->cart->empty_cart();
				
				wp_redirect( $result->uri, 302 );
				exit; // The action ends here, now we go over to mCASH to complete the order
						    
		    }
		    
			$gateway->log( 'Redirecting the customer to the mCASH Payment Portal failed' );
		    
	    } catch( Exception $e ){
		    $gateway->log( 'Failure while trying to create a payment request to the mCASH API. Error: ' . $e->getMessage() );
		    $order->update_status('failed', __( 'mCASH Direct transaction failed. Error: ' . $e->getMessage() ) );
	        wp_redirect(get_permalink(wc_get_page_id('cart')));
	        exit;		    
	    }	    
	    
    }  
    
    /**
     * release function.
     * 
     * @access public
     * @static
     * @param mixed $order
     * @return boolean
     */
    public static function release( $order ){
	    
	    $static = new static();
	    
	    $static->log( 'Releasing authorized payment for order ' . $order->id );
	    
        if (get_post_meta($order->id, '_payment_method', true) != $static->id ) {
            return;
        }

	    $mcash_tid = get_post_meta($order->id, '_mcash_tid', true);
	    $static->log( 'Releasing ' . $mcash_tid );
	    try {
		    $static->log( 'Sending release call to the mCASH API' );
		    $payment_request = mCASH\PaymentRequest::retrieve($mcash_tid);
		    $result = $payment_request->release();
		    if( $result ){
			     $order->add_order_note( __('mCASH Payment released', 'mcash-woocommerce-plugin'));
			     update_post_meta($payment_request->pos_tid, '_transaction_id', $mcash_tid );
				 update_post_meta($payment_request->pos_tid, '_paid_date', current_time('mysql') );
			     return true;
			}
		    else {
			    $order->add_order_note(__('mCASH manual release failed', 'mcash-woocommerce-plugin'));
			    return false;
			}
	    } catch( Exception $e ){
		    $static->log( 'Failure while trying to release the payment in the mCASH API. Error: ' . $e->getMessage() );
		    $order->add_order_note( __('mCASH Payment release failed: ' . $e->getMessage(), 'mcash-woocommerce-plugin'));
		    return false;
	    }
    }    
    
   /**
     * capture function.
     * 
     * @access public
     * @static
     * @param mixed $order
     * @return boolean
     */
    public static function capture( $order ){

	    $static = new static();
	    
	    $static->log( 'Capturing payment for order ' . $order->id );
	    
        if (get_post_meta($order->id, '_payment_method', true) != $static->id ) {
            return;
        }

        if ($order->get_transaction_id() != '') {
            return;
        }
        
        if( get_post_meta($order->id, '_payment_status', 'false') === "ok" ){
	        return;
        }

	    $mcash_tid = get_post_meta($order->id, '_mcash_tid', true);
	    try {
		    
		    $static->log( 'Trying to capture the order in the mCASH API' );
		    
		    $payment_request = mCASH\PaymentRequest::retrieve($mcash_tid);
		    $result = $payment_request->capture();
		    if( $result ){
				 $static->log( 'Payment is captured' );
			     $order->add_order_note( __('mCASH Payment captured', 'mcash-woocommerce-plugin'));
			     update_post_meta($payment_request->pos_tid, '_transaction_id', $mcash_tid );
				 update_post_meta($payment_request->pos_tid, '_paid_date', current_time('mysql') );
				 update_post_meta($payment_request->pos_tid, '_payment_status', $payment_request->outcome()->status);
			     return true;
			}
		    else {
			    $static->log( 'Capturing of payment faield' );
			    $order->add_order_note(__('mCASH manual capture failed', 'mcash-woocommerce-plugin'));
			    return false;
			}
	    } catch( Exception $e ){
		    $static->log( 'Failure while trying to capture the payment in the mCASH API. Error: ' . $e->getMessage() );
		    $order->add_order_note( __('mCASH Payment capture failed: ' . $e->getMessage(), 'mcash-woocommerce-plugin'));
		    return false;
	    }
	    
    }      
	
}