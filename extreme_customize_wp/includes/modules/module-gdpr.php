
<?php
/**
 * Módulo GDPR/Cookies - Configuración de privacidad y cookies
 * Version: 1.0.2 - Corrección de localize y cookies
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_GDPR {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_gdpr_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Añadir aviso de cookies
        add_action('wp_footer', [$this, 'add_cookie_notice']);
        add_shortcode('wpec_cookie_notice', [$this, 'shortcode_cookie_notice']);
        
        // Administrar
        add_action('admin_menu', [$this, 'add_gdpr_menu']);
        
        // Manejar aceptación de cookies
        add_action('wp_ajax_wpec_accept_cookies', [$this, 'ajax_accept_cookies']);
        add_action('wp_ajax_nopriv_wpec_accept_cookies', [$this, 'ajax_accept_cookies']);
    }
    
    public function add_cookie_notice() {
        if (!$this->should_show_notice()) {
            return;
        }
        
        $this->enqueue_cookie_assets();
        
        $notice = $this->get_setting('notice_text', '');
        $accept_text = $this->get_setting('accept_text', 'Aceptar');
        $reject_text = $this->get_setting('reject_text', 'Rechazar');
        $policy_url = $this->get_setting('policy_url', '');
        
        if (empty($notice)) {
            return;
        }
        
        $expiry = intval($this->get_setting('cookie_expiry', 365));
        ?>
        <div id="wpec-cookie-notice" style="position: fixed; bottom: 0; left: 0; right: 0; background: #f4f4f4; color: #333; padding: 15px 20px; text-align: center; border-top: 1px solid #ddd; z-index: 99999; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);">
            <span style="display: inline-block; max-width: 600px;"><?php echo esc_html($notice); ?></span>
            <br style="margin-top: 10px;">
            <button type="button" id="wpec-cookie-accept" style="background: #764ba2; color: white; padding: 10px 24px; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 0 5px;"><?php echo esc_html($accept_text); ?></button>
            <?php if (!empty($reject_text)): ?>
            <button type="button" id="wpec-cookie-reject" style="background: #e0e0e0; color: #333; padding: 10px 24px; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 0 5px;"><?php echo esc_html($reject_text); ?></button>
            <?php endif; ?>
            <?php if ($policy_url): ?>
            <a href="<?php echo esc_url($policy_url); ?>" style="color: #764ba2; margin-left: 10px; font-size: 13px;"><?php _e('Política de cookies', 'wp-extreme-customize'); ?></a>
            <?php endif; ?>
            <script>
            document.getElementById('wpec-cookie-accept').addEventListener('click', function() {
                wpecSetCookie('wpec_cookies_accepted', 'all', <?php echo intval($expiry); ?>);
                jQuery('#wpec-cookie-notice').fadeOut(300, function(){ jQuery(this).remove(); });
            });
            <?php if (!empty($reject_text)): ?>
            document.getElementById('wpec-cookie-reject').addEventListener('click', function() {
                wpecSetCookie('wpec_cookies_accepted', 'necessary', <?php echo intval($expiry); ?>);
                jQuery('#wpec-cookie-notice').fadeOut(300, function(){ jQuery(this).remove(); });
            });
            <?php endif; ?>
            
            function wpecSetCookie(name, value, days) {
                var expires = new Date();
                expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
                document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
            }
            </script>
        </div>
        <?php
    }
    
    public function should_show_notice() {
        if (!isset($_COOKIE['wpec_cookies_accepted'])) {
            return true;
        }
        return false;
    }
    
    private function enqueue_cookie_assets() {
        // Solo en frontend
        if (is_admin()) {
            return;
        }
    }
    
    public function shortcode_cookie_notice($atts = []) {
        $notice = $this->get_setting('notice_text', 'Este sitio utiliza cookies para mejorar tu experiencia');
        $accept_text = $this->get_setting('accept_text', 'Aceptar');
        $policy_url = $this->get_setting('policy_url', '');
        $expiry = intval($this->get_setting('cookie_expiry', 365));
        
        ob_start();
        ?>
        <div id="wpec-cookie-notice" style="position: fixed; bottom: 0; left: 0; right: 0; background: #f4f4f4; color: #333; padding: 15px; text-align: center; border-top: 1px solid #ddd; z-index: 99999;">
            <?php echo esc_html($notice); ?>
            <br>
            <button type="button" id="wpec-cookie-accept" style="background: #764ba2; color: white; padding: 10px 20px; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;"><?php echo esc_html($accept_text); ?></button>
            <?php if ($policy_url): ?>
            <a href="<?php echo esc_url($policy_url); ?>" style="color: #764ba2; margin-left: 10px;"><?php _e('Política de cookies', 'wp-extreme-customize'); ?></a>
            <?php endif; ?>
        </div>
        <script>
        document.getElementById('wpec-cookie-accept').addEventListener('click', function() {
            var expires = new Date();
            expires.setTime(expires.getTime() + <?php echo intval($expiry); ?> * 24 * 60 * 60 * 1000);
            document.cookie = 'wpec_cookies_accepted=all;expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
            jQuery('#wpec-cookie-notice').fadeOut(300, function(){ jQuery(this).remove(); });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_accept_cookies() {
        check_ajax_referer('wpec_gdpr', 'nonce');
        
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'all';
        
        // En el cliente el cookie se crea con document.cookie.
        // Esta acción solo loguea la aceptación.
        wp_send_json_success([
            'message' => __('Cookie aceptada', 'wp-extreme-customize'),
            'type' => $type,
        ]);
    }
    
    public function add_gdpr_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('GDPR / Cookies', 'wp-extreme-customize'),
            __('GDPR / Cookies', 'wp-extreme-customize'),
            'manage_options',
            'wpec-gdpr',
            [$this, 'render_gdpr_settings']
        );
    }
    
    public function render_gdpr_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('GDPR / Cookies', 'wp-extreme-customize'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_gdpr'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wpec_show_notice"><?php _e('Mostrar aviso', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpec_gdpr_settings[show_notice]" id="wpec_show_notice" value="yes" <?php checked($this->settings['show_notice'] ?? 'yes', 'yes'); ?>>
                            <p class="description"><?php _e('Mostrar aviso de cookies en el pie de página.', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_notice_text"><?php _e('Texto del aviso', 'wp-extreme-customize'); ?></label></th>
                        <td><textarea name="wpec_gdpr_settings[notice_text]" id="wpec_notice_text" rows="3" class="medium-text"><?php echo esc_textarea($this->settings['notice_text'] ?? ''); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_accept_text"><?php _e('Texto del botón de aceptación', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="text" name="wpec_gdpr_settings[accept_text]" id="wpec_accept_text" value="<?php echo esc_attr($this->settings['accept_text'] ?? 'Aceptar'); ?>" class="regular-text"></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_reject_text"><?php _e('Texto del botón de rechazo (opcional)', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="text" name="wpec_gdpr_settings[reject_text]" id="wpec_reject_text" value="<?php echo esc_attr($this->settings['reject_text'] ?? 'Rechazar'); ?>" class="regular-text"></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_policy_url"><?php _e('URL de política de cookies', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="url" name="wpec_gdpr_settings[policy_url]" id="wpec_policy_url" value="<?php echo esc_attr($this->settings['policy_url'] ?? ''); ?>" class="large-text"></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_cookie_expiry"><?php _e('Vigencia del consentimiento (días)', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="number" name="wpec_gdpr_settings[cookie_expiry]" id="wpec_cookie_expiry" value="<?php echo esc_attr(intval($this->settings['cookie_expiry'] ?? 365)); ?>" class="small-text" min="1" max="3650"></td>
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

new WPEC_GDPR();
