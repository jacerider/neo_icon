/**
 * @file
 * @file!
 *
 * @copyright Copyright (c) 2016 IcoMoon.io
 * @license Licensed under MIT license
 *            See https://github.com/Keyamoon/svgxuse
 * @version 1.1.23
 */

/* eslint-disable */

/*jslint browser: true */
/*global XDomainRequest, MutationObserver, window */
(function () {
  'use strict';
  if (window && window.addEventListener) {
    var cache = Object.create(null); // Holds xhr objects to prevent multiple requests.
    var checkUseElems:Function;
    var tid:NodeJS.Timeout|undefined|number; // Timeout id.
    var debouncedCheck = function () {
      clearTimeout(tid);
      tid = setTimeout(checkUseElems, 100);
    };
    var unobserveChanges = function () {
      return;
    };
    var observeChanges = function () {
      var observer:MutationObserver;
      window.addEventListener('resize', debouncedCheck, false);
      window.addEventListener('orientationchange', debouncedCheck, false);
      if (window.MutationObserver) {
        observer = new MutationObserver(debouncedCheck);
        observer.observe(document.documentElement, {
          childList: true,
          subtree: true,
          attributes: true
        });
        unobserveChanges = function () {
          try {
            observer.disconnect();
            window.removeEventListener('resize', debouncedCheck, false);
            window.removeEventListener('orientationchange', debouncedCheck, false);
          }
          catch (ignore) {

          }
        };
      }
      else {
        document.documentElement.addEventListener('DOMSubtreeModified', debouncedCheck, false);
        unobserveChanges = function () {
          document.documentElement.removeEventListener('DOMSubtreeModified', debouncedCheck, false);
          window.removeEventListener('resize', debouncedCheck, false);
          window.removeEventListener('orientationchange', debouncedCheck, false);
        };
      }
    };
    var createRequest = function (url:string) {
      // In IE 9, cross origin requests can only be sent using XDomainRequest.
      // XDomainRequest would fail if CORS headers are not set.
      // Therefore, XDomainRequest should only be used with cross origin requests.
      function getOrigin(loc:any) {
        var a;
        if (loc.protocol !== undefined) {
          a = loc;
        }
        else {
          a = document.createElement('a');
          a.href = loc;
        }
        return a.protocol.replace(/:/g, '') + a.host;
      }
      var Request;
      var origin;
      var origin2;
      if (window.XMLHttpRequest) {
        Request = new XMLHttpRequest();
        origin = getOrigin(location);
        origin2 = getOrigin(url);
        if (Request.withCredentials === undefined && origin2 !== '' && origin2 !== origin) {
          // @ts-ignore
          Request = XDomainRequest || undefined;
        }
        else {
          Request = XMLHttpRequest;
        }
      }
      return Request;
    };
    var xlinkNS = 'http://www.w3.org/1999/xlink';
    checkUseElems = function () {
      var base;
      var bcr:DOMRect|boolean;
      var fallback = ''; // Optional fallback URL in case no base path to SVG file was given and no symbol definition was found.
      var hash;
      var href;
      var i;
      var inProgressCount = 0;
      var isHidden;
      var Request;
      var url;
      var uses;
      var xhr;

      function observeIfDone() {
        // If done with making changes, start watching for chagnes in DOM again.
        inProgressCount -= 1;
        if (inProgressCount === 0) { // If all xhrs were resolved
          unobserveChanges(); // make sure to remove old handlers
          observeChanges(); // watch for changes to DOM.
        }
      }

      function attrUpdateFunc(spec:any) {
        return function () {
          if (cache[spec.base] !== true) {
            spec.useEl.setAttributeNS(xlinkNS, 'xlink:href', '#' + spec.hash);
          }
        };
      }

      function onloadFunc(xhr:any) {
        return function () {
          var body = document.body;
          var x = document.createElement('x');
          var svg;
          xhr.onload = null;
          x.innerHTML = xhr.responseText;
          svg = x.getElementsByTagName('svg')[0];
          if (svg) {
            svg.setAttribute('aria-hidden', 'true');
            svg.style.position = 'absolute';
            svg.style.width = '0';
            svg.style.height = '0';
            svg.style.overflow = 'hidden';
            body.insertBefore(svg, body.firstChild);
          }
          observeIfDone();
        };
      }

      function onErrorTimeout(xhr:any) {
        return function () {
          xhr.onerror = null;
          xhr.ontimeout = null;
          observeIfDone();
        };
      }
      unobserveChanges(); // Stop watching for changes to DOM
      // find all use elements.
      uses = document.getElementsByTagName('use');
      for (i = 0; i < uses.length; i += 1) {
        try {
          bcr = uses[i].getBoundingClientRect();
        }
        catch (ignore) {
          // Failed to get bounding rectangle of the use element.
          bcr = false;
        }
        href = uses[i].getAttributeNS(xlinkNS, 'href');
        if (href && href.split) {
          url = href.split('#');
        }
        else {
          url = ["", ""];
        }
        base = url[0];
        hash = url[1];
        isHidden = bcr && bcr.left === 0 && bcr.right === 0 && bcr.top === 0 && bcr.bottom === 0;
        if (bcr && bcr.width === 0 && bcr.height === 0 && !isHidden) {
          // The use element is empty
          // if there is a reference to an external SVG, try to fetch it
          // use the optional fallback URL if there is no reference to an external SVG.
          if (fallback && !base.length && hash && !document.getElementById(hash)) {
            base = fallback;
          }
          if (base.length) {
            // Schedule updating xlink:href.
            xhr = cache[base];
            if (xhr !== true) {
              // True signifies that prepending the SVG was not required.
              setTimeout(attrUpdateFunc({
                useEl: uses[i],
                base: base,
                hash: hash
              }), 0);
            }
            if (xhr === undefined) {
              Request = createRequest(base);
              if (Request !== undefined) {
                xhr = new Request();
                cache[base] = xhr;
                xhr.onload = onloadFunc(xhr);
                xhr.onerror = onErrorTimeout(xhr);
                xhr.ontimeout = onErrorTimeout(xhr);
                xhr.open('GET', base);
                xhr.send();
                inProgressCount += 1;
              }
            }
          }
        }
        else {
          if (!isHidden) {
            if (cache[base] === undefined) {
              // Remember this URL if the use element was not empty and no request was sent.
              cache[base] = true;
            }
            else if (cache[base].onload) {
              // If it turns out that prepending the SVG is not necessary,
              // abort the in-progress xhr.
              cache[base].abort();
              delete cache[base].onload;
              cache[base] = true;
            }
          }
          else if (base.length && cache[base]) {
            attrUpdateFunc({
              useEl: uses[i],
              base: base,
              hash: hash
            })();
          }
        }
      }
      uses = '';
      inProgressCount += 1;
      observeIfDone();
    };
    // The load event fires when all resources have finished loading, which allows detecting whether SVG use elements are empty.
    window.addEventListener('load', function winLoad() {
      window.removeEventListener('load', winLoad, false); // To prevent memory leaks.
      tid = setTimeout(checkUseElems, 0);
    }, false);
  }
}());

export {};
