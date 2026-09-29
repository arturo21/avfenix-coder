
<?php
/**
 * Módulo SEO Extreme - Optimización SEO avanzada
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_SEO {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_seo_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Meta tags
        add_action('wp_head', [$this, 'add_meta_tags']);
        
        // Open Graph
        add_action('wp_head', [$this, 'add_open_graph']);
        
        // Twitter Cards
        add_action('wp_head', [$this, 'add_twitter_cards']);
        
        // Schema.org
        add_action('wp_head', [$this, 'add_schema_org']);
        
        // Canonical
        add_filter('get_canonical_url', [$this, 'custom_canonical']);
        
        // Robots.txt
        add_filter('robots_txt', [$this, 'custom_robots_txt']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_seo_menu']);
    }
    
    public function add_meta_tags() {
        if (is_singular()) {
            $post = get_queried_object();
            
            $description = $this->get_setting('meta_description', '');
            if (empty($description)) {
                $description = get_post_meta($post->ID, '_wpec_meta_description', true);
            }
            
            if (empty($description)) {
                $description = wp_trim_words(strip_shortcodes($post->post_content), 30);
            }
            
            if (!empty($description)) {
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            }
            
            // Keywords
            $keywords = get_post_meta($post->ID, '_wpec_meta_keywords', true);
            if (!empty($keywords)) {
                echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
            }
            
            // Robots
            $robots = get_post_meta($post->ID, '_wpec_meta_robots', true);
            if (!empty($robots)) {
                echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";
            }
        } else {
            // Páginas de archivo, etc.
            $description = $this->get_setting('default_meta_description', '');
            if (!empty($description)) {
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            }
        }
    }
    
    public function add_open_graph() {
        $og = $this->get_setting('open_graph', []);
        
        if (is_singular()) {
            $post = get_queried_object();
            
            $og_title = get_post_meta($post->ID, '_wpec_og_title', true) ?: get_the_title($post);
            $og_description = get_post_meta($post->ID, '_wpec_og_description', true) ?: wp_trim_words(strip_shortcodes($post->post_content), 30);
            $og_image = get_post_meta($post->ID, '_wpec_og_image', true) ?: $this->get_setting('default_og_image', '');
            $og_type = get_post_meta($post->ID, '_wpec_og_type', true) ?: 'article';
        } else {
            $og_title = $og['title'] ?? get_bloginfo('name');
            $og_description = $og['description'] ?? get_bloginfo('description');
            $og_image = $og['image'] ?? '';
            $og_type = $og['type'] ?? 'website';
        }
        
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        
        if ($og_image) {
            echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        }
    }
    
    public function add_twitter_cards() {
        $twitter = $this->get_setting('twitter_cards', []);
        
        $card_type = $twitter['card_type'] ?? 'summary_large_image';
        $site = $twitter['site'] ?? '';
        $creator = $twitter['creator'] ?? '';
        
        echo '<meta name="twitter:card" content="' . esc_attr($card_type) . '">' . "\n";
        
        if ($site) {
            echo '<meta name="twitter:site" content="' . esc_attr($site) . '">' . "\n";
        }
        
        if ($creator) {
            echo '<meta name="twitter:creator" content="' . esc_attr($creator) . '">' . "\n";
        }
        
        // Usar Open Graph como fallback
        if (is_singular()) {
            $post = get_queried_object();
            echo '<meta name="twitter:title" content="' . esc_attr(get_the_title($post)) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr(wp_trim_words(strip_shortcodes($post->post_content), 30)) . '">' . "\n";
        }
    }
    
    public function add_schema_org() {
        if (!is_singular()) {
            return;
        }
        
        $post = get_queried_object();
        $schema_type = get_post_meta($post->ID, '_wpec_schema_type', true) ?: 'Article';
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'headline' => get_the_title($post),
            'description' => wp_trim_words(strip_shortcodes($post->post_content), 30),
            'url' => get_permalink($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->get_setting('schema_logo', ''),
                ],
            ],
        ];
        
        // Imagen
        $image = get_post_meta($post->ID, '_wpec_og_image', true) ?: get_the_post_thumbnail_url($post, 'full');
        if ($image) {
            $schema['image'] = $image;
        }
        
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    public function custom_canonical($url) {
        $custom = $this->get_setting('custom_canonical', '');
        
        if (is_singular() && !empty($custom)) {
            return $custom;
        }
        
        return $url;
    }
    
    public function custom_robots_txt($output) {
        $custom = $this->get_setting('robots_txt', '');
        
        if (!empty($custom)) {
            return $custom;
        }
        
        return $output;
    }
    
    public function add_seo_menu() {
        add_menu_page(
            'SEO Extreme',
            'SEO Extreme',
            'manage_options',
            'wpec-seo',
            [$this, 'render_seo_settings'],
            'dashicons-chart-bar',
            10
        );
    }
    
    public function render_seo_settings() {
        ?>
        <div class="wrap">
            <h1>SEO Extreme - WP Extreme Customize</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_seo'); ?>
                
                <div class="wpec-tabs">
                    <div class="wpec-tab-nav">
                        <a href="#wpec-tab-general" class="wpec-tab-link active" data-tab="general">General</a>
                        <a href="#wpec-tab-opengraph" class="wpec-tab-link" data-tab="opengraph">Open Graph</a>
                        <a href="#wpec-tab-twitter" class="wpec-tab-link" data-tab="twitter">Twitter Cards</a>
                        <a href="#wpec-tab-schema" class="wpec-tab-link" data-tab="schema">Schema.org</a>
                        <a href="#wpec-tab-advanced" class="wpec-tab-link" data-tab="advanced">Avanzado</a>
                    </div>
                    
                    <div class="wpec-tab-content active" id="wpec-tab-general">
                        <h2>Meta Tags Globales</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_meta_description">Meta Description por defecto</label></th>
                                <td>
                                    <textarea name="wpec_seo[default_meta_description]" rows="3" class="large-text"><?php echo esc_textarea($this->settings['default_meta_description'] ?? ''); ?></textarea>
                                    <p class="description">Usado en páginas que no son entradas (archivos, búsqueda, 404, etc.)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-opengraph">
                        <h2>Open Graph</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_og_title">Título por defecto</label></th>
                                <td>
                                    <input type="text" name="wpec_seo[open_graph][title]" value="<?php echo esc_attr($this->settings['open_graph']['title'] ?? ''); ?>" class="large-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_og_description">Descripción por defecto</label></th>
                                <td>
                                    <textarea name="wpec_seo[open_graph][description]" rows="3" class="large-text"><?php echo esc_textarea($this->settings['open_graph']['description'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_og_image">Imagen por defecto</label></th>
                                <td>
                                    <input type="text" name="wpec_seo[open_graph][image]" value="<?php echo esc_attr($this->settings['open_graph']['image'] ?? ''); ?>" class="large-text">
                                    <p class="description">URL de la imagen (mín. 1200x630px)</p>
                                    <?php if ($this->settings['open_graph']['image'] ?? ''): ?>
                                        <br><img src="<?php echo esc_url($this->settings['open_graph']['image']); ?>" width="200">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_og_type">Tipo por defecto</label></th>
                                <td>
                                    <select name="wpec_seo[open_graph][type]" class="medium-text">
                                        <option value="website" <?php selected($this->settings['open_graph']['type'] ?? '', 'website'); ?>>Sitio web</option>
                                        <option value="article" <?php selected($this->settings['open_graph']['type'] ?? '', 'article'); ?>>Artículo</option>
                                        <option value="product" <?php selected($this->settings['open_graph']['type'] ?? '', 'product'); ?>>Producto</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-twitter">
                        <h2>Twitter Cards</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_twitter_card_type">Tipo de tarjeta</label></th>
                                <td>
                                    <select name="wpec_seo[twitter_cards][card_type]" class="medium-text">
                                        <option value="summary" <?php selected($this->settings['twitter_cards']['card_type'] ?? '', 'summary'); ?>>Resumen</option>
                                        <option value="summary_large_image" <?php selected($this->settings['twitter_cards']['card_type'] ?? '', 'summary_large_image'); ?>>Resumen con imagen grande</option>
                                        <option value="app" <?php selected($this->settings['twitter_cards']['card_type'] ?? '', 'app'); ?>>App</option>
                                        <option value="player" <?php selected($this->settings['twitter_cards']['card_type'] ?? '', 'player'); ?>>Player</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_twitter_site">@usuario del sitio</label></th>
                                <td>
                                    <input type="text" name="wpec_seo[twitter_cards][site]" value="<?php echo esc_attr($this->settings['twitter_cards']['site'] ?? ''); ?>" class="medium-text" placeholder="@usuario">
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_twitter_creator">@creador</label></th>
                                <td>
                                    <input type="text" name="wpec_seo[twitter_cards][creator]" value="<?php echo esc_attr($this->settings['twitter_cards']['creator'] ?? ''); ?>" class="medium-text" placeholder="@usuario">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-schema">
                        <h2>Schema.org (JSON-LD)</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_schema_logo">Logo de la organización</label></th>
                                <td>
                                    <input type="text" name="wpec_seo[schema_logo]" value="<?php echo esc_attr($this->settings['schema_logo'] ?? ''); ?>" class="large-text">
                                    <p class="description">URL del logo para schema.org Organization</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-advanced">
                        <h2>Opciones Avanzadas</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_custom_canonical">Canonical personalizado</label></th>
                                <td>
                                    <input type="url" name="wpec_seo[custom_canonical]" value="<?php echo esc_attr($this->settings['custom_canonical'] ?? ''); ?>" class="large-text">
                                    <p class="description">Sobrescribe la URL canónica globalmente</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_robots_txt">robots.txt personalizado</label></th>
                                <td>
                                    <textarea name="wpec_seo[robots_txt]" rows="10" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea($this->settings['robots_txt'] ?? ''); ?></textarea>
                                    <p class="description">Contenido completo del robots.txt (deja en blanco para usar el de WordPress)</p>
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
            });
            </script>
        </div>
        <?php
    }
    
    private function get_setting($key, $default = '') {
        $settings = $this->settings;
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}

new WPEC_SEO();
