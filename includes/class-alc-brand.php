<?php
/**
 * Works out the client's brand from the website itself: logo, colors and
 * fonts from theme.json, Elementor, Astra, GeneratePress, Kadence or
 * Storefront, with the logo's own colors as a fallback. Anything set in
 * Settings > Studio Console wins over what is detected.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Brand {

	const CACHE   = 'alc_brand_detect';
	const VERSION = 4;

	/** @var array|null */
	private static $resolved = null;

	/** @var bool */
	private static $faces_printed = false;

	public static function init() {
		$flush = array( __CLASS__, 'flush' );
		foreach ( array( 'switch_theme', 'customize_save_after', 'update_option_site_icon', 'update_option_elementor_active_kit', 'update_option_astra-settings', 'update_option_generate_settings', 'save_post_wp_global_styles', 'save_post_elementor_library', 'elementor/core/files/clear_cache' ) as $hook ) {
			add_action( $hook, $flush );
		}
		add_action( 'update_option_' . ALC_Settings::OPTION, array( __CLASS__, 'reset_request_cache' ) );
	}

	public static function flush() {
		delete_transient( self::CACHE );
		self::$resolved = null;
	}

	public static function reset_request_cache() {
		self::$resolved = null;
	}

	/* --------------------------------------------------------------------
	 * Color themes
	 * ------------------------------------------------------------------ */

	/**
	 * Ready-made color themes. Panel colors the login brand panel, sidebar
	 * and dashboard header; accent colors buttons and the active menu item.
	 *
	 * @return array
	 */
	public static function themes() {
		$themes = array(
			'studio'   => array( __( 'Studio', 'alibiewi-console' ), '#0B0B0B', '#C44332' ),
			'midnight' => array( __( 'Midnight', 'alibiewi-console' ), '#0F172A', '#3B82F6' ),
			'forest'   => array( __( 'Forest', 'alibiewi-console' ), '#10302A', '#E0A526' ),
			'ocean'    => array( __( 'Ocean', 'alibiewi-console' ), '#0B2440', '#14B8A6' ),
			'royal'    => array( __( 'Royal', 'alibiewi-console' ), '#1E1B4B', '#F59E0B' ),
			'plum'     => array( __( 'Plum', 'alibiewi-console' ), '#2A1438', '#E0569B' ),
			'mint'     => array( __( 'Mint', 'alibiewi-console' ), '#EEF6F1', '#0E7A5A' ),
			'sand'     => array( __( 'Sand', 'alibiewi-console' ), '#F4EFE6', '#B4572E' ),
			'cloud'    => array( __( 'Cloud', 'alibiewi-console' ), '#FFFFFF', '#2563EB' ),
			'blush'    => array( __( 'Blush', 'alibiewi-console' ), '#FBF0F2', '#BE123C' ),
		);

		$out = array();
		foreach ( $themes as $key => $t ) {
			$out[ $key ] = array(
				'label'  => $t[0],
				'panel'  => $t[1],
				'accent' => $t[2],
			);
		}

		/**
		 * Filter the color themes. Each: [ 'label' => '', 'panel' => '#hex', 'accent' => '#hex' ].
		 *
		 * @param array $out Themes keyed by slug.
		 */
		return apply_filters( 'alc_color_themes', $out );
	}

	/**
	 * Theme slugs, for the settings schema (no translation needed).
	 *
	 * @return string[]
	 */
	public static function theme_keys() {
		return array( 'studio', 'midnight', 'forest', 'ocean', 'royal', 'plum', 'mint', 'sand', 'cloud', 'blush' );
	}

	/* --------------------------------------------------------------------
	 * Detection (logo and fonts only)
	 * ------------------------------------------------------------------ */

	/**
	 * Logo and fonts detected from the site, cached for 12 hours.
	 *
	 * @return array
	 */
	public static function detect() {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) && isset( $cached['v'] ) && self::VERSION === $cached['v'] ) {
			return $cached;
		}

		$out = array(
			'v'            => self::VERSION,
			'body_font'    => '',
			'heading_font' => '',
			'font_kind'    => '',
			'font_source'  => '',
			'logo_id'      => 0,
		);

		$detectors = array(
			'elementor'     => __( 'Elementor site settings', 'alibiewi-console' ),
			'theme_json'    => __( 'theme styles', 'alibiewi-console' ),
			'astra'         => __( 'Astra', 'alibiewi-console' ),
			'generatepress' => __( 'GeneratePress', 'alibiewi-console' ),
			'kadence'       => __( 'Kadence', 'alibiewi-console' ),
			'storefront'    => __( 'Storefront', 'alibiewi-console' ),
		);

		foreach ( $detectors as $key => $label ) {
			$found = call_user_func( array( __CLASS__, 'detect_' . $key ) );
			if ( ! is_array( $found ) ) {
				continue;
			}
			if ( ! $out['logo_id'] && ! empty( $found['logo_id'] ) ) {
				$out['logo_id'] = (int) $found['logo_id'];
			}
			// Take both fonts from one source so they load the same way.
			if ( '' === $out['body_font'] && ( ! empty( $found['body_font'] ) || ! empty( $found['heading_font'] ) ) ) {
				$out['body_font']    = isset( $found['body_font'] ) ? (string) $found['body_font'] : '';
				$out['heading_font'] = isset( $found['heading_font'] ) ? (string) $found['heading_font'] : '';
				$out['font_kind']    = $found['font_kind'];
				$out['font_source']  = $label;
			}
		}

		if ( ! $out['logo_id'] ) {
			$out['logo_id'] = (int) get_theme_mod( 'custom_logo' );
		}

		set_transient( self::CACHE, $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	private static function detect_elementor() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return array();
		}
		$kit = (int) get_option( 'elementor_active_kit' );
		$s   = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();
		if ( ! is_array( $s ) ) {
			return array();
		}

		$fonts = array();
		foreach ( (array) ( isset( $s['system_typography'] ) ? $s['system_typography'] : array() ) as $t ) {
			if ( ! empty( $t['_id'] ) && ! empty( $t['typography_font_family'] ) ) {
				$fonts[ $t['_id'] ] = $t['typography_font_family'];
			}
		}

		return array(
			'body_font'    => self::family_stack( self::get( $fonts, 'text' ) ),
			'heading_font' => self::family_stack( self::get( $fonts, 'primary' ) ),
			'font_kind'    => 'google',
			'logo_id'      => isset( $s['site_logo']['id'] ) ? (int) $s['site_logo']['id'] : 0,
		);
	}

	private static function detect_theme_json() {
		if ( ! function_exists( 'wp_get_global_styles' ) || ! function_exists( 'wp_theme_has_theme_json' ) || ! wp_theme_has_theme_json() ) {
			return array();
		}

		$families = array();
		$fsets    = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			if ( ! empty( $fsets[ $origin ] ) && is_array( $fsets[ $origin ] ) ) {
				foreach ( $fsets[ $origin ] as $entry ) {
					if ( isset( $entry['slug'], $entry['fontFamily'] ) ) {
						$families[ $entry['slug'] ] = $entry['fontFamily'];
					}
				}
			}
		}
		if ( ! $families ) {
			return array();
		}

		$font = static function ( $value ) use ( $families ) {
			if ( ! is_string( $value ) || '' === $value ) {
				return '';
			}
			if ( preg_match( '/var\(--wp--preset--font-family--([a-z0-9-]+)\)|var:preset\|font-family\|([a-z0-9-]+)/i', $value, $m ) ) {
				$slug = ! empty( $m[2] ) ? $m[2] : $m[1];
				return isset( $families[ $slug ] ) ? $families[ $slug ] : '';
			}
			return $value;
		};

		$styles  = wp_get_global_styles();
		$heading = $font( self::dig( $styles, array( 'elements', 'heading', 'typography', 'fontFamily' ) ) );
		if ( '' === $heading ) {
			$heading = $font( self::dig( $styles, array( 'elements', 'h1', 'typography', 'fontFamily' ) ) );
		}

		return array(
			'body_font'    => $font( self::dig( $styles, array( 'typography', 'fontFamily' ) ) ),
			'heading_font' => $heading,
			'font_kind'    => 'theme_json',
		);
	}

	private static function detect_astra() {
		if ( ! defined( 'ASTRA_THEME_VERSION' ) ) {
			return array();
		}
		$o = get_option( 'astra-settings', array() );
		$o = is_array( $o ) ? $o : array();
		return array(
			'body_font'    => self::family_stack( self::get( $o, 'body-font-family' ) ),
			'heading_font' => self::family_stack( self::get( $o, 'headings-font-family' ) ),
			'font_kind'    => 'google',
		);
	}

	private static function detect_generatepress() {
		if ( ! defined( 'GENERATE_VERSION' ) ) {
			return array();
		}
		$o = get_option( 'generate_settings', array() );
		$o = is_array( $o ) ? $o : array();
		return array(
			'body_font' => self::family_stack( self::get( $o, 'font_body' ) ),
			'font_kind' => 'google',
		);
	}

	private static function detect_kadence() {
		if ( ! defined( 'KADENCE_VERSION' ) ) {
			return array();
		}
		$base    = get_theme_mod( 'base_font', array() );
		$heading = get_theme_mod( 'heading_font', array() );
		return array(
			'body_font'    => self::family_stack( is_array( $base ) ? self::get( $base, 'family' ) : '' ),
			'heading_font' => self::family_stack( is_array( $heading ) ? self::get( $heading, 'family' ) : '' ),
			'font_kind'    => 'google',
		);
	}

	private static function detect_storefront() {
		if ( 'storefront' !== get_template() ) {
			return array();
		}
		return array(
			'body_font' => self::family_stack( 'Source Sans Pro' ),
			'font_kind' => 'google',
		);
	}

	/* --------------------------------------------------------------------
	 * Logo analysis
	 * ------------------------------------------------------------------ */

	/**
	 * How light the visible part of a logo is, and its main brand color.
	 *
	 * @param int $id Attachment ID.
	 * @return array{lum: float|null, color: string}
	 */
	public static function logo_tone( $id ) {
		$none = array(
			'lum'   => null,
			'color' => '',
		);
		$file = $id ? get_attached_file( $id ) : '';
		if ( ! $file || ! is_readable( $file ) || filesize( $file ) > 8 * MB_IN_BYTES ) {
			return $none;
		}

		$mtime  = (int) filemtime( $file );
		$cached = get_post_meta( $id, '_alc_tone', true );
		if ( is_array( $cached ) && isset( $cached['m'] ) && $cached['m'] === $mtime ) {
			return array(
				'lum'   => $cached['lum'],
				'color' => $cached['color'],
			);
		}

		$mime = (string) get_post_mime_type( $id );
		$tone = ( false !== strpos( $mime, 'svg' ) ) ? self::svg_tone( $file ) : self::raster_tone( $file );
		$tone = $tone ? $tone : $none;

		update_post_meta(
			$id,
			'_alc_tone',
			array(
				'm'     => $mtime,
				'lum'   => $tone['lum'],
				'color' => $tone['color'],
			)
		);
		return $tone;
	}

	/**
	 * @param string $file Path.
	 * @return array|null
	 */
	private static function svg_tone( $file ) {
		$svg = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		preg_match_all( '/(?:fill|stroke|stop-color)\s*[:=]\s*["\']?\s*(#[0-9a-f]{3,8}|white|black)/i', $svg, $m );

		$colors = array();
		foreach ( $m[1] as $raw ) {
			$raw      = strtolower( $raw );
			$hex      = 'white' === $raw ? '#FFFFFF' : ( 'black' === $raw ? '#000000' : alc_hex( $raw ) );
			$colors[] = $hex;
		}
		$colors = array_filter( $colors );
		if ( ! $colors ) {
			$colors = array( '#000000' ); // SVG default fill.
		}

		$sum   = 0;
		$brand = '';
		foreach ( $colors as $hex ) {
			$sum += alc_luminance( $hex );
			if ( '' === $brand && alc_saturation( $hex ) > 0.35 ) {
				$brand = $hex;
			}
		}

		return array(
			'lum'   => $sum / count( $colors ),
			'color' => $brand,
		);
	}

	/**
	 * @param string $file Path.
	 * @return array|null
	 */
	private static function raster_tone( $file ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}
		$src = @imagecreatefromstring( (string) file_get_contents( $file ) ); // phpcs:ignore
		if ( ! $src ) {
			return null;
		}

		$w     = imagesx( $src );
		$h     = imagesy( $src );
		$scale = 48 / max( $w, $h, 1 );
		$sw    = max( 1, (int) round( $w * $scale ) );
		$sh    = max( 1, (int) round( $h * $scale ) );
		$img   = imagecreatetruecolor( $sw, $sh );
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 0, 0, 127 ) );
		imagecopyresampled( $img, $src, 0, 0, 0, 0, $sw, $sh, $w, $h );

		$transparent = 0;
		$lum_sum     = 0;
		$lum_n       = 0;
		$buckets     = array();

		for ( $y = 0; $y < $sh; $y++ ) {
			for ( $x = 0; $x < $sw; $x++ ) {
				$c = imagecolorat( $img, $x, $y );
				$a = ( $c >> 24 ) & 0x7F;
				if ( $a > 100 ) {
					++$transparent;
					continue;
				}
				$r   = ( $c >> 16 ) & 0xFF;
				$g   = ( $c >> 8 ) & 0xFF;
				$b   = $c & 0xFF;
				$hex = sprintf( '#%02X%02X%02X', $r, $g, $b );

				$lum_sum += alc_luminance( $hex );
				++$lum_n;

				list( $hue, $sat, $light ) = alc_hsl( $hex );
				if ( $sat > 0.35 && $light > 0.15 && $light < 0.85 ) {
					$k = (int) floor( $hue / 30 );
					if ( ! isset( $buckets[ $k ] ) ) {
						$buckets[ $k ] = array( 0, 0, 0, 0 );
					}
					$buckets[ $k ][0] += $r;
					$buckets[ $k ][1] += $g;
					$buckets[ $k ][2] += $b;
					++$buckets[ $k ][3];
				}
			}
		}

		// Opaque images show as a box, so the box (corner) color is what matters.
		if ( $transparent < 4 ) {
			$corners = array( imagecolorat( $img, 0, 0 ), imagecolorat( $img, $sw - 1, 0 ), imagecolorat( $img, 0, $sh - 1 ), imagecolorat( $img, $sw - 1, $sh - 1 ) );
			$lum_sum = 0;
			foreach ( $corners as $c ) {
				$lum_sum += alc_luminance( sprintf( '#%02X%02X%02X', ( $c >> 16 ) & 0xFF, ( $c >> 8 ) & 0xFF, $c & 0xFF ) );
			}
			$lum_n = 4;
		}

		imagedestroy( $img );
		imagedestroy( $src );

		$color = '';
		if ( $buckets ) {
			uasort(
				$buckets,
				static function ( $a, $b ) {
					return $b[3] - $a[3];
				}
			);
			$top = reset( $buckets );
			if ( $top[3] >= 6 ) {
				$color = sprintf( '#%02X%02X%02X', $top[0] / $top[3], $top[1] / $top[3], $top[2] / $top[3] );
			}
		}

		return array(
			'lum'   => $lum_n ? $lum_sum / $lum_n : null,
			'color' => $color,
		);
	}

	/* --------------------------------------------------------------------
	 * Resolved brand (settings first, then detection, then defaults)
	 * ------------------------------------------------------------------ */

	/**
	 * @return array
	 */
	public static function resolved() {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}

		$d = self::detect();

		$logo_id = (int) alc_opt( 'logo_id' );
		if ( ! $logo_id || ! wp_attachment_is_image( $logo_id ) ) {
			$logo_id = ( $d['logo_id'] && wp_attachment_is_image( $d['logo_id'] ) ) ? (int) $d['logo_id'] : 0;
		}
		$tone = $logo_id ? self::logo_tone( $logo_id ) : array(
			'lum'   => null,
			'color' => '',
		);

		$themes = self::themes();
		$key    = (string) alc_opt( 'theme' );
		$theme  = isset( $themes[ $key ] ) ? $themes[ $key ] : reset( $themes );

		$accent = alc_hex( (string) alc_opt( 'accent' ) );
		$accent = $accent ? $accent : alc_hex( $theme['accent'] );
		$panel  = alc_hex( (string) alc_opt( 'panel_bg' ) );
		$panel  = $panel ? $panel : alc_hex( $theme['panel'] );

		// Give the logo a backing when it would vanish into the panel.
		$chip = '';
		if ( null !== $tone['lum'] ) {
			$panel_lum = alc_luminance( $panel );
			if ( $tone['lum'] < 0.35 && $panel_lum < 0.3 ) {
				$chip = 'light';
			} elseif ( $tone['lum'] > 0.6 && $panel_lum > 0.6 ) {
				$chip = 'dark';
			}
		}

		$font_mode = (string) alc_opt( 'font' );
		$body      = '';
		$heading   = '';
		if ( 'site' === $font_mode && $d['body_font'] ) {
			$body    = $d['body_font'];
			$heading = $d['heading_font'] ? $d['heading_font'] : $body;
		} elseif ( 'site' === $font_mode && $d['heading_font'] ) {
			$heading = $d['heading_font'];
		}

		$uses_bricolage = 'bricolage' === $font_mode || ( 'site' === $font_mode && ( '' === $body || '' === $heading ) );
		$system         = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
		$bricolage      = '"ALC Bricolage", ' . $system;

		if ( 'system' === $font_mode ) {
			$body    = $system;
			$heading = $system;
		}
		$body    = $body ? $body : $bricolage;
		$heading = $heading ? $heading : $bricolage;

		$icon = get_site_icon_url( 64 );

		self::$resolved = array(
			'theme'          => isset( $themes[ $key ] ) ? $key : key( $themes ),
			'theme_label'    => $theme['label'],
			'accent'         => $accent,
			'panel'          => $panel,
			'customized'     => '' !== (string) alc_opt( 'accent' ) || '' !== (string) alc_opt( 'panel_bg' ),
			'font_source'    => $d['font_source'],
			'logo_id'        => $logo_id,
			'logo_url'       => $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '',
			'logo_lum'       => $tone['lum'],
			'logo_chip'      => $chip,
			'icon_url'       => $icon ? $icon : '',
			'font_mode'      => $font_mode,
			'body_font'      => $body,
			'heading_font'   => $heading,
			'font_kind'      => 'site' === $font_mode ? $d['font_kind'] : '',
			'uses_bricolage' => $uses_bricolage,
		);

		return self::$resolved;
	}

	/* --------------------------------------------------------------------
	 * Fonts
	 * ------------------------------------------------------------------ */

	/**
	 * Load the brand fonts on the current screen.
	 *
	 * @param string $head_hook login_head or admin_head.
	 */
	public static function enqueue_fonts( $head_hook ) {
		$r = self::resolved();

		if ( 'site' === $r['font_mode'] ) {
			if ( 'theme_json' === $r['font_kind'] && function_exists( 'wp_print_font_faces' ) ) {
				add_action( $head_hook, array( __CLASS__, 'print_theme_faces' ), 5 );
			} elseif ( 'google' === $r['font_kind'] ) {
				$i = 0;
				foreach ( array_unique( array( $r['body_font'], $r['heading_font'] ) ) as $stack ) {
					$url = self::google_url( $stack );
					if ( $url ) {
						wp_enqueue_style( 'alc-site-font-' . ( ++$i ), $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
					}
				}
			}
		}
	}

	public static function print_theme_faces() {
		if ( self::$faces_printed ) {
			return;
		}
		self::$faces_printed = true;
		wp_print_font_faces();
	}

	/**
	 * Elementor's local copy if it exists, otherwise Google Fonts, the same
	 * place the website already loads it from.
	 *
	 * @param string $stack CSS font stack.
	 * @return string
	 */
	private static function google_url( $stack ) {
		$family = self::first_family( $stack );
		if ( '' === $family || self::is_system_family( $family ) ) {
			return '';
		}

		$uploads = wp_upload_dir( null, false );
		$slug    = strtolower( str_replace( ' ', '', $family ) );
		$local   = trailingslashit( $uploads['basedir'] ) . 'elementor/google-fonts/css/' . $slug . '.css';
		if ( file_exists( $local ) ) {
			return trailingslashit( $uploads['baseurl'] ) . 'elementor/google-fonts/css/' . $slug . '.css';
		}

		return 'https://fonts.googleapis.com/css?family=' . rawurlencode( $family ) . ':400,500,600,700&display=swap';
	}

	/**
	 * @param string $stack Font stack or single family.
	 * @return string
	 */
	public static function first_family( $stack ) {
		$parts = explode( ',', (string) $stack );
		return trim( trim( $parts[0] ), "\"' " );
	}

	/**
	 * @param string $family Family name.
	 * @return bool
	 */
	private static function is_system_family( $family ) {
		$system = array( 'inherit', 'initial', 'default', 'system', 'system stack', 'system-ui', '-apple-system', 'ui-sans-serif', 'sans-serif', 'serif', 'monospace', 'arial', 'helvetica', 'helvetica neue', 'georgia', 'times new roman', 'verdana', 'tahoma', 'trebuchet ms', 'segoe ui', 'roboto', 'alc bricolage' );
		return in_array( strtolower( $family ), $system, true );
	}

	/**
	 * Turn a bare family name into a safe CSS stack.
	 *
	 * @param mixed $family Family name or stack.
	 * @return string
	 */
	private static function family_stack( $family ) {
		$name = self::first_family( is_string( $family ) ? $family : '' );
		$name = preg_replace( '/[^A-Za-z0-9 \-]/', '', $name );
		if ( '' === $name || in_array( strtolower( $name ), array( 'inherit', 'initial', 'default', 'system stack', 'system' ), true ) ) {
			return '';
		}
		return '"' . $name . '", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
	}

	/* --------------------------------------------------------------------
	 * Small utilities
	 * ------------------------------------------------------------------ */

	/**
	 * @param mixed      $arr Array.
	 * @param string|int $key Key.
	 * @return mixed
	 */
	private static function get( $arr, $key ) {
		return ( is_array( $arr ) && isset( $arr[ $key ] ) ) ? $arr[ $key ] : '';
	}

	/**
	 * @param array $arr  Nested array.
	 * @param array $path Keys.
	 * @return mixed
	 */
	private static function dig( $arr, $path ) {
		foreach ( $path as $key ) {
			if ( ! is_array( $arr ) || ! isset( $arr[ $key ] ) ) {
				return '';
			}
			$arr = $arr[ $key ];
		}
		return $arr;
	}
}
