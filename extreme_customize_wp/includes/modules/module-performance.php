
<?php
/**
 * Módulo Performance Extreme - Optimización de rendimiento
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_Performance {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wpec_performance_settings', []);
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Minificación
        add_filter('style_loader_tag', [$this, 'minify_css'], 10, 2);
        add_filter('script_loader_tag', [$this, 'minify_js'], 10, 2);
        
        // Compresión de imágenes
        add_filter('wp_generate_attachment_metadata', [$this, 'optimize_images']);
        
        // Cache
        add_action('init', [$this, 'setup_cache']);
        
        // Preload
        add_action('wp_head', [$this, 'add_preload_links']);
        
        // Lazy load
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_lazy_loading']);
        
        // DNS prefetch
        add_action('wp_head', [$this, 'add_dns_prefetch']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_performance_menu']);
    }
    
    public function minify_css($tag, $handle) {
        if (!$this->get_setting('minify_css', false)) {
            return $tag;
        }
        
        // En producción, usar un minificador real
        return $tag;
    }
    
    public function minify_js($tag, $handle) {
        if (!$this->get_setting('minify_js', false)) {
            return $tag;
        }
        
        return $tag;
    }
    
    public function optimize_images($metadata) {
        if (!$this->get_setting('optimize_images', false)) {
            return $metadata;
        }
        
        // Aquí se conectaría con un servicio de optimización
        return $metadata;
    }
    
    public function setup_cache() {
        if (!$this->get_setting('enable_cache', false)) {
            return;
        }
        
        $cache_time = $this->get_setting('cache_time', 3600);
        
        // Configurar headers de cache para recursos estáticos
        if (!is_admin() && !is_user_logged_in()) {
            header('Cache-Control: public, max-age=' . $cache_time);
        }
    }
    
    public function add_preload_links() {
        $preloads = $this->get_setting('preload_resources', []);
        
        foreach ($preloads as $resource) {
            if (!empty($resource['url'])) {
                echo '<link rel="preload" href="' . esc_url($resource['url']) . '" as="' . esc_attr($resource['as'] ?? 'script') . '">' . "\n";
            }
        }
        
        // Preload crítico
        $critical_css = $this->get_setting('critical_css', '');
        if ($critical_css) {
            echo '<link rel="preload" href="' . esc_url($critical_css) . '" as="style">' . "\n";
        }
    }
    
    public function add_lazy_loading($attr) {
        if (!$this->get_setting('lazy_load_images', true)) {
            return $attr;
        }
        
        if (empty($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        
        return $attr;
    }
    
    public function add_dns_prefetch() {
        $domains = $this->get_setting('dns_prefetch', []);
        
        foreach ($domains as $domain) {
            if (!empty($domain)) {
                echo '<link rel="dns-prefetch" href="//' . esc_url($domain) . '">' . "\n";
            }
        }
    }
    
    public function add_performance_menu() {
        add_menu_page(
            'Performance Extreme',
            'Performance',
            'manage_options',
            'wpec-performance',
            [$this, 'render_performance_settings'],
            'dashicons-speed',
            10
        );
    }
    
    public function render_performance_settings() {
        ?>
        <div class="wrap">
            <h1>Performance Extreme - WP Extreme Customize</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpec_performance'); ?>
                
                <div class="wpec-tabs">
                    <div class="wpec-tab-nav">
                        <a href="#wpec-tab-cache" class="wpec-tab-link active" data-tab="cache">Cache</a>
                        <a href="#wpec-tab-minify" class="wpec-tab-link" data-tab="minify">Minificación</a>
                        <a href="#wpec-tab-images" class="wpec-tab-link" data-tab="images">Imágenes</a>
                        <a href="#wpec-tab-preload" class="wpec-tab-link" data-tab="preload">Preload/DNS</a>
                    </div>
                    
                    <div class="wpec-tab-content active" id="wpec-tab-cache">
                        <h2>Configuración de Cache</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_enable_cache">Activar cache del navegador</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[enable_cache]" value="yes" <?php checked($this->settings['enable_cache'] ?? '', 'yes'); ?>>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_cache_time">Tiempo de cache (segundos)</label></th>
                                <td>
                                    <input type="number" name="wpec_performance[cache_time]" value="<?php echo esc_attr($this->settings['cache_time'] ?? 3600); ?>" class="small-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-minify">
                        <h2>Minificación</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_minify_css">Minificar CSS</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[minify_css]" value="yes" <?php checked($this->settings['minify_css'] ?? '', 'yes'); ?>>
                                    <p class="description">Minifica archivos CSS (requiere servicio externo o plugin complementario)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_minify_js">Minificar JavaScript</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[minify_js]" value="yes" <?php checked($this->settings['minify_js'] ?? '', 'yes'); ?>>
                                    <p class="description">Minifica archivos JS (requiere servicio externo o plugin complementario)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_combine_css">Combinar CSS</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[combine_css]" value="yes" <?php checked($this->settings['combine_css'] ?? '', 'yes'); ?>>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_combine_js">Combinar JavaScript</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[combine_js]" value="yes" <?php checked($this->settings['combine_js'] ?? '', 'yes'); ?>>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-images">
                        <h2>Optimización de Imágenes</h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="wpec_lazy_load_images">Lazy Load</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[lazy_load_images]" value="yes" <?php checked($this->settings['lazy_load_images'] ?? '', 'yes'); ?>>
                                    <p class="description">Carga perezosa nativa del navegador (loading="lazy")</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_optimize_images">Optimizar imágenes al subir</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[optimize_images]" value="yes" <?php checked($this->settings['optimize_images'] ?? '', 'yes'); ?>>
                                    <p class="description">Conecta con servicios como ShortPixel, Imagify, etc.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="wpec_webp">Convertir a WebP</label></th>
                                <td>
                                    <input type="checkbox" name="wpec_performance[webp]" value="yes" <?php checked($this->settings['webp'] ?? '', 'yes'); ?>>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="wpec-tab-content" id="wpec-tab-preload">
                        <h2>Preload de Recursos Críticos</h2>
                        
                        <p>Añade recursos que se deben precargar (CSS crítico, fuentes, JS crítico)</p>
                        
                        <table class="widefat fixed striped" id="wpec-preload-resources">
                            <thead>
                                <tr>
                                    <th>URL</th>
                                    <th>Tipo (as)</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $preloads = $this->settings['preload_resources'] ?? [];
                                foreach ($preloads as $index => $resource): 
                                ?>
                                <tr>
                                    <td><input type="url" name="wpec_performance[preload_resources][<?php echo $index; ?>][url]" value="<?php echo esc_attr($resource['url']); ?>" class="large-text"></td>
                                    <td>
                                        <select name="wpec_performance[preload_resources][<?php echo $index; ?>][as]" class="medium-text">
                                            <option value="style" <?php selected($resource['as'] ?? '', 'style'); ?>>CSS (style)</option>
                                            <option value="script" <?php selected($resource['as'] ?? '', 'script'); ?>>JavaScript (script)</option>
                                            <option value="font" <?php selected($resource['as'] ?? '', 'font'); ?>>Fuente (font)</option>
                                            <option value="image" <?php selected($resource['as'] ?? '', 'image'); ?>>Imagen (image)</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="button wpec-remove-preload">Eliminar</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button" id="wpec-add-preload">Añadir recurso</button>
                        
                        <h3>CSS Crítico</h3>
                        <p>URL del archivo CSS crítico para preload</p>
                        <input type="text" name="wpec_performance[critical_css]" value="<?php echo esc_attr($this->settings['critical_css'] ?? ''); ?>" class="large-text">
                        
                        <h2>DNS Prefetch</h2>
                        <p>Dominios para DNS prefetch (fonts.googleapis.com, ajax.googleapis.com, etc.)</p>
                        
                        <table class="widefat fixed striped" id="wpec-dns-prefetch">
                            <thead>
                                <tr>
                                    <th>Dominio</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $dns = $this->settings['dns_prefetch'] ?? [];
                                foreach ($dns as $index => $domain): 
                                ?>
                                <tr>
                                    <td><input type="text" name="wpec_performance[dns_prefetch][<?php echo $index; ?>]" value="<?php echo esc_attr($domain); ?>" class="large-text" placeholder="fonts.googleapis.com"></td>
                                    <td><button type="button" class="button wpec-remove-dns">Eliminar</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button" id="wpec-add-dns">Añadir dominio</button>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
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
                
                // Preload
                $('#wpec-add-preload').on('click', function() {
                    var index = Date.now();
                    var row = '<tr>' +
                        '<td><input type="url" name="wpec_performance[preload_resources][' + index + '][url]" class="large-text"></td>' +
                        '<td><select name="wpec_performance[preload_resources][' + index + '][as]" class="medium-text"><option value="style">CSS</option><option value="script">JS</option><option value="font">Font</option><option value="image">Image</option></select></td>' +
                        '<td><button type="button" class="button wpec-remove-preload">Eliminar</button></td>' +
                    '</tr>';
                    $('#wpec-preload-resources tbody').append(row);
                });
                
                $(document).on('click', '.wpec-remove-preload', function() {
                    $(this).closest('tr').remove();
                });
                
                // DNS
                $('#wpec-add-dns').on('click', function() {
                    var index = Date.now();
                    var row = '<tr>' +
                        '<td><input type="text" name="wpec_performance[dns_prefetch][' + index + ']" class="large-text" placeholder="fonts.googleapis.com"></td>' +
                        '<td><button type="button" class="button wpec-remove-dns">Eliminar</button></td>' +
                    '</tr>';
                    $('#wpec-dns-prefetch tbody').append(row);
                });
                
                $(document).on('click', '.wpec-remove-dns', function() {
                    $(this).closest('tr').remove();
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

new WPEC_Performance();
