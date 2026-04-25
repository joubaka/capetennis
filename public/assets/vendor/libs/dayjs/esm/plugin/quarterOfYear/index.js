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
/*!******************************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/quarterOfYear/index.js ***!
  \******************************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _constant__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../constant */ "./resources/assets/vendor/libs/dayjs/esm/constant.js");

/* harmony default export */ __webpack_exports__["default"] = (function (o, c) {
  var proto = c.prototype;
  proto.quarter = function (quarter) {
    if (!this.$utils().u(quarter)) {
      return this.month(this.month() % 3 + (quarter - 1) * 3);
    }
    return Math.ceil((this.month() + 1) / 3);
  };
  var oldAdd = proto.add;
  proto.add = function (number, units) {
    number = Number(number); // eslint-disable-line no-param-reassign

    var unit = this.$utils().p(units);
    if (unit === _constant__WEBPACK_IMPORTED_MODULE_0__.Q) {
      return this.add(number * 3, _constant__WEBPACK_IMPORTED_MODULE_0__.M);
    }
    return oldAdd.bind(this)(number, units);
  };
  var oldStartOf = proto.startOf;
  proto.startOf = function (units, startOf) {
    var utils = this.$utils();
    var isStartOf = !utils.u(startOf) ? startOf : true;
    var unit = utils.p(units);
    if (unit === _constant__WEBPACK_IMPORTED_MODULE_0__.Q) {
      var quarter = this.quarter() - 1;
      return isStartOf ? this.month(quarter * 3).startOf(_constant__WEBPACK_IMPORTED_MODULE_0__.M).startOf(_constant__WEBPACK_IMPORTED_MODULE_0__.D) : this.month(quarter * 3 + 2).endOf(_constant__WEBPACK_IMPORTED_MODULE_0__.M).endOf(_constant__WEBPACK_IMPORTED_MODULE_0__.D);
    }
    return oldStartOf.bind(this)(units, startOf);
  };
});
}();
/******/ 	return __webpack_exports__;
/******/ })()
;
});