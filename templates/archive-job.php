<?php
/**
 * Archive Template for Job Post Type
 */

get_header(); ?>

<div class="sjbm-archive-wrapper" style="max-width: 700px; width: 100%; margin:0 auto;">
    <header class="page-header">
        <h1 class="page-title" style="font-family: poppins; font-weight: bold;"><?php post_type_archive_title(); ?></h1>
    </header>

    <?php if (have_posts()) : ?>
        <div class="sjbm-job-archive">
            <?php while (have_posts()) : the_post(); ?>
                <?php include(SJBM_PLUGIN_DIR . 'templates/content-job.php'); ?>
            <?php endwhile; ?>
        </div>

        <div style="margin: 40px 0; display: flex; justify-content: center;">
            <?php the_posts_pagination(array(
                'mid_size'  => 2,
                'prev_text' => __('« Previous', 'simple-job-board'),
                'next_text' => __('Next »', 'simple-job-board'),
                'before_page_number' => '<span style="display: inline-block; padding: 8px 16px; margin: 0 4px; border: 1px solid #ddd; border-radius: 4px; color: #333; text-decoration: none; font-family: \'Poppins\', sans-serif; font-size: 14px; transition: all 0.3s ease;">',
                'after_page_number'  => '</span>',
            )); ?>
        </div>
    <?php else : ?>
        <p><?php _e('No job listings found.', 'simple-job-board'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>