
<?php
/**
 * Módulo Favicon - Gestión completa de favicons
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Favicon {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_favicon_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Agregar favicons al head
        add_action('wp_head', [$this, 'add_favicons']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_favicon_menu']);
        
        // Login
        add_action('login_head', [$this, 'add_login_favicons']);
    }
    
    public function add_favicons() {
        $favicon = $this->get_setting('favicon_url', '');
        $apple_touch = $this->get_setting('apple_touch_url', '');
        $android_chrome = $this->get_setting('android_chrome_url', '');
        $ms_tile = $this->get_setting('ms_tile_url', '');
        $ms_tile_color = $this->get_setting('ms_tile_color', '#764ba2');
        
        if ($favicon) {
            echo '<link rel="icon" type="image/x-icon" href="' . esc_url($favicon) . '">' . "\n";
            echo '<link rel="shortcut icon" type="image/x-icon" href="' . esc_url($favicon) . '">' . "\n";
        }
        
        if ($apple_touch) {
            echo '<link rel="apple-touch-icon" href="' . esc_url($apple_touch) . '">' . "\n";
        }
        
        if ($android_chrome) {
            echo '<link rel="manifest" href="' . esc_url($android_chrome) . '">' . "\n";
        }
        
        if ($ms_tile) {
            echo '<meta name="msapplication-TileImage" content="' . esc_url($ms_tile) . '">' . "\n";
            echo '<meta name="msapplication-TileColor" content="' . esc_attr($ms_tile_color) . '">' . "\n";
        }
    }
    
    public function add_login_favicons() {
        $favicon = $this->get_setting('favicon_url', '');
        
        if ($favicon) {
            echo '<link rel="icon" type="image/x-icon" href="' . esc_url($favicon) . '">' . "\n";
        }
    }
    
    public function add_favicon_menu() {
        add_menu_page(
            'Favicon',
            'Favicon',
            'manage_options',
            'wpec-favicon',
            [$this, 'render_favicon_settings'],
            'dashicons-images-alt2',
            10
        );
    }
    
    public function render_favicon_settings() {
        ?>
        <div class="wrap">
            <h1>Favicon - WP Extreme Customize</h1>
            
            <p>Configura todos los favicons para tu sitio. Sube las imágenes o ingresa las URLs.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_favicon'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="wpec_favicon">Favicon principal (ICO)</label></th>
                        <td>
                            <input type="text" name="wpec_favicon[favicon_url]" value="<?php echo esc_attr($this->settings['favicon_url'] ?? ''); ?>" class="large-text">
                            <br>
                            <small>Tamaño recomendado: 32x32px o 16x16px (formato .ico)</small>
                            <?php if ($this->settings['favicon_url'] ?? ''): ?>
                                <br><img src="<?php echo esc_url($this->settings['favicon_url']); ?>" width="32" height="32">
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_apple_touch">Apple Touch Icon</label></th>
                        <td>
                            <input type="text" name="wpec_favicon[apple_touch_url]" value="<?php echo esc_attr($this->settings['apple_touch_url'] ?? ''); ?>" class="large-text">
                            <br>
                            <small>Tamaño recomendado: 180x180px (formato .png)</small>
                            <?php if ($this->settings['apple_touch_url'] ?? ''): ?>
                                <br><img src="<?php echo esc_url($this->settings['apple_touch_url']); ?>" width="80" height="80">
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_android_chrome">Android Chrome / Web App Manifest</label></th>
                        <td>
                            <input type="text" name="wpec_favicon[android_chrome_url]" value="<?php echo esc_attr($this->settings['android_chrome_url'] ?? ''); ?>" class="large-text">
                            <br>
                            <small>URL del archivo manifest.json (generado con favicon generators)</small>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_ms_tile">Microsoft Tile (Windows 8/10)</label></th>
                        <td>
                            <input type="text" name="wpec_favicon[ms_tile_url]" value="<?php echo esc_attr($this->settings['ms_tile_url'] ?? ''); ?>" class="large-text">
                            <br>
                            <small>Tamaño recomendado: 144x144px (formato .png)</small>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_ms_tile_color">Color del tile de Microsoft</label></th>
                        <td>
                            <input type="text" name="wpec_favicon[ms_tile_color]" value="<?php echo esc_attr($this->settings['ms_tile_color'] ?? '#764ba2'); ?>" class="color-picker medium-text">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    private function get_setting($key, $default = '') {
        $settings = $this->settings;
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}

new WPEC_Favicon();
