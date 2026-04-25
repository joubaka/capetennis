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

/***/ "./resources/assets/vendor/libs/dayjs/plugin/advancedFormat.js":
/*!*********************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/plugin/advancedFormat.js ***!
  \*********************************************************************/
/***/ (function(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_RESULT__;function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
!function (e, t) {
  "object" == ( false ? 0 : _typeof(exports)) && "undefined" != "object" ? module.exports = t() :  true ? !(__WEBPACK_AMD_DEFINE_FACTORY__ = (t),
		__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
		(__WEBPACK_AMD_DEFINE_FACTORY__.call(exports, __webpack_require__, exports, module)) :
		__WEBPACK_AMD_DEFINE_FACTORY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__)) : 0;
}(this, function () {
  "use strict";

  return function (e, t) {
    var r = t.prototype,
      n = r.format;
    r.format = function (e) {
      var t = this,
        r = this.$locale();
      if (!this.isValid()) return n.bind(this)(e);
      var s = this.$utils(),
        a = (e || "YYYY-MM-DDTHH:mm:ssZ").replace(/\[([^\]]+)]|Q|wo|ww|w|WW|W|zzz|z|gggg|GGGG|Do|X|x|k{1,2}|S/g, function (e) {
          switch (e) {
            case "Q":
              return Math.ceil((t.$M + 1) / 3);
            case "Do":
              return r.ordinal(t.$D);
            case "gggg":
              return t.weekYear();
            case "GGGG":
              return t.isoWeekYear();
            case "wo":
              return r.ordinal(t.week(), "W");
            case "w":
            case "ww":
              return s.s(t.week(), "w" === e ? 1 : 2, "0");
            case "W":
            case "WW":
              return s.s(t.isoWeek(), "W" === e ? 1 : 2, "0");
            case "k":
            case "kk":
              return s.s(String(0 === t.$H ? 24 : t.$H), "k" === e ? 1 : 2, "0");
            case "X":
              return Math.floor(t.$d.getTime() / 1e3);
            case "x":
              return t.$d.getTime();
            case "z":
              return "[" + t.offsetName() + "]";
            case "zzz":
              return "[" + t.offsetName("long") + "]";
            default:
              return e;
          }
        });
      return n.bind(this)(a);
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
/******/ 	var __webpack_exports__ = __webpack_require__("./resources/assets/vendor/libs/dayjs/plugin/advancedFormat.js");
/******/ 	
/******/ 	return __webpack_exports__;
/******/ })()
;
});