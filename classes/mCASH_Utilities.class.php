<?php
/*
  Description: Extends mCASH_WooCommerce. This class handles orders
  Author: Klapp Media AS
  License: The MIT License (MIT)
*/	

abstract class mCASH_Utilities extends mCASH_Gateway {

    /**
     * generate_key_pair function.
     * 
     * @access public
     * @return KV Array
     */
    public function generate_key_pair() {
        $res = openssl_pkey_new(array(
            "digest_alg" => "sha256",
            "private_key_bits" => 1024,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ));
        openssl_pkey_export($res, $privKey);
        $pubKey = openssl_pkey_get_details($res);
        return array(
            'pubKey'      => $pubKey['key'],
            'privKey'     => $privKey
        );
    }


    /**
     * order_description function.
     *
     * Creates a readable description of the curret order to send to the Merchant API
     * 
     * @access public
     * @param mixed $order
     * @return string
     */
    public static function order_description( $order ){
        $text = "";
        if (sizeof($order->get_items()) > 0 ) {
            foreach ( $order->get_items() as $item ) {
                $text = $text . $item['qty'] . "\t" . $item['name'] . "\t" . wc_format_decimal($item['line_subtotal'] + $item['line_subtotal_tax'], 2) . "\n";
            }
            $text .= "1" . "\t" . __( 'Shipping cost', 'mcash-woocommerce-plugin' ) . " \t" . wc_format_decimal( $order->get_total_shipping(), 2 ) . "\n";
        }
        return $text;
    }    	
	
    /**
     * get_url function.
     *
     * Returns the full current url
     * 
     * @access private
     * @return string
     */
    public static function get_url($use_forwarded_host=false){
	    $s = $_SERVER;
        $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true:false;
        $sp = strtolower($s['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
        $port = $s['SERVER_PORT'];
        $port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
        $host = ($use_forwarded_host && isset($s['HTTP_X_FORWARDED_HOST'])) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
        $host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
        return $protocol . '://' . $host . $s['REQUEST_URI'];	    
    }	    
	
} 	