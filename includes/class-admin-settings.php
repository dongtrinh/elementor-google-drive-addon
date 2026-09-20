<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Elementor_Google_Drive_Admin_Settings {
    private $slug            = 'elementor-google-drive-addon';
    private $service_name    = 'Google Drive';
    private $license_manager = null;

    public function __construct( $license_manager = null ) {
        $this->license_manager = $license_manager;
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
        register_setting( $this->slug . '_group', 'ge_' . $this->slug . '_webhook_url' );
        register_setting( $this->slug . '_group', 'ge_' . $this->slug . '_folder_id' );
    }

    public function render_settings_page() {
        $webhook_url = get_option( 'ge_' . $this->slug . '_webhook_url', '' );
        $folder_id   = get_option( 'ge_' . $this->slug . '_folder_id', '' );
        ?>
        <div class="wrap">
            <h1>Elementor Form + <?php echo esc_html( $this->service_name ); ?> Settings</h1>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">
                Upload Elementor Pro Form file attachments directly to Google Drive folders and eliminate server disk bloat.
            </p>

            <?php settings_errors( $this->slug . '_notices' ); ?>

            <?php
            if ( $this->license_manager ) {
                $this->license_manager->render_settings_card();
            }
            ?>

            <div class="ge-api-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); max-width: 800px;">
                <h3 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                    ⚡ <?php echo esc_html( $this->service_name ); ?> Connection &amp; Target Folder
                </h3>
                <form method="post" action="options.php">
                    <?php settings_fields( $this->slug . '_group' ); ?>
                    <table class="form-table" style="margin-top: 0;">
                        <tr>
                            <th scope="row" style="padding-top: 12px; width: 220px;">
                                <?php echo esc_html( $this->service_name ); ?> Webhook / Script URL
                            </th>
                            <td style="padding-top: 12px;">
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <input type="url" name="ge_<?php echo esc_attr( $this->slug ); ?>_webhook_url"
                                           value="<?php echo esc_attr( $webhook_url ); ?>" class="regular-text" id="ge_webhook_url_field"
                                           placeholder="https://script.google.com/macros/s/.../exec"
                                           style="height: 38px; border-radius: 6px; width: 440px;" />
                                    <button type="button" class="button" id="ge_test_connection_btn" style="height: 38px; border-radius: 6px;">Test Connection</button>
                                </div>
                                <span id="ge_test_result" style="display: block; margin-top: 8px; font-weight: 600;"></span>
                                <p class="description" style="margin-top: 8px;">
                                    Enter your Google Apps Script Webhook or Google Drive connector endpoint URL. Need help? Read our <a href="https://gravityextra.com/docs/elementor-google-drive-integration-premium-add-on/" target="_blank" rel="noopener noreferrer">Setup &amp; Configuration Guide</a>.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="padding-top: 12px; width: 220px;">
                                Default Target Folder ID
                            </th>
                            <td style="padding-top: 12px;">
                                <input type="text" name="ge_<?php echo esc_attr( $this->slug ); ?>_folder_id"
                                       value="<?php echo esc_attr( $folder_id ); ?>" class="regular-text"
                                       placeholder="e.g. 1A2b3C4d5E6f7G8h9I0..."
                                       style="height: 38px; border-radius: 6px;" />
                                <p class="description" style="margin-top: 8px;">
                                    The alphanumeric ID from your Google Drive folder URL (e.g. <code>drive.google.com/drive/folders/<b>YOUR_FOLDER_ID</b></code>). Can be overridden per form.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Save Google Drive Settings', 'elementor-google-drive-addon' ) ); ?>
                </form>
            </div>
        </div>
        <script>
        document.getElementById('ge_test_connection_btn').addEventListener('click', function() {
            var resultEl = document.getElementById('ge_test_result');
            resultEl.textContent = 'Connecting to Google Drive...';
            resultEl.style.color = '#666';
            var webhookUrl = document.getElementById('ge_webhook_url_field').value;
            var formData = new FormData();
            formData.append('action', 'ge_google_drive_test_connection');
            formData.append('webhook_url', webhookUrl);
            formData.append('_wpnonce', '<?php echo wp_create_nonce( 'ge_test_connection' ); ?>');
            fetch(ajaxurl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        resultEl.textContent = '✓ ' + (data.data || 'Connection successful!');
                        resultEl.style.color = '#059669';
                    } else {
                        resultEl.textContent = '✗ ' + (data.data || 'Connection failed');
                        resultEl.style.color = '#dc2626';
                    }
                })
                .catch(function() {
                    resultEl.textContent = '✗ Network request failed';
                    resultEl.style.color = '#dc2626';
                });
        });
        </script>
        <?php
    }

    public function ajax_test_connection() {
        check_ajax_referer( 'ge_test_connection', '_wpnonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'elementor-google-drive-addon' ) );
        }

        $webhook_url = isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '';
        if ( empty( $webhook_url ) ) {
            $webhook_url = get_option( 'ge_' . $this->slug . '_webhook_url', '' );
        }

        if ( empty( $webhook_url ) ) {
            wp_send_json_error( __( 'Please enter your Google Drive Webhook URL first.', 'elementor-google-drive-addon' ) );
        }

        $folder_id = get_option( 'ge_' . $this->slug . '_folder_id', '' );
        $api = new Elementor_Google_Drive_API( $webhook_url, $folder_id );
        $result = $api->ping();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( __( 'Google Drive endpoint verified successfully!', 'elementor-google-drive-addon' ) );
    }
}
