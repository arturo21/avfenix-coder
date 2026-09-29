
<?php
/**
 * Módulo Maintenance Mode - Modo mantenimiento con branding
 * Version: 1.0.2 - Corrección de headers y redirección
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Maintenance_Mode {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_maintenance_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Mostrar página de mantenimiento en frontend
        add_action('template_redirect', [$this, 'show_maintenance_page']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_maintenance_menu']);
        
        // AJAX para toggle rápido
        add_action('wp_ajax_wpec_toggle_maintenance', [$this, 'ajax_toggle_maintenance']);
    }
    
    public function show_maintenance_page() {
        if (!get_option('maintenance_mode')) {
            // Usar la opción propia del plugin como respaldo
            $active = $this->get_setting('active', 'no');
            if ($active !== 'yes') {
                return;
            }
        } else {
            $active = 'yes';
        }
        
        if ($active !== 'yes') {
            return;
        }
        
        // No mostrar si es admin
        if (is_admin()) {
            return;
        }
        
        // Permitir acceso a administradores
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Permitir roles autorizados
        $allowed_roles = $this->get_setting('allowed_roles', ['administrator']);
        $user = wp_get_current_user();
        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles, true)) {
                return;
            }
        }
        
        // Permitir IPs autorizadas
        $allowed_ips = $this->get_setting('allowed_ips', []);
        $ip = $this->get_client_ip();
        if (in_array($ip, $allowed_ips, true)) {
            return;
        }
        
        // Enviar headers 503
        status_header(503);
        
        // Buffer para asegurar que los headers se envíen antes del HTML
        if (!headers_sent()) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Retry-After: ' . intval($this->get_setting('retry_after', 3600)));
        }
        
        $this->render_maintenance_page();
        exit;
    }
    
    private function render_maintenance_page() {
        $title = $this->get_setting('title', __('Sitio en mantenimiento', 'wp-extreme-customize'));
        $message = $this->get_setting('message', __('Estamos realizando mejoras. Volvemos pronto.', 'wp-extreme-customize'));
        $bg_color = $this->get_setting('bg_color', '#667eea');
        $brand_color = $this->get_setting('brand_color', '#764ba2');
        $logo = $this->get_setting('logo', '');
        $countdown_enabled = $this->get_setting('countdown', '0');
        $countdown_date = $this->get_setting('countdown_date', '');
        $custom_css = $this->get_setting('custom_css', '');
        $site_name = get_bloginfo('name');
        
        $countdown_html = '';
        if ($countdown_enabled === '1' && $countdown_date) {
            $countdown_html = '<div id="wpec-countdown" style="margin-top: 30px; text-align: center; color: #fff; font-size: 18px;">' .
                sprintf(__('Cuenta atrás: %s', 'wp-extreme-customize'), esc_html($countdown_date)) .
                '</div>';
        }
        
        $logo_html = '';
        if ($logo) {
            $logo_html = '<img src="' . esc_url($logo) . '" alt="' . esc_attr($site_name) . '" style="max-width: 200px; max-height: 80px; margin-bottom: 20px;">';
        }
        
        $home_url = home_url('/');
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?> - <?php echo esc_html($site_name); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                html, body { height: 100%; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, <?php echo esc_attr($bg_color); ?> 0%, <?php echo esc_attr($brand_color); ?> 100%);
                    color: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                .wpec-maintenance-content {
                    text-align: center;
                    max-width: 600px;
                    padding: 40px;
                }
                .wpec-maintenance-content h1 {
                    font-size: 42px;
                    margin-bottom: 20px;
                }
                .wpec-maintenance-content p {
                    font-size: 18px;
                    margin-bottom: 20px;
                    opacity: 0.9;
                }
                .wpec-maintenance-content a {
                    color: #fff;
                    text-decoration: underline;
                }
                .wpec-site-footer {
                    margin-top: 40px;
                    opacity: 0.7;
                    font-size: 14px;
                }
                <?php echo wp_kses_post($custom_css); ?>
            </style>
            <?php do_action('wp_head'); ?>
        </head>
        <body>
            <div class="wpec-maintenance-content">
                <?php echo $logo_html; ?>
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo wp_kses_post($message); ?></p>
                <?php echo $countdown_html; ?>
                <p><a href="<?php echo esc_url($home_url); ?>"><?php _e('Volver al inicio', 'wp-extreme-customize'); ?></a></p>
                <div class="wpec-site-footer">
                    <?php echo esc_html($site_name); ?> &middot; <?php _e('Sitio en mantenimiento', 'wp-extreme-customize'); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    public function add_maintenance_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Maintenance Mode', 'wp-extreme-customize'),
            __('Mantenimiento', 'wp-extreme-customize'),
            'manage_options',
            'wpec-maintenance',
            [$this, 'render_maintenance_settings']
        );
        
        // Barra rápida
        add_action('wp_before_admin_bar_render', [$this, 'add_admin_bar_toggle']);
    }
    
    public function add_admin_bar_toggle($wp_admin_bar) {
        $active = ($this->get_setting('active', 'no') === 'yes') || (bool) get_option('maintenance_mode');
        
        $wp_admin_bar->add_node([
            'id' => 'wpec-maintenance-toggle',
            'title' => $active ? '<span style="color:#ffb90a;">&#9888; ' . __('MANTENIMIENTO ACTIVO', 'wp-extreme-customize') . '</span>' : __('Mantenimiento: OFF', 'wp-extreme-customize'),
            'href' => admin_url('admin.php?page=wpec-maintenance'),
            'meta' => ['class' => 'wpec-maint-btn'],
        ]);
    }
    
    public function ajax_toggle_maintenance() {
        check_ajax_referer('wpec_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'wp-extreme-customize')]);
        }
        
        $settings = get_option('wpec_maintenance_settings', []);
        $settings['active'] = $settings['active'] === 'yes' ? 'no' : 'yes';
        update_option('wpec_maintenance_settings', $settings);
        
        wp_send_json_success(['active' => $settings['active'] === 'yes']);
    }
    
    public function render_maintenance_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Maintenance Mode', 'wp-extreme-customize'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Cuando el modo mantenimiento está activo, solo los usuarios autorizados pueden ver el sitio.', 'wp-extreme-customize'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_maintenance'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wpec_maint_active"><?php _e('Activar modo mantenimiento', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpec_maintenance_settings[active]" id="wpec_maint_active" value="yes" <?php checked($this->settings['active'] ?? '', 'yes'); ?>>
                            <p class="description"><?php _e('Recomendado: también activa la opción nativa de WordPress "Maintenance Mode" (Ajustes → General).', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_title"><?php _e('Título', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="text" name="wpec_maintenance_settings[title]" id="wpec_maint_title" value="<?php echo esc_attr($this->settings['title'] ?? ''); ?>" class="large-text"></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_message"><?php _e('Mensaje', 'wp-extreme-customize'); ?></label></th>
                        <td><textarea name="wpec_maintenance_settings[message]" id="wpec_maint_message" rows="4" class="large-text"><?php echo esc_textarea($this->settings['message'] ?? ''); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_logo"><?php _e('URL del logo', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="url" name="wpec_maintenance_settings[logo]" id="wpec_maint_logo" value="<?php echo esc_attr($this->settings['logo'] ?? ''); ?>" class="large-text">
                            <?php if (!empty($this->settings['logo'])): ?>
                                <br><img src="<?php echo esc_url($this->settings['logo']); ?>" style="max-width:200px; margin-top:5px;" alt="">
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_bg"><?php _e('Color de fondo', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_maintenance_settings[bg_color]" id="wpec_maint_bg" value="<?php echo esc_attr($this->settings['bg_color'] ?? '#667eea'); ?>" class="wpec-color-field">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_brand"><?php _e('Color de marca', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_maintenance_settings[brand_color]" id="wpec_maint_brand" value="<?php echo esc_attr($this->settings['brand_color'] ?? '#764ba2'); ?>" class="wpec-color-field">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_countdown"><?php _e('Mostrar cuenta atrás', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpec_maintenance_settings[countdown]" value="1" <?php checked($this->settings['countdown'] ?? '', '1'); ?>>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_date"><?php _e('Fecha objetivo', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="date" name="wpec_maintenance_settings[countdown_date]" id="wpec_maint_date" value="<?php echo esc_attr($this->settings['countdown_date'] ?? ''); ?>"></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_roles"><?php _e('Roles con acceso (separados por comas)', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_maintenance_settings[allowed_roles]" id="wpec_maint_roles" value="<?php echo esc_attr(implode(', ', (array) ($this->settings['allowed_roles'] ?? []))); ?>" class="large-text">
                            <p class="description"><?php _e('Ejemplo: administrator, editor', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_ips"><?php _e('IPs autorizadas (separadas por comas)', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="text" name="wpec_maintenance_settings[allowed_ips]" id="wpec_maint_ips" value="<?php echo esc_attr(implode(', ', (array) ($this->settings['allowed_ips'] ?? []))); ?>" class="large-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_retry"><?php _e('Retry-After (segundos)', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="number" name="wpec_maintenance_settings[retry_after]" id="wpec_maint_retry" value="<?php echo esc_attr(intval($this->settings['retry_after'] ?? 3600)); ?>" class="small-text"></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="wpec_maint_css"><?php _e('CSS personalizado', 'wp-extreme-customize'); ?></label></th>
                        <td><textarea name="wpec_maintenance_settings[custom_css]" id="wpec_maint_css" rows="6" class="large-text code"><?php echo esc_textarea($this->settings['custom_css'] ?? ''); ?></textarea></td>
                    </tr>
                </table>
                
                <?php
                // Sanitizar arrays (roles e IPs) antes de guardar
                add_action('admin_init', function() {
                    if (isset($_POST['wpec_maintenance_settings'])) {
                        $data = $_POST['wpec_maintenance_settings'];
                        if (isset($data['allowed_roles'])) {
                            $data['allowed_roles'] = array_filter(array_map('sanitize_text_field', explode(',', $data['allowed_roles'])));
                        }
                        if (isset($data['allowed_ips'])) {
                            $data['allowed_ips'] = array_filter(array_map('sanitize_text_field', explode(',', $data['allowed_ips'])));
                        }
                        $_POST['wpec_maintenance_settings'] = $data;
                    }
                });
                submit_button();
                ?>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                $('.wpec-color-field').each(function() {
                    $(this).wpColorPicker();
                });
            });
            </script>
        </div>
        <?php
    }
    
    private function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Maintenance_Mode();
