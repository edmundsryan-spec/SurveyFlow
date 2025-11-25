<?php
namespace SurveyFlow\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Registers the SurveyFlow Gutenberg block and handles rendering.
 */
class Blocks {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
    }

    public function register_block() {
        if (!function_exists('register_block_type')) {
            return; // Gutenberg not available
        }

        $debug   = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        // Use block.js for now; you can change to block.min.js later.
        $js_file = $debug ? 'block.js' : 'block.js';

        wp_register_script(
            'surveyflow-block',
            SURVEYFLOW_URL . 'assets/js/' . $js_file,
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-editor',
                'wp-block-editor',
                'wp-data',
                'wp-i18n',
            ],
            defined('SURVEYFLOW_VERSION') ? SURVEYFLOW_VERSION : '1.0.0',
            true
        );

        register_block_type('surveyflow/survey', [
            'editor_script'   => 'surveyflow-block',
            'render_callback' => [$this, 'render_survey_block'],
            'attributes'      => [
                'surveyId' => [
                    'type'    => 'integer',
                    'default' => 0,
                ],
                'showTitle' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
                // Layout align: '', 'wide', 'full'
                'align' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                // Text align: '', 'left', 'center', 'right', 'justify'
                'textAlign' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                // BoxControl-style objects
                'padding' => [
                    'type'    => 'object',
                    'default' => null,
                ],
                'margin' => [
                    'type'    => 'object',
                    'default' => null,
                ],
            ],
        ]);
    }

    /**
     * Server-side render for the SurveyFlow block.
     *
     * @param array  $attributes Block attributes from editor.
     * @param string $content    Not used (dynamic block).
     * @return string            HTML output.
     */
    public function render_survey_block($attributes, $content) {
        $survey_id  = isset($attributes['surveyId']) ? absint($attributes['surveyId']) : 0;
        $show_title = !empty($attributes['showTitle']);
        $align      = isset($attributes['align']) ? $attributes['align'] : '';
        $text_align = isset($attributes['textAlign']) ? $attributes['textAlign'] : '';
        $padding    = isset($attributes['padding']) && is_array($attributes['padding'])
                        ? $attributes['padding']
                        : [];
        $margin     = isset($attributes['margin']) && is_array($attributes['margin'])
                        ? $attributes['margin']
                        : [];

        if (!$survey_id || get_post_type($survey_id) !== 'surveyflow_survey') {
            return '';
        }

        // Wrapper classes
        $classes = ['surveyflow-block-wrap'];

        // Layout alignment
        if ($align === 'full') {
            $classes[] = 'alignfull';
        } elseif ($align === 'wide') {
            // We treat "wide" as "max width" in CSS
            $classes[] = 'sf-align-max';
        }

        // Text alignment – follow core class naming
        if (in_array($text_align, ['left', 'center', 'right', 'justify'], true)) {
            $classes[] = 'has-text-align-' . $text_align;
        }

        $classes    = array_map('sanitize_html_class', $classes);
        $class_attr = implode(' ', $classes);

        // Build inline style from padding/margin
        $style_parts = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (isset($padding[$side]) && $padding[$side] !== '') {
                $val = trim($padding[$side]);
                $style_parts[] = 'padding-' . $side . ':' . $val;
            }
        }
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (isset($margin[$side]) && $margin[$side] !== '') {
                $val = trim($margin[$side]);
                $style_parts[] = 'margin-' . $side . ':' . $val;
            }
        }

        $style_attr = '';
        if (!empty($style_parts)) {
            $style_attr = ' style="' . esc_attr(implode(';', $style_parts)) . '"';
        }

        $output  = '<div class="' . esc_attr($class_attr) . '"' . $style_attr . '>';

        if ($show_title) {
            $title = get_the_title($survey_id);
            if ($title) {
                $output .= '<h2 class="surveyflow-title">' . esc_html($title) . '</h2>';
            }
        }

        $output .= do_shortcode('[surveyflow id="' . $survey_id . '"]');
        $output .= '</div>';

        return $output;
    }
}
