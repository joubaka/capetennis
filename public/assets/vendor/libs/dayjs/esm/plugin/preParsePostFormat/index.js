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
/*!***********************************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/preParsePostFormat/index.js ***!
  \***********************************************************************************/
__webpack_require__.r(__webpack_exports__);
// Plugin template from https://day.js.org/docs/en/plugin/plugin
/* harmony default export */ __webpack_exports__["default"] = (function (option, dayjsClass) {
  var oldParse = dayjsClass.prototype.parse;
  dayjsClass.prototype.parse = function (cfg) {
    if (typeof cfg.date === 'string') {
      var locale = this.$locale();
      cfg.date = locale && locale.preparse ? locale.preparse(cfg.date) : cfg.date;
    } // original parse result

    return oldParse.bind(this)(cfg);
  }; // // overriding existing API
  // // e.g. extend dayjs().format()

  var oldFormat = dayjsClass.prototype.format;
  dayjsClass.prototype.format = function () {
    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }

    // original format result
    var result = oldFormat.call.apply(oldFormat, [this].concat(args)); // return modified result

    var locale = this.$locale();
    return locale && locale.postformat ? locale.postformat(result) : result;
  };
  var oldFromTo = dayjsClass.prototype.fromToBase;
  if (oldFromTo) {
    dayjsClass.prototype.fromToBase = function (input, withoutSuffix, instance, isFrom) {
      var locale = this.$locale() || instance.$locale(); // original format result

      return oldFromTo.call(this, input, withoutSuffix, instance, isFrom, locale && locale.postformat);
    };
  }
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});