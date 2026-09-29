
/* WP Extreme Customize - Login JavaScript */

jQuery(document).ready(function($) {
    'use strict';
    
    // Auto-focus en campo de usuario
    $('#user_login').trigger('focus');
    
    // Animación de entrada
    $('#login').addClass('wpec-fade-in');
    
    // Toggle "Recordarme"
    var rememberDefault = $('#rememberme').data('default');
    if (rememberDefault === 'yes') {
        $('#rememberme').prop('checked', true);
    }
    
    // Validación de formulario mejorada
    $('#loginform').on('submit', function(e) {
        var $form = $(this);
        var $submit = $form.find('#wp-submit');
        var $user = $('#user_login');
        var $pass = $('#user_pass');
        var hasError = false;
        
        if ($user.val().trim() === '') {
            $user.addClass('wpec-error').trigger('focus');
            hasError = true;
        } else {
            $user.removeClass('wpec-error');
        }
        
        if ($pass.val().trim() === '') {
            $pass.addClass('wpec-error');
            hasError = true;
        } else {
            $pass.removeClass('wpec-error');
        }
        
        if (hasError) {
            e.preventDefault();
            $submit.prop('disabled', false).removeClass('button-primary-disabled');
            return false;
        }
        
        // Deshabilitar botón durante envío
        $submit.prop('disabled', true).addClass('button-primary-disabled');
        $submit.val('<?php _e('Iniciando sesión...', 'wp-extreme-customize'); ?>');
    });
    
    // Limpiar errores al escribir
    $('#user_login, #user_pass').on('input', function() {
        $(this).removeClass('wpec-error');
        $('.wpec-error-message').remove();
    });
    
    // Partículas de fondo (opcional)
    if ($('body').hasClass('wpec-particles')) {
        initParticles();
    }
    
    function initParticles() {
        // Implementación simple de partículas
        var canvas = $('<canvas id="wpec-particles-canvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none;"></canvas>');
        $('body').prepend(canvas);
        
        var ctx = canvas[0].getContext('2d');
        var particles = [];
        var particleCount = 50;
        
        function resize() {
            canvas[0].width = window.innerWidth;
            canvas[0].height = window.innerHeight;
        }
        
        $(window).on('resize', resize);
        resize();
        
        for (var i = 0; i < particleCount; i++) {
            particles.push({
                x: Math.random() * canvas[0].width,
                y: Math.random() * canvas[0].height,
                vx: (Math.random() - 0.5) * 0.5,
                vy: (Math.random() - 0.5) * 0.5,
                size: Math.random() * 3 + 1,
                opacity: Math.random() * 0.5 + 0.1
            });
        }
        
        function animate() {
            ctx.clearRect(0, 0, canvas[0].width, canvas[0].height);
            
            particles.forEach(function(p) {
                p.x += p.vx;
                p.y += p.vy;
                
                if (p.x < 0) p.x = canvas[0].width;
                if (p.x > canvas[0].width) p.x = 0;
                if (p.y < 0) p.y = canvas[0].height;
                if (p.y > canvas[0].height) p.y = 0;
                
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(118, 75, 162, ' + p.opacity + ')';
                ctx.fill();
            });
            
            requestAnimationFrame(animate);
        }
        
        animate();
    }
});

// CSS para animaciones
var wpecLoginStyles = document.createElement('style');
wpecLoginStyles.textContent = `
    .wpec-fade-in {
        animation: wpecFadeIn 0.5s ease-out;
    }
    
    @keyframes wpecFadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .wpec-error {
        border-color: #dc3232 !important;
        box-shadow: 0 0 0 1px #dc3232 !important;
    }
    
    .wpec-error-message {
        color: #dc3232;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }
    
    .button-primary-disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
`;
document.head.appendChild(wpecLoginStyles);
