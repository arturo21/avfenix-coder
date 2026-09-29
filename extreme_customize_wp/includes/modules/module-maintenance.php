
<?php
/**
 * Módulo Maintenance Mode - Modo mantenimiento con branding
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Maintenance_Mode {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_maintenance_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Verificar si el modo mantenimiento está activo
        if ($this->is_maintenance()) {
            add_action('wp', [$this, 'render_maintenance_page']);
            add_filter('option_maintenance_mode', [$this, 'override_maintenance_status']);
        }
        
        // Administrar modo mantenimiento
        add_action('admin_menu', [$this, 'add_maintenance_menu']);
    }
    
    public function is_maintenance() {
        return isset($this->settings['active']) && $this->settings['active'] === 'yes';
    }
    
    public function override_maintenance_status($status) {
        if (isset($this->settings['force_active']) && $this->settings['force_active'] === 'yes') {
            return 'yes';
        }
        return $status;
    }
    
    public function render_maintenance_page() {
        // No romper el login o admin para usuarios con capacidades
        if (is_admin() && current_user_can('manage_options')) {
            return;
        }
        
        $title = $this->get_setting('title', 'Sitio en mantenimiento');
        $message = $this->get_setting('message', 'Estamos realizando mejoras');
        $countdown = $this->get_setting('countdown', '0');
        $bg_color = $this->get_setting('bg_color', '#667eea');
        $brand_color = $this->get_setting('brand_color', '#764ba2');
        $logo = $this->get_setting('logo', '');
        
        // Headers
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 60');
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo $title; ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: <?php echo $bg_color; ?>;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                    color: #333;
                }
                .maintenance-container {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    text-align: center;
                    max-width: 500px;
                    margin: 0 auto;
                }
                .maintenance-logo {
                    width: 150px;
                    margin: 0 auto 20px;
                }
                .maintenance-title {
                    color: <?php echo $brand_color; ?>;
                    font-size: 28px;
                    margin-bottom: 10px;
                }
                .maintenance-message {
                    font-size: 16px;
                    margin-bottom: 30px;
                }
                .countdown {
                    font-size: 18px;
                    color: <?php echo $brand_color; ?>;
                    margin-top: 20px;
                }
                .maintenance-link {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 20px;
                    background: <?php echo $brand_color; ?>;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="AVFDigital" class="maintenance-logo">
                <?php endif; ?>
                <h1 class="maintenance-title"><?php echo $title; ?></h1>
                <p class="maintenance-message"><?php echo $message; ?></p>
                <?php if ($countdown): ?>
                    <div class="countdown">Por favor, vuelve en <?php echo $countdown; ?> segundos</div>
                <?php endif; ?>
                <a href="<?php echo esc_url(home_url()); ?>" class="maintenance-link">Volver al inicio</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    public function add_maintenance_menu() {
        add_menu_page(
            'Modo Mantenimiento',
            'Modo Mantenimiento',
            'manage_options',
            'wpec-maintenance',
            [$this, 'render_maintenance_settings'],
            'dashicons-admin-site',
            10
        );
    }
    
    public function render_maintenance_settings() {
        ?>
        <div class="wrap">
            <h1>Modo Mantenimiento - WP Extreme Customize</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_maintenance'); ?>
                <?php do_settings_sections('wpec_maintenance'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="wpec_active">Activar modo mantenimiento</label></th>
                        <td>
                            <input type="radio" name="wpec_maintenance[active]" value="yes" <?php checked($this->settings['active'] ?? '', 'yes'); ?>>
                            Sí
                            <input type="radio" name="wpec_maintenance[active]" value="no" <?php checked($this->settings['active'] ?? '', 'no'); ?>>
                            No
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_title">Título</label></th>
                        <td><input type="text" name="wpec_maintenance[title]" value="<?php echo esc_attr($this->settings['title'] ?? ''); ?>" class="large-text"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_message">Mensaje</label></th>
                        <td><textarea name="wpec_maintenance[message]" rows="3" class="medium-text"><?php echo esc_attr($this->settings['message'] ?? ''); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_countdown">Cuenta regresiva (segundos)</label></th>
                        <td><input type="number" name="wpec_maintenance[countdown]" value="<?php echo esc_attr($this->settings['countdown'] ?? '0'); ?>" class="small-text"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_bg_color">Color de fondo</label></th>
                        <td><input type="text" name="wpec_maintenance[bg_color]" value="<?php echo esc_attr($this->settings['bg_color'] ?? '#667eea'); ?>" class="color-picker"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_brand_color">Color de marca</label></th>
                        <td><input type="text" name="wpec_maintenance[brand_color]" value="<?php echo esc_attr($this->settings['brand_color'] ?? '#764ba2'); ?>" class="color-picker"></td>
                    </tr>
                    
                    <tr>
                        <th><label for="wpec_logo">Logo</label></th>
                        <td>
                            <input type="text" name="wpec_maintenance[logo]" value="<?php echo esc_attr($this->settings['logo'] ?? ''); ?>" class="large-text">
                            <br>
                            <small>URL de la imagen del logo</small>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new WPEC_Maintenance_Mode();
