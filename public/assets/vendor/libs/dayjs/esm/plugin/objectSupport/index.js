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
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/objectSupport/index.js ***!
  \******************************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony default export */ __webpack_exports__["default"] = (function (o, c, dayjs) {
  var proto = c.prototype;
  var isObject = function isObject(obj) {
    return obj !== null && !(obj instanceof Date) && !(obj instanceof Array) && !proto.$utils().u(obj) && obj.constructor.name === 'Object';
  };
  var prettyUnit = function prettyUnit(u) {
    var unit = proto.$utils().p(u);
    return unit === 'date' ? 'day' : unit;
  };
  var parseDate = function parseDate(cfg) {
    var date = cfg.date,
      utc = cfg.utc;
    var $d = {};
    if (isObject(date)) {
      if (!Object.keys(date).length) {
        return new Date();
      }
      var now = utc ? dayjs.utc() : dayjs();
      Object.keys(date).forEach(function (k) {
        $d[prettyUnit(k)] = date[k];
      });
      var d = $d.day || (!$d.year && !($d.month >= 0) ? now.date() : 1);
      var y = $d.year || now.year();
      var M = $d.month >= 0 ? $d.month : !$d.year && !$d.day ? now.month() : 0; // eslint-disable-line no-nested-ternary,max-len

      var h = $d.hour || 0;
      var m = $d.minute || 0;
      var s = $d.second || 0;
      var ms = $d.millisecond || 0;
      if (utc) {
        return new Date(Date.UTC(y, M, d, h, m, s, ms));
      }
      return new Date(y, M, d, h, m, s, ms);
    }
    return date;
  };
  var oldParse = proto.parse;
  proto.parse = function (cfg) {
    cfg.date = parseDate.bind(this)(cfg);
    oldParse.bind(this)(cfg);
  };
  var oldSet = proto.set;
  var oldAdd = proto.add;
  var oldSubtract = proto.subtract;
  var callObject = function callObject(call, argument, string, offset) {
    if (offset === void 0) {
      offset = 1;
    }
    var keys = Object.keys(argument);
    var chain = this;
    keys.forEach(function (key) {
      chain = call.bind(chain)(argument[key] * offset, key);
    });
    return chain;
  };
  proto.set = function (unit, value) {
    value = value === undefined ? unit : value;
    if (unit.constructor.name === 'Object') {
      return callObject.bind(this)(function (i, s) {
        return oldSet.bind(this)(s, i);
      }, value, unit);
    }
    return oldSet.bind(this)(unit, value);
  };
  proto.add = function (value, unit) {
    if (value.constructor.name === 'Object') {
      return callObject.bind(this)(oldAdd, value, unit);
    }
    return oldAdd.bind(this)(value, unit);
  };
  proto.subtract = function (value, unit) {
    if (value.constructor.name === 'Object') {
      return callObject.bind(this)(oldAdd, value, unit, -1);
    }
    return oldSubtract.bind(this)(value, unit);
  };
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});