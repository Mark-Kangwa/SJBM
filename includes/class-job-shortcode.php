<?php
if (!defined('ABSPATH')) exit;

class SJBM_Job_Shortcode {
    public static function init() {
        add_shortcode('job_board', array(__CLASS__, 'render_job_board'));
        add_action('init', array(__CLASS__, 'register_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_action('wp_ajax_sjbm_filter_jobs', array(__CLASS__, 'ajax_filter_jobs'));
        add_action('wp_ajax_nopriv_sjbm_filter_jobs', array(__CLASS__, 'ajax_filter_jobs'));
        
        // Localize script with AJAX URL and nonce
        add_action('wp_enqueue_scripts', function() {
            wp_localize_script('sjbm-script', 'sjbm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sjbm_nonce')
            ));
        });
    }

    public static function register_rewrite_rules() {
        add_rewrite_rule('^jobs/([^/]+)/?', 'index.php?post_type=job&job_type=$matches[1]', 'top');
    }

    public static function add_query_vars($vars) {
        $vars[] = 'job_type';
        return $vars;
    }

    public static function render_job_board($atts) {
        $default_atts = array(
            'posts_per_page' => 10,
            'job_type' => '',
            'location' => '',
            'show_filters' => 'yes'
        );
        
        $atts = shortcode_atts($default_atts, $atts, 'job_board');
        
        ob_start();
        
        // Get filter values
        $selected_type = isset($_GET['job_type']) ? sanitize_text_field($_GET['job_type']) : $atts['job_type'];
        $selected_location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : $atts['location'];

        // Output filters if enabled - wrapped in container
        if ($atts['show_filters'] === 'yes') {
            echo '<div class="sjbm-filters-container">';
            self::render_job_filters($selected_type, $selected_location);
            echo '</div>';
        }
        
        // Main job board container
        echo '<div class="sjbm-job-board-wrapper">';
        
        // Get and display job listings
        $jobs_html = self::get_jobs_html($atts, $selected_type, $selected_location);
        echo $jobs_html;
        
        echo '</div>'; // Close .sjbm-job-board-wrapper
        
        return ob_get_clean();
    }

