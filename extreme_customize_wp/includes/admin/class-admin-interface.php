
<?php
/**
 * Interfaz de administración principal
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Admin_Interface {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_before_admin_bar_render', [$this, 'customize_admin_bar']);
    }
    
    public function add_admin_pages() {
        // Página principal - Dashboard
        add_menu_page(
            __('WP Extreme Customize', 'wp-extreme-customize'),
            __('Extreme Customize', 'wp-extreme-customize'),
            'manage_options',
            'wp-extreme-customize',
            [$this, 'render_main_page'],
            'dashicons-admin-customizer',
            6
        );
        
        // Configuración General
        add_submenu_page(
            'wp-extreme-customize',
            __('Configuración General', 'wp-extreme-customize'),
            __('Configuración', 'wp-extreme-customize'),
            'manage_options',
            'wpec-general',
            [$this, 'render_general_settings']
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wp-extreme-customize') !== false || 
            strpos($hook, 'wpec-') !== false) {
            wp_enqueue_style('wpec-admin-style', WPEC_PLUGIN_URL . 'assets/css/admin.css');
            wp_enqueue_script('wpec-admin-script', WPEC_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WPEC_VERSION, true);
            
            wp_localize_script('wpec-admin-script', 'wpec_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpec_admin_nonce'),
                'strings' => [
                    'saving' => __('Guardando...', 'wp-extreme-customize'),
                    'saved' => __('Guardado', 'wp-extreme-customize'),
                    'error' => __('Error', 'wp-extreme-customize')
                ]
            ]);
        }
    }
    
    public function render_main_page() {
        $settings = get_option('wpec_settings', []);
        $modules = $settings['modules'] ?? [];
        $active_count = array_filter($modules);
        ?>
        <div class="wrap wpec-main-page">
            <div class="wpec-header">
                <div class="wpec-logo">
                    <span class="dashicons dashicons-admin-customizer"></span>
                    <h1>WP Extreme Customize</h1>
                </div>
                <div class="wpec-version">v<?php echo WPEC_VERSION; ?></div>
            </div>
            
            <div class="wpec-container">
                <div class="wpec-main-content">
                    <div class="wpec-card">
                        <h2><?php _e('Bienvenido a WP Extreme Customize', 'wp-extreme-customize'); ?></h2>
                        <p><?php _e('El plugin más completo para personalizar tu WordPress. Transforma tu sitio según tu marca AVFDigital.', 'wp-extreme-customize'); ?></p>
                        
                        <div class="wpec-features-grid">
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-admin-appearance"></span>
                                <h3><?php _e('Login Customizer', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Cambia logo, colores, fondo y más', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-admin-site"></span>
                                <h3><?php _e('Admin Customizer', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Personaliza el panel de WordPress', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-email-alt"></span>
                                <h3><?php _e('Email Customizer', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Diseña todos los emails del sistema', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-shield-alt"></span>
                                <h3><?php _e('Seguridad Extrema', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Protege tu sitio con configuración avanzada', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-lock"></span>
                                <h3><?php _e('GDPR / Cookies', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Cumple con la normativa de privacidad', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-admin-site-alt3"></span>
                                <h3><?php _e('Maintenance Mode', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Modo mantenimiento con tu branding', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-admin-plugins"></span>
                                <h3><?php _e('Plugin/Theme Manager', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Oculta/renombra plugins y temas', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-images-alt2"></span>
                                <h3><?php _e('Favicon Manager', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Gestión completa de favicons', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-download"></span>
                                <h3><?php _e('Import/Export', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Copia de seguridad de configuraciones', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-admin-network"></span>
                                <h3><?php _e('Multisite Support', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Gestión centralizada de la red', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <h3><?php _e('SEO Extreme', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Meta tags, Open Graph, Schema.org', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-speed"></span>
                                <h3><?php _e('Performance Extreme', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Cache, minificación, lazy load', 'wp-extreme-customize'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="wpec-sidebar">
                    <div class="wpec-card">
                        <h3><?php _e('AVFDigital Branding', 'wp-extreme-customize'); ?></h3>
                        <p><?php _e('Plugin desarrollado por', 'wp-extreme-customize'); ?></p>
                        <div class="wpec-brand">AVFDIGITAL</div>
                    </div>
                    
                    <div class="wpec-card">
                        <h3><?php _e('Estadísticas', 'wp-extreme-customize'); ?></h3>
                        <ul class="wpec-stats">
                            <li><strong><?php _e('PHP:', 'wp-extreme-customize'); ?></strong> <?php echo PHP_VERSION; ?></li>
                            <li><strong><?php _e('WordPress:', 'wp-extreme-customize'); ?></strong> <?php echo get_bloginfo('version'); ?></li>
                            <li><strong><?php _e('Módulos Activos:', 'wp-extreme-customize'); ?></strong> <?php echo count($active_count); ?></li>
                            <li><strong><?php _e('Multisite:', 'wp-extreme-customize'); ?></strong> <?php echo is_multisite() ? __('Sí', 'wp-extreme-customize') : __('No', 'wp-extreme-customize'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="wpec-card">
                        <h3><?php _e('Accesos Rápidos', 'wp-extreme-customize'); ?></h3>
                        <ul class="wpec-quick-links">
                            <li><a href="<?php echo admin_url('admin.php?page=wp-extreme-customize'); ?>"><?php _e('Módulos', 'wp-extreme-customize'); ?></a></li>
                            <li><a href="<?php echo admin_url('admin.php?page=wpec-general'); ?>"><?php _e('Configuración', 'wp-extreme-customize'); ?></a></li>
                            <li><a href="<?php echo admin_url('admin.php?page=wpec-import-export'); ?>"><?php _e('Import/Export', 'wp-extreme-customize'); ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wpec-main-page { max-width: 1200px; }
        .wpec-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }
        .wpec-logo { display: flex; align-items: center; gap: 15px; }
        .wpec-logo .dashicons { font-size: 40px; }
        .wpec-logo h1 { margin: 0; font-size: 28px; }
        .wpec-container { display: grid; grid-template-columns: 1fr 300px; gap: 30px; }
        .wpec-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .wpec-features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .wpec-feature { text-align: center; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; transition: transform 0.2s; }
        .wpec-feature:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .wpec-feature .dashicons { font-size: 40px; color: #2271b1; margin-bottom: 10px; }
        .wpec-brand { font-size: 24px; font-weight: bold; color: #764ba2; text-align: center; margin-top: 10px; }
        .wpec-stats { list-style: none; padding: 0; }
        .wpec-stats li { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .wpec-stats li:last-child { border-bottom: none; }
        .wpec-quick-links { list-style: none; padding: 0; }
        .wpec-quick-links li { margin: 10px 0; }
        .wpec-quick-links a { color: #2271b1; text-decoration: none; }
        .wpec-quick-links a:hover { text-decoration: underline; }
        @media (max-width: 768px) { .wpec-container { grid-template-columns: 1fr; } .wpec-features-grid { grid-template-columns: 1fr; } }
        </style>
        <?php
    }
    
    public function render_general_settings() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Configuración General - WP Extreme Customize', 'wp-extreme-customize') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('wpec_general');
        do_settings_sections('wpec_general');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
    
    public function customize_admin_bar($wp_admin_bar) {
        $settings = get_option('wpec_settings', []);
        
        if (isset($settings['admin_bar']) && $settings['admin_bar']) {
            $wp_admin_bar->add_node([
                'id' => 'wpec-branding',
                'parent' => 'site-name',
                'title' => '<span style="color: #764ba2; font-weight: bold;">AVFDigital</span>',
                'meta' => ['target' => '_self']
            ]);
        }
    }
}

new WPEC_Admin_Interface();
