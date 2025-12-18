<?php
if (!defined('ABSPATH')) exit;

class SJBM_Job_Applications {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_application_post_type'));
        add_filter('manage_job_application_posts_columns', array(__CLASS__, 'add_application_columns'));
        add_action('manage_job_application_posts_custom_column', array(__CLASS__, 'render_application_columns'), 10, 2);
        add_action('restrict_manage_posts', array(__CLASS__, 'add_job_filter_dropdown'));
        add_filter('parse_query', array(__CLASS__, 'filter_applications_by_job'));
        add_action('admin_menu', array(__CLASS__, 'add_export_submenu'));
        add_action('admin_init', array(__CLASS__, 'handle_export'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_application_meta_boxes'));
        add_filter('post_updated_messages', array(__CLASS__, 'application_updated_messages'));
    }

    public static function application_updated_messages($messages) {
        $messages['job_application'] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => __('Application updated.', 'simple-job-board'),
            2 => __('Custom field updated.', 'simple-job-board'),
            3 => __('Custom field deleted.', 'simple-job-board'),
            4 => __('Application updated.', 'simple-job-board'),
            5 => isset($_GET['revision']) ? sprintf(__('Application restored to revision from %s', 'simple-job-board'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => __('Application published.', 'simple-job-board'),
            7 => __('Application saved.', 'simple-job-board'),
            8 => __('Application submitted.', 'simple-job-board'),
            9 => __('Application scheduled.', 'simple-job-board'),
            10 => __('Application draft updated.', 'simple-job-board')
        );
        
        return $messages;
    }

    public static function add_application_meta_boxes() {
        add_meta_box(
            'application_details',
            __('Application Details', 'simple-job-board'),
            array(__CLASS__, 'render_application_meta_box'),
            'job_application',
            'normal',
            'high'
        );
    }

    public static function render_application_meta_box($post) {
        $job_id = get_post_meta($post->ID, '_job_id', true);
        $applicant_name = get_post_meta($post->ID, '_applicant_name', true);
        $applicant_email = get_post_meta($post->ID, '_applicant_email', true);
        $cover_letter = get_post_meta($post->ID, '_cover_letter', true);
        $resume = get_post_meta($post->ID, '_resume', true);
        
        ?>
        <div class="sjbm-admin-application-details">
            <div class="sjbm-detail-row">
                <strong><?php _e('Job:', 'simple-job-board'); ?></strong>
                <?php if ($job_id) : ?>
                    <a href="<?php echo esc_url(get_edit_post_link($job_id)); ?>">
                        <?php echo esc_html(get_the_title($job_id)); ?>
                    </a>
                <?php else : ?>
                    <?php _e('N/A', 'simple-job-board'); ?>
                <?php endif; ?>
            </div>
            
            <div class="sjbm-detail-row">
                <strong><?php _e('Applicant Name:', 'simple-job-board'); ?></strong>
                <?php echo esc_html($applicant_name); ?>
            </div>
            
            <div class="sjbm-detail-row">
                <strong><?php _e('Applicant Email:', 'simple-job-board'); ?></strong>
                <a href="mailto:<?php echo esc_attr($applicant_email); ?>">
                    <?php echo esc_html($applicant_email); ?>
                </a>
            </div>
            
            <div class="sjbm-detail-row">
                <strong><?php _e('Cover Letter:', 'simple-job-board'); ?></strong>
                <div class="sjbm-cover-letter">
                    <?php echo wpautop(esc_html($cover_letter)); ?>
                </div>
            </div>
            
            <?php if (!empty($resume)) : ?>
                <div class="sjbm-detail-row">
                    <strong><?php _e('Resume:', 'simple-job-board'); ?></strong>
                    <a href="<?php echo esc_url($resume['url']); ?>" target="_blank">
                        <?php echo esc_html($resume['name']); ?>
                    </a>
                    <span class="sjbm-file-size">(<?php echo esc_html(size_format(filesize($resume['path']))); ?>)</span>
                </div>
            <?php endif; ?>
            
            <div class="sjbm-detail-row">
                <strong><?php _e('Submitted:', 'simple-job-board'); ?></strong>
                <?php echo esc_html(get_the_date('', $post)); ?> at <?php echo esc_html(get_the_time('', $post)); ?>
            </div>
        </div>
        <?php
    }
   
    public static function add_export_submenu() {
        add_submenu_page(
            'edit.php?post_type=job',
            __('Export Applications', 'simple-job-board'),
            __('Export Applications', 'simple-job-board'),
            'export',
            'sjbm-export-applications',
            array(__CLASS__, 'render_export_page')
        );
    }

    public static function render_export_page() {
        if (!current_user_can('export')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-job-board'));
        }
        
        $jobs = get_posts(array(
            'post_type' => 'job',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        ?>
        <div class="wrap">
            <h1><?php _e('Export Job Applications', 'simple-job-board'); ?></h1>
            
            <form method="post" action="<?php echo esc_url(admin_url('edit.php?post_type=job&page=sjbm-export-applications')); ?>">
                <?php wp_nonce_field('sjbm_export_nonce', 'sjbm_export_nonce'); ?>
                
                <div class="sjbm-export-options">
                    <h2><?php _e('Export Options', 'simple-job-board'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="export_job"><?php _e('Filter by Job:', 'simple-job-board'); ?></label>
                            </th>
                            <td>
                                <select name="export_job" id="export_job">
                                    <option value="all"><?php _e('All Jobs', 'simple-job-board'); ?></option>
                                    <?php foreach ($jobs as $job) : ?>
                                        <option value="<?php echo esc_attr($job->ID); ?>">
                                            <?php echo esc_html($job->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="export_date_from"><?php _e('Date Range:', 'simple-job-board'); ?></label>
                            </th>
                            <td>
                                <input type="date" name="export_date_from" id="export_date_from">
                                <?php _e('to', 'simple-job-board'); ?>
                                <input type="date" name="export_date_to" id="export_date_to">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="sjbm_export" class="button button-primary">
                        <?php _e('Export Applications', 'simple-job-board'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    public static function handle_export() {
        if (!isset($_POST['sjbm_export'])) {
            return;
        }

        check_admin_referer('sjbm_export_nonce', 'sjbm_export_nonce');

        if (!current_user_can('export')) {
            wp_die(__('You do not have sufficient permissions to export data.', 'simple-job-board'));
        }

        // Set up query args
        $args = array(
            'post_type' => 'job_application',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        // Filter by job if specified
        if (!empty($_POST['export_job']) && $_POST['export_job'] != 'all') {
            $args['meta_key'] = '_job_id';
            $args['meta_value'] = absint($_POST['export_job']);
        }

        // Filter by date range if specified
        if (!empty($_POST['export_date_from']) || !empty($_POST['export_date_to'])) {
            $args['date_query'] = array();

            if (!empty($_POST['export_date_from'])) {
                $args['date_query']['after'] = sanitize_text_field($_POST['export_date_from']);
            }

            if (!empty($_POST['export_date_to'])) {
                $args['date_query']['before'] = sanitize_text_field($_POST['export_date_to']);
            }

            $args['date_query']['inclusive'] = true;
        }

        // Get application IDs
        $application_ids = get_posts($args);

        if (empty($application_ids)) {
            wp_die(__('No applications found matching your criteria.', 'simple-job-board'));
        }

        // Prepare CSV data
        $csv_data = array();
        
        // CSV headers
        $csv_data[] = array(
            __('Application ID', 'simple-job-board'),
            __('Job Title', 'simple-job-board'),
            __('Applicant Name', 'simple-job-board'),
            __('Applicant Email', 'simple-job-board'),
            __('Cover Letter', 'simple-job-board'),
            __('Resume File', 'simple-job-board'),
            __('Date Submitted', 'simple-job-board'),
        );

        // Add application data
        foreach ($application_ids as $app_id) {
            $job_id = get_post_meta($app_id, '_job_id', true);
            $resume = get_post_meta($app_id, '_resume', true);
            
            $csv_data[] = array(
                $app_id,
                $job_id ? esc_html(get_the_title($job_id)) : __('N/A', 'simple-job-board'),
                esc_html(get_post_meta($app_id, '_applicant_name', true)),
                esc_html(get_post_meta($app_id, '_applicant_email', true)),
                esc_html(get_post_meta($app_id, '_cover_letter', true)),
                $resume ? esc_html($resume['name']) : __('N/A', 'simple-job-board'),
                esc_html(get_the_date('Y-m-d H:i:s', $app_id)),
            );
        }

        // Generate CSV file
        $filename = 'job-applications-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    public static function register_application_post_type() {
        $labels = array(
            'name' => __('Applications', 'simple-job-board'),
            'singular_name' => __('Application', 'simple-job-board'),
            'add_new' => __('Add New Application', 'simple-job-board'),
            'add_new_item' => __('Add New Application', 'simple-job-board'),
            'edit_item' => __('Edit Application', 'simple-job-board'),
            'new_item' => __('New Application', 'simple-job-board'),
            'view_item' => __('View Application', 'simple-job-board'),
            'search_items' => __('Search Applications', 'simple-job-board'),
            'not_found' => __('No applications found', 'simple-job-board'),
            'not_found_in_trash' => __('No applications found in Trash', 'simple-job-board'),
            'menu_name' => __('Applications', 'simple-job-board'),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=job',
            'query_var' => true,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => array('title'),
            'show_in_rest' => false,
        );

        register_post_type('job_application', $args);
    }

    public static function add_application_columns($columns) {
        $new_columns = array(
            'cb' => $columns['cb'],
            'title' => __('Applicant', 'simple-job-board'),
            'job' => __('Job', 'simple-job-board'),
            'applicant_email' => __('Email', 'simple-job-board'),
            'resume' => __('Resume', 'simple-job-board'),
            'date' => $columns['date']
        );
        return $new_columns;
    }

    public static function render_application_columns($column, $post_id) {
        switch ($column) {
            case 'job':
                $job_id = get_post_meta($post_id, '_job_id', true);
                if ($job_id) {
                    echo '<a href="' . esc_url(get_edit_post_link($job_id)) . '">' . esc_html(get_the_title($job_id)) . '</a>';
                }
                break;
            case 'applicant_email':
                echo esc_html(get_post_meta($post_id, '_applicant_email', true));
                break;
            case 'resume':
                $resume = get_post_meta($post_id, '_resume', true);
                if ($resume) {
                    echo '<a href="' . esc_url($resume['url']) . '" target="_blank">' . __('Download', 'simple-job-board') . '</a>';
                }
                break;
        }
    }

    public static function add_job_filter_dropdown() {
        global $typenow;
        
        if ($typenow == 'job_application') {
            $jobs = get_posts(array(
                'post_type' => 'job',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ));
            
            echo '<select name="job_id" id="job_id">';
            echo '<option value="">' . __('All Jobs', 'simple-job-board') . '</option>';
            
            $current_job = isset($_GET['job_id']) ? absint($_GET['job_id']) : '';
            
            foreach ($jobs as $job) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($job->ID),
                    $job->ID == $current_job ? ' selected="selected"' : '',
                    esc_html($job->post_title)
                );
            }
            
            echo '</select>';
        }
    }

    public static function filter_applications_by_job($query) {
        global $pagenow;
        
        if (is_admin() && $pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'job_application' && !empty($_GET['job_id'])) {
            $query->query_vars['meta_key'] = '_job_id';
            $query->query_vars['meta_value'] = absint($_GET['job_id']);
        }
    }

    public static function get_application_count($job_id) {
        $args = array(
            'post_type' => 'job_application',
            'posts_per_page' => -1,
            'meta_key' => '_job_id',
            'meta_value' => absint($job_id),
            'fields' => 'ids',
        );
        
        $applications = get_posts($args);
        return count($applications);
    }

    public static function create_application($job_id, $data, $files) {
        $name = sanitize_text_field($data['applicant_name']);
        $email = sanitize_email($data['applicant_email']);
        $cover_letter = wp_kses_post($data['cover_letter']);
        
        // Handle file upload
        $resume = array();
        if (!empty($files['resume']) && $files['resume']['error'] == 0) {
            $allowed_types = array('pdf', 'doc', 'docx');
            $file_ext = pathinfo($files['resume']['name'], PATHINFO_EXTENSION);
            
            if (in_array(strtolower($file_ext), $allowed_types)) {
                $upload_dir = wp_upload_dir();
                $upload_path = $upload_dir['path'] . '/' . sanitize_file_name($files['resume']['name']);
                
                // Create uploads/sjbm directory if it doesn't exist
                $custom_dir = $upload_dir['basedir'] . '/sjbm';
                if (!file_exists($custom_dir)) {
                    wp_mkdir_p($custom_dir);
                }
                
                $custom_path = $custom_dir . '/' . sanitize_file_name($files['resume']['name']);
                
                if (move_uploaded_file($files['resume']['tmp_name'], $custom_path)) {
                    $resume = array(
                        'path' => $custom_path,
                        'url' => $upload_dir['baseurl'] . '/sjbm/' . sanitize_file_name($files['resume']['name']),
                        'name' => sanitize_file_name($files['resume']['name']),
                    );
                }
            }
        }
        
        // Create application post
        $application_id = wp_insert_post(array(
            'post_type' => 'job_application',
            'post_title' => $name . ' - ' . get_the_title($job_id),
            'post_status' => 'publish',
        ));
        
        if ($application_id) {
            update_post_meta($application_id, '_job_id', absint($job_id));
            update_post_meta($application_id, '_applicant_name', $name);
            update_post_meta($application_id, '_applicant_email', $email);
            update_post_meta($application_id, '_cover_letter', $cover_letter);
            
            if (!empty($resume)) {
                update_post_meta($application_id, '_resume', $resume);
            }
            
            return $application_id;
        }
        
        return false;
    }
}