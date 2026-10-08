<?php
/**
 * Tests for the WooCommerce connector.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Test_WP_Stream_Connector_WooCommerce
 *
 * @group connectors
 */
class Test_WP_Stream_Connector_WooCommerce extends WP_StreamTestCase {

	/**
	 * Mocked connector with log() stubbed.
	 *
	 * @var Connector_Woocommerce
	 */
	protected $mock;

	/**
	 * Runs before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		$this->mock = $this->getMockBuilder( Connector_Woocommerce::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
	}

	/**
	 * Test that connector attributes and tracked types are registered properly.
	 */
	public function test_registration() {
		$this->assertSame( 'woocommerce', $this->mock->name );

		$this->assertContains( 'shop_order', $this->mock->post_types );
		$this->assertContains( 'product', $this->mock->post_types );
		$this->assertContains( 'product_variation', $this->mock->post_types );
		$this->assertContains( 'shop_coupon', $this->mock->post_types );

		$this->assertContains( 'product_cat', $this->mock->taxonomies );
		$this->assertContains( 'product_tag', $this->mock->taxonomies );
		$this->assertContains( 'product_type', $this->mock->taxonomies );
		$this->assertContains( 'shop_order_status', $this->mock->taxonomies );

		$this->assertContains( 'woocommerce_order_status_changed', $this->mock->actions );
		$this->assertContains( 'woocommerce_attribute_added', $this->mock->actions );
		$this->assertContains( 'woocommerce_attribute_updated', $this->mock->actions );
		$this->assertContains( 'woocommerce_attribute_deleted', $this->mock->actions );
		$this->assertContains( 'woocommerce_tax_rate_added', $this->mock->actions );
		$this->assertContains( 'woocommerce_tax_rate_updated', $this->mock->actions );
		$this->assertContains( 'woocommerce_tax_rate_deleted', $this->mock->actions );
	}

	/**
	 * Test dependency checking when WooCommerce is not loaded.
	 */
	public function test_is_dependency_satisfied_without_woocommerce() {
		$this->assertFalse( $this->mock->is_dependency_satisfied() );
	}

	/**
	 * Test get_label returns WooCommerce string.
	 */
	public function test_get_label() {
		$this->assertSame( 'WooCommerce', $this->mock->get_label() );
	}

	/**
	 * Test get_action_labels provides standard actions.
	 */
	public function test_get_action_labels() {
		$labels = $this->mock->get_action_labels();

		$this->assertIsArray( $labels );
		$this->assertArrayHasKey( 'created', $labels );
		$this->assertArrayHasKey( 'updated', $labels );
		$this->assertArrayHasKey( 'trashed', $labels );
		$this->assertArrayHasKey( 'deleted', $labels );
	}

	/**
	 * Test get_context_labels includes attributes context.
	 */
	public function test_get_context_labels() {
		$labels = $this->mock->get_context_labels();

		$this->assertIsArray( $labels );
		$this->assertArrayHasKey( 'attributes', $labels );
	}

	/**
	 * Test custom settings registration returns expected defaults.
	 */
	public function test_get_custom_settings() {
		$custom_settings = $this->mock->get_custom_settings();

		$this->assertIsArray( $custom_settings );
		$this->assertArrayHasKey( 'woocommerce_default_gateway', $custom_settings );
		$this->assertArrayHasKey( 'woocommerce_gateway_order', $custom_settings );
		$this->assertArrayHasKey( 'woocommerce_default_shipping_method', $custom_settings );
		$this->assertArrayHasKey( 'shipping_debug_mode', $custom_settings );
	}

	/**
	 * Test that exclude_order_post_types adds shop_order to excluded list.
	 */
	public function test_exclude_order_post_types() {
		$post_types = array( 'post', 'page' );
		$filtered   = $this->mock->exclude_order_post_types( $post_types );

		$this->assertContains( 'shop_order', $filtered );
		$this->assertContains( 'post', $filtered );
		$this->assertContains( 'page', $filtered );
	}

	/**
	 * Test that exclude_order_comment_types adds order_note to excluded comment types.
	 */
	public function test_exclude_order_comment_types() {
		$comment_types = array( 'comment' );
		$filtered      = $this->mock->exclude_order_comment_types( $comment_types );

		$this->assertContains( 'order_note', $filtered );
		$this->assertContains( 'comment', $filtered );
	}

