
<?php
/**
 * Módulo Dashboard Widgets - Widgets personalizados para el escritorio de WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Dashboard_Widgets {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_dashboard_widgets', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Eliminar widgets por defecto si está configurado
        add_action('wp_dashboard_setup', [$this, 'remove_default_widgets'], 99);
        
        // Añadir widgets personalizados
        add_action('wp_dashboard_setup', [$this, 'add_custom_widgets']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_dashboard_menu']);
    }
    
    public function remove_default_widgets() {
        $hidden = $this->settings['hidden_default_widgets'] ?? [];
        
        $default_widgets = [
            'dashboard_right_now' => 'De un vistazo',
            'dashboard_activity' => 'Actividad',
            'dashboard_quick_press' => 'Borrador rápido',
            'dashboard_primary' => 'Noticias de WordPress',
            'dashboard_secondary' => 'Populares',
            'dashboard_quick_draft' => 'Borrador rápido',
            'dashboard_recent_comments' => 'Comentarios recientes',
            'dashboard_incoming_links' => 'Enlaces entrantes',
            'dashboard_plugins' => 'Plugins',
        ];
        
        foreach ($hidden as $widget_id) {
            if (isset($default_widgets[$widget_id])) {
                remove_meta_box($widget_id, 'dashboard', 'normal');
                remove_meta_box($widget_id, 'dashboard', 'side');
            }
        }
        
        // Ocultar widgets de plugins específicos
        $hidden_plugin_widgets = $this->settings['hidden_plugin_widgets'] ?? [];
        foreach ($hidden_plugin_widgets as $widget_id) {
            remove_meta_box($widget_id, 'dashboard', 'normal');
            remove_meta_box($widget_id, 'dashboard', 'side');
        }
    }
    
    public function add_custom_widgets() {
        $widgets = $this->settings['custom_widgets'] ?? [];
        
        foreach ($widgets as $index => $widget) {
            if (empty($widget['id']) || empty($widget['title'])) {
                continue;
            }
            
            $callback = function() use ($widget) {
                echo $widget['content'];
            };
            
            wp_add_dashboard_widget(
                'wpec_custom_widget_' . sanitize_key($widget['id']),
                $widget['title'],
                $callback,
                $widget['control_callback'] ?? null
            );
        }
    }
    
    public function add_dashboard_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Widgets del Escritorio', 'wp-extreme-customize'),
            __('Widgets Escritorio', 'wp-extreme-customize'),
            'manage_options',
            'wpec-dashboard-widgets',
            [$this, 'render_dashboard_settings']
        );
    }
    
    public function render_dashboard_settings() {
        $hidden_default = $this->settings['hidden_default_widgets'] ?? [];
        $hidden_plugin = $this->settings['hidden_plugin_widgets'] ?? [];
        $custom_widgets = $this->settings['custom_widgets'] ?? [];
        ?>
        <div class="wrap">
            <h1><?php _e('Widgets del Escritorio', 'wp-extreme-customize'); ?></h1>
            
            <div class="wpec-tabs">
                <div class="wpec-tab-nav">
                    <a href="#wpec-tab-hide-default" class="wpec-tab-link active" data-tab="hide-default"><?php _e('Ocultar Por Defecto', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-hide-plugin" class="wpec-tab-link" data-tab="hide-plugin"><?php _e('Ocultar de Plugins', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-custom" class="wpec-tab-link" data-tab="custom"><?php _e('Widgets Personalizados', 'wp-extreme-customize'); ?></a>
                </div>
                
                <div class="wpec-tab-content active" id="wpec-tab-hide-default">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_dashboard'); ?>
                        
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Widget', 'wp-extreme-customize'); ?></th>
                                    <th><?php _e('Ocultar', 'wp-extreme-customize'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $default_widgets = [
                                    'dashboard_right_now' => 'De un vistazo (Right Now)',
                                    'dashboard_activity' => 'Actividad',
                                    'dashboard_quick_press' => 'Borrador rápido',
                                    'dashboard_primary' => 'Noticias de WordPress',
                                    'dashboard_secondary' => 'Populares',
                                ];
                                foreach ($default_widgets as $id => $name):
                                ?>
                                <tr>
                                    <td><?php echo $name; ?></td>
                                    <td>
                                        <input type="checkbox" name="wpec_dashboard_widgets[hidden_default_widgets][]" value="<?php echo $id; ?>" <?php checked(in_array($id, $hidden_default)); ?>>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-hide-plugin">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_dashboard'); ?>
                        
                        <p><?php _e('Ingresa los IDs de los widgets de plugins que quieres ocultar (separados por comas).', 'wp-extreme-customize'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_hidden_plugin_widgets"><?php _e('IDs de Widgets a Ocultar', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <input type="text" name="wpec_dashboard_widgets[hidden_plugin_widgets]" value="<?php echo esc_attr(implode(', ', $hidden_plugin)); ?>" class="large-text">
                                    <p class="description"><?php _e('Ejemplo: yoast_db_widget, wpseo-dashboard-widget, woocommerce_dashboard_recent_reviews', 'wp-extreme-customize'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-custom">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_dashboard'); ?>
                        
                        <h2><?php _e('Crear Widgets Personalizados', 'wp-extreme-customize'); ?></p>
                        <p><?php _e('Añade widgets personalizados con HTML, shortcodes o PHP (usando shortcodes).', 'wp-extreme-customize'); ?></p>
                        
                        <div id="wpec-custom-widgets-list">
                            <?php foreach ($custom_widgets as $index => $widget): ?>
                            <div class="wpec-widget-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">
                                <h3><?php _e('Widget Personalizado', 'wp-extreme-customize'); ?> #<?php echo $index + 1; ?></h3>
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php _e('ID (slug único)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_dashboard_widgets[custom_widgets][<?php echo $index; ?>][id]" value="<?php echo esc_attr($widget['id']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_dashboard_widgets[custom_widgets][<?php echo $index; ?>][title]" value="<?php echo esc_attr($widget['title']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Contenido (HTML/Shortcodes)', 'wp-extreme-customize'); ?></label></th>
                                        <td>
                                            <textarea name="wpec_dashboard_widgets[custom_widgets][<?php echo $index; ?>][content]" rows="10" class="large-text code"><?php echo esc_textarea($widget['content']); ?></textarea>
                                            <p class="description"><?php _e('Puedes usar HTML y shortcodes. Ejemplo: [wpec_stats]', 'wp-extreme-customize'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Posición', 'wp-extreme-customize'); ?></label></th>
                                        <td>
                                            <select name="wpec_dashboard_widgets[custom_widgets][<?php echo $index; ?>][position]" class="medium-text">
                                                <option value="normal" <?php selected($widget['position'] ?? '', 'normal'); ?>><?php _e('Normal (Centro)', 'wp-extreme-customize'); ?></option>
                                                <option value="side" <?php selected($widget['position'] ?? '', 'side'); ?>><?php _e('Lateral (Sidebar)', 'wp-extreme-customize'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Prioridad', 'wp-extreme-customize'); ?></label></th>
                                        <td>
                                            <select name="wpec_dashboard_widgets[custom_widgets][<?php echo $index; ?>][priority]" class="medium-text">
                                                <option value="high" <?php selected($widget['priority'] ?? '', 'high'); ?>><?php _e('Alta', 'wp-extreme-customize'); ?></option>
                                                <option value="core" <?php selected($widget['priority'] ?? '', 'core'); ?>><?php _e('Normal', 'wp-extreme-customize'); ?></option>
                                                <option value="low" <?php selected($widget['priority'] ?? '', 'low'); ?>><?php _e('Baja', 'wp-extreme-customize'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                <button type="button" class="button button-secondary wpec-remove-widget"><?php _e('Eliminar Widget', 'wp-extreme-customize'); ?></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button button-primary" id="wpec-add-widget"><?php _e('Añadir Widget', 'wp-extreme-customize'); ?></button>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Tabs
                $('.wpec-tab-link').on('click', function(e) {
                    e.preventDefault();
                    var tab = $(this).data('tab');
                    $('.wpec-tab-link').removeClass('active');
                    $(this).addClass('active');
                    $('.wpec-tab-content').removeClass('active');
                    $('#wpec-tab-' + tab).addClass('active');
                });
                
                // Añadir widget
                $('#wpec-add-widget').on('click', function() {
                    var index = Date.now();
                    var widget = '<div class="wpec-widget-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">' +
                        '<h3><?php _e('Widget Personalizado', 'wp-extreme-customize'); ?> #' + (parseInt(index/10000000000) + 1) + '</h3>' +
                        '<table class="form-table">' +
                        '<tr><th><label><?php _e('ID (slug único)', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_dashboard_widgets[custom_widgets][' + index + '][id]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_dashboard_widgets[custom_widgets][' + index + '][title]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Contenido (HTML/Shortcodes)', 'wp-extreme-customize'); ?></label></th><td><textarea name="wpec_dashboard_widgets[custom_widgets][' + index + '][content]" rows="10" class="large-text code"></textarea><p class="description"><?php _e('Puedes usar HTML y shortcodes.', 'wp-extreme-customize'); ?></p></td></tr>' +
                        '<tr><th><label><?php _e('Posición', 'wp-extreme-customize'); ?></label></th><td><select name="wpec_dashboard_widgets[custom_widgets][' + index + '][position]" class="medium-text"><option value="normal"><?php _e('Normal', 'wp-extreme-customize'); ?></option><option value="side"><?php _e('Lateral', 'wp-extreme-customize'); ?></option></select></td></tr>' +
                        '<tr><th><label><?php _e('Prioridad', 'wp-extreme-customize'); ?></label></th><td><select name="wpec_dashboard_widgets[custom_widgets][' + index + '][priority]" class="medium-text"><option value="high"><?php _e('Alta', 'wp-extreme-customize'); ?></option><option value="core"><?php _e('Normal', 'wp-extreme-customize'); ?></option><option value="low"><?php _e('Baja', 'wp-extreme-customize'); ?></option></select></td></tr>' +
                        '</table>' +
                        '<button type="button" class="button button-secondary wpec-remove-widget"><?php _e('Eliminar Widget', 'wp-extreme-customize'); ?></button>' +
                    '</div>';
                    $('#wpec-custom-widgets-list').append(widget);
                });
                
                // Eliminar widget
                $(document).on('click', '.wpec-remove-widget', function() {
                    if (confirm('<?php _e('¿Eliminar este widget?', 'wp-extreme-customize'); ?>')) {
                        $(this).closest('.wpec-widget-item').remove();
                    }
                });
            });
            </script>
        </div>
        <?php
    }
}

new WPEC_Dashboard_Widgets();
