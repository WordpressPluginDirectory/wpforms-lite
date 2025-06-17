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
  // The identifiers for the Redux stores.
  var coreEditSite = 'core/edit-site',
    coreEditor = 'core/editor',
    coreBlockEditor = 'core/block-editor',
    coreNotices = 'core/notices',
    // Heading block name.
    coreHeading = 'core/heading';

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
     * Identifier for the plugin and notice.
     *
     * @since 1.9.5
     */
    pluginId: 'wpforms-edit-post-product-education-guide',
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
     * @since 1.9.5 Added compatibility for the Site Editor.
     */
    load: function load() {
      if (!app.isGutenbergEditor()) {
        app.maybeShowClassicNotice();
        app.bindClassicEvents();
        return;
      }
      app.maybeShowGutenbergNotice();

      // "core/edit-site" store available only in the Site Editor.
      if (!!wp.data.select(coreEditSite)) {
        app.subscribeForSiteEditor();
        return;
      }
      app.subscribeForBlockEditor();
    },
    /**
     * This method listens for changes in the WordPress data store and performs the following actions:
     * - Monitors the editor title and focus mode to detect changes.
     * - Dismisses a custom notice if the focus mode is disabled and the notice is visible.
     * - Shows a custom Gutenberg notice if the title or focus mode changes.
     *
     * @since 1.9.5
     */
    subscribeForSiteEditor: function subscribeForSiteEditor() {
      // Store the initial editor title and focus mode state.
      var prevTitle = app.getEditorTitle();
      var prevFocusMode = null;
      var _wp$data = wp.data,
        subscribe = _wp$data.subscribe,
        select = _wp$data.select,
        dispatch = _wp$data.dispatch;

      // Listen for changes in the WordPress data store.
      subscribe(function () {
        // Fetch the current editor mode setting.
        // If true - Site Editor canvas is opened, and you can edit something.
        // If false - you should see the sidebar with navigation and preview
        // with selected template or page.
        var _select$getEditorSett = select(coreEditor).getEditorSettings(),
          focusMode = _select$getEditorSett.focusMode;

        // If focus mode is disabled and a notice is visible, remove the notice.
        // This is essential because user can switch pages / templates
        // without a page-reload.
        if (!focusMode && app.isNoticeVisible) {
          app.isNoticeVisible = false;
          prevFocusMode = focusMode;
          dispatch(coreNotices).removeNotice(app.pluginId);
        }
        var title = app.getEditorTitle();

        // If neither the title nor the focus mode has changed, do nothing.
        if (prevTitle === title && prevFocusMode === focusMode) {
          return;
        }

        // Update the previous title and focus mode values for the next subscription cycle.
        prevTitle = title;
        prevFocusMode = focusMode;

        // Show a custom Gutenberg notice if conditions are met.
        app.maybeShowGutenbergNotice();
      });
    },
    /**
     * Subscribes to changes in the WordPress block editor and monitors the editor's title.
     * When the title changes, it triggers a process to potentially display a Gutenberg notice.
     * The subscription is automatically stopped if the notice becomes visible.
     *
     * @since 1.9.5
     */
    subscribeForBlockEditor: function subscribeForBlockEditor() {
      var prevTitle = app.getEditorTitle();
      var subscribe = wp.data.subscribe;

      // Subscribe to WordPress data changes.
      var unsubscribe = subscribe(function () {
        var title = app.getEditorTitle();

        // Check if the title has changed since the previous value.
        if (prevTitle === title) {
          return;
        }

        // Update the previous title to the current title.
        prevTitle = title;
        app.maybeShowGutenbergNotice();

        // If the notice is visible, stop the WordPress data subscription.
        if (app.isNoticeVisible) {
          unsubscribe();
        }
      });
    },
    /**
     * Retrieves the title of the post currently being edited. If in the Site Editor,
     * it attempts to fetch the title from the topmost heading block. Otherwise, it
     * retrieves the title attribute of the edited post.
     *
     * @since 1.9.5
     *
     * @return {string} The post title or an empty string if no title is found.
     */
    getEditorTitle: function getEditorTitle() {
      var select = wp.data.select;

      // Retrieve the title for Post Editor.
      if (!select(coreEditSite)) {
        return select(coreEditor).getEditedPostAttribute('title');
      }
      if (app.isEditPostFSE()) {
        return app.getPostTitle();
      }
      return app.getTopmostHeadingTitle();
    },
    /**
     * Retrieves the content of the first heading block.
     *
     * @since 1.9.5
     *
     * @return {string} The topmost heading content or null if not found.
     */
    getTopmostHeadingTitle: function getTopmostHeadingTitle() {
      var _headingBlock$attribu, _headingBlock$attribu2;
      var select = wp.data.select;
      var headings = select(coreBlockEditor).getBlocksByName(coreHeading);
      if (!headings.length) {
        return '';
      }
      var headingBlock = select(coreBlockEditor).getBlock(headings[0]);
      return (_headingBlock$attribu = headingBlock === null || headingBlock === void 0 || (_headingBlock$attribu2 = headingBlock.attributes) === null || _headingBlock$attribu2 === void 0 || (_headingBlock$attribu2 = _headingBlock$attribu2.content) === null || _headingBlock$attribu2 === void 0 ? void 0 : _headingBlock$attribu2.text) !== null && _headingBlock$attribu !== void 0 ? _headingBlock$attribu : '';
    },
    /**
     * Determines if the current editing context is for a post type in the Full Site Editor (FSE).
     *
     * @since 1.9.5
     *
     * @return {boolean} True if the current context represents a post type in the FSE, otherwise false.
     */
    isEditPostFSE: function isEditPostFSE() {
      var select = wp.data.select;
      var _select$getPage = select(coreEditSite).getPage(),
        context = _select$getPage.context;
      return !!(context !== null && context !== void 0 && context.postType);
    },
    /**
     * Retrieves the title of a post based on its type and ID from the current editing context.
     *
     * @since 1.9.5
     *
     * @return {string} The title of the post.
     */
    getPostTitle: function getPostTitle() {
      var select = wp.data.select;
      var _select$getPage2 = select(coreEditSite).getPage(),
        context = _select$getPage2.context;

      // Use `getEditedEntityRecord` instead of `getEntityRecord`
      // to fetch the live, updated data for the post being edited.
      var _ref = select('core').getEditedEntityRecord('postType', context.postType, context.postId) || {},
        _ref$title = _ref.title,
        title = _ref$title === void 0 ? '' : _ref$title;
      return title;
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
     * Create a notice for Gutenberg.
     *
     * @since 1.8.1
     */
    showGutenbergNotice: function showGutenbergNotice() {
      wp.data.dispatch(coreNotices).createInfoNotice(wpforms_edit_post_education.gutenberg_notice.template, app.getGutenbergNoticeSettings());

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
      var noticeSettings = {
        id: app.pluginId,
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
      var Guide = wp.components.Guide,
        useState = wp.element.useState,
        _wp$plugins = wp.plugins,
        registerPlugin = _wp$plugins.registerPlugin,
        unregisterPlugin = _wp$plugins.unregisterPlugin;
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
              unregisterPlugin(app.pluginId);
              setIsOpen(false);
            },
            pages: app.getGuidePages()
          })
        );
      };
      noticeSettings.actions[0].onClick = function () {
        return registerPlugin(app.pluginId, {
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
      var title = app.getEditorTitle();
      if (app.isTitleMatchKeywords(title)) {
        app.isNoticeVisible = true;
        app.showGutenbergNotice();
      }
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
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJuYW1lcyI6WyJXUEZvcm1zRWRpdFBvc3RFZHVjYXRpb24iLCJ3aW5kb3ciLCJkb2N1bWVudCIsIiQiLCJjb3JlRWRpdFNpdGUiLCJjb3JlRWRpdG9yIiwiY29yZUJsb2NrRWRpdG9yIiwiY29yZU5vdGljZXMiLCJjb3JlSGVhZGluZyIsImFwcCIsImlzTm90aWNlVmlzaWJsZSIsInBsdWdpbklkIiwiaW5pdCIsIm9uIiwicmVhZHkiLCJ0aGVuIiwibG9hZCIsImlzR3V0ZW5iZXJnRWRpdG9yIiwibWF5YmVTaG93Q2xhc3NpY05vdGljZSIsImJpbmRDbGFzc2ljRXZlbnRzIiwibWF5YmVTaG93R3V0ZW5iZXJnTm90aWNlIiwid3AiLCJkYXRhIiwic2VsZWN0Iiwic3Vic2NyaWJlRm9yU2l0ZUVkaXRvciIsInN1YnNjcmliZUZvckJsb2NrRWRpdG9yIiwicHJldlRpdGxlIiwiZ2V0RWRpdG9yVGl0bGUiLCJwcmV2Rm9jdXNNb2RlIiwiX3dwJGRhdGEiLCJzdWJzY3JpYmUiLCJkaXNwYXRjaCIsIl9zZWxlY3QkZ2V0RWRpdG9yU2V0dCIsImdldEVkaXRvclNldHRpbmdzIiwiZm9jdXNNb2RlIiwicmVtb3ZlTm90aWNlIiwidGl0bGUiLCJ1bnN1YnNjcmliZSIsImdldEVkaXRlZFBvc3RBdHRyaWJ1dGUiLCJpc0VkaXRQb3N0RlNFIiwiZ2V0UG9zdFRpdGxlIiwiZ2V0VG9wbW9zdEhlYWRpbmdUaXRsZSIsIl9oZWFkaW5nQmxvY2skYXR0cmlidSIsIl9oZWFkaW5nQmxvY2skYXR0cmlidTIiLCJoZWFkaW5ncyIsImdldEJsb2Nrc0J5TmFtZSIsImxlbmd0aCIsImhlYWRpbmdCbG9jayIsImdldEJsb2NrIiwiYXR0cmlidXRlcyIsImNvbnRlbnQiLCJ0ZXh0IiwiX3NlbGVjdCRnZXRQYWdlIiwiZ2V0UGFnZSIsImNvbnRleHQiLCJwb3N0VHlwZSIsIl9zZWxlY3QkZ2V0UGFnZTIiLCJfcmVmIiwiZ2V0RWRpdGVkRW50aXR5UmVjb3JkIiwicG9zdElkIiwiX3JlZiR0aXRsZSIsIiRkb2N1bWVudCIsIl8iLCJkZWJvdW5jZSIsImNsb3NlTm90aWNlIiwiYmxvY2tzIiwic2hvd0d1dGVuYmVyZ05vdGljZSIsImNyZWF0ZUluZm9Ob3RpY2UiLCJ3cGZvcm1zX2VkaXRfcG9zdF9lZHVjYXRpb24iLCJndXRlbmJlcmdfbm90aWNlIiwidGVtcGxhdGUiLCJnZXRHdXRlbmJlcmdOb3RpY2VTZXR0aW5ncyIsImhhc05vdGljZSIsInNldEludGVydmFsIiwibm90aWNlQm9keSIsIiRub3RpY2UiLCJjbG9zZXN0IiwiYWRkQ2xhc3MiLCJmaW5kIiwicmVtb3ZlQ2xhc3MiLCJkaXNtaXNzQnV0dG9uIiwidXBkYXRlVXNlck1ldGEiLCJjbGVhckludGVydmFsIiwibm90aWNlU2V0dGluZ3MiLCJpZCIsImlzRGlzbWlzc2libGUiLCJIVE1MIiwiX191bnN0YWJsZUhUTUwiLCJhY3Rpb25zIiwiY2xhc3NOYW1lIiwidmFyaWFudCIsImxhYmVsIiwiYnV0dG9uIiwiZ3V0ZW5iZXJnX2d1aWRlIiwidXJsIiwiR3VpZGUiLCJjb21wb25lbnRzIiwidXNlU3RhdGUiLCJlbGVtZW50IiwiX3dwJHBsdWdpbnMiLCJwbHVnaW5zIiwicmVnaXN0ZXJQbHVnaW4iLCJ1bnJlZ2lzdGVyUGx1Z2luIiwiR3V0ZW5iZXJnVHV0b3JpYWwiLCJfdXNlU3RhdGUiLCJfdXNlU3RhdGUyIiwiX3NsaWNlZFRvQXJyYXkiLCJpc09wZW4iLCJzZXRJc09wZW4iLCJSZWFjdCIsImNyZWF0ZUVsZW1lbnQiLCJvbkZpbmlzaCIsInBhZ2VzIiwiZ2V0R3VpZGVQYWdlcyIsIm9uQ2xpY2siLCJyZW5kZXIiLCJmb3JFYWNoIiwicGFnZSIsInB1c2giLCJGcmFnbWVudCIsImltYWdlIiwic3JjIiwiYWx0IiwiaXNUaXRsZU1hdGNoS2V5d29yZHMiLCJ2YWwiLCJ0aXRsZVZhbHVlIiwiZXhwZWN0ZWRUaXRsZVJlZ2V4IiwiUmVnRXhwIiwidGVzdCIsInJlbW92ZSIsInBvc3QiLCJhamF4X3VybCIsImFjdGlvbiIsIm5vbmNlIiwiZWR1Y2F0aW9uX25vbmNlIiwic2VjdGlvbiIsImpRdWVyeSJdLCJzb3VyY2VzIjpbImZha2VfZjdiZmM1LmpzIl0sInNvdXJjZXNDb250ZW50IjpbIi8qIGdsb2JhbCB3cGZvcm1zX2VkaXRfcG9zdF9lZHVjYXRpb24gKi9cblxuLy8gbm9pbnNwZWN0aW9uIEVTNkNvbnZlcnRWYXJUb0xldENvbnN0XG4vKipcbiAqIFdQRm9ybXMgRWRpdCBQb3N0IEVkdWNhdGlvbiBmdW5jdGlvbi5cbiAqXG4gKiBAc2luY2UgMS44LjFcbiAqL1xuXG4vLyBlc2xpbnQtZGlzYWJsZS1uZXh0LWxpbmUgbm8tdmFyLCBuby11bnVzZWQtdmFyc1xudmFyIFdQRm9ybXNFZGl0UG9zdEVkdWNhdGlvbiA9IHdpbmRvdy5XUEZvcm1zRWRpdFBvc3RFZHVjYXRpb24gfHwgKCBmdW5jdGlvbiggZG9jdW1lbnQsIHdpbmRvdywgJCApIHtcblx0Ly8gVGhlIGlkZW50aWZpZXJzIGZvciB0aGUgUmVkdXggc3RvcmVzLlxuXHRjb25zdCBjb3JlRWRpdFNpdGUgPSAnY29yZS9lZGl0LXNpdGUnLFxuXHRcdGNvcmVFZGl0b3IgPSAnY29yZS9lZGl0b3InLFxuXHRcdGNvcmVCbG9ja0VkaXRvciA9ICdjb3JlL2Jsb2NrLWVkaXRvcicsXG5cdFx0Y29yZU5vdGljZXMgPSAnY29yZS9ub3RpY2VzJyxcblxuXHRcdC8vIEhlYWRpbmcgYmxvY2sgbmFtZS5cblx0XHRjb3JlSGVhZGluZyA9ICdjb3JlL2hlYWRpbmcnO1xuXG5cdC8qKlxuXHQgKiBQdWJsaWMgZnVuY3Rpb25zIGFuZCBwcm9wZXJ0aWVzLlxuXHQgKlxuXHQgKiBAc2luY2UgMS44LjFcblx0ICpcblx0ICogQHR5cGUge09iamVjdH1cblx0ICovXG5cdGNvbnN0IGFwcCA9IHtcblxuXHRcdC8qKlxuXHRcdCAqIERldGVybWluZSBpZiB0aGUgbm90aWNlIHdhcyBzaG93biBiZWZvcmUuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHRpc05vdGljZVZpc2libGU6IGZhbHNlLFxuXG5cdFx0LyoqXG5cdFx0ICogSWRlbnRpZmllciBmb3IgdGhlIHBsdWdpbiBhbmQgbm90aWNlLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOS41XG5cdFx0ICovXG5cdFx0cGx1Z2luSWQ6ICd3cGZvcm1zLWVkaXQtcG9zdC1wcm9kdWN0LWVkdWNhdGlvbi1ndWlkZScsXG5cblx0XHQvKipcblx0XHQgKiBTdGFydCB0aGUgZW5naW5lLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOC4xXG5cdFx0ICovXG5cdFx0aW5pdCgpIHtcblx0XHRcdCQoIHdpbmRvdyApLm9uKCAnbG9hZCcsIGZ1bmN0aW9uKCkge1xuXHRcdFx0XHQvLyBJbiB0aGUgY2FzZSBvZiBqUXVlcnkgMy4rLCB3ZSBuZWVkIHRvIHdhaXQgZm9yIGEgcmVhZHkgZXZlbnQgZmlyc3QuXG5cdFx0XHRcdGlmICggdHlwZW9mICQucmVhZHkudGhlbiA9PT0gJ2Z1bmN0aW9uJyApIHtcblx0XHRcdFx0XHQkLnJlYWR5LnRoZW4oIGFwcC5sb2FkICk7XG5cdFx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdFx0YXBwLmxvYWQoKTtcblx0XHRcdFx0fVxuXHRcdFx0fSApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBQYWdlIGxvYWQuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKiBAc2luY2UgMS45LjUgQWRkZWQgY29tcGF0aWJpbGl0eSBmb3IgdGhlIFNpdGUgRWRpdG9yLlxuXHRcdCAqL1xuXHRcdGxvYWQoKSB7XG5cdFx0XHRpZiAoICEgYXBwLmlzR3V0ZW5iZXJnRWRpdG9yKCkgKSB7XG5cdFx0XHRcdGFwcC5tYXliZVNob3dDbGFzc2ljTm90aWNlKCk7XG5cdFx0XHRcdGFwcC5iaW5kQ2xhc3NpY0V2ZW50cygpO1xuXG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblxuXHRcdFx0YXBwLm1heWJlU2hvd0d1dGVuYmVyZ05vdGljZSgpO1xuXG5cdFx0XHQvLyBcImNvcmUvZWRpdC1zaXRlXCIgc3RvcmUgYXZhaWxhYmxlIG9ubHkgaW4gdGhlIFNpdGUgRWRpdG9yLlxuXHRcdFx0aWYgKCAhISB3cC5kYXRhLnNlbGVjdCggY29yZUVkaXRTaXRlICkgKSB7XG5cdFx0XHRcdGFwcC5zdWJzY3JpYmVGb3JTaXRlRWRpdG9yKCk7XG5cblx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0fVxuXG5cdFx0XHRhcHAuc3Vic2NyaWJlRm9yQmxvY2tFZGl0b3IoKTtcblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogVGhpcyBtZXRob2QgbGlzdGVucyBmb3IgY2hhbmdlcyBpbiB0aGUgV29yZFByZXNzIGRhdGEgc3RvcmUgYW5kIHBlcmZvcm1zIHRoZSBmb2xsb3dpbmcgYWN0aW9uczpcblx0XHQgKiAtIE1vbml0b3JzIHRoZSBlZGl0b3IgdGl0bGUgYW5kIGZvY3VzIG1vZGUgdG8gZGV0ZWN0IGNoYW5nZXMuXG5cdFx0ICogLSBEaXNtaXNzZXMgYSBjdXN0b20gbm90aWNlIGlmIHRoZSBmb2N1cyBtb2RlIGlzIGRpc2FibGVkIGFuZCB0aGUgbm90aWNlIGlzIHZpc2libGUuXG5cdFx0ICogLSBTaG93cyBhIGN1c3RvbSBHdXRlbmJlcmcgbm90aWNlIGlmIHRoZSB0aXRsZSBvciBmb2N1cyBtb2RlIGNoYW5nZXMuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS45LjVcblx0XHQgKi9cblx0XHRzdWJzY3JpYmVGb3JTaXRlRWRpdG9yKCkge1xuXHRcdFx0Ly8gU3RvcmUgdGhlIGluaXRpYWwgZWRpdG9yIHRpdGxlIGFuZCBmb2N1cyBtb2RlIHN0YXRlLlxuXHRcdFx0bGV0IHByZXZUaXRsZSA9IGFwcC5nZXRFZGl0b3JUaXRsZSgpO1xuXHRcdFx0bGV0IHByZXZGb2N1c01vZGUgPSBudWxsO1xuXHRcdFx0Y29uc3QgeyBzdWJzY3JpYmUsIHNlbGVjdCwgZGlzcGF0Y2ggfSA9IHdwLmRhdGE7XG5cblx0XHRcdC8vIExpc3RlbiBmb3IgY2hhbmdlcyBpbiB0aGUgV29yZFByZXNzIGRhdGEgc3RvcmUuXG5cdFx0XHRzdWJzY3JpYmUoICgpID0+IHtcblx0XHRcdFx0Ly8gRmV0Y2ggdGhlIGN1cnJlbnQgZWRpdG9yIG1vZGUgc2V0dGluZy5cblx0XHRcdFx0Ly8gSWYgdHJ1ZSAtIFNpdGUgRWRpdG9yIGNhbnZhcyBpcyBvcGVuZWQsIGFuZCB5b3UgY2FuIGVkaXQgc29tZXRoaW5nLlxuXHRcdFx0XHQvLyBJZiBmYWxzZSAtIHlvdSBzaG91bGQgc2VlIHRoZSBzaWRlYmFyIHdpdGggbmF2aWdhdGlvbiBhbmQgcHJldmlld1xuXHRcdFx0XHQvLyB3aXRoIHNlbGVjdGVkIHRlbXBsYXRlIG9yIHBhZ2UuXG5cdFx0XHRcdGNvbnN0IHsgZm9jdXNNb2RlIH0gPSBzZWxlY3QoIGNvcmVFZGl0b3IgKS5nZXRFZGl0b3JTZXR0aW5ncygpO1xuXG5cdFx0XHRcdC8vIElmIGZvY3VzIG1vZGUgaXMgZGlzYWJsZWQgYW5kIGEgbm90aWNlIGlzIHZpc2libGUsIHJlbW92ZSB0aGUgbm90aWNlLlxuXHRcdFx0XHQvLyBUaGlzIGlzIGVzc2VudGlhbCBiZWNhdXNlIHVzZXIgY2FuIHN3aXRjaCBwYWdlcyAvIHRlbXBsYXRlc1xuXHRcdFx0XHQvLyB3aXRob3V0IGEgcGFnZS1yZWxvYWQuXG5cdFx0XHRcdGlmICggISBmb2N1c01vZGUgJiYgYXBwLmlzTm90aWNlVmlzaWJsZSApIHtcblx0XHRcdFx0XHRhcHAuaXNOb3RpY2VWaXNpYmxlID0gZmFsc2U7XG5cdFx0XHRcdFx0cHJldkZvY3VzTW9kZSA9IGZvY3VzTW9kZTtcblxuXHRcdFx0XHRcdGRpc3BhdGNoKCBjb3JlTm90aWNlcyApLnJlbW92ZU5vdGljZSggYXBwLnBsdWdpbklkICk7XG5cdFx0XHRcdH1cblxuXHRcdFx0XHRjb25zdCB0aXRsZSA9IGFwcC5nZXRFZGl0b3JUaXRsZSgpO1xuXG5cdFx0XHRcdC8vIElmIG5laXRoZXIgdGhlIHRpdGxlIG5vciB0aGUgZm9jdXMgbW9kZSBoYXMgY2hhbmdlZCwgZG8gbm90aGluZy5cblx0XHRcdFx0aWYgKCBwcmV2VGl0bGUgPT09IHRpdGxlICYmIHByZXZGb2N1c01vZGUgPT09IGZvY3VzTW9kZSApIHtcblx0XHRcdFx0XHRyZXR1cm47XG5cdFx0XHRcdH1cblxuXHRcdFx0XHQvLyBVcGRhdGUgdGhlIHByZXZpb3VzIHRpdGxlIGFuZCBmb2N1cyBtb2RlIHZhbHVlcyBmb3IgdGhlIG5leHQgc3Vic2NyaXB0aW9uIGN5Y2xlLlxuXHRcdFx0XHRwcmV2VGl0bGUgPSB0aXRsZTtcblx0XHRcdFx0cHJldkZvY3VzTW9kZSA9IGZvY3VzTW9kZTtcblxuXHRcdFx0XHQvLyBTaG93IGEgY3VzdG9tIEd1dGVuYmVyZyBub3RpY2UgaWYgY29uZGl0aW9ucyBhcmUgbWV0LlxuXHRcdFx0XHRhcHAubWF5YmVTaG93R3V0ZW5iZXJnTm90aWNlKCk7XG5cdFx0XHR9ICk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFN1YnNjcmliZXMgdG8gY2hhbmdlcyBpbiB0aGUgV29yZFByZXNzIGJsb2NrIGVkaXRvciBhbmQgbW9uaXRvcnMgdGhlIGVkaXRvcidzIHRpdGxlLlxuXHRcdCAqIFdoZW4gdGhlIHRpdGxlIGNoYW5nZXMsIGl0IHRyaWdnZXJzIGEgcHJvY2VzcyB0byBwb3RlbnRpYWxseSBkaXNwbGF5IGEgR3V0ZW5iZXJnIG5vdGljZS5cblx0XHQgKiBUaGUgc3Vic2NyaXB0aW9uIGlzIGF1dG9tYXRpY2FsbHkgc3RvcHBlZCBpZiB0aGUgbm90aWNlIGJlY29tZXMgdmlzaWJsZS5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjkuNVxuXHRcdCAqL1xuXHRcdHN1YnNjcmliZUZvckJsb2NrRWRpdG9yKCkge1xuXHRcdFx0bGV0IHByZXZUaXRsZSA9IGFwcC5nZXRFZGl0b3JUaXRsZSgpO1xuXHRcdFx0Y29uc3QgeyBzdWJzY3JpYmUgfSA9IHdwLmRhdGE7XG5cblx0XHRcdC8vIFN1YnNjcmliZSB0byBXb3JkUHJlc3MgZGF0YSBjaGFuZ2VzLlxuXHRcdFx0Y29uc3QgdW5zdWJzY3JpYmUgPSBzdWJzY3JpYmUoICgpID0+IHtcblx0XHRcdFx0Y29uc3QgdGl0bGUgPSBhcHAuZ2V0RWRpdG9yVGl0bGUoKTtcblxuXHRcdFx0XHQvLyBDaGVjayBpZiB0aGUgdGl0bGUgaGFzIGNoYW5nZWQgc2luY2UgdGhlIHByZXZpb3VzIHZhbHVlLlxuXHRcdFx0XHRpZiAoIHByZXZUaXRsZSA9PT0gdGl0bGUgKSB7XG5cdFx0XHRcdFx0cmV0dXJuO1xuXHRcdFx0XHR9XG5cblx0XHRcdFx0Ly8gVXBkYXRlIHRoZSBwcmV2aW91cyB0aXRsZSB0byB0aGUgY3VycmVudCB0aXRsZS5cblx0XHRcdFx0cHJldlRpdGxlID0gdGl0bGU7XG5cblx0XHRcdFx0YXBwLm1heWJlU2hvd0d1dGVuYmVyZ05vdGljZSgpO1xuXG5cdFx0XHRcdC8vIElmIHRoZSBub3RpY2UgaXMgdmlzaWJsZSwgc3RvcCB0aGUgV29yZFByZXNzIGRhdGEgc3Vic2NyaXB0aW9uLlxuXHRcdFx0XHRpZiAoIGFwcC5pc05vdGljZVZpc2libGUgKSB7XG5cdFx0XHRcdFx0dW5zdWJzY3JpYmUoKTtcblx0XHRcdFx0fVxuXHRcdFx0fSApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBSZXRyaWV2ZXMgdGhlIHRpdGxlIG9mIHRoZSBwb3N0IGN1cnJlbnRseSBiZWluZyBlZGl0ZWQuIElmIGluIHRoZSBTaXRlIEVkaXRvcixcblx0XHQgKiBpdCBhdHRlbXB0cyB0byBmZXRjaCB0aGUgdGl0bGUgZnJvbSB0aGUgdG9wbW9zdCBoZWFkaW5nIGJsb2NrLiBPdGhlcndpc2UsIGl0XG5cdFx0ICogcmV0cmlldmVzIHRoZSB0aXRsZSBhdHRyaWJ1dGUgb2YgdGhlIGVkaXRlZCBwb3N0LlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOS41XG5cdFx0ICpcblx0XHQgKiBAcmV0dXJuIHtzdHJpbmd9IFRoZSBwb3N0IHRpdGxlIG9yIGFuIGVtcHR5IHN0cmluZyBpZiBubyB0aXRsZSBpcyBmb3VuZC5cblx0XHQgKi9cblx0XHRnZXRFZGl0b3JUaXRsZSgpIHtcblx0XHRcdGNvbnN0IHsgc2VsZWN0IH0gPSB3cC5kYXRhO1xuXG5cdFx0XHQvLyBSZXRyaWV2ZSB0aGUgdGl0bGUgZm9yIFBvc3QgRWRpdG9yLlxuXHRcdFx0aWYgKCAhIHNlbGVjdCggY29yZUVkaXRTaXRlICkgKSB7XG5cdFx0XHRcdHJldHVybiBzZWxlY3QoIGNvcmVFZGl0b3IgKS5nZXRFZGl0ZWRQb3N0QXR0cmlidXRlKCAndGl0bGUnICk7XG5cdFx0XHR9XG5cblx0XHRcdGlmICggYXBwLmlzRWRpdFBvc3RGU0UoKSApIHtcblx0XHRcdFx0cmV0dXJuIGFwcC5nZXRQb3N0VGl0bGUoKTtcblx0XHRcdH1cblxuXHRcdFx0cmV0dXJuIGFwcC5nZXRUb3Btb3N0SGVhZGluZ1RpdGxlKCk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFJldHJpZXZlcyB0aGUgY29udGVudCBvZiB0aGUgZmlyc3QgaGVhZGluZyBibG9jay5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjkuNVxuXHRcdCAqXG5cdFx0ICogQHJldHVybiB7c3RyaW5nfSBUaGUgdG9wbW9zdCBoZWFkaW5nIGNvbnRlbnQgb3IgbnVsbCBpZiBub3QgZm91bmQuXG5cdFx0ICovXG5cdFx0Z2V0VG9wbW9zdEhlYWRpbmdUaXRsZSgpIHtcblx0XHRcdGNvbnN0IHsgc2VsZWN0IH0gPSB3cC5kYXRhO1xuXG5cdFx0XHRjb25zdCBoZWFkaW5ncyA9IHNlbGVjdCggY29yZUJsb2NrRWRpdG9yICkuZ2V0QmxvY2tzQnlOYW1lKCBjb3JlSGVhZGluZyApO1xuXG5cdFx0XHRpZiAoICEgaGVhZGluZ3MubGVuZ3RoICkge1xuXHRcdFx0XHRyZXR1cm4gJyc7XG5cdFx0XHR9XG5cblx0XHRcdGNvbnN0IGhlYWRpbmdCbG9jayA9IHNlbGVjdCggY29yZUJsb2NrRWRpdG9yICkuZ2V0QmxvY2soIGhlYWRpbmdzWyAwIF0gKTtcblxuXHRcdFx0cmV0dXJuIGhlYWRpbmdCbG9jaz8uYXR0cmlidXRlcz8uY29udGVudD8udGV4dCA/PyAnJztcblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogRGV0ZXJtaW5lcyBpZiB0aGUgY3VycmVudCBlZGl0aW5nIGNvbnRleHQgaXMgZm9yIGEgcG9zdCB0eXBlIGluIHRoZSBGdWxsIFNpdGUgRWRpdG9yIChGU0UpLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOS41XG5cdFx0ICpcblx0XHQgKiBAcmV0dXJuIHtib29sZWFufSBUcnVlIGlmIHRoZSBjdXJyZW50IGNvbnRleHQgcmVwcmVzZW50cyBhIHBvc3QgdHlwZSBpbiB0aGUgRlNFLCBvdGhlcndpc2UgZmFsc2UuXG5cdFx0ICovXG5cdFx0aXNFZGl0UG9zdEZTRSgpIHtcblx0XHRcdGNvbnN0IHsgc2VsZWN0IH0gPSB3cC5kYXRhO1xuXHRcdFx0Y29uc3QgeyBjb250ZXh0IH0gPSBzZWxlY3QoIGNvcmVFZGl0U2l0ZSApLmdldFBhZ2UoKTtcblxuXHRcdFx0cmV0dXJuICEhIGNvbnRleHQ/LnBvc3RUeXBlO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBSZXRyaWV2ZXMgdGhlIHRpdGxlIG9mIGEgcG9zdCBiYXNlZCBvbiBpdHMgdHlwZSBhbmQgSUQgZnJvbSB0aGUgY3VycmVudCBlZGl0aW5nIGNvbnRleHQuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS45LjVcblx0XHQgKlxuXHRcdCAqIEByZXR1cm4ge3N0cmluZ30gVGhlIHRpdGxlIG9mIHRoZSBwb3N0LlxuXHRcdCAqL1xuXHRcdGdldFBvc3RUaXRsZSgpIHtcblx0XHRcdGNvbnN0IHsgc2VsZWN0IH0gPSB3cC5kYXRhO1xuXHRcdFx0Y29uc3QgeyBjb250ZXh0IH0gPSBzZWxlY3QoIGNvcmVFZGl0U2l0ZSApLmdldFBhZ2UoKTtcblxuXHRcdFx0Ly8gVXNlIGBnZXRFZGl0ZWRFbnRpdHlSZWNvcmRgIGluc3RlYWQgb2YgYGdldEVudGl0eVJlY29yZGBcblx0XHRcdC8vIHRvIGZldGNoIHRoZSBsaXZlLCB1cGRhdGVkIGRhdGEgZm9yIHRoZSBwb3N0IGJlaW5nIGVkaXRlZC5cblx0XHRcdGNvbnN0IHsgdGl0bGUgPSAnJyB9ID0gc2VsZWN0KCAnY29yZScgKS5nZXRFZGl0ZWRFbnRpdHlSZWNvcmQoXG5cdFx0XHRcdCdwb3N0VHlwZScsXG5cdFx0XHRcdGNvbnRleHQucG9zdFR5cGUsXG5cdFx0XHRcdGNvbnRleHQucG9zdElkXG5cdFx0XHQpIHx8IHt9O1xuXG5cdFx0XHRyZXR1cm4gdGl0bGU7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIEJpbmQgZXZlbnRzIGZvciBDbGFzc2ljIEVkaXRvci5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqL1xuXHRcdGJpbmRDbGFzc2ljRXZlbnRzKCkge1xuXHRcdFx0Y29uc3QgJGRvY3VtZW50ID0gJCggZG9jdW1lbnQgKTtcblxuXHRcdFx0aWYgKCAhIGFwcC5pc05vdGljZVZpc2libGUgKSB7XG5cdFx0XHRcdCRkb2N1bWVudC5vbiggJ2lucHV0JywgJyN0aXRsZScsIF8uZGVib3VuY2UoIGFwcC5tYXliZVNob3dDbGFzc2ljTm90aWNlLCAxMDAwICkgKTtcblx0XHRcdH1cblxuXHRcdFx0JGRvY3VtZW50Lm9uKCAnY2xpY2snLCAnLndwZm9ybXMtZWRpdC1wb3N0LWVkdWNhdGlvbi1ub3RpY2UtY2xvc2UnLCBhcHAuY2xvc2VOb3RpY2UgKTtcblx0XHR9LFxuXG5cdFx0LyoqXG5cdFx0ICogRGV0ZXJtaW5lIGlmIHRoZSBlZGl0b3IgaXMgR3V0ZW5iZXJnLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOC4xXG5cdFx0ICpcblx0XHQgKiBAcmV0dXJuIHtib29sZWFufSBUcnVlIGlmIHRoZSBlZGl0b3IgaXMgR3V0ZW5iZXJnLlxuXHRcdCAqL1xuXHRcdGlzR3V0ZW5iZXJnRWRpdG9yKCkge1xuXHRcdFx0cmV0dXJuIHR5cGVvZiB3cCAhPT0gJ3VuZGVmaW5lZCcgJiYgdHlwZW9mIHdwLmJsb2NrcyAhPT0gJ3VuZGVmaW5lZCc7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIENyZWF0ZSBhIG5vdGljZSBmb3IgR3V0ZW5iZXJnLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOC4xXG5cdFx0ICovXG5cdFx0c2hvd0d1dGVuYmVyZ05vdGljZSgpIHtcblx0XHRcdHdwLmRhdGEuZGlzcGF0Y2goIGNvcmVOb3RpY2VzICkuY3JlYXRlSW5mb05vdGljZShcblx0XHRcdFx0d3Bmb3Jtc19lZGl0X3Bvc3RfZWR1Y2F0aW9uLmd1dGVuYmVyZ19ub3RpY2UudGVtcGxhdGUsXG5cdFx0XHRcdGFwcC5nZXRHdXRlbmJlcmdOb3RpY2VTZXR0aW5ncygpXG5cdFx0XHQpO1xuXG5cdFx0XHQvLyBUaGUgbm90aWNlIGNvbXBvbmVudCBkb2Vzbid0IGhhdmUgYSB3YXkgdG8gYWRkIEhUTUwgaWQgb3IgY2xhc3MgdG8gdGhlIG5vdGljZS5cblx0XHRcdC8vIEFsc28sIHRoZSBub3RpY2UgYmVjYW1lIHZpc2libGUgd2l0aCBhIGRlbGF5IG9uIG9sZCBHdXRlbmJlcmcgdmVyc2lvbnMuXG5cdFx0XHRjb25zdCBoYXNOb3RpY2UgPSBzZXRJbnRlcnZhbCggZnVuY3Rpb24oKSB7XG5cdFx0XHRcdGNvbnN0IG5vdGljZUJvZHkgPSAkKCAnLndwZm9ybXMtZWRpdC1wb3N0LWVkdWNhdGlvbi1ub3RpY2UtYm9keScgKTtcblx0XHRcdFx0aWYgKCAhIG5vdGljZUJvZHkubGVuZ3RoICkge1xuXHRcdFx0XHRcdHJldHVybjtcblx0XHRcdFx0fVxuXG5cdFx0XHRcdGNvbnN0ICRub3RpY2UgPSBub3RpY2VCb2R5LmNsb3Nlc3QoICcuY29tcG9uZW50cy1ub3RpY2UnICk7XG5cdFx0XHRcdCRub3RpY2UuYWRkQ2xhc3MoICd3cGZvcm1zLWVkaXQtcG9zdC1lZHVjYXRpb24tbm90aWNlJyApO1xuXHRcdFx0XHQkbm90aWNlLmZpbmQoICcuaXMtc2Vjb25kYXJ5LCAuaXMtbGluaycgKS5yZW1vdmVDbGFzcyggJ2lzLXNlY29uZGFyeScgKS5yZW1vdmVDbGFzcyggJ2lzLWxpbmsnICkuYWRkQ2xhc3MoICdpcy1wcmltYXJ5JyApO1xuXG5cdFx0XHRcdC8vIFdlIGNhbid0IHVzZSBvbkRpc21pc3MgY2FsbGJhY2sgYXMgaXQgd2FzIGludHJvZHVjZWQgaW4gV29yZFByZXNzIDYuMCBvbmx5LlxuXHRcdFx0XHRjb25zdCBkaXNtaXNzQnV0dG9uID0gJG5vdGljZS5maW5kKCAnLmNvbXBvbmVudHMtbm90aWNlX19kaXNtaXNzJyApO1xuXHRcdFx0XHRpZiAoIGRpc21pc3NCdXR0b24gKSB7XG5cdFx0XHRcdFx0ZGlzbWlzc0J1dHRvbi5vbiggJ2NsaWNrJywgZnVuY3Rpb24oKSB7XG5cdFx0XHRcdFx0XHRhcHAudXBkYXRlVXNlck1ldGEoKTtcblx0XHRcdFx0XHR9ICk7XG5cdFx0XHRcdH1cblxuXHRcdFx0XHRjbGVhckludGVydmFsKCBoYXNOb3RpY2UgKTtcblx0XHRcdH0sIDEwMCApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBHZXQgc2V0dGluZ3MgZm9yIHRoZSBHdXRlbmJlcmcgbm90aWNlLlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOC4xXG5cdFx0ICpcblx0XHQgKiBAcmV0dXJuIHtPYmplY3R9IE5vdGljZSBzZXR0aW5ncy5cblx0XHQgKi9cblx0XHRnZXRHdXRlbmJlcmdOb3RpY2VTZXR0aW5ncygpIHtcblx0XHRcdGNvbnN0IG5vdGljZVNldHRpbmdzID0ge1xuXHRcdFx0XHRpZDogYXBwLnBsdWdpbklkLFxuXHRcdFx0XHRpc0Rpc21pc3NpYmxlOiB0cnVlLFxuXHRcdFx0XHRIVE1MOiB0cnVlLFxuXHRcdFx0XHRfX3Vuc3RhYmxlSFRNTDogdHJ1ZSxcblx0XHRcdFx0YWN0aW9uczogW1xuXHRcdFx0XHRcdHtcblx0XHRcdFx0XHRcdGNsYXNzTmFtZTogJ3dwZm9ybXMtZWRpdC1wb3N0LWVkdWNhdGlvbi1ub3RpY2UtZ3VpZGUtYnV0dG9uJyxcblx0XHRcdFx0XHRcdHZhcmlhbnQ6ICdwcmltYXJ5Jyxcblx0XHRcdFx0XHRcdGxhYmVsOiB3cGZvcm1zX2VkaXRfcG9zdF9lZHVjYXRpb24uZ3V0ZW5iZXJnX25vdGljZS5idXR0b24sXG5cdFx0XHRcdFx0fSxcblx0XHRcdFx0XSxcblx0XHRcdH07XG5cblx0XHRcdGlmICggISB3cGZvcm1zX2VkaXRfcG9zdF9lZHVjYXRpb24uZ3V0ZW5iZXJnX2d1aWRlICkge1xuXHRcdFx0XHRub3RpY2VTZXR0aW5ncy5hY3Rpb25zWyAwIF0udXJsID0gd3Bmb3Jtc19lZGl0X3Bvc3RfZWR1Y2F0aW9uLmd1dGVuYmVyZ19ub3RpY2UudXJsO1xuXG5cdFx0XHRcdHJldHVybiBub3RpY2VTZXR0aW5ncztcblx0XHRcdH1cblxuXHRcdFx0Y29uc3QgeyBHdWlkZSB9ID0gd3AuY29tcG9uZW50cyxcblx0XHRcdFx0eyB1c2VTdGF0ZSB9ID0gd3AuZWxlbWVudCxcblx0XHRcdFx0eyByZWdpc3RlclBsdWdpbiwgdW5yZWdpc3RlclBsdWdpbiB9ID0gd3AucGx1Z2lucztcblxuXHRcdFx0Y29uc3QgR3V0ZW5iZXJnVHV0b3JpYWwgPSBmdW5jdGlvbigpIHtcblx0XHRcdFx0Y29uc3QgWyBpc09wZW4sIHNldElzT3BlbiBdID0gdXNlU3RhdGUoIHRydWUgKTtcblxuXHRcdFx0XHRpZiAoICEgaXNPcGVuICkge1xuXHRcdFx0XHRcdHJldHVybiBudWxsO1xuXHRcdFx0XHR9XG5cblx0XHRcdFx0cmV0dXJuIChcblx0XHRcdFx0XHQvLyBlc2xpbnQtZGlzYWJsZS1uZXh0LWxpbmUgcmVhY3QvcmVhY3QtaW4tanN4LXNjb3BlXG5cdFx0XHRcdFx0PEd1aWRlXG5cdFx0XHRcdFx0XHRjbGFzc05hbWU9XCJlZGl0LXBvc3Qtd2VsY29tZS1ndWlkZVwiXG5cdFx0XHRcdFx0XHRvbkZpbmlzaD17ICgpID0+IHtcblx0XHRcdFx0XHRcdFx0dW5yZWdpc3RlclBsdWdpbiggYXBwLnBsdWdpbklkICk7XG5cdFx0XHRcdFx0XHRcdHNldElzT3BlbiggZmFsc2UgKTtcblx0XHRcdFx0XHRcdH0gfVxuXHRcdFx0XHRcdFx0cGFnZXM9eyBhcHAuZ2V0R3VpZGVQYWdlcygpIH1cblx0XHRcdFx0XHQvPlxuXHRcdFx0XHQpO1xuXHRcdFx0fTtcblxuXHRcdFx0bm90aWNlU2V0dGluZ3MuYWN0aW9uc1sgMCBdLm9uQ2xpY2sgPSAoKSA9PiByZWdpc3RlclBsdWdpbiggYXBwLnBsdWdpbklkLCB7IHJlbmRlcjogR3V0ZW5iZXJnVHV0b3JpYWwgfSApO1xuXG5cdFx0XHRyZXR1cm4gbm90aWNlU2V0dGluZ3M7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIEdldCBHdWlkZSBwYWdlcyBpbiBwcm9wZXIgZm9ybWF0LlxuXHRcdCAqXG5cdFx0ICogQHNpbmNlIDEuOC4xXG5cdFx0ICpcblx0XHQgKiBAcmV0dXJuIHtBcnJheX0gR3VpZGUgUGFnZXMuXG5cdFx0ICovXG5cdFx0Z2V0R3VpZGVQYWdlcygpIHtcblx0XHRcdGNvbnN0IHBhZ2VzID0gW107XG5cblx0XHRcdHdwZm9ybXNfZWRpdF9wb3N0X2VkdWNhdGlvbi5ndXRlbmJlcmdfZ3VpZGUuZm9yRWFjaCggZnVuY3Rpb24oIHBhZ2UgKSB7XG5cdFx0XHRcdHBhZ2VzLnB1c2goXG5cdFx0XHRcdFx0e1xuXHRcdFx0XHRcdFx0LyogZXNsaW50LWRpc2FibGUgcmVhY3QvcmVhY3QtaW4tanN4LXNjb3BlICovXG5cdFx0XHRcdFx0XHRjb250ZW50OiAoXG5cdFx0XHRcdFx0XHRcdDw+XG5cdFx0XHRcdFx0XHRcdFx0PGgxIGNsYXNzTmFtZT1cImVkaXQtcG9zdC13ZWxjb21lLWd1aWRlX19oZWFkaW5nXCI+eyBwYWdlLnRpdGxlIH08L2gxPlxuXHRcdFx0XHRcdFx0XHRcdDxwIGNsYXNzTmFtZT1cImVkaXQtcG9zdC13ZWxjb21lLWd1aWRlX190ZXh0XCI+eyBwYWdlLmNvbnRlbnQgfTwvcD5cblx0XHRcdFx0XHRcdFx0PC8+XG5cdFx0XHRcdFx0XHQpLFxuXHRcdFx0XHRcdFx0aW1hZ2U6IDxpbWcgY2xhc3NOYW1lPVwiZWRpdC1wb3N0LXdlbGNvbWUtZ3VpZGVfX2ltYWdlXCIgc3JjPXsgcGFnZS5pbWFnZSB9IGFsdD17IHBhZ2UudGl0bGUgfSAvPixcblx0XHRcdFx0XHRcdC8qIGVzbGludC1lbmFibGUgcmVhY3QvcmVhY3QtaW4tanN4LXNjb3BlICovXG5cdFx0XHRcdFx0fVxuXHRcdFx0XHQpO1xuXHRcdFx0fSApO1xuXG5cdFx0XHRyZXR1cm4gcGFnZXM7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFNob3cgbm90aWNlIGlmIHRoZSBwYWdlIHRpdGxlIG1hdGNoZXMgc29tZSBrZXl3b3JkcyBmb3IgQ2xhc3NpYyBFZGl0b3IuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHRtYXliZVNob3dDbGFzc2ljTm90aWNlKCkge1xuXHRcdFx0aWYgKCBhcHAuaXNOb3RpY2VWaXNpYmxlICkge1xuXHRcdFx0XHRyZXR1cm47XG5cdFx0XHR9XG5cblx0XHRcdGlmICggYXBwLmlzVGl0bGVNYXRjaEtleXdvcmRzKCAkKCAnI3RpdGxlJyApLnZhbCgpICkgKSB7XG5cdFx0XHRcdGFwcC5pc05vdGljZVZpc2libGUgPSB0cnVlO1xuXG5cdFx0XHRcdCQoICcud3Bmb3Jtcy1lZGl0LXBvc3QtZWR1Y2F0aW9uLW5vdGljZScgKS5yZW1vdmVDbGFzcyggJ3dwZm9ybXMtaGlkZGVuJyApO1xuXHRcdFx0fVxuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBTaG93IG5vdGljZSBpZiB0aGUgcGFnZSB0aXRsZSBtYXRjaGVzIHNvbWUga2V5d29yZHMgZm9yIEd1dGVuYmVyZyBFZGl0b3IuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHRtYXliZVNob3dHdXRlbmJlcmdOb3RpY2UoKSB7XG5cdFx0XHRpZiAoIGFwcC5pc05vdGljZVZpc2libGUgKSB7XG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblxuXHRcdFx0Y29uc3QgdGl0bGUgPSBhcHAuZ2V0RWRpdG9yVGl0bGUoKTtcblxuXHRcdFx0aWYgKCBhcHAuaXNUaXRsZU1hdGNoS2V5d29yZHMoIHRpdGxlICkgKSB7XG5cdFx0XHRcdGFwcC5pc05vdGljZVZpc2libGUgPSB0cnVlO1xuXG5cdFx0XHRcdGFwcC5zaG93R3V0ZW5iZXJnTm90aWNlKCk7XG5cdFx0XHR9XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIERldGVybWluZSBpZiB0aGUgdGl0bGUgbWF0Y2hlcyBrZXl3b3Jkcy5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqXG5cdFx0ICogQHBhcmFtIHtzdHJpbmd9IHRpdGxlVmFsdWUgUGFnZSB0aXRsZSB2YWx1ZS5cblx0XHQgKlxuXHRcdCAqIEByZXR1cm4ge2Jvb2xlYW59IFRydWUgaWYgdGhlIHRpdGxlIG1hdGNoZXMgc29tZSBrZXl3b3Jkcy5cblx0XHQgKi9cblx0XHRpc1RpdGxlTWF0Y2hLZXl3b3JkcyggdGl0bGVWYWx1ZSApIHtcblx0XHRcdGNvbnN0IGV4cGVjdGVkVGl0bGVSZWdleCA9IG5ldyBSZWdFeHAoIC9cXGIoY29udGFjdHxmb3JtKVxcYi9pICk7XG5cblx0XHRcdHJldHVybiBleHBlY3RlZFRpdGxlUmVnZXgudGVzdCggdGl0bGVWYWx1ZSApO1xuXHRcdH0sXG5cblx0XHQvKipcblx0XHQgKiBDbG9zZSBhIG5vdGljZS5cblx0XHQgKlxuXHRcdCAqIEBzaW5jZSAxLjguMVxuXHRcdCAqL1xuXHRcdGNsb3NlTm90aWNlKCkge1xuXHRcdFx0JCggdGhpcyApLmNsb3Nlc3QoICcud3Bmb3Jtcy1lZGl0LXBvc3QtZWR1Y2F0aW9uLW5vdGljZScgKS5yZW1vdmUoKTtcblxuXHRcdFx0YXBwLnVwZGF0ZVVzZXJNZXRhKCk7XG5cdFx0fSxcblxuXHRcdC8qKlxuXHRcdCAqIFVwZGF0ZSB1c2VyIG1ldGEgYW5kIGRvbid0IHNob3cgdGhlIG5vdGljZSBuZXh0IHRpbWUuXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS44LjFcblx0XHQgKi9cblx0XHR1cGRhdGVVc2VyTWV0YSgpIHtcblx0XHRcdCQucG9zdChcblx0XHRcdFx0d3Bmb3Jtc19lZGl0X3Bvc3RfZWR1Y2F0aW9uLmFqYXhfdXJsLFxuXHRcdFx0XHR7XG5cdFx0XHRcdFx0YWN0aW9uOiAnd3Bmb3Jtc19lZHVjYXRpb25fZGlzbWlzcycsXG5cdFx0XHRcdFx0bm9uY2U6IHdwZm9ybXNfZWRpdF9wb3N0X2VkdWNhdGlvbi5lZHVjYXRpb25fbm9uY2UsXG5cdFx0XHRcdFx0c2VjdGlvbjogJ2VkaXQtcG9zdC1ub3RpY2UnLFxuXHRcdFx0XHR9XG5cdFx0XHQpO1xuXHRcdH0sXG5cdH07XG5cblx0cmV0dXJuIGFwcDtcbn0oIGRvY3VtZW50LCB3aW5kb3csIGpRdWVyeSApICk7XG5cbldQRm9ybXNFZGl0UG9zdEVkdWNhdGlvbi5pbml0KCk7XG4iXSwibWFwcGluZ3MiOiI7Ozs7Ozs7O0FBQUE7O0FBRUE7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBOztBQUVBO0FBQ0EsSUFBSUEsd0JBQXdCLEdBQUdDLE1BQU0sQ0FBQ0Qsd0JBQXdCLElBQU0sVUFBVUUsUUFBUSxFQUFFRCxNQUFNLEVBQUVFLENBQUMsRUFBRztFQUNuRztFQUNBLElBQU1DLFlBQVksR0FBRyxnQkFBZ0I7SUFDcENDLFVBQVUsR0FBRyxhQUFhO0lBQzFCQyxlQUFlLEdBQUcsbUJBQW1CO0lBQ3JDQyxXQUFXLEdBQUcsY0FBYztJQUU1QjtJQUNBQyxXQUFXLEdBQUcsY0FBYzs7RUFFN0I7QUFDRDtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7RUFDQyxJQUFNQyxHQUFHLEdBQUc7SUFFWDtBQUNGO0FBQ0E7QUFDQTtBQUNBO0lBQ0VDLGVBQWUsRUFBRSxLQUFLO0lBRXRCO0FBQ0Y7QUFDQTtBQUNBO0FBQ0E7SUFDRUMsUUFBUSxFQUFFLDJDQUEyQztJQUVyRDtBQUNGO0FBQ0E7QUFDQTtBQUNBO0lBQ0VDLElBQUksV0FBSkEsSUFBSUEsQ0FBQSxFQUFHO01BQ05ULENBQUMsQ0FBRUYsTUFBTyxDQUFDLENBQUNZLEVBQUUsQ0FBRSxNQUFNLEVBQUUsWUFBVztRQUNsQztRQUNBLElBQUssT0FBT1YsQ0FBQyxDQUFDVyxLQUFLLENBQUNDLElBQUksS0FBSyxVQUFVLEVBQUc7VUFDekNaLENBQUMsQ0FBQ1csS0FBSyxDQUFDQyxJQUFJLENBQUVOLEdBQUcsQ0FBQ08sSUFBSyxDQUFDO1FBQ3pCLENB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
},{}]},{},[1])