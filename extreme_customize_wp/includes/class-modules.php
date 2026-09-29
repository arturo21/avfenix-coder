
<?php
/**
 * Sistema de módulos para WP Extreme Customize
 * Permite activar/desactivar funcionalidades individualmente
 * 
 * Version: 1.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Modules {
    
    private $modules = [];
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_settings', []);
        $this->init_modules();
        add_action('admin_menu', [$this, 'add_modules_page']);
        add_action('wp_ajax_wpec_toggle_module', [$this, 'ajax_toggle_module']);
        add_action('wp_ajax_wpec_sync_all_sites', [$this, 'ajax_sync_all_sites']);
    }
    
    private function init_modules() {
        $this->modules = apply_filters('wpec_modules', [
            'login_customizer' => [
                'name' => __('Login Customizer', 'wp-extreme-customize'),
                'description' => __('Personaliza completamente la página de login', 'wp-extreme-customize'),
                'class' => 'WPEC_Login_Customizer',
                'file' => WPEC_MODULES_DIR . 'module-login.php',
                'active' => true,
                'category' => 'branding',
            ],
            'admin_customizer' => [
                'name' => __('Admin Customizer', 'wp-extreme-customize'),
                'description' => __('Personaliza el panel de administración', 'wp-extreme-customize'),
                'class' => 'WPEC_Admin_Customizer',
                'file' => WPEC_MODULES_DIR . 'module-admin.php',
                'active' => true,
                'category' => 'branding',
            ],
            'email_customizer' => [
                'name' => __('Email Customizer', 'wp-extreme-customize'),
                'description' => __('Personaliza todos los emails de WordPress', 'wp-extreme-customize'),
                'class' => 'WPEC_Email_Customizer',
                'file' => WPEC_MODULES_DIR . 'module-emails.php',
                'active' => true,
                'category' => 'communication',
            ],
            'security' => [
                'name' => __('Security Extreme', 'wp-extreme-customize'),
                'description' => __('Configuración de seguridad avanzada', 'wp-extreme-customize'),
                'class' => 'WPEC_Security',
                'file' => WPEC_MODULES_DIR . 'module-security.php',
                'active' => true,
                'category' => 'security',
            ],
            'gdpr' => [
                'name' => __('GDPR / Cookies', 'wp-extreme-customize'),
                'description' => __('Configuración de privacidad y cookies', 'wp-extreme-customize'),
                'class' => 'WPEC_GDPR',
                'file' => WPEC_MODULES_DIR . 'module-gdpr.php',
                'active' => true,
                'category' => 'compliance',
            ],
            'maintenance' => [
                'name' => __('Maintenance Mode', 'wp-extreme-customize'),
                'description' => __('Modo mantenimiento con branding', 'wp-extreme-customize'),
                'class' => 'WPEC_Maintenance_Mode',
                'file' => WPEC_MODULES_DIR . 'module-maintenance.php',
                'active' => true,
                'category' => 'utility',
            ],
            'manager' => [
                'name' => __('Plugin/Theme Manager', 'wp-extreme-customize'),
                'description' => __('Ocultar/renombrar plugins, temas y actualizaciones', 'wp-extreme-customize'),
                'class' => 'WPEC_Manager',
                'file' => WPEC_MODULES_DIR . 'module-manager.php',
                'active' => true,
                'category' => 'utility',
            ],
            'favicon' => [
                'name' => __('Favicon Manager', 'wp-extreme-customize'),
                'description' => __('Gestión completa de favicons', 'wp-extreme-customize'),
                'class' => 'WPEC_Favicon',
                'file' => WPEC_MODULES_DIR . 'module-favicon.php',
                'active' => true,
                'category' => 'branding',
            ],
            'import_export' => [
                'name' => __('Import/Export', 'wp-extreme-customize'),
                'description' => __('Importar y exportar configuraciones', 'wp-extreme-customize'),
                'class' => 'WPEC_Import_Export',
                'file' => WPEC_MODULES_DIR . 'module-import-export.php',
                'active' => true,
                'category' => 'utility',
            ],
            'multisite' => [
                'name' => __('Multisite Support', 'wp-extreme-customize'),
                'description' => __('Soporte completo para WordPress Multisite', 'wp-extreme-customize'),
                'class' => 'WPEC_Multisite',
                'file' => WPEC_MODULES_DIR . 'module-multisite.php',
                'active' => is_multisite(),
                'category' => 'multisite',
            ],
            'seo' => [
                'name' => __('SEO Extreme', 'wp-extreme-customize'),
                'description' => __('Optimización SEO avanzada', 'wp-extreme-customize'),
                'class' => 'WPEC_SEO',
                'file' => WPEC_MODULES_DIR . 'module-seo.php',
                'active' => false,
                'category' => 'marketing',
            ],
            'performance' => [
                'name' => __('Performance Extreme', 'wp-extreme-customize'),
                'description' => __('Optimización de rendimiento', 'wp-extreme-customize'),
                'class' => 'WPEC_Performance',
                'file' => WPEC_MODULES_DIR . 'module-performance.php',
                'active' => false,
                'category' => 'performance',
            ],
            'admin_color_schemes' => [
                'name' => __('Admin Color Schemes', 'wp-extreme-customize'),
                'description' => __('Esquemas de color personalizados para admin', 'wp-extreme-customize'),
                'class' => 'WPEC_Admin_Color_Schemes',
                'file' => WPEC_MODULES_DIR . 'module-admin-color-schemes.php',
                'active' => false,
                'category' => 'branding',
            ],
            'dashboard_widgets' => [
                'name' => __('Dashboard Widgets', 'wp-extreme-customize'),
                'description' => __('Widgets personalizados para el escritorio', 'wp-extreme-customize'),
                'class' => 'WPEC_Dashboard_Widgets',
                'file' => WPEC_MODULES_DIR . 'module-dashboard-widgets.php',
                'active' => false,
                'category' => 'admin',
            ],
            'admin_menu_items' => [
                'name' => __('Admin Menu Items', 'wp-extreme-customize'),
                'description' => __('Crear menús y submenús personalizados', 'wp-extreme-customize'),
                'class' => 'WPEC_Admin_Menu_Items',
                'file' => WPEC_MODULES_DIR . 'module-admin-menu-items.php',
                'active' => false,
                'category' => 'admin',
            ],
            'admin_bar_menus' => [
                'name' => __('Admin Bar Menus', 'wp-extreme-customize'),
                'description' => __('Menús personalizados en la barra de admin', 'wp-extreme-customize'),
                'class' => 'WPEC_Admin_Bar_Menus',
                'file' => WPEC_MODULES_DIR . 'module-admin-bar-menus.php',
                'active' => false,
                'category' => 'admin',
            ],
            'recaptcha' => [
                'name' => __('reCAPTCHA', 'wp-extreme-customize'),
                'description' => __('Google reCAPTCHA v2/v3 para formularios', 'wp-extreme-customize'),
                'class' => 'WPEC_Recaptcha',
                'file' => WPEC_MODULES_DIR . 'module-recaptcha.php',
                'active' => false,
                'category' => 'security',
            ],
            'activity_log' => [
                'name' => __('Activity Log', 'wp-extreme-customize'),
                'description' => __('Registro de actividad de usuarios', 'wp-extreme-customize'),
                'class' => 'WPEC_Activity_Log',
                'file' => WPEC_MODULES_DIR . 'module-activity-log.php',
                'active' => false,
                'category' => 'security',
            ],
            'file_monitor' => [
                'name' => __('File Monitor', 'wp-extreme-customize'),
                'description' => __('Monitor de cambios en archivos del sistema', 'wp-extreme-customize'),
                'class' => 'WPEC_File_Monitor',
                'file' => WPEC_MODULES_DIR . 'module-file-monitor.php',
                'active' => false,
                'category' => 'security',
            ],
            'login_url' => [
                'name' => __('Custom Login URL', 'wp-extreme-customize'),
                'description' => __('URL de login personalizada', 'wp-extreme-customize'),
                'class' => 'WPEC_Custom_Login_URL',
                'file' => WPEC_MODULES_DIR . 'module-login-url.php',
                'active' => false,
                'category' => 'security',
            ],
        ]);
        
        // Cargar módulos activos
        foreach ($this->modules as $key => $module) {
            $is_active = $module['active'];
            
            // Verificar si el usuario lo ha activado/desactivado en settings
            $user_modules = $this->settings['modules'] ?? [];
            if (isset($user_modules[$key])) {
                $is_active = (bool) $user_modules[$key];
            }
            
            if ($is_active && file_exists($module['file'])) {
                require_once $module['file'];
                if (class_exists($module['class'])) {
                    try {
                        new $module['class']();
                    } catch (Exception $e) {
                        error_log('WPEC Module Error (' . $key . '): ' . $e->getMessage());
                    }
                }
            }
        }
    }
    
    public function add_modules_page() {
        add_menu_page(
            __('WP Extreme Customize', 'wp-extreme-customize'),
            __('Extreme Customize', 'wp-extreme-customize'),
            'manage_options',
            'wp-extreme-customize',
            [$this, 'modules_page_content'],
            'dashicons-admin-customizer',
            6
        );
    }
    
    public function modules_page_content() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <div class="wpec-header">
                <div class="wpec-logo">
                    <span class="dashicons dashicons-admin-customizer"></span>
                    <h1>WP Extreme Customize</h1>
                </div>
                <div class="wpec-version">v<?php echo WPEC_VERSION; ?></div>
            </div>
            
            <p><?php _e('Activa/desactiva las funcionalidades del plugin', 'wp-extreme-customize'); ?></p>
            
            <div class="wpec-filters" style="margin-bottom: 20px;">
                <label for="wpec-category-filter"><?php _e('Filtrar por categoría:', 'wp-extreme-customize'); ?></label>
                <select id="wpec-category-filter" style="margin-left: 10px;">
                    <option value="all"><?php _e('Todas', 'wp-extreme-customize'); ?></option>
                    <option value="branding"><?php _e('Branding', 'wp-extreme-customize'); ?></option>
                    <option value="security"><?php _e('Seguridad', 'wp-extreme-customize'); ?></option>
                    <option value="admin"><?php _e('Administración', 'wp-extreme-customize'); ?></option>
                    <option value="communication"><?php _e('Comunicación', 'wp-extreme-customize'); ?></option>
                    <option value="compliance"><?php _e('Cumplimiento', 'wp-extreme-customize'); ?></option>
                    <option value="utility"><?php _e('Utilidades', 'wp-extreme-customize'); ?></option>
                    <option value="multisite"><?php _e('Multisite', 'wp-extreme-customize'); ?></option>
                    <option value="marketing"><?php _e('Marketing/SEO', 'wp-extreme-customize'); ?></option>
                    <option value="performance"><?php _e('Rendimiento', 'wp-extreme-customize'); ?></option>
                </select>
            </div>
            
            <div class="wpec-modules-grid">
                <?php foreach ($this->modules as $key => $module): 
                    $is_active = $this->settings['modules'][$key] ?? $module['active'];
                ?>
                    <div class="wpec-module-card <?php echo $is_active ? 'active' : 'inactive'; ?>" data-category="<?php echo esc_attr($module['category']); ?>">
                        <div class="wpec-module-header">
                            <h3><?php echo esc_html($module['name']); ?></h3>
                            <label class="wpec-switch">
                                <input type="checkbox" <?php checked($is_active); ?> data-module="<?php echo esc_attr($key); ?>">
                                <span class="wpec-slider"></span>
                            </label>
                        </div>
                        <div class="wpec-module-body">
                            <p><?php echo esc_html($module['description']); ?></p>
                            <div class="wpec-module-meta">
                                <span class="wpec-category"><?php echo esc_html(ucfirst($module['category'])); ?></span>
                            </div>
                            <div class="wpec-module-actions">
                                <a href="#" class="wpec-configure" data-module="<?php echo esc_attr($key); ?>">
                                    <?php _e('Configurar', 'wp-extreme-customize'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .wpec-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }
        .wpec-logo { display: flex; align-items: center; gap: 15px; }
        .wpec-logo .dashicons { font-size: 40px; }
        .wpec-logo h1 { margin: 0; font-size: 28px; }
        .wpec-filters select { padding: 5px 10px; }
        .wpec-modules-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .wpec-module-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; transition: all 0.3s ease; }
        .wpec-module-card.active { border-left: 4px solid #2271b1; }
        .wpec-module-card.inactive { opacity: 0.7; }
        .wpec-module-header { padding: 20px; background: #f8f9fa; display: flex; justify-content: space-between; align-items: center; }
        .wpec-module-header h3 { margin: 0; font-size: 18px; }
        .wpec-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .wpec-switch input { opacity: 0; width: 0; height: 0; }
        .wpec-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .wpec-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .wpec-slider { background-color: #2271b1; }
        input:checked + .wpec-slider:before { transform: translateX(26px); }
        .wpec-module-body { padding: 20px; }
        .wpec-module-meta { margin-bottom: 10px; }
        .wpec-category { background: #e0e0e0; padding: 2px 8px; border-radius: 12px; font-size: 11px; text-transform: uppercase; }
        .wpec-module-actions { margin-top: 15px; }
        .wpec-configure { color: #2271b1; text-decoration: none; font-weight: 600; }
        .wpec-configure:hover { text-decoration: underline; }
        .wpec-stat-card { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center; }
        .wpec-filters { background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .wpec-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .wpec-export-options label,
        .wpec-import-options label { display: block; margin: 5px 0; }
        #wpec-custom-links tbody tr,
        #wpec-custom-help tbody tr,
        #wpec-preload-resources tbody tr,
        #wpec-dns-prefetch tbody tr { border-bottom: 1px solid #f0f0f0; }
        .wpec-scheme-item,
        .wpec-widget-item,
        .wpec-menu-item,
        .wpec-bar-item { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9; }
        .wpec-scheme-item h3,
        .wpec-widget-item h3,
        .wpec-menu-item h3,
        .wpec-bar-item h3 { margin-top: 0; }
        .wpec-remove-link,
        .wpec-remove-help,
        .wpec-remove-preload,
        .wpec-remove-dns,
        .wpec-remove-scheme,
        .wpec-remove-widget,
        .wpec-remove-menu,
        .wpec-remove-main,
        .wpec-remove-sub,
        .wpec-remove-group { margin-top: 10px; }
        @media (max-width: 768px) {
            .wpec-container { grid-template-columns: 1fr; }
            .wpec-features-grid { grid-template-columns: 1fr; }
            .wpec-tab-nav { flex-wrap: wrap; }
            .wpec-tab-link { flex: 1; text-align: center; padding: 10px; }
        }
        /* Color pickers */
        .color-picker { width: 80px !important; height: 30px; }
        /* Spinner */
        .spinner { visibility: hidden; float: right; margin-top: 4px; }
        .spinner.is-active { visibility: visible; }
        /* Notices */
        .notice { margin: 15px 0; padding: 12px 20px; border-left: 4px solid #0073aa; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .notice-success { border-left-color: #28a745; }
        .notice-error { border-left-color: #dc3232; }
        .notice-warning { border-left-color: #ffc107; }
        .notice-info { border-left-color: #17a2b8; }
        .notice-inline { display: inline-block; margin: 10px 0; padding: 8px 15px; border-radius: 4px; }
        /* Tables */
        .widefat.fixed.striped th { font-weight: 600; }
        .widefat.fixed.striped td { vertical-align: middle; }
        /* Code areas */
        textarea.code, input.code { font-family: Monaco, Menlo, Consolas, "Courier New", monospace !important; font-size: 13px; line-height: 1.5; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Filtro de categorías
            $('#wpec-category-filter').on('change', function() {
                var category = $(this).val();
                $('.wpec-module-card').each(function() {
                    if (category === 'all' || $(this).data('category') === category) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
            // Toggle módulos
            $('.wpec-switch input').on('change', function() {
                var module = $(this).data('module');
                var enabled = $(this).is(':checked');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wpec_toggle_module',
                        module: module,
                        enabled: enabled,
                        nonce: '<?php echo wp_create_nonce("wpec_toggle_module"); ?>'
                    },
                    success: function(response) {
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function ajax_toggle_module() {
        check_ajax_referer('wpec_toggle_module', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'wp-extreme-customize')]);
        }
        
        $module = sanitize_text_field($_POST['module'] ?? '');
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        
        if (empty($module)) {
            wp_send_json_error(['message' => __('Módulo no especificado', 'wp-extreme-customize')]);
        }
        
        $settings = get_option('wpec_settings', []);
        if (!isset($settings['modules'])) {
            $settings['modules'] = [];
        }
        
        $settings['modules'][$module] = $enabled;
        update_option('wpec_settings', $settings);
        
        wp_send_json_success(['message' => __('Módulo actualizado', 'wp-extreme-customize')]);
    }
    
    public function ajax_sync_all_sites() {
        check_ajax_referer('wpec_sync_all', 'nonce');
        
        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'wp-extreme-customize')]);
        }
        
        if (!is_multisite()) {
            wp_send_json_error(['message' => 'No es una instalación multisite']);
        }
        
        $sites = get_sites(['number' => 0]);
        $main_site = get_main_site_id();
        $main_settings = $this->get_main_site_settings();
        
        $synced = 0;
        
        foreach ($sites as $site) {
            if ($site->blog_id === $main_site) {
                continue;
            }
            
            switch_to_blog($site->blog_id);
            
            foreach ($main_settings as $option => $value) {
                update_option($option, $value);
            }
            
            restore_current_blog();
            $synced++;
        }
        
        wp_send_json_success(['synced' => $synced, 'message' => sprintf(__('%d sitios sincronizados', 'wp-extreme-customize'), $synced)]);
    }
    
    private function get_main_site_settings() {
        $main_site = get_main_site_id();
        $settings = [];
        
        switch_to_blog($main_site);
        
        $option_names = [
            'wpec_login_settings',
            'wpec_email_settings',
            'wpec_security_settings',
            'wpec_gdpr_settings',
            'wpec_admin_settings',
            'wpec_maintenance_settings',
            'wpec_manager_settings',
            'wpec_favicon_settings',
            'wpec_seo_settings',
            'wpec_performance_settings',
            'wpec_admin_color_schemes',
            'wpec_dashboard_widgets',
            'wpec_admin_menu_items',
            'wpec_admin_bar_menus',
            'wpec_recaptcha_settings',
            'wpec_activity_log_settings',
            'wpec_file_monitor_settings',
            'wpec_login_url_settings',
        ];
        
        foreach ($option_names as $option) {
            $settings[$option] = get_option($option, []);
        }
        
        restore_current_blog();
        
        return $settings;
    }
}

new WPEC_Modules();
