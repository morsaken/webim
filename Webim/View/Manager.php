<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\View;

use Webim\Library\File;

class Manager {

  /**
   * Default file extension to compile
   */
  const EXT = '.html';

  /**
   * Current view path
   *
   * @var Webim\Library\File
   */
  protected static $path;

  /**
   * Cache path
   *
   * @var Webim\Library\File
   */
  protected static $cachePath;

  /**
   * Page file
   *
   * @var Webim\Library\File
   */
  protected $file;

  /**
   * Data container
   *
   * @var array
   */
  protected $data = array();

  /**
   * View renderer
   *
   * @var Webim\View\Renderer
   */
  protected $renderer;

  /**
   * Constructor
   *
   * @param string|Webim\Library\File $name
   * @param null|Webim\Library\File $path
   */
  public function __construct($name, $path = null) {
    //Set view dir for includes, layouts
    if (!is_null($path) && ($path instanceof File) && $path->isFolder()) {
      static::setPath($path);
    } elseif (is_null(static::getPath()) && ($name instanceof File)) {
      static::setPath($name->up());
    }

    //Check the path set
    if (!static::getPath() instanceof File) {
      throw new \RuntimeException('View path must be set!');
    }

    if ($name instanceof File) {
      //Set file
      $file = $name;
    } else {
      if (is_null($path)) {
        //Current file name is for page
        $name = 'pages.' . $name;
      }

      $file = $this->file($name);
    }

    if (!$file->exists()) {
      throw new \RuntimeException('Page file does not exists: ' . $file->getPath());
    }

    //Set file
    $this->file = $file;

    //Set renderer
    $this->renderer = new Renderer;

    //Start compile
    $this->compile();
  }

  /**
   * Get path
   *
   * @return null|Webim\Library\File
   */
  public static function getPath() {
    if (!static::$path instanceof File) {
      return null;
    }

    return static::$path;
  }

  /**
   * Set path
   *
   * @param Webim\Library\File $path
   */
  public static function setPath($path) {
    if ($path instanceof File && $path->isFolder()) {
      static::$path = $path;
    }
  }

  /**
   * Create file
   *
   * @param string $view
   *
   * @return string
   */
  public function file($view) {
    //Incoming view could be like : product.items
    $items = explode('.', $view);

    //Create file path
    return static::getPath()->file(implode(DIRECTORY_SEPARATOR, $items) . static::EXT);
  }

  /**
   * Compile view from html file or cache
   */
  protected function compile() {
    if ($this->file->extension() === trim(static::EXT, '.')) {
      if ($this->isExpired()) {
        $compiled = Compiler::compile($this->file->content());

        $this->file = $this->getCompiledPath()->create();
        $this->file->write($compiled);
      } else {
        $this->file = $this->getCompiledPath();
      }
    }
  }

  /**
   * Determine if the view at the given path is expired.
   *
   * @return bool
   */
  public function isExpired() {
    //Compiled file
    $compiled = $this->getCompiledPath();

    // If the compiled file doesn't exist we will indicate that the view is expired
    // so that it can be re-compiled. Else, we will verify the last modification
    // of the views is less than the modification times of the compiled views.
    if (!$compiled->exists()) {
      return true;
    }

    return $this->file->modified() >= $compiled->modified();
  }

  /**
   * Get the path to the compiled version of a view.
   *
   * @return Webim\Library\File
   */
  public function getCompiledPath() {
    $path = static::getPath()->folder('cache');

    if (static::getCachePath()) {
      $path = static::getCachePath();
    }

    if (!$path->exists()) {
      $path->create();
    }

    return $path->file(md5($this->file->getPath()) . File::getGlobalPHPFileExt());
  }

  /**
   * Get cache path
   *
   * @return null|Webim\Library\File
   */
  public static function getCachePath() {
    if (!static::$cachePath instanceof File) {
      return null;
    }

    return static::$cachePath;
  }

