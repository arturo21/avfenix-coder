
<?php
/**
 * Módulo Security Extreme - Seguridad avanzada
 * Version: 1.0.2 - Corrección TOTP y 2FA
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Security {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_security_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Rate limiting con BD
        add_filter('authenticate', [$this, 'rate_limit_login'], 10, 3);
        
        // 2FA - Verificación en el form de login
        add_action('login_form', [$this, 'add_2fa_field']);
        add_filter('authenticate', [$this, 'verify_2fa_login'], 20, 3);
        
        // 2FA - Ajustes de usuario
        add_action('show_user_profile', [$this, 'show_2fa_settings']);
        add_action('edit_user_profile', [$this, 'show_2fa_settings']);
        add_action('personal_options_update', [$this, 'save_2fa_settings']);
        add_action('edit_user_profile_update', [$this, 'save_2fa_settings']);
        
        // Headers de seguridad
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Ocultar versión de WP
        add_filter('the_generator', '__return_empty_string');
        remove_action('wp_head', 'wp_generator');
        add_filter('style_loader_tag', function($tag) {
            return str_replace('ver=' . get_bloginfo('version'), '', $tag);
        });
        add_filter('script_loader_tag', function($tag) {
            return str_replace('ver=' . get_bloginfo('version'), '', $tag);
        });
        
        // Desactivar XML-RPC
        add_filter('xmlrpc_enabled', '__return_false');
        
        // Desactivar REST API para usuarios no logueados
        add_filter('rest_authentication_errors', [$this, 'restrict_rest_api']);
        
        // Desactivar pingbacks
        add_filter('xmlrpc_methods', [$this, 'remove_pingback']);
        
        // Forzar HTTPS
        add_action('init', [$this, 'force_https']);
        
        // Desactivar editor de archivos
        add_action('init', [$this, 'disable_editors']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_security_menu']);
        
        // AJAX
        add_action('wp_ajax_wpec_verify_2fa', [$this, 'ajax_verify_2fa']);
        add_action('wp_ajax_wpec_generate_backup_codes', [$this, 'ajax_generate_backup_codes']);
    }
    
    public function rate_limit_login($user, $username, $password) {
        if (!$this->get_setting('enable_rate_limit', true)) {
            return $user;
        }
        
        if (is_wp_error($user)) {
            // Registrar intento fallido
            $this->log_login_attempt($username, 0);
            return $user;
        }
        
        $max_attempts = intval($this->get_setting('max_login_attempts', 5));
        $lockout_duration = intval($this->get_setting('lockout_duration', 900));
        
        $ip = $this->get_client_ip();
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_login_attempts';
        $cutoff = date('Y-m-d H:i:s', time() - $lockout_duration);
        
        $failed_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE ip_address = %s AND success = 0 AND attempt_at > %s",
            $ip, $cutoff
        ));
        
        if (intval($failed_attempts) >= $max_attempts) {
            $this->log_login_attempt($username, 0);
            return new WP_Error('wpec_too_many_attempts', sprintf(
                __('Demasiados intentos fallidos. Intenta de nuevo en %d minutos.', 'wp-extreme-customize'),
                intval($lockout_duration / 60)
            ));
        }
        
        // Registrar intento exitoso
        if (!is_wp_error($user) && $user instanceof WP_User) {
            $this->log_login_attempt($username, 1);
            // Limpiar intentos anteriores del IP
            $wpdb->delete($table, ['ip_address' => $ip, 'success' => 0]);
        }
        
        return $user;
    }
    
    private function log_login_attempt($username, $success) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_login_attempts';
        $wpdb->insert($table, [
            'ip_address' => $this->get_client_ip(),
            'username' => sanitize_text_field($username),
            'success' => $success,
            'attempt_at' => current_time('mysql'),
        ]);
        
        // Limpiar registros antiguos (7 días)
        $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE attempt_at < %s", $cutoff));
    }
    
    // ==================== 2FA ====================
    
    public function add_2fa_field() {
        if (!$this->get_setting('enable_2fa', false)) {
            return;
        }
        
        // Solo mostrar si el usuario tiene 2FA habilitado (se detecta por username)
        // El campo se muestra siempre y se verifica en authenticate
        ?>
        <p>
            <label for="wpec_2fa_code"><?php _e('Código de autenticación (2FA)', 'wp-extreme-customize'); ?></label><br>
            <input type="text" name="wpec_2fa_code" id="wpec_2fa_code" class="input" style="width:100%; padding:8px; text-align:center; font-size:18px; letter-spacing:4px;" placeholder="000000" autocomplete="one-time-code" maxlength="6">
        </p>
        <?php
    }
    
    public function verify_2fa_login($user, $username, $password) {
        if (!$this->get_setting('enable_2fa', false)) {
            return $user;
        }
        
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Verificar si el usuario requiere 2FA
        $user_2fa = get_user_meta($user->ID, 'wpec_2fa_enabled', true);
        $force_roles = $this->get_setting('force_2fa_roles', []);
        $user_roles = (array) $user->roles;
        
        $requires_2fa = ($user_2fa === 'yes') || 
                        (count(array_intersect($user_roles, $force_roles)) > 0);
        
        if (!$requires_2fa) {
            return $user;
        }
        
        $code = isset($_POST['wpec_2fa_code']) ? sanitize_text_field(wp_unslash($_POST['wpec_2fa_code'])) : '';
        
        if (empty($code)) {
            return new WP_Error('wpec_2fa_missing', __('Se requiere el código de autenticación 2FA.', 'wp-extreme-customize'));
        }
        
        $secret = get_user_meta($user->ID, 'wpec_2fa_secret', true);
        
        if (empty($secret)) {
            // Si no hay secreto, permitir login (seguridad)
            return $user;
        }
        
        // Verificar código TOTP
        if (!$this->verify_totp($code, $secret)) {
            // Verificar código de respaldo
            if (!$this->verify_backup_code($code, $user->ID)) {
                return new WP_Error('wpec_2fa_invalid', __('Código de autenticación inválido.', 'wp-extreme-customize'));
            }
        }
        
        return $user;
    }
    
    public function show_2fa_settings($user) {
        if (!$this->get_setting('enable_2fa', false)) {
            return;
        }
        
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        $enabled = get_user_meta($user->ID, 'wpec_2fa_enabled', true);
        $secret = get_user_meta($user->ID, 'wpec_2fa_secret', true);
        $otpauth_url = '';
        
        if ($secret) {
            $issuer = rawurlencode(get_bloginfo('name'));
            $account = rawurlencode($user->user_login);
            $otpauth_url = 'otpauth://totp/' . $issuer . ':' . $account . '?secret=' . $secret . '&issuer=' . $issuer;
        }
        ?>
        <h2><?php _e('Autenticación de Dos Factores (2FA)', 'wp-extreme-customize'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wpec_2fa_enabled"><?php _e('Activar 2FA', 'wp-extreme-customize'); ?></label></th>
                <td>
                    <input type="checkbox" name="wpec_2fa_enabled" id="wpec_2fa_enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                    <p class="description"><?php _e('Requiere un código de 6 dígitos al iniciar sesión.', 'wp-extreme-customize'); ?></p>
                </td>
            </tr>
            
            <?php if ($enabled === 'yes' && $secret): ?>
            <tr>
                <th><?php _e('Código TOTP', 'wp-extreme-customize'); ?></th>
                <td>
                    <p><?php _e('Escanea este QR con Google Authenticator, Authy o similar:', 'wp-extreme-customize'); ?></p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo esc_attr(rawurlencode($otpauth_url)); ?>" width="200" height="200" alt="QR Code 2FA">
                    <p><?php _e('Secret (si no puedes escanear):', 'wp-extreme-customize'); ?></p>
                    <code style="font-size:14px;"><?php echo esc_html($secret); ?></code>
                </td>
            </tr>
            <tr>
                <th><?php _e('Probar código', 'wp-extreme-customize'); ?></th>
                <td>
                    <input type="text" id="wpec_2fa_test_code" style="width:100px;" maxlength="6" placeholder="000000">
                    <button type="button" class="button" id="wpec_verify_2fa_btn"><?php _e('Verificar', 'wp-extreme-customize'); ?></button>
                    <span id="wpec_2fa_test_result" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th><?php _e('Códigos de respaldo', 'wp-extreme-customize'); ?></th>
                <td>
                    <button type="button" class="button" id="wpec_generate_backup_btn"><?php _e('Generar códigos de respaldo', 'wp-extreme-customize'); ?></button>
                    <div id="wpec_backup_codes_container" style="margin-top:10px;"></div>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <script>
        jQuery(function($) {
            var nonce = '<?php echo wp_create_nonce('wpec_2fa_ajax'); ?>';
            var userId = '<?php echo intval($user->ID); ?>';
            
            $('#wpec_verify_2fa_btn').on('click', function() {
                var code = $('#wpec_2fa_test_code').val().trim();
                var $result = $('#wpec_2fa_test_result');
                
                if (code.length !== 6) {
                    $result.html('<span style="color:red;"><?php _e('El código debe tener 6 dígitos', 'wp-extreme-customize'); ?></span>');
                    return;
                }
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'wpec_verify_2fa',
                    user_id: userId,
                    code: code,
                    nonce: nonce
                }, function(res) {
                    if (res.success) {
                        $result.html('<span style="color:green;">&#10004; <?php _e('Código válido', 'wp-extreme-customize'); ?></span>');
                    } else {
                        $result.html('<span style="color:red;">&#10008; ' + (res.data.message || '<?php _e('Código inválido', 'wp-extreme-customize'); ?>') + '</span>');
                    }
                });
            });
            
            $('#wpec_generate_backup_btn').on('click', function() {
                if (!confirm('<?php _e('¿Generar 10 nuevos códigos de respaldo? Los anteriores dejarán de funcionar.', 'wp-extreme-customize'); ?>')) {
                    return;
                }
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'wpec_generate_backup_codes',
                    user_id: userId,
                    nonce: nonce
                }, function(res) {
                    if (res.success) {
                        var html = '<p style="color:red;"><?php _e('GUARDA ESTOS CÓDIGOS. Solo se muestran una vez:', 'wp-extreme-customize'); ?></p><pre style="background:#f0f0f0; padding:10px;">' + res.data.codes + '</pre>';
                        $('#wpec_backup_codes_container').html(html);
                    } else {
                        alert(res.data.message || '<?php _e('Error', 'wp-extreme-customize'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function save_2fa_settings($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        if (!isset($_POST['wpec_2fa_enabled'])) {
            update_user_meta($user_id, 'wpec_2fa_enabled', 'no');
            delete_user_meta($user_id, 'wpec_2fa_secret');
            delete_user_meta($user_id, 'wpec_2fa_backup_codes');
            return;
        }
        
        update_user_meta($user_id, 'wpec_2fa_enabled', 'yes');
        
        // Generar secreto si no existe
        if (empty(get_user_meta($user_id, 'wpec_2fa_secret', true))) {
            update_user_meta($user_id, 'wpec_2fa_secret', $this->generate_base32_secret(20));
        }
    }
    
    public function ajax_verify_2fa() {
        check_ajax_referer('wpec_2fa_ajax', 'nonce');
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
        
        if ($user_id !== get_current_user_id() && !current_user_can('edit_user', $user_id)) {
            wp_send_json_error(['message' => __('Permisos insuficientes.', 'wp-extreme-customize')]);
        }
        
        $secret = get_user_meta($user_id, 'wpec_2fa_secret', true);
        
        if (empty($secret)) {
            wp_send_json_error(['message' => __('No hay secreto 2FA configurado.', 'wp-extreme-customize')]);
        }
        
        if ($this->verify_totp($code, $secret)) {
            wp_send_json_success(['message' => __('Código válido', 'wp-extreme-customize')]);
        } elseif ($this->verify_backup_code($code, $user_id)) {
            wp_send_json_success(['message' => __('Código de respaldo válido', 'wp-extreme-customize')]);
        } else {
            wp_send_json_error(['message' => __('Código inválido.', 'wp-extreme-customize')]);
        }
    }
    
    public function ajax_generate_backup_codes() {
        check_ajax_referer('wpec_2fa_ajax', 'nonce');
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if ($user_id !== get_current_user_id() && !current_user_can('edit_user', $user_id)) {
            wp_send_json_error(['message' => __('Permisos insuficientes.', 'wp-extreme-customize')]);
        }
        
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->generate_backup_code();
        }
        
        // Guardar hashes de los códigos (no en texto plano)
        update_user_meta($user_id, 'wpec_2fa_backup_codes', wp_json_encode(array_map(function($c) {
            return hash('sha256', 'wpec_backup_' . $c);
        }, $codes)));
        
        wp_send_json_success(['codes' => implode("\n", $codes)]);
    }
    
    private function verify_backup_code($code, $user_id) {
        $stored = get_user_meta($user_id, 'wpec_2fa_backup_codes', true);
        
        if (empty($stored)) {
            return false;
        }
        
        $codes = json_decode($stored, true);
        
        if (!is_array($codes)) {
            return false;
        }
        
        $hash = hash('sha256', 'wpec_backup_' . $code);
        
        if (in_array($hash, $codes, true)) {
            // Marcar código como usado
            $codes = array_diff($codes, [$hash]);
            update_user_meta($user_id, 'wpec_2fa_backup_codes', wp_json_encode(array_values($codes)));
            return true;
        }
        
        return false;
    }
    
    // ==================== TOTP CORRECTO ====================
    
    /**
     * Genera un secreto TOTP en Base32 (formato RFC 4281)
     */
    private function generate_base32_secret($length = 20) {
        $bytes = random_bytes($length);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $bits = 0;
        $value = 0;
        
        for ($i = 0; $i < strlen($bytes); $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
            $bits += 8;
            
            while ($bits >= 5) {
                $secret .= $alphabet[($value & (0x1F << ($bits - 5))) >> ($bits - 5)];
                $bits -= 5;
            }
        }
        
        if ($bits > 0) {
            $secret .= $alphabet[($value & (0x1F << $bits)) >> $bits];
        }
        
        return $secret;
    }
    
    /**
     * Genera código TOTP para un momento dado
     */
    private function generate_totp($secret_b32, $time_step = 0) {
        // Decodificar Base32 a bytes
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret_b32 = strtoupper(str_replace('=', '', $secret_b32));
        
        $binary = '';
        $buffer = 0;
        $bits_left = 0;
        
        for ($i = 0; $i < strlen($secret_b32); $i++) {
            $char = strpos($alphabet, $secret_b32[$i]);
            
            if ($char === false) {
                continue;
            }
            
            $buffer = ($buffer << 5) | $char;
            $bits_left += 5;
            
            if ($bits_left >= 8) {
                $binary .= chr(($buffer & (0xFF << ($bits_left - 8))) >> ($bits_left - 8));
                $bits_left -= 8;
            }
        }
        
        // Generar tiempo (30 seg por paso)
        $time_counter = intdiv(time(), 30) + $time_step;
        $packed = pack('N', intdiv($time_counter, 0x100000000));
        $packed .= pack('N', $time_counter % 0x100000000);
        
        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $packed, $binary, true);
        
        // Extraer último byte (offset dinámico)
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        
        // Código de 6 dígitos
        $code = ((ord($hmac[$offset]) & 0x7F) << 24)
              | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
              | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
              | (ord($hmac[$offset + 3]) & 0xFF);
        
        $code = $code % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verifica código TOTP con ventana de ±1 paso
     */
    private function verify_totp($code, $secret_b32) {
        $code = trim($code);
        
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }
        
        // ±1 paso de 30 segundos
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals($this->generate_totp($secret_b32, $i), $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function generate_backup_code() {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin I, O, 0, 1 para evitar confusión
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return substr($code, 0, 4) . '-' . substr($code, 4, 4);
    }
    
    // ==================== SECURITY HEADERS ====================
    
    public function add_security_headers() {
        if (!$this->get_setting('enable_security_headers', true)) {
            return;
        }
        
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
            
            if ($this->get_setting('enable_hsts', false)) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }
            
            if ($this->get_setting('enable_csp', false)) {
                $csp = $this->get_setting('csp_policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;");
                header('Content-Security-Policy: ' . $csp);
            }
        }
    }
    
    public function restrict_rest_api($result) {
        if (!$this->get_setting('restrict_rest_api', false)) {
            return $result;
        }
        
        if (!is_user_logged_in()) {
            return new WP_Error('wpec_rest_blocked', __('Acceso a la API REST restringido.', 'wp-extreme-customize'), ['status' => 401]);
        }
        
        return $result;
    }
    
    public function remove_pingback($methods) {
        if (!$this->get_setting('disable_pingbacks', true)) {
            return $methods;
        }
        
        unset($methods['pingback.ping']);
        return $methods;
    }
    
    public function force_https() {
        if (!$this->get_setting('force_https', false) || is_ssl()) {
            return;
        }
        
        $host = $_SERVER['SERVER_NAME'] ?? '';
        if (strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
            wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
            exit;
        }
    }
    
    public function disable_editors() {
        if ($this->get_setting('disable_file_edit', true)) {
            define('DISALLOW_FILE_EDIT', true);
        }
        
        if ($this->get_setting('disable_plugin_install', false) || $this->get_setting('disable_theme_install', false)) {
            define('DISALLOW_FILE_MODS', true);
        }
    }
    
    public function add_security_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Seguridad Extrema', 'wp-extreme-customize'),
            __('Seguridad', 'wp-extreme-customize'),
            'manage_options',
            'wpec-security',
            [$this, 'render_security_settings']
        );
    }
    
    public function render_security_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Seguridad Extrema', 'wp-extreme-customize'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_security'); ?>
                
                <div class="wpec-tabs">
                    <div class="wpec-tab-nav">
                        <a href="#wpec-tab-rate-limit" class="wpec-tab-link active" data-tab="rate-limit"><?php _e('Rate Limiting', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-2fa" class="wpec-tab-link" data-tab="2fa"><?php _e('Autenticación 2FA', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-headers" class="wpec-tab-link" data-tab="headers"><?php _e('Headers Seguridad', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-other" class="wpec-tab-link" data-tab="other"><?php _e('Otras Opciones', 'wp-extreme-customize'); ?></a>
                    </div>
                    
                    <div class="wpec-tab-content active" id="wpec-tab-rate-limit">
                        <h2><?php _e('Límite de Intentos de Login', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Activar Rate Limiting', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[enable_rate_limit]" value="yes" <?php checked($this->settings['enable_rate_limit'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Máximo Intentos', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="number" name="wpec_security_settings[max_login_attempts]" value="<?php echo esc_attr(intval($this->settings['max_login_attempts'] ?? 5)); ?>" class="small-text" min="1" max="20"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Duración Bloqueo (segundos)', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="number" name="wpec_security_settings[lockout_duration]" value="<?php echo esc_attr(intval($this->settings['lockout_duration'] ?? 900)); ?>" class="small-text" min="60" max="86400"></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-2fa">
                        <h2><?php _e('Autenticación de Dos Factores (2FA)', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Activar 2FA', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[enable_2fa]" value="yes" <?php checked($this->settings['enable_2fa'] ?? '', 'yes'); ?>><p class="description"><?php _e('Permite a los usuarios activar autenticación de dos factores.', 'wp-extreme-customize'); ?></p></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Forzar 2FA para Roles', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <?php
                                    $force_roles = (array) ($this->settings['force_2fa_roles'] ?? []);
                                    $all_roles = wp_roles()->get_names();
                                    foreach ($all_roles as $role => $name):
                                    ?>
                                    <label style="display: block; margin: 5px 0;">
                                        <input type="checkbox" name="wpec_security_settings[force_2fa_roles][]" value="<?php echo esc_attr($role); ?>" <?php checked(in_array($role, $force_roles, true)); ?>>
                                        <?php echo esc_html($name); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-headers">
                        <h2><?php _e('Headers de Seguridad HTTP', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Activar Headers', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[enable_security_headers]" value="yes" <?php checked($this->settings['enable_security_headers'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Activar HSTS', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[enable_hsts]" value="yes" <?php checked($this->settings['enable_hsts'] ?? '', 'yes'); ?>><p class="description"><?php _e('Requiere HTTPS válido en el sitio.', 'wp-extreme-customize'); ?></p></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Activar CSP', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[enable_csp]" value="yes" <?php checked($this->settings['enable_csp'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Política CSP', 'wp-extreme-customize'); ?></label></th>
                                <td><textarea name="wpec_security_settings[csp_policy]" rows="3" class="large-text"><?php echo esc_textarea($this->settings['csp_policy'] ?? "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;"); ?></textarea></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-other">
                        <h2><?php _e('Otras Opciones de Seguridad', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Restringir REST API', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[restrict_rest_api]" value="yes" <?php checked($this->settings['restrict_rest_api'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Desactivar Pingbacks', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[disable_pingbacks]" value="yes" <?php checked($this->settings['disable_pingbacks'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Forzar HTTPS', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[force_https]" value="yes" <?php checked($this->settings['force_https'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Ocultar Versión WordPress', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[hide_wp_version]" value="yes" <?php checked($this->settings['hide_wp_version'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Desactivar XML-RPC', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[disable_xmlrpc]" value="yes" <?php checked($this->settings['disable_xmlrpc'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Desactivar Editor de Archivos', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[disable_file_edit]" value="yes" <?php checked($this->settings['disable_file_edit'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Desactivar Instalación Plugins', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[disable_plugin_install]" value="yes" <?php checked($this->settings['disable_plugin_install'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Desactivar Instalación Temas', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_security_settings[disable_theme_install]" value="yes" <?php checked($this->settings['disable_theme_install'] ?? '', 'yes'); ?>></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                $('.wpec-tab-link').on('click', function(e) {
                    e.preventDefault();
                    var tab = $(this).data('tab');
                    $('.wpec-tab-link').removeClass('active');
                    $(this).addClass('active');
                    $('.wpec-tab-content').removeClass('active');
                    $('#wpec-tab-' + tab).addClass('active');
                });
            });
            </script>
        </div>
        <?php
    }
    
    private function get_client_ip() {
        // Priorizar REMOTE_ADDR (más fiable)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Solo usar X-Forwarded-For si el servidor es un proxy confiable
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip = $ips[0];
        }
        
        // Validar formato IP
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'unknown';
        }
        
        return $ip;
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Security();
