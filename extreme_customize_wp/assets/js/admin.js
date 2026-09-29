
/*
 * WP Extreme Customize - Admin JavaScript
 * Version: 1.0.2 - Corregido: localStorage, tabs, color pickers
 */

(function($) {
    'use strict';
    
    var WPEC = {
        init: function() {
            this.initTabs();
            this.initColorPickers();
            this.initToggles();
            this.initConfirmations();
        },
        
        initTabs: function() {
            $(document).on('click', '.wpec-tab-link', function(e) {
                e.preventDefault();
                var $link = $(this);
                var tab = $link.data('tab');
                var $container = $link.closest('.wpec-tabs');
                
                if (!$container.length) {
                    $container = $link.closest('.wrap');
                }
                
                $container.find('.wpec-tab-link').removeClass('active');
                $link.addClass('active');
                $container.find('.wpec-tab-content').removeClass('active');
                $container.find('#wpec-tab-' + tab).addClass('active');
            });
        },
        
        initColorPickers: function() {
            if (typeof $.fn.wpColorPicker === 'function') {
                $('.color-picker, .wpec-color-field').wpColorPicker();
            }
        },
        
        initToggles: function() {
            $(document).on('change', '.wpec-switch input', function() {
                var $switch = $(this);
                var enabled = $switch.is(':checked');
                
                // Disparar evento personalizado
                $switch.closest('.wpec-module-card').toggleClass('active', enabled);
            });
        },
        
        initConfirmations: function() {
            $(document).on('click', '.wpec-confirm-delete', function(e) {
                var message = $(this).data('confirm') || '<?php _e("¿Estás seguro?", "wp-extreme-customize"); ?>';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        // Helper AJAX
        ajax: function(action, data, callback) {
            data = data || {};
            data.action = action;
            
            if (typeof wpec_ajax !== 'undefined' && wpec_ajax.nonce) {
                data.nonce = wpec_ajax.nonce;
            }
            
            $.post(ajaxurl || '<?php echo admin_url("admin-ajax.php"); ?>', data, function(response) {
                if (callback) callback(response);
            }).fail(function(xhr, status, error) {
                if (callback) callback({success: false, data: {message: error}});
            });
        },
        
        // Toast notifications
        toast: function(message, type) {
            type = type || 'success';
            
            // Remover toasts existentes
            $('.wpec-toast').remove();
            
            var $toast = $('<div class="wpec-toast wpec-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);
            
            $toast.fadeIn(300);
            
            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        // Formatear bytes
        formatBytes: function(bytes, decimals) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var dm = decimals || 2;
            var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    };
    
    // Inicializar
    $(document).ready(function() {
        WPEC.init();
    });
    
    // Exponer al global para compatibilidad
    window.WPEC = WPEC;
    
})(jQuery);

/* Estilos para toasts */
var wpecToastStyles = document.createElement('style');
wpecToastStyles.textContent = [
    '.wpec-toast {',
    '  position: fixed;',
    '  bottom: 30px;',
    '  right: 30px;',
    '  padding: 15px 25px;',
    '  border-radius: 4px;',
    '  color: white;',
    '  font-weight: 500;',
    '  z-index: 999999;',
    '  box-shadow: 0 4px 20px rgba(0,0,0,0.2);',
    '  animation: wpecSlideIn 0.3s ease;',
    '}',
    '.wpec-toast-success { background: #28a745; }',
    '.wpec-toast-error { background: #dc3232; }',
    '.wpec-toast-warning { background: #ffc107; color: #333; }',
    '.wpec-toast-info { background: #17a2b8; }',
    '@keyframes wpecSlideIn {',
    '  from { transform: translateX(100%); opacity: 0; }',
    '  to { transform: translateX(0); opacity: 1; }',
    '}',
].join('\n');
document.head.appendChild(wpecToastStyles);
