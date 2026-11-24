<?php
/**
 * Small shared helpers: brand values, colors, fonts, credit, IP.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param string $key Setting key.
 * @return mixed
 */
function alc_opt( $key ) {
	return ALC_Settings::get( $key );
}

/**
 * Client name, falling back to the site title.
 *
 * @return string
 */
function alc_client_name() {
	$name = trim( (string) alc_opt( 'client_name' ) );
	return '' !== $name ? $name : get_bloginfo( 'name' );
}

/**
 * @param string $key Media setting key.
 * @return string Image URL or empty string.
 */
function alc_media_url( $key ) {
	$id = absint( alc_opt( $key ) );
	if ( ! $id ) {
		return '';
	}
	$url = wp_get_attachment_image_url( $id, 'full' );
	return $url ? $url : '';
}

/**
 * @param string $hex Hex color.
 * @return array{0:int,1:int,2:int}
 */
function alc_hex_to_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return array( 0, 0, 0 );
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * WCAG relative luminance.
 *
 * @param string $hex Hex color.
 * @return float
 */
function alc_luminance( $hex ) {
	$channels = array_map(
		static function ( $c ) {
			$c = $c / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		},
		alc_hex_to_rgb( $hex )
	);
	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * Pick near-white or near-black text, whichever contrasts more.
 *
 * @param string $hex Background color.
 * @return string
 */
function alc_ink_for( $hex ) {
	$l       = alc_luminance( $hex );
	$vs_white = 1.05 / ( $l + 0.05 );
	$vs_black = ( $l + 0.05 ) / ( alc_luminance( '#0B0B0B' ) + 0.05 );
	return $vs_white >= $vs_black ? '#FFFFFF' : '#0B0B0B';
}

/**
 * @param string $hex   Hex color.
 * @param float  $alpha Alpha 0..1.
 * @return string
 */
function alc_rgba( $hex, $alpha ) {
	list( $r, $g, $b ) = alc_hex_to_rgb( $hex );
	return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( number_format( (float) $alpha, 2, '.', '' ), '0' ), '.' ) );
}

/**
 * Normalise any CSS color we can read (#rgb, #rrggbb, #rrggbbaa, rgb[a]()) to #RRGGBB.
 *
 * @param mixed $value Color.
 * @return string Hex or ''.
 */
