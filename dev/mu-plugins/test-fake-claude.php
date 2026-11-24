<?php
// TEST ONLY: stands in for Claude. Lists priced sources with citations, quotes others, and
// deliberately includes a raw URL so the plugin's URL stripping can be checked.
add_filter( 'alc_ai_pre_request', function ( $pre, $body ) {
	update_option( 'alc_test_last_request', $body, false );
	$last = end( $body['messages'] );
	$text = $last['content'];
	preg_match_all( '#<source id="(\d+)" title="([^"]*)">\s*(.*?)\s*</source>#s', $text, $m, PREG_SET_ORDER );
	preg_match( '#Products for sale: (\d+)#', $text, $count );
	if ( ! $m ) {
		return "NOT_FOUND I could not find that on the website. Please visit https://example.com/contact for help.";
	}
	$lines = array(); $priced = 0;
	foreach ( array_slice( $m, 0, 4 ) as $s ) {
		if ( preg_match( '/Price: ([^.]+)/', $s[3], $p ) ) { $lines[] = '- **' . html_entity_decode( $s[2] ) . '**, ' . $p[1] . ' [' . $s[1] . ']'; $priced++; }
		else { $first = preg_split( '/(?<=[.!?])\s+/', $s[3] ); $lines[] = '- ' . $first[0] . ' [' . $s[1] . ']'; }
	}
	$lead = $priced ? ( ( $count ? 'We have ' . $count[1] . ' products. ' : '' ) . 'These fit your budget:' ) : 'Here is what the website says:';
	return $lead . "\n" . implode( "\n", $lines ) . "\nSee https://shopzonea.com/shop/ for more.";
}, 10, 2 );
