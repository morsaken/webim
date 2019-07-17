<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

use Webim\Http\Request;

class File {

  /**
   * Document root
   *
   * @var string
   */
  protected static $root = '/';

  /**
   * Global PHP file extension
   *
   * @var string
   */
  protected static $ext = '.php';

  /**
   * Path
   *
   * @var string
   */
  protected $path;

  /**
   * File options
   *
   * @var array
   */
  private $options = array(
    'fileIn' => array(),
    'folderIn' => array(),
    'fileNotIn' => array(),
    'folderNotIn' => array(),
    'sort' => 'asc',
    'overwrite' => false
  );

  /**
   * Constructor
   *
   * @param string|Webim\Library\File $dir
   * @param null|string $file
   */
  public function __construct($dir, $file = null) {
    $path = $this->transformDir($dir);

    if (!is_null($file)) {
      $path .= $file;
    }

    $this->path = $path;
  }

  /**
   * Transform directory
   *
   * @param $dir
   *
   * @return string
   */
  protected static function transformDir($dir) {
    if (strpos($dir, static::getRoot()) === 0) {
      //Remove document root
      $dir = str_replace(static::getRoot(), '', $dir);
    }

    if (str_contains($dir, '.')) {
      $dir = str_replace('.', DIRECTORY_SEPARATOR, $dir);
    }

    $dir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, rtrim($dir, '/\\'));

