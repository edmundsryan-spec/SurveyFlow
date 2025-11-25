<?php
namespace SurveyFlow\Core;

if (!defined('ABSPATH')) exit;

class Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'admin']);
    }

    public function admin($hook)
    {
        // Get post type safely (works on post.php, post-new.php)
        $post_type = get_post_type() ?: ($_GET['post_type'] ?? '');
        if (empty($post_type) && isset($_GET['post'])) {
            $post_type = get_post_type((int) $_GET['post']);
        }

        // Only load scripts on SurveyFlow survey edit pages
        if (in_array($hook, ['post.php', 'post-new.php'], true) && $post_type === 'surveyflow_survey') {

            // ✅ Base admin CSS
            wp_enqueue_style(
                'surveyflow-admin',
                SURVEYFLOW_URL . 'assets/css/admin.css',
                [],
                SURVEYFLOW_VERSION
            );

            // ✅ WordPress bundled dependencies
            wp_enqueue_script('jquery-ui-sortable');

            // ✅ Enqueue your admin builder JS
            wp_enqueue_script(
                'surveyflow-admin',
                SURVEYFLOW_URL . 'assets/js/admin.js',
                ['jquery', 'jquery-ui-sortable'],
                SURVEYFLOW_VERSION,
                true
            );

            // ✅ Localize script — defines "surveyflowAdmin"
            wp_localize_script('surveyflow-admin', 'surveyflowAdmin', [
                'nonce'    => wp_create_nonce('surveyflow_nonce'),
                'ajaxUrl'  => admin_url('admin-ajax.php'),
                'version'  => SURVEYFLOW_VERSION,
            ]);
        }
    }

    public function frontend()
    {
        // Just pre-register for now
        wp_register_style(
            'surveyflow-frontend',
            SURVEYFLOW_URL . 'assets/css/frontend.css',
            [],
            SURVEYFLOW_VERSION
        );

        wp_register_script(
            'surveyflow-frontend',
            SURVEYFLOW_URL . 'assets/js/frontend.js',
            ['jquery'],
            SURVEYFLOW_VERSION,
            true
        );
    }
}
