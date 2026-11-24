<?php
/**
 * Small brand touches across wp-admin.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Branding {

	public static function init() {
		if ( alc_opt( 'admin_footer' ) ) {
			add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ), 20 );
		}
	}

	/**
	 * @param string $text Core footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( ! alc_opt( 'studio_credit' ) ) {
			return esc_html( alc_client_name() );
		}

		return sprintf(
			/* translators: %s: studio link. */
			esc_html__( 'Built and cared for by %s', 'alibiewi-console' ),
			'<a href="' . esc_url( alc_studio_url() ) . '" target="_blank" rel="noopener">Al-Ibiewi Studio</a>'
		);
	}
}
