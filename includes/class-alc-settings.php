<?php
/**
 * Settings store: one option, a typed schema, defaults and sanitizing.
 *
 * Labels live in ALC_Admin_Settings so nothing here calls translation
 * functions before init.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Settings {

	const OPTION = 'alc_settings';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Every setting, grouped by the settings tab it lives on.
	 *
	 * @return array
	 */
	public static function schema() {
		return array(
			// Brand.
			'client_name'           => array( 'tab' => 'brand', 'type' => 'text', 'default' => '' ),
			'logo_id'               => array( 'tab' => 'brand', 'type' => 'media', 'default' => 0 ),
			'logo_width'            => array( 'tab' => 'brand', 'type' => 'int', 'default' => 150, 'min' => 60, 'max' => 320 ),
			'theme'                 => array( 'tab' => 'brand', 'type' => 'theme', 'default' => 'studio', 'options' => array( 'studio', 'midnight', 'forest', 'ocean', 'royal', 'plum', 'mint', 'sand', 'cloud', 'blush' ) ),
			'accent'                => array( 'tab' => 'brand', 'type' => 'color', 'default' => '' ),
			'panel_bg'              => array( 'tab' => 'brand', 'type' => 'color', 'default' => '' ),
			'font'                  => array( 'tab' => 'brand', 'type' => 'select', 'default' => 'site', 'options' => array( 'site', 'bricolage', 'system' ) ),
			'admin_chrome'          => array( 'tab' => 'brand', 'type' => 'bool', 'default' => 1 ),
			'studio_credit'         => array( 'tab' => 'brand', 'type' => 'bool', 'default' => 1 ),
			'admin_footer'          => array( 'tab' => 'brand', 'type' => 'bool', 'default' => 1 ),

			// Login.
			'login_enabled'         => array( 'tab' => 'login', 'type' => 'bool', 'default' => 1 ),
			'login_headline'        => array( 'tab' => 'login', 'type' => 'text', 'default' => 'Welcome back.' ),
			'login_subtext'         => array( 'tab' => 'login', 'type' => 'textarea', 'default' => 'Sign in to update your pages, check new enquiries and keep your website running smoothly.' ),
			'login_image_id'        => array( 'tab' => 'login', 'type' => 'media', 'default' => 0 ),
			'login_limit'           => array( 'tab' => 'login', 'type' => 'bool', 'default' => 1 ),
			'login_max_attempts'    => array( 'tab' => 'login', 'type' => 'int', 'default' => 5, 'min' => 3, 'max' => 20 ),
			'login_lockout_minutes' => array( 'tab' => 'login', 'type' => 'int', 'default' => 15, 'min' => 5, 'max' => 1440 ),
			'login_generic_errors'  => array( 'tab' => 'login', 'type' => 'bool', 'default' => 1 ),
			'trust_cloudflare'      => array( 'tab' => 'login', 'type' => 'bool', 'default' => 0 ),

			// Dashboard.
			'dashboard_enabled'     => array( 'tab' => 'dashboard', 'type' => 'bool', 'default' => 1 ),
			'dashboard_message'     => array( 'tab' => 'dashboard', 'type' => 'text', 'default' => 'Here is what is happening on your website today.' ),
			'show_woo'              => array( 'tab' => 'dashboard', 'type' => 'bool', 'default' => 1 ),
			'show_forms'            => array( 'tab' => 'dashboard', 'type' => 'bool', 'default' => 1 ),
			'show_health'           => array( 'tab' => 'dashboard', 'type' => 'bool', 'default' => 1 ),
			'tutorials'             => array( 'tab' => 'dashboard', 'type' => 'textarea', 'default' => '' ),

			// Client mode.
			'client_mode'           => array( 'tab' => 'client', 'type' => 'bool', 'default' => 0 ),
			'client_appearance'     => array( 'tab' => 'client', 'type' => 'bool', 'default' => 1 ),
			'client_users'          => array( 'tab' => 'client', 'type' => 'bool', 'default' => 1 ),
			'client_store'          => array( 'tab' => 'client', 'type' => 'bool', 'default' => 1 ),
			'client_forms'          => array( 'tab' => 'client', 'type' => 'bool', 'default' => 1 ),
			'client_hide_notices'   => array( 'tab' => 'client', 'type' => 'bool', 'default' => 1 ),
			'client_hide_help'      => array( 'tab' => 'client', 'type' => 'bool', 'default' => 1 ),
			'client_hidden_menus'   => array( 'tab' => 'client', 'type' => 'list', 'default' => array( 'tools.php' ) ),

			// AI assistant.
			'ai_enabled'            => array( 'tab' => 'ai', 'type' => 'bool', 'default' => 0 ),
			'ai_api_key'            => array( 'tab' => 'ai', 'type' => 'secret', 'default' => '' ),
			'ai_model'              => array( 'tab' => 'ai', 'type' => 'select', 'default' => 'claude-haiku-5-5', 'options' => array( 'claude-haiku-5-5', 'claude-sonnet-5-5' ) ),
			'ai_frontend'           => array( 'tab' => 'ai', 'type' => 'bool', 'default' => 1 ),
			'ai_position'           => array( 'tab' => 'ai', 'type' => 'select', 'default' => 'right', 'options' => array( 'right', 'left' ) ),
			'ai_avoid'              => array( 'tab' => 'ai', 'type' => 'bool', 'default' => 1 ),
			'ai_offset'             => array( 'tab' => 'ai', 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 400 ),
			'ai_whatsapp'           => array( 'tab' => 'ai', 'type' => 'bool', 'default' => 1 ),
			'ai_contact_whatsapp'   => array( 'tab' => 'ai', 'type' => 'phone', 'default' => '' ),
			'ai_contact_phone'      => array( 'tab' => 'ai', 'type' => 'text', 'default' => '' ),
			'ai_contact_email'      => array( 'tab' => 'ai', 'type' => 'email', 'default' => '' ),
			'ai_contact_url'        => array( 'tab' => 'ai', 'type' => 'url', 'default' => '' ),
			'ai_name'               => array( 'tab' => 'ai', 'type' => 'text', 'default' => '' ),
			'ai_welcome'            => array( 'tab' => 'ai', 'type' => 'textarea', 'default' => 'Hi! Ask me anything about this website and I will answer from its pages.' ),
			'ai_suggestions'        => array( 'tab' => 'ai', 'type' => 'textarea', 'default' => '' ),
			'ai_post_types'         => array( 'tab' => 'ai', 'type' => 'types', 'default' => array( 'page', 'post', 'product' ) ),
			'ai_rate_limit'         => array( 'tab' => 'ai', 'type' => 'int', 'default' => 20, 'min' => 3, 'max' => 200 ),
			'ai_daily_cap'          => array( 'tab' => 'ai', 'type' => 'int', 'default' => 300, 'min' => 10, 'max' => 10000 ),
			'ai_log'                => array( 'tab' => 'ai', 'type' => 'bool', 'default' => 1 ),

			// Support.
			'support_whatsapp'      => array( 'tab' => 'support', 'type' => 'phone', 'default' => '' ),
			'support_email'         => array( 'tab' => 'support', 'type' => 'email', 'default' => '' ),
			'support_url'           => array( 'tab' => 'support', 'type' => 'url', 'default' => '' ),
			'support_hours'         => array( 'tab' => 'support', 'type' => 'text', 'default' => 'Monday to Friday, 9am to 5pm WAT' ),
		);
	}

	/**
	 * @return array
	 */
	public static function defaults() {
		$out = array();
		foreach ( self::schema() as $key => $field ) {
			$out[ $key ] = $field['default'];
		}
		return $out;
	}

	/**
	 * Saved settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitize only the fields on the given tab and keep the rest as saved,
	 * so saving one tab never wipes another. Pass $tab = null to sanitize
	 * every field (used by import).
	 *
	 * @param array       $input Raw input.
	 * @param string|null $tab   Tab key or null for all.
	 * @return array
	 */
	public static function sanitize( $input, $tab = null ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::all();

		foreach ( self::schema() as $key => $field ) {
			if ( null !== $tab && $field['tab'] !== $tab ) {
				continue;
			}
			// Whole-array saves (import) only touch keys they actually contain.
			if ( null === $tab && ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$raw = array_key_exists( $key, $input ) ? $input[ $key ] : null;

			// Secrets: blank keeps the saved value, the clear box removes it.
			if ( 'secret' === $field['type'] ) {
				if ( ! empty( $input[ $key . '_clear' ] ) ) {
					$out[ $key ] = '';
				} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
					$out[ $key ] = preg_replace( '/[^A-Za-z0-9_\-]/', '', trim( $raw ) );
				}
				continue;
			}

			$out[ $key ] = self::sanitize_field( $raw, $field );
		}

		return $out;
	}

	/**
	 * @param mixed $raw   Raw value.
	 * @param array $field Schema entry.
	 * @return mixed
	 */
	private static function sanitize_field( $raw, $field ) {
		switch ( $field['type'] ) {
			case 'bool':
				return empty( $raw ) ? 0 : 1;

			case 'int':
				if ( null === $raw || '' === $raw ) {
					return $field['default'];
				}
				return max( $field['min'], min( $field['max'], (int) $raw ) );

			case 'media':
				$id = absint( $raw );
				return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;

			case 'color':
				if ( null === $raw || '' === trim( (string) $raw ) ) {
					return '';
				}
				$color = sanitize_hex_color( is_string( $raw ) ? trim( $raw ) : '' );
				return $color ? strtoupper( $color ) : $field['default'];

			case 'select':
				return in_array( $raw, $field['options'], true ) ? $raw : $field['default'];

			case 'types':
				if ( ! is_array( $raw ) ) {
					return array();
				}
				return array_values( array_unique( array_filter( array_map( 'sanitize_key', $raw ) ) ) );

			case 'list':
				if ( ! is_array( $raw ) ) {
					return array();
				}
				$items = array_map( 'sanitize_text_field', array_map( 'strval', $raw ) );
				return array_slice( array_values( array_unique( array_filter( $items ) ) ), 0, 100 );

			case 'theme':
				$keys = array_merge( $field['options'], array_keys( (array) apply_filters( 'alc_color_themes', array() ) ) );
				return in_array( $raw, $keys, true ) ? $raw : $field['default'];

			case 'textarea':
				return null === $raw ? '' : sanitize_textarea_field( (string) $raw );

			case 'email':
				return null === $raw ? '' : sanitize_email( (string) $raw );

			case 'url':
				return null === $raw ? '' : esc_url_raw( trim( (string) $raw ) );

			case 'phone':
				return null === $raw ? '' : preg_replace( '/\D+/', '', (string) $raw );

			case 'text':
			default:
				return null === $raw ? '' : sanitize_text_field( (string) $raw );
		}
	}
}

add_action( 'update_option_' . ALC_Settings::OPTION, array( 'ALC_Settings', 'flush' ) );
add_action( 'add_option_' . ALC_Settings::OPTION, array( 'ALC_Settings', 'flush' ) );
