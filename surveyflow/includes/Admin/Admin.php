<?php
namespace SurveyFlow\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Main Admin Controller
 * - Registers metaboxes (Builder + Settings)
 * - Saves survey config (NOT survey responses)
 * - Adds "View Responses" link under each survey
 * - Registers hidden admin page for responses table (no visible menu)
 * - Enqueues builder assets ONLY on Survey edit screens
 * - Adds "Shortcode" column with click-to-copy on Surveys list screen
 */
class Admin
{
    private $metabox;
    private $settings;

    public function __construct()
    {
        add_action('add_meta_boxes',                 [$this, 'register_metaboxes']);
        add_action('save_post',                      [$this, 'save_post']);
        add_action('admin_enqueue_scripts',          [$this, 'enqueue_builder_assets']);
        add_filter('post_row_actions',               [$this, 'add_view_responses_row_action'], 10, 2);
        add_action('admin_menu',                     [$this, 'register_hidden_responses_page']);
        add_filter('manage_surveyflow_survey_posts_columns',        [$this, 'add_shortcode_column']);
        add_action('manage_surveyflow_survey_posts_custom_column',  [$this, 'render_shortcode_column'], 10, 2);
        add_action('admin_enqueue_scripts',          [$this, 'enqueue_shortcode_copy_js']);
    }

    /**
     * Registers the Survey Builder and Survey Settings metaboxes on the Survey post type.
     */
    public function register_metaboxes($post_type)
    {
        if ($post_type !== 'surveyflow_survey') return;

        // Builder Metabox
        if (!isset($this->metabox)) {
            $this->metabox = new \SurveyFlow\Admin\MetaBox();
        }
        add_meta_box(
            'surveyflow_builder',
            __('Survey Builder', 'surveyflow'),
            [$this->metabox, 'render'],
            'surveyflow_survey',
            'normal',
            'high'
        );

        // Settings Metabox
        if (!isset($this->settings)) {
            $this->settings = new \SurveyFlow\Admin\SurveySettings();
        }
        add_meta_box(
            'surveyflow_settings',
            __('Survey Settings', 'surveyflow'),
            [$this->settings, 'render'],
            'surveyflow_survey',
            'normal',
            'default'
        );
    }

    /**
     * Save Builder + Settings meta (NOT responses).
     */
    public function save_post($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (get_post_type($post_id) !== 'surveyflow_survey') return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Save Builder JSON
        if (isset($_POST['surveyflow_questions_nonce'])
            && wp_verify_nonce($_POST['surveyflow_questions_nonce'], 'surveyflow_save_questions')) {

            $raw  = isset($_POST['surveyflow_questions_json']) ? wp_unslash($_POST['surveyflow_questions_json']) : '';
            $data = json_decode($raw, true);
            if (!is_array($data)) $data = [];

            update_post_meta($post_id, '_surveyflow_questions', wp_json_encode($data));
        }

        // Save Settings
        if (isset($_POST['surveyflow_settings_nonce'])
            && wp_verify_nonce($_POST['surveyflow_settings_nonce'], 'surveyflow_save_settings')) {

            $disq = isset($_POST['surveyflow_disqualified_message'])
                ? wp_kses_post(wp_unslash($_POST['surveyflow_disqualified_message']))
                : '';

            $one  = !empty($_POST['surveyflow_one_response']) ? 1 : 0;
            $auth = !empty($_POST['surveyflow_logged_in_only']) ? 1 : 0;

            update_post_meta($post_id, '_surveyflow_disqualified_message', $disq);
            update_post_meta($post_id, '_surveyflow_one_response', $one);
            update_post_meta($post_id, '_surveyflow_logged_in_only', $auth);
        }
    }

    /**
     * Enqueue builder CSS/JS ONLY on Add/Edit Survey screens.
     */
    public function enqueue_builder_assets($hook)
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'surveyflow_survey') return;

        // Media library (for creator media)
        wp_enqueue_media();

        // Admin CSS
        wp_enqueue_style(
            'surveyflow-admin',
            SURVEYFLOW_URL . 'assets/css/admin.css',
            [],
            SURVEYFLOW_VERSION
        );

        // Unified builder JS (minified)
        wp_enqueue_script(
            'surveyflow-admin',
            SURVEYFLOW_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable', 'wp-util', 'media-editor'],
            SURVEYFLOW_VERSION,
            true
        );
    }

    /**
     * Adds [View Responses] row action under each Survey.
     */
    public function add_view_responses_row_action($actions, $post)
    {
        if ($post->post_type !== 'surveyflow_survey') return $actions;

        $url = add_query_arg(
            [
                'post_type' => 'surveyflow_survey',
                'page'      => 'surveyflow-responses',
                'survey'    => $post->ID,
            ],
            admin_url('edit.php')
        );

        $actions['surveyflow_view_responses'] =
            '<a href="' . esc_url($url) . '">' . esc_html__('View Responses', 'surveyflow') . '</a>';

        return $actions;
    }

    /**
     * Hidden admin page used to display survey responses (no menu entry).
     */
    public function register_hidden_responses_page()
    {
        add_submenu_page(
            'edit.php?post_type=surveyflow_survey',
            __('Responses', 'surveyflow'),
            __('Responses', 'surveyflow'),
            'edit_posts',
            'surveyflow-responses',
            function () {
                if (!class_exists('\SurveyFlow\Admin\ResponsesPage')) {
                    require_once SURVEYFLOW_PATH . 'includes/Admin/ResponsesPage.php';
                }
                (new \SurveyFlow\Admin\ResponsesPage())->render();
            }
        );

        // Hide it from the menu UI
        remove_submenu_page('edit.php?post_type=surveyflow_survey', 'surveyflow-responses');
    }

    /**
     * Add "Shortcode" column to Surveys list.
     */
    public function add_shortcode_column($columns)
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['surveyflow_shortcode'] = __('Shortcode', 'surveyflow');
            }
        }
        return $new;
    }

    /**
     * Render shortcode + copy icon in the column.
     */
    public function render_shortcode_column($column, $post_id)
    {
        if ($column !== 'surveyflow_shortcode') return;

        $shortcode = '[surveyflow id="' . intval($post_id) . '"]';
        echo '<span class="sf-copy-shortcode" data-code="' . esc_attr($shortcode) . '">'
            . esc_html($shortcode)
            . ' <span class="sf-copy-icon">📋</span>'
            . '</span>';
    }

    /**
     * Enqueue small script + inline CSS for click-to-copy on Survey list screen only.
     */
    public function enqueue_shortcode_copy_js($hook)
    {
        if ($hook !== 'edit.php' || ($_GET['post_type'] ?? '') !== 'surveyflow_survey') return;

        wp_enqueue_script(
            'surveyflow-copy-shortcode',
            SURVEYFLOW_URL . 'assets/js/admin-copy-shortcode.js',
            [],
            SURVEYFLOW_VERSION,
            true
        );

        // Use core 'common' as a style handle to attach inline CSS safely
        wp_enqueue_style('common');
        $css = '.sf-copy-shortcode{cursor:pointer;display:inline-block;padding:4px 6px;border:1px solid #ccc;border-radius:4px;background:#fafafa;font-family:monospace}
.sf-copy-shortcode:hover{background:#f0f0f0}
.sf-copy-icon{opacity:.6;margin-left:4px}
.sf-copy-shortcode.sf-copied{background:#dfffe0!important;border-color:#55bb55}';
        wp_add_inline_style('common', $css);
    }
}
