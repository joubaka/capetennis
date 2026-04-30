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
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
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
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
!function() {
/*!*************************************************************************!*\
  !*** ./resources/assets/vendor/libs/dayjs/esm/plugin/duration/index.js ***!
  \*************************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _constant__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../constant */ "./resources/assets/vendor/libs/dayjs/esm/constant.js");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }

var MILLISECONDS_A_YEAR = _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_DAY * 365;
var MILLISECONDS_A_MONTH = _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_DAY * 30;
var durationRegex = /^(-|\+)?P(?:([-+]?[0-9,.]*)Y)?(?:([-+]?[0-9,.]*)M)?(?:([-+]?[0-9,.]*)W)?(?:([-+]?[0-9,.]*)D)?(?:T(?:([-+]?[0-9,.]*)H)?(?:([-+]?[0-9,.]*)M)?(?:([-+]?[0-9,.]*)S)?)?$/;
var unitToMS = {
  years: MILLISECONDS_A_YEAR,
  months: MILLISECONDS_A_MONTH,
  days: _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_DAY,
  hours: _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_HOUR,
  minutes: _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_MINUTE,
  seconds: _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_SECOND,
  milliseconds: 1,
  weeks: _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_WEEK
};
var isDuration = function isDuration(d) {
  return d instanceof Duration;
}; // eslint-disable-line no-use-before-define

var $d;
var $u;
var wrapper = function wrapper(input, instance, unit) {
  return new Duration(input, unit, instance.$l);
}; // eslint-disable-line no-use-before-define

