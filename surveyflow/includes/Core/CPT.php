<?php
namespace SurveyFlow\Core;

if (!defined('ABSPATH')) exit;

/**
 * Registers the Survey CPT container.
 * Keeps it minimal: title only; all builder UI comes via metabox.
 */
class CPT
{
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    public function register(): void
    {
        $labels = [
            'name'                  => __('Surveys', 'surveyflow'),
            'singular_name'         => __('Survey', 'surveyflow'),
            'add_new'               => __('Add New', 'surveyflow'),
            'add_new_item'          => __('Add New Survey', 'surveyflow'),
            'edit_item'             => __('Edit Survey', 'surveyflow'),
            'new_item'              => __('New Survey', 'surveyflow'),
            'view_item'             => __('View Survey', 'surveyflow'),
            'search_items'          => __('Search Surveys', 'surveyflow'),
            'not_found'             => __('No surveys found', 'surveyflow'),
            'not_found_in_trash'    => __('No surveys found in Trash', 'surveyflow'),
            'menu_name'             => __('Surveys', 'surveyflow'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-feedback',
            'supports'           => ['title'],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'show_in_rest'       => true,
        ];

        register_post_type('surveyflow_survey', $args);
    }
}