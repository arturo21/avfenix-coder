
<?php
/**
 * Sistema de Internacionalización para WP Extreme Customize
 * Gestiona traducciones y configuración de idiomas
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPEC_i18n {
    
    private $text_domain = 'wp-extreme-customize';
    private $languages_dir;
    private $current_lang;
    private $available_languages;
    
    public function __construct() {
        $this->languages_dir = WPEC_PLUGIN_DIR . 'languages';
        $this->init_hooks();
        $this->load_available_languages();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        add_filter('locale', [$this, 'set_locale'], 10, 2);
        add_action('admin_init', [$this, 'load_admin_translations']);
        
        // Filtros para traducciones dinámicas
        add_filter('wpec_translate', [$this, 'translate_string'], 10, 3);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            $this->text_domain,
            false,
            dirname(plugin_basename(WPEC_PLUGIN_FILE)) . '/languages'
        );
        
        // Cargar traducciones personalizadas
        $this->load_custom_translations();
    }
    
    public function set_locale($locale, $domain) {
        if ($domain === $this->text_domain) {
            $user_lang = get_user_language();
            if ($user_lang) {
                $locale = $this->get_locale_from_lang($user_lang);
            }
        }
        return $locale;
    }
    
    public function load_admin_translations() {
        if (is_user_logged_in()) {
            $user_lang = get_user_language();
            if ($user_lang && $user_lang !== 'en_US') {
                $this->load_language_file($user_lang);
            }
        }
    }
    
    private function load_available_languages() {
        $this->available_languages = [];
        
        if (!is_dir($this->languages_dir)) {
            return;
        }
        
        $language_files = glob($this->languages_dir . '*.mo');
        if ($language_files) {
            foreach ($language_files as $file) {
                $filename = basename($file, '.mo');
                $parts = explode('_', $filename);
                
                if (count($parts) >= 2) {
                    $lang_code = $parts[0];
                    $locale = $filename;
                    
                    $this->available_languages[$locale] = [
                        'locale' => $locale,
                        'lang_code' => $lang_code,
                        'name' => $this->get_language_name($lang_code),
                        'file' => $file
                    ];
                }
            }
        }
        
        // Ordenar por nombre de idioma
        usort($this->available_languages, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
    }
    
    private function load_language_file($locale) {
        $file = $this->languages_dir . '/' . $locale . '.mo';
        if (file_exists($file)) {
            load_textdomain($this->text_domain, $file);
        }
    }
    
    private function load_custom_translations() {
        // Cargar traducciones personalizadas desde la base de datos
        $custom_translations = get_option('wpec_custom_translations', []);
        if (!empty($custom_translations)) {
            add_filter('ngettext', [$this, 'apply_custom_translations'], 10, 4);
            add_filter('gettext', [$this, 'apply_custom_translations'], 10, 3);
        }
    }
    
    public function apply_custom_translations($translation, $text, $domain = '') {
        if ($domain !== $this->text_domain) {
            return $translation;
        }
        
        $custom_translations = get_option('wpec_custom_translations', []);
        if (isset($custom_translations[$text])) {
            return $custom_translations[$text];
        }
        
        return $translation;
    }
    
    private function get_language_name($lang_code) {
        $languages = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ru' => 'Русский',
            'zh' => '中文',
            'ja' => '日本語',
            'ko' => '한국어',
            'ar' => 'العربية',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'tr' => 'Türkçe',
            'sv' => 'Svenska',
            'no' => 'Norsk',
            'da' => 'Dansk',
            'fi' => 'Suomi',
            'cs' => 'Čeština',
            'el' => 'Ελληνικά',
            'he' => 'עברית',
            'hi' => 'हिन्दी',
            'id' => 'Bahasa Indonesia',
            'ms' => 'Bahasa Melayu',
            'th' => 'ไทย',
            'uk' => 'Українська',
            'vi' => 'Tiếng Việt'
        ];
        
        return isset($languages[$lang_code]) ? $languages[$lang_code] : strtoupper($lang_code);
    }
    
    private function get_locale_from_lang($lang_code) {
        $locale_map = [
            'en' => 'en_US',
            'es' => 'es_ES',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'it' => 'it_IT',
            'pt' => 'pt_BR',
            'ru' => 'ru_RU',
            'zh' => 'zh_CN',
            'ja' => 'ja_JP',
            'ko' => 'ko_KR',
            'ar' => 'ar_SA',
            'nl' => 'nl_NL',
            'pl' => 'pl_PL',
            'tr' => 'tr_TR',
            'sv' => 'sv_SE',
            'no' => 'nb_NO',
            'da' => 'da_DK',
            'fi' => 'fi_FI',
            'cs' => 'cs_CZ',
            'el' => 'el_GR',
            'he' => 'he_IL',
            'hi' => 'hi_IN',
            'id' => 'id_ID',
            'ms' => 'ms_MY',
            'th' => 'th_TH',
            'uk' => 'uk_UA',
            'vi' => 'vi_VN'
        ];
        
        return isset($locale_map[$lang_code]) ? $locale_map[$lang_code] : $lang_code . '_' . strtoupper($lang_code);
    }
    
    public function get_available_languages() {
        return $this->available_languages;
    }
    
    public function get_current_language() {
        if (!$this->current_lang) {
            $this->current_lang = determined_current_language();
        }
        return $this->current_lang;
    }
    
    public function translate($text, $context = '', $domain = '') {
        if (empty($domain)) {
            $domain = $this->text_domain;
        }
        
        if ($context) {
            return translate_with_context($text, $context, $domain);
        }
        
        return translate($text, $domain);
    }
    
    // Generar archivo .pot para traducciones
    public function generate_pot_file() {
        $pot_content = $this->extract_translatable_strings();
        
        $pot_file = $this->languages_dir . '/wp-extreme-customize.pot';
        file_put_contents($pot_file, $pot_content);
        
        return $pot_file;
    }
    
    private function extract_translatable_strings() {
        $strings = [];
        $files_to_scan = [
            WPEC_PLUGIN_DIR . 'wp-extreme-customize.php',
            WPEC_INCLUDES_DIR . 'class-modules.php',
            WPEC_INCLUDES_DIR . 'admin/class-admin-interface.php',
            WPEC_INCLUDES_DIR . 'modules/module-login.php'
        ];
        
        foreach ($files_to_scan as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $this->extract_strings_from_content($content, $strings);
            }
        }
        
        // Generar contenido POT
        $pot_header = $this->get_pot_header();
        $pot_content = $pot_header . "\n";
        
        foreach ($strings as $string => $locations) {
            $pot_content .= "#: " . implode(', ', $locations) . "\n";
            $pot_content .= 'msgid "' . $this->escape_string($string) . "\"\n";
            $pot_content .= "msgstr \"\"\n\n";
        }
        
        return $pot_content;
    }
    
    private function extract_strings_from_content($content, &$strings) {
        // Buscar cadenas con __(), _e(), _n(), _x(), translate()
        $patterns = [
            '/__\(\s*["\']([^"\']+)["\']\s*[,)]/' => 'single',
            '/_e\(\s*["\']([^"\']+)["\']\s*[,)]/' => 'single',
            '/_n\(\s*["\']([^"\']+)["\']\s*,\s*["\']([^"\']+)["\']\s*,/' => 'plural',
            '/_nx\(\s*["\']([^"\']+)["\']\s*,\s*["\']([^"\']+)["\']\s*,/' => 'plural',
            '/_x\(\s*["\']([^"\']+)["\']\s*,\s*["\']([^"\']+)["\']\s*[,)]/' => 'context'
        ];
        
        foreach ($patterns as $pattern => $type) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $string = $match[1];
                $location = $this->get_string_location($content, $match[0]);
                
                if (!isset($strings[$string])) {
                    $strings[$string] = [];
                }
                
                if (!in_array($location, $strings[$string])) {
                    $strings[$string][] = $location;
                }
            }
        }
    }
    
    private function get_string_location($content, $string) {
        $lines = explode("\n", $content);
        $pos = strpos($content, $string);
        $line_number = 0;
        $current_pos = 0;
        
        foreach ($lines as $line) {
            $current_pos += strlen($line) + 1; // +1 for newline
            if ($current_pos > $pos) {
                break;
            }
            $line_number++;
        }
        
        return 'line:' . $line_number;
    }
    
    private function escape_string($string) {
        return addcslashes($string, "\"\\");
    }
    
    private function get_pot_header() {
        return sprintf(
            "# WP Extreme Customize translation template\n" .
            "# Copyright (C) %d AVFDigital\n" .
            "# This file is distributed under the same license as the WP Extreme Customize plugin.\n" .
            "msgid \"\"\n" .
            "msgstr \"\"\n" .
            "\"Project-Id-Version: WP Extreme Customize %s\\n\"\n" .
            "\"Report-Msgid-Bugs-To: \\n\"" .
            "\"MIME-Version: 1.0\\n\"" .
            "\"Content-Type: text/plain; charset=UTF-8\\n\"" .
            "\"Content-Transfer-Encoding: 8bit\\n\"" .
            "\"Language: \\n\"" .
            "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n",
            date('Y'),
            WPEC_VERSION
        );
    }
}

// Inicializar el sistema de i18n
new WPEC_i18n();

// Funciones de utilidad para traducción
if (!function_exists('wpec___')) {
    function wpec___($text, $context = '', $domain = 'wp-extreme-customize') {
        if ($context) {
            return translate_with_context($text, $context, $domain);
        }
        return translate($text, $domain);
    }
}

if (!function_exists('wpec_e')) {
    function wpec_e($text, $context = '', $domain = 'wp-extreme-customize') {
        echo wpec___($text, $context, $domain);
    }
}

if (!function_exists('wpec_n')) {
    function wpec_n($single, $plural, $number, $domain = 'wp-extreme-customize') {
        return _n($single, $plural, $number, $domain);
    }
}

if (!function_exists('wpec_nx')) {
    function wpec_nx($single, $plural, $number, $context, $domain = 'wp-extreme-customize') {
        return _nx($single, $plural, $number, $context, $domain);
    }
}

if (!function_exists('wpec_x')) {
    function wpec_x($string, $context, $domain = 'wp-extreme-customize') {
        return translate_with_context($string, $context, $domain);
    }
}
