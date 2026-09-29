
<?php
/**
 * Módulo Email Customizer - Personalización de todos los emails de WordPress
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
        // Filtrar todos los emails de WordPress
        add_filter('wp_mail', [$this, 'custom_wp_mail'], 10, 3);
        add_filter('gettext', [$this, 'translate_email_text'], 10, 3);
        
        // Emails específicos
        add_filter('retrieve_password_message', [$this, 'custom_retrieve_password'], 10, 2);
        add_filter('new_user_notification_email', [$this, 'custom_new_user_notification'], 10, 2);
        add_filter('comment_email', [$this, 'custom_comment_email'], 10, 2);
        add_filter('comment_moderation_email', [$this, 'custom_comment_moderation'], 10, 2);
        add_filter('woocommerce_email_subject', [$this, 'custom_woocommerce_subject'], 10, 3);
        add_filter('woocommerce_email_head', [$this, 'custom_woocommerce_headers'], 10, 2);
    }
    
    public function custom_wp_mail($headers, $headers, $mail) {
        // Aplicar firma personalizada a todos los emails
        $signature = $this->get_setting('signature', '');
        if (!empty($signature)) {
            $headers['X-Signature'] = $signature;
        }
        return $headers;
    }
    
    public function translate_email_text($translation, $text, $domain) {
        // Aplicar traducciones personalizadas
        $custom = get_option('wpec_custom_translations', []);
        if (isset($custom[$text])) {
            return $custom[$text];
        }
        return $translation;
    }
    
    public function custom_retrieve_password($message, $key) {
        $settings = $this->get_settings();
        $site_name = $settings['site_name'] ?? get_bloginfo('name');
        $site_url = get_site_url();
        $support_email = $settings['support_email'] ?? '';
        
        $new_message = sprintf(
            __('<h2>Restablecer contraseña</h2>')
            . '<p>Hola %s,</p>'
            . '<p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p>'
            . '<p><a href="%s" style="background: %s; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Restablecer contraseña</a></p>'
            . '<p>Si no solicitaste este cambio, ignora este correo.</p>'
            . '<p>Saludos,<br/>%s</p>',
            /* %1$s */ '%s', /* %2$s */ esc_url($site_url . '/wp-login.php?action=rp&key=' . $key . '&login=%s'),
            /* $3 */ $this->get_setting('brand_color', '#764ba2'),
            /* $4 */ $site_name
        );
        
        return $new_message;
    }
    
    public function custom_new_user_notification($email, $user) {
        $settings = $this->get_settings();
        $blog_name = $settings['blog_name'] ?? get_bloginfo('name');
        $admin_email = $settings['admin_email'] ?? get_option('admin_email');
        $brand_color = $this->get_setting('brand_color', '#764ba2');
        
        $message = sprintf(
            '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
            . '<div style="background: %1$s; color: %2$s; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;">'
            . '<h1>%3$s</h1>'
            . '</div>'
            . '<div style="background: #f4f4f4; padding: 20px; border: 1px solid #ddd;">'
            . '<p>Hola %1$s,</p>'
            . '<p>Tu cuenta ha sido creada con éxito.</p>'
            . '<p>Puedes iniciar sesión en <a href="%3$s" style="color: %2$s;">%3$s</a></p>'
            . '<p>Nombre de usuario: %4$s</p>'
            . '<p>Contraseña: %5$s</p>'
            . '<p>Si tienes alguna pregunta, contacta con nosotros en: <a href="mailto:%6$s">%6$s</a></p>'
            . '</div>'
            . '<div style="background: %1$s; color: %2$s; padding: 10px; text-align: center; margin-top: -1px; border-radius: 0 0 5px 5px; font-size: 12px;">'
            . '© ' . date('Y') . ' %3$s - Todos los derechos reservados'
            . '</div>'
            . '</div>',
            /* %1$s */ get_user_first_name($user->ID) ?? 'Usuario',
            /* %2$s */ '#ffffff',
            /* %3$s */ $blog_name,
            /* %4$s */ $user->user_login,
            /* %5$s */ wp_generate_password(12, false),
            /* %6$s */ $settings['support_email'] ?? ''
        );
        
        return $message;
    }
    
    public function custom_comment_email($commentdata) {
        $settings = $this->get_settings();
        $comment_author = $commentdata['comment_author'];
        $comment_content = $commentdata['comment_content'];
        $post_title = $commentdata['post_title'];
        $post_url = get_permalink($commentdata['comment_post_ID']);
        
        $brand_color = $this->get_setting('brand_color', '#764ba2');
        
        $email = sprintf(
            '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
            . '<div style="background: %1$s; color: #ffffff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; margin: -8px -8px 0 -8px;">'
            . '<h1>Nuevo comentario</h1>'
            . '</div>'
            . '<div style="background: #f4f4f4; padding: 20px; border: 1px solid #ddd; margin: 0 -8px;;">'
            . '<p>%2$s ha dejado un comentario en <a href="%3$s" style="color: %1$s;">"%1$s"</a></p>'
            . '<p><strong>Comentario:</strong> %4$s</p>'
            . '<p>Para ver el comentario, <a href="%3$s">haz clic aquí</a></p>'
            . '</div>'
            . '<div style="background: %1$s; color: #ffffff; padding: 10px; text-align: center; margin-top: -1px; border-radius: 0 0 5px 5px; font-size: 12px;">'
            . 'Powered by %2$s'
            . '</div>'
            . '</div>',
            /* %1$s */ $brand_color,
            /* %2$s */ get_bloginfo('name'),
            /* %3$s */ $post_url,
            /* %4$s */ nl2br($comment_content)
        );
        
        return $email;
    }
    
    public function custom_comment_moderation($commentdata) {
        $settings = $this->get_settings();
        $comment_author = $commentdata['comment_author'];
        $comment_content = $commentdata['comment_content'];
        $post_title = $commentdata['comment_title'];
        $post_url = get_permalink($commentdata['comment_post_ID']);
        $comment_author_email = $commentdata['comment_author_email'];
        
        $brand_color = $this->get_setting('brand_color', '#764ba2');
        
        $email = sprintf(
            '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
            . '<div style="background: %1$s; color: #ffffff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; margin: -8px -8px 0 -8px;">'
            . '<h1>Comentario pendiente de moderación</h1>'
            . '</div>'
            . '<div style="background: #f4f4f4; padding: 20px; border: 1px solid #ddd; margin: 0 -8px;;">'
            . '<p>Un comentario en <a href="%3$s" style="color: %1$s;">"%1$s"</a> requiere moderación.</p>'
            . '<p><strong>Autor:</strong> %2$s</p>'
            . '<p><strong>Comentario:</strong> %4$s</p>'
            . '<p>Para moderar, <a href="%5$s">haz clic aquí</a></p>'
            . '</div>'
            . '<div style="background: %1$s; color: #ffffff; padding: 10px; text-align: center; margin-top: -1px; border-radius: 0 0 5px 5px; font-size: 12px;">'
            . 'Powered by %2$s'
            . '</div>'
            . '</div>',
            /* %1$s */ $brand_color,
            /* %2$s */ $comment_author,
            /* %3$s */ $post_url,
            /* %4$s */ nl2br($comment_content),
            /* %5$s */ admin_url('options-discussion.php')
        );
        
        return $email;
    }
    
    public function custom_woocommerce_subject($subject, $object, $context) {
        $settings = $this->get_settings();
        $brand_name = $settings['store_name'] ?? get_bloginfo('name');
        
        // Añadir nombre de marca al asunto
        if (!str_contains($subject, $brand_name)) {
            $subject = "[$brand_name] $subject";
        }
        
        return $subject;
    }
    
    public function custom_woocommerce_headers($headers) {
        $settings = $this->get_settings();
        $brand_email = $settings['brand_email'] ?? get_bloginfo('admin_email');
        
        // Reemplazar header From
        $headers['From'] = $brand_email;
        $headers['X-Brand'] = 'AVFDigital';
        
        return $headers;
    }
    
    private function get_settings() {
        return get_option('wpec_email_settings', []);
    }
    
    private function get_setting($key, $default = '') {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}

new WPEC_Email_Customizer();
