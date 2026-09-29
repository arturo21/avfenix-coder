
<?php
/**
 * Módulo Login Customizer - Personalización completa de la página de login
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
        // Enqueue scripts y estilos
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_assets']);
        
        // Personalizar logo
        add_filter('login_headerurl', [$this, 'login_header_url']);
        add_filter('login_headertext', [$this, 'login_header_text']);
        
        // Personalizar mensajes
        add_filter('login_message', [$this, 'custom_login_message']);
        add_filter('login_errors', [$this, 'custom_login_errors']);
        
        // Redirecciones
        add_filter('login_redirect', [$this, 'custom_login_redirect'], 10, 3);
        add_filter('logout_redirect', [$this, 'custom_logout_redirect'], 10, 2);
        
        // Admin
        add_action('admin_menu', [$this, 'add_login_customizer_menu']);
    }
    
    public function enqueue_login_assets() {
        // CSS personalizado
        $custom_css = $this->get_setting('custom_css', '');
        if (!empty($custom_css)) {
            wp_add_inline_style('login', $custom_css);
        }
        
        // JS personalizado
        $custom_js = $this->get_setting('custom_js', '');
        if (!empty($custom_js)) {
            wp_add_inline_script('login', $custom_js);
        }
        
        // Generar CSS dinámico
        $dynamic_css = $this->generate_dynamic_css();
        if (!empty($dynamic_css)) {
            wp_add_inline_style('login', $dynamic_css);
        }
    }
    
    private function generate_dynamic_css() {
        $css = '';
        
        // Logo
        $logo = $this->get_setting('login_logo', '');
        if ($logo) {
            $css .= '#login h1 a { background-image: url(' . esc_url($logo) . '); background-size: contain; width: 300px; height: 100px; }';
        }
        
        // Colores
        $brand_color = $this->get_setting('brand_color', '#764ba2');
        $secondary_color = $this->get_setting('secondary_color', '#667eea');
        $form_bg = $this->get_setting('form_bg_color', '#ffffff');
        $label_color = $this->get_setting('label_color', '#333333');
        $input_bg = $this->get_setting('input_bg_color', '#ffffff');
        $input_border = $this->get_setting('input_border_color', '#dddddd');
        $button_color = $this->get_setting('button_color', $brand_color);
        $button_hover = $this->get_setting('button_hover_color', '#5a3a7a');
        $link_color = $this->get_setting('link_color', $brand_color);
        $error_color = $this->get_setting('error_color', '#dc3232');
        $success_color = $this->get_setting('success_color', '#28a745');
        
        // Fondo
        $bg_image = $this->get_setting('login_bg_image', '');
        $bg_color = $this->get_setting('login_bg_color', '#f0f0f0');
        
        if ($bg_image) {
            $css .= 'body.login { background-image: url(' . esc_url($bg_image) . '); background-size: cover; background-position: center; }';
        } else {
            $css .= 'body.login { background-color: ' . esc_attr($bg_color) . '; }';
        }
        
        // Formulario
        $css .= '
            #login { background-color: ' . esc_attr($form_bg) . '; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            #login h1 a { opacity: 1; }
            #login label { color: ' . esc_attr($label_color) . '; }
            #login input[type="text"], #login input[type="password"], #login input[type="email"] { background-color: ' . esc_attr($input_bg) . '; border-color: ' . esc_attr($input_border) . '; }
            #login input[type="text"]:focus, #login input[type="password"]:focus, #login input[type="email"]:focus { border-color: ' . esc_attr($brand_color) . '; box-shadow: 0 0 0 1px ' . esc_attr($brand_color) . '; }
            .wp-core-ui .button-primary { background-color: ' . esc_attr($button_color) . '; border-color: ' . esc_attr($button_color) . '; }
            .wp-core-ui .button-primary:hover, .wp-core-ui .button-primary:focus { background-color: ' . esc_attr($button_hover) . '; border-color: ' . esc_attr($button_hover) . '; }
            #login a, #login .message a { color: ' . esc_attr($link_color) . '; }
            #login a:hover, #login .message a:hover { color: ' . esc_attr($button_hover) . '; }
            .login .message.error, .login .error { border-left-color: ' . esc_attr($error_color) . '; }
            .login .message.success, .login .success { border-left-color: ' . esc_attr($success_color) . '; }
        ';
        
        // Ocultar elementos
        if ($this->get_setting('hide_wp_logo', false)) {
            $css .= '#login h1 a { background-image: none !important; }';
        }
        
        if ($this->get_setting('hide_back_to_site', false)) {
            $css .= '#backtoblog { display: none !important; }';
        }
        
        return $css;
    }
    
    public function login_header_url() {
        return $this->get_setting('header_url', home_url());
    }
    
    public function login_header_text() {
        return $this->get_setting('header_text', get_bloginfo('name'));
    }
    
    public function custom_login_message($message) {
        $custom_message = $this->get_setting('custom_message', '');
        if (!empty($custom_message)) {
            return '<div class="message wpec-custom-message">' . wp_kses_post($custom_message) . '</div>';
        }
        return $message;
    }
    
    public function custom_login_errors($errors) {
        $custom_error = $this->get_setting('custom_error_message', '');
        if (!empty($custom_error) && is_wp_error($errors)) {
            $error_codes = $errors->get_error_codes();
            if (in_array('invalid_username', $error_codes) || in_array('incorrect_password', $error_codes)) {
                $errors->remove('invalid_username');
                $errors->remove('incorrect_password');
                $errors->add('custom_error', $custom_error);
            }
        }
        return $errors;
    }
    
    public function custom_login_redirect($redirect_to, $requested_redirect, $user) {
        $custom_redirect = $this->get_setting('redirect_after_login', '');
        if (!empty($custom_redirect)) {
            return $custom_redirect;
        }
        return $redirect_to;
    }
    
    public function custom_logout_redirect($redirect_to, $requested_redirect) {
        $custom_redirect = $this->get_setting('redirect_after_logout', '');
        if (!empty($custom_redirect)) {
            return $custom_redirect;
        }
        return $redirect_to;
    }
    
    public function add_login_customizer_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Login Customizer', 'wp-extreme-customize'),
            __('Login Customizer', 'wp-extreme-customize'),
            'manage_options',
            'wpec-login-customizer',
            [$this, 'render_login_customizer_settings']
        );
    }
    
    public function render_login_customizer_settings() {
        ?>
        <div class="wrap">
            <h1><?php _e('Login Customizer', 'wp-extreme-customize'); ?></h1>
            
            <div class="wpec-tabs">
                <div class="wpec-tab-nav">
                    <a href="#wpec-tab-appearance" class="wpec-tab-link active" data-tab="appearance"><?php _e('Apariencia', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-colors" class="wpec-tab-link" data-tab="colors"><?php _e('Colores', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-messages" class="wpec-tab-link" data-tab="messages"><?php _e('Mensajes', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-redirects" class="wpec-tab-link" data-tab="redirects"><?php _e('Redirecciones', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-advanced" class="wpec-tab-link" data-tab="advanced"><?php _e('Avanzado', 'wp-extreme-customize'); ?></a>
                </div>
                
                <div class="wpec-tab-content active" id="wpec-tab-appearance">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_login'); ?>
                        
                        <h2><?php _e('Logo', 'wp-extreme-customize'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('URL del Logo', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <input type="url" name="wpec_login_settings[login_logo]" value="<?php echo esc_attr($this->settings['login_logo'] ?? ''); ?>" class="large-text">
                                    <?php if ($this->settings['login_logo'] ?? ''): ?>
                                        <br><img src="<?php echo esc_url($this->settings['login_logo']); ?>" style="max-width: 300px; max-height: 100px;">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('URL del Enlace del Logo', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="url" name="wpec_login_settings[header_url]" value="<?php echo esc_attr($this->settings['header_url'] ?? home_url()); ?>" class="large-text"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Texto Alt/Título', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[header_text]" value="<?php echo esc_attr($this->settings['header_text'] ?? get_bloginfo('name')); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                        
                        <h2><?php _e('Fondo', 'wp-extreme-customize'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Imagen de Fondo', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <input type="url" name="wpec_login_settings[login_bg_image]" value="<?php echo esc_attr($this->settings['login_bg_image'] ?? ''); ?>" class="large-text">
                                    <?php if ($this->settings['login_bg_image'] ?? ''): ?>
                                        <br><img src="<?php echo esc_url($this->settings['login_bg_image']); ?>" style="max-width: 300px;">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color de Fondo', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[login_bg_color]" value="<?php echo esc_attr($this->settings['login_bg_color'] ?? '#f0f0f0'); ?>" class="color-picker"></td>
                            </tr>
                        </table>
                        
                        <h2><?php _e('Formulario', 'wp-extreme-customize'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Color de Fondo del Formulario', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[form_bg_color]" value="<?php echo esc_attr($this->settings['form_bg_color'] ?? '#ffffff'); ?>" class="color-picker"></td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-colors">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_login'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Color Principal (Brand)', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[brand_color]" value="<?php echo esc_attr($this->settings['brand_color'] ?? '#764ba2'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Secundario', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[secondary_color]" value="<?php echo esc_attr($this->settings['secondary_color'] ?? '#667eea'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Etiquetas', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[label_color]" value="<?php echo esc_attr($this->settings['label_color'] ?? '#333333'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Fondo Inputs', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[input_bg_color]" value="<?php echo esc_attr($this->settings['input_bg_color'] ?? '#ffffff'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Borde Inputs', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[input_border_color]" value="<?php echo esc_attr($this->settings['input_border_color'] ?? '#dddddd'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Botón', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[button_color]" value="<?php echo esc_attr($this->settings['button_color'] ?? '#764ba2'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Botón Hover', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[button_hover_color]" value="<?php echo esc_attr($this->settings['button_hover_color'] ?? '#5a3a7a'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Enlaces', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[link_color]" value="<?php echo esc_attr($this->settings['link_color'] ?? '#764ba2'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Error', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[error_color]" value="<?php echo esc_attr($this->settings['error_color'] ?? '#dc3232'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Éxito', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_login_settings[success_color]" value="<?php echo esc_attr($this->settings['success_color'] ?? '#28a745'); ?>" class="color-picker"></td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-messages">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_login'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Mensaje Personalizado', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_login_settings[custom_message]" rows="4" class="large-text"><?php echo esc_textarea($this->settings['custom_message'] ?? ''); ?></textarea>
                                    <p class="description"><?php _e('HTML permitido. Se muestra encima del formulario.', 'wp-extreme-customize'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Mensaje de Error Personalizado', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <input type="text" name="wpec_login_settings[custom_error_message]" value="<?php echo esc_attr($this->settings['custom_error_message'] ?? ''); ?>" class="large-text">
                                    <p class="description"><?php _e('Reemplaza el mensaje de error genérico de credenciales incorrectas.', 'wp-extreme-customize'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-redirects">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_login'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Redirección después de Login', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="url" name="wpec_login_settings[redirect_after_login]" value="<?php echo esc_attr($this->settings['redirect_after_login'] ?? ''); ?>" class="large-text"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Redirección después de Logout', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="url" name="wpec_login_settings[redirect_after_logout]" value="<?php echo esc_attr($this->settings['redirect_after_logout'] ?? ''); ?>" class="large-text"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Marcar "Recordarme" por defecto', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_login_settings[remember_me_default]" value="yes" <?php checked($this->settings['remember_me_default'] ?? '', 'yes'); ?>></td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-advanced">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_login'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Ocultar Logo de WordPress', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_login_settings[hide_wp_logo]" value="yes" <?php checked($this->settings['hide_wp_logo'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Ocultar "Volver al sitio"', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_login_settings[hide_back_to_site]" value="yes" <?php checked($this->settings['hide_back_to_site'] ?? '', 'yes'); ?>></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('CSS Personalizado', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_login_settings[custom_css]" rows="10" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea($this->settings['custom_css'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('JavaScript Personalizado', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_login_settings[custom_js]" rows="8" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea($this->settings['custom_js'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
            
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
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Login_Customizer();
