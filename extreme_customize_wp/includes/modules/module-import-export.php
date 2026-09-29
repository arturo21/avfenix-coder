
<?php
/**
 * Módulo Import/Export - Importar y exportar configuraciones
 * Version: 1.0.2 - Corrección de export con descarga correcta
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Import_Export {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_import_export_menu']);
        add_action('wp_ajax_wpec_export_config', [$this, 'ajax_export_config']);
        add_action('wp_ajax_wpec_import_config', [$this, 'ajax_import_config']);
    }
    
    public function add_import_export_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Import/Export', 'wp-extreme-customize'),
            __('Import/Export', 'wp-extreme-customize'),
            'manage_options',
            'wpec-import-export',
            [$this, 'render_import_export_page']
        );
    }
    
    public function render_import_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        
        $modules_list = [
            'login' => __('Login Customizer', 'wp-extreme-customize'),
            'emails' => __('Email Customizer', 'wp-extreme-customize'),
            'security' => __('Security', 'wp-extreme-customize'),
            'gdpr' => __('GDPR/Cookies', 'wp-extreme-customize'),
            'maintenance' => __('Maintenance Mode', 'wp-extreme-customize'),
            'manager' => __('Plugin/Theme Manager', 'wp-extreme-customize'),
            'favicon' => __('Favicon', 'wp-extreme-customize'),
            'seo' => __('SEO', 'wp-extreme-customize'),
            'performance' => __('Performance', 'wp-extreme-customize'),
            'admin_colors' => __('Admin Colors', 'wp-extreme-customize'),
            'dashboard' => __('Dashboard Widgets', 'wp-extreme-customize'),
            'admin_menu' => __('Admin Menus', 'wp-extreme-customize'),
            'admin_bar' => __('Admin Bar', 'wp-extreme-customize'),
            'activity_log' => __('Activity Log', 'wp-extreme-customize'),
            'file_monitor' => __('File Monitor', 'wp-extreme-customize'),
        ];
        
        $export_nonce = wp_create_nonce('wpec_export_config');
        $import_nonce = wp_create_nonce('wpec_import_config');
        ?>
        <div class="wrap">
            <h1><?php _e('Import/Export', 'wp-extreme-customize'); ?></h1>
            
            <div class="wpec-section" style="background:#fff; padding:20px; margin-bottom:20px; border:1px solid #ddd; border-radius:8px;">
                <h2><?php _e('Exportar Configuración', 'wp-extreme-customize'); ?></h2>
                <p><?php _e('Descarga un archivo JSON con la configuración seleccionada.', 'wp-extreme-customize'); ?></p>
                
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:10px; margin:15px 0;">
                    <?php foreach ($modules_list as $key => $label): ?>
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" class="wpec-export-mod" value="<?php echo esc_attr($key); ?>" checked>
                        <?php echo esc_html($label); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" class="button button-primary" id="wpec-export-btn"><?php _e('Exportar', 'wp-extreme-customize'); ?></button>
                <span class="spinner" style="visibility:hidden; float:none; display:inline-block; margin:0 10px;"></span>
                <span id="wpec-export-result" style="margin-left:10px;"></span>
            </div>
            
            <div class="wpec-section" style="background:#fff; padding:20px; margin-bottom:20px; border:1px solid #ddd; border-radius:8px;">
                <h2><?php _e('Importar Configuración', 'wp-extreme-customize'); ?></h2>
                <p><?php _e('Sube un archivo JSON exportado previamente.', 'wp-extreme-customize'); ?></p>
                
                <form enctype="multipart/form-data" method="post" id="wpec-import-form">
                    <input type="file" name="wpec_import_file" accept=".json,application/json" required style="margin-bottom:10px;">
                    <br>
                    <label style="display:flex; align-items:center; gap:8px; margin-bottom:15px;">
                        <input type="checkbox" name="wpec_overwrite" value="yes" checked>
                        <?php _e('Sobrescribir configuración existente', 'wp-extreme-customize'); ?>
                    </label>
                    <button type="button" class="button button-primary" id="wpec-import-btn"><?php _e('Importar', 'wp-extreme-customize'); ?></button>
                    <span class="spinner" style="visibility:hidden; float:none; display:inline-block; margin:0 10px;"></span>
                    <div id="wpec-import-result" style="margin-top:15px;"></div>
                </form>
            </div>
            
            <script>
            jQuery(function($) {
                var exportNonce = '<?php echo esc_js($export_nonce); ?>';
                var importNonce = '<?php echo esc_js($import_nonce); ?>';
                var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                
                $('#wpec-export-btn').on('click', function() {
                    var $btn = $(this);
                    var $spinner = $btn.next('.spinner');
                    var $result = $('#wpec-export-result');
                    
                    var modules = [];
                    $('.wpec-export-mod:checked').each(function() { modules.push($(this).val()); });
                    
                    if (modules.length === 0) {
                        alert('<?php _e('Selecciona al menos un módulo', 'wp-extreme-customize'); ?>');
                        return;
                    }
                    
                    $btn.prop('disabled', true);
                    $spinner.css('visibility', 'visible');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpec_export_config',
                            modules: modules,
                            nonce: exportNonce
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success && res.data.url) {
                                window.location.href = res.data.url;
                                $result.html('<span style="color:green;">&#10004; <?php _e('Archivo generado', 'wp-extreme-customize'); ?></span>');
                            } else {
                                $result.html('<span style="color:red;">' + (res.data.message || '<?php _e('Error', 'wp-extreme-customize'); ?>') + '</span>');
                            }
                            $btn.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                        },
                        error: function() {
                            $result.html('<span style="color:red;"><?php _e('Error al exportar', 'wp-extreme-customize'); ?></span>');
                            $btn.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                        }
                    });
                });
                
                $('#wpec-import-btn').on('click', function() {
                    var $btn = $(this);
                    var $spinner = $(this).next('.spinner');
                    var $result = $('#wpec-import-result');
                    var formData = new FormData($('#wpec-import-form')[0]);
                    
                    formData.append('action', 'wpec_import_config');
                    formData.append('nonce', importNonce);
                    
                    $btn.prop('disabled', true);
                    $spinner.css('visibility', 'visible');
                    $result.html('');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(res) {
                            $btn.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                            if (res.success) {
                                $result.html('<div class="notice notice-success" style="padding:10px; margin:0;"><p>' + res.data.message + '</p></div>');
                            } else {
                                $result.html('<div class="notice notice-error" style="padding:10px; margin:0;"><p>' + (res.data.message || '<?php _e('Error', 'wp-extreme-customize'); ?>') + '</p></div>');
                            }
                        },
                        error: function() {
                            $btn.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                            $result.html('<div class="notice notice-error" style="padding:10px; margin:0;"><p><?php _e('Error al importar', 'wp-extreme-customize'); ?></p></div>');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function ajax_export_config() {
        check_ajax_referer('wpec_export_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'wp-extreme-customize')]);
        }
        
        $modules = isset($_POST['modules']) ? (array) wp_unslash($_POST['modules']) : [];
        
        $option_map = [
            'login' => 'wpec_login_settings',
            'emails' => 'wpec_email_settings',
            'security' => 'wpec_security_settings',
            'gdpr' => 'wpec_gdpr_settings',
            'maintenance' => 'wpec_maintenance_settings',
            'manager' => 'wpec_manager_settings',
            'favicon' => 'wpec_favicon_settings',
            'seo' => 'wpec_seo_settings',
            'performance' => 'wpec_performance_settings',
            'admin_colors' => 'wpec_admin_color_schemes',
            'dashboard' => 'wpec_dashboard_widgets',
            'admin_menu' => 'wpec_admin_menu_items',
            'admin_bar' => 'wpec_admin_bar_menus',
            'activity_log' => 'wpec_activity_log_settings',
            'file_monitor' => 'wpec_file_monitor_settings',
        ];
        
        $config = [
            'wpec_version' => WPEC_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name'),
            'modules' => [],
        ];
        
        foreach ($modules as $module) {
            $module = sanitize_key($module);
            if (isset($option_map[$module])) {
                $config['modules'][$module] = get_option($option_map[$module], []);
            }
        }
        
        // Guardar en archivo temporal
        $filename = 'wpec-config-' . date('Y-m-d-His') . '.json';
        $temp_dir = wp_tempdir('wpec-export');
        $path = $temp_dir->path . '/' . $filename;
        
        file_put_contents($path, wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        chmod($path, 0644);
        
        // Generar URL temporal
        $url = home_url('/wp-content/uploads/' . wp_basename($temp_dir->path) . '/' . $filename);
        
        // Expira en 5 minutos
        set_transient('wpec_export_' . md5($filename), $path, 300);
        
        wp_send_json_success([
            'url' => $url,
            'filename' => $filename,
            'modules_exported' => count($modules),
        ]);
    }
    
    public function ajax_import_config() {
        check_ajax_referer('wpec_import_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'wp-extreme-customize')]);
        }
        
        if (!isset($_FILES['wpec_import_file']) || $_FILES['wpec_import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('Error al subir archivo', 'wp-extreme-customize')]);
        }
        
        $content = file_get_contents($_FILES['wpec_import_file']['tmp_name']);
        $config = json_decode($content, true);
        
        if (!$config || !isset($config['modules'])) {
            wp_send_json_error(['message' => __('Archivo JSON inválido o sin campo "modules"', 'wp-extreme-customize')]);
        }
        
        $overwrite = isset($_POST['wpec_overwrite']) && $_POST['wpec_overwrite'] === 'yes';
        
        $option_map = [
            'login' => 'wpec_login_settings',
            'emails' => 'wpec_email_settings',
            'security' => 'wpec_security_settings',
            'gdpr' => 'wpec_gdpr_settings',
            'maintenance' => 'wpec_maintenance_settings',
            'manager' => 'wpec_manager_settings',
            'favicon' => 'wpec_favicon_settings',
            'seo' => 'wpec_seo_settings',
            'performance' => 'wpec_performance_settings',
            'admin_colors' => 'wpec_admin_color_schemes',
            'dashboard' => 'wpec_dashboard_widgets',
            'admin_menu' => 'wpec_admin_menu_items',
            'admin_bar' => 'wpec_admin_bar_menus',
            'activity_log' => 'wpec_activity_log_settings',
            'file_monitor' => 'wpec_file_monitor_settings',
        ];
        
        $imported = 0;
        $skipped = 0;
        
        foreach ($config['modules'] as $module => $data) {
            $module = sanitize_key($module);
            if (!isset($option_map[$module]) || !is_array($data)) {
                continue;
            }
            
            $option = $option_map[$module];
            $existing = get_option($option, false);
            
            if ($overwrite || $existing === false) {
                update_option($option, $data);
                $imported++;
            } else {
                $skipped++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('Importados %d módulos, %d omitidos (ya existentes).', 'wp-extreme-customize'),
                $imported, $skipped
            ),
        ]);
    }
}

new WPEC_Import_Export();
