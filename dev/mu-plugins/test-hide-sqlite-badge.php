<?php
// TEST ONLY: hide the SQLite test-environment badge in screenshots.
add_action( 'admin_bar_menu', function ( $bar ) { foreach ( array_keys( (array) $bar->get_nodes() ) as $id ) { if ( false !== stripos( $id, 'sqlite' ) ) { $bar->remove_node( $id ); } } }, 9999 );
