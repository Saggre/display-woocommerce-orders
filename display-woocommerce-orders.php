<?php
/**
 * Plugin Name: Display Woocommerce Orders
 * Description: Display a list of Woocommerce ordres
 * Version: 1.0.0
 * Author: Sakri Koskimies
 */
 
//TODO show by product category
function joinment_get_wc_products_array () {
	$category = get_category_by_slug( 'category-slug' );
	
	$db_products = get_posts( array( 
    'numberposts' => -1,
    'post_type' => 'product'
	));
		
	$return = array();

	foreach ( $db_products as $db_product ) {
		$product = wc_get_product($db_product->ID);
		$return[$db_product->ID]["name"] = $product->get_name();
		$return[$db_product->ID]["checkout_values"] = array();
	}
	
	return $return;
}

function joinment_get_wc_checkout_fields () {
	/*
    * First lets start the session. You cant use here WC_Session directly
    * because it's an abstract class. But you can use WC_Session_Handler which
    * extends WC_Session
    */
    WC()->session = new WC_Session_Handler;

    /*
    * Next lets create a customer so we can access checkout fields
    * If you will check a constructor for WC_Customer class you will see
    * that if you will not provide user to create customer it will use some
    * default one. Magic.
    */
    WC()->customer = new WC_Customer;

	return WC()->checkout->checkout_fields["order"];
}

// Create the shortcode
add_shortcode( 'display-woocommerce-orders', 'joinment_display_wc_shortcode' );
function joinment_display_wc_shortcode( $atts ) {
	
	//Default atts
	$a = shortcode_atts( array(
        'list-key' => 'name'
    ), $atts );
	
	// Replace space and comma with just comma
	$a['list-key'] = str_replace(', ', ',', $a['list-key']); 
		
	// If list-key is an array
	if(strpos($a['list-key'], ',') !== false){
		$a['list-key'] = explode(',', $a['list-key']);
	}else{
		// Make $a into an array anyway
		$a['list-key'] = array($a['list-key']);
	}
			
	// An array of products
	$products = joinment_get_wc_products_array();

	$db_orders = get_posts( array( 
		'numberposts'    => -1,
		'post_type' => 'shop_order',
		'post_status' => 'wc-completed'
	) );

	// Fill products array with sales of that product
	foreach ( $db_orders as $db_order ) {

		// Getting Order ID
		$order_id = $db_order->ID;

		// Getting an instance of the order object
		$order = wc_get_order( $order_id );
		
		//print_r($order);
		
		// For each item in order
		foreach ($order->get_items() as $item) {
			
			// Transfer order info to each item for listing (Only needed if form is in checkout and not in item)
			foreach($a['list-key'] as $key){
				$item[$key] = $order->get_meta($key);
			}
			
			// If products array has this product in it / Add to product's checkout_values array
			if(array_key_exists($item["product_id"], $products)){
				array_push($products[$item["product_id"]]["checkout_values"], $item);
			}
		}
	}
	
	// Get checkout fields
	$checkout_fields = joinment_get_wc_checkout_fields();
		
	// Loop products and their sales and echo HTML
	foreach ($products as $product) {
		echo '<h3>'.$product["name"].'</h3>';
		echo '<div>';	
		foreach ($product["checkout_values"] as $checkout_values) {
			echo '<ul>';
			// For all required field keys
			$list_index = 0;
			foreach($a['list-key'] as $key){
				// If there is text for this field
				if(strlen($checkout_values[$key]) > 0){
					echo ($list_index == 0 ? '<h4>' : '<li>')
					.(array_key_exists($key, $checkout_fields) ? $checkout_fields[$key]["label"].': ' : '').$checkout_values[$key]
					.($list_index == 0 ? '</h4>' : '</li>');
					
					$list_index++;
				}
			}
			echo '</ul>';
		}
		echo '</div>';
	}



}



