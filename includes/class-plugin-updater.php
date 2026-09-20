<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Elementor_Google_Drive_Plugin_Updater {
    private $file;
    private $slug;
    private $version;
    private $api_url = 'https://gravityextra.com/';

    public function __construct( $file, $slug, $version_const ) {
        $this->file    = $file;
        $this->slug    = $slug;
        $this->version = defined( $version_const ) ? constant( $version_const ) : '1.1.0';
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;
        $license_key    = get_option( 'ge_' . $this->slug . '_license_key', '' );
        $license_status = get_option( 'ge_' . $this->slug . '_license_status', 'invalid' );
        if ( empty( $license_key ) || 'valid' !== $license_status ) return $transient;

        $res = wp_remote_get( $this->api_url . 'wp-json/ge-license/v1/check-update', array(
            'timeout' => 15,
            'body'    => array( 'slug' => $this->slug, 'version' => $this->version, 'license_key' => $license_key ),
        ) );
        if ( is_wp_error( $res ) ) return $transient;
        $data = json_decode( wp_remote_retrieve_body( $res ) );
        if ( ! empty( $data->new_version ) && version_compare( $this->version, $data->new_version, '<' ) ) {
            $p = new \stdClass();
            $p->slug = $this->slug;
            $p->plugin = plugin_basename( $this->file );
            $p->new_version = $data->new_version;
            $p->url = $this->api_url;
            $p->package = isset( $data->package ) ? $data->package : '';
            $transient->response[ plugin_basename( $this->file ) ] = $p;
        }
        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) return $result;
        $license_key    = get_option( 'ge_' . $this->slug . '_license_key', '' );
        $license_status = get_option( 'ge_' . $this->slug . '_license_status', 'invalid' );
        if ( empty( $license_key ) || 'valid' !== $license_status ) return $result;
        $res = wp_remote_get( $this->api_url . 'wp-json/ge-license/v1/plugin-info', array(
            'timeout' => 15,
            'body'    => array( 'slug' => $this->slug, 'license_key' => $license_key ),
        ) );
        if ( is_wp_error( $res ) ) return $result;
        $data = json_decode( wp_remote_retrieve_body( $res ) );
        if ( ! empty( $data->name ) ) return $data;
        return $result;
    }
}
