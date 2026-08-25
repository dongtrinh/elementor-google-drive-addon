<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Elementor_Google_Drive_Form_Action extends \ElementorPro\Modules\Forms\Classes\Integration_Base {
    public function get_name() { return 'google_drive_for_elementor_form'; }
    public function get_label() { return __( 'Google Drive', 'elementor-google-drive-addon' ); }

    public function run( $record, $ajax_handler ) {
        $settings = $record->get( 'form_settings' );
        $raw = $record->get( 'fields' );
        $fields = array();
        foreach ( $raw as $id => $f ) { $fields[ $id ] = $f['value']; }

        
        if ( empty( $settings['gd_webhook_url'] ) ) return;
        $body = array(
            'form_name'    => $record->get_form_settings( 'form_name' ),
            'folder_id'    => $settings['gd_folder_id'] ?? '',
            'submitted_at' => current_time( 'mysql' ),
            'fields'       => $fields,
        );
        $api = new Elementor_Google_Drive_API();
        $api->submit( $settings['gd_webhook_url'], $body );
        
    }

    public function register_settings_section( $widget ) {
        $widget->start_controls_section( 'google_drive_setting', [
            'label'     => __( 'Google Drive Settings', 'elementor-google-drive-addon' ),
            'condition' => [ 'submit_actions' => $this->get_name() ],
        ] );
        $widget->add_control( 'gd_webhook_url', [
            'label' => __( 'Google Apps Script Drive Webhook URL — <b>REQUIRED</b>', 'elementor-google-drive-addon' ),
            'type'  => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
            
        ] );
        $widget->add_control( 'gd_folder_id', [
            'label' => __( 'Target Folder ID (optional)', 'elementor-google-drive-addon' ),
            'type'  => \Elementor\Controls_Manager::TEXT,
            
            
        ] );
        $widget->end_controls_section();
    }
    public function on_export( $element ) {
        unset( $element['gd_webhook_url'], $element['gd_folder_id'] );
        return $element;
    }
}
