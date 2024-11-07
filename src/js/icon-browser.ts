import { NeoIconBrowser } from './browser/icon-browser';

(function (Drupal, once) {

  Drupal.behaviors.chatInbox = {
    attach: (context:HTMLElement) => {
      once('neo.icon-browser', '.neo-icon-browser', context).forEach(el => {
        new NeoIconBrowser(el);
      });
    }
  };

})(Drupal, once);

export {};
