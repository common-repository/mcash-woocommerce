<?php
	
/**
 * mCASH_Shipping class.
 * 
 * @extends WC_Shipping_Method
 */
class mCASH_Shipping extends WC_Shipping_Method {

	/**
	* Constructor for your shipping class
	*
	* @access public
	* @return void
	*/
	public function __construct() {
		$this->id                 = 'mcash_shipping';
		$this->title       = __( 'mCASH Shipping', 'mcash-woocommerce-plugin' );
		$this->method_description = __( 'This shipping method is used for transactions with mCASH Direct and mCASH Express', 'mcash-woocommerce-plugin' ); // 
		$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
		$this->init();
	}	
	
	/**
	 * Init your settings
	 *
	 * @access public
	 * @return void
	 */
	function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	
}

?>