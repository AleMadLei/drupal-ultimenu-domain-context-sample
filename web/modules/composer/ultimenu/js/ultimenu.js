/**
 * @file
 * Provides mobile toggler for the the Ultimenu main block.
 */

(function ($, Drupal, drupalSettings) {

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
    var elms = elm.querySelectorAll('.caret:not(.is-caret)');
    me.once(me.forEach(elms, function (caret) {
      $(caret).click(me.doClickCaret.bind(me));
      caret.classList.add('is-caret');
    }));

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
      context = me.context(context);

      var $body = $('body');
      var elms = context.querySelectorAll(_ultimenu);

      if (elms.length) {
        me.prepare();

        // @todo use core/once when min D9.2.
        me.once(me.forEach(elms, doUltimenu, context));

        // Reacts on clicking Ultimenu hamburger button.
        $body.off('.uBurger').on('click.uBurger', _hamburger, me.doClickHamburger.bind(me));
        $body.off('.uBackdrop').on('click.uBackdrop', '.' + _backdrop, me.triggerClickHamburger.bind(me));

        // Reacts on resizing Ultimenu.
        me.onResize(me.doResizeMain.bind(me))();
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
