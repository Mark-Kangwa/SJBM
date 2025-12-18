<?php
if (!defined('ABSPATH')) exit;

class SJBM_Job_Post_Type {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_job_post_type'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_job_meta_boxes'));
        add_action('save_post_job', array(__CLASS__, 'save_job_meta'));
        add_filter('manage_job_posts_columns', array(__CLASS__, 'add_job_columns'));
        add_action('manage_job_posts_custom_column', array(__CLASS__, 'render_job_columns'), 10, 2);
        add_filter('post_updated_messages', array(__CLASS__, 'job_updated_messages'));
    }

    public static function register_job_post_type() {
        $labels = array(
            'name' => __('Jobs', 'simple-job-board'),
            'singular_name' => __('Job', 'simple-job-board'),
            'add_new' => __('Add New Job', 'simple-job-board'),
            'add_new_item' => __('Add New Job', 'simple-job-board'),
            'edit_item' => __('Edit Job', 'simple-job-board'),
            'new_item' => __('New Job', 'simple-job-board'),
            'view_item' => __('View Job', 'simple-job-board'),
            'search_items' => __('Search Jobs', 'simple-job-board'),
            'not_found' => __('No jobs found', 'simple-job-board'),
            'not_found_in_trash' => __('No jobs found in Trash', 'simple-job-board'),
            'menu_name' => __('Jobs', 'simple-job-board'),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'jobs'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 20,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'menu_icon' => 'dashicons-businessman',
            'show_in_rest' => true,
        );

        register_post_type('job', $args);

        // Register Job Type taxonomy
        register_taxonomy('job_type', 'job', array(
            'label' => __('Job Type', 'simple-job-board'),
            'hierarchical' => false,
            'labels' => array(
                'name' => __('Job Types', 'simple-job-board'),
                'singular_name' => __('Job Type', 'simple-job-board'),
            ),
            'public' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'job-type'),
        ));

        // Add default terms
        $default_types = array('Full-Time', 'Part-Time', 'Remote', 'Hybrid');
        foreach ($default_types as $type) {
            if (!term_exists($type, 'job_type')) {
                wp_insert_term($type, 'job_type', array(
                'slug' => sanitize_title($type)
            ));
            }
        }
    }

    public static function job_updated_messages($messages) {
        global $post;
        
        $messages['job'] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => __('Job updated.', 'simple-job-board'),
            2 => __('Custom field updated.', 'simple-job-board'),
            3 => __('Custom field deleted.', 'simple-job-board'),
            4 => __('Job updated.', 'simple-job-board'),
            5 => isset($_GET['revision']) ? sprintf(__('Job restored to revision from %s', 'simple-job-board'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => __('Job published.', 'simple-job-board'),
            7 => __('Job saved.', 'simple-job-board'),
            8 => __('Job submitted.', 'simple-job-board'),
            9 => sprintf(__('Job scheduled for: <strong>%1$s</strong>.', 'simple-job-board'), date_i18n(__('M j, Y @ G:i', 'simple-job-board'), strtotime($post->post_date))),
            10 => __('Job draft updated.', 'simple-job-board')
        );
        
        return $messages;
    }

    public static function add_job_meta_boxes() {
        add_meta_box(
            'job_details',
            __('Job Details', 'simple-job-board'),
            array(__CLASS__, 'render_job_meta_box'),
            'job',
            'normal',
            'high'
        );
    }

    public static function render_job_meta_box($post) {
        wp_nonce_field('sjbm_job_meta_nonce', 'sjbm_job_meta_nonce');
        
        $job_location = get_post_meta($post->ID, '_job_location', true);
        $application_email = get_post_meta($post->ID, '_application_email', true);
        $application_link = get_post_meta($post->ID, '_application_link', true);
        $application_deadline = get_post_meta($post->ID, '_application_deadline', true);
        $salary = get_post_meta($post->ID, '_salary', true);
        
        ?>
        <div class="sjbm-meta-field">
            <label for="job_location"><?php _e('Job Location:', 'simple-job-board'); ?></label>
            <input type="text" id="job_location" name="job_location" value="<?php echo esc_attr($job_location); ?>" class="widefat">
        </div>

        <div class="sjbm-meta-field">
            <label for="salary"><?php _e('Salary:', 'simple-job-board'); ?></label>
            <input type="text" id="salary" name="salary" value="<?php echo esc_attr($salary); ?>" class="widefat">
        </div>

        <div class="sjbm-meta-field">
            <label for="application_deadline"><?php _e('Application Deadline:', 'simple-job-board'); ?></label>
            <input type="date" id="application_deadline" name="application_deadline" value="<?php echo esc_attr($application_deadline); ?>" class="widefat">
        </div>
        
        <div class="sjbm-meta-field">
            <label for="application_email"><?php _e('Application Email:', 'simple-job-board'); ?></label>
            <input type="email" id="application_email" name="application_email" value="<?php echo esc_attr($application_email); ?>" class="widefat">
        </div>
        
        <div class="sjbm-meta-field">
            <label for="application_link"><?php _e('Application Link:', 'simple-job-board'); ?></label>
            <input type="url" id="application_link" name="application_link" value="<?php echo esc_attr($application_link); ?>" class="widefat">
            <p class="description"><?php _e('Provide either an email or a link, not both.', 'simple-job-board'); ?></p>
        </div>
        <?php
    }

    public static function save_job_meta($post_id) {
        // Verify nonce
        if (!isset($_POST['sjbm_job_meta_nonce']) || !wp_verify_nonce($_POST['sjbm_job_meta_nonce'], 'sjbm_job_meta_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save/update meta data
        $fields = array(
            'job_location' => 'sanitize_text_field',
            'salary' => 'sanitize_text_field',
            'application_deadline' => 'sanitize_text_field',
            'application_email' => 'sanitize_email',
            'application_link' => 'esc_url_raw'
        );

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    public static function add_job_columns($columns) {
        $new_columns = array(
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'job_type' => __('Type', 'simple-job-board'),
            'job_location' => __('Location', 'simple-job-board'),
            'salary' => __('Salary', 'simple-job-board'),
            'application_deadline' => __('Deadline', 'simple-job-board'),
            'applications' => __('Applications', 'simple-job-board'),
            'date' => $columns['date']
        );
        return $new_columns;
    }

    public static function render_job_columns($column, $post_id) {
        switch ($column) {
            case 'job_location':
                echo esc_html(get_post_meta($post_id, '_job_location', true));
                break;
                
            case 'salary':
                echo esc_html(get_post_meta($post_id, '_salary', true));
                break;
                
            case 'job_type':
                echo get_the_term_list($post_id, 'job_type', '', ', ', '');
                break;

            case 'application_deadline':
                $deadline = get_post_meta($post_id, '_application_deadline', true);
                echo $deadline ? esc_html(date_i18n(get_option('date_format'), strtotime($deadline))) : __('No deadline', 'simple-job-board');
                break;

            case 'applications':
                $count = SJBM_Job_Applications::get_application_count($post_id);
                echo '<a href="' . esc_url(admin_url('edit.php?post_type=job_application&job_id=' . $post_id)) . '">' . absint($count) . '</a>';
                break;
        }
    }
}