<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Security_Logs {
    private $table_name;
    private $db_version = '1.0';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'security_logs';
        
        // Create logs table on activation
        register_activation_hook(__FILE__, array($this, 'create_logs_table'));
        
        // Add actions
        add_action('admin_init', array($this, 'check_security_issues'));
        add_action('wp_login_failed', array($this, 'log_failed_login'));
        add_action('wp_login', array($this, 'log_successful_login'), 10, 2);
    }

    public function create_logs_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_description text NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('security_logs_db_version', $this->db_version);
    }

    public function render_logs_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch($action) {
            case 'clear':
                $this->clear_logs();
                break;
            case 'export':
                $this->export_logs();
                break;
            default:
                $this->display_logs();
                break;
        }
    }

    private function display_logs() {
        global $wpdb;
        
        // Pagination settings
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $this->table_name");
        
        // Get logs
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        // Security Status
        $security_status = $this->check_security_issues();
        ?>
        <div class="wrap">
            <h1>Security Logs</h1>

            <!-- Security Status Section -->
            <div class="security-status-box">
                <h2>Security Status</h2>
                <?php foreach ($security_status as $check => $status): ?>
                    <div class="status-item <?php echo $status['status']; ?>">
                        <span class="dashicons <?php echo $status['status'] === 'ok' ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
                        <?php echo esc_html($status['message']); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Actions -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo esc_url(add_query_arg('action', 'clear')); ?>" 
                       class="button" 
                       onclick="return confirm('Are you sure you want to clear all logs?');">
                        Clear Logs
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('action', 'export')); ?>" 
                       class="button">
                        Export Logs
                    </a>
                </div>
            </div>

            <!-- Logs Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event Type</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->created_at); ?></td>
                                <td><?php echo esc_html($log->event_type); ?></td>
                                <td><?php echo esc_html($log->event_description); ?></td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                                <td><?php echo esc_html($log->user_agent); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php
            $total_pages = ceil($total_items / $per_page);
            if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .security-status-box {
            background: #fff;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .status-item {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
        }
        .status-item.ok {
            border-left: 4px solid #46b450;
        }
        .status-item.warning {
            border-left: 4px solid #ffb900;
        }
        .status-item.error {
            border-left: 4px solid #dc3232;
        }
        </style>
        <?php
    }

    private function clear_logs() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE $this->table_name");
        wp_redirect(remove_query_arg('action'));
        exit;
    }

    private function export_logs() {
        global $wpdb;
        
        $logs = $wpdb->get_results("SELECT * FROM $this->table_name ORDER BY created_at DESC");
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="security-logs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, array('Time', 'Event Type', 'Description', 'IP Address', 'User Agent'));
        
        // Add data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->created_at,
                $log->event_type,
                $log->event_description,
                $log->ip_address,
                $log->user_agent
            ));
        }
        
        fclose($output);
        exit;
    }

    public function log_event($event_type, $description) {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_name,
            array(
                'event_type' => $event_type,
                'event_description' => $description,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'user_id' => get_current_user_id()
            ),
            array('%s', '%s', '%s', '%s', '%d')
        );
    }

    public function log_failed_login($username) {
        $this->log_event(
            'failed_login',
            sprintf('Failed login attempt for username: %s', sanitize_user($username))
        );
    }

    public function log_successful_login($username, $user) {
        $this->log_event(
            'successful_login',
            sprintf('Successful login: %s', sanitize_user($username))
        );
    }

    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header]);
                $ip = trim($ip[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    public function check_security_issues() {
        $status = array();

        // Check WordPress version
        global $wp_version;
        $status['wordpress_version'] = array(
            'status' => version_compare($wp_version, '5.0', '>=') ? 'ok' : 'error',
            'message' => 'WordPress Version: ' . $wp_version
        );

        // Check PHP version
        $status['php_version'] = array(
            'status' => version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'warning',
            'message' => 'PHP Version: ' . PHP_VERSION
        );

        // Check if debug mode is enabled
        $status['debug_mode'] = array(
            'status' => defined('WP_DEBUG') && WP_DEBUG ? 'warning' : 'ok',
            'message' => 'Debug Mode: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled')
        );

        // Check file permissions
        $wp_config_file = ABSPATH . 'wp-config.php';
        $file_perms = file_exists($wp_config_file) ? substr(sprintf('%o', fileperms($wp_config_file)), -4) : '';
        $status['file_permissions'] = array(
            'status' => $file_perms == '0644' || $file_perms == '0640' ? 'ok' : 'warning',
            'message' => 'wp-config.php permissions: ' . $file_perms
        );

        return $status;
    }
}
?>