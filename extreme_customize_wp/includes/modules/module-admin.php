
<?php
/**
 * Módulo Admin Customizer - Personalización avanzada del panel
 * Version: 1.0.2 - Escape correcto de CSS/JS inline
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Admin_Customizer {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_admin_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Admin bar
        add_action('wp_before_admin_bar_render', [$this, 'customize_admin_bar']);
        
        // Menú
        add_action('admin_menu', [$this, 'customize_admin_menu']);
        
        // CSS/JS del admin
        add_action('admin_head', [$this, 'custom_admin_css']);
        add_action('admin_footer', [$this, 'custom_admin_js']);
        
        // Login
        add_action('login_enqueue_scripts', [$this, 'custom_login_css']);
        
        // Footer
        add_filter('admin_footer_text', [$this, 'custom_admin_footer']);
        
        // Help tabs
        add_action('load-index.php', [$this, 'add_custom_help']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_admin_customizer_menu']);
    }
    
    public function customize_admin_bar($wp_admin_bar) {
        if (!is_admin_bar_showing()) {
            return;
        }
        
        // Ocultar elementos
        $hidden = (array) $this->get_setting('hidden_bar_items', []);
        foreach ($hidden as $id) {
            $wp_admin_bar->remove_node($id);
        }
        
        // Añadir enlaces personalizados
        $links = (array) $this->get_setting('custom_links', []);
        foreach ($links as $link) {
            if (empty($link['id']) || empty($link['title']) || empty($link['url'])) {
                continue;
            }
            
            if (!current_user_can($link['capability'] ?? 'read')) {
                continue;
            }
            
            $wp_admin_bar->add_node([
                'id' => sanitize_key($link['id']),
                'title' => esc_html($link['title']),
                'href' => esc_url($link['url']),
                'parent' => sanitize_key($link['parent'] ?? false),
                'meta' => [
                    'target' => sanitize_text_field($link['target'] ?? '_self'),
                    'title' => esc_attr($link['title']),
                ],
            ]);
        }
        
        // Logo personalizado
        $logo = $this->get_setting('custom_logo', '');
        if (!empty($logo)) {
            $wp_admin_bar->add_node([
                'id' => 'wpec-logo',
                'title' => '<img src="' . esc_url($logo) . '" style="height:28px; margin-top:-5px;" alt="' . esc_attr(get_bloginfo('name')) . '">',
                'href' => admin_url(),
                'meta' => ['class' => 'wpec-custom-logo'],
            ]);
        }
    }
    
    public function customize_admin_menu() {
        // Ocultar elementos del menú
        $hidden = (array) $this->get_setting('hidden_menu_items', []);
        foreach ($hidden as $menu_slug) {
            remove_menu_page($menu_slug);
        }
        
        // Ocultar submenús
        $hidden_subs = (array) $this->get_setting('hidden_submenu_items', []);
        foreach ($hidden_subs as $parent_slug => $sub_slugs) {
            if (!is_array($sub_slugs)) {
                continue;
            }
            foreach ($sub_slugs as $sub_slug) {
                remove_submenu_page($parent_slug, $sub_slug);
            }
        }
    }
    
    public function custom_admin_css() {
        $custom_css = $this->get_setting('custom_css', '');
        
        if (!empty($custom_css)) {
            // Escapar y enviar CSS seguro
            echo '<style id="wpec-admin-css">' . "\n" . $custom_css . "\n" . '</style>' . "\n";
        }
    }
    
    public function custom_admin_js() {
        $custom_js = $this->get_setting('custom_js', '');
        
        if (!empty($custom_js)) {
            echo '<script id="wpec-admin-js">' . "\n" . $custom_js . "\n" . '</script>' . "\n";
        }
    }
    
    public function custom_login_css() {
        $login_css = $this->get_setting('login_custom_css', '');
        
        if (!empty($login_css)) {
            wp_add_inline_style('login', $login_css);
        }
    }
    
    public function custom_admin_footer($footer_text) {
        $custom_footer = $this->get_setting('admin_footer_text', '');
        
        if (!empty($custom_footer)) {
            return wp_kses_post($custom_footer);
        }
        
        return $footer_text;
    }
    
    public function add_custom_help() {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        $custom_help = (array) $this->get_setting('custom_help', []);
        
        foreach ($custom_help as $help) {
            if (empty($help['title']) || empty($help['content'])) {
                continue;
            }
            
            $screen->add_help_tab([
                'id' => sanitize_key($help['title']),
                'title' => sanitize_text_field($help['title']),
                'content' => wp_kses_post($help['content']),
            ]);
        }
    }
    
    public function add_admin_customizer_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Admin Customizer', 'wp-extreme-customize'),
            __('Admin Customizer', 'wp-extreme-customize'),
            'manage_options',
            'wpec-admin-customizer',
            [$this, 'render_admin_customizer_settings']
        );
    }
    
    public function render_admin_customizer_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Admin Customizer', 'wp-extreme-customize'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_admin'); ?>
                
                <div class="wpec-tabs">
                    <div class="wpec-tab-nav">
                        <a href="#wpec-tab-bar" class="wpec-tab-link active" data-tab="bar"><?php _e('Admin Bar', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-menu" class="wpec-tab-link" data-tab="menu"><?php _e('Menú', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-style" class="wpec-tab-link" data-tab="style"><?php _e('Estilos', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-footer" class="wpec-tab-link" data-tab="footer"><?php _e('Footer', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-help" class="wpec-tab-link" data-tab="help"><?php _e('Help', 'wp-extreme-customize'); ?></a>
                    </div>
                    
                    <div class="wpec-tab-content active" id="wpec-tab-bar">
                        <h2><?php _e('Admin Bar', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_custom_logo"><?php _e('Logo personalizado', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <input type="url" name="wpec_admin_settings[custom_logo]" id="wpec_custom_logo" value="<?php echo esc_attr($this->settings['custom_logo'] ?? ''); ?>" class="large-text">
                                    <?php if (!empty($this->settings['custom_logo'])): ?>
                                        <br><img src="<?php echo esc_url($this->settings['custom_logo']); ?>" style="max-height:40px; margin-top:5px;" alt="">
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Enlaces personalizados', 'wp-extreme-customize'); ?></h3>
                        <div id="wpec-custom-links">
                            <?php
                            $links = (array) ($this->settings['custom_links'] ?? []);
                            foreach ($links as $index => $link):
                            ?>
                            <div class="wpec-custom-link" style="border:1px solid #ddd; padding:15px; margin-bottom:10px; background:#f9f9f9;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <strong><?php echo esc_html($link['title'] ?? 'Enlace'); ?></strong>
                                    <button type="button" class="button button-small wpec-remove-link">X</button>
                                </div>
                                <table class="form-table" style="margin-bottom:0;">
                                    <tr>
                                        <th><label><?php _e('ID', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_settings[custom_links][<?php echo intval($index); ?>][id]" value="<?php echo esc_attr($link['id'] ?? ''); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_settings[custom_links][<?php echo intval($index); ?>][title]" value="<?php echo esc_attr($link['title'] ?? ''); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('URL', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="url" name="wpec_admin_settings[custom_links][<?php echo intval($index); ?>][url]" value="<?php echo esc_attr($link['url'] ?? ''); ?>" class="large-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Capacidad', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_settings[custom_links][<?php echo intval($index); ?>][capability]" value="<?php echo esc_attr($link['capability'] ?? 'read'); ?>" class="regular-text"></td>
                                    </tr>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button" id="wpec-add-link"><?php _e('Añadir enlace', 'wp-extreme-customize'); ?></button>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-menu">
                        <h2><?php _e('Menú Admin', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label><?php _e('Ocultar elementos del menú', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <?php
                                    $standard_menus = [
                                        'index.php' => 'Dashboard',
                                        'edit.php' => 'Entradas',
                                        'edit.php?post_type=page' => 'Páginas',
                                        'upload.php' => 'Medios',
                                        'edit-comments.php' => 'Comentarios',
                                        'themes.php' => 'Apariencia',
                                        'plugins.php' => 'Plugins',
                                        'users.php' => 'Usuarios',
                                        'tools.php' => 'Herramientas',
                                        'options-general.php' => 'Ajustes',
                                    ];
                                    $hidden = (array) $this->get_setting('hidden_menu_items', []);
                                    foreach ($standard_menus as $slug => $name):
                                    ?>
                                    <label style="display:block; margin:5px 0;">
                                        <input type="checkbox" name="wpec_admin_settings[hidden_menu_items][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $hidden, true)); ?>>
                                        <?php echo esc_html($name); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-style">
                        <h2><?php _e('CSS Personalizado', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_custom_css"><?php _e('CSS Admin', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_admin_settings[custom_css]" id="wpec_custom_css" rows="10" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea($this->settings['custom_css'] ?? ''); ?></textarea>
                                    <p class="description"><?php _e('CSS que se inyecta en todas las páginas del panel.', 'wp-extreme-customize'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_login_css"><?php _e('CSS Login', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_admin_settings[login_custom_css]" id="wpec_login_css" rows="8" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea($this->settings['login_custom_css'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-footer">
                        <h2><?php _e('Footer Personalizado', 'wp-extreme-customize'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_admin_footer_text"><?php _e('Texto del footer', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_admin_settings[admin_footer_text]" id="wpec_admin_footer_text" rows="4" class="large-text"><?php echo esc_textarea($this->settings['admin_footer_text'] ?? ''); ?></textarea>
                                    <p class="description"><?php _e('HTML permitido. Se muestra en el footer del panel.', 'wp-extreme-customize'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-help">
                        <h2><?php _e('Help Contextual', 'wp-extreme-customize'); ?></h2>
                        
                        <div id="wpec-custom-help">
                            <?php
                            $helps = (array) ($this->settings['custom_help'] ?? []);
                            foreach ($helps as $index => $help):
                            ?>
                            <div class="wpec-help-item" style="border:1px solid #ddd; padding:15px; margin-bottom:10px; background:#f9f9f9;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <strong><?php echo esc_html($help['title'] ?? 'Ayuda'); ?></strong>
                                    <button type="button" class="button button-small wpec-remove-help">X</button>
                                </div>
                                <table class="form-table" style="margin-bottom:0;">
                                    <tr>
                                        <th><label><?php _e('Título', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_settings[custom_help][<?php echo intval($index); ?>][title]" value="<?php echo esc_attr($help['title'] ?? ''); ?>" class="regular-text"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Contenido', 'wp-extreme-customize'); ?></label></th>
                                        <td><textarea name="wpec_admin_settings[custom_help][<?php echo intval($index); ?>][content]" rows="4" class="large-text"><?php echo esc_textarea($help['content'] ?? ''); ?></textarea></td>
                                    </tr>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button" id="wpec-add-help"><?php _e('Añadir ayuda', 'wp-extreme-customize'); ?></button>
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
                
                $('.color-picker').wpColorPicker();
                
                // Añadir enlace
                $('#wpec-add-link').on('click', function() {
                    var index = Date.now();
                    var html = '<div class="wpec-custom-link" style="border:1px solid #ddd; padding:15px; margin-bottom:10px; background:#f9f9f9;">' +
                        '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">' +
                        '<strong>Nuevo enlace</strong>' +
                        '<button type="button" class="button button-small wpec-remove-link">X</button>' +
                        '</div>' +
                        '<table class="form-table" style="margin-bottom:0;">' +
                        '<tr><th><label>ID</label></th><td><input type="text" name="wpec_admin_settings[custom_links][' + index + '][id]" class="regular-text"></td></tr>' +
                        '<tr><th><label>Título</label></th><td><input type="text" name="wpec_admin_settings[custom_links][' + index + '][title]" class="regular-text"></td></tr>' +
                        '<tr><th><label>URL</label></th><td><input type="url" name="wpec_admin_settings[custom_links][' + index + '][url]" class="large-text"></td></tr>' +
                        '<tr><th><label>Capacidad</label></th><td><input type="text" name="wpec_admin_settings[custom_links][' + index + '][capability]" value="read" class="regular-text"></td></tr>' +
                        '</table>' +
                    '</div>';
                    $('#wpec-custom-links').append(html);
                });
                
                $(document).on('click', '.wpec-remove-link, .wpec-remove-help', function() {
                    $(this).closest('.wpec-custom-link, .wpec-help-item').remove();
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

new WPEC_Admin_Customizer();
