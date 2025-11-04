<?php
namespace SurveyFlow\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Renders the Survey Settings metabox (message + restrictions).
 */
class SurveySettings
{
    public function render($post)
    {
        $disq  = get_post_meta($post->ID, '_surveyflow_disqualified_message', true);
        $disq  = $disq !== '' ? $disq : __('You do not qualify for this survey.', 'surveyflow');
        $one   = (int) get_post_meta($post->ID, '_surveyflow_one_response', true);
        $auth  = (int) get_post_meta($post->ID, '_surveyflow_logged_in_only', true);

        wp_nonce_field('surveyflow_save_settings', 'surveyflow_settings_nonce');

        echo '<div class="surveyflow-settings" style="padding:10px 0;">';

        echo '<p><label for="surveyflow_disqualified_message"><strong>'
            . esc_html__('Disqualified Message', 'surveyflow') . '</strong></label><br>';
        echo '<textarea id="surveyflow_disqualified_message" name="surveyflow_disqualified_message" rows="3" style="width:100%;">'
            . esc_textarea($disq) . '</textarea></p>';

        echo '<p><label><input type="checkbox" name="surveyflow_one_response" value="1" '
            . checked($one, 1, false) . '> '
            . esc_html__('Limit to one response per user (cookie + IP).', 'surveyflow') . '</label></p>';

        echo '<p><label><input type="checkbox" name="surveyflow_logged_in_only" value="1" '
            . checked($auth, 1, false) . '> '
            . esc_html__('Restrict to logged-in users only.', 'surveyflow') . '</label></p>';

        echo '</div>';
    }
}
