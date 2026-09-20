<?php
/**
 * Plugin Name:       Elementor Form + Google Drive Integration Premium Add-on
 * Description:       Connect Elementor Pro Form to Google Drive — automatically upload file attachments and sync form submissions directly to Google Drive folders.
 * Version:           1.1.0
 * Author:            GravityExtra
 * Author URI:        https://gravityextra.com/
 * License:           GPL v2 or later
 * Text Domain:       elementor-google-drive-addon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELEMENTOR_GOOGLE_DRIVE_ADDON_VERSION', '1.1.0' );
define( 'ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELEMENTOR_GOOGLE_DRIVE_ADDON_SLUG', 'elementor-google-drive-addon' );

final class Elementor_Google_Drive_Extension {

	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'i18n' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function i18n() {
		load_plugin_textdomain( 'elementor-google-drive-addon' );
	}

	public function init() {
		require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-google-drive-api.php';

		if ( is_admin() ) {
			require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-license-manager.php';
			require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-admin-settings.php';
			require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-plugin-updater.php';

			$license_manager = new Elementor_Google_Drive_License_Manager(
				ELEMENTOR_GOOGLE_DRIVE_ADDON_SLUG,
				'Elementor Form + Google Drive Integration Premium Add-on'
			);

			new Elementor_Google_Drive_Admin_Settings( $license_manager );
			new Elementor_Google_Drive_Plugin_Updater( __FILE__, ELEMENTOR_GOOGLE_DRIVE_ADDON_SLUG, 'ELEMENTOR_GOOGLE_DRIVE_ADDON_VERSION' );

			add_action( 'admin_notices', array( $this, 'admin_notice_missing_elementor_pro' ) );
		}

		// Modern Elementor Pro Form action hook (fires only when Elementor Pro is active)
		add_action( 'elementor_pro/forms/actions/register', array( $this, 'register_form_action_modern' ) );

		// Legacy Elementor Pro Form fallback
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_form_action_legacy' ) );
	}

	public function admin_notice_missing_elementor_pro() {
		if ( class_exists( '\ElementorPro\Plugin' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Elementor Form + Google Drive Integration requires Elementor Pro Form to be installed and activated.', 'elementor-google-drive-addon' ); ?></p>
		</div>
		<?php
	}

	public function register_form_action_modern( $form_actions_registrar ) {
		if ( ! class_exists( '\ElementorPro\Modules\Forms\Classes\Integration_Base' ) && ! class_exists( '\ElementorPro\Modules\Forms\Classes\Action_Base' ) ) {
			return;
		}
		require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-action-google-drive.php';
		$form_actions_registrar->register( new Elementor_Google_Drive_Form_Action() );
	}

	public function register_form_action_legacy() {
		if ( did_action( 'elementor_pro/forms/actions/register' ) ) {
			return;
		}
		if ( ! class_exists( '\ElementorPro\Plugin' ) || ! isset( \ElementorPro\Plugin::instance()->modules_manager ) ) {
			return;
		}
		$forms_module = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' );
		if ( ! $forms_module || ! method_exists( $forms_module, 'add_form_action' ) ) {
			return;
		}
		if ( ! class_exists( '\ElementorPro\Modules\Forms\Classes\Integration_Base' ) && ! class_exists( '\ElementorPro\Modules\Forms\Classes\Action_Base' ) ) {
			return;
		}
		require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-action-google-drive.php';
		$action = new Elementor_Google_Drive_Form_Action();
		$forms_module->add_form_action( $action->get_name(), $action );
	}
}

Elementor_Google_Drive_Extension::instance();
