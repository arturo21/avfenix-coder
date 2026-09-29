
<?php
/**
 * Módulo Plugin/Theme Manager - Ocultar/renombrar plugins, temas y actualizaciones
 * Version: 1.0.2 - Corrección de validaciones y seguridad
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Manager {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_manager_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Ocultar plugins del listado
        add_filter('all_plugins', [$this, 'hide_plugins']);
        add_filter('plugin_action_links', [$this, 'custom_plugin_actions'], 10, 4);
        
        // Ocultar temas
        add_filter('wp_prepare_themes_for_js', [$this, 'hide_themes']);
        add_filter('themes_api', [$this, 'custom_themes_api'], 10, 3);
        
        // Ocultar actualizaciones
        add_filter('site_transient_update_plugins', [$this, 'hide_plugin_updates']);
        add_filter('site_transient_update_themes', [$this, 'hide_theme_updates']);
        add_filter('pre_site_transient_update_core', [$this, 'hide_core_updates']);
        
        // Desactivar editores
        add_action('admin_init', [$this, 'disable_editors']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_manager_menu']);
    }
    
    public function hide_plugins($plugins) {
        $hidden = (array) $this->get_setting('hidden_plugins', []);
        
        foreach ($hidden as $plugin_file) {
            if (isset($plugins[$plugin_file])) {
                unset($plugins[$plugin_file]);
            }
        }
        
        return $plugins;
    }
    
    public function custom_plugin_actions($actions, $plugin_file, $plugin_data, $context) {
        $renamed = (array) $this->get_setting('renamed_plugins', []);
        
        // Renombrar plugins en la lista
        if (isset($renamed[$plugin_file])) {
            $plugin_data['Name'] = $renamed[$plugin_file];
        }
        
        return $actions;
    }
    
    public function hide_themes($themes) {
        $hidden = (array) $this->get_setting('hidden_themes', []);
        
        foreach ($hidden as $theme_slug) {
            if (isset($themes[$theme_slug])) {
                unset($themes[$theme_slug]);
            }
        }
        
        return $themes;
    }
    
    public function custom_themes_api($result, $action, $args) {
        // Si se solicita información de un tema oculto, devolver vacío
        $hidden = (array) $this->get_setting('hidden_themes', []);
        
        if (isset($args->slug) && in_array($args->slug, $hidden, true)) {
            return new WP_Error('theme_hidden', __('Este tema no está disponible.', 'wp-extreme-customize'));
        }
        
        return $result;
    }
    
    public function hide_plugin_updates($transient) {
        if (!isset($transient->response) || !is_array($transient->response)) {
            return $transient;
        }
        
        $hidden = (array) $this->get_setting('hide_plugin_updates', []);
        
        foreach ($hidden as $plugin_file) {
            if (isset($transient->response[$plugin_file])) {
                unset($transient->response[$plugin_file]);
            }
        }
        
        return $transient;
    }
    
    public function hide_theme_updates($transient) {
        if (!isset($transient->response) || !is_array($transient->response)) {
            return $transient;
        }
        
        $hidden = (array) $this->get_setting('hide_theme_updates', []);
        
        foreach ($hidden as $theme => $data) {
            if (isset($transient->response[$theme])) {
                unset($transient->response[$theme]);
            }
        }
        
        return $transient;
    }
    
    public function hide_core_updates($transient) {
        if (!$this->get_setting('hide_core_updates', false)) {
            return $transient;
        }
        
        // Retornar el último core check para evitar actualización
        if (is_object($transient)) {
            $transient->updates = [];
        }
        
        return $transient;
    }
    
    public function disable_editors() {
        if ($this->get_setting('disable_plugin_editor', true)) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }
        
        if ($this->get_setting('disable_theme_editor', true)) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }
    }
    
    public function add_manager_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Plugin/Theme Manager', 'wp-extreme-customize'),
            __('Manager', 'wp-extreme-customize'),
            'manage_options',
            'wpec-manager',
            [$this, 'render_manager_settings']
        );
    }
    
    public function render_manager_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Plugin/Theme Manager', 'wp-extreme-customize'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_manager'); ?>
                
                <div class="wpec-tabs">
                    <div class="wpec-tab-nav">
                        <a href="#wpec-tab-plugins" class="wpec-tab-link active" data-tab="plugins"><?php _e('Plugins', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-themes" class="wpec-tab-link" data-tab="themes"><?php _e('Temas', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-updates" class="wpec-tab-link" data-tab="updates"><?php _e('Actualizaciones', 'wp-extreme-customize'); ?></a>
                    </div>
                    
                    <div class="wpec-tab-content active" id="wpec-tab-plugins">
                        <h2><?php _e('Plugins Ocultos', 'wp-extreme-customize'); ?></h2>
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Archivo del plugin', 'wp-extreme-customize'); ?></th>
                                    <th><?php _e('Acción', 'wp-extreme-customize'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $hidden = (array) $this->get_setting('hidden_plugins', []);
                                foreach ($hidden as $plugin_file):
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($plugin_file); ?></code></td>
                                    <td>
                                        <button type="button" class="button button-small wpec-remove-hidden" data-type="hidden_plugins" data-value="<?php echo esc_attr($plugin_file); ?>"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <h3><?php _e('Añadir plugin oculto', 'wp-extreme-customize'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_new_hidden_plugin"><?php _e('Archivo del plugin', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <input type="text" id="wpec_new_hidden_plugin" class="regular-text" placeholder="plugin-folder/plugin-file.php">
                                    <button type="button" class="button button-small" id="wpec-add-hidden-plugin"><?php _e('Añadir', 'wp-extreme-customize'); ?></button>
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Renombrar Plugins', 'wp-extreme-customize'); ?></h3>
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Plugin', 'wp-extreme-customize'); ?></th>
                                    <th><?php _e('Nuevo nombre', 'wp-extreme-customize'); ?></th>
                                    <th><?php _e('Acción', 'wp-extreme-customize'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $renamed = (array) $this->get_setting('renamed_plugins', []);
                                foreach ($renamed as $plugin_file => $new_name):
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($plugin_file); ?></code></td>
                                    <td><strong><?php echo esc_html($new_name); ?></strong></td>
                                    <td>
                                        <button type="button" class="button button-small wpec-remove-renamed" data-value="<?php echo esc_attr($plugin_file); ?>"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-themes">
                        <h2><?php _e('Temas Ocultos', 'wp-extreme-customize'); ?></h2>
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Tema (slug)', 'wp-extreme-customize'); ?></th>
                                    <th><?php _e('Acción', 'wp-extreme-customize'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $hidden_themes = (array) $this->get_setting('hidden_themes', []);
                                foreach ($hidden_themes as $theme_slug):
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($theme_slug); ?></code></td>
                                    <td>
                                        <button type="button" class="button button-small wpec-remove-hidden-theme" data-value="<?php echo esc_attr($theme_slug); ?>"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-updates">
                        <h2><?php _e('Actualizaciones Ocultas', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label><?php _e('Ocultar actualizaciones de Core', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="checkbox" name="wpec_manager_settings[hide_core_updates]" value="yes" <?php checked($this->settings['hide_core_updates'] ?? '', 'yes'); ?>></td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Plugins sin actualización', 'wp-extreme-customize'); ?></h3>
                        <p class="description"><?php _e('Archivos de plugins a ocultar en las actualizaciones.', 'wp-extreme-customize'); ?></p>
                        <textarea name="wpec_manager_settings[hide_plugin_updates]" rows="5" class="large-text code"><?php echo esc_textarea(implode("\n", (array) ($this->settings['hide_plugin_updates'] ?? []))); ?></textarea>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                $('.wpec-tab-link').on('click', function(e) {
                    e.preventDefault();
                    var tab = $(this).data('tab');
                    $('.wpec-tab-link').removeClass('active');
                    $(this).addClass('active');
                    $('.wpec-tab-content').removeClass('active');
                    $('#wpec-tab-' + tab).addClass('active');
                });
                
                // Añadir plugin oculto
                $('#wpec-add-hidden-plugin').on('click', function() {
                    var plugin = $('#wpec_new_hidden_plugin').val().trim();
                    if (!plugin) {
                        alert('<?php _e('Introduce el archivo del plugin', 'wp-extreme-customize'); ?>');
                        return;
                    }
                    
                    // Añadir a la tabla de forma dinámica (se guardará al submit)
                    var row = '<tr><td><code>' + plugin.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code></td><td><button type="button" class="button button-small wpec-remove-hidden" data-type="hidden_plugins" data-value="' + plugin.replace(/"/g, '&quot;') + '">Eliminar</button></td></tr>';
                    $('.wpec-tab-content.active tbody').append(row);
                    $('#wpec_new_hidden_plugin').val('');
                });
                
                $(document).on('click', '.wpec-remove-hidden, .wpec-remove-hidden-theme', function() {
                    $(this).closest('tr').remove();
                });
                
                $(document).on('click', '.wpec-remove-renamed', function() {
                    $(this).closest('tr').remove();
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

new WPEC_Manager();
