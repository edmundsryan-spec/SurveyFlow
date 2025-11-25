<?php
namespace SurveyFlow\Frontend;

if (!defined('ABSPATH')) exit;

class Frontend {

    public function __construct() {

        // Direct preview mode: ?surveyflow_preview=123
        add_action('template_redirect', function() {
            if (isset($_GET['surveyflow_preview'])) {
                $survey_id = absint($_GET['surveyflow_preview']);
                if (!$survey_id) return;

                status_header(200);
                nocache_headers();

                echo '<!DOCTYPE html><html><head>';
                echo '<meta charset="utf-8">';
                echo '<title>Survey Preview</title>';
                wp_head();
                echo '</head><body class="surveyflow-preview">';
                echo do_shortcode('[surveyflow id="' . $survey_id . '"]');
                wp_footer();
                echo '</body></html>';
                exit;
            }
        });

        add_shortcode('surveyflow', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        add_action('wp_ajax_surveyflow_submit', [$this, 'handle_submit']);
        add_action('wp_ajax_nopriv_surveyflow_submit', [$this, 'handle_submit']);
    }

    /**
     * Only enqueue CSS & JS if the page actually contains the shortcode.
     */
    public function maybe_enqueue_assets() {
        if (is_admin()) return;
        global $post;
        if (!isset($post) || !is_a($post, 'WP_Post')) return;
        if (has_shortcode($post->post_content, 'surveyflow')) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets() {
        wp_enqueue_style(
            'surveyflow-frontend',
            SURVEYFLOW_URL . 'assets/css/frontend.css',
            [],
            SURVEYFLOW_VERSION
        );

        wp_enqueue_script(
            'surveyflow-frontend',
            SURVEYFLOW_URL . 'assets/js/frontend.js',
            ['jquery'],
            SURVEYFLOW_VERSION,
            true
        );

        wp_localize_script('surveyflow-frontend', 'surveyflowAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('surveyflow_frontend_nonce'),
        ]);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'surveyflow');
        $survey_id = intval($atts['id']);
        if (!$survey_id) return '<div class="surveyflow-msg">No survey specified.</div>';

        // Force enqueue in case shortcode is rendered outside main content
        $this->enqueue_assets();

        // Survey settings
        $msg_disq = get_post_meta($survey_id, '_surveyflow_disqualified_message', true);
        $msg_disq = $msg_disq !== '' ? $msg_disq : __('You do not qualify for this survey.', 'surveyflow');
        $one_resp = (int) get_post_meta($survey_id, '_surveyflow_one_response', true);
        $login_only = (int) get_post_meta($survey_id, '_surveyflow_logged_in_only', true);

        // Logged-in required
        if ($login_only && !is_user_logged_in()) {
            return '<div class="surveyflow-msg">'.esc_html__('Please log in to take this survey.', 'surveyflow').'</div>';
        }

        // One-response enforcement (UI layer)
        if ($one_resp) {
            if (!empty($_COOKIE['surveyflow_done_'.$survey_id])) {
                return '<div class="surveyflow-msg">'.esc_html__('You have already completed this survey. Thank you!', 'surveyflow').'</div>';
            }
        }

        // Load questions
        $raw = get_post_meta($survey_id, '_surveyflow_questions', true);
        if (empty($raw)) return '<div class="surveyflow-msg">This survey has no questions.</div>';
        $questions = json_decode($raw, true);
        if (!is_array($questions) || !count($questions)) {
            return '<div class="surveyflow-msg">This survey has no questions.</div>';
        }

        // Split into pages based on "break"
        $pages = [[]];
        $page_index = 0;
        foreach ($questions as $idx => $q) {
            $type = $q['type'] ?? 'short_text';
            if ($type === 'break') {
                $pages[++$page_index] = [];
                continue;
            }
            $pages[$page_index][] = ['index' => $idx, 'q' => $q];
        }

        ob_start();
        ?>
        <form class="surveyflow-form" data-survey-id="<?php echo esc_attr($survey_id); ?>" data-disq-msg="<?php echo esc_attr($msg_disq); ?>">

            <?php foreach ($pages as $pi => $items): ?>
                <div class="surveyflow-page" data-page="<?php echo esc_attr($pi+1); ?>" style="<?php echo $pi===0 ? '' : 'display:none;'; ?>">
                    <?php foreach ($items as $item):
                        $i = $item['index']; $q = $item['q']; $type = $q['type'];

                        // Section / Title block
                        if ($type === 'section'): ?>
                            <div class="surveyflow-section">
                                <?php if (!empty($q['title'])): ?>
                                    <h3><?php echo esc_html($q['title']); ?></h3>
                                <?php endif; ?>
                                <?php if (!empty($q['description'])): ?>
                                    <p class="sf-desc"><?php echo esc_html($q['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php continue; endif;

                        $is_preq = ($type === 'prequalifier');
                        $data_attrs = $is_preq
                            ? ' data-type="prequalifier" data-subtype="'.esc_attr($q['subtype'] ?? 'radio').'"'
                            : ' data-type="'.esc_attr($type).'"';

                        echo '<div class="surveyflow-question"'.$data_attrs.' data-index="'.esc_attr($i).'">';

                        // Creator media attachment
                        if (!empty($q['media']['url'])) {
                            echo '<div class="surveyflow-media"><img src="'.esc_url($q['media']['url']).'" alt=""></div>';
                        }

                        // Question Label (clean, no prefixes, no bold)
                        if (!empty($q['title'])) {
                            echo '<label class="sf-label">'.esc_html($q['title']).'</label>';
                        }

                        // Field name
                        $name = 'q_'.$i;

                        // Render inputs
                        switch ($type) {
                            case 'short_text':
                            case 'email':
                            case 'phone':
                            case 'address':
                                echo '<input type="text" name="'.esc_attr($name).'" class="sf-input">';
                                break;

                            case 'long_text':
                                echo '<textarea name="'.esc_attr($name).'" class="sf-input"></textarea>';
                                break;

                            case 'radio':
                            case 'checkbox':
                                if (!empty($q['options'])) {
                                    foreach ($q['options'] as $oi => $opt) {
                                        $oid = $name.'_'.$oi;
                                        $inputType = ($type === 'checkbox') ? 'checkbox' : 'radio';
                                        $other = !empty($opt['other']);
                                        echo '<label class="sf-option">';
                                        echo '<input type="'.$inputType.'" name="'.esc_attr($name).($type==='checkbox'?'[]':'').'" value="'.esc_attr($opt['label']).'" id="'.esc_attr($oid).'"'.($other?' data-other="1"':'').'> ';
                                        echo esc_html($opt['label']);
                                        if ($other) {
                                            echo ' <input type="text" class="sf-other" name="'.esc_attr($name.'_other').'" placeholder="'.esc_attr__('Other...','surveyflow').'" aria-hidden="true" style="display:none;">';
                                        }
                                        echo '</label><br>';
                                    }
                                }
                                break;

                            case 'dropdown':
                                echo '<select name="'.esc_attr($name).'" class="sf-input">';
                                echo '<option value="">-- '.__('Select','surveyflow').' --</option>';
                                if (!empty($q['options'])) {
                                    foreach ($q['options'] as $opt) {
                                        echo '<option value="'.esc_attr($opt['label']).'">'.esc_html($opt['label']).'</option>';
                                    }
                                }
                                echo '</select>';
                                break;

                            case 'media_upload':
                                echo '<input type="file" name="'.esc_attr($name).'" class="sf-file" accept="image/*,video/*,audio/*,application/pdf">';
                                break;

                            case 'prequalifier':
                                $sub = $q['subtype'] ?? 'radio';
                                if (!empty($q['options'])) {
                                    // Radio/checkbox rendering
                                    if ($sub !== 'dropdown') {
                                        foreach ($q['options'] as $oi => $opt) {
                                            $oid = $name.'_'.$oi;
                                            $disq = !empty($opt['disqualify']);
                                            $inputType = ($sub === 'checkbox') ? 'checkbox' : 'radio';
                                            echo '<label class="sf-option">';
                                            echo '<input type="'.$inputType.'" data-disqualify="'.($disq?1:0).'" name="'.esc_attr($name).($sub==='checkbox'?'[]':'').'" value="'.esc_attr($opt['label']).'" id="'.esc_attr($oid).'"> ';
                                            echo esc_html($opt['label']);
                                            echo '</label><br>';
                                        }
                                    } else {
                                        // Dropdown prequalifier
                                        echo '<select name="'.esc_attr($name).'" class="sf-input sf-preq-select">';
                                        echo '<option value="">-- '.__('Select','surveyflow').' --</option>';
                                        foreach ($q['options'] as $opt) {
                                            $disq = !empty($opt['disqualify']);
                                            echo '<option value="'.esc_attr($opt['label']).'" data-disqualify="'.($disq?1:0).'">'.esc_html($opt['label']).'</option>';
                                        }
                                        echo '</select>';
                                    }
                                }
                                break;
                        }

                        echo '</div>'; // .surveyflow-question
                    endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="surveyflow-nav">
                <button type="button" class="button sf-btn-prev" style="display:none;"><?php esc_html_e('Back', 'surveyflow'); ?></button>
                <button type="button" class="button sf-btn-next"><?php esc_html_e('Next', 'surveyflow'); ?></button>
                <button type="submit" class="button button-primary sf-btn-submit" style="display:none;"><?php esc_html_e('Submit', 'surveyflow'); ?></button>
            </div>

        </form>

        <div class="surveyflow-message" style="display:none;"></div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX submit handler
     */
   public function handle_submit() {
    check_ajax_referer('surveyflow_frontend_nonce', 'nonce');

    $survey_id = intval($_POST['survey_id'] ?? 0);
    if (!$survey_id || get_post_type($survey_id) !== 'surveyflow_survey') {
        wp_send_json_error(['message' => __('Invalid survey.', 'surveyflow')]);
    }

    // Settings
    $one_resp   = (int) get_post_meta($survey_id, '_surveyflow_one_response', true);
    $login_only = (int) get_post_meta($survey_id, '_surveyflow_logged_in_only', true);

    if ($login_only && !is_user_logged_in()) {
        wp_send_json_error(['message' => __('Please log in to take this survey.', 'surveyflow')]);
    }

    // Parse answers JSON from POST
    $answers_raw = $_POST['answers'] ?? '';
    if (is_array($answers_raw)) {
        // If a JS change ever sends array directly, normalize to array
        $answers = $answers_raw;
    } else {
        $answers = json_decode(stripslashes((string)$answers_raw), true);
    }
    if (!is_array($answers)) $answers = [];

    // Load question set (to find media_upload keys)
    $raw       = get_post_meta($survey_id, '_surveyflow_questions', true);
    $questions = $raw ? json_decode($raw, true) : [];
    $media_keys = [];
    if (is_array($questions)) {
        foreach ($questions as $idx => $q) {
            if (!empty($q['type']) && $q['type'] === 'media_upload') {
                $media_keys[] = 'q_' . $idx;
            }
        }
    }

    // Handle uploads for media_upload questions
    $uploads = [];
    foreach ($media_keys as $mk) {
        if (!isset($_FILES[$mk]) || empty($_FILES[$mk]['name'])) continue;

        $file = $_FILES[$mk];
        $overrides = ['test_form' => false];
        $uploaded = wp_handle_upload($file, $overrides);

        if (!empty($uploaded['error'])) {
            wp_send_json_error(['message' => sprintf(__('File upload failed: %s', 'surveyflow'), $uploaded['error'])]);
        }

        $filetype = wp_check_filetype($uploaded['file'], null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name($file['name']),
            'post_content'   => '',
            'post_status'    => 'private',
            'meta_input'     => ['_surveyflow_upload' => true, '_survey_id' => $survey_id],
        ];
        $attach_id = wp_insert_attachment($attachment, $uploaded['file']);
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        $uploads[$mk] = [
            'attachment_id' => $attach_id,
            'url'           => wp_get_attachment_url($attach_id),
            'name'          => $file['name'],
        ];
    }

    // One-response enforcement (Rule B): by user_id if logged in; else by IP
    $user_id = get_current_user_id();
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($one_resp) {
        global $wpdb;
        $table = \SurveyFlow\Core\Database::get_table_name();

        if ($user_id) {
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE survey_id = %d AND user_id = %d",
                    $survey_id, $user_id
                )
            );
        } else {
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE survey_id = %d AND ip = %s",
                    $survey_id, $ip
                )
            );
        }

        if ($exists > 0) {
            wp_send_json_error(['message' => __('You have already completed this survey. Thank you!', 'surveyflow')]);
        }
    }

    // Disqualified flag: frontend hides UI early; we still store the flag if present (optional)
    $disqualified = 0; // For now always 0; if you pass a hidden field later, parse it here.

    // Insert into DB
    global $wpdb;
    $table = \SurveyFlow\Core\Database::get_table_name();

    $inserted = $wpdb->insert(
        $table,
        [
            'survey_id'    => $survey_id,
            'user_id'      => (int) $user_id,
            'ip'           => substr((string)$ip, 0, 64),
            'created_at'   => current_time('mysql'),
            'disqualified' => (int) $disqualified,
            'answers_json' => wp_json_encode($answers),
            'uploads_json' => wp_json_encode($uploads),
        ],
        [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ]
    );

    if (!$inserted) {
        wp_send_json_error(['message' => __('Could not save your response. Please try again.', 'surveyflow')]);
    }

    // Client-side cookie for UI block (server is source of truth)
    if ($one_resp) {
        setcookie(
            'surveyflow_done_' . $survey_id,
            '1',
            time() + YEAR_IN_SECONDS,
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    wp_send_json_success(['message' => __('Thank you for your submission!', 'surveyflow')]);
}

}
