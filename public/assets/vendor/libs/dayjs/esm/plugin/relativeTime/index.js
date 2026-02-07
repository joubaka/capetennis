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
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/assets/vendor/libs/dayjs/esm/constant.js":
/*!************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/constant.js ***!
  \************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "SECONDS_A_MINUTE": function() { return /* binding */ SECONDS_A_MINUTE; },
/* harmony export */   "SECONDS_A_HOUR": function() { return /* binding */ SECONDS_A_HOUR; },
/* harmony export */   "SECONDS_A_DAY": function() { return /* binding */ SECONDS_A_DAY; },
/* harmony export */   "SECONDS_A_WEEK": function() { return /* binding */ SECONDS_A_WEEK; },
/* harmony export */   "MILLISECONDS_A_SECOND": function() { return /* binding */ MILLISECONDS_A_SECOND; },
/* harmony export */   "MILLISECONDS_A_MINUTE": function() { return /* binding */ MILLISECONDS_A_MINUTE; },
/* harmony export */   "MILLISECONDS_A_HOUR": function() { return /* binding */ MILLISECONDS_A_HOUR; },
/* harmony export */   "MILLISECONDS_A_DAY": function() { return /* binding */ MILLISECONDS_A_DAY; },
/* harmony export */   "MILLISECONDS_A_WEEK": function() { return /* binding */ MILLISECONDS_A_WEEK; },
/* harmony export */   "MS": function() { return /* binding */ MS; },
/* harmony export */   "S": function() { return /* binding */ S; },
/* harmony export */   "MIN": function() { return /* binding */ MIN; },
/* harmony export */   "H": function() { return /* binding */ H; },
/* harmony export */   "D": function() { return /* binding */ D; },
/* harmony export */   "W": function() { return /* binding */ W; },
/* harmony export */   "M": function() { return /* binding */ M; },
/* harmony export */   "Q": function() { return /* binding */ Q; },
/* harmony export */   "Y": function() { return /* binding */ Y; },
/* harmony export */   "DATE": function() { return /* binding */ DATE; },
/* harmony export */   "FORMAT_DEFAULT": function() { return /* binding */ FORMAT_DEFAULT; },
/* harmony export */   "INVALID_DATE_STRING": function() { return /* binding */ INVALID_DATE_STRING; },
/* harmony export */   "REGEX_PARSE": function() { return /* binding */ REGEX_PARSE; },
/* harmony export */   "REGEX_FORMAT": function() { return /* binding */ REGEX_FORMAT; }
/* harmony export */ });
var SECONDS_A_MINUTE = 60;
var SECONDS_A_HOUR = SECONDS_A_MINUTE * 60;
var SECONDS_A_DAY = SECONDS_A_HOUR * 24;
var SECONDS_A_WEEK = SECONDS_A_DAY * 7;
var MILLISECONDS_A_SECOND = 1e3;
var MILLISECONDS_A_MINUTE = SECONDS_A_MINUTE * MILLISECONDS_A_SECOND;
var MILLISECONDS_A_HOUR = SECONDS_A_HOUR * MILLISECONDS_A_SECOND;
var MILLISECONDS_A_DAY = SECONDS_A_DAY * MILLISECONDS_A_SECOND;
var MILLISECONDS_A_WEEK = SECONDS_A_WEEK * MILLISECONDS_A_SECOND; // English locales

var MS = 'millisecond';
var S = 'second';
var MIN = 'minute';
var H = 'hour';
var D = 'day';
var W = 'week';
var M = 'month';
var Q = 'quarter';
var Y = 'year';
var DATE = 'date';
var FORMAT_DEFAULT = 'YYYY-MM-DDTHH:mm:ssZ';
var INVALID_DATE_STRING = 'Invalid Date'; // regex

