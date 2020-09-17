<?php
/**
 * WP Integration Test w/ WooCommerce
 *
 * Tests for WooCommerce connector class callbacks.
 *
 * @package WP_Stream
 */
namespace WP_Stream;

class Test_WP_Stream_Connector_Woocommerce extends WP_StreamTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		// Make partial of Connector_Woocommmerce class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Woocommerce::class )
			->setMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	/**
	 * Create simple product.
	 *
	 * @param bool  $save Save or return object.
	 * @param array $props Properties to be set in the new product, as an associative array.
	 *
	 * @return WC_Product_Simple
	 */
	private function create_simple_product( $save = true, $props = array() ) {
		$product       = new \WC_Product_Simple();
		$default_props =
			array(
				'name'          => 'Dummy Product',
				'regular_price' => 10,
				'price'         => 10,
				'sku'           => 'DUMMY SKU',
				'manage_stock'  => false,
				'tax_status'    => 'taxable',
				'downloadable'  => false,
				'virtual'       => false,
				'stock_status'  => 'instock',
				'weight'        => '1.1',
			);

		$product->set_props( array_merge( $default_props, $props ) );

		if ( $save ) {
			$product->save();
			return \wc_get_product( $product->get_id() );
		} else {
			return $product;
		}
	}

	/**
	 * Create a simple flat rate at the cost of 10.
	 *
	 * @param float $cost Optional. Cost of flat rate method.
	 */
	private function create_simple_flat_rate( $cost = 10 ) {
		$flat_rate_settings = array(
			'enabled'      => 'yes',
			'title'        => 'Flat rate',
			'availability' => 'all',
			'countries'    => '',
			'tax_status'   => 'taxable',
			'cost'         => $cost,
		);

		update_option( 'woocommerce_flat_rate_settings', $flat_rate_settings );
		update_option( 'woocommerce_flat_rate', array() );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		\WC()->shipping()->load_shipping_methods();
	}

	/**
	 * Create a order.
	 *
	 * @param int        $customer_id The ID of the customer the order is for.
	 * @param WC_Product $product The product to add to the order.
	 *
	 * @return WC_Order
	 */
	private function create_order( $customer_id = 1, $product = null ) {

		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = $this->create_simple_product();
		}

		$this->create_simple_flat_rate();

		$order_data = array(
			'status'        => 'pending',
			'customer_id'   => $customer_id,
			'customer_note' => '',
			'total'         => '',
		);

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception.
		$order                  = \wc_create_order( $order_data );

		// Add order products.
		$item = new \WC_Order_Item_Product();
		$item->set_props(
			array(
				'product'  => $product,
				'quantity' => 4,
				'subtotal' => \wc_get_price_excluding_tax( $product, array( 'qty' => 4 ) ),
				'total'    => \wc_get_price_excluding_tax( $product, array( 'qty' => 4 ) ),
			)
		);
		$item->save();
		$order->add_item( $item );

		// Set billing address.
		$order->set_billing_first_name( 'Jeroen' );
		$order->set_billing_last_name( 'Sormani' );
		$order->set_billing_company( 'WooCompany' );
		$order->set_billing_address_1( 'WooAddress' );
		$order->set_billing_address_2( '' );
		$order->set_billing_city( 'WooCity' );
		$order->set_billing_state( 'NY' );
		$order->set_billing_postcode( '12345' );
		$order->set_billing_country( 'US' );
		$order->set_billing_email( 'admin@example.org' );
		$order->set_billing_phone( '555-32123' );

		// Add shipping costs.
		$shipping_taxes = \WC_Tax::calc_shipping_tax( '10', \WC_Tax::get_shipping_tax_rates() );
		$rate           = new \WC_Shipping_Rate( 'flat_rate_shipping', 'Flat rate shipping', '10', $shipping_taxes, 'flat_rate' );
		$item           = new \WC_Order_Item_Shipping();
		$item->set_props(
			array(
				'method_title' => $rate->label,
				'method_id'    => $rate->id,
				'total'        => wc_format_decimal( $rate->cost ),
				'taxes'        => $rate->taxes,
			)
		);
		foreach ( $rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
		$order->add_item( $item );

		// Set payment gateway.
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( $payment_gateways['bacs'] );

		// Set totals.
		$order->set_shipping_total( 10 );
		$order->set_discount_total( 0 );
		$order->set_discount_tax( 0 );
		$order->set_cart_tax( 0 );
		$order->set_shipping_tax( 0 );
		$order->set_total( 50 ); // 4 x $10 simple helper product
		$order->save();

		return $order;
	}

	public function test_callback_transition_post_status() {
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo(
						esc_html_x(
							'%s created',
							'Order title',
							'stream'
						)
					),
					$this->equalTo(
						array(
							'post_title'    => 'shop_order',
							'singular_name' => 'order',
							'new_status'    => 'publish',
							'old_status'    => 'auto-draft',
							'revision_id'   => null,
						)
					),
					$this->greaterThan( 0 ),
					$this->equalTo( 'shop_order' ),
					$this->equalTo( 'updated' )
				)
			);

		// Create/update/trash/restore order to trigger callback.
		$order = $this->create_order();

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( 'wp_stream_test_callback_transition_post_status' ) );
	}

	public function test_callback_deleted_post() {

	}

	public function test_callback_woocommerce_order_status_changed() {

	}

	public function test_callback_woocommerce_attribute_added() {

	}

	public function test_callback_woocommerce_attribute_updated() {

	}

	public function test_callback_woocommerce_attribute_deleted() {

	}

	public function test_callback_woocommerce_tax_rate_added() {

	}

	public function test_callback_woocommerce_tax_rate_updated() {

	}

	public function test_callback_woocommerce_tax_rate_deleted() {

	}

	public function test_callback_updated_option() {

	}
}
