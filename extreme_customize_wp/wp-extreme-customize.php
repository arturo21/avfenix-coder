
<?php
/**
 * Plugin Name: WP Extreme Customize
 * Description: Plugin completo de personalización extrema para WordPress. Branding, seguridad, emails, administración y más.
 * Version: 1.0.0
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
define('WPEC_VERSION', '1.0.0');
define('WPEC_PLUGIN_FILE', __FILE__);
define('WPEC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPEC_PLUGIN_URL', plugin_dir_url(__FILE__))
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
        $this->load_components();
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
    }
    
    public function activate() {
        // Crear tabla de opciones personalizadas si es necesario
        $this->create_custom_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public static function uninstall() {
        // Limpiar datos al desinstalar
        delete_option('wpec_settings');
        // Eliminar tablas personalizadas si existen
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpec_custom_data");
    }
    
    private function create_custom_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpec_custom_data';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY name_index (name)
        ) $charset_collate;";
        
        require_once(_ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-extreme-customize',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    public function load_components() {
        // Cargar componentes core
        $this->load_core_components();
        // Cargar módulos
        $this->load_modules();
    }
    
    private function load_core_components() {
        $core_files = glob(WPEC_CORE_DIR . '*.php');
        if ($core_files) {
            foreach ($core_files as $file) {
                require_once $file;
            }
        }
    }
    
    private function load_modules() {
        $module_files = glob(WPEC_MODULES_DIR . '*.php');
        if ($module_files) {
            foreach ($module_files as $file) {
                require_once $file;
            }
        }
    }
    
    public function load_admin() {
        $admin_files = glob(WPEC_ADMIN_DIR . '*.php');
        if ($admin_files) {
            foreach ($admin_files as $file) {
                require_once $file;
            }
        }
    }
}

// Inicializar el plugin
WPExtremeCustomize::get_instance();

// Cargar componentes adicionales después de la inicialización
add_action('plugins_loaded', function() {
    // Cargar todos los archivos de includes
    $includes = [
        'class-login-customizer.php',
        'class-admin-customizer.php',
        'class-email-customizer.php',
        'class-security.php',
        'class-utilities.php',
        'class-modules.php'
    ];
    
    foreach ($includes as $file) {
        $filepath = WPEC_INCLUDES_DIR . $file;
        if (file_exists($filepath)) {
            require_once $filepath;
        }
    }
});
