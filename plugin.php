<?php
/**
 * Plugin Name: Display WooCommerce Orders
 * Description: Display order meta in a table from orders containing a certain product
 * Version: 0.1.0
 * Author: Sakri Koskimies
 */

/**
 * Class Display_WC_Orders
 */
class Display_WC_Orders {
	private static $shortcode_name = 'display-woocommerce-orders';

	/**
	 * Display_WC_Orders constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_print_footer_scripts', array( $this, 'print_inline_style' ) );
	}

	/**
	 * Echo shortcode
	 *
	 * @param array $atts Shortcode atts.
	 *
	 * @return string
	 */
	public function print_shortcode( array $atts ) {
		$atts = shortcode_atts( array(
			'product-id' => '',
			'meta'       => '',
			'title'      => '',
			'after-date' => 0
		), $atts, static::$shortcode_name );

		$product_id = $atts['product-id'];
		$metas      = explode( ',', $atts['meta'] );
		$titles     = explode( ',', $atts['title'] );
		$after      = $atts['after-date'];

		if ( ! empty( $after ) ) {
			try {
				$after = DateTime::createFromFormat( 'Y-m-d', $after )->getTimestamp();
			} catch ( Throwable $e ) {
				// Do nothing.
			}
		}

		$order_ids = static::get_orders_containing_product( $product_id );

		ob_start();

		echo '<div class="display-woocommerce-orders--container">';
		echo '<table class="display-woocommerce-orders">';

		$this->print_titles( $titles );

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( empty( $order ) || $order->get_date_completed()->getTimestamp() < $after ) {
				continue;
			}

			echo '<tr>';

			foreach ( $metas as $meta ) {
				$meta  = trim( $meta );
				$value = $order->get_meta( $meta );

				printf( '<td>%s</td>', $value );
			}

			echo '</tr>';
		}

		echo '</table>';
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Echo table titles
	 *
	 * @param string[] $titles Titles to print.
	 */
	private function print_titles( array $titles ) {
		echo '<tr>';

		foreach ( $titles as $title ) {
			printf( '<th>%s</th>', trim( $title ) );
		}

		echo '</tr>';
	}

	/**
	 * Get a list of order id's containing a product
	 *
	 * @param int|string $product_id Product id to search for.
	 *
	 * @return int[]|string[]
	 */
	private static function get_orders_containing_product( $product_id ) {
		global $wpdb;

		// Sanity check.
		if ( ! is_numeric( $product_id ) ) {
			return array();
		}

		$results = $wpdb->get_col( "
        SELECT order_items.order_id
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
        AND posts.post_status != 'trash'
        AND order_items.order_item_type = 'line_item'
        AND order_item_meta.meta_key = '_product_id'
        AND order_item_meta.meta_value = '$product_id'
    " );

		return $results;
	}

	/**
	 * Register shortcode
	 */
	public function register_shortcode() {
		add_shortcode( static::$shortcode_name, array( $this, 'print_shortcode' ) );
	}

	/**
	 * Print inline style to the footer. Yikes
	 */
	public function print_inline_style() {
		?>
        <style>
            table.display-woocommerce-orders {
                width: 100% !important;
            }

            table.display-woocommerce-orders th, table.display-woocommerce-orders td {
                text-align: left;
                padding: 6px;
            }

            .display-woocommerce-orders--container {
                overflow-x: auto !important;
            }
        </style>
		<?php
	}
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$plugin = new Display_WC_Orders();
	}
);
