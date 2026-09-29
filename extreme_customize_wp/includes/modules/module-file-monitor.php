
<?php
/**
 * Módulo File Monitor - Monitor de cambios en archivos del sistema
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_File_Monitor {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_file_monitor_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        if (!$this->get_setting('enabled', false)) {
            return;
        }
        
        // Programar escaneo periódico
        $frequency = $this->get_setting('scan_frequency', 'daily');
        $hook_name = 'wpec_file_monitor_scan';
        
        if (!wp_next_scheduled($hook_name)) {
            wp_schedule_event(time(), $frequency, $hook_name);
        }
        add_action($hook_name, [$this, 'scan_files']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_file_monitor_menu']);
        add_action('wp_ajax_wpec_run_file_scan', [$this, 'ajax_run_scan']);
        add_action('wp_ajax_wpec_get_file_hash', [$this, 'ajax_get_file_hash']);
    }
    
    public function scan_files() {
        $excluded_paths = $this->get_setting('excluded_paths', [
            'wp-content/uploads',
            'wp-content/cache',
            'wp-content/upgrade',
            'wp-content/backups',
        ]);
        
        $alert_on_core = $this->get_setting('alert_on_core_changes', true);
        $alert_on_plugin = $this->get_setting('alert_on_plugin_changes', true);
        $alert_on_theme = $this->get_setting('alert_on_theme_changes', true);
        
        $results = [
            'created' => [],
            'modified' => [],
            'deleted' => [],
        ];
        
        // Escanear WordPress Core
        if ($alert_on_core) {
            $core_results = $this->scan_directory(ABSPATH, 'core', $excluded_paths);
            $results = array_merge_recursive($results, $core_results);
        }
        
        // Escanear plugins
        if ($alert_on_plugin) {
            $plugin_results = $this->scan_directory(WP_PLUGIN_DIR, 'plugin', $excluded_paths);
            $results = array_merge_recursive($results, $plugin_results);
        }
        
        // Escanear temas
        if ($alert_on_theme) {
            $theme_dir = get_theme_root();
            $theme_results = $this->scan_directory($theme_dir, 'theme', $excluded_paths);
            $results = array_merge_recursive($results, $theme_results);
        }
        
        // Guardar resultados y enviar alertas
        if (!empty($results['created']) || !empty($results['modified']) || !empty($results['deleted'])) {
            $this->save_scan_results($results);
            $this->send_alert($results);
        }
        
        return $results;
    }
    
    private function scan_directory($directory, $type, $excluded_paths) {
        $results = ['created' => [], 'modified' => [], 'deleted' => []];
        $excluded_full = [];
        
        foreach ($excluded_paths as $path) {
            $excluded_full[] = rtrim(ABSPATH, '/') . '/' . ltrim($path, '/');
        }
        
        $files = $this->get_all_files($directory, $excluded_full);
        $stored_hashes = $this->get_stored_hashes($type);
        
        foreach ($files as $file) {
            $relative_path = str_replace(ABSPATH, '', $file);
            $hash = $this->calculate_hash($file);
            
            if (!isset($stored_hashes[$relative_path])) {
                $results['created'][] = [
                    'path' => $relative_path,
                    'hash' => $hash,
                    'type' => $type,
                    'detected_at' => current_time('mysql'),
                ];
            } elseif ($stored_hashes[$relative_path] !== $hash) {
                $results['modified'][] = [
                    'path' => $relative_path,
                    'old_hash' => $stored_hashes[$relative_path],
                    'new_hash' => $hash,
                    'type' => $type,
                    'detected_at' => current_time('mysql'),
                ];
            }
        }
        
        // Detectar archivos eliminados
        foreach ($stored_hashes as $path => $hash) {
            $full_path = ABSPATH . $path;
            if (!file_exists($full_path)) {
                $results['deleted'][] = [
                    'path' => $path,
                    'old_hash' => $hash,
                    'type' => $type,
                    'detected_at' => current_time('mysql'),
                ];
            }
        }
        
        // Actualizar hashes almacenados
        $new_hashes = [];
        foreach ($files as $file) {
            $relative_path = str_replace(ABSPATH, '', $file);
            $new_hashes[$relative_path] = $this->calculate_hash($file);
        }
        $this->save_hashes($type, $new_hashes);
        
        return $results;
    }
    
    private function get_all_files($directory, $excluded_paths) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $path = $file->getPathname();
            
            // Verificar exclusiones
            $excluded = false;
            foreach ($excluded_paths as $excluded_path) {
                if (strpos($path, $excluded_path) === 0) {
                    $excluded = true;
                    break;
                }
            }
            
            if (!$excluded) {
                $files[] = $path;
            }
        }
        
        return $files;
    }
    
    private function calculate_hash($file) {
        return hash_file('sha256', $file);
    }
    
    private function get_stored_hashes($type) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_file_changes';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT file_path, new_hash FROM $table WHERE change_type IN ('created', 'modified') AND file_path LIKE %s ORDER BY detected_at DESC",
            $type . '/%'
        ));
        
        $hashes = [];
        foreach ($results as $row) {
            if (!isset($hashes[$row->file_path])) {
                $hashes[$row->file_path] = $row->new_hash;
            }
        }
        
        return $hashes;
    }
    
    private function save_hashes($type, $hashes) {
        // Los hashes se guardan automáticamente en save_scan_results
    }
    
    private function save_scan_results($results) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_file_changes';
        
        foreach (['created', 'modified', 'deleted'] as $change_type) {
            foreach ($results[$change_type] as $change) {
                $wpdb->insert($table, [
                    'file_path' => $change['path'],
                    'change_type' => $change_type,
                    'old_hash' => $change['old_hash'] ?? null,
                    'new_hash' => $change['new_hash'] ?? null,
                    'detected_at' => $change['detected_at'],
                ]);
            }
        }
    }
    
    private function send_alert($results) {
        $notify_email = $this->get_setting('notify_email', get_option('admin_email'));
        
        if (empty($notify_email)) {
            return;
        }
        
        $subject = sprintf('[%s] %s', get_bloginfo('name'), __('Alerta: Cambios en archivos detectados', 'wp-extreme-customize'));
        
        $message = '<h2>Cambios en archivos detectados</h2>';
        $message .= '<p>Se han detectado los siguientes cambios en el sistema de archivos:</p>';
        
        foreach (['created' => 'Creados', 'modified' => 'Modificados', 'deleted' => 'Eliminados'] as $type => $label) {
            if (!empty($results[$type])) {
                $message .= '<h3>' . $label . ' (' . count($results[$type]) . ')</h3>';
                $message .= '<ul>';
                foreach (array_slice($results[$type], 0, 20) as $change) {
                    $message .= '<li><code>' . esc_html($change['path']) . '</code></li>';
                }
                if (count($results[$type]) > 20) {
                    $message .= '<li>... y ' . (count($results[$type]) - 20) . ' más</li>';
                }
                $message .= '</ul>';
            }
        }
        
        $message .= '<p><a href="' . admin_url('admin.php?page=wpec-file-monitor') . '">Ver detalles en el panel</a></p>';
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($notify_email, $subject, $message, $headers);
    }
    
    public function ajax_run_scan() {
        check_ajax_referer('wpec_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'wp-extreme-customize')]);
        }
        
        $results = $this->scan_files();
        
        wp_send_json_success([
            'message' => sprintf(__('Escaneo completado. %d creados, %d modificados, %d eliminados.', 'wp-extreme-customize'),
                count($results['created']),
                count($results['modified']),
                count($results['deleted'])
            ),
            'results' => $results,
        ]);
    }
    
    public function ajax_get_file_hash() {
        check_ajax_referer('wpec_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'wp-extreme-customize')]);
        }
        
        $file_path = sanitize_text_field($_POST['file_path'] ?? '');
        $full_path = ABSPATH . $file_path;
        
        if (!file_exists($full_path) || !is_file($full_path)) {
            wp_send_json_error(['message' => __('Archivo no encontrado', 'wp-extreme-customize')]);
        }
        
        $hash = $this->calculate_hash($full_path);
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_file_changes';
        $stored = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE file_path = %s ORDER BY detected_at DESC LIMIT 1",
            $file_path
        ));
        
        wp_send_json_success([
            'current_hash' => $hash,
            'stored_hash' => $stored->new_hash ?? null,
            'matches' => $stored && $stored->new_hash === $hash,
        ]);
    }
    
    public function add_file_monitor_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Monitor de Archivos', 'wp-extreme-customize'),
            __('Monitor Archivos', 'wp-extreme-customize'),
            'manage_options',
            'wpec-file-monitor',
            [$this, 'render_file_monitor_page']
        );
    }
    
    public function render_file_monitor_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_file_changes';
        
        // Estadísticas
        $stats = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'created' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE change_type = 'created'"),
            'modified' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE change_type = 'modified'"),
            'deleted' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE change_type = 'deleted'"),
            'today' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE DATE(detected_at) = %s", date('Y-m-d'))),
        ];
        
        // Últimos cambios
        $recent = $wpdb->get_results("SELECT * FROM $table ORDER BY detected_at DESC LIMIT 20");
        ?>
        <div class="wrap">
            <h1><?php _e('Monitor de Cambios en Archivos', 'wp-extreme-customize'); ?></h1>
            
            <div class="wpec-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="wpec-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo $stats['total']; ?></div>
                    <div><?php _e('Total Cambios', 'wp-extreme-customize'); ?></div>
                </div>
                <div class="wpec-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #28a745;"><?php echo $stats['created']; ?></div>
                    <div><?php _e('Creados', 'wp-extreme-customize'); ?></div>
                </div>
                <div class="wpec-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #ffc107;"><?php echo $stats['modified']; ?></div>
                    <div><?php _e('Modificados', 'wp-extreme-customize'); ?></div>
                </div>
                <div class="wpec-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #dc3545;"><?php echo $stats['deleted']; ?></div>
                    <div><?php _e('Eliminados', 'wp-extreme-customize'); ?></div>
                </div>
                <div class="wpec-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #17a2b8;"><?php echo $stats['today']; ?></div>
                    <div><?php _e('Hoy', 'wp-extreme-customize'); ?></div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <button type="button" class="button button-primary" id="wpec-run-scan"><?php _e('Ejecutar Escaneo Ahora', 'wp-extreme-customize'); ?></button>
                <span class="spinner" id="wpec-scan-spinner" style="float: none; margin: 0 10px;"></span>
                <span id="wpec-scan-result"></span>
            </div>
            
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Fecha', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Tipo', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Archivo', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Hash Anterior', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Hash Nuevo', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Verificar', 'wp-extreme-customize'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $change): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($change->detected_at)); ?></td>
                        <td>
                            <span style="background: <?php echo $change->change_type === 'created' ? '#28a745' : ($change->change_type === 'modified' ? '#ffc107' : '#dc3545'); ?>; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;"><?php echo ucfirst($change->change_type); ?></span>
                        </td>
                        <td><code><?php echo esc_html($change->file_path); ?></code></td>
                        <td><code style="font-size: 10px;"><?php echo esc_html(substr($change->old_hash ?? '', 0, 16)) . '...'; ?></code></td>
                        <td><code style="font-size: 10px;"><?php echo esc_html(substr($change->new_hash ?? '', 0, 16)) . '...'; ?></code></td>
                        <td>
                            <button type="button" class="button button-small wpec-verify-file" data-file="<?php echo esc_attr($change->file_path); ?>"><?php _e('Verificar', 'wp-extreme-customize'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2><?php _e('Configuración', 'wp-extreme-customize'); ?></h2>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_file_monitor'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Activar Monitor', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpec_file_monitor_settings[enabled]" value="yes" <?php checked($this->settings['enabled'] ?? '', 'yes'); ?>>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Frecuencia de Escaneo', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <select name="wpec_file_monitor_settings[scan_frequency]" class="medium-text">
                                <option value="hourly" <?php selected($this->settings['scan_frequency'] ?? '', 'hourly'); ?>><?php _e('Cada hora', 'wp-extreme-customize'); ?></option>
                                <option value="twicedaily" <?php selected($this->settings['scan_frequency'] ?? '', 'twicedaily'); ?>><?php _e('Dos veces al día', 'wp-extreme-customize'); ?></option>
                                <option value="daily" <?php selected($this->settings['scan_frequency'] ?? '', 'daily'); ?>><?php _e('Diario', 'wp-extreme-customize'); ?></option>
                                <option value="weekly" <?php selected($this->settings['scan_frequency'] ?? '', 'weekly'); ?>><?php _e('Semanal', 'wp-extreme-customize'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Rutas Excluidas', 'wp-extreme-customize'); ?></label></th>
                        <td>
                            <textarea name="wpec_file_monitor_settings[excluded_paths]" rows="5" class="large-text"><?php echo esc_textarea(implode("\n", $this->settings['excluded_paths'] ?? [])); ?></textarea>
                            <p class="description"><?php _e('Una ruta por línea. Rutas relativas a la raíz de WordPress.', 'wp-extreme-customize'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('Alertar por Cambios', 'wp-extreme-customize'); ?></th>
                        <td>
                            <label><input type="checkbox" name="wpec_file_monitor_settings[alert_on_core_changes]" value="yes" <?php checked($this->settings['alert_on_core_changes'] ?? '', 'yes'); ?>> <?php _e('Core de WordPress', 'wp-extreme-customize'); ?></label><br>
                            <label><input type="checkbox" name="wpec_file_monitor_settings[alert_on_plugin_changes]" value="yes" <?php checked($this->settings['alert_on_plugin_changes'] ?? '', 'yes'); ?>> <?php _e('Plugins', 'wp-extreme-customize'); ?></label><br>
                            <label><input type="checkbox" name="wpec_file_monitor_settings[alert_on_theme_changes]" value="yes" <?php checked($this->settings['alert_on_theme_changes'] ?? '', 'yes'); ?>> <?php _e('Temas', 'wp-extreme-customize'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('Email de Notificación', 'wp-extreme-customize'); ?></label></th>
                        <td><input type="email" name="wpec_file_monitor_settings[notify_email]" value="<?php echo esc_attr($this->settings['notify_email'] ?? get_option('admin_email')); ?>" class="regular-text"></td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                $('#wpec-run-scan').on('click', function() {
                    var $btn = $(this);
                    var $spinner = $('#wpec-scan-spinner');
                    var $result = $('#wpec-scan-result');
                    
                    $btn.prop('disabled', true);
                    $spinner.css('visibility', 'visible');
                    $result.html('');
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'wpec_run_file_scan',
                            nonce: '<?php echo wp_create_nonce('wpec_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            $btn.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                            if (response.success) {
                                $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            $btn.prop('disabled', false);
                            $spinner.css('visibility', 'hidden');
                            $result.html('<div class="notice notice-error inline"><p><?php _e('Error al ejecutar escaneo', 'wp-extreme-customize'); ?></p></div>');
                        }
                    });
                });
                
                // Verificar archivo
                $(document).on('click', '.wpec-verify-file', function() {
                    var $btn = $(this);
                    var file = $btn.data('file');
                    
                    $btn.prop('disabled', true).text('<?php _e('Verificando...', 'wp-extreme-customize'); ?>');
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'wpec_get_file_hash',
                            file_path: file,
                            nonce: '<?php echo wp_create_nonce('wpec_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            $btn.prop('disabled', false).text('<?php _e('Verificar', 'wp-extreme-customize'); ?>');
                            if (response.success) {
                                var msg = response.data.matches ? 
                                    '<?php _e('✓ Hash coincide', 'wp-extreme-customize'); ?>' : 
                                    '<?php _e('✗ Hash NO coincide', 'wp-extreme-customize'); ?>';
                                alert(msg + '\nActual: ' + response.data.current_hash + '\nAlmacenado: ' + (response.data.stored_hash || 'N/A'));
                            }
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_File_Monitor();
