<?php
// TEST ONLY: just enough of WooCommerce for the assistant's store logic.
add_action( 'init', function () {
	register_post_type( 'product', array( 'public' => true, 'label' => 'Products', 'show_ui' => true, 'has_archive' => true, 'menu_icon' => 'dashicons-products' ) );
	register_taxonomy( 'product_cat', 'product', array( 'public' => true, 'label' => 'Categories' ) );
}, 0 );
class ALC_Test_Product {
	private $p;
	public function __construct( $p ) { $this->p = $p; }
	public function get_price() { return get_post_meta( $this->p->ID, '_price', true ); }
	public function get_name() { return $this->p->post_title; }
	public function get_sku() { return ''; }
	public function is_in_stock() { return 'outofstock' !== get_post_meta( $this->p->ID, '_stock_status', true ); }
	public function get_short_description() { return $this->p->post_excerpt; }
}
function wc_get_product( $id ) { $p = get_post( $id ); return ( $p && 'product' === $p->post_type ) ? new ALC_Test_Product( $p ) : false; }
function wc_price( $v ) { return '&#8358;' . number_format( (float) $v ); }
function get_woocommerce_currency() { return 'NGN'; }
