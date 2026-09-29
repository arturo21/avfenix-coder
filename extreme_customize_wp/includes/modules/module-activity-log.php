
<?php
/**
 * Módulo Activity Log - Registro de actividad de usuarios
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Activity_Log {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_activity_log_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        if (!$this->get_setting('enabled', false)) {
            return;
        }
        
        // Acciones de usuario
        add_action('wp_login', [$this, 'log_login'], 10, 2);
        add_action('wp_logout', [$this, 'log_logout']);
        add_action('profile_update', [$this, 'log_profile_update'], 10, 2);
        add_action('user_register', [$this, 'log_user_register']);
        add_action('delete_user', [$this, 'log_user_delete']);
        
        // Acciones de contenido
        add_action('save_post', [$this, 'log_post_save'], 10, 3);
        add_action('delete_post', [$this, 'log_post_delete']);
        add_action('trashed_post', [$this, 'log_post_trash']);
        add_action('untrashed_post', [$this, 'log_post_untrash']);
        
        // Acciones de plugins/temas
        add_action('activated_plugin', [$this, 'log_plugin_activate']);
        add_action('deactivated_plugin', [$this, 'log_plugin_deactivate']);
        add_action('switch_theme', [$this, 'log_theme_switch']);
        
        // Acciones de ajustes
        add_action('update_option', [$this, 'log_option_update'], 10, 3);
        
        // Admin
        add_action('admin_menu', [$this, 'add_activity_log_menu']);
    }
    
    public function log_login($user_login, $user) {
        $this->log_action('user_login', 'user', $user->ID, [
            'user_login' => $user_login,
            'user_email' => $user->user_email,
        ]);
    }
    
    public function log_logout() {
        $user_id = get_current_user_id();
        $this->log_action('user_logout', 'user', $user_id, []);
    }
    
    public function log_profile_update($user_id, $old_user_data) {
        $this->log_action('profile_update', 'user', $user_id, [
            'changes' => $this->get_user_changes($old_user_data, get_userdata($user_id)),
        ]);
    }
    
    public function log_user_register($user_id) {
        $this->log_action('user_register', 'user', $user_id, []);
    }
    
    public function log_user_delete($user_id) {
        $this->log_action('user_delete', 'user', $user_id, []);
    }
    
    public function log_post_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $action = $update ? 'post_update' : 'post_create';
        $this->log_action($action, 'post', $post_id, [
            'post_type' => $post->post_type,
            'post_title' => $post->post_title,
            'post_status' => $post->post_status,
        ]);
    }
    
    public function log_post_delete($post_id) {
        $this->log_action('post_delete', 'post', $post_id, []);
    }
    
    public function log_post_trash($post_id) {
        $this->log_action('post_trash', 'post', $post_id, []);
    }
    
    public function log_post_untrash($post_id) {
        $this->log_action('post_untrash', 'post', $post_id, []);
    }
    
    public function log_plugin_activate($plugin) {
        $this->log_action('plugin_activate', 'plugin', 0, [
            'plugin' => $plugin,
        ]);
    }
    
    public function log_plugin_deactivate($plugin) {
        $this->log_action('plugin_deactivate', 'plugin', 0, [
            'plugin' => $plugin,
        ]);
    }
    
    public function log_theme_switch($new_theme) {
        $this->log_action('theme_switch', 'theme', 0, [
            'new_theme' => $new_theme->get_stylesheet(),
        ]);
    }
    
    public function log_option_update($option, $old_value, $new_value) {
        $excluded = $this->get_setting('excluded_actions', []);
        if (in_array('option_update_' . $option, $excluded)) {
            return;
        }
        
        // Solo loguear opciones importantes
        $important_options = ['blogname', 'blogdescription', 'admin_email', 'users_can_register', 'default_role', 'permalink_structure'];
        if (!in_array($option, $important_options)) {
            return;
        }
        
        $this->log_action('option_update', 'option', 0, [
            'option' => $option,
            'old_value' => is_scalar($old_value) ? $old_value : 'array/object',
            'new_value' => is_scalar($new_value) ? $new_value : 'array/object',
        ]);
    }
    
    private function get_user_changes($old_user, $new_user) {
        $changes = [];
        $fields = ['user_login', 'user_email', 'display_name', 'user_url', 'role'];
        
        foreach ($fields as $field) {
            $old_val = isset($old_user->$field) ? $old_user->$field : '';
            $new_val = isset($new_user->$field) ? $new_user->$field : '';
            
            if ($old_val !== $new_val) {
                $changes[$field] = ['old' => $old_val, 'new' => $new_val];
            }
        }
        
        return $changes;
    }
    
    private function log_action($action, $object_type, $object_id, $details = []) {
        $retention_days = $this->get_setting('retention_days', 90);
        $excluded = $this->get_setting('excluded_actions', []);
        
        if (in_array($action, $excluded)) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_activity_log';
        
        $wpdb->insert($table, [
            'user_id' => get_current_user_id(),
            'action' => $action,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
            'details' => wp_json_encode($details),
            'created_at' => current_time('mysql'),
        ]);
        
        // Limpiar logs antiguos
        $this->cleanup_old_logs($retention_days);
    }
    
    private function cleanup_old_logs($retention_days) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_activity_log';
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", $cutoff));
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
    
    public function add_activity_log_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Registro de Actividad', 'wp-extreme-customize'),
            __('Registro Actividad', 'wp-extreme-customize'),
            'manage_options',
            'wpec-activity-log',
            [$this, 'render_activity_log_page']
        );
    }
    
    public function render_activity_log_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpec_activity_log';
        
        // Filtros
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $where = ['1=1'];
        $args = [];
        
        if (!empty($_GET['action'])) {
            $where[] = 'action = %s';
            $args[] = sanitize_text_field($_GET['action']);
        }
        
        if (!empty($_GET['user_id'])) {
            $where[] = 'user_id = %d';
            $args[] = intval($_GET['user_id']);
        }
        
        if (!empty($_GET['date_from'])) {
            $where[] = 'created_at >= %s';
            $args[] = sanitize_text_field($_GET['date_from']) . ' 00:00:00';
        }
        
        if (!empty($_GET['date_to'])) {
            $where[] = 'created_at <= %s';
            $args[] = sanitize_text_field($_GET['date_to']) . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Total
        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where_clause", ...$args));
        $total_pages = ceil($total / $per_page);
        
        // Logs
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($args, [$per_page, $offset])
        ));
        
        // Acciones únicas para filtro
        $actions = $wpdb->get_col("SELECT DISTINCT action FROM $table ORDER BY action");
        ?>
        <div class="wrap">
            <h1><?php _e('Registro de Actividad', 'wp-extreme-customize'); ?></h1>
            
            <div class="wpec-filters" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd;">
                <form method="get" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                    <input type="hidden" name="page" value="wpec-activity-log">
                    
                    <div>
                        <label><?php _e('Acción', 'wp-extreme-customize'); ?></label>
                        <select name="action" class="medium-text">
                            <option value=""><?php _e('Todas', 'wp-extreme-customize'); ?></option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo esc_attr($action); ?>" <?php selected($_GET['action'] ?? '', $action); ?>><?php echo esc_html($action); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label><?php _e('Usuario ID', 'wp-extreme-customize'); ?></label>
                        <input type="number" name="user_id" value="<?php echo esc_attr($_GET['user_id'] ?? ''); ?>" class="small-text">
                    </div>
                    
                    <div>
                        <label><?php _e('Desde', 'wp-extreme-customize'); ?></label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label><?php _e('Hasta', 'wp-extreme-customize'); ?></label>
                        <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="button button-primary"><?php _e('Filtrar', 'wp-extreme-customize'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=wpec-activity-log'); ?>" class="button"><?php _e('Limpiar', 'wp-extreme-customize'); ?></a>
                </form>
            </div>
            
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Fecha', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Usuario', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Acción', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Objeto', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('IP', 'wp-extreme-customize'); ?></th>
                        <th><?php _e('Detalles', 'wp-extreme-customize'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log->created_at)); ?></td>
                        <td>
                            <?php if ($log->user_id): ?>
                                <?php 
                                $user = get_userdata($log->user_id);
                                echo $user ? esc_html($user->display_name) . ' (#' . $log->user_id . ')' : 'Usuario eliminado (#' . $log->user_id . ')';
                                ?>
                            <?php else: ?>
                                <?php _e('Sistema', 'wp-extreme-customize'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td>
                            <?php if ($log->object_type && $log->object_id): ?>
                                <?php echo esc_html($log->object_type); ?> #<?php echo intval($log->object_id); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->ip_address); ?></td>
                        <td>
                            <?php 
                            $details = json_decode($log->details, true);
                            if ($details) {
                                echo '<pre style="margin: 0; font-size: 11px;">' . esc_html(wp_json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;"><?php _e('No hay registros de actividad.', 'wp-extreme-customize'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <?php
                $base_url = admin_url('admin.php?page=wpec-activity-log');
                $query_args = $_GET;
                unset($query_args['paged']);
                $base_url .= '&' . http_build_query($query_args);
                
                echo paginate_links([
                    'base' => $base_url . '&paged=%#%',
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                    'prev_text' => __('« Anterior', 'wp-extreme-customize'),
                    'next_text' => __('Siguiente »', 'wp-extreme-customize'),
                ]);
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Activity_Log();
