
<?php
/**
 * Módulo Email Customizer - Personalizar emails de WordPress
 * Version: 1.0.2 - Corrección de seguridad y validación
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Email_Customizer {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_email_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Filtros de WordPress para emails
        add_filter('wp_mail_from', [$this, 'custom_mail_from']);
        add_filter('wp_mail_from_name', [$this, 'custom_mail_from_name']);
        add_filter('wp_mail_content_type', [$this, 'custom_content_type']);
        
        // Email de restablecimiento de contraseña
        add_filter('retrieve_password_message', [$this, 'custom_reset_password_email'], 10, 4);
        add_filter('retrieve_password_title', [$this, 'custom_reset_password_title'], 10, 2);
        
        // Email de nuevo usuario
        add_filter('wp_new_user_notification_email', [$this, 'custom_new_user_email'], 10, 3);
        add_filter('wp_new_user_notification_email_admin', [$this, 'custom_new_user_admin_email'], 10, 3);
        
        // Email de comentario
        add_filter('comment_notification_text', [$this, 'custom_comment_email'], 10, 2);
        add_filter('comment_notification_title', [$this, 'custom_comment_email_title'], 10, 2);
        
        // WooCommerce emails (si existe)
        add_action('woocommerce_email_header', [$this, 'woocommerce_email_header'], 10, 2);
        add_action('woocommerce_email_footer', [$this, 'woocommerce_email_footer'], 10, 1);
        
        // Admin
        add_action('admin_menu', [$this, 'add_email_customizer_menu']);
    }
    
    public function custom_mail_from($from_email) {
        $custom = $this->get_setting('from_email', '');
        if (!empty($custom) && is_email($custom)) {
            return $custom;
        }
        return $from_email;
    }
    
    public function custom_mail_from_name($from_name) {
        $custom = $this->get_setting('from_name', '');
        if (!empty($custom)) {
            return sanitize_text_field($custom);
        }
        return $from_name;
    }
    
    public function custom_content_type($content_type) {
        return 'text/html';
    }
    
    public function custom_reset_password_email($message, $key, $user_login, $user_data) {
        $custom = $this->get_setting('reset_password_template', '');
        
        if (empty($custom)) {
            return $message;
        }
        
        $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
        
        $message = str_replace(
            ['{site_name}', '{user_login}', '{reset_url}', '{admin_email}'],
            [get_bloginfo('name'), $user_login, $reset_url, get_option('admin_email')],
            $custom
        );
        
        return $message;
    }
    
    public function custom_reset_password_title($title, $user_login) {
        $custom = $this->get_setting('reset_password_title', '');
        return !empty($custom) ? sanitize_text_field($custom) : $title;
    }
    
    public function custom_new_user_email($email, $user, $password_generated) {
        $template = $this->get_setting('new_user_template', '');
        
        if (!empty($template)) {
            $email['message'] = str_replace(
                ['{site_name}', '{user_login}', '{user_email}', '{password}', '{admin_email}'],
                [get_bloginfo('name'), $user->user_login, $user->user_email, '***', get_option('admin_email')],
                $template
            );
        }
        
        return $email;
    }
    
    public function custom_new_user_admin_email($email, $user, $password_generated) {
        $template = $this->get_setting('new_user_admin_template', '');
        
        if (!empty($template)) {
            $email['message'] = str_replace(
                ['{site_name}', '{user_login}', '{user_email}', '{admin_email}'],
                [get_bloginfo('name'), $user->user_login, $user->user_email, get_option('admin_email')],
                $template
            );
        }
        
        return $email;
    }
    
    public function custom_comment_email($message, $comment_id) {
        $template = $this->get_setting('comment_template', '');
        
        if (!empty($template)) {
            $comment = get_comment($comment_id);
            $message = str_replace(
                ['{comment_author}', '{comment_content}', '{post_title}', '{comment_url}', '{site_name}'],
                [get_comment_author($comment_id), get_comment_text($comment_id), get_the_title($comment->comment_post_ID), get_comment_link($comment_id), get_bloginfo('name')],
                $template
            );
        }
        
        return $message;
    }
    
    public function custom_comment_email_title($title, $comment_id) {
        $custom = $this->get_setting('comment_title', '');
        return !empty($custom) ? sanitize_text_field($custom) : $title;
    }
    
    public function woocommerce_email_header($email_heading, $email) {
        $header_template = $this->get_setting('woo_header_template', '');
        if (!empty($header_template)) {
            echo wp_kses_post($header_template);
        }
    }
    
    public function woocommerce_email_footer($email) {
        $footer_template = $this->get_setting('woo_footer_template', '');
        if (!empty($footer_template)) {
            echo wp_kses_post($footer_template);
        }
    }
    
    public function add_email_customizer_menu() {
        add_submenu_page(
            'wp-extreme-customize',
            __('Email Customizer', 'wp-extreme-customize'),
            __('Email Customizer', 'wp-extreme-customize'),
            'manage_options',
            'wpec-email-customizer',
            [$this, 'render_email_customizer_settings']
        );
    }
    
    public function render_email_customizer_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos.', 'wp-extreme-customize'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Email Customizer', 'wp-extreme-customize'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_email'); ?>
                
                <div class="wpec-tabs">
                    <div class="wpec-tab-nav">
                        <a href="#wpec-tab-general" class="wpec-tab-link active" data-tab="general"><?php _e('General', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-password" class="wpec-tab-link" data-tab="password"><?php _e('Password Reset', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-new-user" class="wpec-tab-link" data-tab="new-user"><?php _e('New User', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-comment" class="wpec-tab-link" data-tab="comment"><?php _e('Comment', 'wp-extreme-customize'); ?></a>
                        <a href="#wpec-tab-woo" class="wpec-tab-link" data-tab="woo"><?php _e('WooCommerce', 'wp-extreme-customize'); ?></a>
                    </div>
                    
                    <div class="wpec-tab-content active" id="wpec-tab-general">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_from_name"><?php _e('From Name', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_email_settings[from_name]" id="wpec_from_name" value="<?php echo esc_attr($this->settings['from_name'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_from_email"><?php _e('From Email', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="email" name="wpec_email_settings[from_email]" id="wpec_from_email" value="<?php echo esc_attr($this->settings['from_email'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_support_email"><?php _e('Support Email', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="email" name="wpec_email_settings[support_email]" id="wpec_support_email" value="<?php echo esc_attr($this->settings['support_email'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_site_name"><?php _e('Site Name', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_email_settings[site_name]" id="wpec_site_name" value="<?php echo esc_attr($this->settings['site_name'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_brand_color"><?php _e('Brand Color', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_email_settings[brand_color]" id="wpec_brand_color" value="<?php echo esc_attr($this->settings['brand_color'] ?? '#764ba2'); ?>" class="color-picker"></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-password">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_reset_title"><?php _e('Título email reset', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_email_settings[reset_password_title]" id="wpec_reset_title" value="<?php echo esc_attr($this->settings['reset_password_title'] ?? ''); ?>" class="large-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_reset_template"><?php _e('Template', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_email_settings[reset_password_template]" id="wpec_reset_template" rows="8" class="large-text code"><?php echo esc_textarea($this->settings['reset_password_template'] ?? ''); ?></textarea>
                                    <p class="description">Variables: {site_name}, {user_login}, {reset_url}, {admin_email}</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-new-user">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_new_user_template"><?php _e('Template para usuario', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_email_settings[new_user_template]" id="wpec_new_user_template" rows="8" class="large-text code"><?php echo esc_textarea($this->settings['new_user_template'] ?? ''); ?></textarea>
                                    <p class="description">Variables: {site_name}, {user_login}, {user_email}, {password}</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_new_user_admin_template"><?php _e('Template para admin', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_email_settings[new_user_admin_template]" id="wpec_new_user_admin_template" rows="8" class="large-text code"><?php echo esc_textarea($this->settings['new_user_admin_template'] ?? ''); ?></textarea>
                                    <p class="description">Variables: {site_name}, {user_login}, {user_email}, {admin_email}</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-comment">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_comment_title"><?php _e('Título email comentario', 'wp-extreme-customize'); ?></label></th>
                                <td><input type="text" name="wpec_email_settings[comment_title]" id="wpec_comment_title" value="<?php echo esc_attr($this->settings['comment_title'] ?? ''); ?>" class="large-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_comment_template"><?php _e('Template', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_email_settings[comment_template]" id="wpec_comment_template" rows="8" class="large-text code"><?php echo esc_textarea($this->settings['comment_template'] ?? ''); ?></textarea>
                                    <p class="description">Variables: {comment_author}, {comment_content}, {post_title}, {comment_url}, {site_name}</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-woo">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="wpec_woo_header"><?php _e('Header WooCommerce', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_email_settings[woo_header_template]" id="wpec_woo_header" rows="6" class="large-text code"><?php echo esc_textarea($this->settings['woo_header_template'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpec_woo_footer"><?php _e('Footer WooCommerce', 'wp-extreme-customize'); ?></label></th>
                                <td>
                                    <textarea name="wpec_email_settings[woo_footer_template]" id="wpec_woo_footer" rows="6" class="large-text code"><?php echo esc_textarea($this->settings['woo_footer_template'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
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
            });
            </script>
        </div>
        <?php
    }
    
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
}

new WPEC_Email_Customizer();
