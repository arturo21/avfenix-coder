
<?php
/**
 * Módulo Login Customizer - Personalización extrema del login
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Login_Customizer {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_login_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Personalizar página de login
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_scripts']);
        add_filter('login_headerurl', [$this, 'login_header_url']);
        add_filter('login_headertext', [$this, 'login_header_text']);
        add_filter('login_background_color', [$this, 'login_bg_color']);
        add_action('login_head', [$this, 'login_custom_css']);
        
        // Personalizar formularios
        add_filter('login_form_top', [$this, 'login_form_top']);
        add_filter('login_form_bottom', [$this, 'login_form_bottom']);
        
        // Redirecciones personalizadas
        add_filter('login_redirect', [$this, 'login_redirect'], 10, 3);
        add_filter('logout_redirect', [$this, 'logout_redirect']);
        
        // Mensajes personalizados
        add_filter('login_errors', [$this, 'custom_login_errors'], 10, 2);
    }
    
    public function enqueue_login_scripts() {
        wp_enqueue_style('wpec-login-style', WPEC_PLUGIN_URL . 'assets/css/login.css');
        wp_enqueue_script('wpec-login-script', WPEC_PLUGIN_URL . 'assets/js/login.js');
        
        // Pasar configuración a JavaScript
        wp_localize_script('wpec-login-script', 'wpec_login', [
            'logo_url' => $this->get_setting('logo_url', ''),
            'logo_width' => $this->get_setting('logo_width', '280px'),
            'bg_image' => $this->get_setting('bg_image', ''),
            'brand_color' => $this->get_setting('brand_color', '#764ba2')
        ]);
    }
    
    public function login_header_url($url) {
        return $this->get_setting('header_url', home_url());
    }
    
    public function login_header_text($text) {
        return $this->get_setting('header_text', get_bloginfo('name'));
    }
    
    public function login_bg_color($color) {
        return $this->get_setting('bg_color', $color);
    }
    
    public function login_custom_css() {
        $css = $this->get_setting('custom_css', '');
        if ($css) {
            echo '<style>' . wp_strip_all_tags($css, true) . '</style>';
        }
    }
    
    public function login_form_top($content) {
        $top = $this->get_setting('form_top', '');
        return $top . $content;
    }
    
    public function login_form_bottom($content) {
        $bottom = $this->get_setting('form_bottom', '');
        return $content . $bottom;
    }
    
    public function login_redirect($redirect_to, $request, $user) {
        $custom_redirect = $this->get_setting('login_redirect', '');
        if ($custom_redirect) {
            return home_url($custom_redirect);
        }
        return $redirect_to;
    }
    
    public function logout_redirect($redirect_to) {
        $custom_redirect = $this->get_setting('logout_redirect', '');
        if ($custom_redirect) {
            return home_url($custom_redirect);
        }
        return $redirect_to;
    }
    
    public function custom_login_errors($errors, $error) {
        $custom_errors = $this->get_setting('custom_errors', []);
        if (isset($custom_errors[$error])) {
            return $custom_errors[$error];
        }
        return $errors;
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Login_Customizer();
