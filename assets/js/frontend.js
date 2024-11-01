'use strict';

(function($) {
  $(document).on('click touch', '.wpcuf-uf-product-add', function(e) {
    e.preventDefault();

    var $btn = $(this);
    var $wrap = $btn.closest('.wpcuf-uf-wrap');
    var $product = $btn.closest('.wpcuf-uf-product');

    if (!$product.hasClass('wpcuf-uf-product-disabled')) {
      var key = $product.data('key');
      var product_id = $product.data('product_id');
      var variation_id = $product.data('variation_id');
      var variation = $product.data('attrs');

      $wrap.addClass('wpcuf-uf-wrap-loading');
      $product.addClass('wpcuf-uf-product-loading');

      var data = {
        action: 'wpcuf_add_to_cart',
        type: 'uf',
        key: key,
        product_id: product_id,
        variation_id: variation_id,
        variation: variation,
        nonce: wpcuf_vars.nonce,
      };

      $.post(wpcuf_vars.wc_ajax_url.toString().
              replace('%%endpoint%%', 'wpcuf_add_to_cart'), data,
          function(response) {
            if (!response) {
              return;
            }

            if (response.error && response.product_url) {
              window.location = response.product_url;
              return;
            }

            if (wc_add_to_cart_params.cart_redirect_after_add === 'yes') {
              window.location = wc_add_to_cart_params.cart_url;
              return;
            }

            $(document.body).
                trigger('added_to_cart',
                    [response.fragments, response.cart_hash, $btn]);
            $(document.body).
                trigger('wpcuf_added_to_cart',
                    [response.fragments, response.cart_hash, $btn]);

            $product.removeClass('wpcuf-uf-product-loading').
                addClass('wpcuf-uf-product-added');
            $wrap.removeClass('wpcuf-uf-wrap-loading');

            if ($('form.woocommerce-cart-form').length) {
              $(document.body).trigger('wc_update_cart');
            } else {
              location.reload();
            }
          });
    }
  });

  $(document).on('change', '#wpcuf_ob_checkbox', function(e) {
    e.preventDefault();

    var $this = $(this);
    var $wrap = $this.closest('.wpcuf-ob-wrap');

    if (!$wrap.hasClass('wpcuf-ob-disabled')) {
      var key = $wrap.data('key');
      var item_key = $wrap.data('item_key');
      var product_id = $wrap.data('product_id');
      var variation_id = $wrap.data('variation_id');
      var variation = $wrap.data('attrs');
      var data = {};

      $wrap.addClass('wpcuf-ob-wrap-loading');

      if (!$this.prop('checked')) {
        // remove from cart
        data = {
          action: 'wpcuf_remove_from_cart',
          item_key: item_key,
          nonce: wpcuf_vars.nonce,
        };

        $.post(wpcuf_vars.wc_ajax_url.toString().
                replace('%%endpoint%%', 'wpcuf_remove_from_cart'), data,
            function(response) {
              $(document.body).trigger('wpcuf_removed_from_cart');

              $wrap.removeClass('wpcuf-ob-wrap-loading');

              if ($('.woocommerce-checkout').length) {
                $(document.body).trigger('update_checkout');
              } else {
                location.reload();
              }
            });
      } else {
        // add to cart
        data = {
          action: 'wpcuf_add_to_cart',
          type: 'ob',
          key: key,
          product_id: product_id,
          variation_id: variation_id,
          variation: variation,
          nonce: wpcuf_vars.nonce,
        };

        $.post(wpcuf_vars.wc_ajax_url.toString().
                replace('%%endpoint%%', 'wpcuf_add_to_cart'), data,
            function(response) {
              if (!response) {
                return;
              }

              if (response.error && response.product_url) {
                window.location = response.product_url;
                return;
              }

              if (wc_add_to_cart_params.cart_redirect_after_add === 'yes') {
                window.location = wc_add_to_cart_params.cart_url;
                return;
              }

              $(document.body).
                  trigger('added_to_cart',
                      [response.fragments, response.cart_hash]);
              $(document.body).
                  trigger('wpcuf_added_to_cart',
                      [response.fragments, response.cart_hash]);

              $wrap.removeClass('wpcuf-ob-wrap-loading');

              if ($('form.woocommerce-cart-form').length) {
                $(document.body).trigger('wc_update_cart');
              } else if ($('.woocommerce-checkout').length) {
                $(document.body).trigger('update_checkout');
              } else {
                location.reload();
              }
            });
      }
    }
  });

  $(document).on('updated_wc_div', function() {
    $(document).find('.wpcuf_variations_form').each(function() {
      $(this).wc_variation_form();
    });
  });

  $(document).on('woovr_selected', function(e, selected, variations) {
    var $product = variations.closest('.wpcuf-uf-product');

    if ($product.length) {
      var _id = selected.attr('data-id');
      var _purchasable = selected.attr('data-purchasable');
      var _attrs = selected.attr('data-attrs');

      if (_purchasable === 'yes' && _id >= 0) {
        // change data
        $product.attr('data-variation_id', _id);
        $product.attr('data-attrs', _attrs.replace(/\/$/, ''));
        $product.removeClass('wpcuf-uf-product-disabled');
      } else {
        // reset data
        $product.attr('data-variation_id', 0);
        $product.attr('data-attrs', '');
        $product.addClass('wpcuf-uf-product-disabled');
      }
    }
  });

  $(document).on('found_variation', function(e, t) {
    var $product = $(e['target']).closest('.wpcuf-uf-product');

    if ($product.length) {
      if (t['is_purchasable']) {
        if (t['is_in_stock']) {
          $product.attr('data-variation_id', t['variation_id']);
          $product.removeClass('wpcuf-uf-product-disabled');
        } else {
          $product.attr('data-variation_id', 0);
          $product.addClass('wpcuf-uf-product-disabled');
        }

        // change attributes
        var attrs = {};

        $product.find('select[name^="attribute_"]').each(function() {
          var attr_name = $(this).attr('name');

          attrs[attr_name] = $(this).val();
        });

        $product.attr('data-attrs', JSON.stringify(attrs));
      }

      $(document).trigger('wpcuf_found_variation', [$product, t]);
    }
  });

  $(document).on('reset_data', function(e) {
    var $product = $(e['target']).closest('.wpcuf-uf-product');

    if ($product.length) {
      // reset data
      $product.attr('data-variation_id', 0);
      $product.attr('data-attrs', '');
      $product.addClass('wpcuf-uf-product-disabled');

      $(document).trigger('wpcuf_reset_data', [$product]);
    }
  });
})(jQuery);