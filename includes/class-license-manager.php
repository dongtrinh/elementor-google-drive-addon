<?php
/**
 * Elementor Form + Google Drive Integration License Manager
 *
 * @package Elementor_Google_Drive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Google_Drive_License_Manager {

	private $slug;
	private $item_name;
	private $api_url = 'https://gravityextra.com/wp-json/ge-api/v1/license/';

	/**
	 * MD5 hashes of GravityExtra WooCommerce Product IDs:
	 * - 39759: Elementor Form + Google Drive Integration Premium Addon - 1 Site Plan
	 * - 39760: Elementor Form + Google Drive Integration Premium Addon - 5 Site Plan
	 * - 39761: Elementor Form + Google Drive Integration Premium Addon - 10 Site Plan
	 */
	private $gravity_extra_ids = array(
		'1'  => 'd2cec4ae765e49fa93728f323ca5894e',
		'5'  => 'e5c8f30b30415b1fc94d820ba9d4d08c',
		'10' => '082e8c0e2b18920a3d5fcc6c3c109114',
	);

	public function __construct( $slug, $item_name, $api_url = '' ) {
		$this->slug      = sanitize_key( $slug );
		$this->item_name = sanitize_text_field( $item_name );
		if ( ! empty( $api_url ) ) {
			$this->api_url = esc_url_raw( $api_url );
		}

		add_action( 'admin_init', array( $this, 'handle_license_action' ) );
	}

	public function is_valid() {
		return 'valid' === get_option( 'ge_' . $this->slug . '_license_status', 'invalid' );
	}

	public function get_license_key() {
		return get_option( 'ge_' . $this->slug . '_license_key', '' );
	}

	public function get_status() {
		return get_option( 'ge_' . $this->slug . '_license_status', 'invalid' );
	}

	public function get_expires() {
		return get_option( 'ge_' . $this->slug . '_license_expires', '' );
	}

	public function handle_license_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check activation submit
		if ( isset( $_POST[ $this->slug . '_license_activate' ] ) && check_admin_referer( $this->slug . '_license_nonce', $this->slug . '_license_nonce_field' ) ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST[ $this->slug . '_license_key' ] ?? '' ) );
			$this->activate( $license_key );
		}

		// Check deactivation submit
		if ( isset( $_POST[ $this->slug . '_license_deactivate' ] ) && check_admin_referer( $this->slug . '_license_nonce', $this->slug . '_license_nonce_field' ) ) {
			$this->deactivate();
		}
	}

	public function activate( $license_key ) {
		$license_key = trim( $license_key );

		if ( empty( $license_key ) ) {
			add_settings_error( $this->slug . '_notices', 'empty_key', __( 'Please enter a valid GravityExtra License Key.', 'elementor-google-drive-addon' ), 'error' );
			return false;
		}

		$activated = false;
		$last_error_msg = __( 'Invalid or expired License Key.', 'elementor-google-drive-addon' );

		// Iterate through product ID tier hashes (1, 5, 10 sites)
		foreach ( $this->gravity_extra_ids as $tier => $ge_id ) {
			$api_params = array(
				'edd_action'        => 'activate_license',
				'license'           => $license_key,
				'_gravity_extra_id' => $ge_id,
				'item_name'         => rawurlencode( $this->item_name ),
				'url'               => home_url(),
			);

			$response = wp_remote_post(
				$this->api_url,
				array(
					'timeout'   => 15,
					'sslverify' => true,
					'body'      => $api_params,
				)
			);

			if ( is_wp_error( $response ) ) {
				add_settings_error( $this->slug . '_notices', 'api_conn_error', sprintf( __( 'Connection to GravityExtra license server failed: %s', 'elementor-google-drive-addon' ), $response->get_error_message() ), 'error' );
				return false;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! empty( $license_data['success'] ) || ( isset( $license_data['license'] ) && 'valid' === $license_data['license'] ) ) {
				update_option( 'ge_' . $this->slug . '_license_key', $license_key );
				update_option( 'ge_' . $this->slug . '_license_status', 'valid' );
				update_option( 'ge_' . $this->slug . '_license_tier', $tier );
				if ( ! empty( $license_data['expires'] ) ) {
					update_option( 'ge_' . $this->slug . '_license_expires', $license_data['expires'] );
				}
				add_settings_error( $this->slug . '_notices', 'activated', __( 'GravityExtra License activated successfully! Automatic updates and premium features are enabled.', 'elementor-google-drive-addon' ), 'updated' );
				$activated = true;
				break;
			}

			if ( ! empty( $license_data['msg'] ) ) {
				$last_error_msg = $license_data['msg'];
			} elseif ( ! empty( $license_data['error'] ) ) {
				$last_error_msg = $this->get_error_message( $license_data['error'] );
			}
		}

		if ( ! $activated ) {
			update_option( 'ge_' . $this->slug . '_license_status', 'invalid' );
			add_settings_error( $this->slug . '_notices', 'invalid_key', $last_error_msg, 'error' );
			return false;
		}

		return true;
	}

	public function deactivate() {
		$license_key = $this->get_license_key();

		if ( ! empty( $license_key ) ) {
			foreach ( $this->gravity_extra_ids as $ge_id ) {
				wp_remote_post(
					$this->api_url,
					array(
						'timeout'   => 15,
						'sslverify' => true,
						'body'      => array(
							'edd_action'        => 'deactivate_license',
							'license'           => $license_key,
							'_gravity_extra_id' => $ge_id,
							'item_name'         => rawurlencode( $this->item_name ),
							'url'               => home_url(),
						),
					)
				);
			}
		}

		delete_option( 'ge_' . $this->slug . '_license_key' );
		update_option( 'ge_' . $this->slug . '_license_status', 'invalid' );
		delete_option( 'ge_' . $this->slug . '_license_expires' );
		delete_option( 'ge_' . $this->slug . '_license_tier' );
		add_settings_error( $this->slug . '_notices', 'deactivated', __( 'License deactivated successfully.', 'elementor-google-drive-addon' ), 'updated' );
		return true;
	}

	private function get_error_message( $error ) {
		switch ( $error ) {
			case 'expired':
				return __( 'Your license key has expired. Please renew it to receive updates.', 'elementor-google-drive-addon' );
			case 'disabled':
			case 'revoked':
				return __( 'Your license key has been disabled.', 'elementor-google-drive-addon' );
			case 'missing':
				return __( 'Invalid license key. Please check your purchase email.', 'elementor-google-drive-addon' );
			case 'invalid':
			case 'site_inactive':
				return __( 'Your license is not active for this URL.', 'elementor-google-drive-addon' );
			case 'item_name_mismatch':
				return sprintf( __( 'This license key is not valid for %s.', 'elementor-google-drive-addon' ), $this->item_name );
			case 'no_activations_left':
				return __( 'Your license key has reached its activation limit.', 'elementor-google-drive-addon' );
			default:
				return __( 'An error occurred while validating the license key.', 'elementor-google-drive-addon' );
		}
	}

	public function render_settings_card() {
		$is_valid    = $this->is_valid();
		$license_key = $this->get_license_key();
		$expires     = $this->get_expires();
		?>
		<div class="ge-license-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); max-width: 800px;">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
				<h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
					<span>🔑</span> <?php esc_html_e( 'GravityExtra License Activation', 'elementor-google-drive-addon' ); ?>
				</h3>
				<?php if ( $is_valid ) : ?>
					<span style="background: #dcfce7; color: #15803d; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 5px;">
						<span style="width: 6px; height: 6px; background: #16a34a; border-radius: 50%;"></span>
						<?php esc_html_e( 'Active & Verified', 'elementor-google-drive-addon' ); ?>
					</span>
				<?php else : ?>
					<span style="background: #fee2e2; color: #b91c1c; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 5px;">
						<span style="width: 6px; height: 6px; background: #dc2626; border-radius: 50%;"></span>
						<?php esc_html_e( 'Not Activated', 'elementor-google-drive-addon' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<p style="color: #64748b; font-size: 13px; margin: 0 0 16px 0; line-height: 1.5;">
				<?php esc_html_e( 'Enter your GravityExtra license key below to unlock automatic plugin updates, priority developer support, and seamless integration features.', 'elementor-google-drive-addon' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( $this->slug . '_license_nonce', $this->slug . '_license_nonce_field' ); ?>
				<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
					<input type="text"
						   name="<?php echo esc_attr( $this->slug ); ?>_license_key"
						   value="<?php echo esc_attr( $license_key ); ?>"
						   placeholder="e.g. 9f8e7d6c5b4a3..."
						   <?php echo $is_valid ? 'readonly' : ''; ?>
						   style="flex: 1; min-width: 280px; height: 38px; padding: 0 12px; border: 1px solid <?php echo $is_valid ? '#cbd5e1' : '#059669'; ?>; border-radius: 6px; font-family: monospace; font-size: 13px; background: <?php echo $is_valid ? '#f8fafc' : '#ffffff'; ?>;" />

					<?php if ( ! $is_valid ) : ?>
						<button type="submit"
								name="<?php echo esc_attr( $this->slug ); ?>_license_activate"
								class="button button-primary"
								style="height: 38px; padding: 0 18px; border-radius: 6px; font-weight: 700; background: #059669; border-color: #059669; box-shadow: none;">
							<?php esc_html_e( 'Activate License', 'elementor-google-drive-addon' ); ?>
						</button>
					<?php else : ?>
						<button type="submit"
								name="<?php echo esc_attr( $this->slug ); ?>_license_deactivate"
								class="button button-secondary"
								style="height: 38px; padding: 0 18px; border-radius: 6px; font-weight: 600; color: #dc2626; border-color: #fca5a5;">
							<?php esc_html_e( 'Deactivate', 'elementor-google-drive-addon' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<?php if ( $is_valid && ! empty( $expires ) ) : ?>
					<p style="margin: 8px 0 0 0; font-size: 12px; color: #64748b;">
						<?php printf( esc_html__( 'License validity: %s', 'elementor-google-drive-addon' ), esc_html( 'lifetime' === $expires ? 'Lifetime' : date_i18n( get_option( 'date_format' ), strtotime( $expires ) ) ) ); ?>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