function alc_hex( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$v = strtolower( trim( $value ) );
	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $v, $m ) ) {
		$h = $m[1];
		if ( strlen( $h ) <= 4 ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		return '#' . strtoupper( substr( $h, 0, 6 ) );
	}
	if ( preg_match( '/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/', $v, $m ) ) {
		return sprintf( '#%02X%02X%02X', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
	}
	return '';
}

/**
 * @param string $hex Hex color.
 * @return array{0: float, 1: float, 2: float} Hue 0-360, saturation 0-1, lightness 0-1.
 */
function alc_hsl( $hex ) {
	list( $r, $g, $b ) = array_map(
		static function ( $c ) {
			return $c / 255;
		},
		alc_hex_to_rgb( $hex )
	);
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$l   = ( $max + $min ) / 2;
	if ( $max === $min ) {
		return array( 0.0, 0.0, $l );
	}
	$d = $max - $min;
	$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
	if ( $max === $r ) {
		$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $d + 2;
	} else {
		$h = ( $r - $g ) / $d + 4;
	}
	return array( $h * 60, $s, $l );
}

/**
 * @param string $hex Hex color.
 * @return float
 */
function alc_saturation( $hex ) {
	$hsl = alc_hsl( $hex );
	return $hsl[1];
}

/**
 * Mix two colors. $t = 0 returns $a, 1 returns $b.
 *
 * @param string $a Hex.
 * @param string $b Hex.
 * @param float  $t Amount of $b.
 * @return string
 */
function alc_mix( $a, $b, $t ) {
	$x = alc_hex_to_rgb( $a );
	$y = alc_hex_to_rgb( $b );
	return sprintf(
		'#%02X%02X%02X',
		round( $x[0] + ( $y[0] - $x[0] ) * $t ),
		round( $x[1] + ( $y[1] - $x[1] ) * $t ),
		round( $x[2] + ( $y[2] - $x[2] ) * $t )
	);
}

/**
 * WCAG contrast ratio.
 *
 * @param string $a Hex.
 * @param string $b Hex.
 * @return float
 */
function alc_contrast( $a, $b ) {
	$la = alc_luminance( $a );
	$lb = alc_luminance( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * Nudge a color darker (or lighter) until it reads on the given background.
 *
 * @param string $hex Color.
 * @param string $bg  Background.
 * @param float  $min Target ratio.
 * @return string
 */
function alc_readable( $hex, $bg = '#FFFFFF', $min = 4.5 ) {
	$toward = alc_luminance( $bg ) > 0.4 ? '#000000' : '#FFFFFF';
	$out    = $hex;
	for ( $i = 1; $i <= 10 && alc_contrast( $out, $bg ) < $min; $i++ ) {
		$out = alc_mix( $hex, $toward, $i * 0.1 );
	}
	return $out;
}

/**
 * The logo URL to show (setting, then site logo).
 *
 * @return string
 */
function alc_logo_url() {
	$brand = ALC_Brand::resolved();
	return $brand['logo_url'];
}

/**
 * Self-hosted Bricolage face, only when it is actually used.
 *
 * @return string
 */
function alc_font_face_css() {
	$brand = ALC_Brand::resolved();
	if ( empty( $brand['uses_bricolage'] ) ) {
		return '';
	}
	$src = esc_url( ALC_URL . 'assets/fonts/bricolage-grotesque-latin-wght-normal.woff2' );
	return "@font-face{font-family:\"ALC Bricolage\";src:url('{$src}') format('woff2');font-weight:200 800;font-style:normal;font-display:swap;}";
}

/**
 * Brand tokens as CSS custom properties.
 *
 * @param string $selector Scope selector.
 * @return string
 */
function alc_brand_css( $selector = ':root' ) {
	$b         = ALC_Brand::resolved();
	$accent    = $b['accent'];
	$panel     = $b['panel'];
	$panel_ink = alc_ink_for( $panel );
	$text      = alc_readable( $accent, '#FFFFFF', 4.5 );
	$theme     = alc_contrast( $accent, '#FFFFFF' ) >= 3 ? $accent : $text;
	$rgb       = implode( ',', alc_hex_to_rgb( $theme ) );

	$vars = array(
		'--alc-accent'                     => $accent,
		'--alc-accent-ink'                 => alc_ink_for( $accent ),
		'--alc-accent-text'                => $text,
		'--alc-accent-soft'                => alc_rgba( $accent, 0.14 ),
		'--alc-accent-ring'                => alc_rgba( $accent, 0.32 ),
		'--alc-accent-on-panel'            => alc_contrast( $accent, $panel ) >= 2 ? $accent : $panel_ink,
		'--alc-panel'                      => $panel,
		'--alc-panel-ink'                  => $panel_ink,
		'--alc-panel-muted'                => alc_rgba( $panel_ink, 0.68 ),
		'--alc-panel-line'                 => alc_rgba( $panel_ink, 0.14 ),
		'--alc-panel-hover'                => alc_rgba( $panel_ink, 0.08 ),
		'--alc-panel-veil'                 => alc_rgba( $panel, 0.8 ),
		'--alc-font'                       => $b['body_font'],
		'--alc-font-display'               => $b['heading_font'],
		'--alc-logo-width'                 => absint( alc_opt( 'logo_width' ) ) . 'px',
		'--wp-admin-theme-color'           => $theme,
		'--wp-admin-theme-color--rgb'      => $rgb,
		'--wp-admin-theme-color-darker-10' => alc_mix( $theme, '#000000', 0.1 ),
		'--wp-admin-theme-color-darker-10--rgb' => implode( ',', alc_hex_to_rgb( alc_mix( $theme, '#000000', 0.1 ) ) ),
		'--wp-admin-theme-color-darker-20' => alc_mix( $theme, '#000000', 0.2 ),
		'--wp-admin-theme-color-darker-20--rgb' => implode( ',', alc_hex_to_rgb( alc_mix( $theme, '#000000', 0.2 ) ) ),
	);

	$css = '';
	foreach ( $vars as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}

	return alc_font_face_css() . $selector . '{' . $css . '}';
}

/**
 * The editor's theme wrapper sets its own blue inline, so restate the
 * admin theme color there with !important.
 *
 * @return string
 */
function alc_editor_theme_css() {
	$css = alc_brand_css( '' );
	if ( ! preg_match_all( '/(--wp-admin-theme-color[a-z0-9-]*):([^;]+);/', $css, $m, PREG_SET_ORDER ) ) {
		return '';
	}
	$out = '';
	foreach ( $m as $pair ) {
		$out .= $pair[1] . ':' . $pair[2] . ' !important;';
	}
	return 'body.alc-chrome [style*="--wp-admin-theme-color"]{' . $out . '}';
}

/**
 * Link back to the studio, tagged so visits from client sites show up in analytics.
 *
 * @return string
 */
function alc_studio_url() {
	$url = add_query_arg(
		array(
			'utm_source'   => rawurlencode( wp_parse_url( home_url(), PHP_URL_HOST ) ),
			'utm_medium'   => 'client-console',
			'utm_campaign' => 'credit',
		),
		'https://al-ibiewi.com.ng/'
	);
	/**
	 * Filter the studio credit link.
	 *
	 * @param string $url Link URL.
	 */
	return apply_filters( 'alc_studio_url', $url );
}

/**
 * The studio credit. Uses assets/img/studio-mark.svg when you add one.
 *
 * @return string
 */
function alc_studio_credit_html() {
	if ( ! alc_opt( 'studio_credit' ) ) {
		return '';
	}

	$mark = '';
	if ( file_exists( ALC_PATH . 'assets/img/studio-mark.svg' ) ) {
		$mark = '<img class="alc-credit__mark" src="' . esc_url( ALC_URL . 'assets/img/studio-mark.svg' ) . '" alt="" width="18" height="18">';
	}

	return sprintf(
		'<a class="alc-credit" href="%1$s" target="_blank" rel="noopener">%2$s<span>%3$s <strong>Al-Ibiewi Studio</strong></span></a>',
		esc_url( alc_studio_url() ),
		$mark,
		esc_html__( 'Crafted by', 'alibiewi-console' )
	);
}

/**
 * Visitor IP. Only trusts the Cloudflare header when told the site is behind Cloudflare.
 *
 * @return string
 */
function alc_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	if ( alc_opt( 'trust_cloudflare' ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
			$ip = $cf;
		}
	}

	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/**
 * Tutorials setting parsed into [ title, url ] pairs.
 *
 * @return array<int, array{title:string,url:string}>
 */
function alc_tutorials() {
	$out   = array();
	$lines = preg_split( '/\r\n|\r|\n/', (string) alc_opt( 'tutorials' ) );
	foreach ( $lines as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && '' !== $parts[0] && filter_var( $parts[1], FILTER_VALIDATE_URL ) && in_array( wp_parse_url( $parts[1], PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			$out[] = array(
				'title' => $parts[0],
				'url'   => $parts[1],
			);
		}
	}
	return $out;
}