  /**
   * Set cache path
   *
   * @param Webim\Library\File $path
   */
  public static function setCachePath($path) {
    if ($path instanceof File && $path->isFolder()) {
      static::$cachePath = $path;
    }
  }

  /**
   * Get the rendered content of the view based on a given condition.
   *
   * @param string $type
   * @param bool $condition
   * @param string $view
   * @param array $data
   *
   * @return string
   */
  public function makeWhen($type, $condition, $view, $data = array()) {
    if (!$condition) {
      return '';
    }

    return $this->make($type, $view, $data);
  }

  /**
   * Make view content
   *
   * @param string $type
   * @param string $view
   * @param array $data
   *
   * @return $this
   */
  public function make($type, $view, $data = array()) {
    //Create file path
    $path = $this->file($type . 's.' . $view);

    //Data arrays
    $args = func_get_args();

    //Remove type and view from arguments
    unset($args[0]);
    unset($args[1]);

    //Current data array
    $data = array();

    foreach ($args as $arg) {
      if (is_array($arg)) {
        $data = array_merge($data, $arg);
      }
    }

    return static::create($path, static::getPath())->data($this->data, $data);
  }

  /**
   * Set data
   *
   * @param array $data
   *
   * @return $this
   */
  public function data($data = array()) {
    foreach (func_get_args() as $data) {
      if (!is_array($data)) {
        $data = array($data);
      }

      $this->data = array_merge($this->data, $data);
    }

    return $this;
  }

  /**
   * Init view
   *
   * @param string|Webim\Library\File $name
   * @param null|Webim\Library\File $path
   *
   * @return static
   */
  public static function create($name, $path = null) {
    return new static($name, $path);
  }

  /**
   * Shortcut for data
   *
   * @param string $key
   * @param mixed $value
   *
   * @return $this
   */
  public function with($key, $value) {
    $this->data[$key] = $value;

    return $this;
  }

  /**
   * Get the sections of the rendered view.
   *
   * @return string
   */
  public function renderSections() {
    $render = $this->renderer;

    return $this->render(function ($view) use ($render) {
      return $render->getSections();
    });
  }

  /**
   * Get the string contents of the view.
   *
   * @param null|\Closure $callback
   *
   * @return string
   */
  public function render(\Closure $callback = null) {
    $contents = $this->renderContents();

    $response = isset($callback) ? $callback($this, $contents) : null;

    // Once we have the contents of the view, we will flush the sections if we are
    // done rendering all views so that there is nothing left hanging over when
    // another view is rendered in the future by the application developers.
    $this->renderer->flushSectionsIfDoneRendering();

    return $response ?: $contents;
  }

  /**
   * Get the contents of the view instance.
   *
   * @return string
   */
  protected function renderContents() {
    // We will keep track of the amount of views being rendered so we can flush
    // the section after the complete rendering operation is done. This will
    // clear out the sections for any separate views that may be rendered.
    $this->renderer->incrementRender();

    $contents = $this->getContents();

    // Once we've finished rendering the view, we'll decrement the render count
    // so that each sections get flushed out next time a view is created and
    // no old sections are staying around in the memory of an environment.
    $this->renderer->decrementRender();

    return $contents;
  }

  /**
   * Get the evaluated contents of the view.
   *
   * @return string
   *
   * @throws \Exception
   */
  protected function getContents() {
    if (!$this->file->isFile()) {
      throw new \LogicException('Not a valid file: ' . $this->file->getPath());
    }

    if (!isset($this->data['__view']) || !isset($this->data['__render'])) {
      $this->data['__view'] = $this;
      $this->data['__render'] = $this->renderer;
    }

    extract($this->data);

    ob_start();

    include($this->file->getPath());

    return ltrim(ob_get_clean());
  }

  /**
   * Checks file existence
   *
   * @param string $type
   * @param string $view
   *
   * @return bool
   */
  public function exists($type, $view) {
    return $this->file($type . 's.' . $view)->exists();
  }

  /**
   * Magic to string
   *
   * @return string
   */
  public function __toString() {
    return $this->render();
  }

}