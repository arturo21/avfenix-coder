
/* WP Extreme Customize - Admin JavaScript */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Configuración de tabs
        $('.wpec-tabs').each(function() {
            var $tabs = $(this);
            var $tabItems = $tabs.find('.wpec-tab-item');
            var $tabContents = $tabs.find('.wpec-tab-content');
            
            $tabItems.on('click', function(e) {
                e.preventDefault();
                var $this = $(this);
                var tabId = $this.data('tab');
                
                $tabItems.removeClass('active');
                $tabContents.removeClass('active');
                
                $this.addClass('active');
                $('#wpec-tab-' + tabId).addClass('active');
            });
        });
        
        // Configuración de colores
        $('.wpec-color-picker').wpColorPicker();
        
        // Configuración de imagen
        $('.wpec-image-upload').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $input = $button.siblings('input');
            
            var frame = wp.media({
                title: $button.data('title'),
                button: { text: $button.data('button') },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                $button.siblings('.wpec-image-preview').attr('src', attachment.url);
            });
            
            frame.open();
        });
        
        // Vista previa de configuración
        $('.wpec-preview-button').on('click', function(e) {
            e.preventDefault();
            var previewWindow = window.open('', '_blank');
            var previewContent = generatePreviewContent();
            previewWindow.document.write(previewContent);
            previewWindow.document.close();
        });
        
        function generatePreviewContent() {
            var settings = {};
            $('.wpec-setting-input').each(function() {
                var $input = $(this);
                var name = $input.attr('name');
                var value = $input.val();
                settings[name] = value;
            });
            
            // Generar HTML de previsualización
            var html = '<!DOCTYPE html>';
            html += '<html><head><title>Preview - WP Extreme Customize</title>';
            html += '<style>body { font-family: sans-serif; padding: 20px; }</style>';
            html += '</head><body>';
            html += '<h1>Preview Configuration</h1>';
            html += '<pre>' + JSON.stringify(settings, null, 2) + '</pre>';
            html += '</body></html>';
            
            return html;
        }
    });
})(jQuery);
