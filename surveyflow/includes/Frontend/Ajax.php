<?php
namespace SurveyFlow\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * Class Ajax
 * Handles AJAX submission of surveys.
 */
class Ajax {

    public function __construct() {
        add_action('wp_ajax_surveyflow_submit', [$this, 'submit']);
        add_action('wp_ajax_nopriv_surveyflow_submit', [$this, 'submit']);
    }

    /**
     * Handle survey submission
     */
    public function submit() {
        check_ajax_referer('surveyflow_nonce', 'nonce');

        $survey_id = intval($_POST['survey_id'] ?? 0);
        if (!$survey_id) {
            wp_send_json_error(['message' => __('Invalid survey ID.', 'surveyflow')]);
        }

        $answers = $_POST['surveyflow'] ?? [];
        $user_id = get_current_user_id();
        $user_key = $user_id ? 'user_' . $user_id : 'ip_' . $_SERVER['REMOTE_ADDR'];

        // Check for single submission per user
        $single_submission = get_post_meta($survey_id, '_surveyflow_single_submission', true);
        if ($single_submission) {
            $submitted_users = get_post_meta($survey_id, '_surveyflow_submitted_users', true) ?: [];
            if (in_array($user_key, $submitted_users)) {
                wp_send_json_error(['message' => __('You have already submitted this survey.', 'surveyflow')]);
            }
        }

        // Check for prequalifiers
        $questions = get_post_meta($survey_id, '_surveyflow_questions', false);
        foreach ($questions as $q_raw) {
            $q = maybe_unserialize($q_raw);
            if (!empty($q['prequalifier']) && !empty($q['prequal_answer'])) {
                $answer = $answers[$q['title']] ?? '';
                if (trim($answer) === trim($q['prequal_answer'])) {
                    wp_send_json_success([
                        'message' => __('You are not qualified for this survey.', 'surveyflow'),
                        'disqualified' => true
                    ]);
                }
            }
        }

        // Save answers
        $all_answers = get_post_meta($survey_id, '_surveyflow_answers', true) ?: [];
        $all_answers[$user_key] = $answers;
        update_post_meta($survey_id, '_surveyflow_answers', $all_answers);

        // Mark user as submitted if single submission enforced
        if ($single_submission) {
            $submitted_users = get_post_meta($survey_id, '_surveyflow_submitted_users', true) ?: [];
            $submitted_users[] = $user_key;
            update_post_meta($survey_id, '_surveyflow_submitted_users', $submitted_users);
        }

        wp_send_json_success(['message' => __('Survey submitted successfully!', 'surveyflow')]);
    }
}