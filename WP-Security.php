<?php
/**
 * Plugin Name: WP Data Security
 * Description: Demonstrates proper implementation of WordPress nonces and data sanitization
 * Version: 1.0
 * Author: Aqsa Mumtaz
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

class WP_Security_Implementation {
    private static $instance = null;
    private $nonce_name = 'security_demo_nonce';
    private $nonce_action = 'security_demo_action';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('wp_ajax_save_ajax_data', array($this, 'handle_ajax_submission'));
    }

    // Add menu item to WordPress admin
    public function add_admin_menu() {
        $main_page = add_menu_page(
            'Security Demo',
            'Security Demo',
            'manage_options',
            'security-demo',
            array($this, 'render_admin_page'),
            'dashicons-shield'
        );

        // Add submenu for tests
        add_submenu_page(
            'security-demo',
            'Security Tests',
            'Security Tests',
            'manage_options',
            'security-demo-tests',
            array($this, 'render_test_page')
        );

         // Add new submenu for logs
    add_submenu_page(
        'security-demo',
        'Security Logs',
        'Security Logs',
        'manage_options',
        'security-demo-logs',
        array($this, 'render_logs_page')
    );
    }

    // Render the test page
    public function render_test_page() {
        require_once plugin_dir_path(__FILE__) . 'security-tests.php';
        $tests = new Security_Plugin_Tests();
        $tests->run_tests();
    }


    // Add after your existing methods
public function render_logs_page() {
    require_once plugin_dir_path(__FILE__) . 'security-logs.php';
    $logs = new WP_Security_Logs();
    $logs->render_logs_page();
}
    // Render the admin page
    public function render_admin_page() {
        // Create nonce for the form
        $nonce = wp_create_nonce($this->nonce_action);
        ?>
        <div class="wrap">
            <h1>Security Implementation Demo</h1>

            <!-- Regular Form Example -->
            <form method="post" action="">
                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                
                <h2>Regular Form</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="user_input">Text Input:</label></th>
                        <td>
                            <input type="text" id="user_input" name="user_input" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_input">Email Input:</label></th>
                        <td>
                            <input type="email" id="email_input" name="email_input" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="url_input">URL Input:</label></th>
                        <td>
                            <input type="url" id="url_input" name="url_input" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit_form" class="button button-primary" value="Submit Form">
                </p>
            </form>

            <!-- AJAX Form Example -->
            <form id="ajax-form">
                <h2>AJAX Form</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="ajax_text">AJAX Text Input:</label></th>
                        <td>
                            <input type="text" id="ajax_text" name="ajax_text" class="regular-text">
                            <input type="hidden" id="ajax_nonce" value="<?php echo $nonce; ?>">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="ajax-submit" class="button button-primary">Submit AJAX</button>
                </p>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#ajax-submit').on('click', function() {
                var data = {
                    'action': 'save_ajax_data',
                    'nonce': $('#ajax_nonce').val(),
                    'ajax_text': $('#ajax_text').val()
                };

                $.post(ajaxurl, data, function(response) {
                    alert(response.message);
                });
            });
        });
        </script>
        <?php
    }

    // Handle regular form submission
    public function handle_form_submission() {
        if (!isset($_POST['submit_form'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST[$this->nonce_name]) || 
            !wp_verify_nonce($_POST[$this->nonce_name], $this->nonce_action)) {
            wp_die('Security check failed!');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access!');
        }

        // Sanitize and validate form data
        $sanitized_data = $this->sanitize_form_data($_POST);

        // Save or process the sanitized data
        $this->process_form_data($sanitized_data);

        // Add success message
        add_settings_error(
            'security_demo_messages',
            'security_demo_message',
            'Data saved successfully!',
            'updated'
        );
    }

    // Handle AJAX submission
    public function handle_ajax_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || 
            !wp_verify_nonce($_POST['nonce'], $this->nonce_action)) {
            wp_send_json_error(array('message' => 'Security check failed!'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access!'));
        }

        // Sanitize and save AJAX data
        $ajax_text = isset($_POST['ajax_text']) ? 
            sanitize_text_field($_POST['ajax_text']) : '';

        // Process the data (example)
        update_option('security_demo_ajax_text', $ajax_text);

        wp_send_json_success(array('message' => 'Data saved successfully!'));
    }

    // Sanitize form data
    private function sanitize_form_data($post_data) {
        $sanitized = array();

        // Sanitize text input
        if (isset($post_data['user_input'])) {
            $sanitized['user_input'] = sanitize_text_field($post_data['user_input']);
        }

        // Sanitize email
        if (isset($post_data['email_input'])) {
            $email = sanitize_email($post_data['email_input']);
            if (!is_email($email)) {
                add_settings_error(
                    'security_demo_messages',
                    'invalid_email',
                    'Please enter a valid email address.',
                    'error'
                );
            }
            $sanitized['email_input'] = $email;
        }

        // Sanitize URL
        if (isset($post_data['url_input'])) {
            $url = esc_url_raw($post_data['url_input']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                add_settings_error(
                    'security_demo_messages',
                    'invalid_url',
                    'Please enter a valid URL.',
                    'error'
                );
            }
            $sanitized['url_input'] = $url;
        }

        return $sanitized;
    }

    // Process the sanitized form data
    private function process_form_data($sanitized_data) {
        // Example: Save to WordPress options
        foreach ($sanitized_data as $key => $value) {
            update_option('security_demo_' . $key, $value);
        }
    }
}

// Initialize the plugin
function security_implementation_init() {
    return WP_Security_Implementation::get_instance();
}
add_action('plugins_loaded', 'security_implementation_init');