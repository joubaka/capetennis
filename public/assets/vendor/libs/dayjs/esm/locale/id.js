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

/***/ }),

/***/ "./resources/assets/vendor/libs/dayjs/esm/index.js":
/*!*********************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/index.js ***!
  \*********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _constant__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constant */ "./resources/assets/vendor/libs/dayjs/esm/constant.js");
/* harmony import */ var _locale_en__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./locale/en */ "./resources/assets/vendor/libs/dayjs/esm/locale/en.js");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utils */ "./resources/assets/vendor/libs/dayjs/esm/utils.js");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }



var L = 'en'; // global locale

var Ls = {}; // global loaded locale

Ls[L] = _locale_en__WEBPACK_IMPORTED_MODULE_1__["default"];
var isDayjs = function isDayjs(d) {
  return d instanceof Dayjs;
}; // eslint-disable-line no-use-before-define

var parseLocale = function parseLocale(preset, object, isLocal) {
  var l;
  if (!preset) return L;
  if (typeof preset === 'string') {
    var presetLower = preset.toLowerCase();
    if (Ls[presetLower]) {
      l = presetLower;
    }
    if (object) {
      Ls[presetLower] = object;
      l = presetLower;
    }
    var presetSplit = preset.split('-');
    if (!l && presetSplit.length > 1) {
      return parseLocale(presetSplit[0]);
    }
  } else {
    var name = preset.name;
    Ls[name] = preset;
    l = name;
  }
  if (!isLocal && l) L = l;
  return l || !isLocal && L;
};
var dayjs = function dayjs(date, c) {
  if (isDayjs(date)) {
    return date.clone();
  } // eslint-disable-next-line no-nested-ternary

  var cfg = _typeof(c) === 'object' ? c : {};
  cfg.date = date;
  cfg.args = arguments; // eslint-disable-line prefer-rest-params

  return new Dayjs(cfg); // eslint-disable-line no-use-before-define
};

var wrapper = function wrapper(date, instance) {
  return dayjs(date, {
    locale: instance.$L,
    utc: instance.$u,
    x: instance.$x,
    $offset: instance.$offset // todo: refactor; do not use this.$offset in you code
  });
};

var Utils = _utils__WEBPACK_IMPORTED_MODULE_2__["default"]; // for plugin use

Utils.l = parseLocale;
Utils.i = isDayjs;
Utils.w = wrapper;
var parseDate = function parseDate(cfg) {
  var date = cfg.date,
    utc = cfg.utc;
  if (date === null) return new Date(NaN); // null is invalid

  if (Utils.u(date)) return new Date(); // today

  if (date instanceof Date) return new Date(date);
  if (typeof date === 'string' && !/Z$/i.test(date)) {
    var d = date.match(_constant__WEBPACK_IMPORTED_MODULE_0__.REGEX_PARSE);
    if (d) {
      var m = d[2] - 1 || 0;
      var ms = (d[7] || '0').substring(0, 3);
      if (utc) {
        return new Date(Date.UTC(d[1], m, d[3] || 1, d[4] || 0, d[5] || 0, d[6] || 0, ms));
      }
      return new Date(d[1], m, d[3] || 1, d[4] || 0, d[5] || 0, d[6] || 0, ms);
    }
  }
  return new Date(date); // everything else
};

