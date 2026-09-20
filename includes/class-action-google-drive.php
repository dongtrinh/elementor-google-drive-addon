<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Elementor_Google_Drive_Form_Action extends \ElementorPro\Modules\Forms\Classes\Integration_Base {
    public function get_name() { return 'google_drive_for_elementor_form'; }
    public function get_label() { return __( 'Google Drive', 'elementor-google-drive-addon' ); }

    public function run( $record, $ajax_handler ) {
        $settings = $record->get( 'form_settings' );
        $raw      = $record->get( 'fields' );
        $files    = $record->get( 'files' );

        $fields = array();
        foreach ( $raw as $id => $f ) {
            $fields[ $id ] = $f['value'];
        }

        // Handle uploaded file attachments
        $uploaded_files = array();
        if ( ! empty( $files ) && is_array( $files ) ) {
            foreach ( $files as $field_id => $file_info ) {
                if ( empty( $file_info['path'] ) && ! empty( $file_info['url'] ) ) {
                    $uploaded_files[] = array(
                        'field_id'  => $field_id,
                        'file_name' => $file_info['name'] ?? basename( $file_info['url'] ),
                        'file_url'  => $file_info['url'],
                    );
                } elseif ( ! empty( $file_info['path'] ) && file_exists( $file_info['path'] ) ) {
                    $file_data = file_get_contents( $file_info['path'] );
                    $uploaded_files[] = array(
                        'field_id'   => $field_id,
                        'file_name'  => $file_info['name'] ?? basename( $file_info['path'] ),
                        'file_url'   => $file_info['url'] ?? '',
                        'mime_type'  => $file_info['type'] ?? mime_content_type( $file_info['path'] ),
                        'base64'     => base64_encode( $file_data ),
                        'size'       => filesize( $file_info['path'] ),
                    );
                }
            }
        }

        // Webhook destination: Form override or Global fallback
        $webhook_url = ! empty( $settings['gd_webhook_url'] ) ? trim( $settings['gd_webhook_url'] ) : get_option( 'ge_elementor-google-drive-addon_webhook_url', '' );
        if ( empty( $webhook_url ) ) {
            return;
        }

        // Folder ID: Form override or Global fallback
        $folder_id = ! empty( $settings['gd_folder_id'] ) ? trim( $settings['gd_folder_id'] ) : get_option( 'ge_elementor-google-drive-addon_folder_id', '' );

        // Custom Fields Mapping Repeater
        $custom_properties = array();
        if ( ! empty( $settings['gd_custom_mapping'] ) && is_array( $settings['gd_custom_mapping'] ) ) {
            foreach ( $settings['gd_custom_mapping'] as $item ) {
                $prop_key = trim( $item['gd_property_name'] ?? '' );
                $field_id = trim( $item['gd_field_id'] ?? '' );
                if ( ! empty( $prop_key ) && ! empty( $field_id ) && isset( $fields[ $field_id ] ) ) {
                    $custom_properties[ $prop_key ] = $fields[ $field_id ];
                }
            }
        }

        $body = array(
            'form_name'         => $record->get_form_settings( 'form_name' ),
            'folder_id'         => $folder_id,
            'subfolder_name'    => $settings['gd_subfolder_name'] ?? '',
            'submitted_at'      => current_time( 'mysql' ),
            'fields'            => $fields,
            'files'             => $uploaded_files,
            'custom_properties' => $custom_properties,
            'user_ip'           => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        );

        $body = apply_filters( 'ge_elementor_google_drive_payload', $body, $record );

        $api = new Elementor_Google_Drive_API( $webhook_url, $folder_id );
        $api->upload_files_and_submit( $body, $webhook_url );
    }

    public function register_settings_section( $widget ) {
        $widget->start_controls_section( 'google_drive_setting', [
            'label'     => __( 'Google Drive Settings', 'elementor-google-drive-addon' ),
            'condition' => [ 'submit_actions' => $this->get_name() ],
        ] );

        $widget->add_control( 'gd_webhook_url', [
            'label'       => __( 'Google Drive Webhook URL (Optional Override)', 'elementor-google-drive-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
            'description' => __( 'Leave blank to use the Global Webhook configured under Settings &rarr; Elementor + Google Drive.', 'elementor-google-drive-addon' ),
        ] );

        $widget->add_control( 'gd_folder_id', [
            'label'       => __( 'Target Folder ID (Optional Override)', 'elementor-google-drive-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
            'description' => __( 'Leave blank to use the Global Folder ID.', 'elementor-google-drive-addon' ),
        ] );

        $widget->add_control( 'gd_subfolder_name', [
            'label'       => __( 'Dynamic Subfolder Name (Optional)', 'elementor-google-drive-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
            'placeholder' => 'e.g. [field:name] or [field:email]',
            'description' => __( 'Automatically create a dedicated subfolder inside your target Drive folder for each submission.', 'elementor-google-drive-addon' ),
        ] );

        // Custom Fields Mapping Repeater
        $repeater = new \Elementor\Repeater();

        $repeater->add_control( 'gd_property_name', [
            'label'       => __( 'Google Drive Meta / Property Name', 'elementor-google-drive-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => 'e.g. Client_Company',
            'label_block' => true,
        ] );

        $repeater->add_control( 'gd_field_id', [
            'label'       => __( 'Form Field ID', 'elementor-google-drive-addon' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'placeholder' => 'e.g. company_name',
            'label_block' => true,
        ] );

        $widget->add_control( 'gd_custom_mapping', [
            'label'         => __( 'Custom Properties Mapping', 'elementor-google-drive-addon' ),
            'type'          => \Elementor\Controls_Manager::REPEATER,
            'fields'        => $repeater->get_controls(),
            'title_field'   => '{{{ gd_property_name }}} &rarr; {{{ gd_field_id }}}',
            'prevent_empty' => false,
            'description'   => __( 'Map unlimited form field inputs to custom Google Drive file metadata properties.', 'elementor-google-drive-addon' ),
        ] );

        $widget->end_controls_section();
    }

    public function on_export( $element ) {
        unset( $element['gd_webhook_url'], $element['gd_folder_id'], $element['gd_subfolder_name'], $element['gd_custom_mapping'] );
        return $element;
    }
}
