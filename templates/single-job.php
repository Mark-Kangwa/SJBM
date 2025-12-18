<?php
/**
 * Single Job Template
 */

get_header();

while (have_posts()) : the_post();
    $job_id = get_the_ID();
    $job_location = get_post_meta($job_id, '_job_location', true);
    $job_types = get_the_terms($job_id, 'job_type');
	$salary = get_post_meta($job_id, '_salary', true);
	$deadline = get_post_meta($job_id, '_application_deadline', true);
    $is_past_deadline = $deadline && strtotime($deadline) < current_time('timestamp');
    $application_link = get_post_meta($job_id, '_application_link', true);
    
   ?>
    
    <div class="sjbm-single-job" style="max-width: 800px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="sjbm-job-header">
                <h1 class="sjbm-job-title" style="color: #4464AD; font-size: 28px; margin-bottom: 10px; font-family:  poppins;"><?php the_title(); ?></h1>
                
                <div class="sjbm-job-meta" style="margin-bottom: 20px; color: #555; font-size: 16px;">
                    <?php if ($job_types) : ?>
                        <span class="sjbm-job-type" style="margin-right: 15px;">
                            <?php echo esc_html(implode(', ', wp_list_pluck($job_types, 'name'))); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($job_location) : ?>
                        <span class="sjbm-job-location" style="margin-right: 15px;">
                            <?php echo esc_html($job_location); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($salary) : ?>
                        <span class="sjbm-job-salary" style="margin-right: 15px; color: #008000; font-weight: bold;">
                            <?php echo esc_html($salary); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($deadline) : ?>
                        <span class="sjbm-job-deadline" style="color: #FF0000; font-weight: bold;">
                            <?php 
                            printf(
                                __('Apply by: %s', 'simple-job-board'),
                                date_i18n(get_option('date_format'), strtotime($deadline))
                            );
                            ?>
                            <?php if ($is_past_deadline) : ?>
                                <span class="sjbm-deadline-passed">(<?php _e('Closed', 'simple-job-board'); ?>)</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>
            
            <div class="sjbm-job-content" style="margin-bottom: 30px; line-height: 1.6;">
                <?php the_content(); ?>
            </div>
            
            <footer class="sjbm-job-footer">
                <h3 style="font-size: 20px; margin-bottom: 15px;"><?php esc_html_e('Apply for this position', 'simple-job-board'); ?></h3>
                
                <?php if ($is_past_deadline) : ?>
                    <div style="background-color: #FFEBEE; padding: 10px; border-left: 4px solid #F44336; margin-bottom: 20px;">
                        <p><?php esc_html_e('Applications for this position are now closed.', 'simple-job-board'); ?></p>
                    </div>
                <?php elseif (!empty($application_link)) : ?>
                    <div style="margin-bottom: 20px;">
                        <p><?php esc_html_e('Please apply through our external application system:', 'simple-job-board'); ?></p>
                        <a href="<?php echo esc_url($application_link); ?>" style="display: inline-block; background-color: #4464AD; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                            <?php esc_html_e('Apply Now', 'simple-job-board'); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <form id="sjbm-application-form" method="post" enctype="multipart/form-data" style="margin-bottom: 30px;">
                        <input type="hidden" name="job_id" value="<?php echo absint($job_id); ?>">
                        <input type="hidden" name="action" value="sjbm_submit_application">
                        <?php wp_nonce_field('sjbm_application_nonce', 'sjbm_nonce'); ?>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="applicant_name" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Full Name', 'simple-job-board'); ?>*</label>
                            <input type="text" name="applicant_name" id="applicant_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="applicant_email" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Email', 'simple-job-board'); ?>*</label>
                            <input type="email" name="applicant_email" id="applicant_email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="phone" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Phone Number', 'simple-job-board'); ?></label>
                            <input type="tel" name="phone" id="phone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="cover_letter" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Cover Letter', 'simple-job-board'); ?>*</label>
                            <textarea name="cover_letter" id="cover_letter" rows="6" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label for="resume" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Resume (PDF or DOC)', 'simple-job-board'); ?>*</label>
                            <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" required style="margin-bottom: 5px;">
                            <p style="font-size: 13px; color: #666;"><?php esc_html_e('Max file size: 2MB', 'simple-job-board'); ?></p>
                        </div>
                        
                        <button type="submit" style="background-color: #4464AD; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
                            <?php esc_html_e('Submit Application', 'simple-job-board'); ?>
                        </button>
                        
                        <div id="sjbm-form-message" style="margin-top: 15px;"></div>
                    </form>
                <?php endif; ?>
            </footer>
        </article>
    </div>
    
<?php endwhile;

get_footer();