var prettyUnit = function prettyUnit(unit) {
  return $u.p(unit) + "s";
};
var isNegative = function isNegative(number) {
  return number < 0;
};
var roundNumber = function roundNumber(number) {
  return isNegative(number) ? Math.ceil(number) : Math.floor(number);
};
var absolute = function absolute(number) {
  return Math.abs(number);
};
var getNumberUnitFormat = function getNumberUnitFormat(number, unit) {
  if (!number) {
    return {
      negative: false,
      format: ''
    };
  }
  if (isNegative(number)) {
    return {
      negative: true,
      format: "" + absolute(number) + unit
    };
  }
  return {
    negative: false,
    format: "" + number + unit
  };
};
var Duration = /*#__PURE__*/function () {
  function Duration(input, unit, locale) {
    var _this = this;
    this.$d = {};
    this.$l = locale;
    if (input === undefined) {
      this.$ms = 0;
      this.parseFromMilliseconds();
    }
    if (unit) {
      return wrapper(input * unitToMS[prettyUnit(unit)], this);
    }
    if (typeof input === 'number') {
      this.$ms = input;
      this.parseFromMilliseconds();
      return this;
    }
    if (_typeof(input) === 'object') {
      Object.keys(input).forEach(function (k) {
        _this.$d[prettyUnit(k)] = input[k];
      });
      this.calMilliseconds();
      return this;
    }
    if (typeof input === 'string') {
      var d = input.match(durationRegex);
      if (d) {
        var properties = d.slice(2);
        var numberD = properties.map(function (value) {
          return value != null ? Number(value) : 0;
        });
        this.$d.years = numberD[0];
        this.$d.months = numberD[1];
        this.$d.weeks = numberD[2];
        this.$d.days = numberD[3];
        this.$d.hours = numberD[4];
        this.$d.minutes = numberD[5];
        this.$d.seconds = numberD[6];
        this.calMilliseconds();
        return this;
      }
    }
    return this;
  }
  var _proto = Duration.prototype;
  _proto.calMilliseconds = function calMilliseconds() {
    var _this2 = this;
    this.$ms = Object.keys(this.$d).reduce(function (total, unit) {
      return total + (_this2.$d[unit] || 0) * unitToMS[unit];
    }, 0);
  };
  _proto.parseFromMilliseconds = function parseFromMilliseconds() {
    var $ms = this.$ms;
    this.$d.years = roundNumber($ms / MILLISECONDS_A_YEAR);
    $ms %= MILLISECONDS_A_YEAR;
    this.$d.months = roundNumber($ms / MILLISECONDS_A_MONTH);
    $ms %= MILLISECONDS_A_MONTH;
    this.$d.days = roundNumber($ms / _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_DAY);
    $ms %= _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_DAY;
    this.$d.hours = roundNumber($ms / _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_HOUR);
    $ms %= _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_HOUR;
    this.$d.minutes = roundNumber($ms / _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_MINUTE);
    $ms %= _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_MINUTE;
    this.$d.seconds = roundNumber($ms / _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_SECOND);
    $ms %= _constant__WEBPACK_IMPORTED_MODULE_0__.MILLISECONDS_A_SECOND;
    this.$d.milliseconds = $ms;
  };
  _proto.toISOString = function toISOString() {
    var Y = getNumberUnitFormat(this.$d.years, 'Y');
    var M = getNumberUnitFormat(this.$d.months, 'M');
    var days = +this.$d.days || 0;
    if (this.$d.weeks) {
      days += this.$d.weeks * 7;
    }
    var D = getNumberUnitFormat(days, 'D');
    var H = getNumberUnitFormat(this.$d.hours, 'H');
    var m = getNumberUnitFormat(this.$d.minutes, 'M');
    var seconds = this.$d.seconds || 0;
    if (this.$d.milliseconds) {
      seconds += this.$d.milliseconds / 1000;
    }
    var S = getNumberUnitFormat(seconds, 'S');
    var negativeMode = Y.negative || M.negative || D.negative || H.negative || m.negative || S.negative;
    var T = H.format || m.format || S.format ? 'T' : '';
    var P = negativeMode ? '-' : '';
    var result = P + "P" + Y.format + M.format + D.format + T + H.format + m.format + S.format;
    return result === 'P' || result === '-P' ? 'P0D' : result;
  };
  _proto.toJSON = function toJSON() {
    return this.toISOString();
  };
  _proto.format = function format(formatStr) {
    var str = formatStr || 'YYYY-MM-DDTHH:mm:ss';
    var matches = {
      Y: this.$d.years,
      YY: $u.s(this.$d.years, 2, '0'),
      YYYY: $u.s(this.$d.years, 4, '0'),
      M: this.$d.months,
      MM: $u.s(this.$d.months, 2, '0'),
      D: this.$d.days,
      DD: $u.s(this.$d.days, 2, '0'),
      H: this.$d.hours,
      HH: $u.s(this.$d.hours, 2, '0'),
      m: this.$d.minutes,
      mm: $u.s(this.$d.minutes, 2, '0'),
      s: this.$d.seconds,
      ss: $u.s(this.$d.seconds, 2, '0'),
      SSS: $u.s(this.$d.milliseconds, 3, '0')
    };
    return str.replace(_constant__WEBPACK_IMPORTED_MODULE_0__.REGEX_FORMAT, function (match, $1) {
      return $1 || String(matches[match]);
    });
  };
  _proto.as = function as(unit) {
    return this.$ms / unitToMS[prettyUnit(unit)];
  };
  _proto.get = function get(unit) {
    var base = this.$ms;
    var pUnit = prettyUnit(unit);
    if (pUnit === 'milliseconds') {
      base %= 1000;
    } else if (pUnit === 'weeks') {
      base = roundNumber(base / unitToMS[pUnit]);
    } else {
      base = this.$d[pUnit];
    }
    return base === 0 ? 0 : base; // a === 0 will be true on both 0 and -0
  };

  _proto.add = function add(input, unit, isSubtract) {
    var another;
    if (unit) {
      another = input * unitToMS[prettyUnit(unit)];
    } else if (isDuration(input)) {
      another = input.$ms;
    } else {
      another = wrapper(input, this).$ms;
    }
    return wrapper(this.$ms + another * (isSubtract ? -1 : 1), this);
  };
  _proto.subtract = function subtract(input, unit) {
    return this.add(input, unit, true);
  };
  _proto.locale = function locale(l) {
    var that = this.clone();
    that.$l = l;
    return that;
  };
  _proto.clone = function clone() {
    return wrapper(this.$ms, this);
  };
  _proto.humanize = function humanize(withSuffix) {
    return $d().add(this.$ms, 'ms').locale(this.$l).fromNow(!withSuffix);
  };
  _proto.valueOf = function valueOf() {
    return this.asMilliseconds();
  };
  _proto.milliseconds = function milliseconds() {
    return this.get('milliseconds');
  };
  _proto.asMilliseconds = function asMilliseconds() {
    return this.as('milliseconds');
  };
  _proto.seconds = function seconds() {
    return this.get('seconds');
  };
  _proto.asSeconds = function asSeconds() {
    return this.as('seconds');
  };
  _proto.minutes = function minutes() {
    return this.get('minutes');
  };
  _proto.asMinutes = function asMinutes() {
    return this.as('minutes');
  };
  _proto.hours = function hours() {
    return this.get('hours');
  };
  _proto.asHours = function asHours() {
    return this.as('hours');
  };
  _proto.days = function days() {
    return this.get('days');
  };
  _proto.asDays = function asDays() {
    return this.as('days');
  };
  _proto.weeks = function weeks() {
    return this.get('weeks');
  };
  _proto.asWeeks = function asWeeks() {
    return this.as('weeks');
  };
  _proto.months = function months() {
    return this.get('months');
  };
  _proto.asMonths = function asMonths() {
    return this.as('months');
  };
  _proto.years = function years() {
    return this.get('years');
  };
  _proto.asYears = function asYears() {
    return this.as('years');
  };
  return Duration;
}();
var manipulateDuration = function manipulateDuration(date, duration, k) {
  return date.add(duration.years() * k, 'y').add(duration.months() * k, 'M').add(duration.days() * k, 'd').add(duration.hours() * k, 'h').add(duration.minutes() * k, 'm').add(duration.seconds() * k, 's').add(duration.milliseconds() * k, 'ms');
};
/* harmony default export */ __webpack_exports__["default"] = (function (option, Dayjs, dayjs) {
  $d = dayjs;
  $u = dayjs().$utils();
  dayjs.duration = function (input, unit) {
    var $l = dayjs.locale();
    return wrapper(input, {
      $l: $l
    }, unit);
  };
  dayjs.isDuration = isDuration;
  var oldAdd = Dayjs.prototype.add;
  var oldSubtract = Dayjs.prototype.subtract;
  Dayjs.prototype.add = function (value, unit) {
    if (isDuration(value)) {
      return manipulateDuration(this, value, 1);
    }
    return oldAdd.bind(this)(value, unit);
  };
  Dayjs.prototype.subtract = function (value, unit) {
    if (isDuration(value)) {
      return manipulateDuration(this, value, -1);
    }
    return oldSubtract.bind(this)(value, unit);
  };
});
}();
/******/ 	return __webpack_exports__;
/******/ })()
;
});