(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory(require("jQuery"));
	else if(typeof define === 'function' && define.amd)
		define(["jQuery"], factory);
	else {
		var a = typeof exports === 'object' ? factory(require("jQuery")) : factory(root["jQuery"]);
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function(__WEBPACK_EXTERNAL_MODULE_jquery__) {
return /******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "jquery":
/*!*************************!*\
  !*** external "jQuery" ***!
  \*************************/
/***/ (function(module) {

module.exports = __WEBPACK_EXTERNAL_MODULE_jquery__;

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
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
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
/*!********************************************!*\
  !*** ./resources/js/pages/seriesEvents.js ***!
  \********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! jquery */ "jquery");
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_0__);

jquery__WEBPACK_IMPORTED_MODULE_0___default()(document).ready(function () {
  console.log('📂 Series Events JS Loaded');

  /*
  |--------------------------------------------------------------------------
  | SELECT2 INIT
  |--------------------------------------------------------------------------
  */

  var $select = jquery__WEBPACK_IMPORTED_MODULE_0___default()('.select2');
  console.log('Select2 elements found:', $select.length);
  if ((jquery__WEBPACK_IMPORTED_MODULE_0___default().fn.select2)) {
    $select.select2({
      width: '100%',
      allowClear: true,
      placeholder: function placeholder() {
        return jquery__WEBPACK_IMPORTED_MODULE_0___default()(this).data('placeholder');
      }
    });
    console.log('Select2 initialized ✅');
  } else {
    console.warn('Select2 NOT loaded ❌');
  }

  /*
  |--------------------------------------------------------------------------
  | TOASTR CONFIG
  |--------------------------------------------------------------------------
  */

  if (window.toastr) {
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: "toast-top-right",
      timeOut: 2500
    };
    console.log('Toastr ready ✅');
  } else {
    console.warn('Toastr NOT loaded ❌');
  }

  /*
  |--------------------------------------------------------------------------
  | LOGO PREVIEW LOGIC
  |--------------------------------------------------------------------------
  */

  var $logoSelect = jquery__WEBPACK_IMPORTED_MODULE_0___default()('select[name="logo_existing"]');
  var $logoUpload = jquery__WEBPACK_IMPORTED_MODULE_0___default()('input[name="logo_upload"]');
  var $preview = jquery__WEBPACK_IMPORTED_MODULE_0___default()('#logo-preview');

  // When selecting existing logo
  $logoSelect.on('change', function () {
    var filename = jquery__WEBPACK_IMPORTED_MODULE_0___default()(this).val();
    console.log('Selected existing logo:', filename);
    if (!filename) {
      $preview.addClass('d-none');
      return;
    }
    var imageUrl = "/assets/img/logos/".concat(filename);
    $preview.attr('src', imageUrl).removeClass('d-none');
    if (window.toastr) {
      toastr.info('Existing logo selected');
    }
  });

  // When uploading new logo
  $logoUpload.on('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    console.log('Uploading logo file:', file.name);
    var reader = new FileReader();
    reader.onload = function (event) {
      $preview.attr('src', event.target.result).removeClass('d-none');
    };
    reader.readAsDataURL(file);
    if (window.toastr) {
      toastr.success('New logo preview loaded');
    }
  });
});
}();
/******/ 	return __webpack_exports__;
/******/ })()
;
});