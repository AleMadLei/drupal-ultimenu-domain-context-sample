/**
 * @file
 * Provides AJAX functionality for Ultimenu blocks.
 */

(function ($, Drupal, drupalSettings, _win) {

  'use strict';

  var _name = 'ultiajax';
  var _mounted = _name + '--on';
  var _ajaxContainer = '[data-' + _name + ']:not(.' + _mounted + ')';
  var _ajaxTrigger = '[data-' + _name + '-trigger]';

  Drupal.ultimenu = Drupal.ultimenu || {};

  /**
   * Ultimenu utility functions for the ajaxified links, including main menu.
   *
   * @param {HTMLElement} elm
   *   The ultimenu HTML element.
   */
  function doUltimenuAjax(elm) {
    var me = Drupal.ultimenu;
    var $elm = $(elm);

    if (drupalSettings.ultimenu && drupalSettings.ultimenu.ajaxmw && _win.matchMedia) {
      var mw = _win.matchMedia('(max-device-width: ' + drupalSettings.ultimenu.ajaxmw + ')');
      if (mw.matches) {
        // Load all AJAX contents if so configured.
        $elm.find(_ajaxTrigger).each(function (i, el) {
          me.executeAjax(el);
        });

        return;
      }
    }

    // Regular mobie/ desktop AJAX.
    $elm.off().on('mouseover click', _ajaxTrigger, me.triggerAjax.bind(me));
    $elm.addClass(_mounted);
  }

  /**
   * Attaches Ultimenu behavior to HTML element [data-ultiajax].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ultimenuAjax = {
    attach: function (context) {

      var me = Drupal.ultimenu;
      context = me.context(context);

      var elms = context.querySelectorAll(_ajaxContainer);

      if (elms.length) {
        me.once(me.forEach(elms, doUltimenuAjax, context));
      }
    }
  };

})(jQuery, Drupal, drupalSettings, this);
