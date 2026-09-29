
<?php
/**
 * Plugin Name: WP Extreme Customize
 * Description: Plugin completo de personalización extrema para WordPress. Branding, seguridad, emails, administración y más.
 * Version: 1.0.2
 * Author: AVFDigital
 * Author URI: https://avfdigital.com
 * Plugin URI: https://avfdigital.com/wp-extreme-customize
 * License: GPL-2.0+
 * Text Domain: wp-extreme-customize
 * Domain Path: /languages
 * Requires PHP: 7.4+
 * Requires WP: 5.8+
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('WPEC_VERSION', '1.0.2');
define('WPEC_PLUGIN_FILE', __FILE__);
define('WPEC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPEC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPEC_INCLUDES_DIR', WPEC_PLUGIN_DIR . 'includes/');
define('WPEC_ASSETS_DIR', WPEC_PLUGIN_DIR . 'assets/');
define('WPEC_ADMIN_DIR', WPEC_INCLUDES_DIR . 'admin/');
define('WPEC_CORE_DIR', WPEC_INCLUDES_DIR . 'core/');
define('WPEC_MODULES_DIR', WPEC_INCLUDES_DIR . 'modules/');

// Inicializar el plugin
class WPExtremeCustomize {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, ['WPExtremeCustomize', 'uninstall']);
        
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'load_components']);
        
        // Cargar admin solo en WordPress admin
        if (is_admin()) {
            add_action('admin_init', [$this, 'load_admin']);
        }
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_assets']);
    }
    
    public function activate() {
        // Verificar requisitos mínimos
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('WP Extreme Customize requiere PHP 7.4 o superior.');
        }
        
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('WP Extreme Customize requiere WordPress 5.8 o superior.');
        }
        
        // Crear tabla de opciones personalizadas
        $this->create_custom_tables();
        flush_rewrite_rules();
        
        // Configurar opciones por defecto
        $this->set_default_options();
        
        // Programar limpieza de transients expirados
        if (!wp_next_scheduled('wpec_cleanup_transients')) {
            wp_schedule_event(time(), 'daily', 'wpec_cleanup_transients');
        }
        add_action('wpec_cleanup_transients', [$this, 'cleanup_expired_transients']);
    }
    
    public function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('wpec_cleanup_transients');
    }
    
    public static function uninstall() {
        // Limpiar opciones
        $options_to_delete = [
            'wpec_settings',
            'wpec_login_settings',
            'wpec_email_settings',
            'wpec_security_settings',
            'wpec_admin_settings',
            'wpec_gdpr_settings',
            'wpec_maintenance_settings',
            'wpec_manager_settings',
            'wpec_favicon_settings',
            'wpec_seo_settings',
            'wpec_performance_settings',
            'wpec_multisite_settings',
            'wpec_admin_color_schemes',
            'wpec_dashboard_widgets',
            'wpec_admin_menu_items',
            'wpec_admin_bar_menus',
            'wpec_recaptcha_settings',
            'wpec_activity_log_settings',
            'wpec_file_monitor_settings',
            'wpec_login_url_settings',
        ];
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
            // También borrar de site options en multisite
            if (is_multisite()) {
                delete_site_option($option);
            }
        }
        
        // Eliminar tablas personalizadas
        global $wpdb;
        if (isset($wpdb)) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpec_custom_data");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpec_activity_log");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpec_file_changes");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpec_login_attempts");
        }
    }
    
    private function create_custom_tables() {
        global $wpdb;
        
        if (!isset($wpdb)) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de datos personalizados
        $table_custom = $wpdb->prefix . 'wpec_custom_data';
        $sql = "CREATE TABLE $table_custom (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY name_index (name)
        ) $charset_collate;";
        
        // Tabla de activity log
        $table_activity = $wpdb->prefix . 'wpec_activity_log';
        $sql_activity = "CREATE TABLE $table_activity (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            action varchar(100) NOT NULL,
            object_type varchar(50),
            object_id bigint(20) DEFAULT 0,
            ip_address varchar(45),
            user_agent text,
            details longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabla de file changes
        $table_files = $wpdb->prefix . 'wpec_file_changes';
        $sql_files = "CREATE TABLE $table_files (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_path varchar(500) NOT NULL,
            change_type enum('created','modified','deleted') NOT NULL,
            old_hash varchar(64),
            new_hash varchar(64),
            detected_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY file_path (file_path),
            KEY change_type (change_type)
        ) $charset_collate;";
        
        // Tabla de login attempts
        $table_login = $wpdb->prefix . 'wpec_login_attempts';
        $sql_login = "CREATE TABLE $table_login (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            username varchar(100),
            success tinyint(1) DEFAULT 0,
            attempt_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY ip_address (ip_address),
            KEY attempt_at (attempt_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_activity);
        dbDelta($sql_files);
        dbDelta($sql_login);
    }
    
    private function set_default_options() {
        $defaults = [
            'wpec_settings' => [
                'modules' => [
                    'login_customizer' => true,
                    'admin_customizer' => true,
                    'email_customizer' => true,
                    'security' => true,
                    'gdpr' => true,
                    'maintenance' => true,
                    'manager' => true,
                    'favicon' => true,
                    'import_export' => true,
                    'multisite' => is_multisite(),
                    'seo' => false,
                    'performance' => false,
                    'admin_color_schemes' => false,
                    'dashboard_widgets' => false,
                    'admin_menu_items' => false,
                    'admin_bar_menus' => false,
                    'recaptcha' => false,
                    'activity_log' => false,
                    'file_monitor' => false,
                    'login_url' => false,
                ]
            ],
            'wpec_login_settings' => [
                'brand_color' => '#764ba2',
                'secondary_color' => '#667eea',
                'header_url' => home_url(),
                'header_text' => get_bloginfo('name'),
                'login_logo' => '',
                'login_bg_image' => '',
                'login_bg_color' => '#f0f0f0',
                'form_bg_color' => '#ffffff',
                'label_color' => '#333333',
                'input_bg_color' => '#ffffff',
                'input_border_color' => '#dddddd',
                'button_color' => '#764ba2',
                'button_hover_color' => '#5a3a7a',
                'link_color' => '#764ba2',
                'error_color' => '#dc3232',
                'success_color' => '#28a745',
                'custom_css' => '',
                'custom_js' => '',
                'redirect_after_login' => '',
                'redirect_after_logout' => '',
                'hide_wp_logo' => false,
                'hide_back_to_site' => false,
                'remember_me_default' => false,
                'custom_message' => '',
                'custom_error_message' => '',
            ],
            'wpec_email_settings' => [
                'brand_color' => '#764ba2',
                'site_name' => get_bloginfo('name'),
                'support_email' => get_option('admin_email'),
                'from_name' => get_bloginfo('name'),
                'from_email' => get_option('admin_email'),
                'header_image' => '',
                'footer_text' => '',
                'footer_links' => '',
                'custom_css' => '',
            ],
            'wpec_security_settings' => [
                'enable_rate_limit' => true,
                'max_login_attempts' => 5,
                'lockout_time' => 15,
                'lockout_duration' => 900,
                'enable_security_headers' => true,
                'enable_hsts' => false,
                'enable_csp' => false,
                'csp_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;",
                'disable_pingbacks' => true,
                'disable_xmlrpc' => true,
                'hide_wp_version' => true,
                'restrict_rest_api' => false,
                'force_https' => false,
                'enable_2fa' => false,
                'force_2fa_roles' => ['administrator'],
                'enable_recaptcha' => false,
                'recaptcha_version' => 'v3',
                'recaptcha_site_key' => '',
                'recaptcha_secret_key' => '',
                'recaptcha_score' => 0.5,
                'disable_file_edit' => true,
                'disable_plugin_install' => false,
                'disable_theme_install' => false,
                'protect_login' => true,
                'protect_register' => true,
                'protect_comments' => true,
                'protect_lostpassword' => true,
            ],
            'wpec_admin_settings' => [
                'admin_footer_text' => '',
                'custom_css' => '',
                'login_custom_css' => '',
                'hidden_bar_items' => [],
                'custom_links' => [],
                'custom_logo' => '',
                'hidden_menu_items' => [],
                'hidden_submenu_items' => [],
                'menu_order' => [],
                'custom_help' => [],
                'color_scheme' => 'default',
                'custom_colors' => [],
            ],
            'wpec_gdpr_settings' => [
                'show_notice' => true,
                'notice_text' => 'Este sitio utiliza cookies para mejorar tu experiencia.',
                'accept_text' => 'Aceptar',
                'reject_text' => 'Rechazar',
                'policy_url' => '',
                'cookie_expiry' => 365,
                'show_categories' => false,
                'categories' => [
                    'necessary' => ['label' => 'Necesarias', 'description' => 'Cookies esenciales para el funcionamiento'],
                    'analytics' => ['label' => 'Analíticas', 'description' => 'Cookies para análisis de tráfico'],
                    'marketing' => ['label' => 'Marketing', 'description' => 'Cookies para publicidad personalizada'],
                ],
            ],
            'wpec_maintenance_settings' => [
                'active' => 'no',
                'title' => 'Sitio en mantenimiento',
                'message' => 'Estamos realizando mejoras. Volvemos pronto.',
                'bg_color' => '#667eea',
                'brand_color' => '#764ba2',
                'logo' => '',
                'countdown' => '0',
                'countdown_date' => '',
                'allowed_roles' => ['administrator'],
                'allowed_ips' => [],
                'custom_css' => '',
                'retry_after' => 3600,
            ],
            'wpec_manager_settings' => [
                'hidden_plugins' => [],
                'renamed_plugins' => [],
                'hidden_themes' => [],
                'hide_plugin_updates' => [],
                'hide_theme_updates' => [],
                'hide_core_updates' => false,
                'disable_plugin_editor' => true,
                'disable_theme_editor' => true,
            ],
            'wpec_favicon_settings' => [
                'favicon_url' => '',
                'apple_touch_url' => '',
                'android_chrome_url' => '',
                'ms_tile_url' => '',
                'ms_tile_color' => '#764ba2',
                'theme_color' => '#764ba2',
            ],
            'wpec_seo_settings' => [
                'default_meta_description' => '',
                'open_graph' => [
                    'title' => '',
                    'description' => '',
                    'image' => '',
                    'type' => 'website',
                ],
                'twitter_cards' => [
                    'card_type' => 'summary_large_image',
                    'site' => '',
                    'creator' => '',
                ],
                'schema_org' => [
                    'logo' => '',
                    'organization_name' => get_bloginfo('name'),
                ],
                'custom_canonical' => '',
                'robots_txt' => '',
            ],
            'wpec_performance_settings' => [
                'enable_cache' => false,
                'cache_time' => 3600,
                'minify_css' => false,
                'minify_js' => false,
                'combine_css' => false,
                'combine_js' => false,
                'lazy_load_images' => true,
                'optimize_images' => false,
                'webp' => false,
                'preload_resources' => [],
                'critical_css' => '',
                'dns_prefetch' => [
                    'fonts.googleapis.com',
                    'fonts.gstatic.com',
                    'ajax.googleapis.com',
                ],
            ],
            'wpec_multisite_settings' => [
                'global_branding' => false,
                'global_login' => false,
                'global_emails' => false,
                'global_security' => false,
                'global_gdpr' => false,
                'global_seo' => false,
                'apply_to_new_sites' => true,
            ],
            'wpec_admin_color_schemes' => [],
            'wpec_dashboard_widgets' => [],
            'wpec_admin_menu_items' => [],
            'wpec_admin_bar_menus' => [],
            'wpec_recaptcha_settings' => [],
            'wpec_activity_log_settings' => [
                'enabled' => false,
                'log_level' => 'info',
                'retention_days' => 90,
                'excluded_actions' => [],
            ],
            'wpec_file_monitor_settings' => [
                'enabled' => false,
                'scan_frequency' => 'daily',
                'excluded_paths' => [
                    'wp-content/uploads',
                    'wp-content/cache',
                    'wp-content/upgrade',
                ],
                'notify_email' => get_option('admin_email'),
                'alert_on_core_changes' => true,
                'alert_on_plugin_changes' => true,
                'alert_on_theme_changes' => true,
            ],
            'wpec_login_url_settings' => [
                'enable_custom_login_url' => false,
                'login_slug' => 'login',
                'logout_slug' => 'logout',
                'lostpassword_slug' => 'lost-password',
                'register_slug' => 'register',
            ],
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option, false) === false) {
                add_option($option, $value);
            }
        }
        
        // Configurar opciones de red en multisite
        if (is_multisite()) {
            foreach ($defaults as $option => $value) {
                if (get_site_option($option, false) === false) {
                    add_site_option($option, $value);
                }
            }
        }
    }
    
    public function cleanup_expired_transients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpec_%' AND option_value < " . time());
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpec_%' AND option_value < " . time());
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-extreme-customize',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    public function load_components() {
        $this->load_core_components();
        $this->load_modules();
    }
    
    private function load_core_components() {
        $core_files = glob(WPEC_INCLUDES_DIR . 'core/*.php');
        if ($core_files) {
            foreach ($core_files as $file) {
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }
    }
    
    private function load_modules() {
        // Cargar módulos en orden de dependencia
        $module_order = [
            'module-login.php',
            'module-emails.php',
            'module-admin.php',
            'module-security.php',
            'module-gdpr.php',
            'module-maintenance.php',
            'module-manager.php',
            'module-favicon.php',
            'module-import-export.php',
            'module-multisite.php',
            'module-seo.php',
            'module-performance.php',
            'module-admin-color-schemes.php',
            'module-dashboard-widgets.php',
            'module-admin-menu-items.php',
            'module-admin-bar-menus.php',
            'module-recaptcha.php',
            'module-activity-log.php',
            'module-file-monitor.php',
            'module-login-url.php',
        ];
        
        foreach ($module_order as $module_file) {
            $file_path = WPEC_MODULES_DIR . $module_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    public function load_admin() {
        $admin_files = glob(WPEC_ADMIN_DIR . '*.php');
        if ($admin_files) {
            foreach ($admin_files as $file) {
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }
        
        // Registrar settings para todos los módulos
        $this->register_all_settings();
    }
    
    private function register_all_settings() {
        $settings_groups = [
            'wpec_settings' => ['wpec_settings'],
            'wpec_login' => ['wpec_login_settings'],
            'wpec_email' => ['wpec_email_settings'],
            'wpec_security' => ['wpec_security_settings'],
            'wpec_admin' => ['wpec_admin_settings'],
            'wpec_gdpr' => ['wpec_gdpr_settings'],
            'wpec_maintenance' => ['wpec_maintenance_settings'],
            'wpec_manager' => ['wpec_manager_settings'],
            'wpec_favicon' => ['wpec_favicon_settings'],
            'wpec_seo' => ['wpec_seo_settings'],
            'wpec_performance' => ['wpec_performance_settings'],
            'wpec_multisite' => ['wpec_multisite_settings'],
            'wpec_admin_colors' => ['wpec_admin_color_schemes'],
            'wpec_dashboard' => ['wpec_dashboard_widgets'],
            'wpec_admin_menu' => ['wpec_admin_menu_items'],
            'wpec_admin_bar' => ['wpec_admin_bar_menus'],
            'wpec_recaptcha' => ['wpec_recaptcha_settings'],
            'wpec_activity' => ['wpec_activity_log_settings'],
            'wpec_file_monitor' => ['wpec_file_monitor_settings'],
            'wpec_login_url' => ['wpec_login_url_settings'],
        ];
        
        foreach ($settings_groups as $group => $options) {
            foreach ($options as $option) {
                register_setting($group, $option, [
                    'type' => 'array',
                    'sanitize_callback' => [$this, 'sanitize_settings_array'],
                    'default' => [],
                ]);
            }
        }
    }
    
    public function sanitize_settings_array($input) {
        if (!is_array($input)) {
            return [];
        }
        
        return $this->recursive_sanitize($input);
    }
    
    private function recursive_sanitize($data) {
        if (!is_array($data)) {
            if (is_bool($data)) {
                return $data;
            }
            if (is_int($data)) {
                return intval($data);
            }
            if (is_float($data)) {
                return floatval($data);
            }
            return sanitize_text_field($data);
        }
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = $this->recursive_sanitize($value);
        }
        
        return $sanitized;
    }
    
    public function enqueue_frontend_assets() {
        // CSS para frontend si es necesario
        if (is_user_logged_in()) {
            $settings = get_option('wpec_admin_settings', []);
            if (!empty($settings['custom_css'])) {
                wp_add_inline_style('wpec-frontend-style', $settings['custom_css']);
            }
        }
    }
    
    public function enqueue_login_assets() {
        $settings = get_option('wpec_admin_settings', []);
        if (!empty($settings['login_custom_css'])) {
            wp_add_inline_style('login', $settings['login_custom_css']);
        }
    }
}

// Inicializar el plugin
add_action('plugins_loaded', ['WPExtremeCustomize', 'get_instance']);
