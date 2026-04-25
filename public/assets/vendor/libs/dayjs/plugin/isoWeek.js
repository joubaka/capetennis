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

/***/ "./resources/assets/vendor/libs/dayjs/plugin/isoWeek.js":
/*!**************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/plugin/isoWeek.js ***!
  \**************************************************************/
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

  var e = "day";
  return function (t, i, s) {
    var a = function a(t) {
        return t.add(4 - t.isoWeekday(), e);
      },
      d = i.prototype;
    d.isoWeekYear = function () {
      return a(this).year();
    }, d.isoWeek = function (t) {
      if (!this.$utils().u(t)) return this.add(7 * (t - this.isoWeek()), e);
      var i,
        d,
        n,
        o,
        r = a(this),
        u = (i = this.isoWeekYear(), d = this.$u, n = (d ? s.utc : s)().year(i).startOf("year"), o = 4 - n.isoWeekday(), n.isoWeekday() > 4 && (o += 7), n.add(o, e));
      return r.diff(u, "week") + 1;
    }, d.isoWeekday = function (e) {
      return this.$utils().u(e) ? this.day() || 7 : this.day(this.day() % 7 ? e : e - 7);
    };
    var n = d.startOf;
    d.startOf = function (e, t) {
      var i = this.$utils(),
        s = !!i.u(t) || t;
      return "isoweek" === i.p(e) ? s ? this.date(this.date() - (this.isoWeekday() - 1)).startOf("day") : this.date(this.date() - 1 - (this.isoWeekday() - 1) + 7).endOf("day") : n.bind(this)(e, t);
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
/******/ 	var __webpack_exports__ = __webpack_require__("./resources/assets/vendor/libs/dayjs/plugin/isoWeek.js");
/******/ 	
/******/ 	return __webpack_exports__;
/******/ })()
;
});