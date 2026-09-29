
<?php
/**
 * Módulo Admin Menu Items - Crear y gestionar elementos de menú personalizados
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Admin_Menu_Items {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_admin_menu_items', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Añadir menús personalizados
        add_action('admin_menu', [$this, 'add_custom_menus'], 999);
        
        // Procesar páginas de menús personalizados
        add_action('admin_init', [$this, 'handle_custom_menu_pages']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_admin_menu_items_menu']);
    }
    
    public function add_custom_menus() {
        $menus = $this->settings['custom_menus'] ?? [];
        
        foreach ($menus as $index => $menu) {
            if (empty($menu['title']) || empty($menu['slug'])) {
                continue;
            }
            
            // Verificar capacidad
            $capability = $menu['capability'] ?? 'manage_options';
            if (!current_user_can($capability)) {
                continue;
            }
            
            $position = isset($menu['position']) ? intval($menu['position']) : null;
            $icon = $menu['icon'] ?? 'dashicons-admin-generic';
            
            if ($menu['type'] === 'submenu' && !empty($menu['parent'])) {
                // Es un submenú
                $parent = $menu['parent'];
                $function = function() use ($menu) {
                    echo $menu['content'] ?? '<p>Contenido del submenú</p>';
                };
                
                add_submenu_page($parent, $menu['title'], $menu['title'], $capability, $menu['slug'], $function, $position);
            } else {
                // Es un menú principal
                $function = function() use ($menu) {
                    echo $menu['content'] ?? '<p>Contenido del menú</p>';
                };
                
                add_menu_page($menu['title'], $menu['title'], $capability, $menu['slug'], $function, $icon, $position);
            }
        }
    }
    
    public function handle_custom_menu_pages() {
        $menus = $this->settings['custom_menus'] ?? [];
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        
        foreach ($menus as $menu) {
            if ($menu['slug'] === $current_page && !empty($menu['handler'])) {
                // Ejecutar handler personalizado si existe
                if (is_callable($menu['handler'])) {
                    call_user_func($menu['handler']);
                }
            }
        }
    }
    
    public function add_admin_menu_items_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Elementos de Menú Admin', 'wp-extreme-customize'),
            __('Menú Personalizado', 'wp-extreme-customize'),
            'manage_options',
            'wpec-admin-menu-items',
            [$this, 'render_admin_menu_items_settings']
        );
    }
    
    public function render_admin_menu_items_settings() {
        $custom_menus = $this->settings['custom_menus'] ?? [];
        ?>
        <div class="wrap">
            <h1><?php _e('Elementos de Menú Personalizados', 'wp-extreme-customize'); ?></h1>
            <p><?php _e('Crea menús y submenús personalizados en el panel de administración.', 'wp-extreme-customize'); ?></p>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_admin_menu'); ?>
                
                <div id="wpec-custom-menus-list">
                    <?php foreach ($custom_menus as $index => $menu): ?>
                    <div class="wpec-menu-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3><?php _e('Menú Personalizado', 'wp-extreme-customize'); ?> #<?php echo $index + 1; ?></h3>
                            <button type="button" class="button button-secondary wpec-remove-menu"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Tipo', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <select name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][type]" class="medium-text wpec-menu-type">
                                        <option value="menu" <?php selected($menu['type'] ?? '', 'menu'); ?>><?php _e('Menú Principal', 'wp-extreme-customize'); ?></option>
                                        <option value="submenu" <?php selected($menu['type'] ?? '', 'submenu'); ?>><?php _e('Submenú', 'wp-extreme-customize'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr class="wpec-parent-row" style="<?php echo ($menu['type'] ?? '') !== 'submenu' ? 'display: none;' : ''; ?>">
                                <th><label><?php _e('Menú Padre', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <select name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][parent]" class="medium-text">
                                        <option value=""><?php _e('Seleccionar...', 'wp-extreme-customize'); ?></option>
                                        <option value="dashboard" <?php selected($menu['parent'] ?? '', 'dashboard'); ?>><?php _e('Dashboard', 'wp-extreme-customize'); ?></option>
                                        <option value="edit.php" <?php selected($menu['parent'] ?? '', 'edit.php'); ?>><?php _e('Entradas', 'wp-extreme-customize'); ?></option>
                                        <option value="edit.php?post_type=page" <?php selected($menu['parent'] ?? '', 'edit.php?post_type=page'); ?>><?php _e('Páginas', 'wp-extreme-customize'); ?></option>
                                        <option value="themes.php" <?php selected($menu['parent'] ?? '', 'themes.php'); ?>><?php _e('Apariencia', 'wp-extreme-customize'); ?></option>
                                        <option value="plugins.php" <?php selected($menu['parent'] ?? '', 'plugins.php'); ?>><?php _e('Plugins', 'wp-extreme-customize'); ?></option>
                                        <option value="users.php" <?php selected($menu['parent'] ?? '', 'users.php'); ?>><?php _e('Usuarios', 'wp-extreme-customize'); ?></option>
                                        <option value="tools.php" <?php selected($menu['parent'] ?? '', 'tools.php'); ?>><?php _e('Herramientas', 'wp-extreme-customize'); ?></option>
                                        <option value="options-general.php" <?php selected($menu['parent'] ?? '', 'options-general.php'); ?>><?php _e('Ajustes', 'wp-extreme-customize'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label><?php _e('Título del Menú', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][title]" value="<?php echo esc_attr($menu['title']); ?>" class="regular-text" required></td>
                            </tr>
                            
                            <tr>
                                <th><label><?php _e('Slug (URL)', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][slug]" value="<?php echo esc_attr($menu['slug']); ?>" class="regular-text" required><p class="description"><?php _e('Solo letras, números, guiones y guiones bajos.', 'wp-extreme-customize'); ?></p></td>
                            </tr>
                            
                            <tr class="wpec-icon-row" style="<?php echo ($menu['type'] ?? '') === 'submenu' ? 'display: none;' : ''; ?>">
                                <th><label><?php _e('Icono (Dashicon)', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][icon]" value="<?php echo esc_attr($menu['icon'] ?? 'dashicons-admin-generic'); ?>" class="regular-text"><p class="description"><?php _e('Ejemplo: dashicons-admin-site, dashicons-star-filled, etc.', 'wp-extreme-customize'); ?></p></td>
                            </tr>
                            
                            <tr>
                                <th><label><?php _e('Capacidad Requerida', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][capability]" value="<?php echo esc_attr($menu['capability'] ?? 'manage_options'); ?>" class="regular-text"><p class="description"><?php _e('Capacidad de WordPress requerida para ver el menú.', 'wp-extreme-customize'); ?></p></td>
                            </tr>
                            
                            <tr>
                                <th><label><?php _e('Posición', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="number" name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][position]" value="<?php echo esc_attr($menu['position'] ?? ''); ?>" class="small-text"><p class="description"><?php _e('Posición en el menú (dejar vacío para automático).', 'wp-extreme-customize'); ?></p></td>
                            </tr>
                            
                            <tr>
                                <th><label><?php _e('Contenido HTML', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_admin_menu_items[custom_menus][<?php echo $index; ?>][content]" rows="8" class="large-text code"><?php echo esc_textarea($menu['content'] ?? '<p>Contenido de la página</p>'); ?></textarea>
                                    <p class="description"><?php _e('HTML que se mostrará en la página. Puedes usar shortcodes.', 'wp-extreme-customize'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" class="button button-primary" id="wpec-add-menu"><?php _e('Añadir Menú', 'wp-extreme-customize'); ?></button>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                // Mostrar/ocultar campos según tipo
                function toggleMenuFields($select) {
                    var type = $select.val();
                    var $row = $select.closest('tr').closest('.wpec-menu-item');
                    $row.find('.wpec-parent-row').toggle(type === 'submenu');
                    $row.find('.wpec-icon-row').toggle(type !== 'submenu');
                }
                
                $('.wpec-menu-type').each(function() {
                    toggleMenuFields($(this));
                }).on('change', function() {
                    toggleMenuFields($(this));
                });
                
                // Añadir menú
                $('#wpec-add-menu').on('click', function() {
                    var index = Date.now();
                    var menu = '<div class="wpec-menu-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">' +
                        '<h3><?php _e('Menú Personalizado', 'wp-extreme-customize'); ?> #' + (parseInt(index/10000000000) + 1) + '</h3>' +
                        '<button type="button" class="button button-secondary wpec-remove-menu"><?php _e('Eliminar', 'wp-extreme-customize'); ?></button>' +
                        '</div>' +
                        '<table class="form-table">' +
                        '<tr><th><label><?php _e('Tipo', 'wp-extreme-customize'); ?></label></th><td><select name="wpec_admin_menu_items[custom_menus][' + index + '][type]" class="medium-text wpec-menu-type"><option value="menu"><?php _e('Menú Principal', 'wp-extreme-customize'); ?></option><option value="submenu"><?php _e('Submenú', 'wp-extreme-customize'); ?></option></select></td></tr>' +
                        '<tr class="wpec-parent-row" style="display: none;"><th><label><?php _e('Menú Padre', 'wp-extreme-customize'); ?></label></th><td><select name="wpec_admin_menu_items[custom_menus][' + index + '][parent]" class="medium-text"><option value=""><?php _e('Seleccionar...', 'wp-extreme-customize'); ?></option><option value="dashboard"><?php _e('Dashboard', 'wp-extreme-customize'); ?></option><option value="edit.php"><?php _e('Entradas', 'wp-extreme-customize'); ?></option><option value="edit.php?post_type=page"><?php _e('Páginas', 'wp-extreme-customize'); ?></option><option value="themes.php"><?php _e('Apariencia', 'wp-extreme-customize'); ?></option><option value="plugins.php"><?php _e('Plugins', 'wp-extreme-customize'); ?></option><option value="users.php"><?php _e('Usuarios', 'wp-extreme-customize'); ?></option><option value="tools.php"><?php _e('Herramientas', 'wp-extreme-customize'); ?></option><option value="options-general.php"><?php _e('Ajustes', 'wp-extreme-customize'); ?></option></select></td></tr>' +
                        '<tr><th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_menu_items[custom_menus][' + index + '][title]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Slug', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_menu_items[custom_menus][' + index + '][slug]" class="regular-text" required></td></tr>' +
                        '<tr class="wpec-icon-row"><th><label><?php _e('Icono', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_menu_items[custom_menus][' + index + '][icon]" value="dashicons-admin-generic" class="regular-text"></td></tr>' +
                        '<tr><th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_menu_items[custom_menus][' + index + '][capability]" value="manage_options" class="regular-text"></td></tr>' +
                        '<tr><th><label><?php _e('Posición', 'wp-extreme-customize'); ?></label></th><td><input type="number" name="wpec_admin_menu_items[custom_menus][' + index + '][position]" class="small-text"></td></tr>' +
                        '<tr><th><label><?php _e('Contenido', 'wp-extreme-customize'); ?></label></th><td><textarea name="wpec_admin_menu_items[custom_menus][' + index + '][content]" rows="8" class="large-text code"><p><?php _e('Contenido de la página', 'wp-extreme-customize'); ?></p></textarea></td></tr>' +
                        '</table>' +
                    '</div>';
                    $('#wpec-custom-menus-list').append(menu);
                });
                
                // Eliminar menú
                $(document).on('click', '.wpec-remove-menu', function() {
                    if (confirm('<?php _e('¿Eliminar este menú?', 'wp-extreme-customize'); ?>')) {
                        $(this).closest('.wpec-menu-item').remove();
                    }
                });
            });
            </script>
        </div>
        <?php
    }
}

new WPEC_Admin_Menu_Items();
