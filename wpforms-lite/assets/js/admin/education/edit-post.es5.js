(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);throw new Error("Cannot find module '"+o+"'")}var f=n[o]={exports:{}};t[o][0].call(f.exports,function(e){var n=t[o][1][e];return s(n?n:e)},f,f.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
"use strict";

function _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t.return && (u = t.return(), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(r) { if (Array.isArray(r)) return r; }
/* global wpforms_edit_post_education */

// noinspection ES6ConvertVarToLetConst
/**
 * WPForms Edit Post Education function.
 *
 * @since 1.8.1
 */

// eslint-disable-next-line no-var, no-unused-vars
var WPFormsEditPostEducation = window.WPFormsEditPostEducation || function (document, window, $) {
  /**
   * Public functions and properties.
   *
   * @since 1.8.1
   *
   * @type {Object}
   */
  var app = {
    /**
     * Determine if the notice was shown before.
     *
     * @since 1.8.1
     */
    isNoticeVisible: false,
    /**
     * Start the engine.
     *
     * @since 1.8.1
     */
    init: function init() {
      $(window).on('load', function () {
        // In the case of jQuery 3.+, we need to wait for a ready event first.
        if (typeof $.ready.then === 'function') {
          $.ready.then(app.load);
        } else {
          app.load();
        }
      });
    },
    /**
     * Page load.
     *
     * @since 1.8.1
     */
    load: function load() {
      if (!app.isGutenbergEditor()) {
        app.maybeShowClassicNotice();
        app.bindClassicEvents();
        return;
      }
      var blockLoadedInterval = setInterval(function () {
        if (!document.querySelector('.editor-post-title__input, iframe[name="editor-canvas"]')) {
          return;
        }
        clearInterval(blockLoadedInterval);
        if (!app.isFse()) {
          app.maybeShowGutenbergNotice();
          app.bindGutenbergEvents();
          return;
        }
        var iframe = document.querySelector('iframe[name="editor-canvas"]');
        var observer = new MutationObserver(function () {
          var iframeDocument = iframe.contentDocument || iframe.contentWindow.document || {};
          if (iframeDocument.readyState === 'complete' && iframeDocument.querySelector('.editor-post-title__input')) {
            app.maybeShowGutenbergNotice();
            app.bindFseEvents();
            observer.disconnect();
          }
        });
        observer.observe(document.body, {
          subtree: true,
          childList: true
        });
      }, 200);
    },
    /**
     * Bind events for Classic Editor.
     *
     * @since 1.8.1
     */
    bindClassicEvents: function bindClassicEvents() {
      var $document = $(document);
      if (!app.isNoticeVisible) {
        $document.on('input', '#title', _.debounce(app.maybeShowClassicNotice, 1000));
      }
      $document.on('click', '.wpforms-edit-post-education-notice-close', app.closeNotice);
    },
    /**
     * Bind events for Gutenberg Editor.
     *
     * @since 1.8.1
     */
    bindGutenbergEvents: function bindGutenbergEvents() {
      var $document = $(document);
      $document.on('DOMSubtreeModified', '.edit-post-layout', app.distractionFreeModeToggle);
      if (app.isNoticeVisible) {
        return;
      }
      $document.on('input', '.editor-post-title__input', _.debounce(app.maybeShowGutenbergNotice, 1000)).on('DOMSubtreeModified', '.editor-post-title__input', _.debounce(app.maybeShowGutenbergNotice, 1000));
    },
    /**
     * Bind events for Gutenberg Editor in FSE mode.
     *
     * @since 1.8.1
     */
    bindFseEvents: function bindFseEvents() {
      var $iframe = $('iframe[name="editor-canvas"]');
      $(document).on('DOMSubtreeModified', '.edit-post-layout', app.distractionFreeModeToggle);
      $iframe.contents().on('input', '.editor-post-title__input', _.debounce(app.maybeShowGutenbergNotice, 1000)).on('DOMSubtreeModified', '.editor-post-title__input', _.debounce(app.maybeShowGutenbergNotice, 1000));
    },
    /**
     * Determine if the editor is Gutenberg.
     *
     * @since 1.8.1
     *
     * @return {boolean} True if the editor is Gutenberg.
     */
    isGutenbergEditor: function isGutenbergEditor() {
      return typeof wp !== 'undefined' && typeof wp.blocks !== 'undefined';
    },
    /**
     * Determine if the editor is Gutenberg in FSE mode.
     *
     * @since 1.8.1
     *
     * @return {boolean} True if the Gutenberg editor in FSE mode.
     */
    isFse: function isFse() {
      return Boolean($('iframe[name="editor-canvas"]').length);
    },
    /**
     * Create a notice for Gutenberg.
     *
     * @since 1.8.1
     */
    showGutenbergNotice: function showGutenbergNotice() {
      wp.data.dispatch('core/notices').createInfoNotice(wpforms_edit_post_education.gutenberg_notice.template, app.getGutenbergNoticeSettings());

      // The notice component doesn't have a way to add HTML id or class to the notice.
      // Also, the notice became visible with a delay on old Gutenberg versions.
      var hasNotice = setInterval(function () {
        var noticeBody = $('.wpforms-edit-post-education-notice-body');
        if (!noticeBody.length) {
          return;
        }
        var $notice = noticeBody.closest('.components-notice');
        $notice.addClass('wpforms-edit-post-education-notice');
        $notice.find('.is-secondary, .is-link').removeClass('is-secondary').removeClass('is-link').addClass('is-primary');

        // We can't use onDismiss callback as it was introduced in WordPress 6.0 only.
        var dismissButton = $notice.find('.components-notice__dismiss');
        if (dismissButton) {
          dismissButton.on('click', function () {
            app.updateUserMeta();
          });
        }
        clearInterval(hasNotice);
      }, 100);
    },
    /**
     * Get settings for the Gutenberg notice.
     *
     * @since 1.8.1
     *
     * @return {Object} Notice settings.
     */
    getGutenbergNoticeSettings: function getGutenbergNoticeSettings() {
      var pluginName = 'wpforms-edit-post-product-education-guide';
      var noticeSettings = {
        id: pluginName,
        isDismissible: true,
        HTML: true,
        __unstableHTML: true,
        actions: [{
          className: 'wpforms-edit-post-education-notice-guide-button',
          variant: 'primary',
          label: wpforms_edit_post_education.gutenberg_notice.button
        }]
      };
      if (!wpforms_edit_post_education.gutenberg_guide) {
        noticeSettings.actions[0].url = wpforms_edit_post_education.gutenberg_notice.url;
        return noticeSettings;
      }
      var Guide = wp.components.Guide;
      var useState = wp.element.useState;
      var registerPlugin = wp.plugins.registerPlugin;
      var unregisterPlugin = wp.plugins.unregisterPlugin;
      var GutenbergTutorial = function GutenbergTutorial() {
        var _useState = useState(true),
          _useState2 = _slicedToArray(_useState, 2),
          isOpen = _useState2[0],
          setIsOpen = _useState2[1];
        if (!isOpen) {
          return null;
        }
        return (
          /*#__PURE__*/
          // eslint-disable-next-line react/react-in-jsx-scope
          React.createElement(Guide, {
            className: "edit-post-welcome-guide",
            onFinish: function onFinish() {
              unregisterPlugin(pluginName);
              setIsOpen(false);
            },
            pages: app.getGuidePages()
          })
        );
      };
      noticeSettings.actions[0].onClick = function () {
        return registerPlugin(pluginName, {
          render: GutenbergTutorial
        });
      };
      return noticeSettings;
    },
    /**
     * Get Guide pages in proper format.
     *
     * @since 1.8.1
     *
     * @return {Array} Guide Pages.
     */
    getGuidePages: function getGuidePages() {
      var pages = [];
      wpforms_edit_post_education.gutenberg_guide.forEach(function (page) {
        pages.push({
          /* eslint-disable react/react-in-jsx-scope */
          content: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("h1", {
            className: "edit-post-welcome-guide__heading"
          }, page.title), /*#__PURE__*/React.createElement("p", {
            className: "edit-post-welcome-guide__text"
          }, page.content)),
          image: /*#__PURE__*/React.createElement("img", {
            className: "edit-post-welcome-guide__image",
            src: page.image,
            alt: page.title
          })
          /* eslint-enable react/react-in-jsx-scope */
        });
      });
      return pages;
    },
    /**
     * Show notice if the page title matches some keywords for Classic Editor.
     *
     * @since 1.8.1
     */
    maybeShowClassicNotice: function maybeShowClassicNotice() {
      if (app.isNoticeVisible) {
        return;
      }
      if (app.isTitleMatchKeywords($('#title').val())) {
        app.isNoticeVisible = true;
        $('.wpforms-edit-post-education-notice').removeClass('wpforms-hidden');
      }
    },
    /**
     * Show notice if the page title matches some keywords for Gutenberg Editor.
     *
     * @since 1.8.1
     */
    maybeShowGutenbergNotice: function maybeShowGutenbergNotice() {
      if (app.isNoticeVisible) {
        return;
      }
      var $postTitle = app.isFse() ? $('iframe[name="editor-canvas"]').contents().find('.editor-post-title__input') : $('.editor-post-title__input');
      var tagName = $postTitle.prop('tagName');
      var title = tagName === 'TEXTAREA' ? $postTitle.val() : $postTitle.text();
      if (app.isTitleMatchKeywords(title)) {
        app.isNoticeVisible = true;
        app.showGutenbergNotice();
      }
    },
    /**
     * Add notice class when the distraction mode is enabled.
     *
     * @since 1.8.1.2
     */
    distractionFreeModeToggle: function distractionFreeModeToggle() {
      if (!app.isNoticeVisible) {
        return;
      }
      var $document = $(document);
      var isDistractionFreeMode = Boolean($document.find('.is-distraction-free').length);
      if (!isDistractionFreeMode) {
        return;
      }
      var isNoticeHasClass = Boolean($('.wpforms-edit-post-education-notice').length);
      if (isNoticeHasClass) {
        return;
      }
      var $noticeBody = $document.find('.wpforms-edit-post-education-notice-body');
      var $notice = $noticeBody.closest('.components-notice');
      $notice.addClass('wpforms-edit-post-education-notice');
    },
    /**
     * Determine if the title matches keywords.
     *
     * @since 1.8.1
     *
     * @param {string} titleValue Page title value.
     *
     * @return {boolean} True if the title matches some keywords.
     */
    isTitleMatchKeywords: function isTitleMatchKeywords(titleValue) {
      var expectedTitleRegex = new RegExp(/\b(contact|form)\b/i);
      return expectedTitleRegex.test(titleValue);
    },
    /**
     * Close a notice.
     *
     * @since 1.8.1
     */
    closeNotice: function closeNotice() {
      $(this).closest('.wpforms-edit-post-education-notice').remove();
      app.updateUserMeta();
    },
    /**
     * Update user meta and don't show the notice next time.
     *
     * @since 1.8.1
     */
    updateUserMeta: function updateUserMeta() {
      $.post(wpforms_edit_post_education.ajax_url, {
        action: 'wpforms_education_dismiss',
        nonce: wpforms_edit_post_education.education_nonce,
        section: 'edit-post-notice'
      });
    }
  };
  return app;
}(document, window, jQuery);
WPFormsEditPostEducation.init();
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJuYW1lcyI6WyJXUEZvcm1zRWRpdFBvc3RFZHVjYXRpb24iLCJ3aW5kb3ciLCJkb2N1bWVudCIsIiQiLCJhcHAiLCJpc05vdGljZVZpc2libGUiLCJpbml0Iiwib24iLCJyZWFkeSIsInRoZW4iLCJsb2FkIiwiaXNHdXRlbmJlcmdFZGl0b3IiLCJtYXliZVNob3dDbGFzc2ljTm90aWNlIiwiYmluZENsYXNzaWNFdmVudHMiLCJibG9ja0xvYWRlZEludGVydmFsIiwic2V0SW50ZXJ2YWwiLCJxdWVyeVNlbGVjdG9yIiwiY2xlYXJJbnRlcnZhbCIsImlzRnNlIiwibWF5YmVTaG93R3V0ZW5iZXJnTm90aWNlIiwiYmluZEd1dGVuYmVyZ0V2ZW50cyIsImlmcmFtZSIsIm9ic2VydmVyIiwiTXV0YXRpb25PYnNlcnZlciIsImlmcmFtZURvY3VtZW50IiwiY29udGVudERvY3VtZW50IiwiY29udGVudFdpbmRvdyIsInJlYWR5U3RhdGUiLCJiaW5kRnNlRXZlbnRzIiwiZGlzY29ubmVjdCIsIm9ic2VydmUiLCJib2R5Iiwic3VidHJlZSIsImNoaWxkTGlzdCIsIiRkb2N1bWVudCIsIl8iLCJkZWJvdW5jZSIsImNsb3NlTm90aWNlIiwiZGlzdHJhY3Rpb25GcmVlTW9kZVRvZ2dsZSIsIiRpZnJhbWUiLCJjb250ZW50cyIsIndwIiwiYmxvY2tzIiwiQm9vbGVhbiIsImxlbmd0aCIsInNob3dHdXRlbmJlcmdOb3RpY2UiLCJkYXRhIiwiZGlzcGF0Y2giLCJjcmVhdGVJbmZvTm90aWNlIiwid3Bmb3Jtc19lZGl0X3Bvc3RfZWR1Y2F0aW9uIiwiZ3V0ZW5iZXJnX25vdGljZSIsInRlbXBsYXRlIiwiZ2V0R3V0ZW5iZXJnTm90aWNlU2V0dGluZ3MiLCJoYXNOb3RpY2UiLCJub3RpY2VCb2R5IiwiJG5vdGljZSIsImNsb3Nlc3QiLCJhZGRDbGFzcyIsImZpbmQiLCJyZW1vdmVDbGFzcyIsImRpc21pc3NCdXR0b24iLCJ1cGRhdGVVc2VyTWV0YSIsInBsdWdpbk5hbWUiLCJub3RpY2VTZXR0aW5ncyIsImlkIiwiaXNEaXNtaXNzaWJsZSIsIkhUTUwiLCJfX3Vuc3RhYmxlSFRNTCIsImFjdGlvbnMiLCJjbGFzc05hbWUiLCJ2YXJpYW50IiwibGFiZWwiLCJidXR0b24iLCJndXRlbmJlcmdfZ3VpZGUiLCJ1cmwiLCJHdWlkZSIsImNvbXBvbmVudHMiLCJ1c2VTdGF0ZSIsImVsZW1lbnQiLCJyZWdpc3RlclBsdWdpbiIsInBsdWdpbnMiLCJ1bnJlZ2lzdGVyUGx1Z2luIiwiR3V0ZW5iZXJnVHV0b3JpYWwiLCJfdXNlU3RhdGUiLCJfdXNlU3RhdGUyIiwiX3NsaWNlZFRvQXJyYXkiLCJpc09wZW4iLCJzZXRJc09wZW4iLCJSZWFjdCIsImNyZWF0ZUVsZW1lbnQiLCJvbkZpbmlzaCIsInBhZ2VzIiwiZ2V0R3VpZGVQYWdlcyIsIm9uQ2xpY2siLCJyZW5kZXIiLCJmb3JFYWNoIiwicGFnZSIsInB1c2giLCJjb250ZW50IiwiRnJhZ21lbnQiLCJ0aXRsZSIsImltYWdlIiwic3JjIiwiYWx0IiwiaXNUaXRsZU1hdGNoS2V5d29yZHMiLCJ2YWwiLCIkcG9zdFRpdGxlIiwidGFnTmFtZSIsInByb3AiLCJ0ZXh0IiwiaXNEaXN0cmFjdGlvbkZyZWVNb2RlIiwiaXNOb3RpY2VIYXNDbGFzcyIsIiRub3RpY2VCb2R5IiwidGl0bGVWYWx1ZSIsImV4cGVjdGVkVGl0bGVSZWdleCIsIlJlZ0V4cCIsInRlc3QiLCJyZW1vdmUiLCJwb3N0IiwiYWpheF91cmwiLCJhY3Rpb24iLCJub25jZSIsImVkdWNhdGlvbl9ub25jZSIsInNlY3Rpb24iLCJqUXVlcnkiXSwic291cmNlcyI6WyJmYWtlXzk4OGRhMGYzLmpzIl0sInNvdXJjZXNDb250ZW50IjpbIi8qIGdsb2JhbCB3cGZvcm1zX2VkaXRfcG9zdF9lZHVjYXRpb24gKi9cblxuLy8gbm9pbnNwZWN0aW9uIEVTNkNvbnZlcnRWYXJUb0xldENvbnN0XG4vKipcbiAqIFdQRm9ybXMgRWRpdCBQb3N0IEVkdWNhdGlvbiBmdW5jdGlvbi5cbiAqXG4gKiBAc2luY2UgMS44LjFcbiAqL1xuXG4vLyBlc2xpbnQtZGlzYWJsZS1uZXh0LWxpbmUgbm8tdmFyLCBuby11bnVzZWQtdmFyc1xudmFyIFdQRm9ybXNFZGl0UG9zdEVkdWNhdGlvbiA9IHdpbmRvdy5XUEZvcm1zRWRpdFBvc3RFZHVjYXRpb24gfHwgKCBmdW5jdGlvbiggZG9jdW1lbnQsIHdpbmRvdywgJCApIHtcblx0LyoqXG5cdCAqIFB1YmxpYyBmdW5jdGlvbnMgYW5kIHByb3BlcnRpZXMuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjguMVxuXHQgKlxuXHQgKiBAdHlwZSB7T2JqZWN0fVxuXHQgKi9cblx0Y29uc3QgYXBwID0ge1xuXG5cdFx0LyoqXG5cdFx0ICogRGV0ZXJtaW5lIGlmIHRoZSBub3RpY2Ugd2FzIHNob3duIGJlZm9yZS5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqL1xuXHRcdGlzTm90aWNlVmlzaWJsZTogZmFsc2UsXG5cblx0XHQvKipcblx0XHQgKiBTdGFydCB0aGUgZW5naW5lLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOC4xXG5cdFx0ICovXG5cdFx0aW5pdCgpIHtcblx0XHRcdCQoIHdpbmRvdyApLm9uKCAnbG9hZCcsIGZ1bmN0aW9uKCkge1xuXHRcdFx0XHQvLyBJbiB0aGUgY2FzZSBvZiBqUXVlcnkgMy4rLCB3ZSBuZWVkIHRvIHdhaXQgZm9yIGEgcmVhZHkgZXZlbnQgZmlyc3QuXG5cdFx0XHRcdGlmICggdHlwZW9mICQucmVhZHkudGhlbiA9PT0gJ2Z1bmN0aW9uJyApIHtcblx0XHRcdFx0XHQkLnJlYWR5LnRoZW4oIGFwcC5sb2FkICk7XG5cdFx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdFx0YXBwLmxvYWQoKTtcblx0XHRcdFx0fVxuXHRcdFx0fSApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBQYWdlIGxvYWQuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHRsb2FkKCkge1xuXHRcdFx0aWYgKCAhIGFwcC5pc0d1dGVuYmVyZ0VkaXRvcigpICkge1xuXHRcdFx0XHRhcHAubWF5YmVTaG93Q2xhc3NpY05vdGljZSgpO1xuXHRcdFx0XHRhcHAuYmluZENsYXNzaWNFdmVudHMoKTtcblxuXHRcdFx0XHRyZXR1cm47XG5cdFx0XHR9XG5cblx0XHRcdGNvbnN0IGJsb2NrTG9hZGVkSW50ZXJ2YWwgPSBzZXRJbnRlcnZhbCggZnVuY3Rpb24oKSB7XG5cdFx0XHRcdGlmICggISBkb2N1bWVudC5xdWVyeVNlbGVjdG9yKCAnLmVkaXRvci1wb3N0LXRpdGxlX19pbnB1dCwgaWZyYW1lW25hbWU9XCJlZGl0b3ItY2FudmFzXCJdJyApICkge1xuXHRcdFx0XHRcdHJldHVybjtcblx0XHRcdFx0fVxuXG5cdFx0XHRcdGNsZWFySW50ZXJ2YWwoIGJsb2NrTG9hZGVkSW50ZXJ2YWwgKTtcblxuXHRcdFx0XHRpZiAoICEgYXBwLmlzRnNlKCkgKSB7XG5cdFx0XHRcdFx0YXBwLm1heWJlU2hvd0d1dGVuYmVyZ05vdGljZSgpO1xuXHRcdFx0XHRcdGFwcC5iaW5kR3V0ZW5iZXJnRXZlbnRzKCk7XG5cblx0XHRcdFx0XHRyZXR1cm47XG5cdFx0XHRcdH1cblxuXHRcdFx0XHRjb25zdCBpZnJhbWUgPSBkb2N1bWVudC5xdWVyeVNlbGVjdG9yKCAnaWZyYW1lW25hbWU9XCJlZGl0b3ItY2FudmFzXCJdJyApO1xuXHRcdFx0XHRjb25zdCBvYnNlcnZlciA9IG5ldyBNdXRhdGlvbk9ic2VydmVyKCBmdW5jdGlvbigpIHtcblx0XHRcdFx0XHRjb25zdCBpZnJhbWVEb2N1bWVudCA9IGlmcmFtZS5jb250ZW50RG9jdW1lbnQgfHwgaWZyYW1lLmNvbnRlbnRXaW5kb3cuZG9jdW1lbnQgfHwge307XG5cblx0XHRcdFx0XHRpZiAoIGlmcmFtZURvY3VtZW50LnJlYWR5U3RhdGUgPT09ICdjb21wbGV0ZScgJiYgaWZyYW1lRG9jdW1lbnQucXVlcnlTZWxlY3RvciggJy5lZGl0b3ItcG9zdC10aXRsZV9faW5wdXQnICkgKSB7XG5cdFx0XHRcdFx0XHRhcHAubWF5YmVTaG93R3V0ZW5iZXJnTm90aWNlKCk7XG5cdFx0XHRcdFx0XHRhcHAuYmluZEZzZUV2ZW50cygpO1xuXG5cdFx0XHRcdFx0XHRvYnNlcnZlci5kaXNjb25uZWN0KCk7XG5cdFx0XHRcdFx0fVxuXHRcdFx0XHR9ICk7XG5cdFx0XHRcdG9ic2VydmVyLm9ic2VydmUoIGRvY3VtZW50LmJvZHksIHsgc3VidHJlZTogdHJ1ZSwgY2hpbGRMaXN0OiB0cnVlIH0gKTtcblx0XHRcdH0sIDIwMCApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBCaW5kIGV2ZW50cyBmb3IgQ2xhc3NpYyBFZGl0b3IuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHRiaW5kQ2xhc3NpY0V2ZW50cygpIHtcblx0XHRcdGNvbnN0ICRkb2N1bWVudCA9ICQoIGRvY3VtZW50ICk7XG5cblx0XHRcdGlmICggISBhcHAuaXNOb3RpY2VWaXNpYmxlICkge1xuXHRcdFx0XHQkZG9jdW1lbnQub24oICdpbnB1dCcsICcjdGl0bGUnLCBfLmRlYm91bmNlKCBhcHAubWF5YmVTaG93Q2xhc3NpY05vdGljZSwgMTAwMCApICk7XG5cdFx0XHR9XG5cblx0XHRcdCRkb2N1bWVudC5vbiggJ2NsaWNrJywgJy53cGZvcm1zLWVkaXQtcG9zdC1lZHVjYXRpb24tbm90aWNlLWNsb3NlJywgYXBwLmNsb3NlTm90aWNlICk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIEJpbmQgZXZlbnRzIGZvciBHdXRlbmJlcmcgRWRpdG9yLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOC4xXG5cdFx0ICovXG5cdFx0YmluZEd1dGVuYmVyZ0V2ZW50cygpIHtcblx0XHRcdGNvbnN0ICRkb2N1bWVudCA9ICQoIGRvY3VtZW50ICk7XG5cblx0XHRcdCRkb2N1bWVudFxuXHRcdFx0XHQub24oICdET01TdWJ0cmVlTW9kaWZpZWQnLCAnLmVkaXQtcG9zdC1sYXlvdXQnLCBhcHAuZGlzdHJhY3Rpb25GcmVlTW9kZVRvZ2dsZSApO1xuXG5cdFx0XHRpZiAoIGFwcC5pc05vdGljZVZpc2libGUgKSB7XG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblxuXHRcdFx0JGRvY3VtZW50XG5cdFx0XHRcdC5vbiggJ2lucHV0JywgJy5lZGl0b3ItcG9zdC10aXRsZV9faW5wdXQnLCBfLmRlYm91bmNlKCBhcHAubWF5YmVTaG93R3V0ZW5iZXJnTm90aWNlLCAxMDAwICkgKVxuXHRcdFx0XHQub24oICdET01TdWJ0cmVlTW9kaWZpZWQnLCAnLmVkaXRvci1wb3N0LXRpdGxlX19pbnB1dCcsIF8uZGVib3VuY2UoIGFwcC5tYXliZVNob3dHdXRlbmJlcmdOb3RpY2UsIDEwMDAgKSApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBCaW5kIGV2ZW50cyBmb3IgR3V0ZW5iZXJnIEVkaXRvciBpbiBGU0UgbW9kZS5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqL1xuXHRcdGJpbmRGc2VFdmVudHMoKSB7XG5cdFx0XHRjb25zdCAkaWZyYW1lID0gJCggJ2lmcmFtZVtuYW1lPVwiZWRpdG9yLWNhbnZhc1wiXScgKTtcblxuXHRcdFx0JCggZG9jdW1lbnQgKVxuXHRcdFx0XHQub24oICdET01TdWJ0cmVlTW9kaWZpZWQnLCAnLmVkaXQtcG9zdC1sYXlvdXQnLCBhcHAuZGlzdHJhY3Rpb25GcmVlTW9kZVRvZ2dsZSApO1xuXG5cdFx0XHQkaWZyYW1lLmNvbnRlbnRzKClcblx0XHRcdFx0Lm9uKCAnaW5wdXQnLCAnLmVkaXRvci1wb3N0LXRpdGxlX19pbnB1dCcsIF8uZGVib3VuY2UoIGFwcC5tYXliZVNob3dHdXRlbmJlcmdOb3RpY2UsIDEwMDAgKSApXG5cdFx0XHRcdC5vbiggJ0RPTVN1YnRyZWVNb2RpZmllZCcsICcuZWRpdG9yLXBvc3QtdGl0bGVfX2lucHV0JywgXy5kZWJvdW5jZSggYXBwLm1heWJlU2hvd0d1dGVuYmVyZ05vdGljZSwgMTAwMCApICk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIERldGVybWluZSBpZiB0aGUgZWRpdG9yIGlzIEd1dGVuYmVyZy5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqXG5cdFx0ICogQHJldHVybiB7Ym9vbGVhbn0gVHJ1ZSBpZiB0aGUgZWRpdG9yIGlzIEd1dGVuYmVyZy5cblx0XHQgKi9cblx0XHRpc0d1dGVuYmVyZ0VkaXRvcigpIHtcblx0XHRcdHJldHVybiB0eXBlb2Ygd3AgIT09ICd1bmRlZmluZWQnICYmIHR5cGVvZiB3cC5ibG9ja3MgIT09ICd1bmRlZmluZWQnO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBEZXRlcm1pbmUgaWYgdGhlIGVkaXRvciBpcyBHdXRlbmJlcmcgaW4gRlNFIG1vZGUuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKlxuXHRcdCAqIEByZXR1cm4ge2Jvb2xlYW59IFRydWUgaWYgdGhlIEd1dGVuYmVyZyBlZGl0b3IgaW4gRlNFIG1vZGUuXG5cdFx0ICovXG5cdFx0aXNGc2UoKSB7XG5cdFx0XHRyZXR1cm4gQm9vbGVhbiggJCggJ2lmcmFtZVtuYW1lPVwiZWRpdG9yLWNhbnZhc1wiXScgKS5sZW5ndGggKTtcblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogQ3JlYXRlIGEgbm90aWNlIGZvciBHdXRlbmJlcmcuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHRzaG93R3V0ZW5iZXJnTm90aWNlKCkge1xuXHRcdFx0d3AuZGF0YS5kaXNwYXRjaCggJ2NvcmUvbm90aWNlcycgKS5jcmVhdGVJbmZvTm90aWNlKFxuXHRcdFx0XHR3cGZvcm1zX2VkaXRfcG9zdF9lZHVjYXRpb24uZ3V0ZW5iZXJnX25vdGljZS50ZW1wbGF0ZSxcblx0XHRcdFx0YXBwLmdldEd1dGVuYmVyZ05vdGljZVNldHRpbmdzKClcblx0XHRcdCk7XG5cblx0XHRcdC8vIFRoZSBub3RpY2UgY29tcG9uZW50IGRvZXNuJ3QgaGF2ZSBhIHdheSB0byBhZGQgSFRNTCBpZCBvciBjbGFzcyB0byB0aGUgbm90aWNlLlxuXHRcdFx0Ly8gQWxzbywgdGhlIG5vdGljZSBiZWNhbWUgdmlzaWJsZSB3aXRoIGEgZGVsYXkgb24gb2xkIEd1dGVuYmVyZyB2ZXJzaW9ucy5cblx0XHRcdGNvbnN0IGhhc05vdGljZSA9IHNldEludGVydmFsKCBmdW5jdGlvbigpIHtcblx0XHRcdFx0Y29uc3Qgbm90aWNlQm9keSA9ICQoICcud3Bmb3Jtcy1lZGl0LXBvc3QtZWR1Y2F0aW9uLW5vdGljZS1ib2R5JyApO1xuXHRcdFx0XHRpZiAoICEgbm90aWNlQm9keS5sZW5ndGggKSB7XG5cdFx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0XHR9XG5cblx0XHRcdFx0Y29uc3QgJG5vdGljZSA9IG5vdGljZUJvZHkuY2xvc2VzdCggJy5jb21wb25lbnRzLW5vdGljZScgKTtcblx0XHRcdFx0JG5vdGljZS5hZGRDbGFzcyggJ3dwZm9ybXMtZWRpdC1wb3N0LWVkdWNhdGlvbi1ub3RpY2UnICk7XG5cdFx0XHRcdCRub3RpY2UuZmluZCggJy5pcy1zZWNvbmRhcnksIC5pcy1saW5rJyApLnJlbW92ZUNsYXNzKCAnaXMtc2Vjb25kYXJ5JyApLnJlbW92ZUNsYXNzKCAnaXMtbGluaycgKS5hZGRDbGFzcyggJ2lzLXByaW1hcnknICk7XG5cblx0XHRcdFx0Ly8gV2UgY2FuJ3QgdXNlIG9uRGlzbWlzcyBjYWxsYmFjayBhcyBpdCB3YXMgaW50cm9kdWNlZCBpbiBXb3JkUHJlc3MgNi4wIG9ubHkuXG5cdFx0XHRcdGNvbnN0IGRpc21pc3NCdXR0b24gPSAkbm90aWNlLmZpbmQoICcuY29tcG9uZW50cy1ub3RpY2VfX2Rpc21pc3MnICk7XG5cdFx0XHRcdGlmICggZGlzbWlzc0J1dHRvbiApIHtcblx0XHRcdFx0XHRkaXNtaXNzQnV0dG9uLm9uKCAnY2xpY2snLCBmdW5jdGlvbigpIHtcblx0XHRcdFx0XHRcdGFwcC51cGRhdGVVc2VyTWV0YSgpO1xuXHRcdFx0XHRcdH0gKTtcblx0XHRcdFx0fVxuXG5cdFx0XHRcdGNsZWFySW50ZXJ2YWwoIGhhc05vdGljZSApO1xuXHRcdFx0fSwgMTAwICk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIEdldCBzZXR0aW5ncyBmb3IgdGhlIEd1dGVuYmVyZyBub3RpY2UuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKlxuXHRcdCAqIEByZXR1cm4ge09iamVjdH0gTm90aWNlIHNldHRpbmdzLlxuXHRcdCAqL1xuXHRcdGdldEd1dGVuYmVyZ05vdGljZVNldHRpbmdzKCkge1xuXHRcdFx0Y29uc3QgcGx1Z2luTmFtZSA9ICd3cGZvcm1zLWVkaXQtcG9zdC1wcm9kdWN0LWVkdWNhdGlvbi1ndWlkZSc7XG5cdFx0XHRjb25zdCBub3RpY2VTZXR0aW5ncyA9IHtcblx0XHRcdFx0aWQ6IHBsdWdpbk5hbWUsXG5cdFx0XHRcdGlzRGlzbWlzc2libGU6IHRydWUsXG5cdFx0XHRcdEhUTUw6IHRydWUsXG5cdFx0XHRcdF9fdW5zdGFibGVIVE1MOiB0cnVlLFxuXHRcdFx0XHRhY3Rpb25zOiBbXG5cdFx0XHRcdFx0e1xuXHRcdFx0XHRcdFx0Y2xhc3NOYW1lOiAnd3Bmb3Jtcy1lZGl0LXBvc3QtZWR1Y2F0aW9uLW5vdGljZS1ndWlkZS1idXR0b24nLFxuXHRcdFx0XHRcdFx0dmFyaWFudDogJ3ByaW1hcnknLFxuXHRcdFx0XHRcdFx0bGFiZWw6IHdwZm9ybXNfZWRpdF9wb3N0X2VkdWNhdGlvbi5ndXRlbmJlcmdfbm90aWNlLmJ1dHRvbixcblx0XHRcdFx0XHR9LFxuXHRcdFx0XHRdLFxuXHRcdFx0fTtcblxuXHRcdFx0aWYgKCAhIHdwZm9ybXNfZWRpdF9wb3N0X2VkdWNhdGlvbi5ndXRlbmJlcmdfZ3VpZGUgKSB7XG5cdFx0XHRcdG5vdGljZVNldHRpbmdzLmFjdGlvbnNbIDAgXS51cmwgPSB3cGZvcm1zX2VkaXRfcG9zdF9lZHVjYXRpb24uZ3V0ZW5iZXJnX25vdGljZS51cmw7XG5cblx0XHRcdFx0cmV0dXJuIG5vdGljZVNldHRpbmdzO1xuXHRcdFx0fVxuXG5cdFx0XHRjb25zdCBHdWlkZSA9IHdwLmNvbXBvbmVudHMuR3VpZGU7XG5cdFx0XHRjb25zdCB1c2VTdGF0ZSA9IHdwLmVsZW1lbnQudXNlU3RhdGU7XG5cdFx0XHRjb25zdCByZWdpc3RlclBsdWdpbiA9IHdwLnBsdWdpbnMucmVnaXN0ZXJQbHVnaW47XG5cdFx0XHRjb25zdCB1bnJlZ2lzdGVyUGx1Z2luID0gd3AucGx1Z2lucy51bnJlZ2lzdGVyUGx1Z2luO1xuXHRcdFx0Y29uc3QgR3V0ZW5iZXJnVHV0b3JpYWwgPSBmdW5jdGlvbigpIHtcblx0XHRcdFx0Y29uc3QgWyBpc09wZW4sIHNldElzT3BlbiBdID0gdXNlU3RhdGUoIHRydWUgKTtcblxuXHRcdFx0XHRpZiAoICEgaXNPcGVuICkge1xuXHRcdFx0XHRcdHJldHVybiBudWxsO1xuXHRcdFx0XHR9XG5cblx0XHRcdFx0cmV0dXJuIChcblx0XHRcdFx0XHQvLyBlc2xpbnQtZGlzYWJsZS1uZXh0LWxpbmUgcmVhY3QvcmVhY3QtaW4tanN4LXNjb3BlXG5cdFx0XHRcdFx0PEd1aWRlXG5cdFx0XHRcdFx0XHRjbGFzc05hbWU9XCJlZGl0LXBvc3Qtd2VsY29tZS1ndWlkZVwiXG5cdFx0XHRcdFx0XHRvbkZpbmlzaD17ICgpID0+IHtcblx0XHRcdFx0XHRcdFx0dW5yZWdpc3RlclBsdWdpbiggcGx1Z2luTmFtZSApO1xuXHRcdFx0XHRcdFx0XHRzZXRJc09wZW4oIGZhbHNlICk7XG5cdFx0XHRcdFx0XHR9IH1cblx0XHRcdFx0XHRcdHBhZ2VzPXsgYXBwLmdldEd1aWRlUGFnZXMoKSB9XG5cdFx0XHRcdFx0Lz5cblx0XHRcdFx0KTtcblx0XHRcdH07XG5cblx0XHRcdG5vdGljZVNldHRpbmdzLmFjdGlvbnNbIDAgXS5vbkNsaWNrID0gKCkgPT4gcmVnaXN0ZXJQbHVnaW4oIHBsdWdpbk5hbWUsIHsgcmVuZGVyOiBHdXRlbmJlcmdUdXRvcmlhbCB9ICk7XG5cblx0XHRcdHJldHVybiBub3RpY2VTZXR0aW5ncztcblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogR2V0IEd1aWRlIHBhZ2VzIGluIHByb3BlciBmb3JtYXQuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKlxuXHRcdCAqIEByZXR1cm4ge0FycmF5fSBHdWlkZSBQYWdlcy5cblx0XHQgKi9cblx0XHRnZXRHdWlkZVBhZ2VzKCkge1xuXHRcdFx0Y29uc3QgcGFnZXMgPSBbXTtcblxuXHRcdFx0d3Bmb3Jtc19lZGl0X3Bvc3RfZWR1Y2F0aW9uLmd1dGVuYmVyZ19ndWlkZS5mb3JFYWNoKCBmdW5jdGlvbiggcGFnZSApIHtcblx0XHRcdFx0cGFnZXMucHVzaChcblx0XHRcdFx0XHR7XG5cdFx0XHRcdFx0XHQvKiBlc2xpbnQtZGlzYWJsZSByZWFjdC9yZWFjdC1pbi1qc3gtc2NvcGUgKi9cblx0XHRcdFx0XHRcdGNvbnRlbnQ6IChcblx0XHRcdFx0XHRcdFx0PD5cblx0XHRcdFx0XHRcdFx0XHQ8aDEgY2xhc3NOYW1lPVwiZWRpdC1wb3N0LXdlbGNvbWUtZ3VpZGVfX2hlYWRpbmdcIj57IHBhZ2UudGl0bGUgfTwvaDE+XG5cdFx0XHRcdFx0XHRcdFx0PHAgY2xhc3NOYW1lPVwiZWRpdC1wb3N0LXdlbGNvbWUtZ3VpZGVfX3RleHRcIj57IHBhZ2UuY29udGVudCB9PC9wPlxuXHRcdFx0XHRcdFx0XHQ8Lz5cblx0XHRcdFx0XHRcdCksXG5cdFx0XHRcdFx0XHRpbWFnZTogPGltZyBjbGFzc05hbWU9XCJlZGl0LXBvc3Qtd2VsY29tZS1ndWlkZV9faW1hZ2VcIiBzcmM9eyBwYWdlLmltYWdlIH0gYWx0PXsgcGFnZS50aXRsZSB9IC8+LFxuXHRcdFx0XHRcdFx0LyogZXNsaW50LWVuYWJsZSByZWFjdC9yZWFjdC1pbi1qc3gtc2NvcGUgKi9cblx0XHRcdFx0XHR9XG5cdFx0XHRcdCk7XG5cdFx0XHR9ICk7XG5cblx0XHRcdHJldHVybiBwYWdlcztcblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogU2hvdyBub3RpY2UgaWYgdGhlIHBhZ2UgdGl0bGUgbWF0Y2hlcyBzb21lIGtleXdvcmRzIGZvciBDbGFzc2ljIEVkaXRvci5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqL1xuXHRcdG1heWJlU2hvd0NsYXNzaWNOb3RpY2UoKSB7XG5cdFx0XHRpZiAoIGFwcC5pc05vdGljZVZpc2libGUgKSB7XG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblxuXHRcdFx0aWYgKCBhcHAuaXNUaXRsZU1hdGNoS2V5d29yZHMoICQoICcjdGl0bGUnICkudmFsKCkgKSApIHtcblx0XHRcdFx0YXBwLmlzTm90aWNlVmlzaWJsZSA9IHRydWU7XG5cblx0XHRcdFx0JCggJy53cGZvcm1zLWVkaXQtcG9zdC1lZHVjYXRpb24tbm90aWNlJyApLnJlbW92ZUNsYXNzKCAnd3Bmb3Jtcy1oaWRkZW4nICk7XG5cdFx0XHR9XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFNob3cgbm90aWNlIGlmIHRoZSBwYWdlIHRpdGxlIG1hdGNoZXMgc29tZSBrZXl3b3JkcyBmb3IgR3V0ZW5iZXJnIEVkaXRvci5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqL1xuXHRcdG1heWJlU2hvd0d1dGVuYmVyZ05vdGljZSgpIHtcblx0XHRcdGlmICggYXBwLmlzTm90aWNlVmlzaWJsZSApIHtcblx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0fVxuXG5cdFx0XHRjb25zdCAkcG9zdFRpdGxlID0gYXBwLmlzRnNlKClcblx0XHRcdFx0PyAkKCAnaWZyYW1lW25hbWU9XCJlZGl0b3ItY2FudmFzXCJdJyApLmNvbnRlbnRzKCkuZmluZCggJy5lZGl0b3ItcG9zdC10aXRsZV9faW5wdXQnIClcblx0XHRcdFx0OiAkKCAnLmVkaXRvci1wb3N0LXRpdGxlX19pbnB1dCcgKTtcblx0XHRcdGNvbnN0IHRhZ05hbWUgPSAkcG9zdFRpdGxlLnByb3AoICd0YWdOYW1lJyApO1xuXHRcdFx0Y29uc3QgdGl0bGUgPSB0YWdOYW1lID09PSAnVEVYVEFSRUEnID8gJHBvc3RUaXRsZS52YWwoKSA6ICRwb3N0VGl0bGUudGV4dCgpO1xuXG5cdFx0XHRpZiAoIGFwcC5pc1RpdGxlTWF0Y2hLZXl3b3JkcyggdGl0bGUgKSApIHtcblx0XHRcdFx0YXBwLmlzTm90aWNlVmlzaWJsZSA9IHRydWU7XG5cblx0XHRcdFx0YXBwLnNob3dHdXRlbmJlcmdOb3RpY2UoKTtcblx0XHRcdH1cblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogQWRkIG5vdGljZSBjbGFzcyB3aGVuIHRoZSBkaXN0cmFjdGlvbiBtb2RlIGlzIGVuYWJsZWQuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjEuMlxuXHRcdCAqL1xuXHRcdGRpc3RyYWN0aW9uRnJlZU1vZGVUb2dnbGUoKSB7XG5cdFx0XHRpZiAoICEgYXBwLmlzTm90aWNlVmlzaWJsZSApIHtcblx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0fVxuXG5cdFx0XHRjb25zdCAkZG9jdW1lbnQgPSAkKCBkb2N1bWVudCApO1xuXHRcdFx0Y29uc3QgaXNEaXN0cmFjdGlvbkZyZWVNb2RlID0gQm9vbGVhbiggJGRvY3VtZW50LmZpbmQoICcuaXMtZGlzdHJhY3Rpb24tZnJlZScgKS5sZW5ndGggKTtcblxuXHRcdFx0aWYgKCAhIGlzRGlzdHJhY3Rpb25GcmVlTW9kZSApIHtcblx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0fVxuXG5cdFx0XHRjb25zdCBpc05vdGljZUhhc0NsYXNzID0gQm9vbGVhbiggJCggJy53cGZvcm1zLWVkaXQtcG9zdC1lZHVjYXRpb24tbm90aWNlJyApLmxlbmd0aCApO1xuXG5cdFx0XHRpZiAoIGlzTm90aWNlSGFzQ2xhc3MgKSB7XG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblxuXHRcdFx0Y29uc3QgJG5vdGljZUJvZHkgPSAkZG9jdW1lbnQuZmluZCggJy53cGZvcm1zLWVkaXQtcG9zdC1lZHVjYXRpb24tbm90aWNlLWJvZHknICk7XG5cdFx0XHRjb25zdCAkbm90aWNlID0gJG5vdGljZUJvZHkuY2xvc2VzdCggJy5jb21wb25lbnRzLW5vdGljZScgKTtcblxuXHRcdFx0JG5vdGljZS5hZGRDbGFzcyggJ3dwZm9ybXMtZWRpdC1wb3N0LWVkdWNhdGlvbi1ub3RpY2UnICk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIERldGVybWluZSBpZiB0aGUgdGl0bGUgbWF0Y2hlcyBrZXl3b3Jkcy5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtzdHJpbmd9IHRpdGxlVmFsdWUgUGFnZSB0aXRsZSB2YWx1ZS5cblx0XHQgKlxuXHRcdCAqIEByZXR1cm4ge2Jvb2xlYW59IFRydWUgaWYgdGhlIHRpdGxlIG1hdGNoZXMgc29tZSBrZXl3b3Jkcy5cblx0XHQgKi9cblx0XHRpc1RpdGxlTWF0Y2hLZXl3b3JkcyggdGl0bGVWYWx1ZSApIHtcblx0XHRcdGNvbnN0IGV4cGVjdGVkVGl0bGVSZWdleCA9IG5ldyBSZWdFeHAoIC9cXGIoY29udGFjdHxmb3JtKVxcYi9pICk7XG5cblx0XHRcdHJldHVybiBleHBlY3RlZFRpdGxlUmVnZXgudGVzdCggdGl0bGVWYWx1ZSApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBDbG9zZSBhIG5vdGljZS5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqL1xuXHRcdGNsb3NlTm90aWNlKCkge1xuXHRcdFx0JCggdGhpcyApLmNsb3Nlc3QoICcud3Bmb3Jtcy1lZGl0LXBvc3QtZWR1Y2F0aW9uLW5vdGljZScgKS5yZW1vdmUoKTtcblxuXHRcdFx0YXBwLnVwZGF0ZVVzZXJNZXRhKCk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFVwZGF0ZSB1c2VyIG1ldGEgYW5kIGRvbid0IHNob3cgdGhlIG5vdGljZSBuZXh0IHRpbWUuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHR1cGRhdGVVc2VyTWV0YSgpIHtcblx0XHRcdCQucG9zdChcblx0XHRcdFx0d3Bmb3Jtc19lZGl0X3Bvc3RfZWR1Y2F0aW9uLmFqYXhfdXJsLFxuXHRcdFx0XHR7XG5cdFx0XHRcdFx0YWN0aW9uOiAnd3Bmb3Jtc19lZHVjYXRpb25fZGlzbWlzcycsXG5cdFx0XHRcdFx0bm9uY2U6IHdwZm9ybXNfZWRpdF9wb3N0X2VkdWNhdGlvbi5lZHVjYXRpb25fbm9uY2UsXG5cdFx0XHRcdFx0c2VjdGlvbjogJ2VkaXQtcG9zdC1ub3RpY2UnLFxuXHRcdFx0XHR9XG5cdFx0XHQpO1xuXHRcdH0sXG5cdH07XG5cblx0cmV0dXJuIGFwcDtcbn0oIGRvY3VtZW50LCB3aW5kb3csIGpRdWVyeSApICk7XG5cbldQRm9ybXNFZGl0UG9zdEVkdWNhdGlvbi5pbml0KCk7XG4iXSwibWFwcGluZ3MiOiI7Ozs7Ozs7O0FBQUE7O0FBRUE7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBOztBQUVBO0FBQ0EsSUFBSUEsd0JBQXdCLEdBQUdDLE1BQU0sQ0FBQ0Qsd0JBQXdCLElBQU0sVUFBVUUsUUFBUSxFQUFFRCxNQUFNLEVBQUVFLENBQUMsRUFBRztFQUNuRztBQUNEO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtFQUNDLElBQU1DLEdBQUcsR0FBRztJQUVYO0FBQ0Y7QUFDQTtBQUNBO0FBQ0E7SUFDRUMsZUFBZSxFQUFFLEtBQUs7SUFFdEI7QUFDRjtBQUNBO0FBQ0E7QUFDQTtJQUNFQyxJQUFJLFdBQUpBLElBQUlBLENBQUEsRUFBRztNQUNOSCxDQUFDLENBQUVGLE1BQU8sQ0FBQyxDQUFDTSxFQUFFLENBQUUsTUFBTSxFQUFFLFlBQVc7UUFDbEM7UUFDQSxJQUFLLE9BQU9KLENBQUMsQ0FBQ0ssS0FBSyxDQUFDQyxJQUFJLEtBQUssVUFBVSxFQUFHO1VBQ3pDTixDQUFDLENBQUNLLEtBQUssQ0FBQ0MsSUFBSSxDQUFFTCxHQUFHLENBQUNNLElBQUssQ0FBQztRQUN6QixDQUFDLE1BQU07VUFDTk4sR0FBRyxDQUFDTSxJQUFJLENBQUMsQ0FBQztRQUNYO01BQ0QsQ0FBRSxDQUFDO0lBQ0osQ0FBQztJQUVEO0FBQ0Y7QUFDQTtBQUNBO0FBQ0E7SUFDRUEsSUFBSSxXQUFKQSxJQUFJQSxDQUFBLEVBQUc7TUFDTixJQUFLLENBQUVOLEdBQUcsQ0FBQ08saUJBQWlC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
},{}]},{},[1])