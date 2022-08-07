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

    if (drupalSettings.ultimenu && drupalSettings.ultimenu.ajaxmw && _win.matchMedia) {
      var mw = _win.matchMedia('(max-device-width: ' + drupalSettings.ultimenu.ajaxmw + ')');
      if (mw.matches) {
        var links = elm.querySelectorAll(_ajaxTrigger);
        if (links.length) {
          // Load all AJAX contents if so configured.
          $.forEach(links, function (el) {
            me.executeAjax(el);
          });

          return;
        }
      }
    }

    // Regular mobie/ desktop AJAX.
    $.on(elm, 'mouseover click', _ajaxTrigger, me.triggerAjax.bind(me));
    elm.classList.add(_mounted);
  }

  /**
   * Attaches Ultimenu behavior to HTML element [data-ultiajax].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ultimenuAjax = {
    attach: function (context) {

      var me = Drupal.ultimenu;
      context = $.context(context);

      var items = context.querySelectorAll(_ajaxContainer);
      if (items.length) {
        $.once($.forEach(items, doUltimenuAjax));
      }
    }
  };

})(dBlazy, Drupal, drupalSettings, this);
