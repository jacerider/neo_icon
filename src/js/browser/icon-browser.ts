class NeoIconBrowser {
  protected element:HTMLElement;
  protected content:HTMLElement;
  protected list:HTMLElement;
  protected search:HTMLInputElement;
  protected categories:HTMLSelectElement;
  protected pager:NodeListOf<HTMLElement>;
  protected pagerPrev:NodeListOf<HTMLElement>;
  protected pagerNext:NodeListOf<HTMLElement>;
  protected infoPages:HTMLElement;
  protected iconsAll: Array<NeoIcon> = [];
  protected icons: Array<NeoIcon> = [];
  protected limit:number = 80;
  protected page:number = 1;
  protected searchTimer:NodeJS.Timeout|undefined;
  protected category:string = '';
  protected searchQuery:string = '';
  protected showInfo:boolean = false;
  protected updateInput:string|null;
  protected updateInputFormat:string|null;
  protected updateAllowEmpty:boolean = false;
  protected updateIcon:string|null;

  /**
   * Construct.
   */
  constructor(element:HTMLElement) {
    this.element = element;
    this.content = element.querySelector('.neo-icon-browser--content') as HTMLElement;
    this.list = element.querySelector('.neo-icon-browser--list') as HTMLElement;
    this.search = element.querySelector('.neo-icon-browser--search') as HTMLInputElement;
    this.categories = element.querySelector('.neo-icon-browser--libraries') as HTMLSelectElement;
    this.pager = element.querySelectorAll('.neo-icon-browser--pager');
    this.pagerPrev = element.querySelectorAll('.neo-icon-browser--pager-prev');
    this.pagerNext = element.querySelectorAll('.neo-icon-browser--pager-next');
    this.infoPages = element.querySelector('.neo-icon-browser--info-pages') as HTMLElement;
    this.showInfo = this.element.dataset.showInfo === 'true';
    this.updateInput = this.element.dataset.updateInput || null;
    this.updateInputFormat = this.element.dataset.updateInputFormat || 'name';
    // this.updateAllowEmpty = this.element.dataset.updateAllowEmpty === 'true';
    this.updateAllowEmpty = true;
    this.updateIcon = this.element.dataset.updateIcon || null;

    this.content.style.display = 'none';
    this.content.classList.remove('hidden');

    this.fetchData().then(data => {
      this.iconsAll = data;
      this.buildCategories();
      this.buildSearch();
      this.buildIcons();
      this.buildPager();
      const loader = this.element.querySelector('.neo-icon-browser--loader') as HTMLElement;
      if (loader) {
        loader.addEventListener('transitionend', () => {
          loader.style.display = 'none';
          this.content.style.display = 'block';
          setTimeout(() => {
            this.content.classList.remove('opacity-0');
          });
        });
        loader.classList.add('opacity-0');
      }
    });
  }

  /**
   * Fetch icon data.
   */
  protected fetchData = async ():Promise<Array<NeoIcon>> => {
    let url = drupalSettings.path.baseUrl + 'api/icons';
    if (this.element.dataset.libraries) {
      const libraries = JSON.parse(this.element.dataset.libraries) as Array<string>;
      url += '/' + libraries.join('+');
    }
    const source = await fetch(
      url
    );
    const data = await source.json();
    return data;
  }

  protected buildCategories() {
    if (!this.categories) {
      return;
    }
    this.categories.addEventListener('change', () => {
      this.category = this.categories.value;
      this.buildIcons();
    });
  }

  protected buildSearch() {
    this.searchQuery = this.search.value.toString().toLowerCase();
    this.search.focus();
    this.search.addEventListener('keyup', () => {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.searchQuery = this.search.value.toString().toLowerCase();
        this.buildIcons();
      }, 300);
    });
  }

  protected buildPager() {
    this.pagerPrev.forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault();
        if (!item.classList.contains('disabled')) {
          this.page--;
          this.placeIcons();
        }
      });
    });
    this.pagerNext.forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault();
        if (!item.classList.contains('disabled')) {
          this.page++;
          this.placeIcons();
        }
      });
    });
    this.togglePager();
  }

  protected togglePager() {
    if (this.limit > this.icons.length) {
      this.pager.forEach(item => item.classList.add('hidden'));
    }
    else {
      this.pager.forEach(item => item.classList.remove('hidden'));
    }
    if (this.page === 1) {
      this.pagerPrev.forEach(item => item.classList.add('disabled'));
    }
    else {
      this.pagerPrev.forEach(item => item.classList.remove('disabled'));
    }
    if (this.limit * this.page > this.icons.length) {
      this.pagerNext.forEach(item => item.classList.add('disabled'));
    }
    else {
      this.pagerNext.forEach(item => item.classList.remove('disabled'));
    }
  }

  protected buildIcons() {
    this.icons = this.iconsAll;
    if (this.category !== '') {
      this.icons = this.icons.filter(icon => {
        return icon.library === this.category;
      });
    }
    if (this.searchQuery !== '') {
      this.icons = this.icons.filter(icon => {
        return icon.name.toLowerCase().indexOf(this.searchQuery) > -1;
      });
    }
    this.page = 1;
    this.placeIcons();
  }

  protected copyToClipboard(e:Event, text:string, value:string) {
    e.preventDefault();
    let target = e.target as HTMLTippyElement;
    target = target.classList.contains('use-neo-tooltip') ? target : target.closest('.use-neo-tooltip') as HTMLTippyElement;
    if (target.hasOwnProperty('_tippy')) {
      target._tippy.setContent('Copied');
      target._tippy.show();
      navigator.clipboard.writeText(value);
      setTimeout(() => {
        target._tippy.hide();
        setTimeout(() => {
          target._tippy.setContent(text);
        }, 1000);
      }, 1000);
    }
  }

  protected placeIcons() {
    const icons:NeoIcon[] = [];
    let limit = this.limit;
    if (this.updateInput && this.updateAllowEmpty) {
      let empty = this.icons.find(icon => {
        return icon.name === 'ban';
      });
      if (empty) {
        empty = Object.assign({}, empty);
        empty.empty = true;
        empty.render = empty.render.replace(empty.selector, empty.selector + ' text-alert-500 opacity-60');
        icons.push(empty);
        limit--;
      }
    }
    const max = limit * this.page;
    const min = max - limit;
    let count = 0;
    this.icons.forEach(icon => {
      if (count >= min && count < max) {
        icons.push(icon);
      }
      count++;
    });

    this.list.innerHTML = '';
    icons.forEach(icon => {
      const item = this.showInfo ? document.createElement('div') : document.createElement('a');
      const itemIcon = this.showInfo ? document.createElement('a') : document.createElement('div');
      itemIcon.classList.add('neo-icon-browser--icon', 'flex', 'items-center', 'justify-center', 'rounded', 'h-20', 'text-4xl', 'bg-base-200', 'border', 'border-base-300', 'text-base-content-200', 'w-full', 'overflow-hidden', '[&_span:before]:!text-base-content-200');
      itemIcon.innerHTML = icon.render;
      item.appendChild(itemIcon);
      if (this.showInfo && !this.updateInput && !this.updateIcon && !icon.empty) {
        const itemIconContent = 'Copy Icon Name';
        itemIcon.setAttribute('href', '#');
        itemIcon.classList.add('use-neo-tooltip');
        itemIcon.setAttribute('data-tippy-content', itemIconContent);
        itemIcon.setAttribute('data-tippy-delay', '200');
        itemIcon.addEventListener('click', e => {
          this.copyToClipboard(e, itemIconContent, icon.name);
        });

        const itemInfo = document.createElement('div');
        itemInfo.classList.add('neo-icon-browser--icon-info', 'flex', 'flex-col', 'text-xs', 'text-base-content-300', 'mt-1');
        item.appendChild(itemInfo);

        const itemName = document.createElement('a');
        itemName.setAttribute('href', '#');
        itemName.classList.add('neo-icon-browser--icon-name', 'flex', 'text-base', 'text-xs');
        itemName.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t('Name') + ':</div> <div class="text-ellipsis overflow-hidden whitespace-nowrap">' + icon.name + '</div>';
        itemName.classList.add('use-neo-tooltip');
        itemName.setAttribute('data-tippy-content', itemIconContent);
        itemName.setAttribute('data-tippy-delay', '200');
        itemName.addEventListener('click', e => {
          this.copyToClipboard(e, itemIconContent, icon.name);
        });
        itemInfo.appendChild(itemName);

        const itemLibrary = document.createElement('div');
        itemLibrary.classList.add('neo-icon-browser--icon-library', 'flex');
        itemLibrary.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t('Library') + ':</div> ' + icon.library;
        itemInfo.appendChild(itemLibrary);

        if (icon.hex) {
          const itemHex = document.createElement('a');
          const itemHexContent = 'Copy Hex Value';
          itemHex.setAttribute('href', '#');
          itemHex.classList.add('neo-icon-browser--icon-hex', 'flex', 'text-base', 'text-xs');
          itemHex.innerHTML = '<div class="mr-1 opacity-60">' + Drupal.t('Hex') + ':</div> ' + icon.hex;
          itemHex.classList.add('use-neo-tooltip');
          itemHex.setAttribute('data-tippy-content', itemHexContent);
          itemHex.setAttribute('data-tippy-delay', '200');
          itemHex.addEventListener('click', e => {
            this.copyToClipboard(e, itemHexContent, icon.hex);
          });
          itemInfo.appendChild(itemHex);
        }
      }
      else {
        if (icon.empty) {
          itemIcon.classList.add('opacity-60', 'text-alert-500');
          item.classList.add('neo-icon-browser--empty');
          item.classList.add('use-neo-tooltip');
          item.classList.add('use-neo-tooltip');
          item.setAttribute('data-tippy-content', 'None');
        }
        item.setAttribute('href', '#');
        item.addEventListener('click', e => {
          e.preventDefault();

          const modal = this.element.closest('.neo-modal');
          if (modal) {
            NeoModal.closeTop();
          }
          if (this.updateInput) {
            const element = document.querySelector(this.updateInput) as HTMLInputElement;
            if (element) {
              if (icon.empty) {
                element.value = '';
              }
              else {
                switch (this.updateInputFormat) {
                  case 'selector':
                    element.value = icon.selector;
                    break;
                  case 'name':
                  default:
                    element.value = icon.name;
                    break;
                }
              }
              element.dispatchEvent(new Event('input', {
                bubbles: true,
                cancelable: true,
              }));
            }
          }
          if (this.updateIcon) {
            const element = document.querySelector(this.updateIcon) as HTMLInputElement;
            if (element) {
              element.outerHTML = icon.render;
            }
          }
        });
      }
      item.classList.add('neo-icon-browser--item', 'bg-base-50', 'border', 'border-base-300', 'rounded-lg', 'p-3', 'm-1', 'flex', 'flex-col', 'hover:bg-base-100', 'focus', 'transition-all');
      item.tabIndex = 0;
      item.setAttribute('aria-label', icon.name);
      this.list.appendChild(item);
    });
    const modal = this.element.closest('.neo-modal--content-inner');
    if (modal) {
      modal.scrollTo({top: 0, behavior: 'smooth'});
    }
    else {
      this.element.scrollIntoView({behavior: 'smooth'});
    }
    if (Drupal.behaviors && Drupal.behaviors.neoTooltip) {
      Drupal.behaviors.neoTooltip.attach(this.element);
    }
    this.togglePager();
    this.buildInfo();
  }

  protected buildInfo() {
    this.infoPages.innerHTML = Drupal.t('Page <strong>@current</strong> of <strong>@total</strong>', {
      '@current': this.page.toString(),
      '@total': ((Math.floor(this.icons.length / this.limit) + 1)).toString(),
    });
  }

}

export {NeoIconBrowser};
