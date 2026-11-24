<?php
/**
 * White-label settings screen under Settings, with JSON export and import
 * so a new client site can be set up in seconds.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Admin_Settings {

	const PAGE  = 'alc-settings';
	const GROUP = 'alc_settings_group';

	/** @var bool Skip tab-aware sanitizing for whole-array saves (import, reset). */
	private static $bypass = false;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_alc_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_alc_import', array( __CLASS__, 'import' ) );
		add_action( 'admin_post_alc_reset', array( __CLASS__, 'reset' ) );
		add_action( 'admin_post_alc_rescan', array( __CLASS__, 'rescan' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ALC_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function url( $tab = '' ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
	}

	public static function menu() {
		add_options_page(
			__( 'Studio Console', 'alibiewi-console' ),
			__( 'Studio Console', 'alibiewi-console' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			ALC_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => ALC_Settings::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( self::$bypass ) {
			return $input;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php already verified the nonce.
		$tab = isset( $_POST['alc_tab'] ) ? sanitize_key( wp_unslash( $_POST['alc_tab'] ) ) : '';

		if ( ! array_key_exists( $tab, self::tabs() ) || 'tools' === $tab ) {
			// Programmatic add_option/update_option: trust only known keys.
			return is_array( $input ) ? ALC_Settings::sanitize( $input, null ) : ALC_Settings::all();
		}

		return ALC_Settings::sanitize( $input, $tab );
	}

	/**
	 * @return array
	 */
	public static function tabs() {
		return array(
			'brand'     => __( 'Brand', 'alibiewi-console' ),
			'login'     => __( 'Login', 'alibiewi-console' ),
			'dashboard' => __( 'Dashboard', 'alibiewi-console' ),
			'client'    => __( 'Client mode', 'alibiewi-console' ),
			'ai'        => __( 'AI assistant', 'alibiewi-console' ),
			'support'   => __( 'Support', 'alibiewi-console' ),
			'tools'     => __( 'Import and export', 'alibiewi-console' ),
		);
	}

	/**
	 * Labels and help text for each setting.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'client_name'           => array( __( 'Brand name', 'alibiewi-console' ), __( 'Shown on the login screen and dashboard. Leave empty to use the site title.', 'alibiewi-console' ) ),
			'logo_id'               => array( __( 'Logo', 'alibiewi-console' ), __( 'Leave empty to use the website logo. Upload one here only to override it.', 'alibiewi-console' ) ),
			'logo_width'            => array( __( 'Logo width', 'alibiewi-console' ), __( 'In pixels, on the login screen.', 'alibiewi-console' ) ),
			'theme'                 => array( __( 'Color theme', 'alibiewi-console' ), __( 'Sets the login panel, sidebar, dashboard header and buttons in one click.', 'alibiewi-console' ) ),
			'accent'                => array( __( 'Custom accent', 'alibiewi-console' ), __( 'Optional. Buttons, the active menu item and highlights. Leave empty to use the theme\'s accent.', 'alibiewi-console' ) ),
			'panel_bg'              => array( __( 'Custom panel color', 'alibiewi-console' ), __( 'Optional. Login panel, sidebar and dashboard header. Leave empty to use the theme\'s panel.', 'alibiewi-console' ) ),
			'font'                  => array(
				__( 'Typeface', 'alibiewi-console' ),
				__( 'Match the website loads the fonts the site already uses. Bricolage Grotesque is bundled with the plugin.', 'alibiewi-console' ),
				array(
					'site'      => __( 'Match the website', 'alibiewi-console' ),
					'bricolage' => __( 'Bricolage Grotesque', 'alibiewi-console' ),
					'system'    => __( 'System font', 'alibiewi-console' ),
				),
			),
			'admin_chrome'          => array( __( 'Branded admin', 'alibiewi-console' ), __( 'Style the sidebar, top bar and main buttons across wp-admin in the brand colors.', 'alibiewi-console' ) ),
			'studio_credit'         => array( __( 'Studio credit', 'alibiewi-console' ), __( 'Show "Crafted by Al-Ibiewi Studio" on the login screen and dashboard.', 'alibiewi-console' ) ),
			'admin_footer'          => array( __( 'Admin footer', 'alibiewi-console' ), __( 'Replace "Thank you for creating with WordPress" in the admin footer.', 'alibiewi-console' ) ),

			'login_enabled'         => array( __( 'Branded login', 'alibiewi-console' ), __( 'Use the split-screen login. Turn off to return to the default WordPress screen.', 'alibiewi-console' ) ),
			'login_headline'        => array( __( 'Headline', 'alibiewi-console' ), __( 'Large text on the brand panel. Keep it short.', 'alibiewi-console' ) ),
			'login_subtext'         => array( __( 'Supporting text', 'alibiewi-console' ), '' ),
			'login_image_id'        => array( __( 'Panel image', 'alibiewi-console' ), __( 'Optional. The panel color is laid over it so the text stays readable.', 'alibiewi-console' ) ),
			'login_limit'           => array( __( 'Limit login attempts', 'alibiewi-console' ), __( 'Pause sign-in for an IP address after repeated failures. Turn off if Wordfence or a similar plugin already does this.', 'alibiewi-console' ) ),
			'login_max_attempts'    => array( __( 'Attempts before pause', 'alibiewi-console' ), '' ),
			'login_lockout_minutes' => array( __( 'Pause length', 'alibiewi-console' ), __( 'In minutes.', 'alibiewi-console' ) ),
			'login_generic_errors'  => array( __( 'Private error messages', 'alibiewi-console' ), __( 'Show one message for a wrong username or password, so the form never confirms which usernames exist.', 'alibiewi-console' ) ),
			'trust_cloudflare'      => array( __( 'Behind Cloudflare', 'alibiewi-console' ), __( 'Read the visitor IP from Cloudflare so lockouts hit the right person. Only turn on when the site is proxied through Cloudflare.', 'alibiewi-console' ) ),

			'dashboard_enabled'     => array( __( 'Client dashboard', 'alibiewi-console' ), __( 'Replace the default WordPress dashboard. Admins can still open the classic one from the footer.', 'alibiewi-console' ) ),
			'dashboard_message'     => array( __( 'Welcome line', 'alibiewi-console' ), __( 'Shown under the greeting.', 'alibiewi-console' ) ),
			'show_woo'              => array( __( 'Store widgets', 'alibiewi-console' ), __( 'Sales, orders to fulfil and recent orders when WooCommerce is active.', 'alibiewi-console' ) ),
			'show_forms'            => array( __( 'Form entries', 'alibiewi-console' ), __( 'Latest enquiries when Fluent Forms is active.', 'alibiewi-console' ) ),
			'show_health'           => array( __( 'Website health', 'alibiewi-console' ), __( 'Updates, search visibility, HTTPS and Site Health, for administrators only.', 'alibiewi-console' ) ),
			'tutorials'             => array( __( 'How-to guides', 'alibiewi-console' ), __( 'One per line as Title | URL. Short Loom or YouTube walkthroughs work well.', 'alibiewi-console' ) ),

			'client_mode'           => array( __( 'Client mode', 'alibiewi-console' ), __( 'Creates the Site Owner role: everything a client needs to run the site, without plugins, themes, updates or settings.', 'alibiewi-console' ) ),
			'client_appearance'     => array( __( 'Menus and site design', 'alibiewi-console' ), __( 'Menus, widgets, the Customizer and the Site Editor.', 'alibiewi-console' ) ),
			'client_users'          => array( __( 'Manage users', 'alibiewi-console' ), __( 'Add and edit users below administrator. Administrators stay out of reach.', 'alibiewi-console' ) ),
			'client_store'          => array( __( 'Run the store', 'alibiewi-console' ), __( 'Orders, products, coupons and WooCommerce reports, like a Shop Manager.', 'alibiewi-console' ) ),
			'client_forms'          => array( __( 'Form entries', 'alibiewi-console' ), __( 'Read and manage Fluent Forms entries.', 'alibiewi-console' ) ),
			'client_hide_notices'   => array( __( 'Hide admin notices', 'alibiewi-console' ), __( 'Removes plugin nags and upsell banners for Site Owners.', 'alibiewi-console' ) ),
			'client_hide_help'      => array( __( 'Hide Help tabs', 'alibiewi-console' ), __( 'Removes the WordPress Help tab at the top of screens.', 'alibiewi-console' ) ),
			'client_hidden_menus'   => array( __( 'Hide menus', 'alibiewi-console' ), __( 'Extra menus to hide from Site Owners. Hidden pages are blocked even if the address is typed in.', 'alibiewi-console' ) ),
			'ai_enabled'            => array( __( 'AI assistant', 'alibiewi-console' ), __( 'Answers visitor questions from this website\'s own pages using Claude, with links to the pages it used.', 'alibiewi-console' ) ),
			'ai_api_key'            => array( __( 'Claude API key', 'alibiewi-console' ), __( 'From console.anthropic.com. Stored on this site only and never shown again or exported. You can also define ALC_ANTHROPIC_API_KEY in wp-config.php.', 'alibiewi-console' ) ),
			'ai_model'              => array(
				__( 'Model', 'alibiewi-console' ),
				__( 'Haiku is fast and very low cost, ideal for a website assistant. Sonnet writes richer answers at a higher cost.', 'alibiewi-console' ),
				array(
					'claude-haiku-5-5'  => __( 'Claude Haiku 5.5 (recommended)', 'alibiewi-console' ),
					'claude-sonnet-5-5' => __( 'Claude Sonnet 5.5', 'alibiewi-console' ),
				),
			),
			'ai_frontend'           => array( __( 'Show to visitors', 'alibiewi-console' ), __( 'A chat bubble on every page of the website. Off: only the [alc_assistant] shortcode and the test box below.', 'alibiewi-console' ) ),
			'ai_position'           => array(
				__( 'Bubble position', 'alibiewi-console' ),
				'',
				array(
					'right' => __( 'Bottom right', 'alibiewi-console' ),
					'left'  => __( 'Bottom left', 'alibiewi-console' ),
				),
			),
			'ai_avoid'              => array( __( 'Avoid other floating buttons', 'alibiewi-console' ), __( 'Moves the bubble above WhatsApp buttons, cookie bars and back-to-top buttons in the same corner.', 'alibiewi-console' ) ),
			'ai_offset'             => array( __( 'Extra space from the bottom', 'alibiewi-console' ), __( 'In pixels. Use it to place the bubble exactly, on top of what auto-avoid does.', 'alibiewi-console' ) ),
			'ai_whatsapp'           => array( __( 'WhatsApp handoff', 'alibiewi-console' ), __( 'Offers "Chat with us on WhatsApp" in the assistant, and after answers it could not find, using the business WhatsApp number below.', 'alibiewi-console' ) ),
			'ai_contact_whatsapp'   => array( __( 'Business WhatsApp', 'alibiewi-console' ), __( 'The business\'s own number for customers, in international format without the plus. Not your studio number: that lives on the Support tab for clients.', 'alibiewi-console' ) ),
			'ai_contact_phone'      => array( __( 'Business phone', 'alibiewi-console' ), __( 'Optional. Shared when a customer needs a person.', 'alibiewi-console' ) ),
			'ai_contact_email'      => array( __( 'Business email', 'alibiewi-console' ), __( 'Optional. Shared when a customer needs a person.', 'alibiewi-console' ) ),
			'ai_contact_url'        => array( __( 'Contact page', 'alibiewi-console' ), __( 'Leave empty to use the site\'s page called Contact, when there is one.', 'alibiewi-console' ) ),
			'ai_name'               => array( __( 'Assistant name', 'alibiewi-console' ), __( 'Leave empty for "Ask" plus the brand name.', 'alibiewi-console' ) ),
			'ai_welcome'            => array( __( 'Welcome message', 'alibiewi-console' ), '' ),
			'ai_suggestions'        => array( __( 'Suggested questions', 'alibiewi-console' ), __( 'Up to four, one per line. Shown as tap-to-ask buttons. Leave empty and the assistant suggests questions based on the site\'s pages.', 'alibiewi-console' ) ),
			'ai_post_types'         => array( __( 'Content to learn from', 'alibiewi-console' ), __( 'Only published, public, non-password-protected content is used.', 'alibiewi-console' ) ),
			'ai_rate_limit'         => array( __( 'Questions per visitor per hour', 'alibiewi-console' ), '' ),
			'ai_daily_cap'          => array( __( 'Daily limit for the whole site', 'alibiewi-console' ), __( 'Keeps API costs predictable. With Haiku, 300 questions a day costs well under a dollar.', 'alibiewi-console' ) ),
			'ai_log'                => array( __( 'Keep recent questions', 'alibiewi-console' ), __( 'Saves the last 100 questions, without names or IPs, so you can spot what the website does not answer yet.', 'alibiewi-console' ) ),
			'support_whatsapp'      => array( __( 'WhatsApp number', 'alibiewi-console' ), __( 'International format without the plus, for example 2348012345678.', 'alibiewi-console' ) ),
			'support_email'         => array( __( 'Support email', 'alibiewi-console' ), '' ),
			'support_url'           => array( __( 'Support link', 'alibiewi-console' ), __( 'A booking page, help desk or care plan page.', 'alibiewi-console' ) ),
			'support_hours'         => array( __( 'Support hours', 'alibiewi-console' ), '' ),
		);
	}

	/**
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		ALC_Brand::enqueue_fonts( 'admin_head' );
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'alc-settings', ALC_URL . 'assets/css/settings.css', array(), ALC_VERSION );
		wp_add_inline_style( 'alc-settings', alc_brand_css( '.alc-settings' ) );
		wp_enqueue_script( 'alc-settings', ALC_URL . 'assets/js/settings.js', array( 'jquery', 'wp-color-picker' ), ALC_VERSION, true );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen routing.
		if ( isset( $_GET['tab'] ) && 'ai' === $_GET['tab'] && ALC_AI::is_ready() ) {
			ALC_AI::register_assets();
			wp_enqueue_style( 'alc-assistant' );
			wp_enqueue_script( 'alc-assistant' );
		}
		wp_localize_script(
			'alc-settings',
			'alcSettings',
			array(
				'chooseTitle'  => __( 'Choose an image', 'alibiewi-console' ),
				'chooseButton' => __( 'Use this image', 'alibiewi-console' ),
			)
		);
	}

	/**
	 * @param array $links Plugin row links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'alibiewi-console' ) . '</a>' );
		return $links;
	}

	/* --------------------------------------------------------------------
	 * Screen
	 * ------------------------------------------------------------------ */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'brand';
		$tab = array_key_exists( $tab, $tabs ) ? $tab : 'brand';
		?>
		<div class="wrap alc-settings">
			<header class="alc-set-head">
				<div>
					<h1 class="alc-set-head__title"><?php esc_html_e( 'Studio Console', 'alibiewi-console' ); ?></h1>
					<p class="alc-set-head__sub"><?php esc_html_e( 'Brand the login screen and dashboard for this website.', 'alibiewi-console' ); ?></p>
				</div>
				<div class="alc-set-head__actions">
					<a class="alc-btn alc-btn--ghost" href="<?php echo esc_url( wp_login_url() ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'Preview login', 'alibiewi-console' ); ?></a>
					<?php if ( alc_opt( 'dashboard_enabled' ) ) : ?>
						<a class="alc-btn alc-btn--ghost" href="<?php echo esc_url( ALC_Dashboard::url() ); ?>"><span class="dashicons dashicons-dashboard" aria-hidden="true"></span><?php esc_html_e( 'Open dashboard', 'alibiewi-console' ); ?></a>
					<?php endif; ?>
				</div>
			</header>

			<hr class="wp-header-end">
			<?php self::notice(); ?>

			<nav class="alc-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'alibiewi-console' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="alc-tab<?php echo $key === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'tools' === $tab ) {
				self::render_tools();
			} else {
				self::render_form( $tab );
			}
			?>
		</div>
		<?php
	}

	/**
	 * @param string $tab Tab key.
	 */
	private static function render_form( $tab ) {
		$labels = self::fields();
		?>
		<form class="alc-set-card" method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
			<?php settings_fields( self::GROUP ); ?>
			<input type="hidden" name="alc_tab" value="<?php echo esc_attr( $tab ); ?>">

			<?php
			if ( 'brand' === $tab ) {
				self::render_detected();
			}
			if ( 'client' === $tab ) {
				ALC_Client_Mode::render_summary();
			}
			if ( 'ai' === $tab ) {
				self::render_ai_status();
			}
			?>
			<div class="alc-fields">
				<?php
				foreach ( ALC_Settings::schema() as $key => $field ) {
					if ( $field['tab'] !== $tab ) {
						continue;
					}
					self::render_field( $key, $field, isset( $labels[ $key ] ) ? $labels[ $key ] : array( $key, '' ) );
				}
				?>
			</div>

			<div class="alc-set-card__foot">
				<?php submit_button( __( 'Save changes', 'alibiewi-console' ), 'primary alc-save', 'submit', false ); ?>
			</div>
		</form>
		<?php
		if ( 'client' === $tab ) {
			ALC_Client_Mode::render_users_card();
		}
		if ( 'ai' === $tab ) {
			self::render_ai_tester();
		}
	}

	private static function render_ai_status() {
		$stats = ALC_AI_Index::stats();
		$usage = get_option( 'alc_ai_usage', array() );
		$error = get_option( 'alc_ai_last_error' );
		$ready = ALC_AI::is_ready();
		$month = ( is_array( $usage ) && isset( $usage['month'] ) && gmdate( 'Y-m' ) === $usage['month'] ) ? $usage : array(
			'questions' => 0,
			'input'     => 0,
			'output'    => 0,
		);
		?>
		<div class="alc-ai-status">
			<div class="alc-ai-status__item">
				<span class="alc-ai-status__label"><?php esc_html_e( 'Status', 'alibiewi-console' ); ?></span>
				<span class="alc-ai-status__value">
					<?php
					if ( ! alc_opt( 'ai_enabled' ) ) {
						esc_html_e( 'Off', 'alibiewi-console' );
					} elseif ( ! ALC_AI::api_key() ) {
						esc_html_e( 'Needs an API key', 'alibiewi-console' );
					} elseif ( ALC_AI::is_live() ) {
						esc_html_e( 'Live for visitors', 'alibiewi-console' );
					} else {
						esc_html_e( 'Ready, hidden from visitors', 'alibiewi-console' );
					}
					?>
				</span>
			</div>
			<div class="alc-ai-status__item">
				<span class="alc-ai-status__label"><?php esc_html_e( 'Learned from', 'alibiewi-console' ); ?></span>
				<span class="alc-ai-status__value">
					<?php
					/* translators: 1: pages, 2: passages. */
					echo esc_html( sprintf( _n( '%1$d page, %2$d passages', '%1$d pages, %2$d passages', $stats['posts'], 'alibiewi-console' ), $stats['posts'], $stats['chunks'] ) );
					?>
				</span>
				<?php if ( $stats['built'] ) : ?>
					<span class="alc-ai-status__note">
						<?php
						/* translators: %s: time ago. */
						echo esc_html( sprintf( __( 'Full rebuild %s ago, updates itself when you save content', 'alibiewi-console' ), human_time_diff( $stats['built'] ) ) );
						?>
					</span>
				<?php endif; ?>
			</div>
			<div class="alc-ai-status__item">
				<span class="alc-ai-status__label"><?php esc_html_e( 'This month', 'alibiewi-console' ); ?></span>
				<span class="alc-ai-status__value">
					<?php
					/* translators: %d: questions. */
					echo esc_html( sprintf( _n( '%d question', '%d questions', (int) $month['questions'], 'alibiewi-console' ), (int) $month['questions'] ) );
					?>
				</span>
				<span class="alc-ai-status__note">
					<?php
					/* translators: 1: input tokens, 2: output tokens. */
					echo esc_html( sprintf( __( '%1$s tokens in, %2$s out', 'alibiewi-console' ), number_format_i18n( (int) $month['input'] ), number_format_i18n( (int) $month['output'] ) ) );
					?>
				</span>
			</div>
			<div class="alc-ai-status__actions">
				<?php if ( alc_opt( 'ai_enabled' ) ) : ?>
					<a class="button alc-detect__rescan" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=alc_ai_check' ), 'alc_ai_check' ) ); ?>"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Check connection', 'alibiewi-console' ); ?></a>
					<a class="button alc-detect__rescan" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=alc_ai_rebuild' ), 'alc_ai_rebuild' ) ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Relearn the website', 'alibiewi-console' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( is_array( $error ) && ! empty( $error['message'] ) && $ready ) : ?>
			<p class="alc-ai-error">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php
				/* translators: 1: time ago, 2: error message. */
				echo esc_html( sprintf( __( 'Last API error %1$s ago: %2$s', 'alibiewi-console' ), human_time_diff( (int) $error['time'] ), $error['message'] ) );
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	private static function render_ai_tester() {
		$log = ALC_AI::recent_log( 15 );
		?>
		<div class="alc-ai-grid">
			<section class="alc-set-card alc-ai-test">
				<h2 class="alc-set-card__title"><?php esc_html_e( 'Try it', 'alibiewi-console' ); ?></h2>
				<?php if ( ALC_AI::is_ready() ) : ?>
					<p class="alc-set-card__text"><?php esc_html_e( 'This is exactly what visitors see. Your own questions here are not rate limited.', 'alibiewi-console' ); ?></p>
					<div class="alc-ai alc-ai--inline" data-alc-assistant="inline"></div>
				<?php else : ?>
					<p class="alc-set-card__text"><?php esc_html_e( 'Turn the assistant on, add your API key and save to try it here.', 'alibiewi-console' ); ?></p>
				<?php endif; ?>
			</section>

			<section class="alc-set-card alc-ai-log">
				<div class="alc-ai-log__head">
					<h2 class="alc-set-card__title"><?php esc_html_e( 'What people asked', 'alibiewi-console' ); ?></h2>
					<?php if ( $log ) : ?>
						<a class="alc-ai-log__clear" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=alc_ai_clear_log' ), 'alc_ai_clear_log' ) ); ?>"><?php esc_html_e( 'Clear', 'alibiewi-console' ); ?></a>
					<?php endif; ?>
				</div>
				<p class="alc-set-card__text"><?php esc_html_e( 'Questions marked "Not on the site" show content worth adding.', 'alibiewi-console' ); ?></p>
				<?php if ( $log ) : ?>
					<ul class="alc-ai-log__list">
						<?php foreach ( $log as $row ) : ?>
							<li>
								<span class="alc-ai-log__q"><?php echo esc_html( $row['q'] ); ?></span>
								<span class="alc-ai-log__meta">
									<?php if ( empty( $row['f'] ) ) : ?>
										<span class="alc-pill-mini is-gap"><?php esc_html_e( 'Not on the site', 'alibiewi-console' ); ?></span>
									<?php endif; ?>
									<?php echo esc_html( human_time_diff( (int) $row['t'] ) ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="alc-owners__note"><?php esc_html_e( 'No questions yet.', 'alibiewi-console' ); ?></p>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * @param string $key   Setting key.
	 * @param array  $field Schema entry.
	 * @param array  $label [ label, description, choices? ].
	 */
	private static function render_field( $key, $field, $label ) {
		$id    = 'alc_' . $key;
		$name  = ALC_Settings::OPTION . '[' . $key . ']';
		$value = alc_opt( $key );
		$desc  = isset( $label[1] ) ? $label[1] : '';
		$is_bool = in_array( $field['type'], array( 'bool', 'theme', 'list', 'types' ), true );
		?>
		<div class="alc-field alc-field--<?php echo esc_attr( $field['type'] ); ?>">
			<div class="alc-field__label">
				<?php if ( $is_bool ) : ?>
					<span class="alc-field__name" id="<?php echo esc_attr( $id ); ?>_label"><?php echo esc_html( $label[0] ); ?></span>
				<?php else : ?>
					<label class="alc-field__name" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label[0] ); ?></label>
				<?php endif; ?>
				<?php if ( $desc ) : ?>
					<p class="alc-field__desc" id="<?php echo esc_attr( $id ); ?>_desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</div>
			<div class="alc-field__control">
				<?php
				$described = $desc ? ' aria-describedby="' . esc_attr( $id ) . '_desc"' : '';

				switch ( $field['type'] ) {
					case 'bool':
						printf(
							'<label class="alc-switch"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s aria-labelledby="%1$s_label"%4$s><span class="alc-switch__track" aria-hidden="true"></span></label>',
							esc_attr( $id ),
							esc_attr( $name ),
							checked( (int) $value, 1, false ),
							$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
						);
						break;

					case 'textarea':
						printf( '<textarea class="large-text" rows="4" id="%1$s" name="%2$s"%4$s>%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ), $described ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						break;

					case 'int':
						printf( '<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d"%6$s>', esc_attr( $id ), esc_attr( $name ), (int) $value, (int) $field['min'], (int) $field['max'], $described ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						break;

					case 'color':
						printf( '<input type="text" class="alc-color" id="%1$s" name="%2$s" value="%3$s" data-role="%4$s"%5$s>', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( 'accent' === $key ? 'accent' : 'panel' ), $described ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						break;

					case 'list':
						ALC_Client_Mode::render_menu_picker( $name, $value );
						break;

					case 'secret':
						if ( ALC_AI::key_from_constant() ) {
							echo '<p class="alc-secret__state is-set"><span class="dashicons dashicons-lock" aria-hidden="true"></span>' . esc_html__( 'Set in wp-config.php', 'alibiewi-console' ) . '</p>';
							break;
						}
						$saved = (string) $value;
						printf(
							'<input type="password" class="regular-text" id="%1$s" name="%2$s" value="" autocomplete="new-password" spellcheck="false" placeholder="%3$s"%4$s>',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $saved ? sprintf( /* translators: %s: last four characters. */ __( 'Saved key ending %s. Paste a new one to replace it.', 'alibiewi-console' ), substr( $saved, -4 ) ) : 'sk-ant-...' ),
							$described // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						if ( $saved ) {
							printf( '<label class="alc-secret__clear"><input type="checkbox" name="%1$s" value="1"> %2$s</label>', esc_attr( ALC_Settings::OPTION . '[' . $key . '_clear]' ), esc_html__( 'Remove the saved key', 'alibiewi-console' ) );
						}
						break;

					case 'types':
						$value = is_array( $value ) ? $value : array();
						echo '<div class="alc-types">';
						foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
							if ( 'attachment' === $type->name ) {
								continue;
							}
							printf(
								'<label class="alc-type"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
								esc_attr( $name ),
								esc_attr( $type->name ),
								checked( in_array( $type->name, $value, true ), true, false ),
								esc_html( $type->labels->name )
							);
						}
						echo '</div>';
						break;

					case 'theme':
						echo '<div class="alc-themes" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '_label">';
						foreach ( ALC_Brand::themes() as $slug => $theme ) {
							$ink = alc_ink_for( $theme['panel'] );
							printf(
								'<label class="alc-theme"><input type="radio" name="%1$s" value="%2$s" data-panel="%3$s" data-accent="%4$s" %5$s><span class="alc-theme__preview" style="%6$s"><span class="alc-theme__bar" style="%7$s"></span><span class="alc-theme__line" style="%8$s"></span><span class="alc-theme__line is-short" style="%8$s"></span><span class="alc-theme__pill" style="%7$s"></span></span><span class="alc-theme__name">%9$s</span></label>',
								esc_attr( $name ),
								esc_attr( $slug ),
								esc_attr( $theme['panel'] ),
								esc_attr( $theme['accent'] ),
								checked( $value, $slug, false ),
								esc_attr( 'background:' . $theme['panel'] ),
								esc_attr( 'background:' . $theme['accent'] ),
								esc_attr( 'background:' . alc_rgba( $ink, 0.22 ) ),
								esc_html( $theme['label'] )
							);
						}
						echo '</div>';
						break;

					case 'select':
						$choices = isset( $label[2] ) ? $label[2] : array_combine( $field['options'], $field['options'] );
						printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $name ), $described ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						foreach ( $choices as $opt_value => $opt_label ) {
							printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $opt_value ), selected( $value, $opt_value, false ), esc_html( $opt_label ) );
						}
						echo '</select>';
						break;

					case 'media':
						$url = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
						?>
						<div class="alc-media<?php echo $url ? ' has-image' : ''; ?>">
							<input type="hidden" class="alc-media__input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (int) $value ); ?>">
							<div class="alc-media__preview<?php echo 'logo_id' === $key ? ' is-logo' : ''; ?>">
								<?php if ( $url ) : ?>
									<img src="<?php echo esc_url( $url ); ?>" alt="">
								<?php endif; ?>
							</div>
							<div class="alc-media__buttons">
								<button type="button" class="button alc-media__choose"><?php esc_html_e( 'Choose image', 'alibiewi-console' ); ?></button>
								<button type="button" class="button-link alc-media__remove"><?php esc_html_e( 'Remove', 'alibiewi-console' ); ?></button>
							</div>
						</div>
						<?php
						break;

					case 'email':
					case 'url':
					case 'phone':
					case 'text':
					default:
						$types = array(
							'email' => 'email',
							'url'   => 'url',
							'phone' => 'tel',
						);
						$type  = isset( $types[ $field['type'] ] ) ? $types[ $field['type'] ] : 'text';
						$extra = 'phone' === $field['type'] ? ' inputmode="numeric" pattern="[0-9]*"' : '';
						printf( '<input type="%1$s" class="regular-text" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s>', esc_attr( $type ), esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), $extra, $described ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * What the console picked up from the website, with a re-scan button.
	 */
	private static function render_detected() {
		$r    = ALC_Brand::resolved();
		$font = ALC_Brand::first_family( $r['heading_font'] );
		$body = ALC_Brand::first_family( $r['body_font'] );
		$font = 'ALC Bricolage' === $font ? 'Bricolage Grotesque' : $font;
		$body = 'ALC Bricolage' === $body ? 'Bricolage Grotesque' : $body;

		if ( 'site' === $r['font_mode'] && $r['font_source'] ) {
			/* translators: %s: where the fonts were found, e.g. Elementor site settings. */
			$font_note = sprintf( __( 'From %s', 'alibiewi-console' ), $r['font_source'] );
		} elseif ( 'site' === $r['font_mode'] ) {
			$font_note = __( 'No site fonts found, using Bricolage Grotesque', 'alibiewi-console' );
		} else {
			$font_note = __( 'Chosen below', 'alibiewi-console' );
		}
		?>
		<div class="alc-detect">
			<div class="alc-detect__head">
				<div>
					<p class="alc-detect__title"><?php esc_html_e( 'From your website', 'alibiewi-console' ); ?></p>
					<p class="alc-detect__sub"><?php esc_html_e( 'The logo and fonts come from the site unless you set them below.', 'alibiewi-console' ); ?></p>
				</div>
				<a class="button alc-detect__rescan" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=alc_rescan' ), 'alc_rescan' ) ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Scan again', 'alibiewi-console' ); ?></a>
			</div>
			<div class="alc-detect__grid">
				<div class="alc-detect__item">
					<span class="alc-detect__label"><?php esc_html_e( 'Logo', 'alibiewi-console' ); ?></span>
					<span class="alc-detect__logo alc-live-panel<?php echo $r['logo_chip'] ? ' has-chip-' . esc_attr( $r['logo_chip'] ) : ''; ?>">
						<?php if ( $r['logo_url'] ) : ?>
							<img src="<?php echo esc_url( $r['logo_url'] ); ?>" alt="">
						<?php elseif ( $r['icon_url'] ) : ?>
							<img src="<?php echo esc_url( $r['icon_url'] ); ?>" alt="">
						<?php else : ?>
							<em><?php esc_html_e( 'No logo found', 'alibiewi-console' ); ?></em>
						<?php endif; ?>
					</span>
				</div>
				<div class="alc-detect__item">
					<span class="alc-detect__label"><?php esc_html_e( 'Fonts', 'alibiewi-console' ); ?></span>
					<span class="alc-detect__font" style="<?php echo esc_attr( 'font-family:' . $r['heading_font'] ); ?>"><?php echo esc_html( $font ); ?></span>
					<span class="alc-detect__note"><?php echo esc_html( $font_note . ( $font !== $body ? ', ' . sprintf( /* translators: %s: body font. */ __( 'text in %s', 'alibiewi-console' ), $body ) : '' ) ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_tools() {
		?>
		<div class="alc-tools">
			<section class="alc-set-card">
				<h2 class="alc-set-card__title"><?php esc_html_e( 'Export settings', 'alibiewi-console' ); ?></h2>
				<p class="alc-set-card__text"><?php esc_html_e( 'Download this site\'s console settings as a JSON file. Import it on another client site to start from the same setup.', 'alibiewi-console' ); ?></p>
				<a class="button button-primary alc-save" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=alc_export' ), 'alc_export' ) ); ?>"><?php esc_html_e( 'Download JSON', 'alibiewi-console' ); ?></a>
			</section>

			<section class="alc-set-card">
				<h2 class="alc-set-card__title"><?php esc_html_e( 'Import settings', 'alibiewi-console' ); ?></h2>
				<p class="alc-set-card__text"><?php esc_html_e( 'Upload a file exported from Studio Console. Logos and images are stored per site, so choose them again after importing.', 'alibiewi-console' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="alc-import">
					<input type="hidden" name="action" value="alc_import">
					<?php wp_nonce_field( 'alc_import' ); ?>
					<label class="screen-reader-text" for="alc_import_file"><?php esc_html_e( 'Settings file', 'alibiewi-console' ); ?></label>
					<input type="file" id="alc_import_file" name="alc_import_file" accept="application/json,.json" required>
					<?php submit_button( __( 'Import', 'alibiewi-console' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>

			<section class="alc-set-card">
				<h2 class="alc-set-card__title"><?php esc_html_e( 'Reset', 'alibiewi-console' ); ?></h2>
				<p class="alc-set-card__text"><?php esc_html_e( 'Return every console setting to the Al-Ibiewi Studio defaults.', 'alibiewi-console' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Reset all Studio Console settings?', 'alibiewi-console' ) ); ?>');">
					<input type="hidden" name="action" value="alc_reset">
					<?php wp_nonce_field( 'alc_reset' ); ?>
					<?php submit_button( __( 'Reset to defaults', 'alibiewi-console' ), 'delete', 'submit', false ); ?>
				</form>
			</section>
		</div>
		<?php
	}

	private static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$code = isset( $_GET['alc_notice'] ) ? sanitize_key( wp_unslash( $_GET['alc_notice'] ) ) : '';

		$notices = array(
			'imported'    => array( 'success', __( 'Settings imported. Choose the logo and panel image again if this is a new site.', 'alibiewi-console' ) ),
			'reset'       => array( 'success', __( 'Settings reset to defaults.', 'alibiewi-console' ) ),
			'ai_rebuilt'     => array( 'success', __( 'The assistant has relearned the website.', 'alibiewi-console' ) ),
			'ai_check_ok'    => array( 'success', __( 'Connected. Claude answered with your key. If visitors still see errors, a security or caching plugin may be blocking the REST API for logged-out visitors.', 'alibiewi-console' ) ),
			'ai_check_fail'  => array( 'error', __( 'Claude did not accept the request. See the error above the settings: usually the key is wrong, has no credit, or was revoked.', 'alibiewi-console' ) ),
			'ai_check_nokey' => array( 'error', __( 'Add your Claude API key and save first.', 'alibiewi-console' ) ),
			'ai_log_cleared' => array( 'success', __( 'Question log cleared.', 'alibiewi-console' ) ),
			'role_client' => array( 'success', __( 'Done. That person is now a Site Owner.', 'alibiewi-console' ) ),
			'role_admin'  => array( 'success', __( 'Done. That person is an administrator again.', 'alibiewi-console' ) ),
			'role_failed' => array( 'error', __( 'That role change could not be made.', 'alibiewi-console' ) ),
			'rescanned'   => array( 'success', __( 'Website scanned again for its logo and fonts.', 'alibiewi-console' ) ),
			'bad_file'    => array( 'error', __( 'That file could not be read. Export a fresh one from Studio Console and try again.', 'alibiewi-console' ) ),
		);

		if ( isset( $notices[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $code ][0] ), esc_html( $notices[ $code ][1] ) );
		}
	}

	/* --------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	public static function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'alibiewi-console' ), 403 );
		}
		check_admin_referer( 'alc_export' );

		$host = sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$file = 'studio-console-' . $host . '-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );

		echo wp_json_encode(
			array(
				'plugin'   => 'alibiewi-console',
				'version'  => ALC_VERSION,
				'exported' => gmdate( 'c' ),
				'site'     => home_url( '/' ),
				'settings' => array_diff_key( ALC_Settings::all(), array( 'ai_api_key' => true ) ),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	public static function import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'alibiewi-console' ), 403 );
		}
		check_admin_referer( 'alc_import' );

		$back = self::url( 'tools' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below, never stored as-is.
		$file = isset( $_FILES['alc_import_file'] ) ? $_FILES['alc_import_file'] : null;

		if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > 200 * KB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'alc_notice', 'bad_file', $back ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp upload.
		$data = json_decode( (string) file_get_contents( $file['tmp_name'] ), true );
		if ( is_array( $data ) && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data = $data['settings'];
		}

		if ( ! is_array( $data ) || ! array_intersect_key( $data, ALC_Settings::defaults() ) ) {
			wp_safe_redirect( add_query_arg( 'alc_notice', 'bad_file', $back ) );
			exit;
		}

		unset( $data['ai_api_key'], $data['ai_api_key_clear'] );

		self::$bypass = true;
		update_option( ALC_Settings::OPTION, ALC_Settings::sanitize( $data, null ) );
		self::$bypass = false;

		wp_safe_redirect( add_query_arg( 'alc_notice', 'imported', $back ) );
		exit;
	}

	public static function rescan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'alibiewi-console' ), 403 );
		}
		check_admin_referer( 'alc_rescan' );
		ALC_Brand::flush();
		$logo = (int) get_theme_mod( 'custom_logo' );
		if ( $logo ) {
			delete_post_meta( $logo, '_alc_tone' );
		}
		wp_safe_redirect( add_query_arg( 'alc_notice', 'rescanned', self::url( 'brand' ) ) );
		exit;
	}

	public static function reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'alibiewi-console' ), 403 );
		}
		check_admin_referer( 'alc_reset' );

		self::$bypass = true;
		update_option( ALC_Settings::OPTION, ALC_Settings::defaults() );
		self::$bypass = false;

		wp_safe_redirect( add_query_arg( 'alc_notice', 'reset', self::url( 'tools' ) ) );
		exit;
	}
}
