<?php
	
/** ------------------------------------------------------------------------- *
 * 
 * patch.php
 *
 * Applies any patches needed upon updates of plugin
 * 
 * -------------------------------------------------------------------------- */
 
$patch_version = 1;

$current_patch_version = get_option('mcash_patch_version');

if ($patch_version > $current_patch_version) {
	
	/*
	* Patching type error in meta key from "_ payment_status" to "_payment_status"
	*/
	$args = array(
		'post_type' => 'shop_order',
		'post_status' => 'wc-processing',
		'meta_query' => array(
			array(
				'key'		=>	'_ payment_status',
				'value' 	=> 	'auth',
				'compare'	=>	'='	
			)
		),			
		'posts_per_page' => '-1'
	);
	
	$orders_query = new WP_Query($args);
	$customer_orders = $orders_query->posts;

	foreach ($customer_orders AS $order_post) {	
		
		$current_status = get_post_meta($order_post->ID, '_ payment_status', true);
		update_post_meta($order_post->ID, '_payment_status', $current_status);
		
	}	
	
	update_option('mcash_patch_version', $patch_version);
	
}
