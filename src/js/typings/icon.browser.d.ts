// interface ExoIcons {
//   [index: string]: ExoIcon;
// }

interface ExoIcon {
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
