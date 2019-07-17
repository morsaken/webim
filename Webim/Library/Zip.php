<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Zip {

  /*
   * zipfile class, for reading or writing .zip files See http://www.gamingg.net
   * for more of my work Based on tutorial given by John Coggeshall at
   * http://www.zend.com/zend/spotlight/creating-zip-files3.php Copyright (C)
   * Joshua Townsend and licensed under the GPL Version 1.0
   */
  private $datasec = array(); // array to store compressed data
  private $dirs = array(); // array of directories that have been created

  // already
  private $ctrlDir = array(); // central directory
  private $eofCtrlDir = "\x50\x4b\x05\x06\x00\x00\x00\x00"; // end of Central

  // directory record
  private $oldOffset = 0;
  private $baseDir = '.';

  /**
   * Read zipped file
   *
   * @param Webim\Library\File $file
   *
   * @return array
   */
  public function read(File $file) {
    // Clear current file
    $this->datasec = array();

    if (!$file->exists() || !$file->isFile()) {
      return array();
    }

    // File information
    $uncompressed = array(
      'name' => $file->name,
      'modified' => $file->modified,
      'size' => $file->size(),
      'comment' => '',
      'files' => array()
    );

    // Read file
    $fh = fopen($file->getPath(), 'rb');
    $filedata = fread($fh, $uncompressed['size']);
    fclose($fh);

    // Break into sections
    $filesecta = explode("\x50\x4b\x05\x06", $filedata);

    // ZIP Comment
    $unpackeda = unpack("x16/v1length", $filesecta[1]);
    $uncompressed['comment'] = substr($filesecta[1], 18, $unpackeda['length']);
    $uncompressed['comment'] = str_replace(array(
      "\r\n",
      "\r"
    ), "\n", $uncompressed['comment']);

    // Cut entries from the central directory
    $filesecta = explode("\x50\x4b\x01\x02", $filedata);
    $filesecta = explode("\x50\x4b\x03\x04", $filesecta[0]);
    array_shift($filesecta); // Removes empty entry/signature

    foreach ($filesecta as $filedata) {
      // CRC:crc, FD:file date, FT: file time, CM: compression method,
      // GPF: general purpose flag, VN: version needed, CS: compressed
      // size, UCS: uncompressed size, FNL: filename length
      $entrya = array();
      $entrya['error'] = '';

      $unpackeda = unpack("v1version/v1general_purpose/v1compress_method/v1file_time/v1file_date/V1crc/V1size_compressed/V1size_uncompressed/v1filename_length", $filedata);

      // Check for encryption
      $isencrypted = (($unpackeda['general_purpose'] & 0x0001) ? true : false);

      // Check for value block after compressed data
      if ($unpackeda['general_purpose'] & 0x0008) {
        $unpackeda2 = unpack("V1crc/V1size_compressed/V1size_uncompressed", substr($filedata, -12));

        $unpackeda['crc'] = $unpackeda2['crc'];
        $unpackeda['size_compressed'] = $unpackeda2['size_uncompressed'];
        $unpackeda['size_uncompressed'] = $unpackeda2['size_uncompressed'];

        unset($unpackeda2);
      }

      $entrya['name'] = substr($filedata, 26, $unpackeda['filename_length']);

      if (substr($entrya['name'], -1) == '/') { // skip directories
        continue;
      }

      $entrya['dir'] = dirname($entrya['name']);
      $entrya['dir'] = ($entrya['dir'] == '.' ? '' : $entrya['dir']);
      $entrya['name'] = basename($entrya['name']);

      $filedata = substr($filedata, 26 + $unpackeda['filename_length']);

      if (strlen($filedata) != $unpackeda['size_compressed']) {
        $entrya['error'] = 'Compressed size is not equal to the value given in header.';
      }

      if ($isencrypted) {
        $entrya['error'] = 'Encryption is not supported.';
      } else {
        switch ($unpackeda['compress_method']) {
          case 0 : // Stored
            // Not compressed, continue
            break;
          case 8 : // Deflated
            $filedata = gzinflate($filedata);
            break;
          case 12 : // BZIP2
            if (!extension_loaded('bz2')) {
              @dl((strtolower(substr(PHP_OS, 0, 3)) == 'win') ? 'php_bz2.dll' : 'bz2.so');
            }

            if (extension_loaded('bz2')) {
              $filedata = bzdecompress($filedata);
            } else {
              $entrya['error'] = 'Required BZIP2 Extension not available.';
            }
            break;
          default :
            $entrya['error'] = "Compression method ({$unpackeda["compress_method"]}) not supported.";
        }

        if (!$entrya['error']) {
          if ($filedata === false) {
            $entrya['error'] = 'Decompression failed.';
          } elseif (strlen($filedata) != $unpackeda['size_uncompressed']) {
            $entrya['error'] = 'File size is not equal to the value given in header.';
          } elseif (crc32($filedata) != $unpackeda['crc']) {
            $entrya['error'] = 'CRC32 checksum is not equal to the value given in header.';
          }
        }

        $entrya['filemtime'] = mktime(($unpackeda['file_time'] & 0xf800) >> 11, ($unpackeda['file_time'] & 0x07e0) >> 5, ($unpackeda['file_time'] & 0x001f) << 1, ($unpackeda['file_date'] & 0x01e0) >> 5, ($unpackeda['file_date'] & 0x001f), (($unpackeda['file_date'] & 0xfe00) >> 9) + 1980);
        $entrya['data'] = $filedata;
      }

      $uncompressed['files'][] = $entrya;
    }

    return $uncompressed;
  }

  /**
   * Add file / folder
   *
   * @param Webim\Library\File $file
   * @param string $root
   * @param null|string $fileName
   *
   * @return bool
   */
  public function add(File $file, $root = '', $fileName = null) {
    //Trim first
    $root = trim($root, '/');

    if (strlen($root) > 0) {
      $root .= '/';
    }

    // File or folder
    if ($file->isFile()) {
      $this->createFile($file->content(), $root . ($fileName ? $fileName : $file->baseName));
    } else {
      foreach ($file->folders() as $subfolder) {
        $this->createDir($root . $subfolder->baseName);

        $this->add($subfolder, $root . $subfolder->baseName);
      }

      foreach ($file->files() as $subfile) {
        $this->add($subfile, $root);
      }
    }

    return true;
  }

  /**
   * Create file
   *
   * @param string $data
   * @param string $name
   */
  public function createFile($data, $name) {
    // Adds a file to the path
    // specified by $name with the
    // contents $data
    $name = str_replace('\\', '/', $name);

    $fr = "\x50\x4b\x03\x04";
    $fr .= "\x14\x00"; // version needed to extract
    $fr .= "\x00\x00"; // general purpose bit flag
    $fr .= "\x08\x00"; // compression method
    $fr .= "\x00\x00\x00\x00"; // last mod time and date

    $unc_len = strlen($data);
    $crc = crc32($data);
    $zdata = gzcompress($data);
    $zdata = substr($zdata, 2, -4); // fix crc bug
    $c_len = strlen($zdata);
    $fr .= pack('V', $crc); // crc32
    $fr .= pack('V', $c_len); // compressed filesize
    $fr .= pack('V', $unc_len); // uncompressed filesize
    $fr .= pack('v', strlen($name)); // length of filename
    $fr .= pack('v', 0); // extra field length
    $fr .= iconv('UTF-8', 'CP852', $name);
    // end of 'local file header' segment

    // 'file data' segment
    $fr .= $zdata;

    // 'data descriptor' segment (optional but necessary if archive is not
    // served as file)
    $fr .= pack('V', $crc); // crc32
    $fr .= pack('V', $c_len); // compressed filesize
    $fr .= pack('V', $unc_len); // uncompressed filesize

    // add this entry to array
    $this->datasec[] = $fr;

    $new_offset = strlen(implode('', $this->datasec));

    // now add to central directory record
    $cdrec = "\x50\x4b\x01\x02";
    $cdrec .= "\x00\x00"; // version made by
    $cdrec .= "\x14\x00"; // version needed to extract
    $cdrec .= "\x00\x00"; // general purpose bit flag
    $cdrec .= "\x08\x00"; // compression method
    $cdrec .= "\x00\x00\x00\x00"; // last mod time & date
    $cdrec .= pack('V', $crc); // crc32
    $cdrec .= pack('V', $c_len); // compressed filesize
    $cdrec .= pack('V', $unc_len); // uncompressed filesize
    $cdrec .= pack('v', strlen($name)); // length of filename
    $cdrec .= pack('v', 0); // extra field length
    $cdrec .= pack('v', 0); // file comment length
    $cdrec .= pack('v', 0); // disk number start
    $cdrec .= pack('v', 0); // internal file attributes
    $cdrec .= pack('V', 32); // external file attributes - 'archive' bit set

    $cdrec .= pack('V', $this->oldOffset); // relative offset of local header
    $this->oldOffset = $new_offset;

    $cdrec .= $name;
    // optional extra field, file comment goes here
    // save to central directory
    $this->ctrlDir[] = $cdrec;
  }

  /**
   * Create folder
   *
   * @param string $name
   */
  public function createDir($name) {
    // Adds a directory to the zip with the
    // name $name
    $name = str_replace('\\', '/', $name);

    $fr = "\x50\x4b\x03\x04";
    $fr .= "\x0a\x00"; // version needed to extract
    $fr .= "\x00\x00"; // general purpose bit flag
    $fr .= "\x00\x00"; // compression method
    $fr .= "\x00\x00\x00\x00"; // last mod time and date

    $fr .= pack('V', 0); // crc32
    $fr .= pack('V', 0); // compressed filesize
    $fr .= pack('V', 0); // uncompressed filesize
    $fr .= pack('v', strlen($name)); // length of pathname
    $fr .= pack('v', 0); // extra field length
    $fr .= $name;
    // end of 'local file header' segment

    // no 'file data' segment for path

    // 'data descriptor' segment (optional but necessary if archive is not
    // served as file)
    $fr .= pack('V', 0); // crc32
    $fr .= pack('V', 0); // compressed filesize
    $fr .= pack('V', 0); // uncompressed filesize

    // add this entry to array
    $this->datasec[] = $fr;

    $new_offset = strlen(implode('', $this->datasec));

    // ext. file attributes mirrors MS-DOS directory attr byte, detailed
    // at http://support.microsoft.com/support/kb/articles/Q125/0/19.asp

    // now add to central record
    $cdrec = "\x50\x4b\x01\x02";
    $cdrec .= "\x00\x00"; // version made by
    $cdrec .= "\x0a\x00"; // version needed to extract
    $cdrec .= "\x00\x00"; // general purpose bit flag
    $cdrec .= "\x00\x00"; // compression method
    $cdrec .= "\x00\x00\x00\x00"; // last mod time and date
    $cdrec .= pack('V', 0); // crc32
    $cdrec .= pack('V', 0); // compressed filesize
    $cdrec .= pack('V', 0); // uncompressed filesize
    $cdrec .= pack('v', strlen($name)); // length of filename
    $cdrec .= pack('v', 0); // extra field length
    $cdrec .= pack('v', 0); // file comment length
    $cdrec .= pack('v', 0); // disk number start
    $cdrec .= pack('v', 0); // internal file attributes
    $cdrec .= pack('V', 16); // external file attributes - 'directory' bit set

    $cdrec .= pack('V', $this->oldOffset); // relative offset of local header
    $this->oldOffset = $new_offset;

    $cdrec .= $name;
    // optional extra field, file comment goes here
    // save to array
    $this->ctrlDir[] = $cdrec;
    $this->dirs[] = $name;
  }

  /**
   * Make file
   *
   * @return string
   */
  public function make() {
    // return zipped file contents
    $data = implode('', $this->datasec);
    $ctrldir = implode('', $this->ctrlDir);

    return $data
      . $ctrldir
      . $this->eofCtrlDir
      . pack('v', sizeof($this->ctrlDir)) //total number of entries "on this disk"
      . pack('v', sizeof($this->ctrlDir)) //total number of entries overall
      . pack('V', strlen($ctrldir)) //size of central dir
      . pack('V', strlen($data)) //offset to start of central dir
      . "\x00\x00"; // .zip file comment length
  }

}