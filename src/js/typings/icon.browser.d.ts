interface NeoIcon {
  name: string;
  hex: string;
  selector: string;
  library: string;
  render: string;
  empty: ?boolean;
}

interface HTMLTippyElement extends HTMLElement {
  _tippy: any;
}
