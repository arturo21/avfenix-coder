
/* WP Extreme Customize - Cookie Consent Script */

function wpec_accept_cookies() {
    var nonce = wpec_cookie.nonce;
    
    jQuery.ajax({
        url: wpec_cookie.accept_url,
        type: 'POST',
        data: {
            action: 'wpec_accept_cookies',
            nonce: nonce
        },
        success: function(response) {
            if (response.success) {
                // Ocultar el aviso
                jQuery('#wpec-cookie-notice').fadeOut(300);
            }
        }
    });
}
