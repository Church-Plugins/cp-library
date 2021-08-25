<?php

namespace SC_Library\Admin;

use SC_Library\Init as SC_Library;

class Settings {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of \SC_Library\Settings
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Settings ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Add Hooks and Actions
	 */
	protected function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ), 50 );
		add_action( 'admin_menu', array( $this, 'admin_menu'        ), 50 );
		add_action( 'admin_notices', array( $this, 'license_admin_notice' ) );
	}

	/**
	 * Register the settings
	 *
	 * @access      public
	 * @since       1.0.0
	 */
	public function register_settings() {
		register_setting( 'sc_library_settings_group', 'sc_library_license_key', array( $this, 'sanitize_license' ) );
	}

	/**
	 * Add the menu item
	 *
	 * @access      public
	 * @since       1.0.0
	 * @return      void
	 */
	public function admin_menu() {
		add_submenu_page( 'settings', __( 'Church Plugins Library Settings', 'cp-library' ), __( 'CP Library', 'cp-library' ), 'manage_options', cp_library()->get_id(), array( $this, 'settings_page' ) );
	}

	public function settings_page() {
		$license  = get_option( 'sc_library_license_key', '' );
		$status   = get_option( 'sc_library_license_status', '' );

		if ( isset( $_REQUEST['updated'] ) && $_REQUEST['updated'] !== false ) : ?>
			<div class="updated fade"><p><strong><?php _e( 'Options saved', 'cp-library' ); ?></strong></p></div>
		<?php endif; ?>

		<div class="rcpbp-wrap">

			<h2 class="rcpbp-settings-title"><?php echo esc_html( get_admin_page_title() ); ?></h2><hr>

			<form method="post" action="options.php" class="rcp_options_form">
				<?php settings_fields( 'sc_library_settings_group' ); ?>

				<table class="form-table">
					<tr>
						<th>
							<label for="sc_library_license_key"><?php _e( 'License Key', 'cp-library' ); ?></label>
						</th>
						<td>
							<p><input class="regular-text" type="text" id="sc_library_license_key" name="sc_library_license_key" value="<?php echo esc_attr( $license ); ?>" />
							<?php if( $status == 'valid' ) : ?>
								<?php wp_nonce_field( 'sc_library_deactivate_license', 'sc_library_deactivate_license' ); ?>
								<?php submit_button( 'Deactivate License', 'secondary', 'sc_library_license_deactivate', false ); ?>
								<span style="color:green">&nbsp;&nbsp;<?php _e( 'active', 'cp-library' ); ?></span>
							<?php elseif( 'invalid' == $status ) : ?>
								<?php echo $status; ?>
							<?php elseif( $license ) : ?>
								<?php submit_button( 'Activate License', 'secondary', 'sc_library_license_activate', false ); ?>
							<?php endif; ?></p>

							<p class="description"><?php printf( __( 'Enter your Church Plugins - Library license key. This is required for automatic updates and <a href="%s">support</a>.', 'cp-library' ), cp_library()->get_support_url() ); ?></p>
						</td>
					</tr>

				</table>

				<?php settings_fields( 'sc_library_settings_group' ); ?>
				<?php wp_nonce_field( 'sc_library_nonce', 'sc_library_nonce' ); ?>
				<?php submit_button( 'Save Options' ); ?>

			</form>
		</div>

	<?php
	}


	public function sanitize_license( $new ) {
		$old = get_option( 'sc_library_license_key' );
		if ( $old && $old != $new ) {
			delete_option( 'sc_library_license_key' ); // new license has been entered, so must reactivate
		}

		return $new;
	}

	/**
	 * This is a means of catching errors from the activation method above and displaying it to the customer
	 */
	public function license_admin_notice() {
		if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

			switch ( $_GET['sl_activation'] ) {

				case 'false':
					$message = urldecode( $_GET['message'] );
					?>
					<div class="error">
						<p><?php echo $message; ?></p>
					</div>
					<?php
					break;

				case 'true':
				default:
					// Developers can put a custom success message here for when activation is successful if they way.
					break;

			}
		}
	}


}
