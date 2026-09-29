
<?php
/**
 * Sistema de módulos para WP Extreme Customize
 * Permite activar/desactivar funcionalidades individualmente
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
    }
    
    private function init_modules() {
        $this->modules = apply_filters('wpec_modules', [
            'login_customizer' => [
                'name' => __('Login Customizer', 'wp-extreme-customize'),
                'description' => __('Personaliza completamente la página de login', 'wp-extreme-customize'),
                'class' => 'WPEC_Login_Customizer',
                'file' => WPEC_MODULES_DIR . 'module-login.php',
                'active' => true
            ],
            'admin_customizer' => [
                'name' => __('Admin Customizer', 'wp-extreme-customize'),
                'description' => __('Personaliza el panel de administración', 'wp-extreme-customize'),
                'class' => 'WPEC_Admin_Customizer',
                'file' => WPEC_MODULES_DIR . 'module-admin.php',
                'active' => true
            ],
            'email_customizer' => [
                'name' => __('Email Customizer', 'wp-extreme-customize'),
                'description' => __('Personaliza todos los emails de WordPress', 'wp-extreme-customize'),
                'class' => 'WPEC_Email_Customizer',
                'file' => WPEC_MODULES_DIR . 'module-emails.php',
                'active' => true
            ],
            'security' => [
                'name' => __('Security Extreme', 'wp-extreme-customize'),
                'description' => __('Configuración de seguridad avanzada', 'wp-extreme-customize'),
                'class' => 'WPEC_Security',
                'file' => WPEC_MODULES_DIR . 'module-security.php',
                'active' => true
            ],
            'seo' => [
                'name' => __('SEO Extreme', 'wp-extreme-customize'),
                'description' => __('Optimización SEO avanzada', 'wp-extreme-customize'),
                'class' => 'WPEC_SEO',
                'file' => WPEC_MODULES_DIR . 'module-seo.php',
                'active' => false
            ],
            'performance' => [
                'name' => __('Performance Extreme', 'wp-extreme-customize'),
                'description' => __('Optimización de rendimiento', 'wp-extreme-customize'),
                'class' => 'WPEC_Performance',
                'file' => WPEC_MODULES_DIR . 'module-performance.php',
                'active' => false
            ]
        ]);
        
        // Cargar módulos activos
        foreach ($this->modules as $key => $module) {
            if ($module['active'] && file_exists($module['file'])) {
                require_once $module['file'];
                if (class_exists($module['class'])) {
                    new $module['class']();
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
        ?>
        <div class="wrap">
            <h1><?php _e('WP Extreme Customize - Módulos', 'wp-extreme-customize'); ?></h1>
            <p><?php _e('Activa/desactiva las funcionalidades del plugin', 'wp-extreme-customize'); ?></p>
            
            <div class="wpec-modules-grid">
                <?php foreach ($this->modules as $key => $module): ?>
                    <div class="wpec-module-card <?php echo $module['active'] ? 'active' : 'inactive'; ?>">
                        <div class="wpec-module-header">
                            <h3><?php echo esc_html($module['name']); ?></h3>
                            <label class="wpec-switch">
                                <input type="checkbox" <?php checked($module['active']); ?> data-module="<?php echo esc_attr($key); ?>">
                                <span class="wpec-slider"></span>
                            </label>
                        </div>
                        <div class="wpec-module-body">
                            <p><?php echo esc_html($module['description']); ?></p>
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
        .wpec-modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .wpec-module-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .wpec-module-card.active {
            border-left: 4px solid #2271b1;
        }
        .wpec-module-card.inactive {
            opacity: 0.6;
        }
        .wpec-module-header {
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .wpec-module-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .wpec-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .wpec-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .wpec-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        .wpec-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .wpec-slider {
            background-color: #2271b1;
        }
        input:checked + .wpec-slider:before {
            transform: translateX(26px);
        }
        .wpec-module-body {
            padding: 20px;
        }
        .wpec-module-actions {
            margin-top: 15px;
        }
        .wpec-configure {
            color: #2271b1;
            text-decoration: none;
            font-weight: 600;
        }
        .wpec-configure:hover {
            text-decoration: underline;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
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
            wp_die(__('Permisos insuficientes', 'wp-extreme-customize'));
        }
        
        $module = sanitize_text_field($_POST['module']);
        $enabled = (bool) $_POST['enabled'];
        
        $settings = get_option('wpec_settings', []);
        if (!isset($settings['modules'])) {
            $settings['modules'] = [];
        }
        
        $settings['modules'][$module] = $enabled;
        update_option('wpec_settings', $settings);
        
        wp_send_json_success();
    }
}

new WPEC_Modules();
