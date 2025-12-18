jQuery(document).ready(function($) {
    $('#sjbm-application-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $message = $form.find('.sjbm-form-message');
        $message.removeClass('success error').html('').hide();
        
        // Clear previous errors
        $form.find('.sjbm-form-error').remove();
        $form.find('.error').removeClass('error');
        
        // Check if deadline has passed (from localized data)
        if (typeof sjbm_job_data !== 'undefined' && sjbm_job_data.is_past_deadline) {
            $message.addClass('error').text(sjbm_job_data.deadline_passed_message).show();
            return false;
        }
        
        // Validate required fields
        var isValid = true;
        $form.find('[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                var $error = $('<div class="sjbm-form-error">' + $(this).attr('name') + ' is required</div>');
                $(this).after($error);
                isValid = false;
            }
        });
        
        if (!isValid) {
            $message.addClass('error').text('Please fill in all required fields.').show();
            return;
        }
        
        // Check file size if file is selected
        var resumeInput = $form.find('input[type="file"]')[0];
        if (resumeInput.files.length > 0 && resumeInput.files[0].size > 2 * 1024 * 1024) {
            $message.addClass('error').text('File size exceeds 2MB limit.').show();
            return;
        }
        
        // Prepare form data
        var formData = new FormData($form[0]);
        
        // Add AJAX action
        formData.append('action', 'sjbm_submit_application');
        
        // Disable submit button
        $form.find('button[type="submit"]').prop('disabled', true).text('Submitting...');
        
        // AJAX request
        $.ajax({
            url: sjbm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                clearTimeout(ajaxTimeout);
    if (response.success) {
        // For filtered results
        if (response.data.html) {
            var $newContent = $(response.data.html);
            $('.sjbm-job-listings').html($newContent);
            
            // Reinitialize any JavaScript that needs to run on the new content
            $newContent.find('.sjbm-job-item').css({
                'opacity': '0',
                'transform': 'translateY(20px)'
            }).each(function(index) {
                $(this).delay(100 * index).animate({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                }, 300);
            });
        }
        // For form submissions
        else {
            $form[0].reset();
            $message.addClass('success').text(response.data.message).show();
        }
    } else {
        // Error handling remains the same
    }
            },
			
			
            error: function(xhr, status, error) {
                $message.addClass('error').text('An error occurred. Please try again.').show();
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false).text('Submit Application');
                
                // Scroll to message
                $('html, body').animate({
                    scrollTop: $message.offset().top - 100
                }, 500);
            }
        });
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