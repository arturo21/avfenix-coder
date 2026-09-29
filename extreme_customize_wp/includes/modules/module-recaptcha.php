
<?php
/**
 * Módulo reCAPTCHA - Integración Google reCAPTCHA v2/v3
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Recaptcha {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_recaptcha_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Enqueue scripts
        add_action('login_enqueue_scripts', [$this, 'enqueue_recaptcha_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_recaptcha_scripts']);
        
        // Login form
        add_action('login_form', [$this, 'add_recaptcha_to_login']);
        add_action('wp_authenticate', [$this, 'verify_login_recaptcha'], 10, 2);
        
        // Registration form
        add_action('register_form', [$this, 'add_recaptcha_to_register']);
        add_action('registration_errors', [$this, 'verify_register_recaptcha'], 10, 3);
        
        // Comment form
        add_action('comment_form', [$this, 'add_recaptcha_to_comments']);
        add_filter('preprocess_comment', [$this, 'verify_comment_recaptcha']);
        
        // Lost password
        add_action('lostpassword_form', [$this, 'add_recaptcha_to_lostpassword']);
        add_filter('lostpassword_errors', [$this, 'verify_lostpassword_recaptcha']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_recaptcha_menu']);
    }
    
    public function enqueue_recaptcha_scripts() {
        if (!$this->is_recaptcha_enabled()) {
            return;
        }
        
        $version = $this->get_setting('recaptcha_version', 'v3');
        $site_key = $this->get_setting('recaptcha_site_key', '');
        
        if (empty($site_key)) {
            return;
        }
        
        if ($version === 'v3') {
            wp_enqueue_script('wpec-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . $site_key, [], WPEC_VERSION, true);
        } else {
            wp_enqueue_script('wpec-recaptcha-v2', 'https://www.google.com/recaptcha/api.js', [], WPEC_VERSION, true);
        }
    }
    
    public function add_recaptcha_to_login() {
        if (!$this->is_recaptcha_enabled()) {
            return;
        }
        
        $version = $this->get_setting('recaptcha_version', 'v3');
        $site_key = $this->get_setting('recaptcha_site_key', '');
        
        if ($version === 'v3') {
            ?>
            <script>
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo esc_js($site_key); ?>', {action: 'login'}).then(function(token) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'wpec_recaptcha_token';
                    input.value = token;
                    document.getElementById('loginform').appendChild(input);
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '" data-action="login"></div>';
        }
    }
    
    public function verify_login_recaptcha($user, $username) {
        if (!$this->is_recaptcha_enabled() || is_wp_error($user)) {
            return $user;
        }
        
        $token = isset($_POST['wpec_recaptcha_token']) ? sanitize_text_field($_POST['wpec_recaptcha_token']) : '';
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
        
        $response = $token ?: $recaptcha_response;
        
        if (empty($response)) {
            return new WP_Error('wpec_recaptcha_missing', __('Verificación reCAPTCHA requerida.', 'wp-extreme-customize'));
        }
        
        if (!$this->verify_recaptcha($response, 'login')) {
            return new WP_Error('wpec_recaptcha_failed', __('Verificación reCAPTCHA fallida. Intenta de nuevo.', 'wp-extreme-customize'));
        }
        
        return $user;
    }
    
    public function add_recaptcha_to_register() {
        if (!$this->is_recaptcha_enabled()) {
            return;
        }
        
        $version = $this->get_setting('recaptcha_version', 'v3');
        $site_key = $this->get_setting('recaptcha_site_key', '');
        
        if ($version === 'v3') {
            ?>
            <script>
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo esc_js($site_key); ?>', {action: 'register'}).then(function(token) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'wpec_recaptcha_token';
                    input.value = token;
                    document.getElementById('registerform').appendChild(input);
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '" data-action="register"></div>';
        }
    }
    
    public function verify_register_recaptcha($errors, $sanitized_user_login, $user_email) {
        if (!$this->is_recaptcha_enabled()) {
            return $errors;
        }
        
        $token = isset($_POST['wpec_recaptcha_token']) ? sanitize_text_field($_POST['wpec_recaptcha_token']) : '';
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
        
        $response = $token ?: $recaptcha_response;
        
        if (empty($response)) {
            $errors->add('wpec_recaptcha_missing', __('Verificación reCAPTCHA requerida.', 'wp-extreme-customize'));
        } elseif (!$this->verify_recaptcha($response, 'register')) {
            $errors->add('wpec_recaptcha_failed', __('Verificación reCAPTCHA fallida. Intenta de nuevo.', 'wp-extreme-customize'));
        }
        
        return $errors;
    }
    
    public function add_recaptcha_to_comments() {
        if (!$this->is_recaptcha_enabled() || is_user_logged_in()) {
            return;
        }
        
        $version = $this->get_setting('recaptcha_version', 'v3');
        $site_key = $this->get_setting('recaptcha_site_key', '');
        
        if ($version === 'v3') {
            ?>
            <script>
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo esc_js($site_key); ?>', {action: 'comment'}).then(function(token) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'wpec_recaptcha_token';
                    input.value = token;
                    document.getElementById('commentform').appendChild(input);
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '" data-action="comment"></div>';
        }
    }
    
    public function verify_comment_recaptcha($commentdata) {
        if (!$this->is_recaptcha_enabled() || is_user_logged_in()) {
            return $commentdata;
        }
        
        $token = isset($_POST['wpec_recaptcha_token']) ? sanitize_text_field($_POST['wpec_recaptcha_token']) : '';
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
        
        $response = $token ?: $recaptcha_response;
        
        if (empty($response) || !$this->verify_recaptcha($response, 'comment')) {
            wp_die(__('Verificación reCAPTCHA fallida. Intenta de nuevo.', 'wp-extreme-customize'));
        }
        
        return $commentdata;
    }
    
    public function add_recaptcha_to_lostpassword() {
        if (!$this->is_recaptcha_enabled()) {
            return;
        }
        
        $version = $this->get_setting('recaptcha_version', 'v3');
        $site_key = $this->get_setting('recaptcha_site_key', '');
        
        if ($version === 'v3') {
            ?>
            <script>
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo esc_js($site_key); ?>', {action: 'lostpassword'}).then(function(token) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'wpec_recaptcha_token';
                    input.value = token;
                    document.getElementById('lostpasswordform').appendChild(input);
                });
            });
            </script>
            <?php
        } else {
            echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '" data-action="lostpassword"></div>';
        }
    }
    
    public function verify_lostpassword_recaptcha($errors) {
        if (!$this->is_recaptcha_enabled()) {
            return $errors;
        }
        
        $token = isset($_POST['wpec_recaptcha_token']) ? sanitize_text_field($_POST['wpec_recaptcha_token']) : '';
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
        
        $response = $token ?: $recaptcha_response;
        
        if (empty($response)) {
            $errors->add('wpec_recaptcha_missing', __('Verificación reCAPTCHA requerida.', 'wp-extreme-customize'));
        } elseif (!$this->verify_recaptcha($response, 'lostpassword')) {
            $errors->add('wpec_recaptcha_failed', __('Verificación reCAPTCHA fallida. Intenta de nuevo.', 'wp-extreme-customize'));
        }
        
        return $errors;
    }
    
    private function verify_recaptcha($response, $action = '') {
        $secret_key = $this->get_setting('recaptcha_secret_key', '');
        $version = $this->get_setting('recaptcha_version', 'v3');
        $min_score = $this->get_setting('recaptcha_score', 0.5);
        
        if (empty($secret_key)) {
            return false;
        }
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $args = [
            'secret' => $secret_key,
            'response' => $response,
            'remoteip' => $this->get_client_ip(),
        ];
        
        $response = wp_remote_post($url, [
            'body' => $args,
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!$result || !$result['success']) {
            return false;
        }
        
        if ($version === 'v3') {
            $score = $result['score'] ?? 0;
            $action_name = $result['action'] ?? '';
            
            if ($score < $min_score) {
                return false;
            }
            
            if ($action && $action_name !== $action) {
                return false;
            }
        }
        
        return true;
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
    
    private function is_recaptcha_enabled() {
        return $this->get_setting('enable_recaptcha', false) && 
               !empty($this->get_setting('recaptcha_site_key')) && 
               !empty($this->get_setting('recaptcha_secret_key'));
    }
    
    public function add_recaptcha_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('reCAPTCHA', 'wp-extreme-customize'),
            __('reCAPTCHA', 'wp-extreme-customize'),
            'manage_options',
            'wpec-recaptcha',
            [$this, 'render_recaptcha_settings']
        );
    }
    
    public function render_recaptcha_settings() {
        ?>
        <div class="wrap">
            <h1><?php _e('Google reCAPTCHA', 'wp-extreme-customize'); ?></h1>
            <p><?php _e('Configura Google reCAPTCHA v2 o v3 para proteger formularios.', 'wp-extreme-customize'); ?></p>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_recaptcha'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Activar reCAPTCHA', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpec_recaptcha_settings[enable_recaptcha]" value="yes" <?php checked($this->settings['enable_recaptcha'] ?? '', 'yes'); ?>>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Versión', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <select name="wpec_recaptcha_settings[recaptcha_version]" class="medium-text">
                                <option value="v2" <?php selected($this->settings['recaptcha_version'] ?? '', 'v2'); ?>><?php _e('v2 (Checkbox)', 'wp-extreme-customize'); ?></option>
                                <option value="v3" <?php selected($this->settings['recaptcha_version'] ?? '', 'v3'); ?>><?php _e('v3 (Invisible/Score)', 'wp-extreme-customize'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Site Key', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="text" name="wpec_recaptcha_settings[recaptcha_site_key]" value="<?php echo esc_attr($this->settings['recaptcha_site_key'] ?? ''); ?>" class="large-text"></td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Secret Key', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="password" name="wpec_recaptcha_settings[recaptcha_secret_key]" value="<?php echo esc_attr($this->settings['recaptcha_secret_key'] ?? ''); ?>" class="large-text"></td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Puntuación Mínima (v3)', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="number" name="wpec_recaptcha_settings[recaptcha_score]" value="<?php echo esc_attr($this->settings['recaptcha_score'] ?? 0.5); ?>" class="small-text" step="0.1" min="0" max="1">
                            <p class="description"><?php _e('Puntuación mínima para considerar válido (0.0 - 1.0). Solo v3.', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('Proteger Formularios', 'wp-extreme-customize'); ?></th>
                        <td>
                            <label><input type="checkbox" name="wpec_recaptcha_settings[protect_login]" value="yes" <?php checked($this->settings['protect_login'] ?? '', 'yes'); ?>> <?php _e('Login', 'wp-extreme-customize'); ?></label><br>
                            <label><input type="checkbox" name="wpec_recaptcha_settings[protect_register]" value="yes" <?php checked($this->settings['protect_register'] ?? '', 'yes'); ?>> <?php _e('Registro', 'wp-extreme-customize'); ?></label><br>
                            <label><input type="checkbox" name="wpec_recaptcha_settings[protect_comments]" value="yes" <?php checked($this->settings['protect_comments'] ?? '', 'yes'); ?>> <?php _e('Comentarios', 'wp-extreme-customize'); ?></label><br>
                            <label><input type="checkbox" name="wpec_recaptcha_settings[protect_lostpassword]" value="yes" <?php checked($this->settings['protect_lostpassword'] ?? '', 'yes'); ?>> <?php _e('Recuperar contraseña', 'wp-extreme-customize'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Recaptcha();
