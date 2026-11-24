<?php
/**
 * Client mode: a "Site Owner" role for clients who run the website day to
 * day without access to plugins, themes, updates, settings or administrator
 * accounts. Restrictions are real capabilities, not hidden menus, so a
 * typed-in URL cannot get around them. Extra menus can also be hidden.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Client_Mode {

	const ROLE = 'alc_client';

	public static function init() {
		// Keep the role in step with the settings.
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync_role' ) );
		add_action( 'update_option_' . ALC_Settings::OPTION, array( __CLASS__, 'sync_role' ), 20 );

		// Safeguards apply whenever anyone holds the role.
		add_filter( 'editable_roles', array( __CLASS__, 'editable_roles' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'protect_admins' ), 10, 4 );

		add_action( 'admin_post_alc_set_role', array( __CLASS__, 'handle_set_role' ) );

		if ( ! alc_opt( 'client_mode' ) ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'hide_menus' ), 9999 );
		add_action( 'current_screen', array( __CLASS__, 'guard_screen' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'hide_notices' ), 1000 );
		add_action( 'admin_head', array( __CLASS__, 'admin_css' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar_cleanup' ), 999 );
	}

	/* --------------------------------------------------------------------
	 * Who is a client
	 * ------------------------------------------------------------------ */

	/**
	 * @param int|null $user_id User ID, current user when null.
	 * @return bool
	 */
	public static function is_client( $user_id = null ) {
		$user = null === $user_id ? wp_get_current_user() : get_userdata( $user_id );
		return $user && $user->exists() && in_array( self::ROLE, (array) $user->roles, true );
	}

	/**
	 * @return WP_User[]
	 */
	public static function clients() {
		return get_users(
			array(
				'role'    => self::ROLE,
				'orderby' => 'display_name',
				'number'  => 200,
			)
		);
	}

	/* --------------------------------------------------------------------
	 * The role
	 * ------------------------------------------------------------------ */

	/**
	 * Capabilities a Site Owner gets, built from the settings.
	 *
	 * @return array<string, bool>
	 */
	public static function caps() {
		$editor = get_role( 'editor' );
		$caps   = $editor ? $editor->capabilities : array( 'read' => true );
		$caps   = array_filter( $caps );

		if ( alc_opt( 'client_appearance' ) ) {
			$caps['edit_theme_options'] = true;
		}

		if ( alc_opt( 'client_users' ) ) {
			foreach ( array( 'list_users', 'create_users', 'edit_users', 'delete_users', 'promote_users', 'remove_users' ) as $cap ) {
				$caps[ $cap ] = true;
			}
		}

		if ( alc_opt( 'client_store' ) && class_exists( 'WooCommerce' ) ) {
			$manager = get_role( 'shop_manager' );
			if ( $manager ) {
				foreach ( array_filter( $manager->capabilities ) as $cap => $grant ) {
					if ( preg_match( '/woocommerce|product|shop_order|shop_coupon|shop_webhook/', $cap ) ) {
						$caps[ $cap ] = true;
					}
				}
			}
		}

		if ( alc_opt( 'client_forms' ) ) {
			foreach ( array( 'fluentform_dashboard_access', 'fluentform_entries_viewer', 'fluentform_manage_entries', 'fluentform_view_payments' ) as $cap ) {
				$caps[ $cap ] = true;
			}
		}

		// Never, whatever another plugin added to the editor role.
		$never = array( 'manage_options', 'activate_plugins', 'install_plugins', 'edit_plugins', 'delete_plugins', 'update_plugins', 'install_themes', 'switch_themes', 'edit_themes', 'delete_themes', 'update_themes', 'update_core', 'edit_files', 'import', 'export', 'manage_network', 'unfiltered_upload', 'create_sites', 'delete_sites' );
		foreach ( $never as $cap ) {
			unset( $caps[ $cap ] );
		}

		/**
		 * Filter Site Owner capabilities.
		 *
		 * @param array $caps Capability => true.
		 */
		$caps = apply_filters( 'alc_client_caps', $caps );
		ksort( $caps );
		return $caps;
	}

	/**
	 * Re-check now and then, so new plugins (WooCommerce, Fluent Forms)
	 * are picked up without a manual save.
	 */
	public static function maybe_sync_role() {
		if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		$want = alc_opt( 'client_mode' ) ? md5( wp_json_encode( self::caps() ) ) : 'off';
		if ( get_option( 'alc_client_role_hash' ) !== $want || ( alc_opt( 'client_mode' ) && ! get_role( self::ROLE ) ) ) {
			self::sync_role();
		}
	}

	public static function sync_role() {
		if ( ! alc_opt( 'client_mode' ) ) {
			// Only drop the role if nobody would be left without one.
			if ( get_role( self::ROLE ) && ! self::clients() ) {
				remove_role( self::ROLE );
			}
			update_option( 'alc_client_role_hash', 'off', false );
			return;
		}

		$caps = self::caps();
		$role = get_role( self::ROLE );

		if ( ! $role ) {
			add_role( self::ROLE, __( 'Site Owner', 'alibiewi-console' ), $caps );
		} else {
			foreach ( array_keys( $role->capabilities ) as $cap ) {
				if ( empty( $caps[ $cap ] ) ) {
					$role->remove_cap( $cap );
				}
			}
			foreach ( $caps as $cap => $grant ) {
				if ( empty( $role->capabilities[ $cap ] ) ) {
					$role->add_cap( $cap );
				}
			}
		}

		update_option( 'alc_client_role_hash', md5( wp_json_encode( $caps ) ), false );
	}

	/* --------------------------------------------------------------------
	 * Safeguards
	 * ------------------------------------------------------------------ */

	/**
	 * Site Owners can never hand out administrator-level roles.
	 *
	 * @param array $roles Roles.
	 * @return array
	 */
	public static function editable_roles( $roles ) {
		if ( ! self::is_client() ) {
			return $roles;
		}
		foreach ( $roles as $key => $role ) {
			$caps = isset( $role['capabilities'] ) ? (array) $role['capabilities'] : array();
			$powerful = ! empty( $caps['manage_options'] ) || ! empty( $caps['activate_plugins'] ) || ( ! empty( $caps['promote_users'] ) && self::ROLE !== $key );
			if ( 'administrator' === $key || $powerful ) {
				unset( $roles[ $key ] );
			}
		}
		return $roles;
	}

	/**
	 * Site Owners cannot edit, delete or change the role of administrators.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id Acting user.
	 * @param array    $args    Args, [0] is the target user.
	 * @return string[]
	 */
	public static function protect_admins( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_user', 'delete_user', 'remove_user', 'promote_user' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$target = (int) $args[0];
		if ( $target === (int) $user_id || ! self::is_client( $user_id ) ) {
			return $caps;
		}
		$other = get_userdata( $target );
		if ( $other && ( in_array( 'administrator', (array) $other->roles, true ) || $other->has_cap( 'manage_options' ) ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}

	/* --------------------------------------------------------------------
	 * Tidy wp-admin for clients
	 * ------------------------------------------------------------------ */

	/**
	 * @return string[]
	 */
	public static function hidden_menus() {
		$list = alc_opt( 'client_hidden_menus' );
		return is_array( $list ) ? array_values( array_filter( array_map( 'strval', $list ) ) ) : array();
	}

	/**
	 * Does this admin URL point at a menu hidden from the current client?
	 *
	 * @param string $url Admin URL.
	 * @return bool
	 */
	public static function url_hidden( $url ) {
		if ( ! alc_opt( 'client_mode' ) || ! self::is_client() ) {
			return false;
		}
		$hidden = self::hidden_menus();
		if ( ! $hidden ) {
			return false;
		}
		$path = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$page = isset( $query['page'] ) ? (string) $query['page'] : '';
		return in_array( $path, $hidden, true ) || ( '' !== $page && in_array( $page, $hidden, true ) );
	}

	public static function hide_menus() {
		if ( ! self::is_client() ) {
			return;
		}
		foreach ( self::hidden_menus() as $slug ) {
			remove_menu_page( $slug );
		}
	}

	/**
	 * A hidden menu stays hidden even when its URL is typed in.
	 *
	 * @param WP_Screen $screen Current screen.
	 */
	public static function guard_screen( $screen ) {
		if ( ! self::is_client() || wp_doing_ajax() ) {
			return;
		}
		$hidden = self::hidden_menus();
		if ( ! $hidden ) {
			return;
		}

		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		$page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$parent = function_exists( 'get_admin_page_parent' ) ? get_admin_page_parent() : '';

		foreach ( array( $pagenow, $page, $parent ) as $key ) {
			if ( $key && in_array( $key, $hidden, true ) ) {
				$home = alc_opt( 'dashboard_enabled' ) ? ALC_Dashboard::url() : admin_url( 'profile.php' );
				wp_safe_redirect( add_query_arg( 'alc_blocked', 1, $home ) );
				exit;
			}
		}
	}

	public static function hide_notices() {
		if ( ! self::is_client() || ! alc_opt( 'client_hide_notices' ) ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	public static function admin_css() {
		if ( ! self::is_client() || ! alc_opt( 'client_hide_help' ) ) {
			return;
		}
		echo '<style id="alc-client-css">#contextual-help-link-wrap,#contextual-help-wrap{display:none!important}</style>';
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return self::is_client() ? $classes . ' alc-client' : $classes;
	}

	/**
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function bar_cleanup( $bar ) {
		if ( ! self::is_client() ) {
			return;
		}
		foreach ( array( 'wp-logo', 'updates', 'customize' ) as $node ) {
			$bar->remove_node( $node );
		}
	}

	/* --------------------------------------------------------------------
	 * Settings screen pieces
	 * ------------------------------------------------------------------ */

	/**
	 * Top-level menu items an administrator sees, for the hide picker.
	 *
	 * @return array<int, array{slug:string,title:string,icon:string,caps:string[]}>
	 */
	public static function menu_items() {
		global $menu, $submenu;
		$out  = array();
		$skip = array( 'index.php', 'profile.php' );

		foreach ( (array) $menu as $item ) {
			if ( empty( $item[2] ) || ( isset( $item[4] ) && false !== strpos( $item[4], 'wp-menu-separator' ) ) || in_array( $item[2], $skip, true ) ) {
				continue;
			}
			$title = trim( wp_strip_all_tags( preg_replace( '#<span[^>]*>.*?</span>#s', '', (string) $item[0] ) ) );
			if ( '' === $title ) {
				continue;
			}
			$icon = isset( $item[6] ) ? (string) $item[6] : '';
			$caps = array( isset( $item[1] ) ? (string) $item[1] : 'read' );
			if ( ! empty( $submenu[ $item[2] ] ) ) {
				foreach ( $submenu[ $item[2] ] as $sub ) {
					$caps[] = isset( $sub[1] ) ? (string) $sub[1] : 'read';
				}
			}
			$out[] = array(
				'slug'  => (string) $item[2],
				'title' => $title,
				'icon'  => ( 0 === strpos( $icon, 'dashicons-' ) ) ? $icon : 'dashicons-admin-generic',
				'caps'  => array_unique( $caps ),
			);
		}
		return $out;
	}

	/**
	 * @param string $name  Field name.
	 * @param array  $value Hidden slugs.
	 */
	public static function render_menu_picker( $name, $value ) {
		$caps  = self::caps();
		$value = is_array( $value ) ? $value : array();

		echo '<div class="alc-menus">';
		foreach ( self::menu_items() as $item ) {
			$allowed = false;
			foreach ( $item['caps'] as $cap ) {
				if ( 'read' === $cap || ! empty( $caps[ $cap ] ) ) {
					$allowed = true;
					break;
				}
			}
			$checked = in_array( $item['slug'], $value, true );

			printf(
				'<label class="alc-menu-item%1$s"><input type="checkbox" name="%2$s[]" value="%3$s" %4$s %5$s><span class="dashicons %6$s" aria-hidden="true"></span><span class="alc-menu-item__title">%7$s</span><span class="alc-menu-item__state">%8$s</span></label>',
				$allowed ? '' : ' is-locked',
				esc_attr( $name ),
				esc_attr( $item['slug'] ),
				checked( $checked || ! $allowed, true, false ),
				disabled( ! $allowed, true, false ),
				esc_attr( $item['icon'] ),
				esc_html( $item['title'] ),
				$allowed ? esc_html__( 'Hide', 'alibiewi-console' ) : esc_html__( 'No access', 'alibiewi-console' )
			);
		}
		echo '</div>';
	}

	/**
	 * What Site Owners can and cannot do, at a glance.
	 */
	public static function render_summary() {
		$can = array( __( 'Pages, posts and media', 'alibiewi-console' ), __( 'Comments', 'alibiewi-console' ) );
		if ( alc_opt( 'client_appearance' ) ) {
			$can[] = __( 'Menus and site design', 'alibiewi-console' );
		}
		if ( alc_opt( 'client_users' ) ) {
			$can[] = __( 'Users below administrator', 'alibiewi-console' );
		}
		if ( alc_opt( 'client_store' ) && class_exists( 'WooCommerce' ) ) {
			$can[] = __( 'Orders, products and coupons', 'alibiewi-console' );
		}
		if ( alc_opt( 'client_forms' ) ) {
			$can[] = __( 'Form entries', 'alibiewi-console' );
		}
		$cannot = array( __( 'Plugins and themes', 'alibiewi-console' ), __( 'Updates', 'alibiewi-console' ), __( 'Site settings', 'alibiewi-console' ), __( 'Administrator accounts', 'alibiewi-console' ), __( 'Studio Console', 'alibiewi-console' ) );
		?>
		<div class="alc-summary">
			<div class="alc-summary__col">
				<p class="alc-summary__title"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Site Owners can manage', 'alibiewi-console' ); ?></p>
				<ul><?php foreach ( $can as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul>
			</div>
			<div class="alc-summary__col is-locked">
				<p class="alc-summary__title"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e( 'Only you can manage', 'alibiewi-console' ); ?></p>
				<ul><?php foreach ( $cannot as $item ) : ?><li><?php echo esc_html( $item ); ?></li><?php endforeach; ?></ul>
			</div>
		</div>
		<?php
	}

	/**
	 * People with the role, and a quick way to switch someone over.
	 */
	public static function render_users_card() {
		$clients = self::clients();
		$others  = get_users(
			array(
				'role__not_in' => array( self::ROLE ),
				'exclude'      => array( get_current_user_id() ),
				'orderby'      => 'display_name',
				'number'       => 200,
			)
		);
		$ready   = alc_opt( 'client_mode' ) && get_role( self::ROLE );
		?>
		<section class="alc-set-card alc-owners">
			<h2 class="alc-set-card__title"><?php esc_html_e( 'Site Owners', 'alibiewi-console' ); ?></h2>
			<p class="alc-set-card__text"><?php esc_html_e( 'Give your client the Site Owner role instead of Administrator. You can switch anyone back at any time.', 'alibiewi-console' ); ?></p>

			<?php if ( ! $ready ) : ?>
				<p class="alc-owners__note"><?php esc_html_e( 'Turn on client mode above and save to create the Site Owner role.', 'alibiewi-console' ); ?></p>
			<?php else : ?>
				<?php if ( $clients ) : ?>
					<ul class="alc-owners__list">
						<?php foreach ( $clients as $user ) : ?>
							<li class="alc-owners__row">
								<?php echo get_avatar( $user->ID, 36, '', '', array( 'class' => 'alc-owners__avatar' ) ); ?>
								<span class="alc-owners__who">
									<strong><?php echo esc_html( $user->display_name ); ?></strong>
									<span><?php echo esc_html( $user->user_email ); ?></span>
								</span>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Give this person full administrator access?', 'alibiewi-console' ) ); ?>');">
									<input type="hidden" name="action" value="alc_set_role">
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
									<input type="hidden" name="role" value="administrator">
									<?php wp_nonce_field( 'alc_set_role_' . $user->ID ); ?>
									<button type="submit" class="button-link alc-owners__revert"><?php esc_html_e( 'Make administrator', 'alibiewi-console' ); ?></button>
								</form>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="alc-owners__note"><?php esc_html_e( 'Nobody has the Site Owner role yet.', 'alibiewi-console' ); ?></p>
				<?php endif; ?>

				<?php if ( $others ) : ?>
					<form class="alc-owners__add" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="alc_set_role">
						<input type="hidden" name="role" value="<?php echo esc_attr( self::ROLE ); ?>">
						<?php wp_nonce_field( 'alc_set_role_any' ); ?>
						<label class="screen-reader-text" for="alc_owner_user"><?php esc_html_e( 'User', 'alibiewi-console' ); ?></label>
						<select id="alc_owner_user" name="user_id">
							<?php
							foreach ( $others as $user ) {
								$roles = array_map( 'translate_user_role', array_map( array( __CLASS__, 'role_name' ), (array) $user->roles ) );
								printf( '<option value="%1$d">%2$s (%3$s)</option>', (int) $user->ID, esc_html( $user->display_name ), esc_html( implode( ', ', $roles ) ) );
							}
							?>
						</select>
						<button type="submit" class="button alc-owners__make"><?php esc_html_e( 'Make Site Owner', 'alibiewi-console' ); ?></button>
					</form>
					<p class="alc-owners__hint"><?php esc_html_e( 'Your own account is not listed, so you can never lock yourself out.', 'alibiewi-console' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * @param string $slug Role slug.
	 * @return string
	 */
	public static function role_name( $slug ) {
		$roles = wp_roles()->roles;
		return isset( $roles[ $slug ]['name'] ) ? $roles[ $slug ]['name'] : $slug;
	}

	public static function handle_set_role() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'promote_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'alibiewi-console' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified right below.
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$role    = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		// phpcs:enable

		if ( self::ROLE === $role ) {
			check_admin_referer( 'alc_set_role_any' );
		} else {
			check_admin_referer( 'alc_set_role_' . $user_id );
		}

		$back = ALC_Admin_Settings::url( 'client' );
		$user = get_userdata( $user_id );

		if ( ! $user || get_current_user_id() === $user_id || ! in_array( $role, array( 'administrator', self::ROLE ), true ) || ! get_role( $role ) ) {
			wp_safe_redirect( add_query_arg( 'alc_notice', 'role_failed', $back ) );
			exit;
		}

		$user->set_role( $role );
		wp_safe_redirect( add_query_arg( 'alc_notice', self::ROLE === $role ? 'role_client' : 'role_admin', $back ) );
		exit;
	}
}