var Dayjs = /*#__PURE__*/function () {
  function Dayjs(cfg) {
    this.$L = parseLocale(cfg.locale, null, true);
    this.parse(cfg); // for plugin
  }

  var _proto = Dayjs.prototype;
  _proto.parse = function parse(cfg) {
    this.$d = parseDate(cfg);
    this.$x = cfg.x || {};
    this.init();
  };
  _proto.init = function init() {
    var $d = this.$d;
    this.$y = $d.getFullYear();
    this.$M = $d.getMonth();
    this.$D = $d.getDate();
    this.$W = $d.getDay();
    this.$H = $d.getHours();
    this.$m = $d.getMinutes();
    this.$s = $d.getSeconds();
    this.$ms = $d.getMilliseconds();
  } // eslint-disable-next-line class-methods-use-this
  ;

  _proto.$utils = function $utils() {
    return Utils;
  };
  _proto.isValid = function isValid() {
    return !(this.$d.toString() === _constant__WEBPACK_IMPORTED_MO                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  date.$d[name](arg);
      date.init();
      this.$d = date.set(_constant__WEBPACK_IMPORTED_MODULE_0__.DATE, Math.min(this.$D, date.daysInMonth())).$d;
    } else if (name) this.$d[name](arg);
    this.init();
    return this;
  };
  _proto.set = function set(string, _int2) {
    return this.clone().$set(string, _int2);
  };
  _proto.get = function get(unit) {
    return this[Utils.p(unit)]();
  };
  _proto.add = function add(number, units) {
    var _this2 = this,
      _C$MIN$C$H$C$S$unit;
    number = Number(number); // eslint-disable-line no-param-reassign

    var unit = Utils.p(units);
    var instanceFactorySet = function instanceFactorySet(n) {
      var d = dayjs(_this2);
      return Utils.w(d.date(d.date() + Math.round(n * number)), _this2);
    };
    if (unit === _constant__WEBPACK_IMPORTED_MODULE_0__.M) {
      return this.set(_constant__WEBPACK_IMPORTED_MODULE_0__.M, this.$M + number);
    }
    if (unit === _constant__WEBPACK_IMPORTED_MODULE_0__.Y) {
      return this.set(_constant__WEBPACK_IMPORTED_MODULE_0__.Y, this.$y + number);
    }
    if (unit === _constant__WEBPACK_IMPORTED_MODULE_0__.D) {
      return instanceFactorySet(1);
    }
    if (unit === _constant__WEBPACK_IMPORTED_MODULE_0__.W) {
      return instanceFactorySet(7);
    }
    var step = (_C$MIN$C$H$C$S$unit = {}, _C$MIN$C$H$C$S$unit[_constant__WEBPACK_IMPORTED_MODULE_0__.MIN] = _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_MINUTE, _C$MIN$C$H$C$S$unit[_constant__WEBPACK_IMPORTED_MODULE_0__.H] = _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_HOUR, _C$MIN$C$H$C$S$unit[_constant__WEBPACK_IMPORTED_MODULE_0__.S] = _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_SECOND, _C$MIN$C$H$C$S$unit)[unit] || 1; // ms

    var nextTimeStamp = this.$d.getTime() + number * step;
    return Utils.w(nextTimeStamp, this);
  };
  _proto.subtract = function subtract(number, string) {
    return this.add(number * -1, string);
  };
  _proto.format = function format(formatStr) {
    var _this3 = this;
    var locale = this.$locale();
    if (!this.isValid()) return locale.invalidDate || _constant__WEBPACK_IMPORTED_MODULE_0__.INVALID_DATE_STRING;
    var str = formatStr || _constant__WEBPACK_IMPORTED_MODULE_0__.FORMAT_DEFAULT;
    var zoneStr = Utils.z(this);
    var $H = this.$H,
      $m = this.$m,
      $M = this.$M;
    var weekdays = locale.weekdays,
      months = locale.months,
      meridiem = locale.meridiem;
    var getShort = function getShort(arr, index, full, length) {
      return arr && (arr[index] || arr(_this3, str)) || full[index].slice(0, length);
    };
    var get$H = function get$H(num) {
      return Utils.s($H % 12 || 12, num, '0');
    };
    var meridiemFunc = meridiem || function (hour, minute, isLowercase) {
      var m = hour < 12 ? 'AM' : 'PM';
      return isLowercase ? m.toLowerCase() : m;
    };
    var matches = function matches(match) {
      switch (match) {
        case 'YY':
          return String(_this3.$y).slice(-2);
        case 'YYYY':
          return Utils.s(_this3.$y, 4, '0');
        case 'M':
          return $M + 1;
        case 'MM':
          return Utils.s($M + 1, 2, '0');
        case 'MMM':
          return getShort(locale.monthsShort, $M, months, 3);
        case 'MMMM':
          return getShort(months, $M);
        case 'D':
          return _this3.$D;
        case 'DD':
          return Utils.s(_this3.$D, 2, '0');
        case 'd':
          return String(_this3.$W);
        case 'dd':
          return getShort(locale.weekdaysMin, _this3.$W, weekdays, 2);
        case 'ddd':
          return getShort(locale.weekdaysShort, _this3.$W, weekdays, 3);
        case 'dddd':
          return weekdays[_this3.$W];
        case 'H':
          return String($H);
        case 'HH':
          return Utils.s($H, 2, '0');
        case 'h':
          return get$H(1);
        case 'hh':
          return get$H(2);
        case 'a':
          return meridiemFunc($H, $m, true);
        case 'A':
          return meridiemFunc($H, $m, false);
        case 'm':
          return String($m);
        case 'mm':
  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    install plugin only once
    plugin(option, Dayjs, dayjs);
    plugin.$i = true;
  }
  return dayjs;
};
dayjs.locale = parseLocale;
dayjs.isDayjs = isDayjs;
dayjs.unix = function (timestamp) {
  return dayjs(timestamp * 1e3);
};
dayjs.en = Ls[L];
dayjs.Ls = Ls;
dayjs.p = {};
/* harmony default export */ __webpack_exports__["default"] = (dayjs);

/***/ }),

