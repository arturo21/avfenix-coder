
<?php
/**
 * Módulo Custom Login URL - URL de login personalizada
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Custom_Login_URL {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_login_url_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Redireccionar wp-login.php
        add_action('init', [$this, 'redirect_wp_login']);
        
        // Añadir rewrite rule
        add_action('init', [$this, 'add_rewrite_rules']);
        
        // Login form action
        add_filter('login_url', [$this, 'custom_login_url'], 10, 3);
        add_filter('logout_url', [$this, 'custom_logout_url'], 10, 3);
        add_filter('lostpassword_url', [$this, 'custom_lostpassword_url'], 10, 2);
        
        // Admin
        add_action('admin_menu', [$this, 'add_login_url_menu']);
    }
    
    public function redirect_wp_login() {
        if (!$this->get_setting('enable_custom_login_url', false)) {
            return;
        }
        
        $custom_slug = $this->get_setting('login_slug', 'login');
        $custom_slug = trim($custom_slug, '/');
        
        // Si estamos en wp-login.php y no es una acción especial
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            $action = isset($_GET['action']) ? $_GET['action'] : '';
            
            // Permitir acciones especiales (recuperar password, etc.)
            $allowed_actions = ['rp', 'resetpass', 'logout', 'lostpassword', 'register'];
            
            if (empty($action) || !in_array($action, $allowed_actions)) {
                // Redireccionar a la URL personalizada
                $redirect_url = home_url('/' . $custom_slug . '/');
                if (!empty($_GET['redirect_to'])) {
                    $redirect_url = add_query_arg('redirect_to', $_GET['redirect_to'], $redirect_url);
                }
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
    }
    
    public function add_rewrite_rules() {
        if (!$this->get_setting('enable_custom_login_url', false)) {
            return;
        }
        
        $custom_slug = $this->get_setting('login_slug', 'login');
        $custom_slug = trim($custom_slug, '/');
        
        // Regla para login personalizado
        add_rewrite_rule(
            '^' . $custom_slug . '/?$',
            'index.php?wpec_custom_login=1',
            'top'
        );
        
        // Regla para logout personalizado
        $logout_slug = $this->get_setting('logout_slug', 'logout');
        $logout_slug = trim($logout_slug, '/');
        if ($logout_slug !== $custom_slug) {
            add_rewrite_rule(
                '^' . $logout_slug . '/?$',
                'index.php?wpec_custom_logout=1',
                'top'
            );
        }
        
        // Regla para lost password
        $lost_slug = $this->get_setting('lostpassword_slug', 'lost-password');
        $lost_slug = trim($lost_slug, '/');
        add_rewrite_rule(
            '^' . $lost_slug . '/?$',
            'index.php?wpec_custom_lostpassword=1',
            'top'
        );
        
        // Regla para register
        $register_slug = $this->get_setting('register_slug', 'register');
        $register_slug = trim($register_slug, '/');
        if (get_option('users_can_register')) {
            add_rewrite_rule(
                '^' . $register_slug . '/?$',
                'index.php?wpec_custom_register=1',
                'top'
            );
        }
    }
    
    public function custom_login_url($login_url, $redirect, $force_reauth) {
        if (!$this->get_setting('enable_custom_login_url', false)) {
            return $login_url;
        }
        
        $custom_slug = $this->get_setting('login_slug', 'login');
        $new_url = home_url('/' . trim($custom_slug, '/') . '/');
        
        if ($redirect) {
            $new_url = add_query_arg('redirect_to', $redirect, $new_url);
        }
        
        if ($force_reauth) {
            $new_url = add_query_arg('reauth', '1', $new_url);
        }
        
        return $new_url;
    }
    
    public function custom_logout_url($logout_url, $redirect, $logout) {
        if (!$this->get_setting('enable_custom_login_url', false)) {
            return $logout_url;
        }
        
        $logout_slug = $this->get_setting('logout_slug', 'logout');
        $new_url = home_url('/' . trim($logout_slug, '/') . '/');
        
        if ($redirect) {
            $new_url = add_query_arg('redirect_to', $redirect, $new_url);
        }
        
        return $new_url;
    }
    
    public function custom_lostpassword_url($lostpassword_url, $redirect) {
        if (!$this->get_setting('enable_custom_login_url', false)) {
            return $lostpassword_url;
        }
        
        $lost_slug = $this->get_setting('lostpassword_slug', 'lost-password');
        $new_url = home_url('/' . trim($lost_slug, '/') . '/');
        
        if ($redirect) {
            $new_url = add_query_arg('redirect_to', $redirect, $new_url);
        }
        
        return $new_url;
    }
    
    public function add_login_url_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('URL Login Personalizada', 'wp-extreme-customize'),
            __('URL Login', 'wp-extreme-customize'),
            'manage_options',
            'wpec-login-url',
            [$this, 'render_login_url_settings']
        );
    }
    
    public function render_login_url_settings() {
        ?>
        <div class="wrap">
            <h1><?php _e('URL de Login Personalizada', 'wp-extreme-customize'); ?></h1>
            <p><?php _e('Cambia las URLs de login, logout, registro y recuperación de contraseña.', 'wp-extreme-customize'); ?></p>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_login_url'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Activar URL Personalizada', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpec_login_url_settings[enable_custom_login_url]" value="yes" <?php checked($this->settings['enable_custom_login_url'] ?? '', 'yes'); ?>>
                            <p class="description"><?php _e('Requiere regenerar los enlaces permanentes (Ajustes → Enlaces permanentes → Guardar cambios).', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Slug de Login', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_login_url_settings[login_slug]" value="<?php echo esc_attr($this->settings['login_slug'] ?? 'login'); ?>" class="regular-text">
                            <p class="description"><?php _e('Ejemplo: "login", "acceso", "entrar". Solo letras, números y guiones.', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Slug de Logout', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_login_url_settings[logout_slug]" value="<?php echo esc_attr($this->settings['logout_slug'] ?? 'logout'); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Slug Recuperar Contraseña', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_login_url_settings[lostpassword_slug]" value="<?php echo esc_attr($this->settings['lostpassword_slug'] ?? 'lost-password'); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Slug Registro', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_login_url_settings[register_slug]" value="<?php echo esc_attr($this->settings['register_slug'] ?? 'register'); ?>" class="regular-text">
                            <p class="description"><?php _e('Solo si el registro de usuarios está habilitado.', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('URL Actual de Login', 'wp-extreme-customize'); ?></th>
                        <td><code><?php echo esc_html(home_url('/' . trim($this->settings['login_slug'] ?? 'login', '/') . '/')); ?></code></td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Importante:', 'wp-extreme-customize'); ?></strong> <?php _e('Después de cambiar los slugs, debes ir a <strong>Ajustes → Enlaces permanentes</strong> y hacer clic en <strong>Guardar cambios</strong> para que las nuevas reglas de reescritura tengan efecto.', 'wp-extreme-customize'); ?></p>
            </div>
        </div>
        <?php
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Custom_Login_URL();
