(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory(require("jQuery"));
	else if(typeof define === 'function' && define.amd)
		define(["jQuery"], factory);
	else {
		var a = typeof exports === 'object' ? factory(require("jQuery")) : factory(root["jQuery"]);
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function(__WEBPACK_EXTERNAL_MODULE_jquery__) {
return /******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "jquery":
/*!*************************!*\
  !*** external "jQuery" ***!
  \*************************/
/***/ (function(module) {

module.exports = __WEBPACK_EXTERNAL_MODULE_jquery__;

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
!function() {
/*!************************************!*\
  !*** ./resources/js/pages/home.js ***!
  \************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! jquery */ "jquery");
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_0__);

jquery__WEBPACK_IMPORTED_MODULE_0___default()(function () {
  var _document$querySelect, _window$routes, _window$routes2;
  /**
   * --------------------------------------------------
   * Base URLs (injected from Blade)
   * --------------------------------------------------
   *
   * Required in Blade:
   *
   * <meta name="app-url" content="{{ config('app.url') }}">
   *
   * <script>
   *   window.routes = {
   *     homeGetEvents: "{{ route('home.events.get') }}",
   *     eventShow: "{{ url('/events') }}/"
   *   };
   *   window.assetBase = "{{ asset('') }}";
   * </script>
   */

  var APP_URL = ((_document$querySelect = document.querySelector('meta[name="app-url"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content) || window.location.origin;
  var getEvents = (_window$routes = window.routes) === null || _window$routes === void 0 ? void 0 : _window$routes.homeGetEvents;
  var showEvent = (_window$routes2 = window.routes) === null || _window$routes2 === void 0 ? void 0 : _window$routes2.eventShow;
  var assetBase = window.assetBase || "".concat(APP_URL, "/");
  if (!getEvents || !showEvent) {
    console.error('Required routes not defined on window.routes');
    return;
  }

  // --------------------------------------------------
  // Date formatting options
  // --------------------------------------------------
  var dateOptions = {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  };
  var searchTimer = null;

  // --------------------------------------------------
  // Render single event card
  // --------------------------------------------------
  function renderEvent(event) {
    if (!event || !event.start_date) return;
    var startDate = new Date(event.start_date);
    var endDate = event.end_date ? new Date(event.end_date) : null;
    var deadlineDate = new Date(startDate);
    if (event.deadline !== null) {
      deadlineDate.setDate(startDate.getDate() - parseInt(event.deadline, 10));
    }
    var img = event.logo ? "<img src=\"".concat(assetBase, "assets/img/logos/").concat(event.logo, "\"\n              height=\"120\"\n              width=\"120\"\n              style=\"margin:5px;border-radius:15px\" />") : '';
    var card = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventInfo').clone().removeClass('d-none');
    card.find('.eventName').text(event.name).attr('href', showEvent + event.id).addClass('text-white');
    card.find('.start_date').text(startDate.toLocaleDateString('en-US', dateOptions));
    card.find('.end_date').text(endDate ? endDate.toLocaleDateString('en-US', dateOptions) : '—');
    card.find('.deadline').text(event.deadline !== null ? deadlineDate.toLocaleDateString('en-US', dateOptions) : '—');
    card.find('.logo').html(img);
    card.find('.buttons').html("<a href=\"".concat(showEvent + event.id, "\"\n          class=\"btn btn-label-success\">\n        More Information\n       </a>"));
    jquery__WEBPACK_IMPORTED_MODULE_0___default()('#test').append(card);
  }

  // --------------------------------------------------
  // Load events via AJAX
  // --------------------------------------------------
  function loadEvents() {
    var period = jquery__WEBPACK_IMPORTED_MODULE_0___default()('.time_period input:checked').val();
    var search = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventSearch').val();
    jquery__WEBPACK_IMPORTED_MODULE_0___default()('#test').empty();
    jquery__WEBPACK_IMPORTED_MODULE_0___default()('#spinner1').removeClass('d-none');
    jquery__WEBPACK_IMPORTED_MODULE_0___default().ajax({
      url: getEvents,
      data: {
        period: period,
        search: search
      },
      success: function success(data) {
        jquery__WEBPACK_IMPORTED_MODULE_0___default()('#spinner1').addClass('d-none');
        if (Array.isArray(data)) {
          data.forEach(renderEvent);
        }
      },
      error: function error(xhr) {
        jquery__WEBPACK_IMPORTED_MODULE_0___default()('#spinner1').addClass('d-none');
        console.error('Error loading events', xhr);
        alert('Error loading events');
      }
    });
  }

  // --------------------------------------------------
  // UI bindings
  // --------------------------------------------------
  jquery__WEBPACK_IMPORTED_MODULE_0___default()('.time_period').on('change', loadEvents);
  jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventSearch').on('keyup', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadEvents, 300);
  });

  // --------------------------------------------------
  // Initial load
  // --------------------------------------------------
  loadEvents();
});
}();
/******/ 	return __webpack_exports__;
/******/ })()
;
});