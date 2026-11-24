<?php
/**
 * Client dashboard that replaces the default wp-admin home.
 *
 * Every widget checks the current user's capabilities, and store or form
 * widgets only appear when WooCommerce or Fluent Forms is active.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Dashboard {

	const SLUG = 'alc-home';

	/** @var string */
	private static $hook = '';

	public static function init() {
		if ( ! alc_opt( 'dashboard_enabled' ) ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'load-index.php', array( __CLASS__, 'redirect' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_ajax_alc_search', array( __CLASS__, 'ajax_search' ) );
	}

	public static function url() {
		return admin_url( 'index.php?page=' . self::SLUG );
	}

	public static function menu() {
		self::$hook = add_submenu_page(
			'index.php',
			__( 'Home', 'alibiewi-console' ),
			__( 'Home', 'alibiewi-console' ),
			'read',
			self::SLUG,
			array( __CLASS__, 'render' ),
			0
		);
		remove_submenu_page( 'index.php', 'index.php' );
	}

	/**
	 * Send the default dashboard to ours. Admins can reach the classic one
	 * with ?alc-classic=1.
	 */
	public static function redirect() {
		if ( is_network_admin() || is_user_admin() || wp_doing_ajax() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only toggle.
		if ( isset( $_GET['alc-classic'] ) && current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		ALC_Brand::enqueue_fonts( 'admin_head' );
		wp_enqueue_style( 'alc-dashboard', ALC_URL . 'assets/css/dashboard.css', array(), ALC_VERSION );
		wp_add_inline_style( 'alc-dashboard', alc_brand_css( 'body.alc-screen' ) );
		wp_enqueue_script( 'alc-dashboard', ALC_URL . 'assets/js/dashboard.js', array(), ALC_VERSION, true );

		$actions = array();
		foreach ( self::quick_actions( 20 ) as $item ) {
			$actions[] = array(
				'label' => $item[0],
				'url'   => $item[1],
				'icon'  => $item[2],
				'meta'  => $item[3],
			);
		}
		wp_localize_script(
			'alc-dashboard',
			'alcDash',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'alc_search' ),
				'actions' => $actions,
				'i18n'    => array(
					'actions' => __( 'Quick actions', 'alibiewi-console' ),
					'content' => __( 'Your content', 'alibiewi-console' ),
					'none'    => __( 'Nothing found. Try another word.', 'alibiewi-console' ),
					'loading' => __( 'Searching', 'alibiewi-console' ),
				),
			)
		);
	}

	/**
	 * Search content the current user can edit.
	 */
	public static function ajax_search() {
		check_ajax_referer( 'alc_search', 'nonce' );

		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( strlen( $q ) < 2 ) {
			wp_send_json_success( array() );
		}

		$types = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name || ( ! $type->public && ! in_array( $type->name, array( 'wp_block' ), true ) ) ) {
				continue;
			}
			if ( current_user_can( $type->cap->edit_posts ) ) {
				$types[ $type->name ] = $type;
			}
		}
		if ( ! $types ) {
			wp_send_json_success( array() );
		}

		$posts = get_posts(
			array(
				's'                => $q,
				'post_type'        => array_keys( $types ),
				'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'   => 12,
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$icons = array(
			'page'    => 'admin-page',
			'post'    => 'admin-post',
			'product' => 'products',
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$meta = $types[ $post->post_type ]->labels->singular_name;
			if ( 'publish' !== $post->post_status ) {
				$status = get_post_status_object( $post->post_status );
				$meta  .= $status ? ', ' . $status->label : '';
			}
			$out[] = array(
				'label' => wp_strip_all_tags( get_the_title( $post ) ) ? wp_strip_all_tags( get_the_title( $post ) ) : __( '(no title)', 'alibiewi-console' ),
				'meta'  => $meta,
				'url'   => get_edit_post_link( $post->ID, 'raw' ),
				'icon'  => isset( $icons[ $post->post_type ] ) ? $icons[ $post->post_type ] : 'admin-generic',
			);
			if ( count( $out ) >= 8 ) {
				break;
			}
		}

		wp_send_json_success( $out );
	}

	/**
	 * @param string $classes Space separated classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && self::$hook && $screen->id === self::$hook ) {
			$classes .= ' alc-screen';
		}
		return $classes;
	}

	/* --------------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------------ */

	private static function woo_ready() {
		return alc_opt( 'show_woo' ) && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) && current_user_can( 'edit_shop_orders' );
	}

	private static function forms_ready() {
		static $ready = null;
		if ( null !== $ready ) {
			return $ready;
		}

		$ready = false;
		if ( ! alc_opt( 'show_forms' ) || ! ( defined( 'FLUENTFORM' ) || defined( 'FLUENTFORM_VERSION' ) ) ) {
			return $ready;
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'fluentform_entries_viewer' ) && ! current_user_can( 'fluentform_dashboard_access' ) ) {
			return $ready;
		}

		$ready = self::table_exists( 'fluentform_submissions' ) && self::table_exists( 'fluentform_forms' );
		return $ready;
	}

	/**
	 * @param string $name Table name without prefix.
	 * @return bool
	 */
	private static function table_exists( $name ) {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private static function orders_url() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders' );
		}
		return admin_url( 'edit.php?post_type=shop_order' );
	}

	/**
	 * Gross sales since the 1st of this month, cached for 10 minutes.
	 *
	 * @return float
	 */
	private static function woo_month_sales() {
		$cached = get_transient( 'alc_woo_month_sales' );
		if ( false !== $cached ) {
			return (float) $cached;
		}

		global $wpdb;
		$start = wp_date( 'Y-m-01 00:00:00' );
		$sum   = 0.0;

		if ( self::table_exists( 'wc_order_stats' ) ) {
			$table = $wpdb->prefix . 'wc_order_stats';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sum = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_sales) FROM {$table} WHERE parent_id = 0 AND date_created >= %s AND status IN ('wc-completed','wc-processing','wc-on-hold')", $start ) );
		} else {
			$orders = wc_get_orders(
				array(
					'status'       => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
					'date_created' => '>=' . wp_date( 'Y-m-01' ),
					'limit'        => 300,
					'type'         => 'shop_order',
				)
			);
			foreach ( $orders as $order ) {
				$sum += (float) $order->get_total();
			}
		}

		set_transient( 'alc_woo_month_sales', $sum, 10 * MINUTE_IN_SECONDS );
		return $sum;
	}

	private static function forms_unread() {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_submissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'unread'" );
	}

	/**
	 * @return array
	 */
	private static function forms_latest() {
		global $wpdb;
		$subs  = $wpdb->prefix . 'fluentform_submissions';
		$forms = $wpdb->prefix . 'fluentform_forms';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT s.id, s.form_id, s.status, s.created_at, s.response, f.title FROM {$subs} s LEFT JOIN {$forms} f ON f.id = s.form_id ORDER BY s.id DESC LIMIT 5" );

		$out = array();
		foreach ( (array) $rows as $row ) {
			list( $who, $contact ) = self::entry_preview( $row->response );
			$out[] = array(
				'title'  => '' !== $who ? $who : sprintf( /* translators: %d: entry ID. */ __( 'Entry #%d', 'alibiewi-console' ), $row->id ),
				'meta'   => trim( implode( ', ', array_filter( array( $contact, $row->title ) ) ) ),
				'time'   => self::ago( strtotime( $row->created_at ), (int) current_time( 'timestamp' ) ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Fluent stores local time.
				'unread' => 'unread' === $row->status,
				'url'    => admin_url( 'admin.php?page=fluent_forms&route=entries&form_id=' . absint( $row->form_id ) . '#/entries/' . absint( $row->id ) ),
			);
		}
		return $out;
	}

	/**
	 * Pull a name and an email out of a Fluent Forms response.
	 *
	 * @param string $json Response JSON.
	 * @return array{0:string,1:string}
	 */
	private static function entry_preview( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return array( '', '' );
		}

		$name  = '';
		$email = '';

		foreach ( $data as $key => $value ) {
			$key = strtolower( (string) $key );

			if ( '' === $name && is_array( $value ) && ( false !== strpos( $key, 'name' ) ) ) {
				$name = trim( implode( ' ', array_filter( array_map( 'strval', array_intersect_key( $value, array_flip( array( 'first_name', 'middle_name', 'last_name' ) ) ) ) ) ) );
			} elseif ( '' === $name && is_string( $value ) && preg_match( '/(^|_)(full_?)?name$/', $key ) ) {
				$name = $value;
			}

			if ( '' === $email && is_string( $value ) && is_email( $value ) ) {
				$email = $value;
			}
		}

		return array( sanitize_text_field( $name ), sanitize_email( $email ) );
	}

	/**
	 * @param int $from Unix time.
	 * @param int $to   Unix time.
	 * @return string
	 */
	private static function ago( $from, $to = 0 ) {
		$to = $to ? $to : time();
		if ( $to - $from < MINUTE_IN_SECONDS ) {
			return __( 'Just now', 'alibiewi-console' );
		}
		/* translators: %s: human readable time difference, e.g. 5 mins. */
		return sprintf( __( '%s ago', 'alibiewi-console' ), human_time_diff( $from, $to ) );
	}

	/**
	 * @return array
	 */
	private static function quick_actions( $limit = 8 ) {
		$items = array();

		$front = (int) get_option( 'page_on_front' );
		if ( 'page' === get_option( 'show_on_front' ) && $front && current_user_can( 'edit_post', $front ) ) {
			$items[] = array( __( 'Edit homepage', 'alibiewi-console' ), get_edit_post_link( $front, 'raw' ), 'admin-home', __( 'Update your front page', 'alibiewi-console' ) );
		}
		if ( current_user_can( 'edit_posts' ) ) {
			$items[] = array( __( 'New post', 'alibiewi-console' ), admin_url( 'post-new.php' ), 'edit', __( 'Share news or an article', 'alibiewi-console' ) );
		}
		if ( current_user_can( 'edit_pages' ) ) {
			$items[] = array( __( 'Pages', 'alibiewi-console' ), admin_url( 'edit.php?post_type=page' ), 'admin-page', __( 'Edit any page on the site', 'alibiewi-console' ) );
		}
		if ( self::woo_ready() ) {
			$items[] = array( __( 'Orders', 'alibiewi-console' ), self::orders_url(), 'cart', __( 'Process and track orders', 'alibiewi-console' ) );
			if ( current_user_can( 'edit_products' ) ) {
				$items[] = array( __( 'Add product', 'alibiewi-console' ), admin_url( 'post-new.php?post_type=product' ), 'products', __( 'List something new', 'alibiewi-console' ) );
			}
		}
		if ( self::forms_ready() ) {
			$items[] = array( __( 'Form entries', 'alibiewi-console' ), admin_url( 'admin.php?page=fluent_forms_all_entries' ), 'email-alt', __( 'Read every enquiry', 'alibiewi-console' ) );
		}
		if ( current_user_can( 'upload_files' ) ) {
			$items[] = array( __( 'Media', 'alibiewi-console' ), admin_url( 'upload.php' ), 'format-image', __( 'Images, videos and files', 'alibiewi-console' ) );
		}
		if ( current_user_can( 'edit_theme_options' ) ) {
			if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
				$items[] = array( __( 'Site design', 'alibiewi-console' ), admin_url( 'site-editor.php' ), 'admin-appearance', __( 'Header, footer and styles', 'alibiewi-console' ) );
			} elseif ( current_theme_supports( 'menus' ) ) {
				$items[] = array( __( 'Menus', 'alibiewi-console' ), admin_url( 'nav-menus.php' ), 'menu-alt', __( 'Change site navigation', 'alibiewi-console' ) );
			}
		}

		/**
		 * Filter the quick action tiles.
		 *
		 * @param array $items Each item: [ label, url, dashicon (without "dashicons-"), description ].
		 */
		$items = array_values(
			array_filter(
				apply_filters( 'alc_quick_actions', $items ),
				static function ( $item ) {
					return ! ALC_Client_Mode::url_hidden( $item[1] );
				}
			)
		);
		return array_slice( $items, 0, $limit );
	}

	/**
	 * @return array
	 */
	private static function stats() {
		$stats = array();

		if ( self::woo_ready() ) {
			$stats[] = array(
				'label' => __( 'Sales this month', 'alibiewi-console' ),
				'icon'  => 'chart-line',
				'value' => wp_strip_all_tags( html_entity_decode( wc_price( self::woo_month_sales() ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ),
				'url'   => admin_url( 'admin.php?page=wc-admin' ),
			);
			if ( function_exists( 'wc_orders_count' ) ) {
				$stats[] = array(
					'label' => __( 'Orders to fulfil', 'alibiewi-console' ),
				'icon'  => 'cart',
					'value' => number_format_i18n( wc_orders_count( 'processing' ) ),
					'url'   => add_query_arg( 'status', 'wc-processing', self::orders_url() ),
				);
			}
		}

		if ( self::forms_ready() ) {
			$stats[] = array(
				'label' => __( 'Unread enquiries', 'alibiewi-console' ),
				'icon'  => 'email-alt',
				'value' => number_format_i18n( self::forms_unread() ),
				'url'   => admin_url( 'admin.php?page=fluent_forms_all_entries' ),
			);
		}

		if ( current_user_can( 'edit_posts' ) ) {
			$stats[] = array(
				'label' => __( 'Published posts', 'alibiewi-console' ),
				'icon'  => 'admin-post',
				'value' => number_format_i18n( (int) wp_count_posts()->publish ),
				'url'   => admin_url( 'edit.php' ),
			);
		}

		if ( current_user_can( 'edit_pages' ) ) {
			$stats[] = array(
				'label' => __( 'Live pages', 'alibiewi-console' ),
				'icon'  => 'admin-page',
				'value' => number_format_i18n( (int) wp_count_posts( 'page' )->publish ),
				'url'   => admin_url( 'edit.php?post_type=page' ),
			);
		}

		if ( current_user_can( 'moderate_comments' ) ) {
			$stats[] = array(
				'label' => __( 'Comments to review', 'alibiewi-console' ),
				'icon'  => 'admin-comments',
				'value' => number_format_i18n( (int) wp_count_comments()->moderated ),
				'url'   => admin_url( 'edit-comments.php?comment_status=moderated' ),
			);
		}

		$stats = array_values(
			array_filter(
				apply_filters( 'alc_stats', $stats ),
				static function ( $stat ) {
					return ! ALC_Client_Mode::url_hidden( $stat['url'] );
				}
			)
		);
		return array_slice( $stats, 0, 4 );
	}

	/**
	 * @return array
	 */
	private static function health_rows() {
		$rows = array();

		if ( current_user_can( 'update_core' ) || current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ) ) {
			$data    = wp_get_update_data();
			$pending = (int) $data['counts']['total'];
			$rows[]  = array(
				'label' => __( 'Updates', 'alibiewi-console' ),
				'value' => $pending ? sprintf( /* translators: %d: number of updates. */ _n( '%d waiting', '%d waiting', $pending, 'alibiewi-console' ), $pending ) : __( 'All up to date', 'alibiewi-console' ),
				'state' => $pending ? 'warn' : 'ok',
				'url'   => admin_url( 'update-core.php' ),
			);
		}

		$public = '0' !== (string) get_option( 'blog_public' );
		$rows[] = array(
			'label' => __( 'Search engines', 'alibiewi-console' ),
			'value' => $public ? __( 'Visible on Google', 'alibiewi-console' ) : __( 'Hidden from search', 'alibiewi-console' ),
			'state' => $public ? 'ok' : 'warn',
			'url'   => current_user_can( 'manage_options' ) ? admin_url( 'options-reading.php' ) : '',
		);

		$https  = function_exists( 'wp_is_using_https' ) ? wp_is_using_https() : is_ssl();
		$rows[] = array(
			'label' => __( 'Secure connection', 'alibiewi-console' ),
			'value' => $https ? __( 'HTTPS on', 'alibiewi-console' ) : __( 'Not using HTTPS', 'alibiewi-console' ),
			'state' => $https ? 'ok' : 'warn',
			'url'   => '',
		);

		$php_ok = version_compare( PHP_VERSION, '8.1', '>=' );
		$rows[] = array(
			'label' => __( 'Server PHP', 'alibiewi-console' ),
			'value' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'state' => $php_ok ? 'ok' : 'warn',
			'url'   => '',
		);

		if ( current_user_can( 'view_site_health_checks' ) ) {
			$result = json_decode( (string) get_transient( 'health-check-site-status-result' ), true );
			if ( is_array( $result ) && isset( $result['critical'] ) ) {
				$critical = (int) $result['critical'];
				$rows[]   = array(
					'label' => __( 'Site Health', 'alibiewi-console' ),
					'value' => $critical ? sprintf( /* translators: %d: number of issues. */ _n( '%d critical issue', '%d critical issues', $critical, 'alibiewi-console' ), $critical ) : __( 'No critical issues', 'alibiewi-console' ),
					'state' => $critical ? 'warn' : 'ok',
					'url'   => admin_url( 'site-health.php' ),
				);
			} else {
				$rows[] = array(
					'label' => __( 'Site Health', 'alibiewi-console' ),
					'value' => __( 'Run a check', 'alibiewi-console' ),
					'state' => 'info',
					'url'   => admin_url( 'site-health.php' ),
				);
			}
		}

		return apply_filters( 'alc_health_rows', $rows );
	}

	/* --------------------------------------------------------------------
	 * View
	 * ------------------------------------------------------------------ */

	public static function render() {
		$user  = wp_get_current_user();
		$first = $user->first_name ? $user->first_name : $user->display_name;
		$hour  = (int) wp_date( 'G' );

		if ( $hour < 12 ) {
			$greeting = __( 'Good morning', 'alibiewi-console' );
		} elseif ( $hour < 17 ) {
			$greeting = __( 'Good afternoon', 'alibiewi-console' );
		} else {
			$greeting = __( 'Good evening', 'alibiewi-console' );
		}

		$actions = self::quick_actions();
		$stats   = self::stats();
		$primary = $actions ? $actions[0] : null;
		$logo    = alc_logo_url();
		?>
		<div class="wrap alc-dash">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Home', 'alibiewi-console' ); ?></h1>

			<section class="alc-hero">
				<div class="alc-hero__text">
					<p class="alc-hero__eyebrow">
						<?php if ( $logo ) : ?>
							<?php $chip = ALC_Brand::resolved()['logo_chip']; ?>
							<img class="alc-hero__logo<?php echo $chip ? ' has-chip-' . esc_attr( $chip ) : ''; ?>" src="<?php echo esc_url( $logo ); ?>" alt="">
						<?php endif; ?>
						<span><?php echo esc_html( alc_client_name() . ', ' . wp_date( 'l j F' ) ); ?></span>
					</p>
					<h2 class="alc-hero__title"><?php echo esc_html( $greeting . ', ' . $first . '.' ); ?></h2>
					<p class="alc-hero__sub"><?php echo esc_html( alc_opt( 'dashboard_message' ) ); ?></p>
					<div class="alc-search">
						<span class="alc-search__icon dashicons dashicons-search" aria-hidden="true"></span>
						<label class="screen-reader-text" for="alc-search-input"><?php esc_html_e( 'Search your website', 'alibiewi-console' ); ?></label>
						<input type="search" id="alc-search-input" class="alc-search__input" placeholder="<?php esc_attr_e( 'Search pages, posts or what you want to do', 'alibiewi-console' ); ?>" data-short-placeholder="<?php esc_attr_e( 'Search or find a task', 'alibiewi-console' ); ?>" role="combobox" aria-expanded="false" aria-controls="alc-search-results" aria-autocomplete="list" autocomplete="off">
						<kbd class="alc-search__kbd" aria-hidden="true">/</kbd>
						<div class="alc-search__panel" hidden>
							<ul id="alc-search-results" class="alc-search__results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'alibiewi-console' ); ?>"></ul>
						</div>
					</div>
				</div>
				<div class="alc-hero__actions">
					<a class="alc-btn alc-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
						<span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e( 'View website', 'alibiewi-console' ); ?>
					</a>
					<?php if ( $primary ) : ?>
						<a class="alc-btn alc-btn--primary" href="<?php echo esc_url( $primary[1] ); ?>">
							<span class="dashicons dashicons-<?php echo esc_attr( $primary[2] ); ?>" aria-hidden="true"></span><?php echo esc_html( $primary[0] ); ?>
						</a>
					<?php endif; ?>
				</div>
			</section>

			<hr class="wp-header-end">
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
			<?php if ( isset( $_GET['alc_blocked'] ) ) : ?>
				<div class="alc-blocked" role="status">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<p><?php echo esc_html( alc_opt( 'studio_credit' ) ? __( 'That area is looked after by Al-Ibiewi Studio. Message us below if you need a change there.', 'alibiewi-console' ) : __( 'That area is looked after by your web team. Message them if you need a change there.', 'alibiewi-console' ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $stats ) : ?>
				<section class="alc-stats" aria-label="<?php esc_attr_e( 'At a glance', 'alibiewi-console' ); ?>">
					<?php foreach ( $stats as $stat ) : ?>
						<a class="alc-stat" href="<?php echo esc_url( $stat['url'] ); ?>">
							<?php if ( ! empty( $stat['icon'] ) ) : ?>
								<span class="alc-stat__icon dashicons dashicons-<?php echo esc_attr( $stat['icon'] ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="alc-stat__value<?php echo mb_strlen( (string) $stat['value'] ) > 8 ? ' alc-stat__value--long' : ''; ?>"><?php echo esc_html( $stat['value'] ); ?></span>
							<span class="alc-stat__label"><?php echo esc_html( $stat['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<?php if ( count( $actions ) > 1 ) : ?>
				<section class="alc-section">
					<h3 class="alc-section__title"><?php esc_html_e( 'Quick actions', 'alibiewi-console' ); ?></h3>
					<div class="alc-actions">
						<?php foreach ( $actions as $item ) : ?>
							<a class="alc-action" href="<?php echo esc_url( $item[1] ); ?>">
								<span class="alc-action__icon dashicons dashicons-<?php echo esc_attr( $item[2] ); ?>" aria-hidden="true"></span>
								<span class="alc-action__label"><?php echo esc_html( $item[0] ); ?></span>
								<span class="alc-action__desc"><?php echo esc_html( $item[3] ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<div class="alc-grid">
				<div class="alc-grid__main">
					<?php
					if ( self::forms_ready() ) {
						self::panel_forms();
					}
					if ( self::woo_ready() ) {
						self::panel_orders();
					}
					self::panel_recent();
					if ( ! self::forms_ready() ) {
						self::panel_comments();
					}
					?>
				</div>
				<aside class="alc-grid__side">
					<?php
					if ( alc_opt( 'show_health' ) && current_user_can( 'manage_options' ) ) {
						self::panel_health();
					}
					self::panel_ai();
					self::panel_support();
					self::panel_guides();
					?>
				</aside>
			</div>

			<footer class="alc-foot">
				<?php echo alc_studio_credit_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<span class="alc-foot__links">
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=alc-settings' ) ); ?>"><?php esc_html_e( 'Console settings', 'alibiewi-console' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'index.php?alc-classic=1' ) ); ?>"><?php esc_html_e( 'Classic dashboard', 'alibiewi-console' ); ?></a>
					</span>
				<?php endif; ?>
			</footer>
		</div>
		<?php
	}

	/**
	 * Card shell.
	 *
	 * @param string $title Heading.
	 * @param string $link  Optional "view all" URL.
	 * @param string $label Optional link label.
	 */
	private static function card_open( $title, $link = '', $label = '' ) {
		echo '<section class="alc-card"><header class="alc-card__head"><h3 class="alc-card__title">' . esc_html( $title ) . '</h3>';
		if ( $link ) {
			echo '<a class="alc-card__link" href="' . esc_url( $link ) . '">' . esc_html( $label ? $label : __( 'View all', 'alibiewi-console' ) ) . '</a>';
		}
		echo '</header>';
	}

	private static function card_close() {
		echo '</section>';
	}

	/**
	 * @param string $text Empty-state message.
	 */
	private static function empty_state( $text ) {
		echo '<p class="alc-empty">' . esc_html( $text ) . '</p>';
	}

	/**
	 * @param array $rows Rows with title, meta, time, url, badge, badge_state.
	 */
	private static function list_rows( $rows ) {
		echo '<ul class="alc-list">';
		foreach ( $rows as $row ) {
			echo '<li><a class="alc-list__row" href="' . esc_url( $row['url'] ) . '">';
			echo '<span class="alc-list__main"><span class="alc-list__title">' . esc_html( $row['title'] ) . '</span>';
			if ( ! empty( $row['meta'] ) ) {
				echo '<span class="alc-list__meta">' . esc_html( $row['meta'] ) . '</span>';
			}
			echo '</span><span class="alc-list__side">';
			if ( ! empty( $row['badge'] ) ) {
				echo '<span class="alc-pill alc-pill--' . esc_attr( $row['badge_state'] ) . '">' . esc_html( $row['badge'] ) . '</span>';
			}
			if ( ! empty( $row['side'] ) ) {
				echo '<span class="alc-list__amount">' . esc_html( $row['side'] ) . '</span>';
			}
			echo '<span class="alc-list__time">' . esc_html( $row['time'] ) . '</span>';
			echo '</span></a></li>';
		}
		echo '</ul>';
	}

	private static function panel_forms() {
		if ( ALC_Client_Mode::url_hidden( admin_url( 'admin.php?page=fluent_forms' ) ) ) {
			return;
		}
		self::card_open( __( 'Latest enquiries', 'alibiewi-console' ), admin_url( 'admin.php?page=fluent_forms_all_entries' ) );
		$rows = array_map(
			static function ( $entry ) {
				$entry['badge']       = $entry['unread'] ? __( 'New', 'alibiewi-console' ) : '';
				$entry['badge_state'] = 'accent';
				return $entry;
			},
			self::forms_latest()
		);
		$rows ? self::list_rows( $rows ) : self::empty_state( __( 'No form entries yet. New enquiries will show up here.', 'alibiewi-console' ) );
		self::card_close();
	}

	private static function panel_orders() {
		if ( ALC_Client_Mode::url_hidden( self::orders_url() ) || ALC_Client_Mode::url_hidden( admin_url( 'admin.php?page=woocommerce' ) ) ) {
			return;
		}
		self::card_open( __( 'Recent orders', 'alibiewi-console' ), self::orders_url() );
		$orders = wc_get_orders(
			array(
				'limit'   => 5,
				'orderby' => 'date',
				'order'   => 'DESC',
				'type'    => 'shop_order',
			)
		);

		$states = array(
			'completed'  => 'ok',
			'processing' => 'info',
			'on-hold'    => 'warn',
			'failed'     => 'warn',
			'cancelled'  => 'neutral',
			'refunded'   => 'neutral',
			'pending'    => 'neutral',
		);

		$rows = array();
		foreach ( $orders as $order ) {
			$name    = trim( $order->get_formatted_billing_full_name() );
			$created = $order->get_date_created();
			$rows[]  = array(
				/* translators: %s: order number. */
				'title'       => sprintf( __( 'Order #%s', 'alibiewi-console' ), $order->get_order_number() ),
				'meta'        => $name ? $name : __( 'Guest', 'alibiewi-console' ),
				'side'        => wp_strip_all_tags( html_entity_decode( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ),
				'badge'       => wc_get_order_status_name( $order->get_status() ),
				'badge_state' => isset( $states[ $order->get_status() ] ) ? $states[ $order->get_status() ] : 'neutral',
				'time'        => $created ? self::ago( $created->getTimestamp() ) : '',
				'url'         => $order->get_edit_order_url(),
			);
		}

		$rows ? self::list_rows( $rows ) : self::empty_state( __( 'No orders yet. Your first one will appear here.', 'alibiewi-console' ) );
		self::card_close();
	}

	private static function panel_recent() {
		$types = array( 'post', 'page' );
		if ( post_type_exists( 'product' ) ) {
			$types[] = 'product';
		}

		$posts = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'posts_per_page'   => 8,
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$type   = get_post_type_object( $post->post_type );
			$status = get_post_status_object( $post->post_status );
			$rows[] = array(
				'title'       => get_the_title( $post ) ? wp_strip_all_tags( get_the_title( $post ) ) : __( '(no title)', 'alibiewi-console' ),
				'meta'        => $type ? $type->labels->singular_name : '',
				'badge'       => 'publish' !== $post->post_status && $status ? $status->label : '',
				'badge_state' => 'neutral',
				'time'        => self::ago( (int) get_post_modified_time( 'U', true, $post ) ),
				'url'         => get_edit_post_link( $post->ID, 'raw' ),
			);
			if ( count( $rows ) >= 5 ) {
				break;
			}
		}

		if ( ! $rows && ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		self::card_open( __( 'Recently edited', 'alibiewi-console' ) );
		$rows ? self::list_rows( $rows ) : self::empty_state( __( 'Nothing edited yet.', 'alibiewi-console' ) );
		self::card_close();
	}

	private static function panel_comments() {
		if ( ! current_user_can( 'moderate_comments' ) || ALC_Client_Mode::url_hidden( admin_url( 'edit-comments.php' ) ) ) {
			return;
		}
		$comments = get_comments(
			array(
				'status' => 'hold',
				'number' => 5,
			)
		);
		if ( ! $comments ) {
			return;
		}

		self::card_open( __( 'Comments waiting for you', 'alibiewi-console' ), admin_url( 'edit-comments.php?comment_status=moderated' ) );
		$rows = array();
		foreach ( $comments as $comment ) {
			$rows[] = array(
				'title'       => $comment->comment_author ? $comment->comment_author : __( 'Anonymous', 'alibiewi-console' ),
				'meta'        => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 12 ),
				'badge'       => __( 'Pending', 'alibiewi-console' ),
				'badge_state' => 'warn',
				'time'        => self::ago( strtotime( $comment->comment_date_gmt . ' UTC' ) ),
				'url'         => admin_url( 'comment.php?action=editcomment&c=' . $comment->comment_ID ),
			);
		}
		self::list_rows( $rows );
		self::card_close();
	}

	private static function panel_health() {
		self::card_open( __( 'Website health', 'alibiewi-console' ) );
		echo '<ul class="alc-health">';
		foreach ( self::health_rows() as $row ) {
			$tag   = $row['url'] ? 'a' : 'div';
			$href  = $row['url'] ? ' href="' . esc_url( $row['url'] ) . '"' : '';
			echo '<li><' . $tag . ' class="alc-health__row"' . $href . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag, escaped href.
			$icons = array(
				'ok'   => 'yes-alt',
				'warn' => 'warning',
				'info' => 'info-outline',
			);
			$icon  = isset( $icons[ $row['state'] ] ) ? $icons[ $row['state'] ] : 'info-outline';
			echo '<span class="alc-health__icon alc-health__icon--' . esc_attr( $row['state'] ) . ' dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
			echo '<span class="alc-health__label">' . esc_html( $row['label'] ) . '</span>';
			echo '<span class="alc-health__value">' . esc_html( $row['value'] ) . '</span>';
			echo '</' . $tag . '></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
		}
		echo '</ul>';
		self::card_close();
	}

	private static function panel_support() {
		$wa    = alc_opt( 'support_whatsapp' );
		$email = alc_opt( 'support_email' );
		$url   = alc_opt( 'support_url' );
		$hours = alc_opt( 'support_hours' );

		if ( ! $wa && ! $email && ! $url ) {
			if ( current_user_can( 'manage_options' ) ) {
				self::card_open( __( 'Need a hand?', 'alibiewi-console' ) );
				echo '<p class="alc-card__text">' . esc_html__( 'Add your WhatsApp number, email or support link so clients can reach you in one tap.', 'alibiewi-console' ) . '</p>';
				echo '<a class="alc-btn alc-btn--soft" href="' . esc_url( admin_url( 'options-general.php?page=alc-settings&tab=support' ) ) . '">' . esc_html__( 'Add support details', 'alibiewi-console' ) . '</a>';
				self::card_close();
			}
			return;
		}

		$site = alc_client_name();
		$text = alc_opt( 'studio_credit' )
			? __( 'Message Al-Ibiewi Studio, the team that built and looks after this website.', 'alibiewi-console' )
			: __( 'Message the team that looks after this website.', 'alibiewi-console' );

		echo '<section class="alc-card alc-card--support">';
		echo '<header class="alc-card__head"><h3 class="alc-card__title">' . esc_html__( 'Need a hand?', 'alibiewi-console' ) . '</h3></header>';
		echo '<p class="alc-card__text">' . esc_html( $text ) . '</p>';
		echo '<div class="alc-support">';

		if ( $wa ) {
			/* translators: 1: site name, 2: site URL. */
			$msg = sprintf( __( 'Hi, I need help with %1$s (%2$s).', 'alibiewi-console' ), $site, home_url( '/' ) );
			echo '<a class="alc-btn alc-btn--primary" href="' . esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $msg ) ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>' . esc_html__( 'WhatsApp us', 'alibiewi-console' ) . '</a>';
		}
		if ( $email ) {
			/* translators: %s: site name. */
			$subject = sprintf( __( 'Website support: %s', 'alibiewi-console' ), $site );
			echo '<a class="alc-btn alc-btn--soft" href="' . esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( $subject ) ) . '"><span class="dashicons dashicons-email" aria-hidden="true"></span>' . esc_html__( 'Email', 'alibiewi-console' ) . '</a>';
		}
		if ( $url ) {
			echo '<a class="alc-btn alc-btn--soft" href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-sos" aria-hidden="true"></span>' . esc_html__( 'Support desk', 'alibiewi-console' ) . '</a>';
		}

		echo '</div>';
		if ( $hours ) {
			echo '<p class="alc-support__hours"><span class="dashicons dashicons-clock" aria-hidden="true"></span>' . esc_html( $hours ) . '</p>';
		}
		echo '</section>';
	}

	private static function panel_ai() {
		if ( ! alc_opt( 'ai_enabled' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$log = ALC_AI::recent_log( 5 );
		if ( ! $log ) {
			return;
		}
		self::card_open( __( 'What visitors asked', 'alibiewi-console' ), admin_url( 'options-general.php?page=alc-settings&tab=ai' ), __( 'See all', 'alibiewi-console' ) );
		$rows = array();
		foreach ( $log as $row ) {
			$rows[] = array(
				'title'       => $row['q'],
				'meta'        => '',
				'badge'       => empty( $row['f'] ) ? __( 'Not on the site', 'alibiewi-console' ) : '',
				'badge_state' => 'warn',
				'time'        => self::ago( (int) $row['t'] ),
				'url'         => admin_url( 'options-general.php?page=alc-settings&tab=ai' ),
			);
		}
		self::list_rows( $rows );
		self::card_close();
	}

	private static function panel_guides() {
		$guides = alc_tutorials();
		if ( ! $guides ) {
			return;
		}
		self::card_open( __( 'How-to guides', 'alibiewi-console' ) );
		echo '<ul class="alc-guides">';
		foreach ( $guides as $guide ) {
			echo '<li><a href="' . esc_url( $guide['url'] ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span><span>' . esc_html( $guide['title'] ) . '</span></a></li>';
		}
		echo '</ul>';
		self::card_close();
	}
}
