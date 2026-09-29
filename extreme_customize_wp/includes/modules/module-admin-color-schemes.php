
<?php
/**
 * Módulo Admin Color Schemes - Esquemas de color personalizados para el admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Admin_Color_Schemes {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_admin_color_schemes', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Añadir esquemas de color personalizados
        add_filter('admin_color_scheme_picker', [$this, 'add_custom_color_schemes']);
        
        // Estilos personalizados basados en esquema
        add_action('admin_head', [$this, 'apply_custom_colors']);
        add_action('login_head', [$this, 'apply_login_custom_colors']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_color_schemes_menu']);
    }
    
    public function add_custom_color_schemes($schemes) {
        $custom_schemes = $this->settings['schemes'] ?? [];
        
        foreach ($custom_schemes as $scheme) {
            if (!empty($scheme['id']) && !empty($scheme['colors'])) {
                $schemes[$scheme['id']] = [
                    'name' => $scheme['name'],
                    'colors' => $scheme['colors'],
                    'url' => '',
                    'icon_colors' => [
                        'base' => $scheme['colors']['icon_base'] ?? '#999',
                        'focus' => $scheme['colors']['icon_focus'] ?? $scheme['colors']['base'] ?? '#0073aa',
                        'current' => $scheme['colors']['icon_current'] ?? '#fff',
                    ],
                ];
            }
        }
        
        return $schemes;
    }
    
    public function apply_custom_colors() {
        $active_scheme = get_user_option('admin_color');
        $custom_schemes = $this->settings['schemes'] ?? [];
        
        foreach ($custom_schemes as $scheme) {
            if ($scheme['id'] === $active_scheme && !empty($scheme['custom_css'])) {
                echo '<style>' . $scheme['custom_css'] . '</style>';
                break;
            }
        }
        
        // Aplicar colores personalizados globales
        $global_colors = $this->settings['global_colors'] ?? [];
        if (!empty($global_colors)) {
            $css = $this->generate_color_css($global_colors);
            echo '<style>' . $css . '</style>';
        }
    }
    
    public function apply_login_custom_colors() {
        $login_colors = $this->settings['login_colors'] ?? [];
        if (!empty($login_colors)) {
            $css = $this->generate_login_css($login_colors);
            echo '<style>' . $css . '</style>';
        }
    }
    
    private function generate_color_css($colors) {
        $css = ':root {';
        
        $color_map = [
            'base' => '--wp-admin-theme-color',
            'focus' => '--wp-admin-theme-color-focus',
            'current' => '--wp-admin-theme-color-current',
            'text' => '--wp-admin-text-color',
            'background' => '--wp-admin-background-color',
            'submenu_bg' => '--wp-admin-submenu-bg',
            'menu_text' => '--wp-admin-menu-text-color',
            'menu_hover' => '--wp-admin-menu-hover-color',
        ];
        
        foreach ($color_map as $key => $var) {
            if (!empty($colors[$key])) {
                $css .= $var . ':' . $colors[$key] . ';';
            }
        }
        
        $css .= '}';
        
        // Aplicar a elementos específicos
        $css .= '
            #wpadminbar { background-color: ' . ($colors['base'] ?? '#1d2327') . '; }
            #adminmenu { background-color: ' . ($colors['submenu_bg'] ?? '#1d2327') . '; }
            #adminmenu .wp-menu-name { color: ' . ($colors['menu_text'] ?? '#ccc') . '; }
            #adminmenu li.menu-top:hover > .wp-menu-name,
            #adminmenu li.wp-has-current-submenu > .wp-menu-name { color: ' . ($colors['menu_hover'] ?? '#fff') . '; }
            .wp-core-ui .button-primary { background-color: ' . ($colors['base'] ?? '#0073aa') . '; border-color: ' . ($colors['base'] ?? '#0073aa') . '; }
            .wp-core-ui .button-primary:hover { background-color: ' . ($colors['focus'] ?? '#005a87') . '; border-color: ' . ($colors['focus'] ?? '#005a87') . '; }
        ';
        
        return $css;
    }
    
    private function generate_login_css($colors) {
        $css = '';
        
        if (!empty($colors['bg_color'])) {
            $css .= 'body.login { background-color: ' . $colors['bg_color'] . '; }';
        }
        if (!empty($colors['form_bg'])) {
            $css .= '#login { background-color: ' . $colors['form_bg'] . '; }';
        }
        if (!empty($colors['button_bg'])) {
            $css .= '.wp-core-ui .button-primary { background-color: ' . $colors['button_bg'] . '; border-color: ' . $colors['button_bg'] . '; }';
            $css .= '.wp-core-ui .button-primary:hover { background-color: ' . ($colors['button_hover'] ?? $colors['button_bg']) . '; border-color: ' . ($colors['button_hover'] ?? $colors['button_bg']) . '; }';
        }
        if (!empty($colors['link_color'])) {
            $css .= '#login a { color: ' . $colors['link_color'] . '; }';
            $css .= '#login a:hover { color: ' . $colors['link_hover'] ?? $colors['link_color'] . '; }';
        }
        if (!empty($colors['logo_url'])) {
            $css .= '#login h1 a { background-image: url(' . $colors['logo_url'] . '); background-size: contain; width: 300px; height: 100px; }';
        }
        
        return $css;
    }
    
    public function add_color_schemes_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Esquemas de Color Admin', 'wp-extreme-customize'),
            __('Esquemas de Color', 'wp-extreme-customize'),
            'manage_options',
            'wpec-admin-colors',
            [$this, 'render_color_schemes_settings']
        );
    }
    
    public function render_color_schemes_settings() {
        $schemes = $this->settings['schemes'] ?? [];
        $global_colors = $this->settings['global_colors'] ?? [];
        $login_colors = $this->settings['login_colors'] ?? [];
        ?>
        <div class="wrap">
            <h1><?php _e('Esquemas de Color Admin', 'wp-extreme-customize'); ?></h1>
            
            <div class="wpec-tabs">
                <div class="wpec-tab-nav">
                    <a href="#wpec-tab-global" class="wpec-tab-link active" data-tab="global"><?php _e('Colores Globales', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-login" class="wpec-tab-link" data-tab="login"><?php _e('Login', 'wp-extreme-customize'); ?></a>
                    <a href="#wpec-tab-schemes" class="wpec-tab-link" data-tab="schemes"><?php _e('Esquemas Personalizados', 'wp-extreme-customize'); ?></a>
                </div>
                
                <div class="wpec-tab-content active" id="wpec-tab-global">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_admin_colors'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Color Base (Primario)', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][base]" value="<?php echo esc_attr($global_colors['base'] ?? '#0073aa'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Focus', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][focus]" value="<?php echo esc_attr($global_colors['focus'] ?? '#005a87'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Current/Active', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][current]" value="<?php echo esc_attr($global_colors['current'] ?? '#fff'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color de Texto', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][text]" value="<?php echo esc_attr($global_colors['text'] ?? '#1d2327'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Fondo Admin', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][background]" value="<?php echo esc_attr($global_colors['background'] ?? '#fafafa'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Fondo Submenú', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][submenu_bg]" value="<?php echo esc_attr($global_colors['submenu_bg'] ?? '#fff'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Texto Menú', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][menu_text]" value="<?php echo esc_attr($global_colors['menu_text'] ?? '#1d2327'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Menú Hover', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[global_colors][menu_hover]" value="<?php echo esc_attr($global_colors['menu_hover'] ?? '#0073aa'); ?>" class="color-picker"></td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-login">
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_admin_colors'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Color de Fondo', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[login_colors][bg_color]" value="<?php echo esc_attr($login_colors['bg_color'] ?? '#f0f0f0'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Fondo Formulario', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[login_colors][form_bg]" value="<?php echo esc_attr($login_colors['form_bg'] ?? '#fff'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Botón', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[login_colors][button_bg]" value="<?php echo esc_attr($login_colors['button_bg'] ?? '#0073aa'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Botón Hover', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[login_colors][button_hover]" value="<?php echo esc_attr($login_colors['button_hover'] ?? '#005a87'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Enlaces', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[login_colors][link_color]" value="<?php echo esc_attr($login_colors['link_color'] ?? '#0073aa'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Color Enlaces Hover', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_admin_color_schemes[login_colors][link_hover]" value="<?php echo esc_attr($login_colors['link_hover'] ?? '#005a87'); ?>" class="color-picker"></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('URL Logo', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="url" name="wpec_admin_color_schemes[login_colors][logo_url]" value="<?php echo esc_attr($login_colors['logo_url'] ?? ''); ?>" class="large-text"></td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="wpec-tab-content" id="wpec-tab-schemes">
                    <h2><?php _e('Crear Esquemas de Color Personalizados', 'wp-extreme-customize'); ?></h2>
                    <p><?php _e('Estos esquemas aparecerán en el perfil de usuario para que cada usuario elija su preferido.', 'wp-extreme-customize'); ?></p>
                    
                    <form method="post" action="options.php">
                        <?php settings_fields('wpec_admin_colors'); ?>
                        
                        <div id="wpec-schemes-list">
                            <?php foreach ($schemes as $index => $scheme): ?>
                            <div class="wpec-scheme-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">
                                <h3><?php _e('Esquema', 'wp-extreme-customize'); ?> #<?php echo $index + 1; ?></h3>
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php _e('ID (slug único)', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][id]" value="<?php echo esc_attr($scheme['id']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Nombre', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][name]" value="<?php echo esc_attr($scheme['name']); ?>" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Color Base', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][colors][base]" value="<?php echo esc_attr($scheme['colors']['base'] ?? ''); ?>" class="color-picker"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Color Focus', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][colors][focus]" value="<?php echo esc_attr($scheme['colors']['focus'] ?? ''); ?>" class="color-picker"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Color Current', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][colors][current]" value="<?php echo esc_attr($scheme['colors']['current'] ?? ''); ?>" class="color-picker"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Icon Base', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][colors][icon_base]" value="<?php echo esc_attr($scheme['colors']['icon_base'] ?? ''); ?>" class="color-picker"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Icon Focus', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][colors][icon_focus]" value="<?php echo esc_attr($scheme['colors']['icon_focus'] ?? ''); ?>" class="color-picker"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('Icon Current', 'wp-extreme-customize'); ?></label></th>
                                        <td><input type="text" name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][colors][icon_current]" value="<?php echo esc_attr($scheme['colors']['icon_current'] ?? ''); ?>" class="color-picker"></td>
                                    </tr>
                                    <tr>
                                        <th><label><?php _e('CSS Personalizado', 'wp-extreme-customize'); ?></label></th>
                                        <td><textarea name="wpec_admin_color_schemes[schemes][<?php echo $index; ?>][custom_css]" rows="5" class="large-text code"><?php echo esc_textarea($scheme['custom_css'] ?? ''); ?></textarea></td>
                                    </tr>
                                </table>
                                <button type="button" class="button button-secondary wpec-remove-scheme"><?php _e('Eliminar Esquema', 'wp-extreme-customize'); ?></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="button button-primary" id="wpec-add-scheme"><?php _e('Añadir Esquema', 'wp-extreme-customize'); ?></button>
                        
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
                
                // Añadir esquema
                $('#wpec-add-scheme').on('click', function() {
                    var index = Date.now();
                    var scheme = '<div class="wpec-scheme-item" style="border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9;">' +
                        '<h3><?php _e('Esquema', 'wp-extreme-customize'); ?> #' + (parseInt(index/10000000000) + 1) + '</h3>' +
                        '<table class="form-table">' +
                        '<tr><th><label><?php _e('ID (slug único)', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][id]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Nombre', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][name]" class="regular-text" required></td></tr>' +
                        '<tr><th><label><?php _e('Color Base', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][colors][base]" class="color-picker"></td></tr>' +
                        '<tr><th><label><?php _e('Color Focus', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][colors][focus]" class="color-picker"></td></tr>' +
                        '<tr><th><label><?php _e('Color Current', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][colors][current]" class="color-picker"></td></tr>' +
                        '<tr><th><label><?php _e('Icon Base', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][colors][icon_base]" class="color-picker"></td></tr>' +
                        '<tr><th><label><?php _e('Icon Focus', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][colors][icon_focus]" class="color-picker"></td></tr>' +
                        '<tr><th><label><?php _e('Icon Current', 'wp-extreme-customize'); ?></label></th><td><input type="text" name="wpec_admin_color_schemes[schemes][' + index + '][colors][icon_current]" class="color-picker"></td></tr>' +
                        '<tr><th><label><?php _e('CSS Personalizado', 'wp-extreme-customize'); ?></label></th><td><textarea name="wpec_admin_color_schemes[schemes][' + index + '][custom_css]" rows="5" class="large-text code"></textarea></td></tr>' +
                        '</table>' +
                        '<button type="button" class="button button-secondary wpec-remove-scheme"><?php _e('Eliminar Esquema', 'wp-extreme-customize'); ?></button>' +
                    '</div>';
                    $('#wpec-schemes-list').append(scheme);
                });
                
                // Eliminar esquema
                $(document).on('click', '.wpec-remove-scheme', function() {
                    if (confirm('<?php _e('¿Eliminar este esquema?', 'wp-extreme-customize'); ?>')) {
                        $(this).closest('.wpec-scheme-item').remove();
                    }
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

new WPEC_Admin_Color_Schemes();
