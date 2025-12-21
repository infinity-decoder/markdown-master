jQuery(document).ready(function($) {
    
    function showToast(message, type) {
        let toast = $('<div class="cotex-toast">' + message + '</div>');
        if(type === 'error') toast.css('border-color', '#ff4d4d');
        $('body').append(toast);
        setTimeout(() => toast.addClass('show'), 100);
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    $('.cotex-toggle-input').on('change', function() {
        let slug = $(this).data('slug');
        let active = $(this).is(':checked');
        let card = $(this).closest('.cotex-card');
        let badge = card.find('.cotex-status-badge');

        // Optimistic UI update
        if (active) {
            card.addClass('active');
            badge.text('Active');
        } else {
            card.removeClass('active');
            badge.text('Inactive');
        }

        $.ajax({
            url: cotexVars.root + 'cotex/v1/modules',
            method: 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', cotexVars.nonce );
            },
            data: {
                slug: slug,
                active: active
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                } else {
                    showToast(response.message, 'error');
                    // Revert UI on failure
                    $(this).prop('checked', !active); 
                }
            },
            error: function() {
                showToast('Connection Error', 'error');
                $(this).prop('checked', !active);
            }
        });
    });

});
