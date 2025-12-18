<?php
if (!defined('ABSPATH')) exit;

class SJBM_Job_Form_Handler {
    public static function init() {
        add_action('wp_ajax_sjbm_submit_application', array(__CLASS__, 'handle_application_submission'));
        add_action('wp_ajax_nopriv_sjbm_submit_application', array(__CLASS__, 'handle_application_submission'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_shortcode('job_application_form', array(__CLASS__, 'render_application_form_shortcode'));
    }

    public static function enqueue_scripts() {
        if (is_singular('job')) {
            wp_enqueue_script(
                'sjbm-application-form', 
                SJBM_PLUGIN_URL . 'assets/js/application-form.js', 
                array('jquery'), 
                SJBM_VERSION, 
                true
            );
			
			// Localize script with AJAX URL and nonce
            wp_localize_script('sjbm-application-form', 'sjbm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sjbm_application_nonce')
            ));
		
            // Localize script with deadline check
            $job_id = get_queried_object_id();
            $deadline = get_post_meta($job_id, '_application_deadline', true);
            $is_past_deadline = $deadline && strtotime($deadline) < current_time('timestamp');
            
            wp_localize_script('sjbm-application-form', 'sjbm_job_data', array(
                'is_past_deadline' => $is_past_deadline,
                'deadline_passed_message' => esc_html__('Applications for this position are now closed.', 'simple-job-board')
            ));
        }
    }

