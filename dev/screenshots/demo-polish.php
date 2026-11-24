<?php
// SCREENSHOT ONLY: copy into mu-plugins on the demo site. The local site runs on plain http, so show the
// health row as a live HTTPS site would.
add_filter( 'alc_health_rows', function ( $rows ) {
	foreach ( $rows as &$row ) {
		if ( isset( $row['label'] ) && 'Secure connection' === $row['label'] ) {
			$row['value'] = 'HTTPS on';
			$row['state'] = 'ok';
		}
	}
	return $rows;
} );

// Hide the SQLite test-database menu from the screenshots.
add_action( 'admin_menu', function () { remove_submenu_page( 'options-general.php', 'sqlite-integration' ); }, 999 );

// A natural reply for the demo question in screenshot 07 (made-up content, as agreed with the owner).
add_filter( 'alc_ai_pre_request', function ( $pre, $body ) {
	$last = end( $body['messages'] );
	if ( false === stripos( $last['content'], 'What can I get for 400k' ) ) {
		return $pre;
	}
	return "For 400k, the **Starter Website** at ₦350,000 is your best fit [1].

When you are ready to grow, add the **AI Assistant Setup** [3] or **SEO Setup** [4].";
}, 20, 2 );
