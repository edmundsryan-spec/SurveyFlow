<?php
namespace SurveyFlow\Core;
if ( ! class_exists( '\SurveyFlow\Core\Helpers' ) ) {
    class Helpers {
        public static function asset_url( $path ) {
            return plugins_url( $path, SURVEYFLOW_PLUGIN_FILE );
        }
        public static function post_has_survey( $post ) {
            if ( ! $post ) return false;
            if ( has_shortcode( $post->post_content, 'surveyflow' ) ) return true;
            if ( has_block( 'surveyflow/block', $post ) ) return true;
            return false;
        }
    }
}
