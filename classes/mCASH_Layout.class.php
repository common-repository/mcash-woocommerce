<?php
/*
  Description: Extends mCASH_WooCommerce. This class handles frontend layout
  Author: Klapp Media AS
  License: The MIT License (MIT)
*/	

interface mCASH_Layout_Interface {
	
	// Adds purchase button on top of cart
	public function before_cart();
	
	// Adds purchase button next to the "add to cart"-button on product view
	public function after_add_to_cart_button();
	
}
	
class mCASH_Layout extends mCASH_Gateway implements mCASH_Layout_Interface {
	
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
     * before_cart function.
     *
     * Adds the mCASH Express option on top of the cart
     * 
     * @access public
     * @return void
     */
    public function before_cart(){
	    $gateway = new static();
	    if( $gateway->enabled == "no" ) return;
	    // Check if mCASH Express is enabled, if not, return
	    if( $gateway->express !== "yes" ) return;	 
	    if( $gateway->show_express_on == "bottom" ) return;      
	    echo "<a class=\"mcsh-express\" href=\"" . add_query_arg('wc-api', 'mcash_woocommerce_express') . "\">" . __('Buy now', 'mcash-woocommerce-plugin') . "<img src=\"" . MCASH_PLUGIN_URL . "images/mcashlogo.svg\" alt=\"mCASH logo\" /></a>";  
    }
    
    /**
     * before_checkout_button function.
     *
     * Adds the mCASH Express option before the proceed to checkout button in the cart
     * 
     * @access public
     * @return void
     */
    public function before_checkout_button(){
	    $gateway = new static();
	    if( $gateway->enabled == "no" ) return;
	    // Check if mCASH Express is enabled, if not, return
	    if( $gateway->express !== "yes" ) return;	 
	    if( $gateway->show_express_on == "top" ) return;   
	    echo "<a class=\"mcsh-express-checkout\" href=\"" . add_query_arg('wc-api', 'mcash_woocommerce_express') . "\">" . __('Buy now', 'mcash-woocommerce-plugin') . "<img src=\"" . MCASH_PLUGIN_URL . "images/mcashlogo.svg\" alt=\"mCASH logo\" /></a>";  
    }    
    
    /**
     * after_add_to_cart_button function.
     *
     * Adds the mCASH Direct button next to the "add to cart"-button
     * 
     * @access public
     * @return void
     */
    public function after_add_to_cart_button(){
	    $gateway = new static();
	    if( $gateway->enabled == "no" ) return;
	    // Check if mCASH Direct is enabled, if not, return
	    if( $gateway->direct !== "yes" ) return;
	  
	    echo "<button type=\"submit\" class=\"mcsh-direct\" name=\"mcash_direct\" value=\"mcash_direct\">" . __('Buy now', 'mcash-woocommerce-plugin') . "<img src=\"" . MCASH_PLUGIN_URL . "images/mcashlogo.svg\" alt=\"mCASH logo\" /></button>";
    }
	
}