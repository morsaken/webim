<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Image;

use Webim\Library\File;

class Picture {

  /**
   * File
   *
   * @var File
   */
  protected $file;

  /**
   * Image width
   *
   * @var int
   */
  protected $width;

  /**
   * Image height
   *
   * @var int
   */
  protected $height;

  /**
   * Constructor
   *
   * @param File $file
   *
   * @throws \Exception
   */
  public function __construct(File $file) {
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
      throw new \Exception('GD library not installed!');
    }

    if ($size = @getimagesize($file)) {
      $this->width = $size[0];
      $this->height = $size[1];

      $this->file = $file;
    } else {
      throw new \Exception('File is not an image: ' . $file->getPath());
    }
  }

  /**
   * Image file
   *
   * @param File $file
   *
   * @return $this
   */
  public static function file(File $file) {
    return new static($file);
  }

  /**
   * Expected size of image
   *
   * @param null|int $width
   * @param null|int $height
   * @param bool $renew
   *
   * @return $this
   */
  public function size($width = null, $height = null, $renew = false) {
    //Width and height
    $width = (int)$width;
    $height = (int)$height;

    //Reset to main file
    $this->reset();

    if ($width || $height) {
      // Determine aspect ratios
      $currentRatio = $this->height / $this->width;

      if (!$width) {
        // Determine width
        $width = $height / $currentRatio;
      }

      if (!$height) {
        // Determine height
        $height = $width * $currentRatio;
      }

      if (($image = $this->hasSize($width, $height)) && !$renew) {
        //Change file
        $this->file = $image;
      } else {
        //Crop
        $this->thumbnail($width, $height);
      }
    }

    return $this;
  }

  /**
   * Reset file
   *
   * @return $this
   */
  public function reset() {
    if (strpos($this->file->name, '@') !== false) {
      //Get the default file
      $this->file = File::path($this->file->rawPath, preg_replace('/\@.*?$/', '', $this->file->name) . $this->file->extension(false));
    }

    return $this;
  }

  /**
   * Check has size
   *
   * @param int $width
   * @param int $height
   *
   * @return bool|File
   */
  public function hasSize($width, $height) {
    $image = File::path(
      $this->file->rawPath,
      $this->file->name . '@' . floor($width) . 'x' . floor($height) . $this->file->extension(false)
    );

    if (!$image->exists()) {
      return false;
    }

    list($imgWidth, $imgHeight) = @getimagesize($image->getPath());

    if (!$imgWidth || !$imgHeight) {
      return false;
    }

    return $image;
  }

  /**
   * Thumbnail
   *
   * @param int|null $width
   * @param int|null $height
   * @param bool $copy
   *
   * @return $this
   */
  public function thumbnail($width = null, $height = null, $copy = true) {
    //Width and height
    $width = (int)$width;
    $height = (int)$height;

    //Reset to main file
    $this->reset();

    if ($width || $height) {
      // Determine aspect ratios
      $currentRatio = $this->height / $this->width;

      if (!$width) {
        // Determine width
        $width = $height / $currentRatio;
      }

      if (!$height) {
        // Determine height
        $height = $width * $currentRatio;
      }

      // New ratio
      $newRatio = $height / $width;

      // Image file
      $image = Image::file($this->file);

      // Fit to height/width
      if ($newRatio > $currentRatio) {
        $image->fitToHeight($height);
      } else {
        $image->fitToWidth($width);
      }

      $left = floor(($image->width() / 2) - ($width / 2));
      $top = floor(($image->height() / 2) - ($height / 2));

      // Return trimmed image
      $image->crop($left, $top, $width + $left, $height + $top);

      $this->width = $width;
      $this->height = $height;

      if ($copy) {
        //New
        $newFileName = $this->file->name . '@' . floor($width) . 'x' . floor($height) . $this->file->extension(false);

        $this->file = File::path($this->file->rawPath, $newFileName)->create();
      }

      $this->file = $image->save($this->file);
    }

    return $this;
  }

  /**
   * Fit image
   *
   * @param int $width
   * @param int $height
   *
   * @return $this
   */
  public function fit($width, $height) {
    //Width and height
    $width = (int)$width;
    $height = (int)$height;

    //Reset to main file
    $this->reset();

    $image = Image::file($this->file)->bestFit($width, $height);

    $this->width = $image->width();
    $this->height = $image->height();
    $this->file = $image->save();

    return $this;
  }

  /**
   * Get the current width
   *
   * @return int
   */
  public function width() {
    return $this->width;
  }

  /**
   * Get the current height
   *
   * @return int
   */
  public function height() {
    return $this->height;
  }

  /**
   * Image resolution
   *
   * @return string
   */
  public function resolution() {
    return $this->width . 'x' . $this->height;
  }

  /**
   * Image orientation
   *
   * @return string
   */
  public function orientation() {
    return Image::file($this->file)->orientation();
  }

  /**
   * Magic string
   *
   * @return string
   */
  public function __toString() {
    return $this->src();
  }

  /**
   * Picture source
   *
   * @return string
   */
  public function src() {
    if (!$this->file->exists()) {
      return null;
    }

    return $this->file->src();
  }

  /**
   * Magic call
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   *
   * @throws \BadMethodCallException
   */
  public function __call($method, $args) {
    if (!method_exists($this->file, $method)) {
      $className = get_class($this);

      throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

    return call_user_func_array(array($this->file, $method), $args);
  }

}