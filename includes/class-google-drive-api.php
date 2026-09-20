<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Elementor_Google_Drive_API {
    private $webhook_url;
    private $folder_id;

    public function __construct( $webhook_url = '', $folder_id = '' ) {
        if ( empty( $webhook_url ) ) {
            $webhook_url = get_option( 'ge_elementor-google-drive-addon_webhook_url', '' );
        }
        if ( empty( $folder_id ) ) {
            $folder_id = get_option( 'ge_elementor-google-drive-addon_folder_id', '' );
        }
        $this->webhook_url = trim( $webhook_url );
        $this->folder_id   = trim( $folder_id );
    }

    public function is_configured() {
        return ! empty( $this->webhook_url );
    }

    public function ping() {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'not_configured', __( 'Google Drive Webhook URL is missing.', 'elementor-google-drive-addon' ) );
        }

        if ( ! filter_var( $this->webhook_url, FILTER_VALIDATE_URL ) ) {
            return new \WP_Error( 'invalid_url', __( 'Please provide a valid HTTP/HTTPS Webhook URL.', 'elementor-google-drive-addon' ) );
        }

        $test_payload = array(
            'action'    => 'ping',
            'timestamp' => current_time( 'mysql' ),
            'source'    => 'GravityExtra Elementor Google Drive Add-on',
            'folder_id' => $this->folder_id,
        );

        $response = wp_remote_post( $this->webhook_url, array(
            'timeout'     => 15,
            'sslverify'   => true,
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'body'        => wp_json_encode( $test_payload ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 400 ) {
            return true;
        }

        return new \WP_Error( 'http_error', sprintf( __( 'Google Drive endpoint responded with status %d', 'elementor-google-drive-addon' ), $code ) );
    }

    public function upload_files_and_submit( $data, $webhook_override = '' ) {
        $url = ! empty( $webhook_override ) ? trim( $webhook_override ) : $this->webhook_url;
        if ( empty( $url ) ) {
            return new \WP_Error( 'no_url', __( 'No Google Drive destination URL configured.', 'elementor-google-drive-addon' ) );
        }

        return wp_remote_post( $url, array(
            'method'      => 'POST',
            'timeout'     => 30,
            'sslverify'   => true,
            'headers'     => array( 'Content-Type' => 'application/json' ),
            'body'        => is_array( $data ) ? wp_json_encode( $data ) : $data,
        ) );
    }
}
