jQuery(document).ready(function($) {
    // Handle application form submission
    $('#sjbm-application-form').on('submit', function(e) {
        e.preventDefault();
        
        // Check if deadline has passed (from localized data)
        if (typeof sjbm_job_data !== 'undefined' && sjbm_job_data.is_past_deadline) {
            var $message = $('.sjbm-form-message');
            $message.addClass('error').text(sjbm_job_data.deadline_passed_message).show();
            return false;
        }
        
        var $form = $(this);
        var $message = $form.find('.sjbm-form-message');
        var formData = new FormData($form[0]);
        
        // Reset message
        $message.hide().removeClass('success error').text('');
        
        // Show loading state
        $form.find('.sjbm-submit-button').prop('disabled', true).text('Submitting...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').text(response.data.message).show();
                    $form[0].reset();
                } else {
                    $message.addClass('error').text(response.data.message).show();
                    
                    // Show field errors if any
                    if (response.data.errors) {
                        $.each(response.data.errors, function(field, error) {
                            var $field = $form.find('[name="' + field + '"]');
                            var $error = $('<div class="sjbm-form-error">' + error + '</div>');
                            
                            $field.after($error);
                            $field.addClass('error');
                            
                            // Remove error on change
                            $field.on('change input', function() {
                                $error.remove();
                                $field.removeClass('error');
                            });
                        });
                    }
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred. Please try again.').show();
            },
            complete: function() {
                $form.find('.sjbm-submit-button').prop('disabled', false).text('Submit Application');
                
                // Scroll to message
                $('html, body').animate({
                    scrollTop: $message.offset().top - 100
                }, 500);
            }
        });
    });
    
    // Initialize filters
    $('.sjbm-job-filters select').on('change', function() {
        $(this).closest('form').submit();
    });

    // Disable form if deadline has passed (client-side check)
    if (typeof sjbm_job_data !== 'undefined' && sjbm_job_data.is_past_deadline) {
        $('#sjbm-application-form').find('input, textarea, button, select').prop('disabled', true);
        $('#sjbm-application-form').before(
            '<div class="notice notice-error">' +
            '<p>' + sjbm_job_data.deadline_passed_message + '</p>' +
            '</div>'
        );
    }
});