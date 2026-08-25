<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Elementor_Google_Drive_Admin_Settings {
    private $slug = 'elementor-google-drive-addon';
    private $service_name = 'Google Drive';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_ge_google_drive_test_connection', array( $this, 'ajax_test_connection' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Elementor + ' . $this->service_name,
            'Elementor + ' . $this->service_name,
            'manage_options',
            $this->slug,
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( $this->slug . '_group', 'ge_' . $this->slug . '_license_key' );
        register_setting( $this->slug . '_group', 'ge_' . $this->slug . '_api_key' );
    }

    public function render_settings_page() {
        $license_key = get_option( 'ge_' . $this->slug . '_license_key', '' );
        $api_key     = get_option( 'ge_' . $this->slug . '_api_key', '' );
        ?>
        <div class="wrap">
            <h1>Elementor Form + <?php echo esc_html( $this->service_name ); ?> Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( $this->slug . '_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">GravityExtra License Key</th>
                        <td>
                            <input type="text" name="ge_<?php echo esc_attr( $this->slug ); ?>_license_key"
                                   value="<?php echo esc_attr( $license_key ); ?>" class="regular-text" />
                            <p class="description">Enter your GravityExtra license key to enable auto-updates.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html( $this->service_name ); ?> API Key / Webhook URL</th>
                        <td>
                            <input type="text" name="ge_<?php echo esc_attr( $this->slug ); ?>_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" id="ge_api_key_field" />
                            <button type="button" class="button" id="ge_test_connection_btn">Test Connection</button>
                            <span id="ge_test_result" style="margin-left:10px;"></span>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        document.getElementById('ge_test_connection_btn').addEventListener('click', function() {
            var resultEl = document.getElementById('ge_test_result');
            resultEl.textContent = 'Testing...';
            resultEl.style.color = '#666';
            var apiKey = document.getElementById('ge_api_key_field').value;
            var formData = new FormData();
            formData.append('action', 'ge_google_drive_test_connection');
            formData.append('api_key', apiKey);
            formData.append('_wpnonce', '<?php echo wp_create_nonce( 'ge_test_connection' ); ?>');
            fetch(ajaxurl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        resultEl.textContent = '✓ Connection successful!';
                        resultEl.style.color = 'green';
                    } else {
                        resultEl.textContent = '✗ ' + (data.data || 'Connection failed');
                        resultEl.style.color = 'red';
                    }
                })
                .catch(function() {
                    resultEl.textContent = '✗ Request failed';
                    resultEl.style.color = 'red';
                });
        });
        </script>
        <?php
    }

    public function ajax_test_connection() {
        check_ajax_referer( 'ge_test_connection', '_wpnonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        if ( empty( $api_key ) ) wp_send_json_error( 'Credential is empty' );
        $api = new Elementor_Google_Drive_API( $api_key );
        $result = $api->ping();
        if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
        wp_send_json_success( 'Connected' );
    }
}
