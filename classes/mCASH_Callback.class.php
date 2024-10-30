<?php
/*
  Description: Extends WooCommerce by Adding mCASH Gateway.
  Author: Klapp Media AS
  License: The MIT License (MIT)
*/

interface mCASH_Callback_Interface {
	
	// Handles callbacks from mCASH. The function verifies the incoming call with the mCASH Pub keys
	public function mcash_woocommerce_callback();

}

class mCASH_Callback extends mCASH_Gateway implements mCASH_Callback_Interface {
	
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
     * mcash_woocommerce_callback function.
     *
     * This function is called from the mCASH mobile App when a user authenticates a transaction.
     * It updates the order information
     * 
     * @access public
     * @return void
     */
    public function mcash_woocommerce_callback() {
	    
	    $static = new static();
	    
       	// Get the input from mCASH
       	@ob_clean();
       	$body = file_get_contents('php://input');
       	$payload = json_decode( $body );
       	$static->log('mcash_woocommerce_callback() $body = ' . $body);
       	
	   	// Get the Transaction ID from the uri
	   	$parts = explode("/", $payload->meta->uri );
        $mcash_tid = $parts[count($parts)-3];
		
		// Malformed request. Missing ID
		if ( !$payload->meta->id ) {
			$static->log('Missing the ID');  
			header('HTTP/1.1 400 Bad Request');
			exit;
		}   
		
		// Validate the incoming request headers and payload
		$headers = mCASH\Utilities\Headers::request_headers();
		if( array_key_exists( 'Authorization', $headers ) ){
			$static->log( 'Authorization header exists' );
			$static->log( 'Validation of method: ' . $_SERVER['REQUEST_METHOD'] );
			$static->log( 'Validation of url: ' . mCASH_Utilities::get_url());
			$static->log( 'Validation of headers: ' . json_encode( $headers ) );
			$static->log( 'Validation of body: ' . $body );
			
			if( !mCASH\Utilities\Encryption::validateHeaders($_SERVER['REQUEST_METHOD'], mCASH_Utilities::get_url(), $headers, $body ) ){
				$static->log( 'Validation of incoming headers failed' );
				header('HTTP/1.1 401 Unauthorized');
				exit;
			} 
		}
		
		try {
			
			$static->log( 'Trying to update the payment for tid ' . $mcash_tid );
			
			// Get the payment request for the transaction
			$payment_request = mCASH\PaymentRequest::retrieve( $mcash_tid );	
			
			// Get the outcome of the transaction
			$outcome = $payment_request->outcome();
			
			$static->log( 'Outcome: ' . json_encode( $outcome ) );
			
			// Get the WC Order
			$order_id = $outcome->pos_tid;
			$order = wc_get_order( $order_id );
			
			update_post_meta($order_id, '_payment_status', $outcome->status);
			
			// Get the transaction type of the current order. If it is direct or express, we might have come here with shipping address, and therefor have to update the quote of the order and return it to mCASH
			$mcash_transaction_type = get_post_meta( $order_id, '_mcash_transaction_type', true );
			
			// If transaction type is direct or express, we need an shipping address from the user. Lets fetch that for the Merchant API
			if( $mcash_transaction_type == "direct" || $mcash_transaction_type == "express" ){
				$static->log( 'Direct or Express checkout' );
				// Check if address is already available
				$address_exists_in_scope = ( isset( $outcome->permissions['user_info']['shipping_address'] ) && !empty( $outcome->permissions['user_info']['shipping_address'] ) );
				// If the address is not yet available, we will try to refetch it every 1 seconds for 10 seconds until its there
				if( !$address_exists_in_scope ){
					
					for( $i=0; $i<=10; $i++ ){
						$static->log( 'Fetching address' );
						sleep(1); //Pause for a second
						// Get the outcome again
						$outcome = $payment_request->outcome();
						if( isset( $outcome->permissions['user_info']['shipping_address'] ) && !empty( $outcome->permissions['user_info']['shipping_address'] ) ){
							$address_exists_in_scope = true;
							break;
						}
					}					
				}
				// Now we should have the address. However, if we dont, respond with a 500 error and let the callback retry
				if( !$address_exists_in_scope ){
					$static->log( 'Quit because of missing address' );
					header( 'HTTP/1.1 500 Service Unavailable' );
					exit;					
				}
				// If we are still here, we have the address. Lets update the order with it
				// Since mCASH returns the full name as a string, we need to split it
				$static->log( 'Updating address now' );
				$name_parts = explode( " ", $outcome->permissions['user_info']['shipping_address']['name'] );
				$lastname = $name_parts[count($name_parts)-1];
				array_pop( $name_parts ); // Remove the last name from the array
				$firstname = implode( " ", $name_parts );
				
				// Create an address array to pass to the Order object
				$address = array(
					'first_name' => $firstname,
					'last_name' => $lastname,
					'company' => "",
					'address_1' => $outcome->permissions['user_info']['shipping_address']['street_address'],
					'address_2' => "",
					'city' => $outcome->permissions['user_info']['shipping_address']['locality'],
					'state' => "",
					'postcode' => $outcome->permissions['user_info']['shipping_address']['postal_code'],
					'country' => $outcome->permissions['user_info']['shipping_address']['country'],
					'email' => $outcome->permissions['user_info']['email'],
					'phone' => $outcome->permissions['user_info']['phone_number'],
				);

				// Now lets set the billing address
				$order->set_address( $address );
				// Set the shipping address
				$order->set_address( $address, 'shipping' );
				
				// Check if the email belongs to an existing customer and assign that customer to the order. If else. Create a new customer and assign
				$existing_user = get_user_by('email', $address['email']);
				
				if( !$existing_user ){
					$random_password = bin2hex(openssl_random_pseudo_bytes(4));
					$existing_user = wc_create_new_customer($address['email'], $address['email'], $random_password);
				}
				
				$order->customer_user = $existing_user->id;
				
				// Log
				$static->log( 'Address is set' );
				$static->log( json_encode( $address ) );
				
			}
			
			
			
			// The payment request have been authenticated with status "auth". The money still havent been captured
			if( $outcome->status == "auth" ){
				
		        // Mark as on-hold (we're awaiting the payment)
		        $order->update_status('processing', __( 'mCASH payment authenticated', 'mcash-woocommerce-plugin' ));
				
				// If autocapture is set to "yes", we will just run a capture on the order right now. 
				if( $static->autocapture == "yes" ){
					if( mCASH_Orders::capture( $order ) ) {
						$order->payment_complete( $mcash_tid );
					}
				} else {
					// If autocapture is set to "yes" for orders that only contains virtual objects
					// Check if it only consists virtual objects, if so, run a capture, and mark the payment as complete
					if( $static->autocapture_virtual == "yes" ){
						$consists_only_of_virtual_objects = true;
						// loop through all the items in the order, and check in any of them are non-virtual
			            if ( sizeof( $order->get_items() ) > 0 ) {
			                foreach ( $order->get_items() as $item ) {
			                    if ( $_product = $order->get_product_from_item( $item ) ) {
			                        $virtual_downloadable_item = $_product->is_downloadable() && $_product->is_virtual();
									if( !$virtual_downloadable_item ) $consists_only_of_virtual_objects = false;
			                    }
			                }
			            }
			            // If this is still true, the order only contains virtual objects, and we will just capture the payment request and complete the order
			            if( $consists_only_of_virtual_objects === true ){
							if( mCASH_Orders::capture( $order ) ) {
								$order->payment_complete( $mcash_tid );
							}				            
			            }							
					}
				
				}
				
			}
			
			// If the customer declined the order i mCASH, we will just cancel it
			if( $outcome->status == "fail" ){
				$order->update_status('cancelled', __('mCASH Order was aborted by customer', 'mcash-woocommerce-plugin' ) );
			}
			
            header('HTTP/1.1 204 No Content');
            exit;			
							
		} catch( Exception $e ){
			$static->log('Failed to fetch outcome ' . $e->getMessage() );  
			$static->log($e);  
			header('HTTP/1.1 503 Service Unavailable');
			exit;
		}
 
    }
	
}