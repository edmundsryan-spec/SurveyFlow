(function (wp) {
  const { registerBlockType } = wp.blocks;
  const { __ } = wp.i18n;
  const { useSelect } = wp.data;
  const {
    PanelBody,
    SelectControl,
    ToggleControl,
  } = wp.components;
  const blockEditor = wp.blockEditor || wp.editor;
  const {
    InspectorControls,
    BlockControls,
    BlockAlignmentControl,
    AlignmentToolbar,
  } = blockEditor;
  const { createElement: el, Fragment } = wp.element;

  // BoxControl – experimental in some WP versions
  const BoxControl =
    wp.components.__experimentalBoxControl || wp.components.BoxControl || null;

  registerBlockType('surveyflow/survey', {
    title: __('SurveyFlow Survey', 'surveyflow'),
    icon: 'feedback',
    category: 'widgets',

    attributes: {
      surveyId: {
        type: 'number',
        default: 0,
      },
      showTitle: {
        type: 'boolean',
        default: false,
      },
      // Layout alignment: '', 'wide', 'full'
      align: {
        type: 'string',
        default: '',
      },
      // Text alignment: '', 'left', 'center', 'right', 'justify'
      textAlign: {
        type: 'string',
        default: '',
      },
      // BoxControl-style objects
      padding: {
        type: 'object',
        default: null,
      },
      margin: {
        type: 'object',
        default: null,
      },
    },

    edit: function (props) {
      const { attributes, setAttributes, className } = props;
      const { surveyId, showTitle, align, textAlign, padding, margin } = attributes;

      // Load surveys (CPT: surveyflow_survey) from core data store
      const surveys = useSelect(function (select) {
        const core = select('core');
        const posts = core.getEntityRecords('postType', 'surveyflow_survey', {
          per_page: -1,
          _fields: ['id', 'title'],
        });
        return posts || [];
      }, []);

      const surveyOptions = [
        { label: __('Select a survey', 'surveyflow'), value: 0 },
      ].concat(
        surveys.map(function (post) {
          const label =
            post.title && post.title.rendered
              ? post.title.rendered
              : '(' + post.id + ')';
          return { label: label, value: post.id };
        })
      );

      const selectedSurvey = surveys.find(function (s) {
        return s.id === surveyId;
      });

      // === Toolbar: layout align (wide/full) + text align (left/center/right/justify) ===
      const toolbar = el(
        BlockControls,
        null,
        el(BlockAlignmentControl, {
          value: align || '',
          onChange: function (nextAlign) {
            setAttributes({ align: nextAlign || '' });
          },
          // Only wide + full for layout
          controls: ['wide', 'full'],
        }),
        el(AlignmentToolbar, {
          value: textAlign || '',
          onChange: function (nextAlign) {
            setAttributes({ textAlign: nextAlign || '' });
          },
        })
      );

      // === Inspector: survey settings + spacing ===
      const inspector = el(
        InspectorControls,
        null,
        el(
          PanelBody,
          {
            title: __('Survey Settings', 'surveyflow'),
            initialOpen: true,
          },
          el(SelectControl, {
            label: __('Survey', 'surveyflow'),
            value: surveyId,
            options: surveyOptions,
            onChange: function (value) {
              setAttributes({ surveyId: parseInt(value, 10) || 0 });
            },
          }),
          el(ToggleControl, {
            label: __('Show survey title', 'surveyflow'),
            checked: !!showTitle,
            onChange: function (val) {
              setAttributes({ showTitle: !!val });
            },
          })
        ),
        BoxControl &&
          el(
            PanelBody,
            {
              title: __('Spacing', 'surveyflow'),
              initialOpen: false,
            },
            el(BoxControl, {
              label: __('Padding', 'surveyflow'),
              values: padding || {},
              onChange: function (value) {
                setAttributes({ padding: value });
              },
            }),
            el(BoxControl, {
              label: __('Margin', 'surveyflow'),
              values: margin || {},
              onChange: function (value) {
                setAttributes({ margin: value });
              },
            })
          )
      );

      // Compute wrapper classes for preview
      const wrapperClasses = ['surveyflow-block-preview-wrapper'];
      if (align === 'full') {
        wrapperClasses.push('alignfull');
      } else if (align === 'wide') {
        wrapperClasses.push('sf-align-max');
      }
      if (textAlign) {
        wrapperClasses.push('has-text-align-' + textAlign);
      }
      if (className) {
        wrapperClasses.push(className);
      }

      // === Editor Preview ===
      let inner;
      if (surveyId && selectedSurvey) {
        const titleText =
          selectedSurvey.title && selectedSurvey.title.rendered
            ? selectedSurvey.title.rendered
            : __('Selected Survey', 'surveyflow');

        const maybeTitle = showTitle
          ? el(
              'h2',
              { className: 'surveyflow-block-preview-title' },
              titleText
            )
          : null;

        inner = el(
          'div',
          { className: 'surveyflow-block-preview' },
          maybeTitle,
          el(
            'p',
            null,
            __('Survey will be rendered on the front end.', 'surveyflow')
          )
        );
      } else {
        inner = el(
          'div',
          { className: 'surveyflow-block-preview' },
          el(
            'p',
            null,
            __('Select a survey to display.', 'surveyflow')
          )
        );
      }

      return el(
        Fragment,
        null,
        toolbar,
        inspector,
        el('div', { className: wrapperClasses.join(' ') }, inner)
      );
    },

    // Dynamic block – everything from PHP
    save: function () {
      return null;
    },
  });
})(window.wp);
