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
  var _window$routes, _window$routes2;
  var getEvents = (_window$routes = window.routes) === null || _window$routes === void 0 ? void 0 : _window$routes.homeGetEvents;
  var showEvent = (_window$routes2 = window.routes) === null || _window$routes2 === void 0 ? void 0 : _window$routes2.eventShow;
  var assetBase = window.assetBase || "".concat(window.location.origin, "/");
  if (!getEvents || !showEvent) {
    console.error('Required home routes are not defined');
    return;
  }
  var $list = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventList');
  var $loading = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventLoading');
  var $empty = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventEmpty');
  var $error = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventError');
  var $results = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventResults');
  var $loadMoreWrap = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventLoadMoreWrap');
  var $loadMore = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventLoadMore');
  var periodLabels = {
    upcoming: 'Upcoming events',
    past: 'Past events',
    all: 'All events'
  };
  var dateOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  };
  var searchTimer = null;
  var activeRequest = null;
  var currentPage = 1;
  var lastPage = 1;
  var renderedTotal = 0;
  function parseLocalDate(value) {
    if (!value) return null;
    var parts = String(value).slice(0, 10).split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
    return new Date(parts[0], parts[1] - 1, parts[2]);
  }
  function formatDate(date) {
    return date ? date.toLocaleDateString('en-ZA', dateOptions) : 'Not set';
  }
  function eventUrl(id) {
    return "".concat(showEvent).concat(encodeURIComponent(id));
  }
  function renderAdminStatus($card, status) {
    if (!status) return;
    var isPublished = status.publication === 'Published';
    var entriesAreOpen = status.entries === 'Sign-up open';
    var $status = $card.find('.event-admin-status').removeClass('d-none');
    jquery__WEBPACK_IMPORTED_MODULE_0___default()('<span>', {
      "class": "badge ".concat(isPublished ? 'bg-label-success' : 'bg-label-warning'),
      text: "Event status: ".concat(status.publication)
    }).appendTo($status);
    jquery__WEBPACK_IMPORTED_MODULE_0___default()('<span>', {
      "class": "badge ".concat(entriesAreOpen ? 'bg-label-info' : 'bg-label-secondary'),
      text: "Entries: ".concat(status.entries)
    }).appendTo($status);
  }
  function renderLogo($card, logo, eventName) {
    if (!logo) return;
    var safeFilename = String(logo).replace(/\\/g, '/').split('/').pop();
    if (!safeFilename) return;
    $card.find('.logo').empty().append(jquery__WEBPACK_IMPORTED_MODULE_0___default()('<img>', {
      alt: "".concat(eventName, " logo"),
      "class": 'event-logo',
      loading: 'lazy',
      src: "".concat(assetBase, "assets/img/logos/").concat(encodeURIComponent(safeFilename))
    }));
  }
  function renderEvent(event) {
    if (!(event !== null && event !== void 0 && event.id) || !(event !== null && event !== void 0 && event.name) || !(event !== null && event !== void 0 && event.start_date)) return false;
    var startDate = parseLocalDate(event.start_date);
    var endDate = parseLocalDate(event.end_date);
    if (!startDate) return false;
    var deadlineDate = null;
    if (event.deadline !== null && event.deadline !== '' && !Number.isNaN(Number(event.deadline))) {
      deadlineDate = new Date(startDate);
      deadlineDate.setDate(startDate.getDate() - Number(event.deadline));
    }
    var $card = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventInfo').children().first().clone();
    var url = eventUrl(event.id);
    $card.find('.eventName').text(event.name).attr('href', url);
    $card.find('.start_date').text(formatDate(startDate));
    $card.find('.end_date').text(formatDate(endDate));
    $card.find('.deadline').text(formatDate(deadlineDate));
    $card.find('.buttons').append(jquery__WEBPACK_IMPORTED_MODULE_0___default()('<a>', {
      "class": 'btn btn-label-primary',
      href: url,
      text: 'View event'
    }).append(' ').append(jquery__WEBPACK_IMPORTED_MODULE_0___default()('<i>', {
      "class": 'ti ti-arrow-right',
      'aria-hidden': 'true'
    })));
    renderLogo($card, event.logo, event.name);
    renderAdminStatus($card, event.admin_status);
    $list.append($card);
    return true;
  }
  function setLoading(isLoading) {
    $list.attr('aria-busy', String(isLoading));
    $loading.toggleClass('d-none', !isLoading);
    if (isLoading) {
      $empty.addClass('d-none');
      $error.addClass('d-none');
      $results.text('');
    }
  }
  function loadEvents() {
    var page = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 1;
    var period = jquery__WEBPACK_IMPORTED_MODULE_0___default()('.time_period input:checked').val() || 'upcoming';
    var search = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventSearch').val().trim();
    var appending = page > 1;
    if (activeRequest) activeRequest.abort();
    if (!appending) {
      $list.empty();
      renderedTotal = 0;
      jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventsHeading').text(periodLabels[period]);
      $loadMoreWrap.addClass('d-none');
      setLoading(true);
    } else {
      $loadMore.prop('disabled', true).text('Loading…');
    }
    activeRequest = jquery__WEBPACK_IMPORTED_MODULE_0___default().ajax({
      url: getEvents,
      data: {
        period: period,
        search: search,
        page: page
      }
    }).done(function (response) {
      var events = Array.isArray(response === null || response === void 0 ? void 0 : response.data) ? response.data : [];
      var meta = (response === null || response === void 0 ? void 0 : response.meta) || {};
      var renderedCount = events.reduce(function (count, event) {
        return count + (renderEvent(event) ? 1 : 0);
      }, 0);
      renderedTotal += renderedCount;
      currentPage = Number(meta.current_page) || page;
      lastPage = Number(meta.last_page) || currentPage;
      var total = Number(meta.total) || renderedTotal;
      var noun = total === 1 ? 'event' : 'events';
      $results.text(renderedTotal < total ? "".concat(renderedTotal, " of ").concat(total, " ").concat(noun) : "".concat(total, " ").concat(noun));
      $empty.toggleClass('d-none', total !== 0);
      $loadMoreWrap.toggleClass('d-none', currentPage >= lastPage);
    }).fail(function (xhr, status) {
      if (status === 'abort') return;
      $error.removeClass('d-none');
      $results.text('Unavailable');
      console.error('Error loading events', xhr.status);
    }).always(function (_response, status) {
      if (status !== 'abort') {
        setLoading(false);
        $loadMore.prop('disabled', false).html('Load more events <i class="ti ti-chevron-down ms-1" aria-hidden="true"></i>');
      }
      activeRequest = null;
    });
  }
  jquery__WEBPACK_IMPORTED_MODULE_0___default()('.time_period').on('change', function () {
    jquery__WEBPACK_IMPORTED_MODULE_0___default()('.home-periods label').removeClass('btn-primary').addClass('btn-label-secondary');
    jquery__WEBPACK_IMPORTED_MODULE_0___default()("label[for=\"".concat(jquery__WEBPACK_IMPORTED_MODULE_0___default()('.time_period input:checked').attr('id'), "\"]")).removeClass('btn-label-secondary').addClass('btn-primary');
    loadEvents();
  });
  jquery__WEBPACK_IMPORTED_MODULE_0___default()('#eventSearch').on('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadEvents, 300);
  });
  jquery__WEBPACK_IMPORTED_MODULE_0___default()('#retryEvents').on('click', function () {
    loadEvents();
  });
  $loadMore.on('click', function () {
    if (currentPage < lastPage) loadEvents(currentPage + 1);
  });
  loadEvents();
});
}();
/******/ 	return __webpack_exports__;
/******/ })()
;
});