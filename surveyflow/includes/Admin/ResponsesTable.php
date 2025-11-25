<?php
namespace SurveyFlow\Admin;

if (!defined('ABSPATH')) exit;

class ResponsesTable extends \WP_List_Table {

    protected $survey_id = 0;
    protected $total_items = 0;

    public function __construct($survey_id) {
        parent::__construct([
            'singular' => 'surveyflow_response',
            'plural'   => 'surveyflow_responses',
            'ajax'     => false,
        ]);
        $this->survey_id = absint($survey_id);
    }

    public function get_columns() {
        return [
            'time'    => __('Date', 'surveyflow'),
            'user'    => __('User', 'surveyflow'),
            'ip'      => __('IP Address', 'surveyflow'),
            'answers' => __('Answers', 'surveyflow'),
            'files'   => __('Files', 'surveyflow'),
            'view'    => __('View', 'surveyflow'),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'time' => ['time', true],
        ];
    }

    public function prepare_items() {
        $per_page    = 20;
        $current_page= $this->get_pagenum();
        $offset      = ($current_page - 1) * $per_page;

        global $wpdb;
        $table = \SurveyFlow\Core\Database::get_table_name();

        // Count total
        $this->total_items = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE survey_id = %d", $this->survey_id)
        );

        // Sorting
        $orderby = (!empty($_GET['orderby']) && $_GET['orderby'] === 'time') ? 'created_at' : 'created_at';
        $order   = (!empty($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

        // Fetch rows
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, ip, created_at, answers_json, uploads_json
                 FROM {$table}
                 WHERE survey_id = %d
                 ORDER BY {$orderby} {$order}
                 LIMIT %d OFFSET %d",
                $this->survey_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        // Transform rows
        $items = [];
        foreach ((array) $rows as $r) {
            $answers = json_decode($r['answers_json'] ?? '[]', true) ?: [];
            $uploads = json_decode($r['uploads_json'] ?? '[]', true) ?: [];
            $items[] = [
                'id'      => (int) $r['id'],
                'time'    => $r['created_at'],
                'user_id' => (int) $r['user_id'],
                'user'    => $r['user_id'] ? (get_userdata((int)$r['user_id'])?->user_login . ' (ID ' . (int)$r['user_id'] . ')') : __('Guest', 'surveyflow'),
                'ip'      => $r['ip'],
                'answers' => $answers,
                'files'   => $uploads,
            ];
        }

        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => $this->total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($this->total_items / $per_page),
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'time':
                return esc_html($item['time']);
            case 'user':
                return esc_html($item['user']);
            case 'ip':
                return esc_html($item['ip']);
            case 'answers':
                return is_array($item['answers']) ? count($item['answers']) : 0;
            case 'files':
                return is_array($item['files']) ? count($item['files']) : 0;
            default:
                return '';
        }
    }

    public function column_view($item) {
        $answers = $item['answers'];
        $uploads = $item['files'];

        $html  = '<details><summary>' . esc_html__('View Details', 'surveyflow') . '</summary>';
        $html .= '<div style="padding:8px 0 0 0;">';

        if (is_array($answers) && !empty($answers)) {
            $html .= '<div><strong>' . esc_html__('Answers', 'surveyflow') . '</strong><br>';
            foreach ($answers as $key => $val) {
                if (is_array($val)) $val = implode(', ', array_map('sanitize_text_field', $val));
                $html .= '<code>' . esc_html($key) . '</code>: ' . esc_html((string)$val) . '<br>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div>' . esc_html__('No answers recorded.', 'surveyflow') . '</div>';
        }

        if (is_array($uploads) && !empty($uploads)) {
            $html .= '<div style="margin-top:6px;"><strong>' . esc_html__('Uploads', 'surveyflow') . '</strong><br>';
            foreach ($uploads as $mk => $info) {
                $name = $info['name'] ?? basename($info['url'] ?? '');
                $url  = $info['url']  ?? '';
                if ($url) {
                    $html .= esc_html($mk) . ': <a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($name) . '</a><br>';
                } else {
                    $html .= esc_html($mk) . ': ' . esc_html($name) . '<br>';
                }
            }
            $html .= '</div>';
        }

        $html .= '</div></details>';
        return $html;
    }
}
