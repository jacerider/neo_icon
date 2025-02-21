<?php

namespace Drupal\neo_icon;

/**
 * Wrapper methods for \Drupal\neo_icon\IconMimeTrait.
 */
trait IconMimeTrait {

  /**
   * Get an icon for a mime type.
   *
   * @param string $mimeType
   *   The mime type.
   *
   * @return string
   *   The icon id.
   */
  protected function getMimeTypeIcon(string $mimeType) {
    switch ($mimeType) {
      // Image types.
      case 'image/jpeg':
      case 'image/png':
      case 'image/gif':
      case 'image/bmp':
        return 'file-image';

      // Audio types.
      case 'audio/mpeg':
      case 'audio/mp4':
      case 'audio/ogg':
      case 'audio/vnd.wav':
        return 'file-audio';

      // Audio types.
      case 'video/mpeg':
      case 'video/mp4':
      case 'video/ogg':
        return 'file-video';

      // Word document types.
      case 'application/msword':
      case 'application/vnd.ms-word.document.macroEnabled.12':
      case 'application/vnd.oasis.opendocument.text':
      case 'application/vnd.oasis.opendocument.text-template':
      case 'application/vnd.oasis.opendocument.text-master':
      case 'application/vnd.oasis.opendocument.text-web':
      case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
      case 'application/vnd.stardivision.writer':
      case 'application/vnd.sun.xml.writer':
      case 'application/vnd.sun.xml.writer.template':
      case 'application/vnd.sun.xml.writer.global':
      case 'application/vnd.wordperfect':
      case 'application/x-abiword':
      case 'application/x-applix-word':
      case 'application/x-kword':
      case 'application/x-kword-crypt':
        return 'file-word';

      // Spreadsheet document types.
      case 'application/vnd.ms-excel':
      case 'application/vnd.ms-excel.sheet.macroEnabled.12':
      case 'application/vnd.oasis.opendocument.spreadsheet':
      case 'application/vnd.oasis.opendocument.spreadsheet-template':
      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
      case 'application/vnd.stardivision.calc':
      case 'application/vnd.sun.xml.calc':
      case 'application/vnd.sun.xml.calc.template':
      case 'application/vnd.lotus-1-2-3':
      case 'application/x-applix-spreadsheet':
      case 'application/x-gnumeric':
      case 'application/x-kspread':
      case 'application/x-kspread-crypt':
        return 'file-excel';

      // Presentation document types.
      case 'application/vnd.ms-powerpoint':
      case 'application/vnd.ms-powerpoint.presentation.macroEnabled.12':
      case 'application/vnd.oasis.opendocument.presentation':
      case 'application/vnd.oasis.opendocument.presentation-template':
      case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
      case 'application/vnd.stardivision.impress':
      case 'application/vnd.sun.xml.impress':
      case 'application/vnd.sun.xml.impress.template':
      case 'application/x-kpresenter':
        return 'file-powerpoint';

      // Compressed archive types.
      case 'application/zip':
      case 'application/x-zip':
      case 'application/stuffit':
      case 'application/x-stuffit':
      case 'application/x-7z-compressed':
      case 'application/x-ace':
      case 'application/x-arj':
      case 'application/x-bzip':
      case 'application/x-bzip-compressed-tar':
      case 'application/x-compress':
      case 'application/x-compressed-tar':
      case 'application/x-cpio-compressed':
      case 'application/x-deb':
      case 'application/x-gzip':
      case 'application/x-java-archive':
      case 'application/x-lha':
      case 'application/x-lhz':
      case 'application/x-lzop':
      case 'application/x-rar':
      case 'application/x-rpm':
      case 'application/x-tzo':
      case 'application/x-tar':
      case 'application/x-tarz':
      case 'application/x-tgz':
        return 'file-archive';

      // Script file types.
      case 'application/ecmascript':
      case 'application/javascript':
      case 'application/mathematica':
      case 'application/vnd.mozilla.xul+xml':
      case 'application/x-asp':
      case 'application/x-awk':
      case 'application/x-cgi':
      case 'application/x-csh':
      case 'application/x-m4':
      case 'application/x-perl':
      case 'application/x-php':
      case 'application/x-ruby':
      case 'application/x-shellscript':
      case 'text/vnd.wap.wmlscript':
      case 'text/x-emacs-lisp':
      case 'text/x-haskell':
      case 'text/x-literate-haskell':
      case 'text/x-lua':
      case 'text/x-makefile':
      case 'text/x-matlab':
      case 'text/x-python':
      case 'text/x-sql':
      case 'text/x-tcl':
        return 'file-code';

      // HTML aliases.
      case 'application/xhtml+xml':
        return 'file-code';

      // Executable types.
      case 'application/x-macbinary':
      case 'application/x-ms-dos-executable':
      case 'application/x-pef-executable':
        return 'file-exclamation';

      // Acrobat types.
      case 'application/pdf':
      case 'application/x-pdf':
      case 'applications/vnd.pdf':
      case 'text/pdf':
      case 'text/x-pdf':
        return 'file-pdf';

      default:
        return 'file';
    }
  }

}