    public static function render_application_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'job_id' => 0,
        ), $atts);
        
        return self::render_application_form(absint($atts['job_id']));
    }

    public static function render_application_form($job_id) {
        if (!$job_id || 'job' !== get_post_type($job_id)) {
            return '';
        }
        
        $deadline = get_post_meta($job_id, '_application_deadline', true);
        $is_past_deadline = $deadline && strtotime($deadline) < current_time('timestamp');
        $application_link = get_post_meta($job_id, '_application_link', true);
        
        ob_start();
        ?>
        <div class="sjbm-application-form">
            <h3><?php esc_html_e('Apply for this position', 'simple-job-board'); ?></h3>
            
            <?php if ($is_past_deadline) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Applications for this position are now closed.', 'simple-job-board'); ?></p>
                </div>
            <?php elseif (!empty($application_link)) : ?>
                <div class="sjbm-external-application">
                    <p><?php esc_html_e('Please apply through our external application system:', 'simple-job-board'); ?></p>
                    <a href="<?php echo esc_url($application_link); ?>" class="sjbm-apply-button" target="_blank">
                        <?php esc_html_e('Apply Now', 'simple-job-board'); ?>
                    </a>
                </div>
            <?php else : ?>
              <form id="sjbm-application-form" method="post" enctype="multipart/form-data">
              <input type="hidden" name="job_id" value="<?php echo absint($job_id); ?>">
              <input type="hidden" name="action" value="sjbm_submit_application">
               <?php wp_nonce_field('sjbm_application_nonce', 'sjbm_nonce'); ?>

                    
                    <div class="sjbm-form-field">
                        <label for="applicant_name"><?php esc_html_e('Full Name', 'simple-job-board'); ?>*</label>
                        <input type="text" name="applicant_name" id="applicant_name" required>
                    </div>
                    
                    <div class="sjbm-form-field">
                        <label for="applicant_email"><?php esc_html_e('Email', 'simple-job-board'); ?>*</label>
                        <input type="email" name="applicant_email" id="applicant_email" required>
                    </div>
                    
                    <div class="sjbm-form-field">
                        <label for="phone"><?php esc_html_e('Phone Number', 'simple-job-board'); ?></label>
                        <input type="tel" name="phone" id="phone">
                    </div>
                    
                    <div class="sjbm-form-field">
                        <label for="cover_letter"><?php esc_html_e('Cover Letter', 'simple-job-board'); ?>*</label>
                        <textarea name="cover_letter" id="cover_letter" rows="6" required></textarea>
                    </div>
                    
                    <div class="sjbm-form-field">
                        <label for="resume"><?php esc_html_e('Resume (PDF or DOC)', 'simple-job-board'); ?>*</label>
                        <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" required>
                        <p class="sjbm-file-hint"><?php esc_html_e('Max file size: 2MB', 'simple-job-board'); ?></p>
                    </div>
                    
                    <div class="sjbm-form-field">
                        <button type="submit" class="sjbm-submit-button">
                            <?php esc_html_e('Submit Application', 'simple-job-board'); ?>
                        </button>
                    </div>
                    
                    <div class="sjbm-form-message"></div>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_application_submission() {
        // Verify nonce first
        if (!isset($_POST['sjbm_nonce']) || !wp_verify_nonce($_POST['sjbm_nonce'], 'sjbm_application_nonce')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed. Please try again.', 'simple-job-board')
            ));
        }
        
        $job_id = isset($_POST['job_id']) ? absint($_POST['job_id']) : 0;
        
        // Validate job exists
        if (!$job_id || !get_post($job_id) || 'job' !== get_post_type($job_id)) {
            wp_send_json_error(array(
                'message' => esc_html__('Invalid job listing.', 'simple-job-board')
            ));
        }
        
        // Check application deadline
        $deadline = get_post_meta($job_id, '_application_deadline', true);
        if ($deadline && strtotime($deadline) < current_time('timestamp')) {
            wp_send_json_error(array(
                'message' => esc_html__('Application deadline has passed. Submissions are no longer accepted.', 'simple-job-board')
            ));
        }
        
        // Check if external application link exists
        $application_link = get_post_meta($job_id, '_application_link', true);
        if (!empty($application_link)) {
            wp_send_json_error(array(
                'message' => esc_html__('Please apply through the external application link provided.', 'simple-job-board')
            ));
        }
        
        // Validate required fields
        $required_fields = array(
            'applicant_name' => esc_html__('Name is required.', 'simple-job-board'),
            'applicant_email' => esc_html__('Valid email is required.', 'simple-job-board'),
            'cover_letter' => esc_html__('Cover letter is required.', 'simple-job-board'),
        );
        
        $errors = array();
        
        foreach ($required_fields as $field => $error_message) {
            if (empty($_POST[$field])) {
                $errors[$field] = $error_message;
            }
        }
        
        // Validate email format
        if (!empty($_POST['applicant_email']) && !is_email($_POST['applicant_email'])) {
            $errors['applicant_email'] = esc_html__('Please enter a valid email address.', 'simple-job-board');
        }
        
        // Validate file upload
        if (empty($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            $errors['resume'] = esc_html__('Resume file is required.', 'simple-job-board');
        } else {
            $allowed_types = array('pdf', 'doc', 'docx');
            $file_ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                $errors['resume'] = esc_html__('Only PDF and DOC files are allowed.', 'simple-job-board');
            }
            
            if ($_FILES['resume']['size'] > 2 * 1024 * 1024) { // 2MB limit
                $errors['resume'] = esc_html__('File size exceeds 2MB limit.', 'simple-job-board');
            }
        }
        
        // Return errors if any
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => esc_html__('Please correct the errors below.', 'simple-job-board'),
                'errors' => $errors,
            ));
        }
        
        // Create application
        $application_id = SJBM_Job_Applications::create_application($job_id, $_POST, $_FILES);
        
        if ($application_id) {
            // Send notification email
            self::send_notification_email($job_id, $_POST, $_FILES);
            
            wp_send_json_success(array(
                'message' => esc_html__('Your application has been submitted successfully!', 'simple-job-board'),
            ));
        } else {
            wp_send_json_error(array(
                'message' => esc_html__('There was an error submitting your application. Please try again.', 'simple-job-board'),
            ));
        }
    }

    private static function send_notification_email($job_id, $data, $files) {
        $job = get_post($job_id);
        $admin_email = get_option('admin_email');
        
        $to = get_post_meta($job_id, '_application_email', true);
        if (empty($to)) {
            $to = $admin_email;
        }
        
        $subject = sprintf(__('New Application for: %s', 'simple-job-board'), $job->post_title);
        
        $message = sprintf(__('A new application has been submitted for the job "%s".', 'simple-job-board'), $job->post_title) . "\n\n";
        $message .= __('Applicant Details:', 'simple-job-board') . "\n";
        $message .= __('Name:', 'simple-job-board') . ' ' . sanitize_text_field($data['applicant_name']) . "\n";
        $message .= __('Email:', 'simple-job-board') . ' ' . sanitize_email($data['applicant_email']) . "\n";
        
        if (!empty($data['phone'])) {
            $message .= __('Phone:', 'simple-job-board') . ' ' . sanitize_text_field($data['phone']) . "\n";
        }
        
        $message .= "\n" . __('Cover Letter:', 'simple-job-board') . "\n" . wp_kses_post($data['cover_letter']) . "\n\n";
        
        // Add deadline info if set
        $deadline = get_post_meta($job_id, '_application_deadline', true);
        if ($deadline) {
            $message .= __('Application Deadline:', 'simple-job-board') . ' ' . date_i18n(get_option('date_format'), strtotime($deadline)) . "\n";
        }
        
        $message .= __('You can view all applications in the WordPress dashboard.', 'simple-job-board') . "\n";
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>',
        );
        
        wp_mail($to, $subject, $message, $headers);
    }
}