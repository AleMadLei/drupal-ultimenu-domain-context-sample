/**
 * @file
 * Provides mobile toggler for the the Ultimenu main block.
 */

(function ($, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _name = 'ultimenu';
  var _mounted = _name + '--on';
  var _canvas = 'is-' + _name + '-canvas';
  var _backdrop = _canvas + '-backdrop';
  var _hamburger = '[data-' + _name + '-button]';
  var _ultimenu = '[data-' + _name + ']:not(.' + _mounted + ')';

  Drupal.ultimenu = Drupal.ultimenu || {};

  /**
   * Ultimenu utility functions for the main menu only.
   *
   * @param {HTMLElement} elm
   *   The ultimenu HTML element.
   */
  function doUltimenu(elm) {
    var me = Drupal.ultimenu;

    // Applies to other Ultimenus.
    $.on(elm, 'click', '.caret', me.doClickCaret.bind(me));

    elm.classList.add(_mounted);
  }

  /**
   * Attaches Ultimenu behavior to HTML element [data-ultimenu].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ultimenu = {
    attach: function (context) {

      var me = Drupal.ultimenu;
      context = $.context(context);

      var $body = _doc.body;
      var items = context.querySelectorAll(_ultimenu);

      if (items.length) {
        me.prepare();

        // @todo use core/once when min D9.2.
        $.once($.forEach(items, doUltimenu));

        // Reacts on clicking Ultimenu hamburger button.
        $.on($body, 'click', _hamburger, me.doClickHamburger.bind(me));
        $.on($body, 'click', '.' + _backdrop, me.triggerClickHamburger.bind(me));

        // Reacts on resizing Ultimenu.
        me.onResize(me.doResizeMain.bind(me))();
      }
    }
  };

})(dBlazy, Drupal, drupalSettings, this, this.document);
