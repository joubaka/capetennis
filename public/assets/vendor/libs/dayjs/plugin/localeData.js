(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function() {
return /******/ (function() { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/assets/vendor/libs/dayjs/plugin/localeData.js":
/*!*****************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/plugin/localeData.js ***!
  \*****************************************************************/
/***/ (function(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_RESULT__;function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
!function (n, e) {
  "object" == ( false ? 0 : _typeof(exports)) && "undefined" != "object" ? module.exports = e() :  true ? !(__WEBPACK_AMD_DEFINE_FACTORY__ = (e),
		__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
		(__WEBPACK_AMD_DEFINE_FACTORY__.call(exports, __webpack_require__, exports, module)) :
		__WEBPACK_AMD_DEFINE_FACTORY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__)) : 0;
}(this, function () {
  "use strict";

  return function (n, e, t) {
    var r = e.prototype,
      o = function o(n) {
        return n && (n.indexOf ? n : n.s);
      },
      u = function u(n, e, t, r, _u) {
        var i = n.name ? n : n.$locale(),
          a = o(i[e]),
          s = o(i[t]),
          f = a || s.map(function (n) {
            return n.slice(0, r);
          });
        if (!_u) return f;
        var d = i.weekStart;
        return f.map(function (n, e) {
          return f[(e + (d || 0)) % 7];
        });
      },
      i = function i() {
        return t.Ls[t.locale()];
      },
      a = function a(n, e) {
        return n.formats[e] || function (n) {
          return n.replace(/(\[[^\]]+])|(MMMM|MM|DD|dddd)/g, function (n, e, t) {
            return e || t.slice(1);
          });
        }(n.formats[e.toUpperCase()]);
      },
      s = function s() {
        var n = this;
        return {
          months: function months(e) {
            return e ? e.format("MMMM") : u(n, "months");
          },
          monthsShort: function monthsShort(e) {
            return e ? e.format("MMM") : u(n, "monthsShort", "months", 3);
          },
          firstDayOfWeek: function firstDayOfWeek() {
            return n.$locale().weekStart || 0;
          },
          weekdays: function weekdays(e) {
            return e ? e.format("dddd") : u(n, "weekdays");
          },
          weekdaysMin: function weekdaysMin(e) {
            return e ? e.format("dd") : u(n, "weekdaysMin", "weekdays", 2);
          },
          weekdaysShort: function weekdaysShort(e) {
            return e ? e.format("ddd") : u(n, "weekdaysShort", "weekdays", 3);
          },
          longDateFormat: function longDateFormat(e) {
            return a(n.$locale(), e);
          },
          meridiem: this.$locale().meridiem,
          ordinal: this.$locale().ordinal
        };
      };
    r.localeData = function () {
      return s.bind(this)();
    }, t.localeData = function () {
      var n = i();
      return {
        firstDayOfWeek: function firstDayOfWeek() {
          return n.weekStart || 0;
        },
        weekdays: function weekdays() {
          return t.weekdays();
        },
        weekdaysShort: function weekdaysShort() {
          return t.weekdaysShort();
        },
        weekdaysMin: function weekdaysMin() {
          return t.weekdaysMin();
        },
        months: function months() {
          return t.months();
        },
        monthsShort: function monthsShort() {
          return t.monthsShort();
        },
        longDateFormat: function longDateFormat(e) {
          return a(n, e);
        },
        meridiem: n.meridiem,
        ordinal: n.ordinal
      };
    }, t.months = function () {
      return u(i(), "months");
    }, t.monthsShort = function () {
      return u(i(), "monthsShort", "months", 3);
    }, t.weekdays = function (n) {
      return u(i(), "weekdays", null, null, n);
    }, t.weekdaysShort = function (n) {
      return u(i(), "weekdaysShort", "weekdays", 3, n);
    }, t.weekdaysMin = function (n) {
      return u(i(), "weekdaysMin", "weekdays", 2, n);
    };
  };
});

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
/******/ 		__webpack_modules__[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	var __webpack_exports__ = __webpack_require__("./resources/assets/vendor/libs/dayjs/plugin/localeData.js");
/******/ 	
/******/ 	return __webpack_exports__;
/******/ })()
;
});