    return static::getRoot() . $dir . DIRECTORY_SEPARATOR;
  }

  /**
   * Returns root path
   *
   * @return string
   */
  public static function getRoot() {
    return static::$root;
  }

  /**
   * Set root
   *
   * @param string $root
   */
  public static function setRoot($root = '/') {
    static::$root = $root;
  }

  /**
   * Initialization
   *
   * @param string|Webim\Library\File $dir
   *
   * @return static
   */
  public static function in($dir) {
    return new static($dir);
  }

  /**
   * Set global php file extension
   *
   * @param string $ext
   */
  public static function setGlobalPHPFileExt($ext = '.php') {
    static::$ext = $ext;
  }

  /**
   * Download
   *
   * @param string $name
   * @param bool $zipped
   * @param array $files
   *
   * @return bool|object
   */
  public function download($name = 'file', $zipped = false, $files = array()) {
    if ($this->exists()) {
      if ($zipped) {
        //File name
        $name = $name . '.zip';

        //Create zip instance
        $zip = new Zip();
        $zip->add($this);

        foreach ($files as $file) {
          if ($file instanceof File) {
            $zip->add($file);
          }
        }

        $content = $zip->make();
      } else {
        //File name
        $name = $name . '.' . $this->extension();

        $content = $this->content();
      }

      $download = new \stdClass();
      $download->name = $name;
      $download->content = $content;
      $download->headers = array(
        'Pragma' => 'public',
        'Expires' => 0,
        'Cache-Control' => 'private, must-revalidate, post-check=0, pre-check=0',
        'Content-Type' => 'application/octet-stream',
        'Content-Transfer-Encoding' => 'binary',
        'Content-Disposition' => 'attachment; filename*=UTF-8\'\'' . urlencode($name),
        'Content-Length' => strlen($content)
      );

      return $download;
    }

    return false;
  }

  /**
   * Checks path existence
   *
   * @return bool
   */
  public function exists() {
    return file_exists($this->path);
  }

  /**
   * Current file extension
   *
   * @param bool $pointless
   *
   * @return null|string
   */
  public function extension($pointless = true) {
    if ($this->exists()) {
      return (!$pointless && strlen($this->info('extension')) ? '.' : '') . $this->info('extension');
    }

    return null;
  }

  /**
   * Info of current path
   *
   * @param null|string $key
   *
   * @return \stdClass|mixed
   */
  public function info($key = null) {
    //Return
    $info = new \stdClass();

    //Default type is unknown
    $info->type = null;

    if ($this->exists()) {
      $info->type = filetype($this->path);
      $info->name = pathinfo($this->path, PATHINFO_FILENAME);
      $info->baseName = pathinfo($this->path, PATHINFO_BASENAME);
      $info->dir = pathinfo($this->path, PATHINFO_DIRNAME);
      $info->baseDir = str_replace(static::$root, '', $info->dir);
      $info->extension = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
      $info->path = $this->path;
      $info->basePath = str_replace(static::$root, '', $this->path);
      $info->rawPath = str_replace(DIRECTORY_SEPARATOR, '.', rtrim((($info->type == 'file') ? $info->baseDir : $info->basePath), DIRECTORY_SEPARATOR));
      $info->source = str_replace(DIRECTORY_SEPARATOR, '/', $info->basePath);
      $info->accessed = fileatime($this->path);
      $info->modified = filemtime($this->path);
      $info->owner = fileowner($this->path);
      $info->perms = fileperms($this->path);
      $info->writable = is_writable($this->path);
    }

    return (!is_null($key) ? object_get($info, $key) : $info);
  }

  /**
   * Content of current file
   *
   * @return null|string
   */
  public function content() {
    $content = null;

    if ($this->exists() && $this->isFile()) {
      $content = @file_get_contents($this->path);
    }

    return $content;
  }

  /**
   * Checks current path is file?
   *
   * @return bool
   */
  public function isFile() {
    return $this->exists() && ('file' == $this->info('type'));
  }

  /**
   * Upload file
   *
   * @param array $incoming
   * @param array $name
   *
   * @return object
   *
   * @throws \LogicException
   */
  public function upload(array $incoming, $name = null) {
    //Upload errors
    $errors = array(
      0 => 'Upload done successfully',
      1 => 'UPLOAD_MAX_FILESIZE (%s) limit exceeded according to php.ini settings',
      2 => 'Upload limit exceeded according to HTML form settings',
      3 => 'A part of file uploaded',
      4 => 'No upload done',
      6 => 'Temporary folder not found'
    );

    if (!isset($incoming['tmp_name'])) {
      throw new \LogicException('Invalid file');
    }

    if (!$this->isFolder()) {
      throw new \LogicException('Invalid upload folder: ' . $this->getPath());
    }

    //Ini settings
    $postMaxSize = ini_get('post_max_size');
    $unit = strtoupper(substr($postMaxSize, -1));
    $multiplier = ($unit == 'M' ? 1048576 : ($unit == 'K' ? 1024 : ($unit == 'G' ? 1073741824 : 1)));

    if (($multiplier * (int)$postMaxSize) < Request::current()->header('Content-Length', 0)) {
      throw new \LogicException(sprintf($errors[1], $postMaxSize));
    }

    //File size
    $fileSize = isset($incoming['size']) ? $incoming['size'] : @filesize($incoming['tmp_name']);

    if (!$fileSize) {
      throw new \LogicException('File size must be greater than zero');
    }

    if (isset($incoming['error']) && $incoming['error']) {
      throw new \LogicException(array_get($errors, $incoming['error'], 'Upload error'));
    }

    if (!isset($incoming['tmp_name']) || ($error = !@is_uploaded_file($incoming['tmp_name']))) {
      throw new \LogicException($error);
    }

    if (!isset($incoming['name'])) {
      throw new \LogicException('Invalid file name');
    }

    //Valid file name
    $validChars = '.A-Z0-9_-~@#$%&()+={}\[\]'; //Valid chars

    //File name
    $fileName = basename($incoming['name']);

    //Split extension from file name
    $extension = strrchr($fileName, '.');

    //Remove extension and get base name
    $baseName = str_replace($extension, '', $fileName);

    //Remove dot from extension
    $extension = strtolower($extension);

    if (strlen($name)) {
      //Change base name
      $baseName = $name;
    }

    //Remove unexpected characters and add extension to make a clear name
    $fileName = strtolower(preg_replace('/[^' . $validChars . ']|\.+$/i', '_', $baseName)) . $extension;

    //Remove dot from extension
    $extension = trim($extension, '.');

    //Set as file
    $file = $this->file($fileName);

    if (!$file->checkName($fileName)) {
      throw new \LogicException('Invalid file extension: ' . $extension);
    }

    if ((array_get($this->options, 'overwrite', false) == false) && $file->exists()) {
      throw new \LogicException('File already exists: ' . $file->name);
    }

    //Remove if exists
    if ($file->exists()) {
      @unlink($file->getPath());
    }

    if (!@move_uploaded_file($incoming['tmp_name'], $file->getPath())) {
      throw new \LogicException('Uploaded file cannot move to location: ' . $file->getPath());
    }

    $upload = new \stdClass();
    $upload->basename = $baseName;
    $upload->extension = $extension;
    $upload->name = $fileName;
    $upload->file = $file;

    return $upload;
  }

  /**
   * Checks current path is directory
   *
   * @return bool
   */
  public function isFolder() {
    return $this->exists() && ('dir' == $this->info('type'));
  }

  /**
   * Returns current path
   *
   * @return string
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * Set file after path
   *
   * @param string $name
   *
   * @return File|$this
   */
  public function file($name) {
    if ($this->isFolder()) {
      return static::path($this->path . str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $name))->opts($this->options);
    }

    return $this;
  }

  /**
   * Set options
   *
   * @param array $opts
   *
   * @return $this
   */
  public function opts($opts = array()) {
    foreach ($opts as $key => $value) {
      $this->opt($key, $value);
    }

    return $this;
  }

  /**
   * Set option
   *
   * @param string $key
   * @param mixed $value
   *
   * @return $this
   */
  public function opt($key, $value) {
    if (isset($this->options[$key])) {
      if (is_array($this->options[$key]) && is_scalar($value)) {
        $this->options[$key][] = $value;
      } else {
        $this->options[$key] = $value;
      }
    }

    return $this;
  }

  /**
   * Start file with a path and a file
   *
   * @param string|Webim\Library\File $path
   * @param null|string $file
   *
   * @return static
   */
  public static function path($path, $file = null) {
    if ($path instanceof File || !is_null($file)) {
      return new static($path, $file);
    }

    $file = strrchr($path, '/');

    if (strlen($file) === 0) {
      $file = strrchr($path, '\\');
    }

    if (strpos($file, '.') !== false) {
      $path = str_replace($file, '', $path);
      $file = trim($file, '/\\');
    } else {
      $file = null;
    }

    return new static($path, $file);
  }

  /**
   * Checks current paths name comparison
   *
   * @param null|string $name
   *
   * @return bool
   */
  private function checkName($name = null) {
    //Checked is default false
    $checked = false;

    //While uploading, file is not exists, so we can get name as a parameter
    if (is_null($name)) {
      //File name
      $name = $this->info('baseName');
    }

    //Filters
    if ($this->isFolder()) {
      $in = array_get($this->options, 'folderIn', array());
      $notIn = array_get($this->options, 'folderNotIn', array());
    } else {
      $in = array_get($this->options, 'fileIn', array());
      $notIn = array_get($this->options, 'fileNotIn', array());
    }

    if (count($in) || count($notIn)) {
      if (!count($in)) {
        //If nothing set as checked
        $checked = true;
      } else {
        foreach ($in as $filter) {
          //Eg: *.*, test*.gif, *.jpe?g
          $pattern = '/^' . str_replace(array(
              '.', '*'
            ), array(
              '\.', '.*'
            ), $filter) . '$/i';

          if (!$checked && preg_match($pattern, $name)) {
            //Checked
            $checked = true;

            break;
          }
        }
      }

      foreach ($notIn as $filter) {
        //Eg: *.*, test*.gif, *.jpe?g
        $pattern = '/^' . str_replace(array(
            '.', '*'
          ), array(
            '\.', '.*'
          ), $filter) . '$/i';

        if ($checked && preg_match($pattern, $name)) {
          //Remove
          $checked = false;

          break;
        }
      }
    } else {
      //If nothing, checked
      $checked = true;
    }

    return $checked;
  }

  /**
   * Set folder after path
   *
   * @param null|string $name
   *
   * @return File
   */
  public function folder($name = null) {
    if ($this->isFolder() && !is_null($name)) {
      $file = static::path($this->path . trim(str_replace('.', DIRECTORY_SEPARATOR, $name), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    } else {
      $file = static::path($this->info('dir') . DIRECTORY_SEPARATOR);
    }

    return $file->opts($this->options);
  }

  /**
   * Synonym to all files and folders
   *
   * @return array
   */
  public function all() {
    return $this->allFilesAndFolders();
  }

  /**
   * All files and folders
   *
   * @return array
   */
  public function allFilesAndFolders() {
    return ($this->allFolders() + $this->allFiles());
  }

  /**
   * All folders recursively
   *
   * @return array
   */
  public function allFolders() {
    //Return
    $folders = $this->folders();

    foreach ($folders as $folder) {
      $folders += $folder->folders();
    }

    return $folders;
  }

  /**
   * Folder list
   *
   * @return array
   */
  public function folders() {
    return $this->lists('dir');
  }

  /**
   * Current path list
   *
   * @param string $type
   *
   * @return array
   */
  private function lists($type = 'file') {
    //Return
    $list = array();

    if ($this->exists()) {
      //All files
      $tmp = array();

      if ($this->info('type') == 'file') {
        $tmp[$this->info('path')] = $this;
      } else {
        clearstatcache();

        //Handle
        $handle = @opendir($this->info('path'));

        while (false !== ($file_name = @readdir($handle))) {
          if (!in_array($file_name, array('.', '..'))) {
            //New path
            $path = rtrim($this->info('path'), '/\\') . DIRECTORY_SEPARATOR . $file_name;

            //File
            $file = static::path($path)->opts($this->options);

            if ($file->info('type') == $type) {
              //Add to list
              $tmp[$path] = $file;
            }
          }
        }

        @closedir($handle);
      }

      //Filtered
      $list = $this->filter($tmp);
    }

    if (array_get($this->options, 'sort', 'asc') == 'asc') {
      asort($list);
    } else {
      krsort($list);
    }

    reset($list);

    return $list;
  }

  /**
   * Filter current path files and folders
   *
   * @param array $list
   *
   * @return array
   */
  private function filter($list = array()) {
    //Return
    $filtered = array();

    foreach ($list as $path => $file) {
      if ($file->checkName()) {
        //To list
        $filtered[$path] = $file;
      }
    }

    return $filtered;
  }

  /**
   * All files recursively
   *
   * @return array
   */
  public function allFiles() {
    //Current path files
    $files = $this->files();

    foreach ($this->allFolders() as $folder) {
      $files += $folder->files();
    }

    return $files;
  }

  /**
   * File list
   *
   * @return array
   */
  public function files() {
    return $this->lists('file');
  }

  /**
   * Checks current path has children
   *
   * @return bool
   */
  public function hasChildren() {
    return $this->isFolder() && (count($this->children()) > 0);
  }

  /**
   * Current paths children
   *
   * @return array
   */
  public function children() {
    return $this->filesAndFolders();
  }

  /**
   * Files and folders list
   *
   * @return array
   */
  public function filesAndFolders() {
    return ($this->folders() + $this->files());
  }

  /**
   * Folder up
   *
   * @return File|$this
   */
  public function up() {
    if ($this->exists()) {
      //Current
      $path = $this->info('path');

      if ($this->isFile()) {
        $path = $this->info('dir');
      }

      //Explode folders
      $segment = explode(
        DIRECTORY_SEPARATOR,
        str_replace(static::$root, '', rtrim($path, DIRECTORY_SEPARATOR))
      );

      //Remove last child
      array_pop($segment);

      //Add doc root
      array_unshift($segment, rtrim(static::$root, DIRECTORY_SEPARATOR));

      //Return new file
      return static::path(implode(DIRECTORY_SEPARATOR, $segment) . DIRECTORY_SEPARATOR);
    }

    return $this;
  }

  /**
   * File size of current path
   *
   * @param bool $all
   * @param bool $readable
   *
   * @return mixed
   */
  public function size($all = true, $readable = false) {
    //Size
    $bytes = 0;

    if ($this->isFile()) {
      $bytes = @filesize($this->getPath());
    } elseif ($all) {
      foreach ($this->filesAndFolders() as $file) {
        if ($file->isFile()) {
          $bytes += @filesize($file->getPath());
        } else {
          $bytes += $file->size($all);
        }
      }
    }

    if ($readable) {
      $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
      $factor = floor((strlen($bytes) - 1) / 3);

      return sprintf('%.2f', $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
    }

    return $bytes;
  }

  /**
   * Create
   *
   * @return File|$this
   *
   * @throws \LogicException
   */
  public function create() {
    if (!$this->exists()) {
      //Explode folders
      $segment = explode(DIRECTORY_SEPARATOR, str_replace($this->getRoot(), '', $this->path));

      //Path
      $path = $this->getRoot();

      foreach ($segment as $folder) {
        if (strlen($folder) > 0) {
          if (strpos($folder, '.') === false) {
            $path .= $folder . DIRECTORY_SEPARATOR;

            //Create
            $this->createFolder($path);
          } else {
            $path .= $folder;

            if (!@touch($path)) {
              throw new \LogicException('File not created: ' . $path);
            }
          }
        }
      }

      if ($path !== $this->path) {
        return static::path($path);
      }
    }

    return $this;
  }

  /**
   * Create directory
   *
   * @param string $name
   * @param bool $withIndexFile
   *
   * @return bool|string
   *
   * @throws \LogicException
   */
  public function createFolder($name, $withIndexFile = true) {
    if (!file_exists($name)) {
      if (!@mkdir($name, 0777)) {
        throw new \LogicException('Directory not created: ' . $name);
      }

      if ($withIndexFile) {
        //Index file
        $fileName = $name . 'index' . static::getGlobalPHPFileExt();

        //Data
        $data = "<?php\n"
          . "/**\n"
          . " * @author Orhan POLAT\n"
          . " */\n\n"
          . "header('Location: ../');";

        if (!@file_put_contents($fileName, $data)) {
          throw new \LogicException('File not created or not writable: ' . $fileName);
        }
      }

      //Return folder name
      return $name;
    }

    return false;
  }

  /**
   * Returns global php file extension
   *
   * @return string
   */
  public static function getGlobalPHPFileExt() {
    return static::$ext;
  }

  /**
   * Remove path
   *
   * @return bool
   */
  public function remove() {
    if ($this->exists()) {
      return $this->removeRecursively($this->path);
    }

    return false;
  }

  /**
   * Remove files and folders recursively
   *
   * @param string|File $path
   *
   * @return bool
   */
  protected function removeRecursively($path) {
    $file = ($path instanceof File ? $path : static::path($path));

    if ($file->isWritable()) {
      if ($file->isFolder()) {
        //Get files
        $files = $file->filesAndFolders();

        foreach ($files as $filePath => $fileInfo) {
          $this->removeRecursively($filePath);
        }

        return @rmdir($file->getPath());
      } else {
        return @unlink($file->getPath());
      }
    } else {
      return false;
    }
  }

  /**
   * Checks current path is writable
   *
   * @return bool
   */
  public function isWritable() {
    return $this->exists() && $this->info('writable');
  }

  /**
   * Copy current path to target path
   *
   * @param string $path
   * @param bool $overwrite
   *
   * @return bool
   */
  public function copyTo($path, $overwrite = false) {
    if ($this->exists()) {
      if ($overwrite) {
        @unlink($path);
      }

      return @copy($this->path, $path);
    }

    return false;
  }

  /**
   * Move current path to target path
   *
   * @param string $path
   * @param bool $overwrite
   *
   * @return bool
   */
  public function moveTo($path, $overwrite = false) {
    if ($this->exists()) {
      if ($overwrite) {
        @unlink($path);
      }

      return @rename($this->path, $path);
    }

    return false;
  }

  /**
   * Duplicate current path file
   *
   * @param string $name
   * @param null|string $extension
   *
   * @return $this
   *
   * @throws \LogicException
   */
  public function duplicate($name, $extension = null) {
    if ($this->exists() && $this->isFile()) {
      $newPath = $this->info('dir') . DIRECTORY_SEPARATOR . implode('.', array_filter(array(
          $name,
          (is_null($extension) ? $this->extension() : $extension)
        ), function ($str) {
          return (strlen($str) > 0);
        }));

      if (static::path($this->info('dir'))->isWritable()) {
        if (@copy($this->path, $newPath)) {
          $this->path = $newPath;
        } else {
          throw new \LogicException('Cannot duplicate file: ' . $newPath);
        }
      } else {
        throw new \LogicException('Target directory is not writable: ' . $this->info('dir'));
      }
    }

    return $this;
  }

  /**
   * Load current file
   *
   * @return mixed|null
   */
  public function load() {
    if ($this->exists() && $this->isFile()) {
      return include($this->path);
    }

    return null;
  }

  /**
   * Read content line by line
   *
   * @return array
   */
  public function lines() {
    $lines = array();

    if ($this->exists() && $this->isFile()) {
      if ($file = @fopen($this->path, 'r')) {
        while (!@feof($file)) {
          $lines[] = @fgets($file);
        }

        @fclose($file);
      }
    }

    return $lines;
  }

  /**
   * Read current file content
   */
  public function read() {
    echo $this->content();
  }

  /**
   * Append data into file
   *
   * @param string $data
   *
   * @return number
   */
  public function append($data) {
    return $this->write($data, true);
  }

  /**
   * Write into file
   *
   * @param string $data
   * @param boolean $append
   *
   * @return number
   */
  public function write($data, $append = false) {
    $flag = null;

    if ($append) {
      $flag = LOCK_EX | FILE_APPEND;
    }

    return @file_put_contents($this->path, $data, $flag);
  }

  /**
   * Prepend data into file
   *
   * @param $data
   *
   * @return number
   */
  public function prepend($data) {
    $data = $data . $this->content();

    return $this->write($data);
  }

  /**
   * File asset source
   *
   * @return string
   */
  public function src() {
    return Request::current()->root() . rtrim(($this->exists() ? '/' . $this->info('source') : ''), '/');
  }

  /**
   * Clone current instance
   *
   * @return File
   */
  public function copyInstance() {
    return clone $this;
  }

  /**
   * Magic get info
   *
   * @param string $key
   *
   * @return mixed|\stdClass
   */
  public function __get($key) {
    return $this->info($key);
  }

  /**
   * Magic string
   *
   * @return string
   */
  public function __toString() {
    return $this->getPath();
  }

  /**
   * Magic call
   *
   * @param string $method
   * @param array $args
   *
   * @return $this
   */
  public function __call($method, $args = array()) {
    if (isset($this->options[$method])) {
      call_user_func_array(array($this, 'opt'), array($method, array_get($args, 0)));
    } elseif (!is_null($return = $this->info($method))) {
      return $return;
    }

    return $this;
  }

}