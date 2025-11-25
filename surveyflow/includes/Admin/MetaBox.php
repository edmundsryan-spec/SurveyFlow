<?php
namespace SurveyFlow\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Survey Builder Metabox (v2)
 * - New builder UI (toolbar + templates)
 * - Hidden JSON field + nonce
 * - Preload saved questions to JS via window.surveyflowSavedQuestions
 */
class MetaBox {
  public function render(\WP_Post $post) {

    // Get saved questions JSON (raw JSON string or empty)
    $saved_json = get_post_meta($post->ID, '_surveyflow_questions', true);
    if (!is_string($saved_json) || $saved_json === '') {
      $saved_json = '[]';
    }

    ?>
    <div id="surveyflow-builder" class="surveyflow-builder">

      <!-- Toolbar -->
      <div class="sf-toolbar">
        <button type="button" class="button button-secondary sf-toolbar-toggle" aria-label="<?php esc_attr_e('Toggle toolbar', 'surveyflow'); ?>">≡</button>

        <button type="button" class="button button-primary sf-add-question">+ <?php esc_html_e('Question', 'surveyflow'); ?></button>
        <button type="button" class="button sf-add-section">+ <?php esc_html_e('Section Title', 'surveyflow'); ?></button>
        <button type="button" class="button sf-add-break">+ <?php esc_html_e('Page Break', 'surveyflow'); ?></button>
        <button type="button" class="button sf-add-preq">+ <?php esc_html_e('Prequalifier', 'surveyflow'); ?></button>

        <span class="sf-toolbar-spacer"></span>

        <button type="button" class="button sf-preview-survey"><?php esc_html_e('Preview', 'surveyflow'); ?></button>
        <button type="button" class="button button-secondary sf-toolbar-save"><?php esc_html_e('Save', 'surveyflow'); ?></button>
      </div>

      <!-- List container -->
      <ul class="sf-questions" id="sf-questions"></ul>

      <!-- Templates -->
      <script type="text/template" id="tpl-question">
        <li class="sf-item sf-item-question" data-type="question">
          <div class="sf-item-head">
            <span class="dashicons dashicons-move sf-handle"></span>
            <span class="sf-item-title"><?php esc_html_e('Question', 'surveyflow'); ?></span>
            <button type="button" class="sf-remove" aria-label="<?php esc_attr_e('Remove', 'surveyflow'); ?>">×</button>
          </div>
          <div class="sf-item-body">
            <label class="sf-row sf-row-type">
              <span class="sf-label"><?php esc_html_e('Question Type', 'surveyflow'); ?></span>
              <select class="sf-type">
                <option value="short_text"><?php esc_html_e('Short Text', 'surveyflow'); ?></option>
                <option value="long_text"><?php esc_html_e('Long Text', 'surveyflow'); ?></option>
                <option value="email"><?php esc_html_e('Email', 'surveyflow'); ?></option>
                <option value="phone"><?php esc_html_e('Phone', 'surveyflow'); ?></option>
                <option value="address"><?php esc_html_e('Address', 'surveyflow'); ?></option>
                <option value="radio"><?php esc_html_e('Radio (choose one)', 'surveyflow'); ?></option>
                <option value="checkbox"><?php esc_html_e('Checkboxes (choose many)', 'surveyflow'); ?></option>
                <option value="dropdown"><?php esc_html_e('Dropdown', 'surveyflow'); ?></option>
                <option value="media_upload"><?php esc_html_e('Media Upload (respondent)', 'surveyflow'); ?></option>
              </select>
            </label>

            <label class="sf-row sf-row-title">
              <span class="sf-label"><?php esc_html_e('Question', 'surveyflow'); ?></span>
              <input type="text" class="sf-title" value="">
            </label>

            <div class="sf-options-wrap" style="display:none;">
              <div class="sf-options"></div>
              <button type="button" class="button sf-add-option"><?php esc_html_e('Add Option', 'surveyflow'); ?></button>
            </div>

            <label class="sf-row sf-required-row" style="display:none;">
              <input type="checkbox" class="sf-required"> <?php esc_html_e('Required', 'surveyflow'); ?>
            </label>

            <div class="sf-row sf-media-wrap">
              <span class="sf-label"><?php esc_html_e('Add Media (optional)', 'surveyflow'); ?></span>
              <div class="sf-media-controls">
                <input type="text" class="sf-media-url" readonly>
                <button type="button" class="button sf-media-pick"><?php esc_html_e('Add Media', 'surveyflow'); ?></button>
                <button type="button" class="button sf-media-clear" style="display:none;"><?php esc_html_e('Remove', 'surveyflow'); ?></button>
              </div>
            </div>
          </div>
        </li>
      </script>

      <script type="text/template" id="tpl-section">
        <li class="sf-item sf-item-section" data-type="section">
          <div class="sf-item-head">
            <span class="dashicons dashicons-move sf-handle"></span>
            <span class="sf-item-title"><?php esc_html_e('Section Title', 'surveyflow'); ?></span>
            <button type="button" class="sf-remove" aria-label="<?php esc_attr_e('Remove', 'surveyflow'); ?>">×</button>
          </div>
          <div class="sf-item-body">
            <label class="sf-row">
              <span class="sf-label"><?php esc_html_e('Title', 'surveyflow'); ?></span>
              <input type="text" class="sf-title" value="">
            </label>
            <label class="sf-row">
              <span class="sf-label"><?php esc_html_e('Description (optional)', 'surveyflow'); ?></span>
              <textarea class="sf-description"></textarea>
            </label>
          </div>
        </li>
      </script>

      <script type="text/template" id="tpl-break">
        <li class="sf-item sf-item-break" data-type="break">
          <div class="sf-item-head">
            <span class="dashicons dashicons-move sf-handle"></span>
            <span class="sf-item-title"><?php esc_html_e('Page Break', 'surveyflow'); ?></span>
            <button type="button" class="sf-remove" aria-label="<?php esc_attr_e('Remove', 'surveyflow'); ?>">×</button>
          </div>
          <div class="sf-item-body">
            <em><?php esc_html_e('Splits the form into pages.', 'surveyflow'); ?></em>
          </div>
        </li>
      </script>

      <script type="text/template" id="tpl-preq">
        <li class="sf-item sf-item-preq" data-type="prequalifier">
          <div class="sf-item-head">
            <span class="dashicons dashicons-move sf-handle"></span>
            <span class="sf-item-title"><?php esc_html_e('Prequalifier', 'surveyflow'); ?></span>
            <button type="button" class="sf-remove" aria-label="<?php esc_attr_e('Remove', 'surveyflow'); ?>">×</button>
          </div>
          <div class="sf-item-body">
            <fieldset class="sf-row sf-preq-subtype">
              <legend class="sf-label"><?php esc_html_e('Question Type', 'surveyflow'); ?></legend>
              <label><input type="radio" name="__PREQ_SUBTYPE__" value="radio" checked> <?php esc_html_e('Radio (single choice)', 'surveyflow'); ?></label>
              <label><input type="radio" name="__PREQ_SUBTYPE__" value="checkbox"> <?php esc_html_e('Checkboxes (many)', 'surveyflow'); ?></label>
              <label><input type="radio" name="__PREQ_SUBTYPE__" value="dropdown"> <?php esc_html_e('Dropdown', 'surveyflow'); ?></label>
            </fieldset>

            <label class="sf-row">
              <span class="sf-label"><?php esc_html_e('Question', 'surveyflow'); ?></span>
              <input type="text" class="sf-title" value="">
            </label>

            <div class="sf-options-wrap">
              <div class="sf-options"></div>
              <button type="button" class="button sf-add-option"><?php esc_html_e('Add Option', 'surveyflow'); ?></button>
              <p class="description"><?php esc_html_e('Toggle “Disqualify” on any option to end the survey with the disqualified message.', 'surveyflow'); ?></p>
            </div>
          </div>
        </li>
      </script>

      <!-- Hidden field where JSON is saved -->
      <input type="hidden" id="surveyflow_questions_json" name="surveyflow_questions_json"
             value="<?php echo esc_attr( get_post_meta($post->ID, '_surveyflow_questions', true) ); ?>">
      <?php wp_nonce_field('surveyflow_save_questions', 'surveyflow_questions_nonce'); ?>

      <!-- Preload to JS for reliable restore -->
      <script>
        // Preload saved questions for the builder to rehydrate.
        // This is raw JSON (already validated server-side).
        window.surveyflowSavedQuestions = <?php echo $saved_json; ?>;
      </script>
    </div>
    <?php
  }
}
