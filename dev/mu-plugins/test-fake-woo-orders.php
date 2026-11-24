<?php
// TEST ONLY: fake WooCommerce orders, so the dashboard's sales, "Orders to fulfil" and
// "Recent orders" panels can be checked and screenshotted. Use it with test-fake-woo.php,
// never alongside real WooCommerce. Orders come from the option `alc_test_orders`, a list of
// array( 'number', 'name', 'total', 'status', 'time' ), newest first.
if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {}
}

class ALC_Test_Order {
	private $o;
	public function __construct( $o ) { $this->o = $o; }
	public function get_total() { return $this->o['total']; }
	public function get_formatted_billing_full_name() { return $this->o['name']; }
	public function get_order_number() { return $this->o['number']; }
	public function get_currency() { return 'NGN'; }
	public function get_status() { return $this->o['status']; }
	public function get_edit_order_url() { return admin_url( 'edit.php?post_type=shop_order' ); }
	public function get_date_created() { return ( new DateTime() )->setTimestamp( (int) $this->o['time'] ); }
}

function wc_get_orders( $args = array() ) {
	$orders = (array) get_option( 'alc_test_orders', array() );
	if ( ! empty( $args['status'] ) ) {
		$want   = array_map( function ( $s ) { return preg_replace( '/^wc-/', '', $s ); }, (array) $args['status'] );
		$orders = array_filter( $orders, function ( $o ) use ( $want ) { return in_array( $o['status'], $want, true ); } );
	}
	if ( ! empty( $args['date_created'] ) && preg_match( '/^>=(.+)$/', $args['date_created'], $m ) ) {
		$from   = strtotime( $m[1] );
		$orders = array_filter( $orders, function ( $o ) use ( $from ) { return $o['time'] >= $from; } );
	}
	$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 10;
	return array_map( function ( $o ) { return new ALC_Test_Order( $o ); }, array_slice( array_values( $orders ), 0, $limit > 0 ? $limit : null ) );
}

function wc_orders_count( $status ) {
	return count( wc_get_orders( array( 'status' => array( $status ), 'limit' => -1 ) ) );
}

function wc_get_order_status_name( $status ) {
	$names = array( 'pending' => 'Pending payment', 'processing' => 'Processing', 'on-hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'failed' => 'Failed' );
	return isset( $names[ $status ] ) ? $names[ $status ] : ucfirst( $status );
}

// Administrators can manage orders, as they can with real WooCommerce.
add_filter( 'user_has_cap', function ( $caps ) {
	if ( ! empty( $caps['manage_options'] ) ) {
		$caps['edit_shop_orders'] = true;
	}
	return $caps;
} );
