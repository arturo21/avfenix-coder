
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
        add_action('wp_before_admin_bar_render', [$this, customize_admin_bar]);
    }
    
    public function add_admin_pages() {
        // Página principal
        add_menu_page(
            __('WP Extreme Customize', 'wp-extreme-customize'),
            __('Extreme Customize', 'wp-extreme-customize'),
            'manage_options',
            'wp-extreme-customize',
            [$this, 'render_main_page'],
            'dashicons-admin-customizer',
            6
        );
        
        // Submenú: Configuración General
        add_settings_section(
            'wpec_general',
            __('Configuración General', 'wp-extreme-customize'),
            [$this, 'general_section_callback'],
            'wp-extreme-customize'
        );
        
        // Página de configuración
        add_options_page(
            __('WP Extreme Customize Configuración', 'wp-extreme-customize'),
            __('Extreme Customize', 'wp-extreme-customize'),
            'manage_options',
            'wp-extreme-customize',
            [$this, 'render_settings_page']
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wp-extreme-customize') !== false) {
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
                                <h3><?php _e('Login Personalizado', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Cambia logo, colores, fondo y más', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-admin-site"></span>
                                <h3><?php _e('Admin Personalizado', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Personaliza el panel de WordPress', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-email-alt"></span>
                                <h3><?php _e('Emails Personalizados', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Diseña todos los emails del sistema', 'wp-extreme-customize'); ?></p>
                            </div>
                            
                            <div class="wpec-feature">
                                <span class="dashicons dashicons-shield-alt"></span>
                                <h3><?php _e('Seguridad Extrema', 'wp-extreme-customize'); ?></h3>
                                <p><?php _e('Protege tu sitio con avanzada', 'wp-extreme-customize'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wpec-card">
                        <h2><?php _e('Configuración Rápida', 'wp-extreme-customize'); ?></h2>
                        <form method="post" action="options.php">
                            <?php settings_fields('wpec_general'); ?>
                            <?php do_settings_sections('wpec_general'); ?>
                            <?php submit_button(__('Configurar Plugin', 'wp-extreme-customize'), 'primary', 'wpec_save_settings'); ?>
                        </form>
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
                            <li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
                            <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></li>
                            <li><strong>Módulos Activos:</strong> <?php echo count(get_option('wpec_settings', [])['modules'] ?? []); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wpec-main-page {
            max-width: 1200px;
        }
        .wpec-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .wpec-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .wpec-logo .dashicons {
            font-size: 40px;
        }
        .wpec-logo h1 {
            margin: 0;
            font-size: 28px;
        }
        .wpec-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
        }
        .wpec-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .wpec-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .wpec-feature {
            text-align: center;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        .wpec-feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .wpec-feature .dashicons {
            font-size: 40px;
            color: #2271b1;
            margin-bottom: 10px;
        }
        .wpec-brand {
            font-size: 24px;
            font-weight: bold;
            color: #764ba2;
            text-align: center;
            margin-top: 10px;
        }
        .wpec-stats {
            list-style: none;
            padding: 0;
        }
        .wpec-stats li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .wpec-stats li:last-child {
            border-bottom: none;
        }
        </style>
        <?php
    }
    
    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Configuración de WP Extreme Customize', 'wp-extreme-customize') . '</h1>';
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
            // Personalizar la barra de administración
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
