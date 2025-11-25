/**
 * SurveyFlow Frontend
 * Refactored: modular, accessible, concise.
 * Requirements:
 *  - jQuery
 *  - Buttons use classes: .sf-btn-next, .sf-btn-prev, .sf-btn-submit
 *  - Questions carry data attrs: data-type, data-index, data-required (1/0), data-subtype (for prequalifier)
 *  - "Other" option: input/option has data-other="1" and the related text input uses .sf-other
 *  - Prequalifier questions always treated as required
 *  - Progress bar present in DOM but hidden by default; enabled via surveyflowSettings.progressBarEnabled
 */
(function ($) {
  'use strict';

  // Provide a safe default settings object if not localized yet
  if (typeof window.surveyflowSettings === 'undefined') {
    window.surveyflowSettings = { progressBarEnabled: false };
  }

  const SurveyFlow = {
    $form: null,
    $pages: null,
    currentPage: 1,
    totalPages: 0,
    reduceMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,

    init() {
      this.$form = $('.surveyflow-form');
      if (!this.$form.length) return;

      this.$pages = this.$form.find('.surveyflow-page');
      this.totalPages = this.$pages.length;

      // Init first page
      this.$pages.hide().filter('[data-page="1"]').show().addClass('active');
      this.currentPage = 1;

      this.bindEvents();
      this.updateNav();
      this.initProgress();
      this.initOtherFields();
    },

    bindEvents() {
      const self = this;

      // Next
      this.$form.on('click', '.sf-btn-next', function (e) {
        e.preventDefault();
        if (!self.validatePage(self.currentPage)) return;
        self.gotoPage(self.currentPage + 1, 'next');
      });

      // Prev
      this.$form.on('click', '.sf-btn-prev', function (e) {
        e.preventDefault();
        self.gotoPage(self.currentPage - 1, 'prev');
      });

      // Submit
      this.$form.on('submit', function (e) {
        e.preventDefault();
        // Validate current page (earlier pages were validated as user progressed)
        if (!self.validatePage(self.currentPage)) return;
        self.handleSubmit();
      });

      // Prequalifier: radio/checkbox
      this.$form.on(
        'change',
        '.surveyflow-question[data-type="prequalifier"] input[type=radio], .surveyflow-question[data-type="prequalifier"] input[type=checkbox]',
        function () {
          const isDisq =
            $(this).data('disqualify') === 1 || $(this).data('disqualify') === '1';
          if (isDisq && $(this).is(':checked')) self.disqualifyNow();
        }
      );
      // Prequalifier: dropdown
      this.$form.on(
        'change',
        '.surveyflow-question[data-type="prequalifier"] .sf-preq-select',
        function () {
          const $opt = $(this).find('option:selected');
          const isDisq =
            $opt.data('disqualify') === 1 || $opt.data('disqualify') === '1';
          if (isDisq) self.disqualifyNow();
        }
      );

      // "Other" reveal logic
      // Radio
      this.$form.on('change', '.surveyflow-question input[type=radio][data-other]', function () {
        const $q = $(this).closest('.surveyflow-question');
        self.toggleOtherForRadio($q);
      });
      // Checkbox
      this.$form.on('change', '.surveyflow-question input[type=checkbox][data-other]', function () {
        const $q = $(this).closest('.surveyflow-question');
        self.toggleOtherForCheckbox($q);
      });
    },

    pageExists(n) {
      return this.$pages.filter('[data-page="' + n + '"]').length > 0;
    },

    updateNav() {
      const hasNext = this.pageExists(this.currentPage + 1);
      const hasPrev = this.currentPage > 1;
      this.$form.find('.sf-btn-prev').toggle(hasPrev);
      this.$form.find('.sf-btn-next').toggle(hasNext);
      this.$form.find('.sf-btn-submit').toggle(!hasNext);
      this.updateProgress();
    },

    gotoPage(n, direction) {
      if (!this.pageExists(n)) return;

      const $old = this.$pages.filter('[data-page="' + this.currentPage + '"]');
      const $next = this.$pages.filter('[data-page="' + n + '"]');

      if (!this.reduceMotion) {
        if (direction === 'prev') {
          $old.addClass('slide-in'); // slide right
        } else {
          $old.addClass('slide-out'); // slide left
        }
        setTimeout(() => {
          $old.removeClass('active slide-out slide-in').hide();
          $next.show().addClass('active');
        }, 300);
      } else {
        $old.removeClass('active slide-out slide-in').hide();
        $next.show().addClass('active');
      }

      this.currentPage = n;
      this.updateNav();
      this.scrollToFormIfNeeded();
    },

    scrollToFormIfNeeded() {
      const formTop = this.$form.offset().top - 20;
      const viewTop = $(window).scrollTop();
      const viewBottom = viewTop + $(window).height();
      if (formTop < viewTop || formTop > viewBottom - 100) {
        $([document.documentElement, document.body]).animate(
          { scrollTop: formTop },
          this.reduceMotion ? 0 : 200
        );
      }
    },

    initProgress() {
      if (!window.surveyflowSettings.progressBarEnabled) return;
      // Show progress container if enabled
      const $wrap = this.$form.prev('.surveyflow-progress');
      if ($wrap.length) $wrap.show();
      this.updateProgress();
    },

    updateProgress() {
      if (!window.surveyflowSettings.progressBarEnabled) return;
      const $bar = $('.surveyflow-progress-bar');
      if (!$bar.length) return;
      const pct = (this.currentPage / this.totalPages) * 100;
      $bar.css('width', pct + '%');
    },

    // ---- Validation ----
    validatePage(pageNum) {
      const self = this;
      let valid = true;

      // Clear previous errors on this page
      const $page = this.$pages.filter('[data-page="' + pageNum + '"]');
      $page.find('.surveyflow-question').removeClass('has-error');
      $page.find('.surveyflow-error-msg').remove();

      $page.find('.surveyflow-question').each(function () {
        const $q = $(this);
        const type = ($q.data('type') || '').toString();
        const requiredAttr = parseInt($q.data('required'), 10);
        const isPrequal = type === 'prequalifier';
        const isRequired = isPrequal || requiredAttr === 1;

        if (!isRequired) return; // skip non-required

        let isFilled = false;

        if (type === 'checkbox') {
          const $checked = $q.find('input[type=checkbox]:checked');
          isFilled = $checked.length > 0;
          // If "Other" is checked AND there's an .sf-other input, require it non-empty
          const $otherBox = $q.find('input[type=checkbox][data-other]:checked');
          if ($otherBox.length) {
            const otherVal = ($q.find('.sf-other').val() || '').trim();
            isFilled = isFilled && otherVal.length > 0;
          }
        } else if (type === 'radio') {
          const $sel = $q.find('input[type=radio]:checked');
          isFilled = $sel.length > 0;
          // If selected is "Other", require .sf-other
          if ($sel.length && ($sel.data('other') === 1 || $sel.data('other') === '1')) {
            const otherVal = ($q.find('.sf-other').val() || '').trim();
            isFilled = otherVal.length > 0;
          }
        } else if (type === 'dropdown') {
          const val = $q.find('select').val() || '';
          isFilled = val.toString().length > 0;
        } else if (type === 'long_text') {
          const val = ($q.find('textarea').val() || '').trim();
          isFilled = val.length > 0;
        } else if (type === 'media_upload') {
          const f = $q.find('input.sf-file')[0];
          isFilled = !!(f && f.files && f.files.length);
        } else if (type === 'prequalifier') {
          const sub = ($q.data('subtype') || 'radio').toString();
          if (sub === 'checkbox') {
            isFilled = $q.find('input[type=checkbox]:checked').length > 0;
          } else if (sub === 'dropdown') {
            const val = $q.find('select').val() || '';
            isFilled = val.toString().length > 0;
          } else {
            isFilled = $q.find('input[type=radio]:checked').length > 0;
          }
        } else {
          // short_text, email, phone, address (text inputs)
          const val = ($q.find('input[type=text]').val() || '').trim();
          isFilled = val.length > 0;
        }

        if (!isFilled) {
          valid = false;
          self.flagError($q, self.requiredMessage($q));
        }
      });

      return valid;
    },

    requiredMessage($q) {
      // Customize per type later if needed
      return $('<div/>', {
        class: 'surveyflow-error-msg',
        text: 'This field is required.'
      });
    },

    flagError($q, $msg) {
      $q.addClass('has-error');
      // Aria
      const $inputs = $q.find('input, select, textarea');
      $inputs.attr('aria-invalid', 'true');
      // Place message after control group
      // Try to place after the last form control in the question block
      const $anchor =
        $q.find('.sf-control, .sf-options, .sf-field').last().length
          ? $q.find('.sf-control, .sf-options, .sf-field').last()
          : $q.children().last();
      $anchor.after($msg);
      // Focus first control of the question for accessibility
      const $firstInput = $q.find('input, select, textarea').first();
      if ($firstInput.length) $firstInput.trigger('focus');
    },

    // ---- OTHER reveal ----
    initOtherFields() {
      const self = this;
      // Initial state per question
      this.$form.find('.surveyflow-question').each(function () {
        const $q = $(this);
        const type = ($q.data('type') || '').toString();
        if (type === 'radio') self.toggleOtherForRadio($q);
        if (type === 'checkbox') self.toggleOtherForCheckbox($q);
      });
    },

    toggleOtherForRadio($q) {
      const $sel = $q.find('input[type=radio]:checked');
      const isOther = $sel.length && ($sel.data('other') === 1 || $sel.data('other') === '1');
      const $other = $q.find('.sf-other');
      if (!$other.length) return;
      if (isOther) {
        $other.show().attr('aria-hidden', 'false').trigger('focus');
      } else {
        $other.val('').hide().attr('aria-hidden', 'true');
      }
    },

    toggleOtherForCheckbox($q) {
      const $otherBox = $q.find('input[type=checkbox][data-other]');
      const $other = $q.find('.sf-other');
      if (!$otherBox.length || !$other.length) return;
      // Active if any "other" box checked
      const active = $otherBox.is(':checked');
      if (active) {
        $other.show().attr('aria-hidden', 'false');
      } else {
        $other.val('').hide().attr('aria-hidden', 'true');
      }
    },

    // ---- Submit ----
    collectAnswers() {
      const answers = {};
      this.$form.find('.surveyflow-question').each(function () {
        const $q = $(this);
        const idx = $q.data('index');
        if (idx === undefined) return;
        const base = 'q_' + idx;
        const type = ($q.data('type') || '').toString();
        let val = null;

        if (type === 'checkbox') {
          val = $q.find('input[type=checkbox]:checked')
            .map(function () { return this.value; }).get();
          const other = ($q.find('.sf-other').val() || '').trim();
          if (other) val.push(other);
        } else if (type === 'radio') {
          val = $q.find('input[type=radio]:checked').val() || '';
          const $sel = $q.find('input[type=radio]:checked');
          if ($sel.length && ($sel.data('other') === 1 || $sel.data('other') === '1')) {
            const other = ($q.find('.sf-other').val() || '').trim();
            if (other) val = other; // replace with typed value
          }
        } else if (type === 'dropdown') {
          val = $q.find('select').val() || '';
        } else if (type === 'long_text') {
          val = ($q.find('textarea').val() || '').trim();
        } else if (type === 'media_upload') {
          val = '(file)'; // file appended separately
        } else if (type === 'prequalifier') {
          const sub = ($q.data('subtype') || 'radio').toString();
          if (sub === 'checkbox') {
            val = $q.find('input[type=checkbox]:checked')
              .map(function () { return this.value; }).get();
          } else if (sub === 'dropdown') {
            val = $q.find('select').val() || '';
          } else {
            val = $q.find('input[type=radio]:checked').val() || '';
          }
        } else {
          // short_text, email, phone, address
          val = ($q.find('input[type=text]').val() || '').trim();
        }

        answers[base] = val;
      });
      return answers;
    },

    handleSubmit() {
      const fd = new FormData();
      fd.append('action', 'surveyflow_submit');
      fd.append('nonce', (window.surveyflowAjax && surveyflowAjax.nonce) ? surveyflowAjax.nonce : '');
      fd.append('survey_id', this.$form.data('survey-id'));
      fd.append('answers', JSON.stringify(this.collectAnswers()));

      // Append files
      this.$form.find('input.sf-file').each(function () {
        if (this.files && this.files[0]) {
          fd.append(this.name, this.files[0], this.files[0].name);
        }
      });

      $.ajax({
        url: (window.surveyflowAjax && surveyflowAjax.ajaxUrl) ? surveyflowAjax.ajaxUrl : '',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: (resp) => {
          if (resp && resp.success) {
            this.$form.hide();
            $('.surveyflow-message').text(resp.data.message).show();
            // UI cookie backup (server should still enforce)
            document.cookie =
              'surveyflow_done_' + this.$form.data('survey-id') +
              '=1;path=/;max-age=' + (365 * 24 * 60 * 60);
          } else {
            alert(resp && resp.data && resp.data.message ? resp.data.message : 'Submission failed.');
          }
        },
        error: () => {
          alert('Submission failed. Please try again.');
        }
      });
    },

    disqualifyNow() {
      const msg = this.$form.data('disq-msg') || 'You do not qualify for this survey.';
      this.$form.hide();
      $('.surveyflow-message').text(msg).show();
    }
  };

  $(function () {
    SurveyFlow.init();
  });
})(jQuery);
