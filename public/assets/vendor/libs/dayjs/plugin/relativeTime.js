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

/***/ "./resources/assets/vendor/libs/dayjs/plugin/relativeTime.js":
/*!*******************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/plugin/relativeTime.js ***!
  \*******************************************************************/
/***/ (function(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_RESULT__;function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
!function (r, e) {
  "object" == ( false ? 0 : _typeof(exports)) && "undefined" != "object" ? module.exports = e() :  true ? !(__WEBPACK_AMD_DEFINE_FACTORY__ = (e),
		__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
		(__WEBPACK_AMD_DEFINE_FACTORY__.call(exports, __webpack_require__, exports, module)) :
		__WEBPACK_AMD_DEFINE_FACTORY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__)) : 0;
}(this, function () {
  "use strict";

  return function (r, e, t) {
    r = r || {};
    var n = e.prototype,
      o = {
        future: "in %s",
        past: "%s ago",
        s: "a few seconds",
        m: "a minute",
        mm: "%d minutes",
        h: "an hour",
        hh: "%d hours",
        d: "a day",
        dd: "%d days",
        M: "a month",
        MM: "%d months",
        y: "a year",
        yy: "%d years"
      };
    function i(r, e, t, o) {
      return n.fromToBase(r, e, t, o);
    }
    t.en.relativeTime = o, n.fromToBase = function (e, n, i, d, u) {
      for (var f, a, s, l = i.$locale().relativeTime || o, h = r.thresholds || [{
          l: "s",
          r: 44,
          d: "second"
        }, {
          l: "m",
          r: 89
        }, {
          l: "mm",
          r: 44,
          d: "minute"
        }, {
          l: "h",
          r: 89
        }, {
          l: "hh",
          r: 21,
          d: "hour"
        }, {
          l: "d",
          r: 35
        }, {
          l: "dd",
          r: 25,
          d: "day"
        }, {
          l: "M",
          r: 45
        }, {
          l: "MM",
          r: 10,
          d: "month"
        }, {
          l: "y",
          r: 17
        }, {
          l: "yy",
          d: "year"
        }], m = h.length, c = 0; c < m; c += 1) {
        var y = h[c];
        y.d && (f = d ? t(e).diff(i, y.d, !0) : i.diff(e, y.d, !0));
        var p = (r.rounding || Math.round)(Math.abs(f));
        if (s = f > 0, p <= y.r || !y.r) {
          p <= 1 && c > 0 && (y = h[c - 1]);
          var v = l[y.l];
          u && (p = u("" + p)), a = "string" == typeof v ? v.replace("%d", p) : v(p, n, y.l, s);
          break;
        }
      }
      if (n) return a;
      var M = s ? l.future : l.past;
      return "function" == typeof M ? M(a) : M.replace("%s", a);
    }, n.to = function (r, e) {
      return i(r, e, this, !0);
    }, n.from = function (r, e) {
      return i(r, e, this);
    };
    var d = function d(r) {
      return r.$u ? t.utc() : t();
    };
    n.toNow = function (r) {
      return this.to(d(this), r);
    }, n.fromNow = function (r) {
      return this.from(d(this), r);
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
/******/ 	var __webpack_exports__ = __webpack_require__("./resources/assets/vendor/libs/dayjs/plugin/relativeTime.js");
/******/ 	
/******/ 	return __webpack_exports__;
/******/ })()
;
});