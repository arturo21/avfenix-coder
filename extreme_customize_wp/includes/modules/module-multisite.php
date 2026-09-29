
<?php
/**
 * Módulo Multisite Support - Soporte completo para WordPress Multisite
 * Version: 1.0.2 - Corrección de get_blog_option -> switch_to_blog
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Multisite {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_multisite_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        if (!is_multisite()) {
            return;
        }
        
        // Sincronizar configuración en sitios nuevos
        add_action('wp_initialize_site', [$this, 'sync_new_site'], 10, 1);
        
        // Admin
        add_action('network_admin_menu', [$this, 'add_multisite_network_menu']);
        add_action('admin_menu', [$this, 'add_multisite_menu']);
        
        // Aplicar configuración global
        add_action('init', [$this, 'apply_global_settings']);
    }
    
    public function apply_global_settings() {
        if (!is_multisite()) {
            return;
        }
        
        $global_branding = $this->get_setting('global_branding', false);
        $global_login = $this->get_setting('global_login', false);
        $global_emails = $this->get_setting('global_emails', false);
        $global_security = $this->get_setting('global_security', false);
        $global_gdpr = $this->get_setting('global_gdpr', false);
        $global_seo = $this->get_setting('global_seo', false);
        
        // Si es sitio principal, no hacer nada (se aplica directamente)
        if (is_main_site()) {
            return;
        }
        
        // Sincronizar branding
        if ($global_branding) {
            add_filter('option_wpec_login_settings', [$this, 'sync_global_login_settings'], 10, 2);
        }
        
        // Sincronizar emails
        if ($global_emails) {
            add_filter('option_wpec_email_settings', [$this, 'sync_global_email_settings'], 10, 2);
        }
        
        // Sincronizar seguridad
        if ($global_security) {
            add_filter('option_wpec_security_settings', [$this, 'sync_global_security_settings'], 10, 2);
        }
        
        // Sincronizar GDPR
        if ($global_gdpr) {
            add_filter('option_wpec_gdpr_settings', [$this, 'sync_global_gdpr_settings'], 10, 2);
        }
        
        // Sincronizar SEO
        if ($global_seo) {
            add_filter('option_wpec_seo_settings', [$this, 'sync_global_seo_settings'], 10, 2);
        }
    }
    
    public function sync_new_site($site) {
        if (!$this->get_setting('apply_to_new_sites', true)) {
            return;
        }
        
        switch_to_blog($site->blog_id);
        
        // Copiar configuración del sitio principal
        $main_site = get_main_site_id();
        $settings_to_sync = [
            'wpec_login_settings',
            'wpec_email_settings',
            'wpec_security_settings',
            'wpec_gdpr_settings',
            'wpec_maintenance_settings',
            'wpec_manager_settings',
            'wpec_admin_settings',
        ];
        
        foreach ($settings_to_sync as $option) {
            $main_value = get_blog_option($main_site, $option, false);
            if ($main_value !== false) {
                update_option($option, $main_value);
            }
        }
        
        restore_current_blog();
    }
    
    // Helpers para obtener opciones del sitio principal
    private function get_main_option($option, $default = false) {
        if (!is_multisite()) {
            return get_option($option, $default);
        }
        
        $main_site = get_main_site_id();
        $value = get_blog_option($main_site, $option, false);
        
        return $value !== false ? $value : $default;
    }
    
    public function sync_global_login_settings($value, $option) {
        $main_value = $this->get_main_option('wpec_login_settings');
        return ($main_value !== false) ? $main_value : $value;
    }
    
    public function sync_global_email_settings($value, $option) {
        $main_value = $this->get_main_option('wpec_email_settings');
        return ($main_value !== false) ? $main_value : $value;
    }
    
    public function sync_global_security_settings($value, $option) {
        $main_value = $this->get_main_option('wpec_security_settings');
        return ($main_value !== false) ? $main_value : $value;
    }
    
    public function sync_global_gdpr_settings($value, $option) {
        $main_value = $this->get_main_option('wpec_gdpr_settings');
        return ($main_value !== false) ? $main_value : $value;
    }
    
    public function sync_global_seo_settings($value, $option) {
        $main_value = $this->get_main_option('wpec_seo_settings');
        return ($main_value !== false) ? $main_value : $value;
    }
    
    public function add_multisite_network_menu() {
        add_menu_page(
            __('WP Extreme Customize - Multisite', 'wp-extreme-customize'),
            __('Extreme Customize', 'wp-extreme-customize'),
            'manage_network_options',
            'wpec-multisite',
            [$this, 'render_multisite_network_page'],
            'dashicons-admin-multisite',
            6
        );
    }
    
    public function add_multisite_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Multisite Settings', 'wp-extreme-customize'),
            __('Multisite', 'wp-extreme-customize'),
            'manage_options',
            'wpec-multisite',
            [$this, 'render_multisite_settings']
        );
    }
    
    public function render_multisite_network_page() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        
        if (!is_multisite()) {
            wp_die(__('Esta función requiere WordPress Multisite.', 'wp-extreme-customize'));
        }
        
        $sites = get_sites(['number' => 20]);
        ?>
        <div class="wrap">
            <h1><?php _e('Multisite - Panel de Red', 'wp-extreme-customize'); ?></h1>
            
            <div class="wpec-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:15px; margin:20px 0;">
                <div class="wpec-stat-card" style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:8px; text-align:center;">
                    <div style="font-size:24px; font-weight:bold;"><?php echo count($sites); ?></div>
                    <div><?php _e('Sitios totales', 'wp-extreme-customize'); ?></div>
                </div>
                <div class="wpec-stat-card" style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:8px; text-align:center;">
                    <div style="font-size:24px; font-weight:bold;"><?php echo get_main_site_id(); ?></div>
                    <div><?php _e('Sitio principal ID', 'wp-extreme-customize'); ?></div>
                </div>
            </div>
            
            <h2><?php _e('Sitios de la red', 'wp-extreme-customize'); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Nombre', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('URL', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Usuario', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Estado', 'wp-extreme-customize'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): 
                        $details = get_blog_details($site->blog_id);
                    ?>
                    <tr>
                        <td><?php echo intval($site->blog_id); ?></td>
                        <td><?php echo esc_html($details->blogname); ?></td>
                        <td><a href="<?php echo esc_url(get_home_url($site->blog_id)); ?>" target="_blank"><?php echo esc_url(get_home_url($site->blog_id)); ?></a></td>
                        <td>
                            <?php 
                            $user_ids = get_users(['blog_id' => $site->blog_id, 'fields' => ['display_name', 'user_login'], 'number' => 3]);
                            foreach ($user_ids as $u): ?>
                                <div><?php echo esc_html($u->display_name ?: $u->user_login); ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td><?php echo $site->spam ? '<span style="color:red;">Spam</span>' : ($site->deleted ? '<span style="color:gray;">Eliminado</span>' : '<span style="color:green;">Activo</span>'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function render_multisite_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Multisite Settings', 'wp-extreme-customize'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_multisite'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php _e('Sincronizar Branding', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="checkbox" name="wpec_multisite_settings[global_branding]" value="yes" <?php checked($this->settings['global_branding'] ?? '', 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Sincronizar Login', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="checkbox" name="wpec_multisite_settings[global_login]" value="yes" <?php checked($this->settings['global_login'] ?? '', 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Sincronizar Emails', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="checkbox" name="wpec_multisite_settings[global_emails]" value="yes" <?php checked($this->settings['global_emails'] ?? '', 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Sincronizar Seguridad', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="checkbox" name="wpec_multisite_settings[global_security]" value="yes" <?php checked($this->settings['global_security'] ?? '', 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Sincronizar GDPR', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="checkbox" name="wpec_multisite_settings[global_gdpr]" value="yes" <?php checked($this->settings['global_gdpr'] ?? '', 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Sincronizar SEO', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="checkbox" name="wpec_multisite_settings[global_seo]" value="yes" <?php checked($this->settings['global_seo'] ?? '', 'yes'); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Aplicar a nuevos sitios', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="checkbox" name="wpec_multisite_settings[apply_to_new_sites]" value="yes" <?php checked($this->settings['apply_to_new_sites'] ?? '', 'yes'); ?>></td>
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

new WPEC_Multisite();
