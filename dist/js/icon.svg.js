/**
 * @file
 * @file!
 *
 * @copyright Copyright (c) 2016 IcoMoon.io
 * @license Licensed under MIT license
 *            See https://github.com/Keyamoon/svgxuse
 * @version 1.1.23
 */
(function() {
  if (window && window.addEventListener) {
    var r = /* @__PURE__ */ Object.create(null), g, E, a = function() {
      clearTimeout(E), E = setTimeout(g, 100);
    }, w = function() {
    }, h = function() {
      var e;
      window.addEventListener("resize", a, !1), window.addEventListener("orientationchange", a, !1), window.MutationObserver ? (e = new MutationObserver(a), e.observe(document.documentElement, {
        childList: !0,
        subtree: !0,
        attributes: !0
      }), w = function() {
        try {
          e.disconnect(), window.removeEventListener("resize", a, !1), window.removeEventListener("orientationchange", a, !1);
        } catch {
        }
      }) : (document.documentElement.addEventListener("DOMSubtreeModified", a, !1), w = function() {
        document.documentElement.removeEventListener("DOMSubtreeModified", a, !1), window.removeEventListener("resize", a, !1), window.removeEventListener("orientationchange", a, !1);
      });
    }, R = function(e) {
      function t(l) {
        var u;
        return l.protocol !== void 0 ? u = l : (u = document.createElement("a"), u.href = l), u.protocol.replace(/:/g, "") + u.host;
      }
      var s, v, i;
      return window.XMLHttpRequest && (s = new XMLHttpRequest(), v = t(location), i = t(e), s.withCredentials === void 0 && i !== "" && i !== v ? s = XDomainRequest || void 0 : s = XMLHttpRequest), s;
    }, b = "http://www.w3.org/1999/xlink";
    g = function() {
      var e, t, s, v, i, l = 0, u, L, m, d, n;
      function p() {
        l -= 1, l === 0 && (w(), h());
      }
      function y(o) {
        return function() {
          r[o.base] !== !0 && o.useEl.setAttributeNS(b, "xlink:href", "#" + o.hash);
        };
      }
      function q(o) {
        return function() {
          var T = document.body, c = document.createElement("x"), f;
          o.onload = null, c.innerHTML = o.responseText, f = c.getElementsByTagName("svg")[0], f && (f.setAttribute("aria-hidden", "true"), f.style.position = "absolute", f.style.width = "0", f.style.height = "0", f.style.overflow = "hidden", T.insertBefore(f, T.firstChild)), p();
        };
      }
      function M(o) {
        return function() {
          o.onerror = null, o.ontimeout = null, p();
        };
      }
      for (w(), d = document.getElementsByTagName("use"), i = 0; i < d.length; i += 1) {
        try {
          t = d[i].getBoundingClientRect();
        } catch {
          t = !1;
        }
        v = d[i].getAttributeNS(b, "href"), v && v.split ? m = v.split("#") : m = ["", ""], e = m[0], s = m[1], u = t && t.left === 0 && t.right === 0 && t.top === 0 && t.bottom === 0, t && t.width === 0 && t.height === 0 && !u ? e.length && (n = r[e], n !== !0 && setTimeout(y({
          useEl: d[i],
          base: e,
          hash: s
        }), 0), n === void 0 && (L = R(e), L !== void 0 && (n = new L(), r[e] = n, n.onload = q(n), n.onerror = M(n), n.ontimeout = M(n), n.open("GET", e), n.send(), l += 1))) : u ? e.length && r[e] && y({
          useEl: d[i],
          base: e,
          hash: s
        })() : r[e] === void 0 ? r[e] = !0 : r[e].onload && (r[e].abort(), delete r[e].onload, r[e] = !0);
      }
      d = "", l += 1, p();
    }, window.addEventListener("load", function e() {
      window.removeEventListener("load", e, !1), E = setTimeout(g, 0);
    }, !1);
  }
})();
//# sourceMappingURL=icon.svg.js.map
