<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Elementor_Google_Drive_API {
    private $api_key;
    private $base_url = '';

    public function __construct( $key = '' ) {
        if ( empty( $key ) ) $key = get_option( 'ge_elementor-google-drive-addon_api_key', '' );
        $this->api_key = trim( $key );
    }
    public function is_configured() { return ! empty( $this->api_key ); }
    private function get_headers() {
        $headers = array( 'Content-Type' => 'application/json' );
        $headers['Authorization'] = 'Bearer ' . $this->api_key;
        return $headers;
    }
    public function ping() {
        if ( ! $this->is_configured() ) return new \WP_Error( 'not_configured', 'Google Drive API credentials missing.' );
        return true;
    }
    public function submit( $endpoint_url, $data, $method = 'POST' ) {
        $url = ! empty( $endpoint_url ) ? $endpoint_url : $this->base_url;
        return wp_remote_request( $url, array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => $this->get_headers(),
            'body'    => is_array( $data ) ? wp_json_encode( $data ) : $data,
        ) );
    }
}