	/**
	 * Test that callback_wp_stream_record_array takes over WooCommerce post types.
	 */
	public function test_callback_wp_stream_record_array_posts_takeover() {
		$records = array(
			array(
				'connector' => 'posts',
				'context'   => 'product',
			),
			array(
				'connector' => 'posts',
				'context'   => 'shop_order',
			),
			array(
				'connector' => 'posts',
				'context'   => 'shop_coupon',
			),
			array(
				'connector' => 'posts',
				'context'   => 'post',
			),
		);

		$filtered = $this->mock->callback_wp_stream_record_array( $records );

		$this->assertSame( 'woocommerce', $filtered[0]['connector'] );
		$this->assertSame( 'woocommerce', $filtered[1]['connector'] );
		$this->assertSame( 'woocommerce', $filtered[2]['connector'] );
		$this->assertSame( 'posts', $filtered[3]['connector'] );
	}

	/**
	 * Test that callback_wp_stream_record_array takes over WooCommerce taxonomies.
	 */
	public function test_callback_wp_stream_record_array_taxonomies_takeover() {
		$records = array(
			array(
				'connector' => 'taxonomies',
				'context'   => 'product_cat',
			),
			array(
				'connector' => 'taxonomies',
				'context'   => 'product_tag',
			),
			array(
				'connector' => 'taxonomies',
				'context'   => 'category',
			),
		);

		$filtered = $this->mock->callback_wp_stream_record_array( $records );

		$this->assertSame( 'woocommerce', $filtered[0]['connector'] );
		$this->assertSame( 'woocommerce', $filtered[1]['connector'] );
		$this->assertSame( 'taxonomies', $filtered[2]['connector'] );
	}

	/**
	 * Test logging when a WooCommerce product attribute is added.
	 */
	public function test_callback_woocommerce_attribute_added() {
		$attribute_id = 42;
		$attribute    = array(
			'attribute_name'  => 'size',
			'attribute_label' => 'Size',
			'attribute_type'  => 'select',
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->stringContains( 'product attribute created' ),
				$this->equalTo( $attribute ),
				$this->equalTo( $attribute_id ),
				$this->equalTo( 'attributes' ),
				$this->equalTo( 'created' )
			);

		$this->mock->callback_woocommerce_attribute_added( $attribute_id, $attribute );
	}

	/**
	 * Test logging when a WooCommerce product attribute is updated.
	 */
	public function test_callback_woocommerce_attribute_updated() {
		$attribute_id = 42;
		$attribute    = array(
			'attribute_name'  => 'size',
			'attribute_label' => 'Shoe Size',
			'attribute_type'  => 'select',
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->stringContains( 'product attribute updated' ),
				$this->equalTo( $attribute ),
				$this->equalTo( $attribute_id ),
				$this->equalTo( 'attributes' ),
				$this->equalTo( 'updated' )
			);

		$this->mock->callback_woocommerce_attribute_updated( $attribute_id, $attribute );
	}

	/**
	 * Test logging when a WooCommerce product attribute is deleted.
	 */
	public function test_callback_woocommerce_attribute_deleted() {
		$attribute_id   = 42;
		$attribute_name = 'color';

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->stringContains( 'product attribute deleted' ),
				$this->equalTo(
					array(
						'attribute_name' => $attribute_name,
					)
				),
				$this->equalTo( $attribute_id ),
				$this->equalTo( 'attributes' ),
				$this->equalTo( 'deleted' )
			);

		$this->mock->callback_woocommerce_attribute_deleted( $attribute_id, $attribute_name );
	}

	/**
	 * Test logging when a WooCommerce tax rate is added.
	 */
	public function test_callback_woocommerce_tax_rate_added() {
		$tax_rate_id = 7;
		$tax_rate    = array(
			'tax_rate_country'  => 'US',
			'tax_rate_state'    => 'NY',
			'tax_rate'          => '8.8750',
			'tax_rate_name'     => 'Sales Tax',
			'tax_rate_priority' => '1',
			'tax_rate_compound' => '0',
			'tax_rate_shipping' => '1',
			'tax_rate_order'    => '0',
			'tax_rate_class'    => '',
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->stringContains( 'tax rate created' ),
				$this->equalTo( $tax_rate ),
				$this->equalTo( $tax_rate_id ),
				$this->equalTo( 'tax' ),
				$this->equalTo( 'created' )
			);

		$this->mock->callback_woocommerce_tax_rate_added( $tax_rate_id, $tax_rate );
	}
}
