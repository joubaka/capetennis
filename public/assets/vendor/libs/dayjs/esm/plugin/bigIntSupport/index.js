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
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
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
/*!******************************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/bigIntSupport/index.js ***!
  \******************************************************************************/
__webpack_require__.r(__webpack_exports__);
// eslint-disable-next-line valid-typeof
var isBigInt = function isBigInt(num) {
  return typeof num === 'bigint';
};
/* harmony default export */ __webpack_exports__["default"] = (function (o, c, dayjs) {
  var proto = c.prototype;
  var parseDate = function parseDate(cfg) {
    var date = cfg.date;
    if (isBigInt(date)) {
      return Number(date);
    }
    return date;
  };
  var oldParse = proto.parse;
  proto.parse = function (cfg) {
    cfg.date = parseDate.bind(this)(cfg);
    oldParse.bind(this)(cfg);
  };
  var oldUnix = dayjs.unix;
  dayjs.unix = function (timestamp) {
    var ts = isBigInt(timestamp) ? Number(timestamp) : timestamp;
    return oldUnix(ts);
  };
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});