<?php
namespace SurveyFlow\Admin;

use SurveyFlow\Core\Database;

if (!defined('ABSPATH')) exit;

/**
 * Admin "View Responses" screen + CSV export
 */
class ResponsesPage
{
    private $survey_id;
    private $per_page = 10;

    public function render()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to view survey responses.', 'surveyflow'));
        }

        $this->survey_id = isset($_GET['survey']) ? absint($_GET['survey']) : 0;
        if (!$this->survey_id || get_post_type($this->survey_id) !== 'surveyflow_survey') {
            echo '<div class="wrap"><h1>' . esc_html__('Survey Responses', 'surveyflow') . '</h1>';
            echo '<p>' . esc_html__('Invalid survey selected.', 'surveyflow') . '</p></div>';
            return;
        }

        // CSV Export?
        if (isset($_GET['surveyflow_export']) && $_GET['surveyflow_export'] === 'csv') {
            $this->export_csv();
            return;
        }

        $this->render_list_screen();
    }

    /**
     * Renders the main responses UI: header, export button, Q&A cards, pagination.
     */
    protected function render_list_screen()
    {
        global $wpdb;

        $survey_title = get_the_title($this->survey_id);

        // Load questions (structure) from postmeta
        $raw_questions = get_post_meta($this->survey_id, '_surveyflow_questions', true);
        $questions     = [];
        if (is_string($raw_questions) && $raw_questions !== '') {
            $decoded = json_decode($raw_questions, true);
            if (is_array($decoded)) {
                $questions = $decoded;
            }
        }

        // Pagination basics
        $table    = Database::get_table_name();
        $paged    = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset   = ($paged - 1) * $this->per_page;
        $per_page = $this->per_page;

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE survey_id = %d",
                $this->survey_id
            )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE survey_id = %d 
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $this->survey_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(sprintf(__('Responses for: %s', 'surveyflow'), $survey_title)) . '</h1>';

        // Export button
        $export_url = add_query_arg(
            [
                'post_type'        => 'surveyflow_survey',
                'page'             => 'surveyflow-responses',
                'survey'           => $this->survey_id,
                'surveyflow_export'=> 'csv',
            ],
            admin_url('edit.php')
        );

        echo '<p><a href="' . esc_url($export_url) . '" class="button button-secondary">';
        esc_html_e('Export to CSV', 'surveyflow');
        echo '</a></p>';

        if ($total === 0 || empty($rows)) {
            echo '<p>' . esc_html__('No responses have been submitted yet.', 'surveyflow') . '</p>';
            echo '</div>';
            return;
        }

        // Pagination summary
        $start = $offset + 1;
        $end   = min($offset + $per_page, $total);
        echo '<p class="description">';
        echo esc_html(sprintf(__('Showing %1$d to %2$d of %3$d responses.', 'surveyflow'), $start, $end, $total));
        echo '</p>';

        echo '<div class="surveyflow-responses">';

        foreach ($rows as $row) {
            $answers = [];
            if (!empty($row['answers_json'])) {
                $tmp = json_decode($row['answers_json'], true);
                if (is_array($tmp)) {
                    $answers = $tmp;
                }
            }

            $uploads = [];
            if (!empty($row['uploads_json'])) {
                $tmp = json_decode($row['uploads_json'], true);
                if (is_array($tmp)) {
                    $uploads = $tmp;
                }
            }

            $disqualified = !empty($row['disqualified']);
            $created_at   = !empty($row['created_at']) ? $row['created_at'] : '';
            $ip           = !empty($row['ip']) ? $row['ip'] : '';
            $user_id      = !empty($row['user_id']) ? (int) $row['user_id'] : 0;
            $user_label   = $user_id ? get_userdata($user_id) : null;

            echo '<div class="sf-response-card">';
            echo '<h2>' . esc_html(sprintf(__('Response #%d', 'surveyflow'), $row['id'])) . '</h2>';

            echo '<p class="sf-response-meta">';
            if ($created_at) {
                echo '<strong>' . esc_html__('Date:', 'surveyflow') . '</strong> ' . esc_html($created_at) . ' &nbsp; ';
            }
            if ($ip) {
                echo '<strong>' . esc_html__('IP:', 'surveyflow') . '</strong> ' . esc_html($ip) . ' &nbsp; ';
            }
            if ($user_label instanceof \WP_User) {
                echo '<strong>' . esc_html__('User:', 'surveyflow') . '</strong> ' . esc_html($user_label->user_login) . ' &nbsp; ';
            }
            if ($disqualified) {
                echo '<span class="sf-response-badge sf-response-badge-disq">' . esc_html__('Disqualified', 'surveyflow') . '</span>';
            }
            echo '</p>';

            echo '<div class="sf-response-qa">';

            foreach ($questions as $index => $q) {
                $type = isset($q['type']) ? $q['type'] : 'short_text';

                // Section titles
                if ($type === 'section') {
                    $title = isset($q['title']) ? $q['title'] : '';
                    if ($title !== '') {
                        echo '<h3 class="sf-response-section">' . esc_html($title) . '</h3>';
                    }
                    if (!empty($q['description'])) {
                        echo '<p class="sf-response-section-desc">' . esc_html($q['description']) . '</p>';
                    }
                    continue;
                }

                // Page breaks: skip
                if ($type === 'break') {
                    continue;
                }

                $key   = 'q_' . $index;
                $label = isset($q['title']) ? $q['title'] : ('Question ' . ($index + 1));
                $value = isset($answers[$key]) ? $answers[$key] : null;
                $upload = isset($uploads[$key]) ? $uploads[$key] : null;

                echo '<div class="sf-response-question">';
                if ($label !== '') {
                    echo '<p class="sf-q-label"><strong>' . esc_html($label) . '</strong></p>';
                }

                echo '<div class="sf-q-answer">';
                if ($value !== null) {
                    echo nl2br(esc_html($this->format_answer_value($value)));
                } else {
                    echo '<em>' . esc_html__('No answer', 'surveyflow') . '</em>';
                }

                // If this question had a file upload, show it
                if (!empty($upload['url'])) {
                    $name = !empty($upload['name']) ? $upload['name'] : basename($upload['url']);
                    echo '<div><a href="' . esc_url($upload['url']) . '" target="_blank" rel="noopener noreferrer">';
                    echo esc_html($name);
                    echo '</a></div>';
                }

                echo '</div>'; // .sf-q-answer
                echo '</div>'; // .sf-response-question
            }

            echo '</div>'; // .sf-response-qa
            echo '</div>'; // .sf-response-card
        }

        echo '</div>'; // .surveyflow-responses

        // Pagination links
        $total_pages = (int) ceil($total / $per_page);
        if ($total_pages > 1) {
            $base_url = add_query_arg(
                [
                    'post_type' => 'surveyflow_survey',
                    'page'      => 'surveyflow-responses',
                    'survey'    => $this->survey_id,
                ],
                admin_url('edit.php')
            );

            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%', $base_url),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => __('« Previous', 'surveyflow'),
                'next_text' => __('Next »', 'surveyflow'),
            ]);
            echo '</div></div>';
        }

        echo '</div>'; // .wrap
    }

    /**
     * Helper: normalize answer into a readable string.
     */
    protected function format_answer_value($value)
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $v) {
                $parts[] = is_scalar($v) ? (string) $v : '';
            }
            return implode(', ', array_filter($parts, 'strlen'));
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Exports all responses for a survey as CSV and forces a download.
     * Includes file URL (if present) inline with the answer.
     */
    protected function export_csv()
    {
        global $wpdb;

        $table = Database::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE survey_id = %d ORDER BY created_at DESC",
                $this->survey_id
            ),
            ARRAY_A
        );

        // Load questions for headers
        $raw_questions = get_post_meta($this->survey_id, '_surveyflow_questions', true);
        $questions     = [];
        if (is_string($raw_questions) && $raw_questions !== '') {
            $decoded = json_decode($raw_questions, true);
            if (is_array($decoded)) {
                $questions = $decoded;
            }
        }

        // Build CSV headers
        $headers = [
            'response_id',
            'created_at',
            'ip',
            'user_id',
            'disqualified',
        ];

        $question_keys   = [];
        $question_labels = [];

        foreach ($questions as $index => $q) {
            $type = isset($q['type']) ? $q['type'] : 'short_text';

            // Skip sections and breaks; they don't directly map to answers
            if ($type === 'section' || $type === 'break') {
                continue;
            }

            $key   = 'q_' . $index;
            $label = isset($q['title']) ? $q['title'] : ('Question ' . ($index + 1));

            $question_keys[]   = $key;
            $question_labels[] = $label;
        }

        foreach ($question_labels as $label) {
            $headers[] = $label;
        }

        // Clear any existing output buffer to avoid corrupting headers
        while (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'survey-' . $this->survey_id . '-responses-' . date('Y-m-d-H-i-s') . '.csv';

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $output = fopen('php://output', 'w');

        // Output header row
        fputcsv($output, $headers);

        // Output data rows
        foreach ($rows as $row) {
            $answers = [];
            if (!empty($row['answers_json'])) {
                $tmp = json_decode($row['answers_json'], true);
                if (is_array($tmp)) {
                    $answers = $tmp;
                }
            }

            $uploads = [];
            if (!empty($row['uploads_json'])) {
                $tmp = json_decode($row['uploads_json'], true);
                if (is_array($tmp)) {
                    $uploads = $tmp;
                }
            }

            $line = [
                isset($row['id']) ? $row['id'] : '',
                isset($row['created_at']) ? $row['created_at'] : '',
                isset($row['ip']) ? $row['ip'] : '',
                isset($row['user_id']) ? $row['user_id'] : '',
                !empty($row['disqualified']) ? 1 : 0,
            ];

            foreach ($question_keys as $qkey) {
                $val    = isset($answers[$qkey]) ? $answers[$qkey] : '';
                $string = $this->format_answer_value($val);

                // If a file was uploaded for this question, append the URL
                if (!empty($uploads[$qkey]['url'])) {
                    $string .= ' [file: ' . $uploads[$qkey]['url'] . ']';
                }

                $line[] = $string;
            }

            fputcsv($output, $line);
        }

        fclose($output);
        exit;
    }
}
