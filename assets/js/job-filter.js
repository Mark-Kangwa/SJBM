jQuery(document).ready(function($) {
    // Handle job filtering
    $('.sjbm-job-filters').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $jobListings = $('.sjbm-job-listings');
        var $pagination = $('.sjbm-pagination');
        var $loading = $('<div class="sjbm-loading">Loading...</div>');
        
        // Show loading indicator
        $jobListings.before($loading);
        
        // Get form data
        var formData = $form.serialize();
		formData += '&action=sjbm_filter_jobs&nonce=' + sjbm_ajax.nonce;
        
        // Add AJAX action
        formData += '&action=sjbm_filter_jobs';
        
        // AJAX request
        $.ajax({
            url: sjbm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Fade out old content
                    $jobListings.css('opacity', '0.5');
                    
                    // Replace with new content
                    setTimeout(function() {
                        $jobListings.html(response.data.html);
                        if (response.data.pagination) {
                            $pagination.html(response.data.pagination);
                        }
                        
                        // Reinitialize any JavaScript that needs to run on the new content
                        $jobListings.find('.sjbm-job-item').css({
                            'opacity': '0',
                            'transform': 'translateY(20px)'
                        }).each(function(index) {
                            $(this).delay(100 * index).animate({
                                'opacity': '1',
                                'transform': 'translateY(0)'
                            }, 300);
                        });
                        
                        // Scroll to top of listings
                        $('html, body').animate({
                            scrollTop: $jobListings.offset().top - 100
                        }, 500);
                    }, 300);
                } else {
                    // Show error message
                    $jobListings.before('<div class="sjbm-filter-error">' + response.data.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
    var errorMsg = 'An error occurred while filtering jobs.';
    
    // Try to get server error message if available
    try {
        var response = JSON.parse(xhr.responseText);
        if (response.data && response.data.message) {
            errorMsg = response.data.message;
        }
    } catch (e) {}
    
    $jobListings.before('<div class="sjbm-filter-error">' + errorMsg + '</div>');
    
    // Remove loading indicator
    $loading.remove();
    $jobListings.css('opacity', '1');
            },
            complete: function() {
                $loading.remove();
                $jobListings.css('opacity', '1');
            }
        });
    });
    
    // Handle pagination links (if using AJAX pagination)
    $(document).on('click', '.sjbm-pagination a', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var $jobListings = $('.sjbm-job-listings');
        var $pagination = $('.sjbm-pagination');
        var $loading = $('<div class="sjbm-loading">Loading...</div>');
        
        // Show loading indicator
        $jobListings.before($loading);
        
        // AJAX request
        $.ajax({
            url: $link.attr('href'),
            type: 'GET',
            dataType: 'html',
            success: function(response) {
                // Extract the relevant parts from the response
                var $response = $(response);
                var newListings = $response.find('.sjbm-job-listings').html();
                var newPagination = $response.find('.sjbm-pagination').html();
                
                // Fade out old content
                $jobListings.css('opacity', '0.5');
                
                // Replace with new content
                setTimeout(function() {
                    $jobListings.html(newListings);
                    if (newPagination) {
                        $pagination.html(newPagination);
                    }
                    
                    // Reinitialize any JavaScript that needs to run on the new content
                    $jobListings.find('.sjbm-job-item').css({
                        'opacity': '0',
                        'transform': 'translateY(20px)'
                    }).each(function(index) {
                        $(this).delay(100 * index).animate({
                            'opacity': '1',
                            'transform': 'translateY(0)'
                        }, 300);
                    });
                    
                    // Update URL without reloading
                    window.history.pushState(null, null, $link.attr('href'));
                    
                    // Scroll to top of listings
                    $('html, body').animate({
                        scrollTop: $jobListings.offset().top - 100
                    }, 500);
                }, 300);
            },
            error: function() {
                $jobListings.before('<div class="sjbm-filter-error">An error occurred while loading the page.</div>');
            },
            complete: function() {
                $loading.remove();
                $jobListings.css('opacity', '1');
            }
        });
    });
});