    /**
     * Get jobs HTML content for both initial load and AJAX requests
     */
    private static function get_jobs_html($atts, $selected_type, $selected_location, $paged = null) {
        $paged = $paged ?: max(1, get_query_var('paged') ?: (get_query_var('page') ?: 1));
        
        $args = array(
            'post_type' => 'job',
            'posts_per_page' => absint($atts['posts_per_page']),
            'paged' => $paged,
            'post_status' => 'publish'
        );
        
        if (!empty($selected_type)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'job_type',
                    'field' => 'slug',
                    'terms' => $selected_type,
                )
            );
        }
        
        if (!empty($selected_location)) {
            $args['meta_query'] = array(
                array(
                    'key' => '_job_location',
                    'value' => $selected_location,
                    'compare' => '=',
                )
            );
        }
        
        $jobs = new WP_Query(apply_filters('sjbm_job_query_args', $args));
        
        ob_start();
        
        if ($jobs->have_posts()) {
            echo '<div class="sjbm-job-listings sjbm-job-listings-list">';
            
            while ($jobs->have_posts()) {
                $jobs->the_post();
                echo self::render_job_item(get_the_ID());
            }
            
            echo '</div>';
            
            // Pagination
            echo '<div class="sjbm-pagination">';
            echo paginate_links(array(
                'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => $jobs->max_num_pages,
                'prev_text' => __('&laquo; Previous', 'simple-job-board'),
                'next_text' => __('Next &raquo;', 'simple-job-board'),
            ));
            echo '</div>';
        } else {
            echo '<div class="sjbm-no-jobs">';
            echo '<p>' . esc_html__('No jobs found matching your criteria.', 'simple-job-board') . '</p>';
            echo '</div>';
        }
        
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function ajax_filter_jobs() {
        header('Content-Type: application/json');
        
        try {
            // Verify nonce first
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sjbm_nonce')) {
                throw new Exception(__('Security check failed. Please refresh the page and try again.', 'simple-job-board'));
            }

            // Get and sanitize parameters
            $posts_per_page = isset($_POST['posts_per_page']) ? absint($_POST['posts_per_page']) : 10;
            $job_type = isset($_POST['job_type']) ? sanitize_text_field($_POST['job_type']) : '';
            $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
            $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;

            // Create a temporary $atts array for get_jobs_html()
            $atts = array(
                'posts_per_page' => $posts_per_page,
                'job_type' => $job_type,
                'location' => $location,
                'show_filters' => 'no' // We don't need filters in AJAX response
            );

            // Get the HTML content
            $html = self::get_jobs_html($atts, $job_type, $location, $paged);

            wp_send_json_success(array(
                'html' => $html
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    private static function render_job_filters($selected_type, $selected_location) {
        $job_types = get_terms(array(
            'taxonomy' => 'job_type',
            'hide_empty' => true,
        ));
        
        $locations = self::get_job_locations();
        
        if (is_wp_error($job_types)) {
            $job_types = array();
        }
        ?>
        <form method="get" class="sjbm-job-filters" id="sjbm-job-filters">
            <?php 
            // Preserve other query parameters
            foreach ($_GET as $key => $value) {
                if (!in_array($key, ['job_type', 'location', 'submit'])) {
                    echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';
                }
            }
            ?>
            
            <?php if (!empty($job_types)) : ?>
                <div class="sjbm-filter-field">
                    <label for="job_type"><?php esc_html_e('Job Type:', 'simple-job-board'); ?></label>
                    <select name="job_type" id="job_type">
                        <option value=""><?php esc_html_e('All Types', 'simple-job-board'); ?></option>
                        <?php foreach ($job_types as $type) : ?>
                            <option value="<?php echo esc_attr($type->slug); ?>" <?php selected($selected_type, $type->slug); ?>>
                                <?php echo esc_html($type->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($locations)) : ?>
                <div class="sjbm-filter-field">
                    <label for="location"><?php esc_html_e('Location:', 'simple-job-board'); ?></label>
                    <select name="location" id="location">
                        <option value=""><?php esc_html_e('All Locations', 'simple-job-board'); ?></option>
                        <?php foreach ($locations as $location) : ?>
                            <option value="<?php echo esc_attr($location); ?>" <?php selected($selected_location, $location); ?>>
                                <?php echo esc_html($location); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <button type="submit" class="sjbm-filter-button"><?php esc_html_e('Filter', 'simple-job-board'); ?></button>
            <?php if (!empty($_GET)) : ?>
                <a href="<?php echo esc_url(remove_query_arg(['job_type', 'location', 'paged'])); ?>" class="sjbm-reset-button">
                    <?php esc_html_e('Reset', 'simple-job-board'); ?>
                </a>
            <?php endif; ?>
        </form>
        <?php
    }

    private static function get_job_locations() {
        global $wpdb;
        
        $locations = $wpdb->get_col(
            "SELECT DISTINCT meta_value 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_job_location' 
             AND meta_value != '' 
             ORDER BY meta_value ASC"
        );
        
        return is_array($locations) ? $locations : array();
    }

    private static function render_job_item($job_id) {
        $job_location = get_post_meta($job_id, '_job_location', true);
        $job_types = get_the_terms($job_id, 'job_type');
        $salary = get_post_meta($job_id, '_salary', true);
        $deadline = get_post_meta($job_id, '_application_deadline', true);
        $is_past_deadline = $deadline && strtotime($deadline) < current_time('timestamp');
        
        ob_start();
        ?>
        <div class="sjbm-job-listing-container">
        <article class="sjbm-job-item <?php echo $is_past_deadline ? 'sjbm-job-expired' : ''; ?>">
            <header class="sjbm-job-header">
                <h3 class="sjbm-job-title">
                    <a href="<?php echo esc_url(get_permalink($job_id)); ?>" style="color: #4464AD; font-family: poppins;">
                        <?php echo esc_html(get_the_title($job_id)); ?>
                    </a>
                </h3>
                
                <div class="sjbm-job-meta" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px 15px; margin-bottom: 10px;">
                    <?php if ($job_types && !is_wp_error($job_types)) : ?>
                        <span class="sjbm-job-type" style="color: #008000; font-weight: bold;">
                            <?php echo esc_html(implode(', ', wp_list_pluck($job_types, 'name'))); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($job_location) : ?>
                        <span class="sjbm-job-location" style="color: #000000;">
                            <?php echo esc_html($job_location); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($salary) : ?>
                        <span class="sjbm-job-salary" style="color: #000000; font-weight: bold;">
                            <?php echo esc_html($salary); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($deadline) : ?>
                        <span class="sjbm-job-deadline" style="color: #FF0000;">
                            <?php 
                            printf(
                                esc_html__('Apply by: %s', 'simple-job-board'),
                                esc_html(date_i18n(get_option('date_format'), strtotime($deadline)))
                            );
                            ?>
                            <?php if ($is_past_deadline) : ?>
                                <span class="sjbm-deadline-passed" style="color: #FF0000; font-style: italic;">
                                    (<?php esc_html_e('Closed', 'simple-job-board'); ?>)
                                </span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>
            
            <div class="sjbm-job-content">
                <div class="sjbm-job-excerpt">
                    <?php echo esc_html(wp_trim_words(get_the_excerpt($job_id), 30)); ?>
                </div>
            </div>
            
            <footer class="sjbm-job-actions">
                <a href="<?php echo esc_url(get_permalink($job_id)); ?>" class="sjbm-view-details">
                    <?php esc_html_e('View Details', 'simple-job-board'); ?>
                </a>
            </footer>
        </article>
    </div>
        <?php
        return ob_get_clean();
    }
}