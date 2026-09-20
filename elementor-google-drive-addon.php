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

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ELEMENTOR_GOOGLE_DRIVE_ADDON_VERSION', '1.1.0' );
define( 'ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

final class Elementor_Google_Drive_Extension {
    private static $_instance = null;
    private $license_manager  = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) { self::$_instance = new self(); }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'init', [ $this, 'i18n' ] );
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function i18n() { load_plugin_textdomain( 'elementor-google-drive-addon' ); }

    public function init() {
        require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-license-manager.php';
        $this->license_manager = new Elementor_Google_Drive_License_Manager( 'elementor-google-drive-addon', 'Elementor Form + Google Drive Integration Premium Add-on' );

        require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-google-drive-api.php';
        require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-action-google-drive.php';

        if ( is_admin() ) {
            require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-admin-settings.php';
            require_once ELEMENTOR_GOOGLE_DRIVE_ADDON_PLUGIN_DIR . 'includes/class-plugin-updater.php';
            new Elementor_Google_Drive_Admin_Settings( $this->license_manager );
            new Elementor_Google_Drive_Plugin_Updater( __FILE__, 'elementor-google-drive-addon', 'ELEMENTOR_GOOGLE_DRIVE_ADDON_VERSION' );
        }

        if ( did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Modules\Forms\Classes\Integration_Base' ) ) {
            add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_form_action' ] );
        }
    }

    public function register_form_action() {
        $action = new Elementor_Google_Drive_Form_Action();
        \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' )->add_form_action( $action->get_name(), $action );
    }
}
Elementor_Google_Drive_Extension::instance();
