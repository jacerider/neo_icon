class y {
  /**
   * Construct.
   */
  constructor(e) {
    this.iconsAll = [], this.icons = [], this.limit = 80, this.page = 1, this.category = "", this.searchQuery = "", this.showInfo = !1, this.updateAllowEmpty = !1, this.fetchData = async () => {
      let i = drupalSettings.path.baseUrl + "api/icons";
      if (this.element.dataset.libraries) {
        const d = JSON.parse(this.element.dataset.libraries);
        i += "/" + d.join("+");
      }
      return await (await fetch(
        i
      )).json();
    }, this.element = e, this.content = e.querySelector(".neo-icon-browser--content"), this.list = e.querySelector(".neo-icon-browser--list"), this.search = e.querySelector(".neo-icon-browser--search"), this.categories = e.querySelector(".neo-icon-browser--libraries"), this.pager = e.querySelectorAll(".neo-icon-browser--pager"), this.pagerPrev = e.querySelectorAll(".neo-icon-browser--pager-prev"), this.pagerNext = e.querySelectorAll(".neo-icon-browser--pager-next"), this.infoPages = e.querySelector(".neo-icon-browser--info-pages"), this.showInfo = this.element.dataset.showInfo === "true", this.updateInput = this.element.dataset.updateInput || null, this.updateInputFormat = this.element.dataset.updateInputFormat || "name", this.updateAllowEmpty = this.element.dataset.updateAllowEmpty === "true", this.updateIcon = this.element.dataset.updateIcon || null, this.content.style.display = "none", this.content.classList.remove("hidden"), this.fetchData().then((i) => {
      this.iconsAll = i, this.buildCategories(), this.buildSearch(), this.buildIcons(), this.buildPager();
      const o = this.element.querySelector(".neo-icon-browser--loader");
      o ? (o.addEventListener("transitionend", () => {
        o.style.display = "none", this.content.style.display = "block", setTimeout(() => {
          this.content.classList.remove("opacity-0"), this.search.focus();
        });
      }), o.classList.add("opacity-0")) : this.search.focus();
    });
  }
  buildCategories() {
    this.categories && this.categories.addEventListener("change", () => {
      this.category = this.categories.value, this.buildIcons();
    });
  }
  buildSearch() {
    this.searchQuery = this.search.value.toString().toLowerCase(), this.search.addEventListener("keyup", () => {
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
  copyToClipboard(e, i, o) {
    e.preventDefault();
    let r = e.target;
    r = r.classList.contains("use-neo-tooltip") ? r : r.closest(".use-neo-tooltip"), r.hasOwnProperty("_tippy") && (r._tippy.setContent("Copied"), r._tippy.show(), navigator.clipboard.writeText(o), setTimeout(() => {
      r._tippy.hide(), setTimeout(() => {
        r._tippy.setContent(i);
      }, 1e3);
    }, 1e3));
  }
  placeIcons() {
    const e = [];
    let i = this.limit;
    if (this.updateInput && this.updateAllowEmpty) {
      let t = this.icons.find((a) => a.name === "ban");
      t && (t = Object.assign({}, t), t.empty = !0, t.render = t.render.replace(t.selector, t.selector + " text-alert-500 opacity-60"), e.push(t), i--);
    }
    const o = i * this.page, r = o - i;
    let d = 0;
    this.icons.forEach((t) => {
      d >= r && d < o && e.push(t), d++;
    }), this.list.innerHTML = "", e.forEach((t) => {
      const a = this.showInfo ? document.createElement("div") : document.createElement("a"), l = this.showInfo ? document.createElement("a") : document.createElement("div");
      if (l.classList.add("neo-icon-browser--icon", "flex", "items-center", "justify-center", "rounded", "h-20", "text-4xl", "bg-base-200", "border", "border-base-300", "text-base-content-200", "w-full", "overflow-hidden", "[&_span:before]:!text-base-content-200"), l.innerHTML = t.render, a.appendChild(l), this.showInfo && !this.updateInput && !this.updateIcon && !t.empty) {
        const c = "Copy Icon Name";
        l.setAttribute("href", "#"), l.classList.add("use-neo-tooltip"), l.setAttribute("data-tippy-content", c), l.setAttribute("data-tippy-delay", "200"), l.addEventListener("click", (n) => {
          this.copyToClipboard(n, c, t.name);
        });
        const h = document.createElement("div");
        h.classList.add("neo-icon-browser--icon-info", "flex", "flex-col", "text-xs", "text-base-content-300", "mt-1"), a.appendChild(h);
        const s = document.createElement("a");
        s.setAttribute("href", "#"), s.classList.add("neo-icon-browser--icon-name", "flex", "text-base", "text-xs"), s.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t("Name") + ':</div> <div class="text-ellipsis overflow-hidden whitespace-nowrap">' + t.name + "</div>", s.classList.add("use-neo-tooltip"), s.setAttribute("data-tippy-content", c), s.setAttribute("data-tippy-delay", "200"), s.addEventListener("click", (n) => {
          this.copyToClipboard(n, c, t.name);
        }), h.appendChild(s);
        const p = document.createElement("div");
        if (p.classList.add("neo-icon-browser--icon-library", "flex"), p.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t("Library") + ":</div> " + t.library, h.appendChild(p), t.hex) {
          const n = document.createElement("a"), m = "Copy Hex Value";
          n.setAttribute("href", "#"), n.classList.add("neo-icon-browser--icon-hex", "flex", "text-base", "text-xs"), n.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t("Hex") + ":</div> " + t.hex, n.classList.add("use-neo-tooltip"), n.setAttribute("data-tippy-content", m), n.setAttribute("data-tippy-delay", "200"), n.addEventListener("click", (f) => {
            this.copyToClipboard(f, m, t.hex);
          }), h.appendChild(n);
        }
      } else
        t.empty && (l.classList.add("opacity-60", "text-alert-500"), a.classList.add("neo-icon-browser--empty"), a.classList.add("use-neo-tooltip"), a.classList.add("use-neo-tooltip"), a.setAttribute("data-tippy-content", "None")), a.setAttribute("href", "#"), a.addEventListener("click", (c) => {
          if (c.preventDefault(), this.element.closest(".neo-modal") && NeoModal.closeTop(), this.updateInput) {
            const s = document.querySelector(this.updateInput);
            if (s) {
              if (t.empty)
                s.value = "";
              else
                switch (this.updateInputFormat) {
                  case "selector":
                    s.value = t.selector;
                    break;
                  case "name":
                  default:
                    s.value = t.name;
                    break;
                }
              s.dispatchEvent(new Event("change")), s.dispatchEvent(new Event("input", {
                bubbles: !0,
                cancelable: !0
              }));
            }
          }
          if (this.updateIcon) {
            const s = document.querySelector(this.updateIcon);
            s && (s.outerHTML = t.render);
          }
        });
      a.classList.add("neo-icon-browser--item", "bg-base-50", "border", "border-base-300", "rounded-lg", "p-3", "m-1", "flex", "flex-col", "hover:bg-base-100", "focus", "transition-all"), a.tabIndex = 0, a.setAttribute("aria-label", t.name), this.list.appendChild(a);
    });
    const b = this.element.closest(".neo-modal--content");
    b ? b.scrollTo({ top: 0, behavior: "smooth" }) : this.content.scrollIntoView({ behavior: "smooth" }), Drupal.behaviors && Drupal.behaviors.neoTooltip && Drupal.behaviors.neoTooltip.attach(this.element), this.togglePager(), this.buildInfo();
  }
  buildInfo() {
    this.infoPages.innerHTML = Drupal.t("Page <strong>@current</strong> of <strong>@total</strong>", {
      "@current": this.page.toString(),
      "@total": (Math.floor(this.icons.length / this.limit) + 1).toString()
    });
  }
}
(function(u, e) {
  u.behaviors.chatInbox = {
    attach: (i) => {
      e("neo.icon-browser", ".neo-icon-browser", i).forEach((o) => {
        new y(o);
      });
    }
  };
})(Drupal, once);
//# sourceMappingURL=icon-browser.js.map
