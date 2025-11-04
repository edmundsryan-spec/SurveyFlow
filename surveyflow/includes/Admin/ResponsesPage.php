<?php
namespace SurveyFlow\Admin;

if (!defined('ABSPATH')) exit;

class ResponsesPage {

    public function render() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to view this page.', 'surveyflow'));
        }

        if (!class_exists('\SurveyFlow\Admin\ResponsesTable')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
            require_once SURVEYFLOW_PATH . 'includes/Admin/ResponsesTable.php';
        }

        $survey_id = isset($_GET['survey']) ? absint($_GET['survey']) : 0;

        // CSV export (DB-based)
        if ($survey_id && isset($_GET['sf_export']) && $_GET['sf_export'] === 'csv') {
            $nonce = $_GET['sf_nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'sf_export_' . $survey_id)) {
                wp_die(__('Invalid export nonce.', 'surveyflow'));
            }
            $this->export_csv($survey_id);
            exit;
        }

        // Dropdown of surveys
        $surveys = get_posts([
            'post_type'      => 'surveyflow_survey',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'pending'],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        echo '<div class="wrap">';

        if ($survey_id) {
            $title = get_the_title($survey_id);

            echo '<h1 class="wp-heading-inline">' . esc_html(sprintf(__('Responses for: %s', 'surveyflow'), $title)) . '</h1>';

            // Count total via DB
            global $wpdb;
            $table = \SurveyFlow\Core\Database::get_table_name();
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE survey_id = %d", $survey_id)
            );
            echo ' <span class="subtitle">' . esc_html(sprintf(_n('%d total response', '%d total responses', $total, 'surveyflow'), $total)) . '</span>';

            echo '<hr class="wp-header-end">';

            // Toolbar
            echo '<form method="get" style="margin:10px 0 20px 0;">';
            echo '<input type="hidden" name="post_type" value="surveyflow_survey">';
            echo '<input type="hidden" name="page" value="surveyflow-responses">';
            echo '<label for="surveyflow-select" class="screen-reader-text">' . esc_html__('Select survey', 'surveyflow') . '</label>';
            echo '<select id="surveyflow-select" name="survey">';
            foreach ($surveys as $sid) {
                $sel = selected($sid, $survey_id, false);
                echo '<option value="' . esc_attr($sid) . '"' . $sel . '>' . esc_html(get_the_title($sid)) . '</option>';
            }
            echo '</select> ';
            echo '<button class="button">' . esc_html__('View', 'surveyflow') . '</button> ';

            $export_url = add_query_arg([
                'post_type' => 'surveyflow_survey',
                'page'      => 'surveyflow-responses',
                'survey'    => $survey_id,
                'sf_export' => 'csv',
                'sf_nonce'  => wp_create_nonce('sf_export_' . $survey_id),
            ], admin_url('edit.php'));
            echo '<a href="' . esc_url($export_url) . '" class="button button-primary">' . esc_html__('Export CSV', 'surveyflow') . '</a>';
            echo '</form>';

            // Table (DB-backed)
            $table_obj = new \SurveyFlow\Admin\ResponsesTable($survey_id);
            $table_obj->prepare_items();
            echo '<form method="post">';
            $table_obj->display();
            echo '</form>';

        } else {
            echo '<h1 class="wp-heading-inline">' . esc_html__('Responses', 'surveyflow') . '</h1>';
            echo '<hr class="wp-header-end">';
            echo '<form method="get">';
            echo '<input type="hidden" name="post_type" value="surveyflow_survey">';
            echo '<input type="hidden" name="page" value="surveyflow-responses">';
            echo '<p><label for="surveyflow-select"><strong>' . esc_html__('Select a survey to view responses:', 'surveyflow') . '</strong></label></p>';
            echo '<select id="surveyflow-select" name="survey" style="min-width:280px;">';
            foreach ($surveys as $sid) {
                echo '<option value="' . esc_attr($sid) . '">' . esc_html(get_the_title($sid)) . '</option>';
            }
            echo '</select> ';
            echo '<button class="button button-primary">' . esc_html__('View Responses', 'surveyflow') . '</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    private function export_csv($survey_id) {
        global $wpdb;
        $table    = \SurveyFlow\Core\Database::get_table_name();
        $title    = sanitize_title(get_the_title($survey_id));
        $filename = 'surveyflow-responses-' . $title . '-' . date('Ymd-His') . '.csv';

        // Stream rows
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT created_at, user_id, ip, answers_json, uploads_json
                 FROM {$table}
                 WHERE survey_id = %d
                 ORDER BY created_at DESC",
                $survey_id
            ),
            ARRAY_A
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Time', 'User ID', 'IP', 'Answers JSON', 'Uploads JSON']);

        foreach ((array) $rows as $r) {
            fputcsv($out, [
                $r['created_at'] ?? '',
                (int) ($r['user_id'] ?? 0),
                $r['ip'] ?? '',
                (string) ($r['answers_json'] ?? ''),
                (string) ($r['uploads_json'] ?? ''),
            ]);
        }

        fclose($out);
        exit;
    }
}
