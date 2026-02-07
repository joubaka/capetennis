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
/*!*****************************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/arraySupport/index.js ***!
  \*****************************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony default export */ __webpack_exports__["default"] = (function (o, c, dayjs) {
  var proto = c.prototype;
  var parseDate = function parseDate(cfg) {
    var date = cfg.date,
      utc = cfg.utc;
    if (Array.isArray(date)) {
      if (utc) {
        if (!date.length) {
          return new Date();
        }
        return new Date(Date.UTC.apply(null, date));
      }
      if (date.length === 1) {
        return dayjs(String(date[0])).toDate();
      }
      return new (Function.prototype.bind.apply(Date, [null].concat(date)))();
    }
    return date;
  };
  var oldParse = proto.parse;
  proto.parse = function (cfg) {
    cfg.date = parseDate.bind(this)(cfg);
    oldParse.bind(this)(cfg);
  };
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});