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
/*!***********************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/minMax/index.js ***!
  \***********************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony default export */ __webpack_exports__["default"] = (function (o, c, d) {
  var sortBy = function sortBy(method, dates) {
    if (!dates || !dates.length || dates.length === 1 && !dates[0] || dates.length === 1 && Array.isArray(dates[0]) && !dates[0].length) {
      return null;
    }
    if (dates.length === 1 && dates[0].length > 0) {
      var _dates = dates;
      dates = _dates[0];
    }
    dates = dates.filter(function (date) {
      return date;
    });
    var result;
    var _dates2 = dates;
    result = _dates2[0];
    for (var i = 1; i < dates.length; i += 1) {
      if (!dates[i].isValid() || dates[i][method](result)) {
        result = dates[i];
      }
    }
    return result;
  };
  d.max = function () {
    var args = [].slice.call(arguments, 0); // eslint-disable-line prefer-rest-params

    return sortBy('isAfter', args);
  };
  d.min = function () {
    var args = [].slice.call(arguments, 0); // eslint-disable-line prefer-rest-params

    return sortBy('isBefore', args);
  };
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});