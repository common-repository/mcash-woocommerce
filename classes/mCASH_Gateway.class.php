<?php
/*
  Description: Extends WooCommerce by Adding mCASH Gateway.
  Author: Klapp Media AS
  License: The MIT License (MIT)
*/


// Abort if the file is accesses directly
if (! defined('ABSPATH') ) {
    exit;
}

/**
 * mCASH_WooCommerce class.
 * 
 * @extends WC_Payment_Gateway
 */
class mCASH_Gateway extends WC_Payment_Gateway {
	
	protected static $allowedCurrencies = array( 'NOK' );
	
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	function __construct(){
		$this->id = "mcash2";
		$this->method_title = __('mCASH', 'mcash-woocommerce-plugin');
		$this->title = __('mCASH', 'mcash-woocommerce-plugin');
		
		$this->init();
		$this->method_description = sprintf( __('mCASH Payment Gateway for WooCommerce. %s', 'mcash-woocommerce-plugin'), $this->welcometext);
	}
	
	/**
	 * init function.
	 *
	 * Initiate basic settings for mCASH
	 * 
	 * @access private
	 * @return void
	 */
	private function init(){
		
		$this->welcometext = null;
		
		// Find the public key for the merchant (if none, handle this as a newly installed plugin)
		$plugin_settings = get_option($this->plugin_id . $this->id . '_settings');
		if( !array_key_exists('priv_key', $plugin_settings) || empty( $plugin_settings['priv_key'] ) ){
			$this->keyPair = mCASH_Utilities::generate_key_pair();
			$plugin_settings['priv_key'] = $this->keyPair['privKey'];
			$plugin_settings['pub_key'] = $this->keyPair['pubKey'];
			update_option($this->plugin_id . $this->id . '_settings', $plugin_settings);	
		} 
		
		// Basic setup
		$this->has_fields = false;
		$this->icon = "https://mca.sh/wp-content/themes/mcash/assets/images/logo_mcash.png";
		$this->supports = array(
			'products',
			'refunds'	
		);
		
		// Initiate settings page fields
		
		$this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }
        
        $this->init_form_fields();

          // If testmode is selected, set mCASH SDK to testmode
        if( $this->testmode == 'yes' ) {
	        $this->welcometext .= sprintf( __('%smCASH is now in Test Mode%s', 'mcash-woocommerce-plugin'), '<div class="inline error"><p><strong>', '</strong></p></div>' );
        }      
        // Check if a merchant id and merchant user id is set
        if( empty( $this->mid ) || empty( $this->sid ) ){
			$this->welcometext .= sprintf( __('%s <br /><strong>Your plugin still needs some configuration before it will be available in the frontend.</strong><br /><br />Before you can start using your gateway, you need to sign up for an account at http://mca.sh/.<br /><br /> After setting up your merchant account, you need to copy this key and paste it into your account settings page: <br /> <pre>%s</pre> When you have done this. Enter your Merchant ID and Merchant User ID below. These were given to you when you created your merchant account. %s', 'mcash-woocommerce-plugin'), '<div class="card">', $this->pub_key, '</div>' );
			$this->enabled = "no";
        }
        
        // Setting are now loaded. Lets initiate mCASH PHP SDK with them
        mCASH\mCASH::setApiLevel( 'KEY' );
        mCASH\mCASH::setApiSecret( $this->priv_key );
        mCASH\mCASH::setApiPublicKey( $this->pub_key );
        mCASH\mCASH::setMerchantId( $this->mid );
        mCASH\mCASH::setUserId( $this->sid );

        if( $this->testmode == "yes" ){
	        mCASH\mCASH::setTestEnvironment( true );
	        mCASH\mCASH::setTestToken( $this->testbed_token );	        
	        mCASH\mCASH::setApiSecret( $this->testbed_privkey );       
        }

   
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
        
