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
/*!*************************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/calendar/index.js ***!
  \*************************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony default export */ __webpack_exports__["default"] = (function (o, c, d) {
  var LT = 'h:mm A';
  var L = 'MM/DD/YYYY';
  var calendarFormat = {
    lastDay: "[Yesterday at] " + LT,
    sameDay: "[Today at] " + LT,
    nextDay: "[Tomorrow at] " + LT,
    nextWeek: "dddd [at] " + LT,
    lastWeek: "[Last] dddd [at] " + LT,
    sameElse: L
  };
  var proto = c.prototype;
  proto.calendar = function (referenceTime, formats) {
    var format = formats || this.$locale().calendar || calendarFormat;
    var referenceStartOfDay = d(referenceTime || undefined).startOf('d');
    var diff = this.diff(referenceStartOfDay, 'd', true);
    var sameElse = 'sameElse';
    /* eslint-disable no-nested-ternary */

    var retVal = diff < -6 ? sameElse : diff < -1 ? 'lastWeek' : diff < 0 ? 'lastDay' : diff < 1 ? 'sameDay' : diff < 2 ? 'nextDay' : diff < 7 ? 'nextWeek' : sameElse;
    /* eslint-enable no-nested-ternary */

    var currentFormat = format[retVal] || calendarFormat[retVal];
    if (typeof currentFormat === 'function') {
      return currentFormat.call(this, d());
    }
    return this.format(currentFormat);
  };
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});