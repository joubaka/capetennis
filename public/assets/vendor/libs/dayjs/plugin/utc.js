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

/***/ "./resources/assets/vendor/libs/dayjs/plugin/utc.js":
/*!**********************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/plugin/utc.js ***!
  \**********************************************************/
/***/ (function(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_RESULT__;function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
!function (t, i) {
  "object" == ( false ? 0 : _typeof(exports)) && "undefined" != "object" ? module.exports = i() :  true ? !(__WEBPACK_AMD_DEFINE_FACTORY__ = (i),
		__WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ?
		(__WEBPACK_AMD_DEFINE_FACTORY__.call(exports, __webpack_require__, exports, module)) :
		__WEBPACK_AMD_DEFINE_FACTORY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__)) : 0;
}(this, function () {
  "use strict";

  var t = "minute",
    i = /[+-]\d\d(?::?\d\d)?/g,
    e = /([+-]|\d\d)/g;
  return function (s, f, n) {
    var u = f.prototype;
    n.utc = function (t) {
      var i = {
        date: t,
        utc: !0,
        args: arguments
      };
      return new f(i);
    }, u.utc = function (i) {
      var e = n(this.toDate(), {
        locale: this.$L,
        utc: !0
      });
      return i ? e.add(this.utcOffset(), t) : e;
    }, u.local = function () {
      return n(this.toDate(), {
        locale: this.$L,
        utc: !1
      });
    };
    var o = u.parse;
    u.parse = function (t) {
      t.utc && (this.$u = !0), this.$utils().u(t.$offset) || (this.$offset = t.$offset), o.call(this, t);
    };
    var r = u.init;
    u.init = function () {
      if (this.$u) {
        var t = this.$d;
        this.$y = t.getUTCFullYear(), this.$M = t.getUTCMonth(), this.$D = t.getUTCDate(), this.$W = t.getUTCDay(), this.$H = t.getUTCHours(), this.$m = t.getUTCMinutes(), this.$s = t.getUTCSeconds(), this.$ms = t.getUTCMilliseconds();
      } else r.call(this);
    };
    var a = u.utcOffset;
    u.utcOffset = function (s, f) {
      var n = this.$utils().u;
      if (n(s)) return this.$u ? 0 : n(this.$offset) ? a.call(this) : this.$offset;
      if ("string" == typeof s && (s = function (t) {
        void 0 === t && (t = "");
        var s = t.match(i);
        if (!s) return null;
        var f = ("" + s[0]).match(e) || ["-", 0, 0],
          n = f[0],
          u = 60 * +f[1] + +f[2];
        return 0 === u ? 0 : "+" === n ? u : -u;
      }(s), null === s)) return this;
      var u = Math.abs(s) <= 16 ? 60 * s : s,
        o = this;
      if (f) return o.$offset = u, o.$u = 0 === s, o;
      if (0 !== s) {
        var r = this.$u ? this.toDate().getTimezoneOffset() : -1 * this.utcOffset();
        (o = this.local().add(u + r, t)).$offset = u, o.$x.$localOffset = r;
      } else o = this.utc();
      return o;
    };
    var h = u.format;
    u.format = function (t) {
      var i = t || (this.$u ? "YYYY-MM-DDTHH:mm:ss[Z]" : "");
      return h.call(this, i);
    }, u.valueOf = function () {
      var t = this.$utils().u(this.$offset) ? 0 : this.$offset + (this.$x.$localOffset || this.$d.getTimezoneOffset());
      return this.$d.valueOf() - 6e4 * t;
    }, u.isUTC = function () {
      return !!this.$u;
    }, u.toISOString = function () {
      return this.toDate().toISOString();
    }, u.toString = function () {
      return this.toDate().toUTCString();
    };
    var l = u.toDate;
    u.toDate = function (t) {
      return "s" === t && this.$offset ? n(this.format("YYYY-MM-DD HH:mm:ss:SSS")).toDate() : l.call(this);
    };
    var c = u.diff;
    u.diff = function (t, i, e) {
      if (t && this.$u === t.$u) return c.call(this, t, i, e);
      var s = this.local(),
        f = n(t).local();
      return c.call(s, f, i, e);
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
/******/ 	var __webpack_exports__ = __webpack_require__("./resources/assets/vendor/libs/dayjs/plugin/utc.js");
/******/ 	
/******/ 	return __webpack_exports__;
/******/ })()
;
});