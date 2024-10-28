var y = Object.defineProperty;
var v = (d, e, i) => e in d ? y(d, e, { enumerable: !0, configurable: !0, writable: !0, value: i }) : d[e] = i;
var s = (d, e, i) => v(d, typeof e != "symbol" ? e + "" : e, i);
class L {
  /**
   * Construct.
   */
  constructor(e) {
    s(this, "element");
    s(this, "content");
    s(this, "list");
    s(this, "search");
    s(this, "categories");
    s(this, "pager");
    s(this, "pagerPrev");
    s(this, "pagerNext");
    s(this, "infoPages");
    s(this, "iconsAll", []);
    s(this, "icons", []);
    s(this, "limit", 80);
    s(this, "page", 1);
    s(this, "searchTimer");
    s(this, "category", "");
    s(this, "searchQuery", "");
    s(this, "showInfo", !1);
    s(this, "updateInput");
    s(this, "updateInputFormat");
    s(this, "updateAllowEmpty", !1);
    s(this, "updateIcon");
    /**
     * Fetch icon data.
     */
    s(this, "fetchData", async () => {
      let e = drupalSettings.path.baseUrl + "api/icons";
      if (this.element.dataset.libraries) {
        const r = JSON.parse(this.element.dataset.libraries);
        e += "/" + r.join("+");
      }
      return await (await fetch(
        e
      )).json();
    });
    this.element = e, this.content = e.querySelector(".neo-icon-browser--content"), this.list = e.querySelector(".neo-icon-browser--list"), this.search = e.querySelector(".neo-icon-browser--search"), this.categories = e.querySelector(".neo-icon-browser--libraries"), this.pager = e.querySelectorAll(".neo-icon-browser--pager"), this.pagerPrev = e.querySelectorAll(".neo-icon-browser--pager-prev"), this.pagerNext = e.querySelectorAll(".neo-icon-browser--pager-next"), this.infoPages = e.querySelector(".neo-icon-browser--info-pages"), this.showInfo = this.element.dataset.showInfo === "true", this.updateInput = this.element.dataset.updateInput || null, this.updateInputFormat = this.element.dataset.updateInputFormat || "name", this.updateAllowEmpty = !0, this.updateIcon = this.element.dataset.updateIcon || null, this.content.style.display = "none", this.content.classList.remove("hidden"), this.fetchData().then((i) => {
      this.iconsAll = i, this.buildCategories(), this.buildSearch(), this.buildIcons(), this.buildPager();
      const n = this.element.querySelector(".neo-icon-browser--loader");
      n && (n.addEventListener("transitionend", () => {
        n.style.display = "none", this.content.style.display = "block", setTimeout(() => {
          this.content.classList.remove("opacity-0");
        });
      }), n.classList.add("opacity-0"));
    });
  }
  buildCategories() {
    this.categories && this.categories.addEventListener("change", () => {
      this.category = this.categories.value, this.buildIcons();
    });
  }
  buildSearch() {
    this.searchQuery = this.search.value.toString().toLowerCase(), this.search.focus(), this.search.addEventListener("keyup", () => {
      clearTimeout(this.searchTimer), this.searchTimer = setTimeout(() => {
        this.searchQuery = this.search.value.toString().toLowerCase(), this.buildIcons();
      }, 300);
    });
  }
  buildPager() {
    this.pagerPrev.forEach((e) => {
      e.addEventListener("click", (i) => {
        i.preventDefault(), e.classList.contains("disabled") || (this.page--, this.placeIcons());
      });
    }), this.pagerNext.forEach((e) => {
      e.addEventListener("click", (i) => {
        i.preventDefault(), e.classList.contains("disabled") || (this.page++, this.placeIcons());
      });
    }), this.togglePager();
  }
  togglePager() {
    this.limit > this.icons.length ? this.pager.forEach((e) => e.classList.add("hidden")) : this.pager.forEach((e) => e.classList.remove("hidden")), this.page === 1 ? this.pagerPrev.forEach((e) => e.classList.add("disabled")) : this.pagerPrev.forEach((e) => e.classList.remove("disabled")), this.limit * this.page > this.icons.length ? this.pagerNext.forEach((e) => e.classList.add("disabled")) : this.pagerNext.forEach((e) => e.classList.remove("disabled"));
  }
  buildIcons() {
    this.icons = this.iconsAll, this.category !== "" && (this.icons = this.icons.filter((e) => e.library === this.category)), this.searchQuery !== "" && (this.icons = this.icons.filter((e) => e.name.toLowerCase().indexOf(this.searchQuery) > -1)), this.page = 1, this.placeIcons();
  }
  copyToClipboard(e, i, n) {
    e.preventDefault();
    let r = e.target;
    r = r.classList.contains("use-neo-tooltip") ? r : r.closest(".use-neo-tooltip"), r.hasOwnProperty("_tippy") && (r._tippy.setContent("Copied"), r._tippy.show(), navigator.clipboard.writeText(n), setTimeout(() => {
      r._tippy.hide(), setTimeout(() => {
        r._tippy.setContent(i);
      }, 1e3);
    }, 1e3));
  }
  placeIcons() {
    const e = [];
    let i = this.limit;
    if (this.updateInput && this.updateAllowEmpty) {
      let t = this.icons.find((o) => o.name === "ban");
      t && (t = Object.assign({}, t), t.empty = !0, t.render = t.render.replace(t.selector, t.selector + " text-alert-500 opacity-60"), e.push(t), i--);
    }
    const n = i * this.page, r = n - i;
    let u = 0;
    this.icons.forEach((t) => {
      u >= r && u < n && e.push(t), u++;
    }), this.list.innerHTML = "", e.forEach((t) => {
      const o = this.showInfo ? document.createElement("div") : document.createElement("a"), c = this.showInfo ? document.createElement("a") : document.createElement("div");
      if (c.classList.add("neo-icon-browser--icon", "flex", "items-center", "justify-center", "rounded", "h-20", "text-4xl", "bg-base-200", "border", "border-base-300", "text-base-content-200", "w-full", "overflow-hidden", "[&_span:before]:!text-base-content-200"), c.innerHTML = t.render, o.appendChild(c), this.showInfo && !this.updateInput && !this.updateIcon && !t.empty) {
        const h = "Copy Icon Name";
        c.setAttribute("href", "#"), c.classList.add("use-neo-tooltip"), c.setAttribute("data-tippy-content", h), c.setAttribute("data-tippy-delay", "200"), c.addEventListener("click", (l) => {
          this.copyToClipboard(l, h, t.name);
        });
        const p = document.createElement("div");
        p.classList.add("neo-icon-browser--icon-info", "flex", "flex-col", "text-xs", "text-base-content-300", "mt-1"), o.appendChild(p);
        const a = document.createElement("a");
        a.setAttribute("href", "#"), a.classList.add("neo-icon-browser--icon-name", "flex", "text-base", "text-xs"), a.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t("Name") + ':</div> <div class="text-ellipsis overflow-hidden whitespace-nowrap">' + t.name + "</div>", a.classList.add("use-neo-tooltip"), a.setAttribute("data-tippy-content", h), a.setAttribute("data-tippy-delay", "200"), a.addEventListener("click", (l) => {
          this.copyToClipboard(l, h, t.name);
        }), p.appendChild(a);
        const b = document.createElement("div");
        if (b.classList.add("neo-icon-browser--icon-library", "flex"), b.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t("Library") + ":</div> " + t.library, p.appendChild(b), t.hex) {
          const l = document.createElement("a"), f = "Copy Hex Value";
          l.setAttribute("href", "#"), l.classList.add("neo-icon-browser--icon-hex", "flex", "text-base", "text-xs"), l.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t("Hex") + ":</div> " + t.hex, l.classList.add("use-neo-tooltip"), l.setAttribute("data-tippy-content", f), l.setAttribute("data-tippy-delay", "200"), l.addEventListener("click", (g) => {
            this.copyToClipboard(g, f, t.hex);
          }), p.appendChild(l);
        }
      } else
        t.empty && (c.classList.add("opacity-60", "text-alert-500"), o.classList.add("neo-icon-browser--empty"), o.classList.add("use-neo-tooltip"), o.classList.add("use-neo-tooltip"), o.setAttribute("data-tippy-content", "None")), o.setAttribute("href", "#"), o.addEventListener("click", (h) => {
          if (h.preventDefault(), this.element.closest(".neo-modal") && NeoModal.closeTop(), this.updateInput) {
            const a = document.querySelector(this.updateInput);
            if (a) {
              if (t.empty)
                a.value = "";
              else
                switch (this.updateInputFormat) {
                  case "selector":
                    a.value = t.selector;
                    break;
                  case "name":
                  default:
                    a.value = t.name;
                    break;
                }
              a.dispatchEvent(new Event("input", {
                bubbles: !0,
                cancelable: !0
              }));
            }
          }
          if (this.updateIcon) {
            const a = document.querySelector(this.updateIcon);
            a && (a.outerHTML = t.render);
          }
        });
      o.classList.add("neo-icon-browser--item", "bg-base-50", "border", "border-base-300", "rounded-lg", "p-3", "m-1", "flex", "flex-col", "hover:bg-base-100", "focus", "transition-all"), o.tabIndex = 0, o.setAttribute("aria-label", t.name), this.list.appendChild(o);
    });
    const m = this.element.closest(".neo-modal--content-inner");
    m ? m.scrollTo({ top: 0, behavior: "smooth" }) : this.element.scrollIntoView({ behavior: "smooth" }), Drupal.behaviors && Drupal.behaviors.neoTooltip && Drupal.behaviors.neoTooltip.attach(this.element), this.togglePager(), this.buildInfo();
  }
  buildInfo() {
    this.infoPages.innerHTML = Drupal.t("Page <strong>@current</strong> of <strong>@total</strong>", {
      "@current": this.page.toString(),
      "@total": (Math.floor(this.icons.length / this.limit) + 1).toString()
    });
  }
}
(function(d, e) {
  d.behaviors.chatInbox = {
    attach: (i) => {
      e("neo.icon.browser", ".neo-icon-browser", i).forEach((n) => {
        new L(n);
      });
    }
  };
})(Drupal, once);
//# sourceMappingURL=icon.browser.js.map
