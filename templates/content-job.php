<?php
/**
 * Single Job Item Template for Archive
 */

$job_id = get_the_ID();
$job_location = get_post_meta($job_id, '_job_location', true);
$job_types = get_the_terms($job_id, 'job_type');
$deadline = get_post_meta($job_id, '_application_deadline', true);
$is_past_deadline = $deadline && strtotime($deadline) < current_time('timestamp');
?>

<div class="sjbm-job-listing-container"> <!-- Added container div -->
    <article id="post-<?php the_ID(); ?>" <?php post_class('sjbm-job-item'); ?>>
        <h2 class="sjbm-job-title">
            <a href="<?php the_permalink(); ?>" style="color: #4464AD; font-family: poppins;"><?php the_title(); ?></a>
        </h2>
        
        <div class="sjbm-job-meta">
            <?php if ($job_types) : ?>
                <span class="sjbm-job-type" style="color: #008000; font-weight: bold;">
                    <?php echo esc_html(implode(', ', wp_list_pluck($job_types, 'name'))); ?>
                </span>
            <?php endif; ?>
            
            <?php if ($job_location) : ?>
                <span class="sjbm-job-location" style="color: #000000;">
                    <?php echo esc_html($job_location); ?>
                </span>
            <?php endif; ?>
            
            <?php if ($deadline) : ?>
                <span class="sjbm-job-deadline">
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
        
        <div class="sjbm-job-excerpt">
            <?php the_excerpt(); ?>
        </div>
        
        <div class="sjbm-job-actions">
            <a href="<?php the_permalink(); ?>" class="sjbm-view-details">
                <?php _e('View Details', 'simple-job-board'); ?>
            </a>
        </div>
    </article>
</div>