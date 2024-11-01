'use strict';

(function($) {
  $(function() {
    wpcuf_source_init();
    wpcuf_build_label();
    wpcuf_terms_init();
    wpcuf_enhanced_select();
    wpcuf_combination_init();
    wpcuf_combination_terms_init();
    wpcuf_timer_init();
    wpcuf_timer_picker();
    wpcuf_roles_init();
    wpcuf_sortable();
  });

  $(document).on('change', '.wpcuf_time_type', function() {
    wpcuf_timer_init($(this).closest('.wpcuf_time'));
  });

  $(document).on('click touch', '.wpcuf_new_time', function(e) {
    var $this = $(this);
    var $timer = $this.closest('.wpcuf_timer_wrap').find('.wpcuf_timer');
    var data = {
      action: 'wpcuf_add_time',
      name: $this.data('name'),
      key: $this.data('key'),
      nonce: wpcuf_vars.nonce,
    };

    $this.addClass('disabled');
    $timer.addClass('wpcuf_timer_loading');

    $.post(ajaxurl, data, function(response) {
      $timer.append(response);
      wpcuf_timer_init();
      wpcuf_timer_picker();
      $timer.removeClass('wpcuf_timer_loading');
      $this.removeClass('disabled');
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.wpcuf_time_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.wpcuf_time').remove();
    }
  });

  $(document).on('change', '.wpcuf_source_selector', function() {
    var $this = $(this);
    var type = $this.data('type');
    var $rule = $this.closest('.wpcuf_rule');

    wpcuf_source_init(type, $rule);
    wpcuf_build_label($rule);
    wpcuf_terms_init();
  });

  $(document).on('change, keyup', '.wpcuf_rule_name_val', function() {
    var name = $(this).val();

    $(this).
        closest('.wpcuf_rule').
        find('.wpcuf_rule_name').
        html(name.replace(/(<([^>]+)>)/ig, ''));
  });

  $(document).on('change', '.wpcuf_terms', function() {
    var $this = $(this);
    var type = $this.data('type');
    var apply = $(this).
        closest('.wpcuf_rule').
        find('.wpcuf_source_selector_' + type).
        val();

    $this.data(apply, $this.val().join());
  });

  $(document).on('change', '.wpcuf_combination_selector', function() {
    wpcuf_combination_init();
    wpcuf_combination_terms_init();
  });

  $(document).on('click touch', '.wpcuf_combination_remove', function() {
    $(this).closest('.wpcuf_combination').remove();
  });

  $(document).on('click touch', '.wpcuf_rule_heading', function(e) {
    if ($(e.target).closest('.wpcuf_rule_remove').length === 0 &&
        $(e.target).closest('.wpcuf_rule_duplicate').length === 0) {
      $(this).closest('.wpcuf_rule').toggleClass('active');
    }
  });

  $(document).on('click touch', '.wpcuf_new_combination', function(e) {
    var $combinations = $(this).
        closest('.wpcuf_tr').
        find('.wpcuf_combinations');
    var key = $(this).
        closest('.wpcuf_rule').data('key');
    var name = $(this).data('name');
    var type = $(this).data('type');
    var data = {
      action: 'wpcuf_add_combination',
      nonce: wpcuf_vars.nonce,
      key: key,
      name: name,
      type: type,
    };

    $combinations.addClass('wpcuf_combinations_loading');

    $.post(ajaxurl, data, function(response) {
      $combinations.append(response).removeClass('wpcuf_combinations_loading');
      wpcuf_combination_init();
      wpcuf_combination_terms_init();
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.wpcuf_new_rule', function(e) {
    e.preventDefault();
    $('.wpcuf_rules').addClass('wpcuf_rules_loading');

    var name = $(this).data('name');
    var data = {
      action: 'wpcuf_add_rule', nonce: wpcuf_vars.nonce, name: name,
    };

    $.post(ajaxurl, data, function(response) {
      $('.wpcuf_rules').append(response);
      wpcuf_source_init();
      wpcuf_build_label();
      wpcuf_terms_init();
      wpcuf_enhanced_select();
      wpcuf_combination_init();
      wpcuf_combination_terms_init();
      wpcuf_timer_init();
      wpcuf_timer_picker();
      wpcuf_roles_init();
      $('.wpcuf_rules').removeClass('wpcuf_rules_loading');
    });
  });

  $(document).on('click touch', '.wpcuf_rule_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.wpcuf_rule').remove();
    }
  });

  $(document).on('click touch', '.wpcuf_rule_duplicate', function(e) {
    e.preventDefault();
    $('.wpcuf_rules').addClass('wpcuf_rules_loading');

    var $rule = $(this).closest('.wpcuf_rule');
    var rule_data = $rule.find('input, select, button, textarea').
        serialize() || 0;
    var name = $(this).data('name');
    var data = {
      action: 'wpcuf_add_rule',
      nonce: wpcuf_vars.nonce,
      name: name,
      rule_data: rule_data,
    };

    $.post(ajaxurl, data, function(response) {
      $(response).insertAfter($rule);
      wpcuf_source_init();
      wpcuf_build_label();
      wpcuf_terms_init();
      wpcuf_enhanced_select();
      wpcuf_combination_init();
      wpcuf_combination_terms_init();
      wpcuf_timer_init();
      wpcuf_timer_picker();
      wpcuf_roles_init();
      $('.wpcuf_rules').removeClass('wpcuf_rules_loading');
    });
  });

  $(document).on('click touch', '.wpcuf_expand_all', function(e) {
    e.preventDefault();

    $('.wpcuf_rule').addClass('active');
  });

  $(document).on('click touch', '.wpcuf_collapse_all', function(e) {
    e.preventDefault();

    $('.wpcuf_rule').removeClass('active');
  });

  $(document).on('click touch', '.wpcuf_conditional_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.wpcuf_conditional_item').remove();
    }
  });

  $(document).on('click touch', '.wpcuf_import_export', function(e) {
    var name = $(this).data('name');

    if (!$('#wpcuf_import_export').length) {
      $('body').append('<div id=\'wpcuf_import_export\'></div>');
    }

    $('#wpcuf_import_export').html('Loading...');

    $('#wpcuf_import_export').dialog({
      minWidth: 460,
      title: 'Import/Export',
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').bind('click', function() {
          $('#wpcuf_import_export').dialog('close');
        });
      },
    });

    var data = {
      action: 'wpcuf_import_export', name: name, nonce: wpcuf_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('#wpcuf_import_export').html(response);
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.wpcuf_import_export_save', function(e) {
    var name = $(this).data('name');

    if (confirm('Are you sure?')) {
      $(this).addClass('disabled');

      var rules = $('.wpcuf_import_export_data').val();
      var data = {
        action: 'wpcuf_import_export_save',
        nonce: wpcuf_vars.nonce,
        rules: rules,
        name: name,
      };

      $.post(ajaxurl, data, function(response) {
        location.reload();
      });
    }
  });

  function wpcuf_terms_init() {
    $('.wpcuf_terms').each(function() {
      var $this = $(this);
      var type = $this.data('type');
      var apply = $this.closest('.wpcuf_rule').
          find('.wpcuf_source_selector_' + type).
          val();

      $this.selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              q: params.term, action: 'wpcuf_search_term', taxonomy: apply,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });

      if (apply !== 'all' && apply !== 'products' && apply !== 'combination') {
        // for terms only
        if ($this.data(apply) !== undefined && $this.data(apply) !== '') {
          $this.val(String($this.data(apply)).split(',')).change();
        } else {
          $this.val([]).change();
        }
      }
    });
  }

  function wpcuf_combination_init() {
    $('.wpcuf_combination_selector').each(function() {
      var $this = $(this);
      var $combination = $this.closest('.wpcuf_combination');
      var val = $this.val();

      if (val === 'price' || val === 'cart_subtotal' || val === 'cart_total' ||
          val === 'cart_count') {
        $combination.find('.wpcuf_combination_compare_wrap').hide();
        $combination.find('.wpcuf_combination_val_wrap').hide();
        $combination.find('.wpcuf_combination_number_compare_wrap').show();
        $combination.find('.wpcuf_combination_number_value_wrap').show();
      } else {
        $combination.find('.wpcuf_combination_number_compare_wrap').hide();
        $combination.find('.wpcuf_combination_number_value_wrap').hide();
        $combination.find('.wpcuf_combination_compare_wrap').show();
        $combination.find('.wpcuf_combination_val_wrap').show();
      }
    });
  }

  function wpcuf_combination_terms_init() {
    $('.wpcuf_apply_terms').each(function() {
      var $this = $(this);
      var taxonomy = $this.closest('.wpcuf_combination').
          find('.wpcuf_combination_selector').
          val();

      $this.selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              q: params.term, action: 'wpcuf_search_term', taxonomy: taxonomy,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });
    });
  }

  function wpcuf_source_init(type = 'apply', $rule) {
    if (typeof $rule !== 'undefined') {
      var apply = $rule.find('.wpcuf_source_selector_' + type).
          find(':selected').
          val();
      var text = $rule.find('.wpcuf_source_selector_' + type).
          find(':selected').
          text();

      $rule.find('.wpcuf_' + type + '_text').text(text);
      $rule.find('.hide_' + type).hide();
      $rule.find('.show_if_' + type + '_' + apply).show();
      $rule.find('.show_' + type).show();
      $rule.find('.hide_if_' + type + '_' + apply).hide();
    } else {
      $('.wpcuf_source_selector').each(function(e) {
        var type = $(this).data('type');
        var $rule = $(this).closest('.wpcuf_rule');
        var apply = $(this).find(':selected').val();
        var text = $(this).find(':selected').text();

        $rule.find('.wpcuf_' + type + '_text').text(text);
        $rule.find('.hide_' + type).hide();
        $rule.find('.show_if_' + type + '_' + apply).show();
        $rule.find('.show_' + type).show();
        $rule.find('.hide_if_' + type + '_' + apply).hide();
      });
    }
  }

  function wpcuf_sortable() {
    $('.wpcuf_rules').sortable({handle: '.wpcuf_rule_move'});
  }

  function wpcuf_build_label($rule) {
    if (typeof $rule !== 'undefined') {
      var apply = $rule.find('.wpcuf_source_selector_apply').
          find('option:selected').
          text();
      var get = $rule.find('.wpcuf_source_selector_get').
          find('option:selected').
          text();

      $rule.find('.wpcuf_rule_apply_get').
          html(
              'Applicable conditions: ' + apply + ' | Suggested products: ' +
              get);
    } else {
      $('.wpcuf_rule ').each(function() {
        var $this = $(this);
        var apply = $this.find('.wpcuf_source_selector_apply').
            find('option:selected').
            text();
        var get = $this.find('.wpcuf_source_selector_get').
            find('option:selected').
            text();

        $this.find('.wpcuf_rule_apply_get').
            html('Applicable conditions: ' + apply + ' | Suggested products: ' +
                get);
      });
    }
  }

  function wpcuf_enhanced_select() {
    $(document.body).trigger('wc-enhanced-select-init');
  }

  function wpcuf_roles_init() {
    $('.wpcuf_roles').selectWoo();
  }

  function wpcuf_timer_init($time) {
    if (typeof $time !== 'undefined') {
      var show = $time.find('.wpcuf_time_type').find(':selected').data('show');
      var $val = $time.find('.wpcuf_time_val');

      if ($val.data(show) !== undefined) {
        $val.val($val.data(show)).trigger('change');
      } else {
        $val.val('').trigger('change');
      }

      $time.find('.wpcuf_hide').hide();
      $time.find('.wpcuf_show_if_' + show).
          show();
    } else {
      $('.wpcuf_time').each(function() {
        var show = $(this).
            find('.wpcuf_time_type').
            find(':selected').
            data('show');
        var $val = $(this).find('.wpcuf_time_val');

        $val.data(show, $val.val());

        $(this).find('.wpcuf_hide').hide();
        $(this).find('.wpcuf_show_if_' + show).show();
      });
    }
  }

  function wpcuf_timer_picker() {
    $('.wpcuf_dpk_date_time:not(.wpcuf_dpk_init)').wpcdpk({
      timepicker: true, onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcuf_dpk_init');

    $('.wpcuf_dpk_date:not(.wpcuf_dpk_init)').wpcdpk({
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcuf_dpk_init');

    $('.wpcuf_dpk_date_range:not(.wpcuf_dpk_init)').wpcdpk({
      range: true,
      multipleDatesSeparator: ' - ',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcuf_dpk_init');

    $('.wpcuf_dpk_date_multi:not(.wpcuf_dpk_init)').wpcdpk({
      multipleDates: 5,
      multipleDatesSeparator: ', ',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcuf_dpk_init');

    $('.wpcuf_dpk_time:not(.wpcuf_dpk_init)').wpcdpk({
      timepicker: true,
      onlyTimepicker: true,
      classes: 'only-time',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcuf_time').
            find('.wpcuf_time_type').
            find(':selected').
            data('show');

        if (dpk.$el.hasClass('wpcuf_time_from') ||
            dpk.$el.hasClass('wpcuf_time_to')) {
          var time_range = dpk.$el.closest('.wpcuf_time').
                  find('.wpcuf_time_from').val() + ' - ' +
              dpk.$el.closest('.wpcuf_time').
                  find('.wpcuf_time_to').val();

          dpk.$el.closest('.wpcuf_time').
              find('.wpcuf_time_val').
              data(show, time_range).
              val(time_range).
              trigger('change');
        } else {
          dpk.$el.closest('.wpcuf_time').
              find('.wpcuf_time_val').data(show, fd).val(fd).trigger('change');
        }
      },
    }).addClass('wpcuf_dpk_init');
  }
})(jQuery);