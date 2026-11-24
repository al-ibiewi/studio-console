<?php
/**
 * Brands the wp-admin sidebar and top bar for every screen.
 *
 * Bar height (32px) and sidebar width (160px) stay at core sizes on purpose:
 * the block editor, WooCommerce and many plugins position themselves against
 * those numbers, so changing them breaks layouts.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Admin_Theme {

	const SCHEME = 'alc_studio';

	public static function init() {
		if ( ! alc_opt( 'admin_chrome' ) ) {
			return;
		}

		add_action( 'admin_init', array( __CLASS__, 'register_scheme' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'hide_scheme_picker' ) );
		add_filter( 'get_user_option_admin_color', array( __CLASS__, 'force_scheme' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 5 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar_brand' ), 0 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar_help' ), 95 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar_cleanup' ), 999 );
	}

	/**
	 * A color scheme so core's icon painter colors plugin SVG menu icons in brand.
	 */
	public static function register_scheme() {
		$b      = ALC_Brand::resolved();
		$ink    = alc_ink_for( $b['panel'] );
		$muted  = alc_mix( $b['panel'], $ink, 0.62 );
		$active = alc_ink_for( $b['accent'] );

		wp_admin_css_color(
			self::SCHEME,
			__( 'Studio', 'alibiewi-console' ),
			false,
			array( $b['panel'], $muted, $b['accent'], $active ),
			array(
				'base'    => $muted,
				'focus'   => $ink,
				'current' => $active,
			)
		);
	}

	public static function hide_scheme_picker() {
		remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
	}

	/**
	 * @param mixed $value Saved scheme.
	 * @return string
	 */
	public static function force_scheme( $value ) {
		return is_admin() ? self::SCHEME : $value;
	}

	public static function assets() {
		ALC_Brand::enqueue_fonts( 'admin_head' );
		wp_enqueue_style( 'alc-admin-theme', ALC_URL . 'assets/css/admin-theme.css', array(), ALC_VERSION );
		wp_add_inline_style( 'alc-admin-theme', alc_brand_css( 'body.alc-chrome' ) . alc_editor_theme_css() );
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$b     = ALC_Brand::resolved();
		$tone  = alc_luminance( $b['panel'] ) > 0.5 ? 'alc-side-light' : 'alc-side-dark';
		return $classes . ' alc-chrome ' . $tone;
	}

	/**
	 * Client mark first in the top bar, linking to the dashboard.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function bar_brand( $bar ) {
		if ( ! is_admin() ) {
			return;
		}
		$b = ALC_Brand::resolved();
		// Core already shows the site icon next to the site name.
		if ( has_site_icon() ) {
			return;
		}
		// A wide logo only works on the white bar if it is dark enough to see.
		$mark = ( $b['logo_url'] && null !== $b['logo_lum'] && $b['logo_lum'] < 0.6 ) ? $b['logo_url'] : '';
		if ( ! $mark ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'alc-brand',
				'title' => '<img class="alc-ab-mark" src="' . esc_url( $mark ) . '" alt="' . esc_attr( alc_client_name() ) . '">',
				'href'  => alc_opt( 'dashboard_enabled' ) ? ALC_Dashboard::url() : admin_url(),
				'meta'  => array( 'title' => alc_client_name() ),
			)
		);
	}

	/**
	 * "Get help" on the right of the top bar when support details exist.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function bar_help( $bar ) {
		if ( ! is_admin() ) {
			return;
		}
		$url = '';
		if ( alc_opt( 'support_whatsapp' ) ) {
			/* translators: 1: site name, 2: site URL. */
			$msg = sprintf( __( 'Hi, I need help with %1$s (%2$s).', 'alibiewi-console' ), alc_client_name(), home_url( '/' ) );
			$url = 'https://wa.me/' . alc_opt( 'support_whatsapp' ) . '?text=' . rawurlencode( $msg );
		} elseif ( alc_opt( 'support_url' ) ) {
			$url = alc_opt( 'support_url' );
		} elseif ( alc_opt( 'support_email' ) ) {
			$url = 'mailto:' . alc_opt( 'support_email' );
		}
		if ( ! $url ) {
			return;
		}

		$bar->add_node(
			array(
				'id'     => 'alc-help',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-sos" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Get help', 'alibiewi-console' ) . '</span>',
				'href'   => $url,
				'meta'   => array(
					'target' => '_blank',
					'rel'    => 'noopener',
				),
			)
		);
	}

	/**
	 * Drop the WordPress logo menu in wp-admin.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function bar_cleanup( $bar ) {
		if ( is_admin() ) {
			$bar->remove_node( 'wp-logo' );
		}
	}
}
