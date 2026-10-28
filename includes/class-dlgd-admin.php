<?php
/**
 * Admin screens.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and activity screens, under their own menu because the plugin works with
 * several download platforms.
 */
final class DLGD_Admin {

	const SETTINGS_PAGE = 'download-guard';
	const ACTIVITY_PAGE = 'download-guard-activity';

	/**
	 * Users shown in each "top downloaders" table.
	 */
	const TOP_USERS = 25;

	/**
	 * Hooks the admin screens.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_pages' ) );
		add_action( 'admin_post_dlgd_block', array( __CLASS__, 'handle_block' ) );
		add_action( 'admin_post_dlgd_unblock', array( __CLASS__, 'handle_unblock' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DLGD_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * URL of the settings screen.
	 *
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'admin.php?page=' . self::SETTINGS_PAGE );
	}

	/**
	 * URL of the activity screen.
	 *
	 * @return string
	 */
	public static function activity_url() {
		return admin_url( 'admin.php?page=' . self::ACTIVITY_PAGE );
	}

	/**
	 * Registers the option.
	 */
	public static function register_setting() {
		register_setting(
			'dlgd',
			DLGD_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'DLGD_Settings', 'sanitize' ),
				'default'           => DLGD_Settings::defaults(),
			)
		);
	}

	/**
	 * Adds the menu and its screens.
	 */
	public static function add_pages() {
		add_menu_page(
			__( 'Download Guard', 'download-guard' ),
			__( 'Download Guard', 'download-guard' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings' ),
			'dashicons-shield',
			81
		);
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Download Guard Settings', 'download-guard' ),
			__( 'Settings', 'download-guard' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings' )
		);
		add_submenu_page(
			self::SETTINGS_PAGE,
			__( 'Download Activity', 'download-guard' ),
			__( 'Activity', 'download-guard' ),
			'manage_options',
			self::ACTIVITY_PAGE,
			array( __CLASS__, 'render_activity' )
		);
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::settings_url() ), esc_html__( 'Settings', 'download-guard' ) )
		);
		return $links;
	}

	/**
	 * Shows which platforms are active and any problem with them.
	 */
	private static function render_platform_notices() {
		$adapters = DLGD_Guard::get_adapters();

		if ( empty( $adapters ) ) {
			echo '<div class="notice notice-error"><p>';
			if ( function_exists( 'edd_get_payment' ) ) {
				esc_html_e( 'Easy Digital Downloads is too old: Download Guard needs version 3.0 or later.', 'download-guard' );
			} else {
				esc_html_e( 'No supported download plugin is active. Download Guard works with Download Monitor, Easy Digital Downloads 3 and WooCommerce.', 'download-guard' );
			}
			echo '</p></div>';
			return;
		}

		$labels = array();
		foreach ( $adapters as $adapter ) {
			$labels[] = $adapter->get_label();
			foreach ( $adapter->get_admin_warnings() as $warning ) {
				echo '<div class="notice notice-warning"><p>' . esc_html( $warning ) . '</p></div>';
			}
		}

		echo '<p>' . esc_html(
			sprintf(
				/* translators: %s: comma-separated list of download plugins. */
				__( 'Counting downloads from: %s.', 'download-guard' ),
				implode( ', ', $labels )
			)
		) . '</p>';
	}

	/**
	 * Renders the settings screen.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = DLGD_Settings::get();
		$option   = DLGD_Settings::OPTION;
		$roles    = wp_roles()->get_names();
		$fields   = array(
			'day'       => __( 'Per day', 'download-guard' ),
			'month'     => __( 'Per 30 days', 'download-guard' ),
			'new_day'   => __( 'New members: per day', 'download-guard' ),
			'new_month' => __( 'New members: per 30 days', 'download-guard' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php self::render_platform_notices(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'dlgd' ); ?>

				<h2><?php esc_html_e( 'Download limits', 'download-guard' ); ?></h2>
				<p><?php esc_html_e( 'Limits count each user\'s downloads over the last 24 hours and the last 30 days, both at the same time. Leave a field empty for no limit. Roles without any limit are not affected, and administrators are never limited. When a user has several limited roles, the most generous limit applies.', 'download-guard' ); ?></p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="dlgd-new-member-days"><?php esc_html_e( 'New-member period', 'download-guard' ); ?></label>
						</th>
						<td>
							<input type="number" min="0" step="1" class="small-text" id="dlgd-new-member-days"
								name="<?php echo esc_attr( $option ); ?>[new_member_days]"
								value="<?php echo esc_attr( $settings['new_member_days'] ); ?>">
							<?php esc_html_e( 'days', 'download-guard' ); ?>
							<p class="description"><?php esc_html_e( 'New-member limits apply during these first days after registration (with Restrict Content Pro, after the first membership). Leave them empty to use the regular limits.', 'download-guard' ); ?></p>
						</td>
					</tr>
				</table>

				<table class="widefat striped" style="max-width:900px">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Role', 'download-guard' ); ?></th>
							<?php foreach ( $fields as $label ) : ?>
								<th scope="col"><?php echo esc_html( $label ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $roles as $role => $name ) : ?>
							<?php
							if ( 'administrator' === $role ) {
								continue;
							}
							$rule = isset( $settings['roles'][ $role ] ) ? $settings['roles'][ $role ] : array();
							?>
							<tr>
								<td><?php echo esc_html( translate_user_role( $name ) ); ?> <code><?php echo esc_html( $role ); ?></code></td>
								<?php foreach ( $fields as $key => $label ) : ?>
									<td>
										<input type="number" min="0" step="1" class="small-text"
											aria-label="<?php echo esc_attr( translate_user_role( $name ) . ': ' . $label ); ?>"
											name="<?php echo esc_attr( $option . '[roles][' . $role . '][' . $key . ']' ); ?>"
											value="<?php echo ! empty( $rule[ $key ] ) ? esc_attr( $rule[ $key ] ) : ''; ?>">
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Bulk downloading', 'download-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Detection', 'download-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[burst][enabled]" value="1" <?php checked( $settings['burst']['enabled'] ); ?>>
								<?php esc_html_e( 'Detect accounts that download too much too fast', 'download-guard' ); ?>
							</label>
							<p>
								<?php
								printf(
									/* translators: 1: number input, 2: number input. */
									esc_html__( 'More than %1$s downloads within %2$s minutes.', 'download-guard' ),
									'<input type="number" min="1" step="1" class="small-text" aria-label="' . esc_attr__( 'Downloads', 'download-guard' ) . '" name="' . esc_attr( $option ) . '[burst][count]" value="' . esc_attr( $settings['burst']['count'] ) . '">',
									'<input type="number" min="1" max="1440" step="1" class="small-text" aria-label="' . esc_attr__( 'Minutes', 'download-guard' ) . '" name="' . esc_attr( $option ) . '[burst][minutes]" value="' . esc_attr( $settings['burst']['minutes'] ) . '">'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'When detected', 'download-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[burst][notify]" value="1" <?php checked( $settings['burst']['notify'] ); ?>>
								<?php esc_html_e( 'Email an alert to', 'download-guard' ); ?>
							</label>
							<input type="email" class="regular-text" aria-label="<?php esc_attr_e( 'Alert email address', 'download-guard' ); ?>"
								name="<?php echo esc_attr( $option ); ?>[burst][email]"
								value="<?php echo esc_attr( $settings['burst']['email'] ); ?>"
								placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[burst][block]" value="1" <?php checked( $settings['burst']['block'] ); ?>>
								<?php esc_html_e( 'Block the account from downloading until an administrator unblocks it', 'download-guard' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sign-up filter', 'download-guard' ); ?></h2>
				<p><?php esc_html_e( 'Rejects throwaway accounts on the WordPress, Restrict Content Pro, Easy Digital Downloads and WooCommerce registration forms.', 'download-guard' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Reject', 'download-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[signup][numeric_usernames]" value="1" <?php checked( $settings['signup']['numeric_usernames'] ); ?>>
								<?php esc_html_e( 'Usernames made only of numbers', 'download-guard' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[signup][disposable_emails]" value="1" <?php checked( $settings['signup']['disposable_emails'] ); ?>>
								<?php esc_html_e( 'Disposable email addresses', 'download-guard' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[signup][check_mx]" value="1" <?php checked( $settings['signup']['check_mx'] ); ?>>
								<?php esc_html_e( 'Email domains that cannot receive mail (DNS check)', 'download-guard' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the activity screen: top downloaders and blocked accounts.
	 */
	public static function render_activity() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects a notice after a redirect from a nonce-checked action.
		$notice = isset( $_GET['dlgd_notice'] ) ? sanitize_key( wp_unslash( $_GET['dlgd_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( 'blocked' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Account blocked from downloading.', 'download-guard' ); ?></p></div>
			<?php elseif ( 'unblocked' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Account unblocked.', 'download-guard' ); ?></p></div>
			<?php endif; ?>

			<?php self::render_platform_notices(); ?>

			<h2><?php esc_html_e( 'Blocked accounts', 'download-guard' ); ?></h2>
			<?php self::render_blocked_table(); ?>

			<h2><?php esc_html_e( 'Top downloaders in the last 24 hours', 'download-guard' ); ?></h2>
			<?php self::render_top_table( time() - DAY_IN_SECONDS ); ?>

			<h2><?php esc_html_e( 'Top downloaders in the last 30 days', 'download-guard' ); ?></h2>
			<?php self::render_top_table( time() - 30 * DAY_IN_SECONDS ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the blocked accounts.
	 */
	private static function render_blocked_table() {
		$users = DLGD_Blocks::get_blocked_users();
		if ( empty( $users ) ) {
			echo '<p>' . esc_html__( 'No account is blocked.', 'download-guard' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width:900px">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'User', 'download-guard' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Blocked on', 'download-guard' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reason', 'download-guard' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'download-guard' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $users as $user ) : ?>
					<?php $details = DLGD_Blocks::get( $user->ID ); ?>
					<tr>
						<td><?php self::render_user( $user ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), isset( $details['time'] ) ? $details['time'] : null ) ); ?></td>
						<td>
							<?php
							if ( isset( $details['reason'] ) && 'burst' === $details['reason'] ) {
								echo esc_html(
									sprintf(
										/* translators: 1: number of downloads, 2: number of minutes. */
										__( '%1$d downloads in %2$d minutes', 'download-guard' ),
										isset( $details['count'] ) ? $details['count'] : 0,
										isset( $details['minutes'] ) ? $details['minutes'] : 0
									)
								);
							} else {
								esc_html_e( 'Blocked by an administrator', 'download-guard' );
							}
							?>
						</td>
						<td><?php self::render_action_button( 'dlgd_unblock', $user->ID, __( 'Unblock', 'download-guard' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the users with the most downloads since a given time.
	 *
	 * @param int $since Timestamp.
	 */
	private static function render_top_table( $since ) {
		$top = DLGD_Guard::get_top_downloaders( $since, self::TOP_USERS );
		if ( empty( $top ) ) {
			echo '<p>' . esc_html__( 'No downloads by registered users in this period.', 'download-guard' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width:900px">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'User', 'download-guard' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Downloads', 'download-guard' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'download-guard' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'download-guard' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top as $user_id => $count ) : ?>
					<?php
					$user = get_userdata( $user_id );
					if ( ! $user ) {
						continue;
					}
					$blocked = null !== DLGD_Blocks::get( $user_id );
					?>
					<tr>
						<td><?php self::render_user( $user ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
						<td><?php echo $blocked ? esc_html__( 'Blocked', 'download-guard' ) : esc_html( self::describe_status( $user_id ) ); ?></td>
						<td>
							<?php
							if ( $blocked ) {
								self::render_action_button( 'dlgd_unblock', $user_id, __( 'Unblock', 'download-guard' ) );
							} elseif ( ! DLGD_Guard::is_exempt( $user_id ) ) {
								self::render_action_button( 'dlgd_block', $user_id, __( 'Block', 'download-guard' ) );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Short description of a user's limit status.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function describe_status( $user_id ) {
		if ( DLGD_Guard::is_exempt( $user_id ) ) {
			return __( 'Exempt', 'download-guard' );
		}
		$decision = DLGD_Guard::get_decision( $user_id );
		if ( $decision['allowed'] ) {
			return __( 'Can download', 'download-guard' );
		}
		return sprintf(
			/* translators: %s: date and time. */
			__( 'At the limit until %s', 'download-guard' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $decision['until'] )
		);
	}

	/**
	 * Renders a user's name with a link to their profile.
	 *
	 * @param WP_User $user User.
	 */
	private static function render_user( WP_User $user ) {
		printf(
			'<a href="%s">%s</a><br><span class="description">%s</span>',
			esc_url( get_edit_user_link( $user->ID ) ),
			esc_html( $user->user_login ),
			esc_html( $user->user_email )
		);
	}

	/**
	 * Renders a small form that blocks or unblocks a user.
	 *
	 * @param string $action  admin-post action.
	 * @param int    $user_id User ID.
	 * @param string $label   Button label.
	 */
	private static function render_action_button( $action, $user_id, $label ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
			<?php wp_nonce_field( $action . '_' . $user_id ); ?>
			<?php submit_button( $label, 'small', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Blocks a user from the activity screen.
	 */
	public static function handle_block() {
		$user_id = self::verify_action( 'dlgd_block' );
		if ( ! DLGD_Guard::is_exempt( $user_id ) ) {
			DLGD_Blocks::block( $user_id, array( 'reason' => 'manual' ) );
		}
		self::redirect( 'blocked' );
	}

	/**
	 * Unblocks a user from the activity screen.
	 */
	public static function handle_unblock() {
		DLGD_Blocks::unblock( self::verify_action( 'dlgd_unblock' ) );
		self::redirect( 'unblocked' );
	}

	/**
	 * Checks permissions and the nonce of a block/unblock request.
	 *
	 * @param string $action admin-post action.
	 * @return int User ID.
	 */
	private static function verify_action( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'download-guard' ), '', array( 'response' => 403 ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( $action . '_' . $user_id );

		if ( ! get_userdata( $user_id ) ) {
			wp_die( esc_html__( 'User not found.', 'download-guard' ), '', array( 'response' => 404 ) );
		}
		return $user_id;
	}

	/**
	 * Goes back to the activity screen with a notice.
	 *
	 * @param string $notice Notice key.
	 */
	private static function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( 'dlgd_notice', $notice, self::activity_url() ) );
		exit;
	}
}
