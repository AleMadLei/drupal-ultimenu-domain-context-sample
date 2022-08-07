/**
 * @file
 * Provides mobile toggler for the the Ultimenu main block.
 */

(function (Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _hidingTimer = void 0;
  var _waitingTimer = void 0;
  var _name = 'ultimenu';
  var _isName = 'is-' + _name;
  var _canvas = _isName + '-canvas';
  var _isBodyActive = _canvas + '--active';
  var _isBodyHiding = _canvas + '--hiding';
  var _isBodyExpanded = _isName + '-expanded';
  var _isItemExpanded = _isName + '-item-expanded';
  var _isLinkActive = _isName + '-active';
  var _isHamburgerActive = _isName + '-button-active';
  var _isHit = 'data-ultiajax-hit';
  var _isHidden = 'hidden';
  var _flyout = 'flyout';
  var _isFlyout = 'is-' + _flyout;
  var _isFlyoutExpanded = _isFlyout + '-expanded';
  var _link = _name + '__link';
  var _ajaxLink = '.' + _name + '__ajax';
  var _ajaxTrigger = 'data-ultiajax-trigger';
  var _ultimenuFlyout = _name + '__' + _flyout;
  var _offCanvas = _canvas + '-off';
  var _onCanvas = _canvas + '-on';
  var _backdrop = _canvas + '-backdrop';
  var _hamburger = '[data-' + _name + '-button]';
  var $body = _doc.body;

  Drupal.ultimenu = {
    documentWidth: 0,
    $backdrop: null,
    $hamburger: null,
    $offCanvas: null,

    context: function (context) {
      // Weirdo: context may be null after Colorbox close.
      context = context || document;

      // jQuery may pass its object as non-expected context identified by length.
      context = 'length' in context ? context[0] : context;
      return context instanceof HTMLDocument ? context : document;
    },

    // @todo remove for core/once when min D9.2.
    once: function (fn) {
      var result;
      var ran = false;
      return function proxy() {
        if (ran) {
          return result;
        }
        ran = true;
        result = fn.apply(this, arguments);
        // For garbage collection.
        fn = null;
        return result;
      };
    },

    // @todo use dblazy at 3+ to DRY.
    isBlazy: 'dBlazy' in window,

    forEach: function (collection, callback, scope) {
      if (this.isBlazy) {
        return window.dBlazy.forEach(collection, callback, scope);
      }
      return collection.forEach(callback);
    },

    closest: function (el, selector) {
      if (this.isBlazy) {
        return window.dBlazy.closest(el, selector);
      }
      return el.closest(selector);
    },

    addRemoveClass: function (op, els, className) {
      var me = this;
      var classes = className.split(' ');
      if (els) {
        if (els.length) {
          me.forEach(els, function (el) {
            el.classList[op].apply(el.classList, classes);
          });
        }
        else if (els.classList) {
          els.classList[op].apply(els.classList, classes);
        }
      }
    },

    addClass: function (els, className) {
      this.addRemoveClass('add', els, className);
    },

    removeClass: function (els, className) {
      this.addRemoveClass('remove', els, className);
    },

    toggleClass: function (el, className) {
      if (el) {
        el.classList.toggle(className);
      }
    },

    slideToggle: function (el, className) {
      if (el) {
        this[el.clientHeight === 0 ? 'addClass' : 'removeClass'](el, className);
      }
    },

    doResizeMain: function () {
      var me = this;
      var width = _win.innerWidth || _doc.body.clientWidth;

      var closeOut = function () {
        me.removeClass($body, _isBodyExpanded + ' ' + _isBodyActive);
        me.closeFlyout();
      };

      // Do not cache the selector, to avoid incorrect classes with its cache.
      if (me.isHidden(_doc.querySelector(_hamburger))) {
        closeOut();
      }
      else {
        me.addClass($body, _isBodyActive);
      }

      if (width !== me.documentWidth) {
        return;
      }

      if (me.isHidden(_doc.querySelector(_hamburger))) {
        closeOut();
      }
      else if (width !== me.documentWidth) {
        me.addClass($body, _isBodyExpanded);

        if (!me.isHidden(_doc.querySelector(_hamburger))) {
          me.addClass($body, _isBodyActive);
        }
      }

      me.documentWidth = width;
    },

    executeAjax: function (el) {
      var me = this;
      var $li = me.closest(el, 'li');
      var $ajax = $li.querySelector(_ajaxLink);

      var cleanUp = function () {
        // Removes attribute to prevent this event from firing again.
        el.removeAttribute(_ajaxTrigger);
      };

      // The AJAX link will be gone on successful AJAX request.
      if ($ajax) {
        // Hover event can fire many times, prevents from too many clicks.
        if (!$ajax.hasAttribute(_isHit)) {
          $ajax.click();

          $ajax.setAttribute(_isHit, 1);
          me.addClass($ajax, _isHidden);
        }

        // This is the last resort while the user is hovering over menu link.
        // If the AJAX link is still there, an error likely stops it, or
        // the AJAX is taking longer time than 1.5 seconds. In such a case,
        // _waitingTimer will re-fire the click event, yet on interval now.
        // At any rate, Drupal.Ajax.ajaxing manages the AJAX requests.
        _win.clearTimeout(_waitingTimer);
        _waitingTimer = _win.setTimeout(function () {
          $ajax = $li.querySelector(_ajaxLink);
          if ($ajax) {
            me.removeClass($ajax, _isHidden);
            $ajax.click();
          }
          else {
            cleanUp();
          }
        }, 1500);
      }
      else {
        cleanUp();
      }
    },

    triggerAjax: function (e) {
      var me = this;
      e.stopPropagation();

      var $link = e.target.classList.contains('caret') ? me.closest(e.target, '.' + _link) : e.target;
      if ($link.hasAttribute(_ajaxTrigger)) {
        me.executeAjax($link);
      }
    },

    triggerClickHamburger: function (e) {
      var me = this;

      e.preventDefault();

      if (me.$hamburger) {
        me.$hamburger.click();
      }
    },

    doClickHamburger: function (e) {
      var me = this;

      e.preventDefault();
      e.stopPropagation();

      var $button = e.target;
      var expanded = $body.classList.contains(_isBodyExpanded);

      me[expanded ? 'removeClass' : 'addClass']($body, _isBodyExpanded);
      me[expanded ? 'removeClass' : 'addClass']($button, _isHamburgerActive);

      me.closeFlyout();

      // Cannot use transitionend as can be jumpy affected by child transitions.
      if (!expanded) {
        _win.clearTimeout(_hidingTimer);
        me.addClass($body, _isBodyHiding);

        _hidingTimer = _win.setTimeout(function () {
          me.removeClass($body, _isBodyHiding);
        }, 400);
      }

      // Scroll to top in case the current viewport is far below the fold.
      if (me.$backdrop) {
        _win.scroll({
          top: me.$backdrop.offsetTop,
          behavior: 'smooth'
        });
      }
    },

    closeFlyout: function () {
      var me = this;
      var actives = _doc.querySelectorAll('.' + _isLinkActive);
      var expands = _doc.querySelectorAll('.' + _isItemExpanded);
      var flyouts = _doc.querySelectorAll('.' + _isFlyoutExpanded);

      me.removeClass(actives, _isLinkActive);
      me.removeClass(expands, _isItemExpanded);
      me.removeClass(flyouts, _isFlyoutExpanded);
    },

    isHidden: function (el) {
      var style = el ? _win.getComputedStyle(el) : false;
      return el && (style.display === 'none' || style.visibility === 'invisible');
    },

    doClickCaret: function (e) {
      var me = this;

      e.preventDefault();
      e.stopPropagation();

      var $caret = e.target;
      var $link = me.closest($caret, '.' + _link);
      var $li = me.closest($link, 'li');
      var $flyout = $link.nextElementSibling;

      // If hoverable for desktop, one at a time click should hide flyouts.
      // We let regular mobile toggle not affected, to avoid jumping accordion.
      if (me.isHidden(me.$hamburger)) {
        me.closeFlyout();
      }

      // Toggle the current flyout.
      if ($flyout && $flyout.classList.contains(_ultimenuFlyout)) {
        var hidden = $flyout.clientHeight === 0;

        me[hidden ? 'addClass' : 'removeClass']($li, _isItemExpanded);
        me[hidden ? 'addClass' : 'removeClass']($link, _isLinkActive);

        me.slideToggle($flyout, _isFlyoutExpanded);
      }
    },

    onResize: function (c, t) {
      _win.onresize = function () {
        _win.clearTimeout(t);
        t = _win.setTimeout(c, 200);
      };
      return c;
    },

    prepare: function () {
      var me = this;
      var settings = drupalSettings.ultimenu || {};
      var btnMain = '[data-ultimenu-button="#ultimenu-main"]';

      $body = _doc.body;
      me.$offCanvas = _doc.querySelector('.' + _offCanvas);
      me.$hamburger = _doc.querySelector(_hamburger);
      me.$backdrop = _doc.querySelector('.' + _backdrop);

      // Allows hard-coded CSS classes to not use this.
      if (settings && (settings.canvasOff && settings.canvasOn)) {
        if (me.$offCanvas === null) {
          me.$offCanvas = _doc.querySelector(settings.canvasOff);
          me.addClass(me.$offCanvas, _offCanvas);
        }

        var $onCanvas = _doc.querySelector('.' + _onCanvas);
        if ($onCanvas === null) {
          var $onCanvases = _doc.querySelectorAll(settings.canvasOn);
          me.addClass($onCanvases, _onCanvas);
        }
      }

      // Moves the hamburger button to the end of the body.
      if (_doc.querySelector('body > ' + btnMain) === null) {
        var hamburger = _doc.querySelector(btnMain);
        if (hamburger) {
          _doc.body.appendChild(hamburger);
        }
      }

      // Prepends our backdrop before the main off-canvas element.
      if (me.$backdrop === null && me.$offCanvas) {
        var $parent = me.$offCanvas.parentNode;
        var el = _doc.createElement('div');
        el.className = _backdrop;
        $parent.insertBefore(el, $parent.firstElementChild || null);

        me.$backdrop = _doc.querySelector('.' + _backdrop);
      }
    }

  };

})(Drupal, drupalSettings, this, this.document);