        // Register a WC API action for Express Checkout
        add_action('woocommerce_api_mcash_woocommerce_express', array( 'mCASH_Orders', 'create_express_purchase' ));
        // Register a WC API action for direct payment
        add_action('woocommerce_api_mcash_woocommerce', array( 'mCASH_Callback', 'mcash_woocommerce_callback' ));
		// Handles click on complete order, redirecting the user to the payment gateway
		add_action('woocommerce_receipt_' . $this->id, array( $this, 'mcash_payment_portal' ));
		
	}
	
	/**
	 * admin_options function.
	 * 
	 * Disabled the plugin if currency is not within allowed currency array.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options() {
        if ( in_array(get_woocommerce_currency(), self::$allowedCurrencies ) ) {
            parent::admin_options();
        } else {
			$plugin_settings = get_option($this->plugin_id . $this->id . '_settings', null);
			$plugin_settings['enabled'] = "no";
			update_option($this->plugin_id . $this->id . '_settings', $plugin_settings);
			$notice = sprintf( __('%s Gateway Disabled. %s mCASH does not support your stores currency %s', 'mcash-woocommerce-plugin' ), '<div class="inline error"><p><strong>', '</strong>', '</p></div>' );    echo $notice;
        }
    }	
    
	/**
	 * is_available function.
	 * 
	 * Wether the plugin is enabled or not
	 *
	 * @access public
	 * @return boolean
	 */
	public function is_available(){
		$available = ( $this->enabled == "yes" ) ? true : false;
		return $available;
	}    
	
	/**
	 * init_form_fields function.
	 *
	 * Sets the setting fields for the gateway
	 * 
	 * @access public
	 * @return void
	 */
	public function init_form_fields(){
		$this->form_fields = array(
			'enabled' => array(
				'title' 	=> __('Enabled/Disabled', 'mcash-woocommerce-plugin'),
				'label'		=> __('Enable this payment gateway', 'mcash-woocommerce-plugin'),
				'type'		=> 'checkbox',
				'default'	=> 'no'
			),
			'title' => array(
				'title'			=> __('Title', 'mcash-woocommerce-plugin'),
				'type'        	=> 'text',
				'description' 	=> __( 'This controls the title which the user sees during checkout.', 'mcash-woocommerce-plugin' ),
				'default'     	=> __( 'mCASH', 'mcash-woocommerce-plugin' ),
				'desc_tip'    	=> true,
			),
			'description' => array(
				'title'			=> __('Description', 'mcash-woocommerce-plugin'),
				'type'        	=> 'text',
				'desc_tip'    	=> true,
				'description' 	=> __( 'This controls the description which the user sees during checkout.', 'mcash-woocommerce-plugin' ),
				'default'     	=> __( 'Pay via mCASH mobile app', 'mcash-woocommerce-plugin' )				
			),
			'api_details' => array(
				'title'       	=> __( 'API Credentials', 'mcash-woocommerce-plugin' ),
				'type'        	=> 'title',
				'description' 	=> sprintf( __( 'Enter your mCASH Merchant API credentials. You can find them here %shttps://my.mca.sh/mssp/%s', 'mcash-woocommerce-plugin' ), '<a href="https://my.mca.sh/mssp/">', '</a>' )
			),			
			'mid' => array(
				'title'			=> __('Merchant ID', 'mcash-woocommerce-plugin'),
				'type'			=> 'text',
				'description' 	=> __( 'The Merchant ID was assigned to you upon registering with mCASH.', 'mcash-woocommerce-plugin' ),
			),
			'sid' => array(
				'title'			=> __('Merchant user ID', 'mcash-woocommerce-plugin'),
				'type'			=> 'text',
				'description' 	=> __( 'The Merchant user ID was assigned to you upon registering with mCASH.')
			),
			'gateway_settings' => array(
				'title'       	=> __( 'Gateway settings', 'mcash-woocommerce-plugin' ),
				'type'        	=> 'title',
				'description' 	=> __( 'Configure the setting for mCASH Payment Gateway for you needs.', 'mcash-woocommerce-plugin' )
			), 
			'capture_on' => array(
				'title'		=> __('Capture payment on', 'mcash-woocommerce-plugin'),
				'type'		=> 'select',
				'options'	=> array(
					'completed' 	=> __('Completed', 'mcash-woocommerce-plugin'),
					'processing'   	=> __('Processing', 'mcash-woocommerce-plugin'), 
				),
				'desc_tip'	=> true,
				'description' => __('When changing the order status to the selected status, the payment will be captured', 'mcash-woocommerce-plugin'),
				'default'	=> 'completed'	
			),        
            'autocapture_virtual' => array(
	        	'title'			=> __('Virtual products', 'mcash-woocommerce-plugin'),
	        	'description' 	=> __('Automatically capture the payment if the order only consists of virtual products', 'mcash-woocommerce-plugin'),
	        	'label'			=> __('Automatically capture payments on virtual products', 'mcash-woocommerce-plugin'),
	        	'type'			=> 'checkbox',
	        	'desc_tip'		=> true,
	        	'default'		=> 'yes'  
            ),
            'autocapture' => array(
                'title'     	=> __('Automatically capture', 'mcash-woocommerce-plugin'),
                'label'    	 	=> __('Capture an authorized payment automatically', 'mcash-woocommerce-plugin'),
                'type'      	=> 'checkbox',
                'desc_tip'		=> true,
                'description' 	=> __('This will override the two previous option if enabled. This captures the payment immediately after the payment is authenticated by the user.', 'mcash-woocommerce-plugin'),
                'default'   	=> 'no',
            ),            
            'express' => array(
                'title'     => __('mCASH Express', 'mcash-woocommerce-plugin'),
                'label'     => __('Enable mCASH Express', 'mcash-woocommerce-plugin'),
                'type'      => 'checkbox',
                'desc_tip'		=> true,
                'description' => __('Enables mCASH Express which lets the user go directly to mCASH to complete his order.', 'mcash-woocommerce-plugin'),
                'default'   => 'yes',
            ), 
            'show_express_on' => array(
	            'title'		=> __('Show Express on', 'mcash-woocommerce-plugin'),
	            'label'		=> __('Choose where you want to display the mCASH Express button', 'mcash-woocommerce-plugin'),
	            'type'		=> 'select',
	            'options'	=> array(
		            'both' => __('Top and bottom', 'mcash-woocommerce-plugin'),
		            'top'  => __('Top only', 'mcash-woocommerce-plugin'),
		            'bottom' => __('Bottom only', 'mcash-woocommerce-plugin')
	            ),
	            'desc_tip'	=> true,
	            'description' => __('The value you choose here will determine where the mCASH Express button will be placed in the cart on your store', 'mcash-woocommerce-plugin'),
	            'default' 	=> 'both'
            ),
            'direct' => array(
                'title'     => __('mCASH Direct', 'mcash-woocommerce-plugin'),
                'label'     => __('Enable mCASH Direct', 'mcash-woocommerce-plugin'),
                'type'      => 'checkbox',
                'desc_tip'		=> true,
                'description' => __('Enables mCASH Direct which lets the user buy a product directly with mCASH', 'mcash-woocommerce-plugin'),
                'default'   => 'yes',
            ),   
			'shipping_settings' => array(
				'title'       	=> __( 'Shipping', 'mcash-woocommerce-plugin' ),
				'type'        	=> 'title',
				'description' 	=> __( 'When a customer buys a product with mCASH Direct or mCASH Express, he or she will not visist the checkout page where they can choose the wanted form of shipping for their product(s). Here you can define a fixed price, let it be free, or give free shipping on orders exceeding a price limit.', 'mcash-woocommerce-plugin' )
			),          
			'shipping_price'	=> array(
				'title'			=> __('Price', 'mcash-woocommerce-plugin'),
				'type'        	=> 'text',
				'desc_tip'    	=> true,
				'description' 	=> __( 'Set the fixed price customers that check out with mCASH Express and mCASH Direct will pay. Leave blank or 0 for free shipping', 'mcash-woocommerce-plugin' ),
				'default'     	=> __( '0', 'mcash-woocommerce-plugin' )				
			),
			'shipping_free_limit' => array(
				'title'			=> __('Free above', 'mcash-woocommerce-plugin'),
				'type'        	=> 'text',
				'desc_tip'    	=> true,
				'description' 	=> __( 'If a customer buys one or more items totalling above this price, the shipment will be free. Leave blank or 0 if you always want to charge for shipping', 'mcash-woocommerce-plugin' ),
				'default'     	=> __( '0', 'mcash-woocommerce-plugin' )					
			),
            'pub_key_text' => array(
                'title'     	=> __('Public RSA key', 'mcash-woocommerce-plugin'),
                'type'      	=> 'title',
                'description'  	=>  sprintf(__('Your public RSA key. Copy this to the corresponding field for your merchant user at %shttps://my.mca.sh/mssp/%s %s<pre>%s</pre>%s', 'mcash-woocommerce-plugin'),  '<a href="https://my.mca.sh/mssp/">', '</a>', '<div class="card">', $this->pub_key, '</div>'),
            ),				   
			'testmode_settings' => array(
				'title'       	=> __( 'Testmode', 'mcash-woocommerce-plugin' ),
				'type'        	=> 'title',
				'description' 	=> __( 'Here you can enable testmode. All transactions will be run against the test mCASH test environment. To do this, you need to obtain a testbed token first.', 'mcash-woocommerce-plugin' )
			),                 
            'testmode' => array(
                'title'     => __('Test Mode', 'mcash-woocommerce-plugin'),
                'label'     => __('Enable Test Mode', 'mcash-woocommerce-plugin'),
                'type'      => 'checkbox',
                'desc_tip'		=> true,
                'description' => __('If enabled, all transactions will be sent to the mCASH Test API.', 'mcash-woocommerce-plugin'),
                'default'   => 'no',
            ),
            'testbed_token' => array(
                'title'     => __('Test token', 'mcash-woocommerce-plugin'),
                'type'      => 'text',
                'description'  => sprintf(__('When using mCASH %stest environment%s , this token needs to be set', 'mcash-woocommerce-plugin'), '<a href="https://mcashtestbed.appspot.com/testbed/">', '</a>')
            ),     
            'testbed_privkey' => array(
                'title'     => __('Test Private Key', 'mcash-woocommerce-plugin'),
                'type'      => 'textarea',
                'description'  => sprintf(__('When using mCASH %stest environment%s, you need to set the Private key retrieved from the testbed.', 'mcash-woocommerce-plugin'), '<a href="https://mcashtestbed.appspot.com/testbed/">', '</a>'),
                'css'       => 'max-width:400px; height: 250px;'
            ),                                        
            'logging' => array(
                'title'     => __('Log Mode', 'mcash-woocommerce-plugin'),
                'label'     => __('Enable logging', 'mcash-woocommerce-plugin'),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),          
            'priv_key' => array(
	            'type' => 'hidden',
				'default' => $this->priv_key
            ),
            'pub_key' => array(
	            'type' => 'hidden',
				'default' => $this->pub_key	            
            )					
		);
	}

    /**
     * process_payment function.
     *
     * Creates an payment request to mCASH Merchant API. 
     * Returns an array with the uri for the payment gateway, or throws a WP_Error
     * 
     * @access public
     * @param mixed $order_id
     * @return array|WP_Error
     */
    public function process_payment( $order_id ){
	    
	    global $woocommerce;
        $order = new WC_Order($order_id);
	    
	    try {
		    $result = mCASH\PaymentRequest::create(array(
				'success_return_uri' => html_entity_decode($this->get_return_url($order)),
				'failure_return_uri' => html_entity_decode($order->get_cancel_order_url()),
				'allow_credit'		 => true,
				'pos_id'			 => $this->id,
				'pos_tid' 			 => strval($order->id),
				'action'			 => 'auth',
				'amount'			 => $order->order_total,
				'text'				 => mCASH_Utilities::order_description($order),
				'currency'			 => get_woocommerce_currency(),  
				'callback_uri'		 => add_query_arg('wc-api', 'mcash_woocommerce', home_url('/'))
		    ));		    
		    
		    if( isset( $result->uri ) ){
		        
		        // Set the transaction ID returned by mCASH
		        update_post_meta($order->id, '_mcash_tid', $result->id);

				// Reduce stock levels
				$order->reduce_order_stock();
		
				// Remove cart
				$woocommerce->cart->empty_cart();
				        
		        $result = array(
		            'result'    => 'success',
		            'redirect'  => $result->uri
		        );
		        
		        return $result;			    
		    }
		    
	    } catch( Exception $e ){
		    return new WP_Error( 'mcash_woocommerce', __('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience. Error: ' . $e->getMessage() ) );
	    }
	    
	    return false;
	        
    }
    
    /**
     * process_refund function.
     * 
     * @access public
     * @param mixed $order_id
     * @param mixed $amount (default: null)
     * @param string $reason (default: '')
     * @return void
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
	    
	    // Since WP deals with amount with comma separated values and the Merchant API with period, we need to replace any that exists in the $amount property
	    $amount = str_replace(",", ".", $amount);

		// Get the Order object
		$order = wc_get_order( $order_id );
		
		// Make sure that mCASH was the payment method for the order
        if (get_post_meta($order->id, '_payment_method', true) != $this->id ) {
	        return new \WP_Error( 'broke', __( 'Cannot make a refund with mCASH since its not the chosen payment method for this order', 'mcash-woocommerce-plugin' ) );
        }
		
		// We can only refund if there has been a capture on the transaction
        if ($order->get_transaction_id() == '') {
            return new \WP_Error( 'broke', __( 'To create a refund via mCASH, the order needs to be captured first.', 'mcash-woocommerce-plugin' ) );
        }

		try {
			// Every refund needs a refund ID. If non is set, create a new one with id 1
			$refund_meta_id = get_post_meta($order_id, '_refund_id', true);
			$refund_id = ( !$refund_meta_id ) ? 1 : $refund_meta_id+1;
			// Get the transaction ID for the current order
			$mcash_tid = get_post_meta($order_id, '_mcash_tid', true);
			// Retrieve the Payment Request object
			$payment_request = mCASH\PaymentRequest::retrieve( $mcash_tid );
			$payment_request->amount = $amount;
			$payment_request->refund_id = "$refund_id"; // Escaped since the api expects a string
			$payment_request->additional_amount = "0"; // Escaped since the api expects a string
			$payment_request->currency = 'NOK';
			$payment_request->text = $reason;
			// Try to refund the amount
			$result = $payment_request->refund();
			if( $result ){
				$order->add_order_note( __('mCASH Refund created. Refund ID ' . $refund_id . '. Value: ' . $amount, 'mcash-woocommerce-plugin'));
				update_post_meta($order_id, '_refund_id', $refund_id);
				return true;
			} else {
				$order->add_order_note( __('mCASH Refund failed.', 'mcash-woocommerce-plugin' ) );
				return new \WP_Error( 'broke', __( 'The refund failed due to a bad response from the mCASH API. Please try again.', 'mcash-woocommerce-plugin' ) );
			}
			
		} catch( Exception $e ){
			$order->add_order_note( __('mCASH Refund failed. Error: ' . $e->getMessage(), 'mcash-woocommerce-plugin' ) );
			return new \WP_Error( 'broke', __( 'The refund failed due to a bad connection to the API. Please try again.', 'mcash-woocommerce-plugin' ) );
		}   
		
		return false;
		
    }
        
    /**
     * process_admin_options function.
     *
     * Processes the options for the gateway
     * 
     * @access public
     * @return void
     */
    public function process_admin_options() {
        parent::process_admin_options();
        $settings = get_option($this->plugin_id . $this->id . '_settings', null);
        update_option($this->plugin_id . $this->id . '_settings', $settings);
    }  
 
    /**
     * log function.
     *
     * Hooks into the WC Logging function
     * 
     * @access public
     * @param mixed $message
     * @return void
     */
    public function log( $message ) {
        if ($this->logging == "yes") {
            if (empty( $this->log ) ) {
                $this->log = new WC_Logger();
            }
            $this->log->add($this->id, $message);
        }
    }      
	
}
	
?>