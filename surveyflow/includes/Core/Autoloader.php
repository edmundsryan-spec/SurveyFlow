<?php
namespace SurveyFlow\Core;

if (!defined('ABSPATH')) exit;

/**
 * PSR-4-like autoloader for the SurveyFlow\* namespace.
 * Maps SurveyFlow\X\Y => /includes/X/Y.php
 */
class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        // Only handle SurveyFlow namespace
        if (strpos($class, 'SurveyFlow\\') !== 0) return;

        // Trim root namespace and map to /includes path
        $relative = str_replace('SurveyFlow\\', '', $class);
        $relative = str_replace('\\', '/', $relative);
        $path = SURVEYFLOW_PATH . 'includes/' . $relative . '.php';

        if (file_exists($path)) {
            require_once $path;
        }
    }
}