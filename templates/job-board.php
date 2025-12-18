<?php
if (!defined('ABSPATH')) exit;

// Get shortcode attributes
$atts = shortcode_atts([
    'posts_per_page' => 10,
    'job_type' => '',
    'location' => '',
    'show_filters' => 'yes'
], $atts, 'job_board');

// Process filter parameters
$selected_type = isset($_GET['job_type']) ? sanitize_text_field($_GET['job_type']) : $atts['job_type'];
$selected_location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : $atts['location'];

// Get available terms
$job_types = get_terms([
    'taxonomy' => 'job_type',
    'hide_empty' => true,
]);

// Get locations - improved query
global $wpdb;
$locations = $wpdb->get_col(
    "SELECT DISTINCT meta_value 
     FROM {$wpdb->postmeta} 
     WHERE meta_key = '_job_location' 
     AND meta_value != '' 
     ORDER BY meta_value ASC"
);
?>

<div class="sjbm-job-board-wrapper">
    
    <?php if ($atts['show_filters'] === 'yes') : ?>
    <form method="get" class="sjbm-job-filters">
        <?php if ($job_types && !is_wp_error($job_types)) : ?>
        <div class="sjbm-filter-field">
            <label for="job_type">Job Type:</label>
            <select name="job_type" id="job_type">
                <option value="">All Types</option>
                <?php foreach ($job_types as $type) : ?>
                <option value="<?php echo esc_attr($type->slug); ?>" 
                    <?php selected($selected_type, $type->slug); ?>>
                    <?php echo esc_html($type->name); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($locations)) : ?>
        <div class="sjbm-filter-field">
            <label for="location">Location:</label>
            <select name="location" id="location">
                <option value="">All Locations</option>
                <?php foreach ($locations as $location) : ?>
                <option value="<?php echo esc_attr($location); ?>" 
                    <?php selected($selected_location, $location); ?>>
                    <?php echo esc_html($location); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <button type="submit" class="sjbm-filter-button">Filter</button>
        
        <?php if (!empty($_GET)) : ?>
        <a href="<?php echo remove_query_arg(['job_type', 'location', 'paged']); ?>" 
           class="sjbm-reset-button">
            Reset
        </a>
        <?php endif; ?>
        
        <?php 
        // Preserve other query vars
        foreach ($_GET as $key => $value) {
            if (!in_array($key, ['job_type', 'location', 'submit'])) {
                echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';
            }
        }
        ?>
    </form>
    <?php endif; ?>
    
    <div class="sjbm-job-listings">
        <?php
        $paged = max(1, get_query_var('paged') ?: (get_query_var('page') ?: 1));
        
        $args = [
            'post_type' => 'job',
            'posts_per_page' => absint($atts['posts_per_page']),
            'paged' => $paged,
            'post_status' => 'publish'
        ];
        
        // Job Type Filter
        if (!empty($selected_type)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'job_type',
                    'field' => 'slug',
                    'terms' => $selected_type
                ]
            ];
        }
        
        // Location Filter (exact match)
        if (!empty($selected_location)) {
            $args['meta_query'] = [
                [
                    'key' => '_job_location',
                    'value' => $selected_location,
                    'compare' => '='
                ]
            ];
        }
        
        $jobs_query = new WP_Query(apply_filters('sjbm_job_query_args', $args));
        
        if ($jobs_query->have_posts()) :
            while ($jobs_query->have_posts()) : $jobs_query->the_post();
                include(SJBM_PLUGIN_DIR . 'templates/content-job.php');
            endwhile;
            
            echo '<div class="sjbm-pagination">';
            echo paginate_links([
                'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => $jobs_query->max_num_pages,
                'prev_text' => __('&laquo; Previous'),
                'next_text' => __('Next &raquo;')
            ]);
            echo '</div>';
        else :
            echo '<p class="sjbm-no-jobs">No jobs found matching your criteria.</p>';
        endif;
        
        wp_reset_postdata();
        ?>
    </div>
</div>