
<?php
/**
 * Módulo Admin Bar Menus - Menús personalizados en la barra de administración
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Admin_Bar_Menus {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_admin_bar_menus', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_before_admin_bar_render', [$this, 'add_custom_admin_bar_items']);
        add_action('admin_menu', [$this, 'add_admin_bar_menus_menu']);
    }
    
    public function add_custom_admin_bar_items($wp_admin_bar) {
        // Menús principales personalizados
        $main_menus = $this->settings['main_menus'] ?? [];
        
        foreach ($main_menus as $index => $menu) {
            if (empty($menu['id']) || empty($menu['title'])) {
                continue;
            }
            
            $capability = $menu['capability'] ?? 'manage_options';
            if (!current_user_can($capability)) {
                continue;
            }
            
            $wp_admin_bar->add_node([
                'id' => 'wpec_custom_' . sanitize_key($menu['id']),
                'title' => $menu['title'],
                'href' => $menu['url'] ?? '#',
                'parent' => $menu['parent'] ?? false,
                'meta' => array_merge([
                    'target' => $menu['target'] ?? '_self',
                    'title' => $menu['title'],
                ], $menu['meta'] ?? []),
            ]);
        }
        
        // Submenús para menús existentes
        $submenus = $this->settings['submenus'] ?? [];
        
        foreach ($submenus as $index => $submenu) {
            if (empty($submenu['id']) || empty($submenu['title']) || empty($submenu['parent'])) {
                continue;
            }
            
            $capability = $submenu['capability'] ?? 'manage_options';
            if (!current_user_can($capability)) {
                continue;
            }
            
            // Verificar que el padre existe
            $parent_node = $wp_admin_bar->get_node($submenu['parent']);
            if (!$parent_node) {
                continue;
            }
            
            $wp_admin_bar->add_node([
                'id' => 'wpec_custom_' . sanitize_key($submenu['id']),
                'title' => $submenu['title'],
                'href' => $submenu['url'] ?? '#',
                'parent' => $submenu['parent'],
                'meta' => array_merge([
                    'target' => $submenu['target'] ?? '_self',
                    'title' => $submenu['title'],
                ], $submenu['meta'] ?? []),
            ]);
        }
        
        // Grupos personalizados
        $groups = $this->settings['groups'] ?? [];
        
        foreach ($groups as $index => $group) {
            if (empty($group['id']) || empty($group['title'])) {
                continue;
            }
            
            $capability = $group['capability'] ?? 'manage_options';
            if (!current_user_can($capability)) {
                continue;
            }
            
            $wp_admin_bar->add_group([
                'id' => 'wpec_group_' . sanitize_key($group['id']),
                'title' => $group['title'],
                'parent' => $group['parent'] ?? false,
                'meta' => $group['meta'] ?? [],
            ]);
        }
    }
    
    public function add_admin_bar_menus_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Menús Barra Admin', 'wp-extreme-customize'),
            __('Barra Admin', 'wp-extreme-customize'),
            'manage_options',
            'wpec-admin-bar-menus',
            [$this, 'render_admin_bar_menus_settings']
        );
    }
    
    public function render_admin_bar_menus_settings() {
        $main_menus = $this->settings['main_menus'] ?? [];
        $submenus = $this->settings['submenus'] ?? [];
        $groups = $this->settings['groups'] ?? [];
        ?>
        <div class="wrap">
            <h1><?php _e('Menús Personalizados en Barra de Admin', 'wp-extreme-customize'); ?></h1>
            <p><?php _e('Añade menús, submenús y grupos personalizados a la barra de administración de WordPress.', 'wp-extreme-customize'); ?></p>
            
            <div class="wpec-tabs">
                <div class="wpec-tab-nav">
                    <a href="#wpec-tab-main" class="wpec-tab-link active" data-tab="main"><?php _e('Menús Principales', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-sub" class="wpec-tab-link" data-tab="sub"><?php _e('Submenús', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-groups" class="wpec-tab-link" data-tab="groups"><?php _e('Grupos', 'wp-extreme-customize'); ?></a>
                </div>
                
                <div class="wpec-tab-content active" id="wpec-tab-main">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_admin_bar'); ?>
                        
                        <div id="wpec-main-menus-list">
                            <?php foreach ($main_menus as $index => $menu): ?>
                            <div class="wpec-bar-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3><?php _e('Menú Principal', 'wp-extreme-customize'); ?> #<?php echo $index + 1; ?></h3>
                                    <button type="button" class="button button-secondary wpec-remove-main"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>
                                </div>
                                
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php _e('ID (slug único)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[main_menus][<?php echo $index; ?>][id]" value="<?php echo esc_attr($menu['id']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[main_menus][<?php echo $index; ?>][title]" value="<?php echo esc_attr($menu['title']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('URL', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="url" name="wpec_admin_bar_menus[main_menus][<?php echo $index; ?>][url]" value="<?php echo esc_attr($menu['url'] ?? '#'); ?>" class="large-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Padre (opcional)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[main_menus][<?php echo $index; ?>][parent]" value="<?php echo esc_attr($menu['parent'] ?? ''); ?>" class="regular-text"><p class="description"><?php _e('ID del nodo padre (ej: site-name, wp-logo).', 'wp-extreme-customize'); ?></p></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[main_menus][<?php echo $index; ?>][capability]" value="<?php echo esc_attr($menu['capability'] ?? 'manage_options'); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Target', 'wp-extreme-customize'); ?></label></th>
                                        <td><select name="wpec_admin_bar_menus[main_menus][<?php echo $index; ?>][target]" class="medium-text"><option value="_self" <?php selected($menu['target'] ?? '', '_self'); ?>><?php _e('Misma ventana', 'wp-extreme-customize'); ?></option><option value="_blank" <?php selected($menu['target'] ?? '', '_blank'); ?>><?php _e('Nueva ventana', 'wp-extreme-customize'); ?></option></select></td>
                                    </tr>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button button-primary" id="wpec-add-main"><?php _e('Añadir Menú Principal', 'wp-extreme-customize'); ?></button>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-sub">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_admin_bar'); ?>
                        
                        <div id="wpec-submenus-list">
                            <?php foreach ($submenus as $index => $submenu): ?>
                            <div class="wpec-bar-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3><?php _e('Submenú', 'wp-extreme-customize'); ?> #<?php echo $index + 1; ?></h3>
                                    <button type="button" class="button button-secondary wpec-remove-sub"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>
                                </div>
                                
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php _e('ID (slug único)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[submenus][<?php echo $index; ?>][id]" value="<?php echo esc_attr($submenu['id']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[submenus][<?php echo $index; ?>][title]" value="<?php echo esc_attr($submenu['title']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Padre (Requerido)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[submenus][<?php echo $index; ?>][parent]" value="<?php echo esc_attr($submenu['parent']); ?>" class="regular-text" required><p class="description"><?php _e('ID del nodo padre existente (ej: wpec_custom_mi-menu, site-name, new-content).', 'wp-extreme-customize'); ?></p></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('URL', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="url" name="wpec_admin_bar_menus[submenus][<?php echo $index; ?>][url]" value="<?php echo esc_attr($submenu['url'] ?? '#'); ?>" class="large-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[submenus][<?php echo $index; ?>][capability]" value="<?php echo esc_attr($submenu['capability'] ?? 'manage_options'); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Target', 'wp-extreme-customize'); ?></label></th>
                                        <td><select name="wpec_admin_bar_menus[submenus][<?php echo $index; ?>][target]" class="medium-text"><option value="_self" <?php selected($submenu['target'] ?? '', '_self'); ?>><?php _e('Misma ventana', 'wp-extreme-customize'); ?></option><option value="_blank" <?php selected($submenu['target'] ?? '', '_blank'); ?>><?php _e('Nueva ventana', 'wp-extreme-customize'); ?></option></select></td>
                                    </tr>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button button-primary" id="wpec-add-sub"><?php _e('Añadir Submenú', 'wp-extreme-customize'); ?></button>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-groups">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_admin_bar'); ?>
                        
                        <div id="wpec-groups-list">
                            <?php foreach ($groups as $index => $group): ?>
                            <div class="wpec-bar-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3><?php _e('Grupo', 'wp-extreme-customize'); ?> #<?php echo $index + 1; ?></h3>
                                    <button type="button" class="button button-secondary wpec-remove-group"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>
                                </div>
                                
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php _e('ID (slug único)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[groups][<?php echo $index; ?>][id]" value="<?php echo esc_attr($group['id']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[groups][<?php echo $index; ?>][title]" value="<?php echo esc_attr($group['title']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Padre (opcional)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[groups][<?php echo $index; ?>][parent]" value="<?php echo esc_attr($group['parent'] ?? ''); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_bar_menus[groups][<?php echo $index; ?>][capability]" value="<?php echo esc_attr($group['capability'] ?? 'manage_options'); ?>" class="regular-text"></td>
                                    </tr>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button button-primary" id="wpec-add-group"><?php _e('Añadir Grupo', 'wp-extreme-customize'); ?></button>
                        
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
                
                // Helper para añadir items
                function addItem(listId, template) {
                    var index = Date.now();
                    $('#' + listId).append(template.replace(/__INDEX__/g, index));
                }
                
                // Main menus
                $('#wpec-add-main').on('click', function() {
                    var tpl = '<div class="wpec-bar-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
                        '<h3><?php _e('Menú Principal', 'wp-extreme-customize'); ?> #__INDEX__</h3>' +
                        '<button type="button" class="button button-secondary wpec-remove-main"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>' +
                        '</div>' +
                        '<table class="form-table">' +
                        '<tr><th><label><?php _e('ID', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[main_menus][__INDEX__][id]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[main_menus][__INDEX__][title]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('URL', 'wp-extreme-customize'); ?></label></th><td><input type="url" name="wpec_admin_bar_menus[main_menus][__INDEX__][url]" value="#" class="large-text"></td></tr>' +
                        '<tr><th><label><?php _e('Padre', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[main_menus][__INDEX__][parent]" class="regular-text"></td></tr>' +
                        '<tr><th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[main_menus][__INDEX__][capability]" value="manage_options" class="regular-text"></td></tr>' +
                        '<tr><th><label><?php _e('Target', 'wp-extreme-customize'); ?></label></th><td><select name="wpec_admin_bar_menus[main_menus][__INDEX__][target]" class="medium-text"><option value="_self"><?php _e('Misma ventana', 'wp-extreme-customize'); ?></option><option value="_blank"><?php _e('Nueva ventana', 'wp-extreme-customize'); ?></option></select></td></tr>' +
                        '</table>' +
                    '</div>';
                    addItem('wpec-main-menus-list', tpl);
                });
                
                $(document).on('click', '.wpec-remove-main', function() {
                    if (confirm('<?php _e('¿Eliminar este menú?', 'wp-extreme-customize'); ?>')) {
                        $(this).closest('.wpec-bar-item').remove();
                    }
                });
                
                // Submenus
                $('#wpec-add-sub').on('click', function() {
                    var tpl = '<div class="wpec-bar-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
                        '<h3><?php _e('Submenú', 'wp-extreme-customize'); ?> #__INDEX__</h3>' +
                        '<button type="button" class="button button-secondary wpec-remove-sub"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>' +
                        '</div>' +
                        '<table class="form-table">' +
                        '<tr><th><label><?php _e('ID', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[submenus][__INDEX__][id]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[submenus][__INDEX__][title]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Padre (Req)', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[submenus][__INDEX__][parent]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('URL', 'wp-extreme-customize'); ?></label></th><td><input type="url" name="wpec_admin_bar_menus[submenus][__INDEX__][url]" value="#" class="large-text"></td></tr>' +
                        '<tr><th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[submenus][__INDEX__][capability]" value="manage_options" class="regular-text"></td></tr>' +
                        '<tr><th><label><?php _e('Target', 'wp-extreme-customize'); ?></label></th><td><select name="wpec_admin_bar_menus[submenus][__INDEX__][target]" class="medium-text"><option value="_self"><?php _e('Misma ventana', 'wp-extreme-customize'); ?></option><option value="_blank"><?php _e('Nueva ventana', 'wp-extreme-customize'); ?></option></select></td></tr>' +
                        '</table>' +
                    '</div>';
                    addItem('wpec-submenus-list', tpl);
                });
                
                $(document).on('click', '.wpec-remove-sub', function() {
                    if (confirm('<?php _e('¿Eliminar este submenú?', 'wp-extreme-customize'); ?>')) {
                        $(this).closest('.wpec-bar-item').remove();
                    }
                });
                
                // Groups
                $('#wpec-add-group').on('click', function() {
                    var tpl = '<div class="wpec-bar-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
                        '<h3><?php _e('Grupo', 'wp-extreme-customize'); ?> #__INDEX__</h3>' +
                        '<button type="button" class="button button-secondary wpec-remove-group"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>' +
                        '</div>' +
                        '<table class="form-table">' +
                        '<tr><th><label><?php _e('ID', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[groups][__INDEX__][id]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[groups][__INDEX__][title]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Padre', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[groups][__INDEX__][parent]" class="regular-text"></td></tr>' +
                        '<tr><th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_bar_menus[groups][__INDEX__][capability]" value="manage_options" class="regular-text"></td></tr>' +
                        '</table>' +
                    '</div>';
                    addItem('wpec-groups-list', tpl);
                });
                
                $(document).on('click', '.wpec-remove-group', function() {
                    if (confirm('<?php _e('¿Eliminar este grupo?', 'wp-extreme-customize'); ?>')) {
                        $(this).closest('.wpec-bar-item').remove();
                    }
                });
            });
            </script>
        </div>
        <?php
    }
}

new WPEC_Admin_Bar_Menus();
