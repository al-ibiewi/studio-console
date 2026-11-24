<?php
/**
 * Branded, split-screen login. Restyles the core screen through hooks and
 * CSS only, so 2FA, CAPTCHA and SSO plugins that hook into the form keep working.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Login {

	public static function init() {
		if ( ! alc_opt( 'login_enabled' ) ) {
			return;
		}

		add_action( 'login_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'login_header', array( __CLASS__, 'panel' ) );
		add_filter( 'login_message', array( __CLASS__, 'form_heading' ), 5 );
		add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'header_text' ) );
		add_filter( 'login_title', array( __CLASS__, 'title' ), 10, 2 );
		add_filter( 'login_body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function assets() {
		ALC_Brand::enqueue_fonts( 'login_head' );
		wp_enqueue_style( 'alc-login', ALC_URL . 'assets/css/login.css', array( 'login' ), ALC_VERSION );
		wp_add_inline_style( 'alc-login', alc_brand_css( 'body.login' ) );
	}

	/**
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		$brand     = ALC_Brand::resolved();
		$classes[] = 'alc-login';
		$classes[] = alc_luminance( $brand['panel'] ) > 0.5 ? 'alc-panel-light' : 'alc-panel-dark';
		if ( $brand['logo_chip'] ) {
			$classes[] = 'alc-logo-chip-' . $brand['logo_chip'];
		}
		if ( alc_media_url( 'login_image_id' ) ) {
			$classes[] = 'alc-has-image';
		}
		return $classes;
	}

	/**
	 * Brand panel, printed right after <body> opens.
	 */
	public static function panel() {
		$name  = alc_client_name();
		$logo  = alc_logo_url();
		$brand = ALC_Brand::resolved();
		$icon  = $brand['icon_url'];
		$image = alc_media_url( 'login_image_id' );
		$style = $image ? ' style="' . esc_attr( "--alc-panel-image:url('" . esc_url( $image ) . "')" ) . '"' : '';
		?>
		<aside class="alc-brand" aria-label="<?php echo esc_attr( $name ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
			<a class="alc-brand__top" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( $logo ) : ?>
					<img class="alc-brand__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>">
				<?php else : ?>
					<?php if ( $icon ) : ?>
						<img class="alc-brand__icon" src="<?php echo esc_url( $icon ); ?>" alt="" width="40" height="40">
					<?php endif; ?>
					<span class="alc-brand__wordmark"><?php echo esc_html( $name ); ?></span>
				<?php endif; ?>
			</a>

			<div class="alc-brand__body">
				<p class="alc-brand__headline"><?php echo esc_html( alc_opt( 'login_headline' ) ); ?></p>
				<?php if ( alc_opt( 'login_subtext' ) ) : ?>
					<p class="alc-brand__sub"><?php echo esc_html( alc_opt( 'login_subtext' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="alc-brand__foot">
				<?php echo alc_studio_credit_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
				<span class="alc-brand__copy">&copy; <?php echo esc_html( wp_date( 'Y' ) . ' ' . $name ); ?></span>
			</div>
		</aside>
		<?php
	}

	/**
	 * Heading above the form that matches the current screen.
	 *
	 * @param string $message Existing login message.
	 * @return string
	 */
	public static function form_heading( $message ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing value.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		$headings = array(
			'login'        => array( __( 'Sign in', 'alibiewi-console' ), __( 'Use your username or email address.', 'alibiewi-console' ) ),
			'lostpassword'     => array( __( 'Reset your password', 'alibiewi-console' ), '' ),
			'retrievepassword' => array( __( 'Reset your password', 'alibiewi-console' ), '' ),
			'rp'           => array( __( 'Choose a new password', 'alibiewi-console' ), __( 'Make it long and unique to this website.', 'alibiewi-console' ) ),
			'resetpass'    => array( __( 'Choose a new password', 'alibiewi-console' ), __( 'Make it long and unique to this website.', 'alibiewi-console' ) ),
			'register'     => array( __( 'Create your account', 'alibiewi-console' ), '' ),
		);

		if ( ! isset( $headings[ $action ] ) ) {
			return $message;
		}

		list( $title, $sub ) = $headings[ $action ];

		$html  = '<div class="alc-form-head">';
		$html .= '<h2 class="alc-form-head__title">' . esc_html( $title ) . '</h2>';
		if ( $sub ) {
			$html .= '<p class="alc-form-head__sub">' . esc_html( $sub ) . '</p>';
		}
		$html .= '</div>';

		return $html . $message;
	}

	public static function header_url() {
		return home_url( '/' );
	}

	public static function header_text() {
		return esc_html( alc_client_name() );
	}

	/**
	 * Drop "WordPress" from the browser tab title.
	 *
	 * @param string $login_title Full title.
	 * @param string $title       Screen title.
	 * @return string
	 */
	public static function title( $login_title, $title ) {
		return sprintf( '%1$s | %2$s', wp_strip_all_tags( $title ), alc_client_name() );
	}
}
