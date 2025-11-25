<?php
/**
 * Uninstall SurveyFlow
 *
 * @package SurveyFlow
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Safe uninstall mode
 * Define this constant in wp-config.php as:
 * define('SURVEYFLOW_SAFE_UNINSTALL', true);
 * to KEEP all data when uninstalling.
 */
if (defined('SURVEYFLOW_SAFE_UNINSTALL') && SURVEYFLOW_SAFE_UNINSTALL === true) {
    return; // Skip cleanup to preserve all surveys and responses
}

// Fetch all surveys
$surveys = get_posts([
    'post_type'      => 'surveyflow_survey',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
]);

foreach ($surveys as $survey_id) {
    // Delete respondent uploads
    $uploads = get_posts([
        'post_type'   => 'attachment',
        'numberposts' => -1,
        'meta_key'    => '_survey_id',
        'meta_value'  => $survey_id,
        'fields'      => 'ids',
    ]);
    foreach ($uploads as $u_id) {
        wp_delete_attachment($u_id, true);
    }

    // Delete the survey and its meta
    wp_delete_post($survey_id, true);
}

// Remove any stored plugin-level options
delete_option('surveyflow_version');
delete_option('surveyflow_settings');