var REGEX_PARSE = /^(\d{4})[-/]?(\d{1,2})?[-/]?(\d{0,2})[Tt\s]*(\d{1,2})?:?(\d{1,2})?:?(\d{1,2})?[.:]?(\d+)?$/;
var REGEX_FORMAT = /\[([^\]]+)]|Y{1,4}|M{1,4}|D{1,2}|d{1,4}|H{1,2}|h{1,2}|a|A|m{1,2}|s{1,2}|Z{1,2}|SSS/g;

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
/*!*****************************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/relativeTime/index.js ***!
  \*****************************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _constant__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../constant */ "./resources/assets/vendor/libs/dayjs/esm/constant.js");

/* harmony default export */ __webpack_exports__["default"] = (function (o, c, d) {
  o = o || {};
  var proto = c.prototype;
  var relObj = {
    future: 'in %s',
    past: '%s ago',
    s: 'a few seconds',
    m: 'a minute',
    mm: '%d minutes',
    h: 'an hour',
    hh: '%d hours',
    d: 'a day',
    dd: '%d days',
    M: 'a month',
    MM: '%d months',
    y: 'a year',
    yy: '%d years'
  };
  d.en.relativeTime = relObj;
  proto.fromToBase = function (input, withoutSuffix, instance, isFrom, postFormat) {
    var loc = instance.$locale().relativeTime || relObj;
    var T = o.thresholds || [{
      l: 's',
      r: 44,
      d: _constant__WEBPACK_IMPORTED_MODULE_0__.S
    }, {
      l: 'm',
      r: 89
    }, {
      l: 'mm',
      r: 44,
      d: _constant__WEBPACK_IMPORTED_MODULE_0__.MIN
    }, {
      l: 'h',
      r: 89
    }, {
      l: 'hh',
      r: 21,
      d: _constant__WEBPACK_IMPORTED_MODULE_0__.H
    }, {
      l: 'd',
      r: 35
    }, {
      l: 'dd',
      r: 25,
      d: _constant__WEBPACK_IMPORTED_MODULE_0__.D
    }, {
      l: 'M',
      r: 45
    }, {
      l: 'MM',
      r: 10,
      d: _constant__WEBPACK_IMPORTED_MODULE_0__.M
    }, {
      l: 'y',
      r: 17
    }, {
      l: 'yy',
      d: _constant__WEBPACK_IMPORTED_MODULE_0__.Y
    }];
    var Tl = T.length;
    var result;
    var out;
    var isFuture;
    for (var i = 0; i < Tl; i += 1) {
      var t = T[i];
      if (t.d) {
        result = isFrom ? d(input).diff(instance, t.d, true) : instance.diff(input, t.d, true);
      }
      var abs = (o.rounding || Math.round)(Math.abs(result));
      isFuture = result > 0;
      if (abs <= t.r || !t.r) {
        if (abs <= 1 && i > 0) t = T[i - 1]; // 1 minutes -> a minute, 0 seconds -> 0 second

        var format = loc[t.l];
        if (postFormat) {
          abs = postFormat("" + abs);
        }
        if (typeof format === 'string') {
          out = format.replace('%d', abs);
        } else {
          out = format(abs, withoutSuffix, t.l, isFuture);
        }
        break;
      }
    }
    if (withoutSuffix) return out;
    var pastOrFuture = isFuture ? loc.future : loc.past;
    if (typeof pastOrFuture === 'function') {
      return pastOrFuture(out);
    }
    return pastOrFuture.replace('%s', out);
  };
  function fromTo(input, withoutSuffix, instance, isFrom) {
    return proto.fromToBase(input, withoutSuffix, instance, isFrom);
  }
  proto.to = function (input, withoutSuffix) {
    return fromTo(input, withoutSuffix, this, true);
  };
  proto.from = function (input, withoutSuffix) {
    return fromTo(input, withoutSuffix, this);
  };
  var makeNow = function makeNow(thisDay) {
    return thisDay.$u ? d.utc() : d();
  };
  proto.toNow = function (withoutSuffix) {
    return this.to(makeNow(this), withoutSuffix);
  };
  proto.fromNow = function (withoutSuffix) {
    return this.from(makeNow(this), withoutSuffix);
  };
});
}();
/******/ 	return __webpack_exports__;
/******/ })()
;
});