/***/ "./resources/assets/vendor/libs/dayjs/esm/locale/en.js":
/*!*************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/locale/en.js ***!
  \*************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// English [en]
// We don't need weekdaysShort, weekdaysMin, monthsShort in en.js locale
/* harmony default export */ __webpack_exports__["default"] = ({
  name: 'en',
  weekdays: 'Sunday_Monday_Tuesday_Wednesday_Thursday_Friday_Saturday'.split('_'),
  months: 'January_February_March_April_May_June_July_August_September_October_November_December'.split('_'),
  ordinal: function ordinal(n) {
    var s = ['th', 'st', 'nd', 'rd'];
    var v = n % 100;
    return "[" + n + (s[(v - 20) % 10] || s[v] || s[0]) + "]";
  }
});

/***/ }),

/***/ "./resources/assets/vendor/libs/dayjs/esm/utils.js":
/*!*********************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/utils.js ***!
  \*********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _constant__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constant */ "./resources/assets/vendor/libs/dayjs/esm/constant.js");

var padStart = function padStart(string, length, pad) {
  var s = String(string);
  if (!s || s.length >= length) return string;
  return "" + Array(length + 1 - s.length).join(pad) + string;
};
var padZoneStr = function padZoneStr(instance) {
  var negMinutes = -instance.utcOffset();
  var minutes = Math.abs(negMinutes);
  var hourOffset = Math.floor(minutes / 60);
  var minuteOffset = minutes % 60;
  return "" + (negMinutes <= 0 ? '+' : '-') + padStart(hourOffset, 2, '0') + ":" + padStart(minuteOffset, 2, '0');
};
var monthDiff = function monthDiff(a, b) {
  // function from moment.js in order to keep the same result
  if (a.date() < b.date()) return -monthDiff(b, a);
  var wholeMonthDiff = (b.year() - a.year()) * 12 + (b.month() - a.month());
  var anchor = a.clone().add(wholeMonthDiff, _constant__WEBPACK_IMPORTED_MODULE_0__.M);
  var c = b - anchor < 0;
  var anchor2 = a.clone().add(wholeMonthDiff + (c ? -1 : 1), _constant__WEBPACK_IMPORTED_MODULE_0__.M);
  return +(-(wholeMonthDiff + (b - anchor) / (c ? anchor - anchor2 : anchor2 - anchor)) || 0);
};
var absFloor = function absFloor(n) {
  return n < 0 ? Math.ceil(n) || 0 : Math.floor(n);
};
var prettyUnit = function prettyUnit(u) {
  var special = {
    M: _constant__WEBPACK_IMPORTED_MODULE_0__.M,
    y: _constant__WEBPACK_IMPORTED_MODULE_0__.Y,
    w: _constant__WEBPACK_IMPORTED_MODULE_0__.W,
    d: _constant__WEBPACK_IMPORTED_MODULE_0__.D,
    D: _constant__WEBPACK_IMPORTED_MODULE_0__.DATE,
    h: _constant__WEBPACK_IMPORTED_MODULE_0__.H,
    m: _constant__WEBPACK_IMPORTED_MODULE_0__.MIN,
    s: _constant__WEBPACK_IMPORTED_MODULE_0__.S,
    ms: _constant__WEBPACK_IMPORTED_MODULE_0__.MS,
    Q: _constant__WEBPACK_IMPORTED_MODULE_0__.Q
  };
  return special[u] || String(u || '').toLowerCase().replace(/s$/, '');
};
var isUndefined = function isUndefined(s) {
  return s === undefined;
};
/* harmony default export */ __webpack_exports__["default"] = ({
  s: padStart,
  z: padZoneStr,
  m: monthDiff,
  a: absFloor,
  p: prettyUnit,
  u: isUndefined
});

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/   [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [  [ 