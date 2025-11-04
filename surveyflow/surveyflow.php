<?php
/**
 * Plugin Name: SurveyFlow
 * Description: Lightweight, extensible survey builder.
 * Version: 1.0.0
 * Author: Ryan Edmunds
 * Author URI: https://ryanedmunds.com/surveyflow/
 * Text Domain: surveyflow
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

// Core path + URL + version constant
define('SURVEYFLOW_PATH', plugin_dir_path(__FILE__));
define('SURVEYFLOW_URL',  plugin_dir_url(__FILE__));
define('SURVEYFLOW_VERSION', '1.0.0');

// Autoloader
require_once SURVEYFLOW_PATH . 'includes/Core/Autoloader.php';
\SurveyFlow\Core\Autoloader::register();

// Create DB table on activation
register_activation_hook(__FILE__, function () {
    require_once SURVEYFLOW_PATH . 'includes/Core/Database.php';
    \SurveyFlow\Core\Database::create_tables();
});


// Core constants
define('SURVEYFLOW_VERSION', '1.0.0');
define('SURVEYFLOW_PATH', plugin_dir_path(__FILE__));
define('SURVEYFLOW_URL',  plugin_dir_url(__FILE__));

// Load and register autoloader FIRST
require_once SURVEYFLOW_PATH . 'includes/Core/Autoloader.php';
\SurveyFlow\Core\Autoloader::register();

// Boot core pieces
add_action('plugins_loaded', function () {
    // Core
    new \SurveyFlow\Core\CPT();
    new \SurveyFlow\Core\Assets();
    new \SurveyFlow\Admin\Admin();
    new \SurveyFlow\Frontend\Frontend();
});