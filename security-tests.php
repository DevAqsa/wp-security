<?php
if (!defined('ABSPATH')) {
    exit;
}

class Security_Plugin_Tests {
    private $nonce_name = 'security_demo_nonce';
    private $nonce_action = 'security_demo_action';
    private $test_results = array();

    public function run_tests() {
        $this->test_results = array(
            'nonce' => $this->test_nonce(),
            'sanitization' => $this->test_sanitization(),
            'permissions' => $this->test_permissions(),
            'ajax' => $this->test_ajax()
        );

        $this->display_results();
    }

    private function test_nonce() {
        $test_result = array();
        $nonce = wp_create_nonce($this->nonce_action);
        $verification = wp_verify_nonce($nonce, $this->nonce_action);

        $test_result['status'] = $verification ? 'passed' : 'failed';
        $test_result['message'] = $verification ? 
            'Nonce generation and verification working correctly' : 
            'Nonce verification failed';

        return $test_result;
    }

    private function test_sanitization() {
        $test_result = array();
        $test_input = '<script>alert("XSS")</script>Test Input';
        $sanitized = sanitize_text_field($test_input);
        
        $test_result['status'] = (strpos($sanitized, '<script>') === false) ? 'passed' : 'failed';
        $test_result['message'] = (strpos($sanitized, '<script>') === false) ? 
            'Sanitization working correctly' : 
            'Sanitization failed to remove script tags';

        return $test_result;
    }

    private function test_permissions() {
        $test_result = array();
        $test_result['status'] = current_user_can('manage_options') ? 'passed' : 'failed';
        $test_result['message'] = current_user_can('manage_options') ? 
            'User has correct permissions' : 
            'User lacks required permissions';

        return $test_result;
    }

    private function test_ajax() {
        $test_result = array();
        $nonce = wp_create_nonce($this->nonce_action);
        
        $test_result['status'] = $nonce ? 'passed' : 'failed';
        $test_result['message'] = $nonce ? 
            'AJAX nonce generation working' : 
            'AJAX nonce generation failed';

        return $test_result;
    }

    private function display_results() {
        ?>
        <div class="wrap">
            <h1>Security Plugin Test Results</h1>
            <table class="widefat" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->test_results as $test => $result): ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($test)); ?> Test</td>
                            <td>
                                <span class="dashicons <?php echo $result['status'] === 'passed' ? 'dashicons-yes' : 'dashicons-no'; ?>"
                                      style="color: <?php echo $result['status'] === 'passed' ? 'green' : 'red'; ?>;">
                                </span>
                                <?php echo esc_html(ucfirst($result['status'])); ?>
                            </td>
                            <td><?php echo esc_html($result['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                <h2>Manual Testing Tools</h2>
                <div id="security-test-forms" style="display: none;">
                    <!-- Nonce Test Form -->
                    <form id="nonce-test-form" method="POST">
                        <input type="hidden" name="<?php echo esc_attr($this->nonce_name); ?>" value="invalid_nonce">
                        <input type="hidden" name="submit_form" value="1">
                    </form>
                    
                    <!-- XSS Test Form -->
                    <form id="xss-test-form" method="POST">
                        <input type="hidden" name="user_input" value="<script>alert('XSS');</script>">
                        <input type="hidden" name="<?php echo esc_attr($this->nonce_name); ?>" 
                               value="<?php echo esc_attr(wp_create_nonce($this->nonce_action)); ?>">
                        <input type="hidden" name="submit_form" value="1">
                    </form>
                </div>

                <button class="button button-primary" onclick="WPSecurityTests.testNonce()">Test Invalid Nonce</button>
                <button class="button button-primary" onclick="WPSecurityTests.testXSS()">Test XSS Protection</button>
                <button class="button button-primary" onclick="WPSecurityTests.testAjaxSecurity()">Test AJAX Security</button>
            </div>

            <script type="text/javascript">
            /* <![CDATA[ */
            var WPSecurityTests = {
                testNonce: function() {
                    document.getElementById('nonce-test-form').submit();
                },
                
                testXSS: function() {
                    document.getElementById('xss-test-form').submit();
                },
                
                testAjaxSecurity: function() {
                    jQuery.post(ajaxurl, {
                        action: 'save_ajax_data',
                        nonce: 'invalid_nonce',
                        ajax_text: 'test'
                    })
                    .done(function(response) {
                        alert('AJAX Response: ' + (response.data ? response.data.message : 'Security check failed'));
                    })
                    .fail(function() {
                        alert('AJAX security check working - Invalid request blocked');
                    });
                }
            };
            /* ]]> */
            </script>
        </div>
        <?php
    }
}
?>