<?php
/*
 @package Simple_Job_Board
 * @wordpress-plugin
Plugin Name: Simple Job Board Manager
Description:  This plugin enables website administrators to create, manage, and publish job listings directly from the WordPress dashboard
Version: 1.0
Author: Mark Kangwa
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('SJBM_VERSION', '1.0');
define('SJBM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SJBM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SJBM_TEMPLATE_PATH', SJBM_PLUGIN_DIR . 'templates/');

// Include required files
require_once SJBM_PLUGIN_DIR . 'includes/class-job-post-type.php';
require_once SJBM_PLUGIN_DIR . 'includes/class-job-applications.php';
require_once SJBM_PLUGIN_DIR . 'includes/class-job-shortcode.php';
require_once SJBM_PLUGIN_DIR . 'includes/class-job-form-handler.php';

/**
 * Main plugin class
 */
class Simple_Job_Board_Manager {
    
    /**
     * Initialize the plugin
     */
    public static function init() {
        // Registers the activation hook
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
        
        // Initialize all components
        SJBM_Job_Post_Type::init();
        SJBM_Job_Applications::init();
        SJBM_Job_Shortcode::init();
        SJBM_Job_Form_Handler::init();
        
        // Setups the template system
        self::init_template_system();
        
        // Loads text domain
        add_action('plugins_loaded', array(__CLASS__, 'load_textdomain'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        
        // Flush rewrite rules on activation
        add_action('init', array(__CLASS__, 'maybe_flush_rewrite_rules'), 20);
    }
    
    /**
     * Activation tasks
     */
    public static function activate() {
        // Set a flag to flush rewrite rules on next init
        update_option('sjbm_flush_rewrite_rules', true);
		
		// Add Hybrid job type if it doesn't exist
    if (!term_exists('Hybrid', 'job_type')) {
        wp_insert_term('Hybrid', 'job_type');
    }
	
    }
	
	
	
 
    /**
     * Flush rewrite rules
     */
    public static function maybe_flush_rewrite_rules() {
        if (get_option('sjbm_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('sjbm_flush_rewrite_rules');
        }
    }
    
    /**
     * Initialize template system
     */
    public static function init_template_system() {
        add_filter('template_include', array(__CLASS__, 'template_loader'));
    }
    
    /**
     * Custom template loader
     */
    public static function template_loader($template) {
        if (is_singular('job')) {
            $file = 'single-job.php';
        } elseif (is_post_type_archive('job')) {
            $file = 'archive-job.php';
        } elseif (is_tax('job_type')) {
            $file = 'taxonomy-job-type.php';
        } else {
            return $template;
        }
        
        // Check theme directory first
        $theme_template = locate_template(array('simple-job-board/' . $file));
        
        if ($theme_template) {
            return $theme_template;
        }
        
        // Use plugin template
        $plugin_template = SJBM_TEMPLATE_PATH . $file;
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return $template;
    }
    
    /**
     * Load plugin textdomain
     */
    public static function load_textdomain() {
        load_plugin_textdomain('simple-job-board', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts() {
        wp_enqueue_style(
            'sjbm-style', 
            SJBM_PLUGIN_URL . 'assets/css/style.css', 
            array(), 
            SJBM_VERSION
        );
        
        wp_enqueue_script(
            'sjbm-script', 
            SJBM_PLUGIN_URL . 'assets/js/script.js', 
            array('jquery'), 
            SJBM_VERSION, 
            true
        );
		
		// Add the new filter handling script
    wp_enqueue_script(
        'sjbm-filter-script', 
        SJBM_PLUGIN_URL . 'assets/js/job-filter.js', 
        array('jquery', 'sjbm-script'), 
        SJBM_VERSION, 
        true
    );
        
        wp_localize_script('sjbm-script', 'sjbm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sjbm_nonce')
        ));
    }
}

// Initialize the plugin
Simple_Job_Board_Manager::init();