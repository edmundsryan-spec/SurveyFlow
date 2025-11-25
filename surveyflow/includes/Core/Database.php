<?php
namespace SurveyFlow\Core;

if (!defined('ABSPATH')) exit;

class Database {
    const TABLE = 'surveyflow_responses';

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Create/upgrade the responses table.
     * Columns:
     *  - id (PK)
     *  - survey_id (post ID)
     *  - user_id (0 if guest)
     *  - ip (varchar(64))
     *  - created_at (datetime)
     *  - disqualified (tinyint 0/1)
     *  - answers_json (longtext)
     *  - uploads_json (longtext)
     */
    public static function create_tables() {
        global $wpdb;
        $table   = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            survey_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            ip VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            disqualified TINYINT(1) NOT NULL DEFAULT 0,
            answers_json LONGTEXT NOT NULL,
            uploads_json LONGTEXT NOT NULL,
            PRIMARY KEY (id),
            KEY survey_id (survey_id),
            KEY user_id (user_id),
            KEY ip (ip),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
