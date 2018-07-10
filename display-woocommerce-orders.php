<?php

/**
 * Plugin Name: Display WooCommerce Orders
 * Description: Display a list of WooCommerce ordres
 * Version: 0.0.1
 * Author: Sakri Koskimies
 */
add_action('wp_enqueue_scripts', 'joinment_display_wc_scripts');

function joinment_display_wc_scripts() {
    wp_register_style('display-woocommerce-orders', plugins_url('/style/display-woocommerce-orders.css', __FILE__));
    wp_enqueue_style('display-woocommerce-orders');
}

/**
 * Get each WooCommerce product
 * @return array
 */
function joinment_get_wc_products_array() {
    $db_products = get_posts(array(
        'numberposts' => -1,
        'post_type' => 'product'
    ));

    $return = array();

    foreach ($db_products as $db_product) {
        $product = wc_get_product($db_product->ID);
        $return[$db_product->ID]["name"] = $product->get_name();
        $return[$db_product->ID]["checkout_values"] = array();
    }

    return $return;
}

/**
 * Get "order" WooCommerce checkout fields
 * @return type
 */
function joinment_get_wc_checkout_fields($checkout_fields) {

    //Empty the cart to trigger empty cart condition
    WC()->cart->empty_cart();

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

    //Used to find checkout fields
    //print_r(WC()->checkout->checkout_fields);

    return WC()->checkout->checkout_fields[$checkout_fields];
}

// Create the shortcode
add_shortcode('display-woocommerce-orders', 'joinment_display_wc_shortcode');

/**
 * This function creates the shortcode
 * @param type $atts
 */
function joinment_display_wc_shortcode($atts) {

    //Default atts
    $a = shortcode_atts(array(
        'product-id' => 0,
        'field-slugs' => "name",
        'show-title' => "true",
        'checkout-fields' => "order"
            ), $atts);

    //Replace double spaces with a single space
    $a['field-slugs'] = str_replace('  ', ' ', $a['field-slugs']);

    // Replace space and comma with just comma
    $a['field-slugs'] = str_replace(', ', ',', $a['field-slugs']);

    // If list-key is an array
    if (strpos($a['field-slugs'], ',') !== false) {
        $a['field-slugs'] = explode(',', $a['field-slugs']);
    } else {
        // Make $a into an array anyway
        $a['field-slugs'] = array($a['field-slugs']);
    }

    // The product
    $product = wc_get_product($a['product-id']);

    //Stop if no product is found with id
    if (!$product instanceof WC_Product) {
        return null;
    }

    //Echo table title
    if ($a['show-title'] !== "false") {
        echo('<h1>' . $product->get_name() . '</h1>');
    }

    echo('<div class="container display-woocommerce-orders">');
    echo('<table class="display-woocommerce-orders">');

    // The orders
    $db_orders = get_posts(array(
        'numberposts' => -1,
        'post_type' => 'shop_order',
        'post_status' => 'wc-completed'
    ));

    //Get checkout fields
    $checkout_fields = joinment_get_wc_checkout_fields($a['checkout-fields']);

    echo("<tr>");

    //Echo table column headers
    foreach ($a['field-slugs'] as $field_slug) {
        if (array_key_exists($field_slug, $checkout_fields)) {
            echo("<th>" . $checkout_fields[$field_slug]["label"] . "</th>");
        }
    }

    echo("</tr>");

    // For each order. Remember that an order can contain multiple products!
    foreach ($db_orders as $db_order) {

        // Getting Order ID
        $order_id = $db_order->ID;

        // Getting an instance of the WC order object
        $order = wc_get_order($order_id);

        // For each product in order
        foreach ($order->get_items() as $product) {

            //If this order contains the selected product
            if ($product["product_id"] == $a['product-id']) {

                echo("<tr>");

                // The order data
                $order_meta_data = $order->get_meta_data();

                $data_values = array();

                //For each data
                foreach ($order_meta_data as $order_data) {
                    $data = $order_data->get_data();

                    //For each selected field
                    foreach ($a['field-slugs'] as $field_slug) {

                        //If selected field is equal to data
                        if ($data["key"] == $field_slug) {
                            //Add data value to array
                            $data_values[$field_slug] = (string) $data["value"];
                            break;
                        }
                    }
                }

                //Debug
                /* print_r($a['field-slugs']);
                  echo("<hr>");
                  print_r($data_values);
                  echo("<hr>");
                  print_r($checkout_fields); */

                //Rerun different loop to show cells in order
                foreach ($a['field-slugs'] as $field_slug) {

                    //If field exists
                    if (array_key_exists($field_slug, $checkout_fields)) {
                        //If field has a value
                        if (array_key_exists($field_slug, $data_values)) {
                            echo("<td>" . $data_values[$field_slug] . "</td>");
                        } else {
                            echo("<td></td>");
                        }
                    }
                }



                echo("</tr>");
            }
        }
    }

    echo('</table>');
    echo('</div>');
}
