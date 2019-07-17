<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Image;

use Webim\Library\File;

class Image {

  /**
   * File
   *
   * @var File
   */
  protected $file;

  /**
   * Image
   *
   * @var Resource
   */
  protected $image;

  /**
   * String
   *
   * @var string
   */
  protected $string;

  /**
   * Image meta data
   *
   * @var array
   */
  protected $meta;

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
   * @param null|File $file
   *
   * @throws \Exception
   */
  public function __construct(File $file = null) {
    if (!extension_loaded('gd') || !function_exists('gd_info')) {
      throw new \Exception('GD library not installed!');
    }

    $this->file = $file;
    $this->read();
  }

  /**
   * Set meta data of image or base64 string
   *
   * @return $this
   *
   * @throws \Exception
   */
  protected function read() {
    //gather meta data
    if (empty($this->string)) {
      $meta = @getimagesize($this->file->getPath());

      switch (array_get($meta, 'mime')) {
        case 'image/gif':
          $this->image = @imagecreatefromgif($this->file->getPath());
          break;
        case 'image/jpeg':
          $this->image = @imagecreatefromjpeg($this->file->getPath());
          break;
        case 'image/png':
          $this->image = @imagecreatefrompng($this->file->getPath());
          break;
        default:
          throw new \Exception('Invalid image: ' . $this->file->getPath());
          break;
      }
    } elseif (function_exists('getimagesizefromstring')) {
      $meta = @getimagesizefromstring($this->string);
    } else {
      throw new \Exception('PHP 5.4 is required to use method getimagesizefromstring');
    }

    $this->width = array_get($meta, 0);
    $this->height = array_get($meta, 1);

    $this->meta = array(
      'orientation' => $this->orientation(),
      'exif' => ((function_exists('exif_read_data') && ($meta['mime'] === 'image/jpeg') && ($this->string === null)) ? @exif_read_data($this->file->getPath()) : null),
      'format' => preg_replace('/^image\//', '', $meta['mime']),
      'mime' => array_get($meta, 'mime')
    );

    @imagesavealpha($this->image, true);
    @imagealphablending($this->image, true);

    return $this;
  }

  /**
   * Get the current orientation
   *
   * @return string  portrait|landscape|square
   */
  public function orientation() {
    if (imagesx($this->image) > imagesy($this->image)) {
      return 'landscape';
    }

    if (imagesx($this->image) < imagesy($this->image)) {
      return 'portrait';
    }

    return 'square';
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
   * Load a base64 string as image
   *
   * @param string $string base64 string
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function base64($string) {
    //remove data URI scheme and spaces from base64 string then decode it
    $this->string = base64_decode(str_replace(' ', '+', preg_replace('#^data:image/[^;]+;base64,#', '', $string)));
    $this->image = @imagecreatefromstring($this->string);

    return $this->read();
  }

  /**
   * Rotates and/or flips an image automatically so the orientation will be correct (based on exif 'Orientation')
   *
   * @return $this
   */
  public function autoOrient() {
    if (isset($this->meta['exif']['Orientation'])) {
      switch ($this->meta['exif']['Orientation']) {
        case 1:
          // Do nothing
          break;
        case 2:
          // Flip horizontal
          $this->flip('x');
          break;
        case 3:
          // Rotate 180 counterclockwise
          $this->rotate(-180);
          break;
        case 4:
          // vertical flip
          $this->flip('y');
          break;
        case 5:
          // Rotate 90 clockwise and flip vertically
          $this->flip('y');
          $this->rotate(90);
          break;
        case 6:
          // Rotate 90 clockwise
          $this->rotate(90);
          break;
        case 7:
          // Rotate 90 clockwise and flip horizontally
          $this->flip('x');
          $this->rotate(90);
          break;
        case 8:
          // Rotate 90 counterclockwise
          $this->rotate(-90);
          break;
      }
    }

    return $this;
  }

  /**
   * Flip an image horizontally or vertically
   *
   * @param string $direction x|y
   *
   * @return $this
   */
  public function flip($direction) {
    $new = imagecreatetruecolor($this->width, $this->height);
    imagealphablending($new, false);
    imagesavealpha($new, true);

    switch (strtolower($direction)) {
      case 'y':

        for ($y = 0; $y < $this->height; $y++) {
          imagecopy($new, $this->image, 0, $y, 0, $this->height - $y - 1, $this->width, 1);
        }

        break;
      default:

        for ($x = 0; $x < $this->width; $x++) {
          imagecopy($new, $this->image, $x, 0, $this->width - $x - 1, 0, 1, $this->height);
        }

        break;
    }

    $this->image = $new;

    return $this;
  }

  /**
   * Rotate an image
   *
   * @param int $angle 0-360
   * @param string $bgColor Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
   *                        Where red, green, blue - integers 0-255, alpha - integer 0-127
   *
   * @return $this
   */
  public function rotate($angle, $bgColor = '#000000') {
    // Perform the rotation
    $rgba = $this->normalizeColor($bgColor);
    $bgColor = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
    $new = imagerotate($this->image, -($this->keepWithin($angle, -360, 360)), $bgColor);
    imagesavealpha($new, true);
    imagealphablending($new, true);

    // Update meta data
    $this->width = imagesx($new);
    $this->height = imagesy($new);
    $this->image = $new;

    return $this;
  }

  /**
   * Converts a hex color value to its RGB equivalent
   *
   * @param string $color Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
   *                       Where red, green, blue - integers 0-255, alpha - integer 0-127
   *
   * @return array|bool
   */
  protected function normalizeColor($color) {
    if (is_string($color)) {
      $color = trim($color, '#');

      if (strlen($color) == 6) {
        list($r, $g, $b) = array(
          $color[0] . $color[1],
          $color[2] . $color[3],
          $color[4] . $color[5]
        );
      } elseif (strlen($color) == 3) {
        list($r, $g, $b) = array(
          $color[0] . $color[0],
          $color[1] . $color[1],
          $color[2] . $color[2]
        );
      } else {
        return false;
      }

      return array(
        'r' => hexdec($r),
        'g' => hexdec($g),
        'b' => hexdec($b),
        'a' => 0
      );
    } elseif (is_array($color) && (count($color) == 3 || count($color) == 4)) {
      if (isset($color['r'], $color['g'], $color['b'])) {
        return array(
          'r' => $this->keepWithin($color['r'], 0, 255),
          'g' => $this->keepWithin($color['g'], 0, 255),
          'b' => $this->keepWithin($color['b'], 0, 255),
          'a' => $this->keepWithin(isset($color['a']) ? $color['a'] : 0, 0, 127)
        );
      } elseif (isset($color[0], $color[1], $color[2])) {
        return array(
          'r' => $this->keepWithin($color[0], 0, 255),
          'g' => $this->keepWithin($color[1], 0, 255),
          'b' => $this->keepWithin($color[2], 0, 255),
          'a' => $this->keepWithin(isset($color[3]) ? $color[3] : 0, 0, 127)
        );
      }
    }

    return false;
  }

  /**
   * Ensures $value is always within $min and $max range.
   *
   * If lower, $min is returned. If higher, $max is returned.
   *
   * @param int|float value
   * @param int|float min
   * @param int|float max
   *
   * @return int|float
   *
   */
  protected function keepWithin($value, $min, $max) {
    if ($value < $min) {
      return $min;
    }

    if ($value > $max) {
      return $max;
    }

    return $value;
  }

  /**
   * Best fit (proportionally resize to fit in specified width/height)
   *
   * Shrink the image proportionally to fit inside a $width x $height box
   *
   * @param int $maxWidth
   * @param int $maxHeight
   *
   * @return  $this
   */
  public function bestFit($maxWidth, $maxHeight) {
    // If it already fits, there's nothing to do
    if ($this->width <= $maxWidth && $this->height <= $maxHeight) {
      return $this;
    }

    // Determine aspect ratio
    $ratio = $this->height / $this->width;

    // Make width fit into new dimensions
    if ($this->width > $maxWidth) {
      $width = $maxWidth;
      $height = $width * $ratio;
    } else {
      $width = $this->width;
      $height = $this->height;
    }

    // Make height fit into new dimensions
    if ($height > $maxHeight) {
      $height = $maxHeight;
      $width = $height / $ratio;
    }

    return $this->resize($width, $height);
  }

  /**
   * Resize an image to the specified dimensions
   *
   * @param int $width
   * @param int $height
   *
   * @return $this
   */
  public function resize($width, $height) {
    // Generate new GD image
    $new = imagecreatetruecolor($width, $height);

    if ($this->meta['format'] === 'gif') {
      // Preserve transparency in GIFs
      $transparentIndex = imagecolortransparent($this->image);
      $palletSize = imagecolorstotal($this->image);

      if ($transparentIndex >= 0 && $transparentIndex < $palletSize) {
        $transparentColor = imagecolorsforindex($this->image, $transparentIndex);
        $transparentIndex = imagecolorallocate($new, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
        imagefill($new, 0, 0, $transparentIndex);
        imagecolortransparent($new, $transparentIndex);
      }
    } else {
      // Preserve transparency in PNGs (benign for JPEGs)
      imagealphablending($new, false);
      imagesavealpha($new, true);
    }

    // Resize
    imagecopyresampled($new, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);

    // Update meta data
    $this->width = $width;
    $this->height = $height;
    $this->image = $new;

    return $this;
  }

  /**
   * Fit to height (proportionally resize to specified height)
   *
   * @param int $height
   *
   * @return $this
   */
  public function fitToHeight($height) {
    $ratio = $this->height / $this->width;
    $width = $height / $ratio;

    return $this->resize($width, $height);
  }

  /**
   * Fit to width (proportionally resize to specified width)
   *
   * @param int $width
   *
   * @return $this
   */
  public function fitToWidth($width) {
    $ratio = $this->height / $this->width;
    $height = $width * $ratio;

    return $this->resize($width, $height);
  }

  /**
   * Crop an image
   *
   * @param int $x1 Left
   * @param int $y1 Top
   * @param int $x2 Right
   * @param int $y2 Bottom
   *
   * @return $this
   */
  public function crop($x1, $y1, $x2, $y2) {
    // Determine crop size
    if ($x2 < $x1) {
      list($x1, $x2) = array($x2, $x1);
    }

    if ($y2 < $y1) {
      list($y1, $y2) = array($y2, $y1);
    }

    $cropWidth = $x2 - $x1;
    $cropHeight = $y2 - $y1;

    // Perform crop
    $new = imagecreatetruecolor($cropWidth, $cropHeight);
    imagealphablending($new, false);
    imagesavealpha($new, true);
    imagecopyresampled($new, $this->image, 0, 0, $x1, $y1, $cropWidth, $cropHeight, $cropWidth, $cropHeight);

    // Update meta data
    $this->width = $cropWidth;
    $this->height = $cropHeight;
    $this->image = $new;

    return $this;
  }

  /**
   * Blur
   *
   * @param string $type selective|gaussian
   * @param int $passes Number of times to apply the filter
   *
   * @return $this
   *
   */
  public function blur($type = 'selective', $passes = 1) {
    switch (strtolower($type)) {
      case 'gaussian':

        $type = IMG_FILTER_GAUSSIAN_BLUR;

        break;
      default:

        $type = IMG_FILTER_SELECTIVE_BLUR;
    }

    for ($i = 0; $i < $passes; $i++) {
      imagefilter($this->image, $type);
    }

    return $this;
  }

  /**
   * Brightness
   *
   * @param int $level darkest = -255, lightest = 255
   *
   * @return $this
   */
  public function brightness($level) {
    imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $this->keepWithin($level, -255, 255));

    return $this;
  }

  /**
   * Contrast
   *
   * @param int $level Min = -100, max = 100
   *
   * @return $this
   */
  public function contrast($level) {
    imagefilter($this->image, IMG_FILTER_CONTRAST, $this->keepWithin($level, -100, 100));

    return $this;
  }

  /**
   * Colorize
   *
   * @param string $color Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
   *                      Where red, green, blue - integers 0-255, alpha - integer 0-127
   * @param float|int $opacity 0-1
   *
   * @return $this
   */
  public function colorize($color, $opacity) {
    $rgba = $this->normalizeColor($color);
    $alpha = $this->keepWithin(127 - (127 * $opacity), 0, 127);

    imagefilter(
      $this->image,
      IMG_FILTER_COLORIZE,
      $this->keepWithin($rgba['r'], 0, 255),
      $this->keepWithin($rgba['g'], 0, 255),
      $this->keepWithin($rgba['b'], 0, 255),
      $alpha
    );

    return $this;
  }

  /**
   * Desaturate
   *
   * @param int $percentage Level of desaturization.
   *
   * @return $this
   */
  public function desaturate($percentage = 100) {
    // Determine percentage
    $percentage = $this->keepWithin($percentage, 0, 100);

    if ($percentage === 100) {
      imagefilter($this->image, IMG_FILTER_GRAYSCALE);
    } else {
      // Make a desaturated copy of the image
      $new = imagecreatetruecolor($this->width, $this->height);
      imagealphablending($new, false);
      imagesavealpha($new, true);
      imagecopy($new, $this->image, 0, 0, 0, 0, $this->width, $this->height);
      imagefilter($new, IMG_FILTER_GRAYSCALE);

      // Merge with specified percentage
      $this->imageCopyMergeAlpha($this->image, $new, 0, 0, 0, 0, $this->width, $this->height, $percentage);
      imagedestroy($new);
    }

    return $this;
  }

  /**
   * Same as PHP's imagecopymerge() function, except preserves alpha-transparency in 24-bit PNGs
   *
   * @param $dstImage
   * @param $srcImage
   * @param $dstX
   * @param $dstY
   * @param $srcX
   * @param $srcY
   * @param $srcW
   * @param $srcH
   * @param $pct
   *
   * @link http://www.php.net/manual/en/function.imagecopymerge.php#88456
   */
  protected function imageCopyMergeAlpha($dstImage, $srcImage, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $pct) {
    // Get image width and height and percentage
    $pct /= 100;
    $w = imagesx($srcImage);
    $h = imagesy($srcImage);

    // Turn alpha blending off
    imagealphablending($srcImage, false);

    // Find the most opaque pixel in the image (the one with the smallest alpha value)
    $minAlpha = 127;

    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        $alpha = (imagecolorat($srcImage, $x, $y) >> 24) & 0xFF;
        if ($alpha < $minAlpha) {
          $minAlpha = $alpha;
        }
      }
    }

    // Loop through image pixels and modify alpha for each
    for ($x = 0; $x < $w; $x++) {
      for ($y = 0; $y < $h; $y++) {
        // Get current alpha value (represents the TRANSPARENCY!)
        $colorXY = imagecolorat($srcImage, $x, $y);

        $alpha = ($colorXY >> 24) & 0xFF;

        // Calculate new alpha
        if ($minAlpha !== 127) {
          $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $minAlpha);
        } else {
          $alpha += 127 * $pct;
        }

        // Get the color index with new alpha
        $alphaColorXY = imagecolorallocatealpha($srcImage, ($colorXY >> 16) & 0xFF, ($colorXY >> 8) & 0xFF, $colorXY & 0xFF, $alpha);

        // Set pixel with the new color + opacity
        if (!imagesetpixel($srcImage, $x, $y, $alphaColorXY)) {
          return;
        }
      }
    }

    // Copy it
    imagesavealpha($dstImage, true);
    imagealphablending($dstImage, true);
    imagesavealpha($srcImage, true);
    imagealphablending($srcImage, true);
    imagecopy($dstImage, $srcImage, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH);
  }

  /**
   * Edge Detect
   *
   * @return $this
   */
  public function edges() {
    imagefilter($this->image, IMG_FILTER_EDGEDETECT);

    return $this;
  }

  /**
   * Emboss
   *
   * @return $this
   */
  public function emboss() {
    imagefilter($this->image, IMG_FILTER_EMBOSS);

    return $this;
  }

  /**
   * Invert
   *
   * @return $this
   *
   */
  public function invert() {
    imagefilter($this->image, IMG_FILTER_NEGATE);

    return $this;
  }

  /**
   * Pixelate
   *
   * @param int $blockSize Size in pixels of each resulting block
   *
   * @return $this
   */
  public function pixelate($blockSize = 10) {
    imagefilter($this->image, IMG_FILTER_PIXELATE, $blockSize, true);

    return $this;
  }

  /**
   * Sepia
   *
   * @return $this
   */
  public function sepia() {
    imagefilter($this->image, IMG_FILTER_GRAYSCALE);
    imagefilter($this->image, IMG_FILTER_COLORIZE, 100, 50, 0);

    return $this;
  }

  /**
   * Sketch
   *
   * @return $this
   */
  public function sketch() {
    imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);

    return $this;
  }

  /**
   * Smooth
   *
   * @param int $level Min = -10, max = 10
   *
   * @return $this
   */
  public function smooth($level) {
    imagefilter($this->image, IMG_FILTER_SMOOTH, $this->keepWithin($level, -10, 10));

    return $this;
  }

  /**
   * Mean Remove
   *
   * @return $this
   */
  public function meanRemove() {
    imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);

    return $this;
  }

  /**
   * Changes the opacity level of the image
   *
   * @param float|int $opacity 0-1
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function opacity($opacity) {
    // Determine opacity
    $opacity = $this->keepWithin($opacity, 0, 1) * 100;

    // Make a copy of the image
    $copy = imagecreatetruecolor($this->width, $this->height);
    imagealphablending($copy, false);
    imagesavealpha($copy, true);
    imagecopy($copy, $this->image, 0, 0, 0, 0, $this->width, $this->height);

    // Create transparent layer
    $this->create($this->width, $this->height, array(0, 0, 0, 127));

    // Merge with specified opacity
    $this->imageCopyMergeAlpha($this->image, $copy, 0, 0, 0, 0, $this->width, $this->height, $opacity);
    imagedestroy($copy);

    return $this;
  }

  /**
   * Create an image from scratch
   *
   * @param int $width Image width
   * @param int|null $height If omitted - assumed equal to $width
   * @param null|string $color Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
   *                           Where red, green, blue - integers 0-255, alpha - integer 0-127
   *
   * @return $this
   */
  public static function create($width, $height = null, $color = null) {
    $class = new static();

    $height = $height ?: $width;
    $class->width = $width;
    $class->height = $height;
    $class->image = imagecreatetruecolor($width, $height);
    $class->meta = array(
      'width' => $width,
      'height' => $height,
      'orientation' => $class->orientation(),
      'exif' => null,
      'format' => 'png',
      'mime' => 'image/png'
    );

    if ($color) {
      $class->fill($color);
    }

    return $class;
  }

  /**
   * Fill image with color
   *
   * @param string $color Hex color string, array(red, green, blue) or array(red, green, blue, alpha).
   *                       Where red, green, blue - integers 0-255, alpha - integer 0-127
   *
   * @return $this
   */
  public function fill($color = '#000000') {
    $rgba = $this->normalizeColor($color);
    $fill_color = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);

    imagealphablending($this->image, false);
    imagesavealpha($this->image, true);
    imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, $fill_color);

    return $this;
  }

  /**
   * Overlay
   *
   * Overlay an image on top of another, works with 24-bit PNG alpha-transparency
   *
   * @param string $overlay An image filename or a SimpleImage object
   * @param string $position center|top|left|bottom|right|top left|top right|bottom left|bottom right
   * @param float|int $opacity Overlay opacity 0-1
   * @param int $xOffset Horizontal offset in pixels
   * @param int $yOffset Vertical offset in pixels
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function overlay($overlay, $position = 'center', $opacity = 1, $xOffset = 0, $yOffset = 0) {
    // Load overlay image
    if (!($overlay instanceof Image)) {
      if (!($overlay instanceof File)) {
        throw new \Exception('Overlay must be instance of Webim\Library\File');
      }

      $overlay = new static($overlay);
    }

    // Convert opacity
    $opacity = $opacity * 100;

    // Determine position
    switch (strtolower($position)) {
      case 'top left':
        $x = 0 + $xOffset;
        $y = 0 + $yOffset;
        break;
      case 'top right':
        $x = $this->width - $overlay->width + $xOffset;
        $y = 0 + $yOffset;
        break;
      case 'top':
        $x = ($this->width / 2) - ($overlay->width / 2) + $xOffset;
        $y = 0 + $yOffset;
        break;
      case 'bottom left':
        $x = 0 + $xOffset;
        $y = $this->height - $overlay->height + $yOffset;
        break;
      case 'bottom right':
        $x = $this->width - $overlay->width + $xOffset;
        $y = $this->height - $overlay->height + $yOffset;
        break;
      case 'bottom':
        $x = ($this->width / 2) - ($overlay->width / 2) + $xOffset;
        $y = $this->height - $overlay->height + $yOffset;
        break;
      case 'left':
        $x = 0 + $xOffset;
        $y = ($this->height / 2) - ($overlay->height / 2) + $yOffset;
        break;
      case 'right':
        $x = $this->width - $overlay->width + $xOffset;
        $y = ($this->height / 2) - ($overlay->height / 2) + $yOffset;
        break;
      case 'center':
      default:
        $x = ($this->width / 2) - ($overlay->width / 2) + $xOffset;
        $y = ($this->height / 2) - ($overlay->height / 2) + $yOffset;
        break;
    }

    // Perform the overlay
    $this->imageCopyMergeAlpha($this->image, $overlay->image, $x, $y, 0, 0, $overlay->width, $overlay->height, $opacity);

    return $this;
  }

  /**
   * Add text to an image
   *
   * @param string $text
   * @param File $fontFile
   * @param float|int $fontSize
   * @param string|array $color
   * @param string $position
   * @param int $xOffset
   * @param int $yOffset
   * @param string|array $strokeColor
   * @param string $strokeSize
   * @param string $alignment
   * @param int $letterSpacing
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function text($text, File $fontFile, $fontSize = 12, $color = '#000000', $position = 'center', $xOffset = 0, $yOffset = 0, $strokeColor = null, $strokeSize = null, $alignment = null, $letterSpacing = 0) {
    // todo - this method could be improved to support the text angle
    $angle = 0;

    $colorArr = array();

    // Determine text color
    if (is_array($color)) {
      foreach ($color as $var) {
        $rgba = $this->normalizeColor($var);
        $colorArr[] = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
      }
    } else {
      $rgba = $this->normalizeColor($color);
      $colorArr[] = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
    }

    if (!$fontFile->exists()) {
      throw new \Exception('Unable to load font: ' . $fontFile->getPath());
    }

    // Determine text box size
    $box = imagettfbbox($fontSize, $angle, $fontFile->getPath(), $text);

    $box_width = abs($box[6] - $box[2]);
    $box_height = abs($box[7] - $box[1]);

    // Determine position
    switch (strtolower($position)) {
      case 'top left':
        $x = 0 + $xOffset;
        $y = 0 + $yOffset + $box_height;
        break;
      case 'top right':
        $x = $this->width - $box_width + $xOffset;
        $y = 0 + $yOffset + $box_height;
        break;
      case 'top':
        $x = ($this->width / 2) - ($box_width / 2) + $xOffset;
        $y = 0 + $yOffset + $box_height;
        break;
      case 'bottom left':
        $x = 0 + $xOffset;
        $y = $this->height - $box_height + $yOffset + $box_height;
        break;
      case 'bottom right':
        $x = $this->width - $box_width + $xOffset;
        $y = $this->height - $box_height + $yOffset + $box_height;
        break;
      case 'bottom':
        $x = ($this->width / 2) - ($box_width / 2) + $xOffset;
        $y = $this->height - $box_height + $yOffset + $box_height;
        break;
      case 'left':
        $x = 0 + $xOffset;
        $y = ($this->height / 2) - (($box_height / 2) - $box_height) + $yOffset;
        break;
      case 'right';
        $x = $this->width - $box_width + $xOffset;
        $y = ($this->height / 2) - (($box_height / 2) - $box_height) + $yOffset;
        break;
      case 'center':
      default:
        $x = ($this->width / 2) - ($box_width / 2) + $xOffset;
        $y = ($this->height / 2) - (($box_height / 2) - $box_height) + $yOffset;
        break;
    }

    if ($alignment === 'left') {
      // Left aligned text
      $x = -($x * 2);
    } else if ($alignment === 'right') {
      // Right aligned text
      $dimensions = imagettfbbox($fontSize, $angle, $fontFile->getPath(), $text);
      $alignment_offset = abs($dimensions[4] - $dimensions[0]);
      $x = -(($x * 2) + $alignment_offset);
    }

    // Add the text
    imagesavealpha($this->image, true);
    imagealphablending($this->image, true);

    if (isset($strokeColor) && isset($strokeSize)) {
      // Text with stroke
      if (is_array($color) || is_array($strokeColor)) {
        // Multi colored text and/or multi colored stroke
        if (is_array($strokeColor)) {
          foreach ($strokeColor as $key => $var) {
            $rgba = $this->normalizeColor($strokeColor[$key]);
            $strokeColor[$key] = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
          }
        } else {
          $rgba = $this->normalizeColor($strokeColor);
          $strokeColor = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
        }

        $arrayOfLetters = str_split($text, 1);

        foreach ($arrayOfLetters as $key => $var) {
          if ($key > 0) {
            $dimensions = imagettfbbox($fontSize, $angle, $fontFile->getPath(), $arrayOfLetters[$key - 1]);
            $x += abs($dimensions[4] - $dimensions[0]) + $letterSpacing;
          }

          // If the next letter is empty, we just move forward to the next letter
          if ($var !== ' ') {
            $this->imageTTFStrokeText($this->image, $fontSize, $angle, $x, $y, current($colorArr), current($strokeColor), $strokeSize, $fontFile->getPath(), $var);

            // #000 is 0, black will reset the array so we write it this way
            if (next($colorArr) === false) {
              reset($colorArr);
            }

            // #000 is 0, black will reset the array so we write it this way
            if (next($strokeColor) === false) {
              reset($strokeColor);
            }
          }
        }
      } else {
        $rgba = $this->normalizeColor($strokeColor);
        $strokeColor = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
        $this->imageTTFStrokeText($this->image, $fontSize, $angle, $x, $y, $colorArr[0], $strokeColor, $strokeSize, $fontFile->getPath(), $text);
      }
    } else {
      // Text without stroke
      if (is_array($color)) {
        // Multi colored text
        $arrayOfLetters = str_split($text, 1);

        foreach ($arrayOfLetters as $key => $var) {
          if ($key > 0) {
            $dimensions = imagettfbbox($fontSize, $angle, $fontFile->getPath(), $arrayOfLetters[$key - 1]);
            $x += abs($dimensions[4] - $dimensions[0]) + $letterSpacing;
          }

          // If the next letter is empty, we just move forward to the next letter
          if ($var !== ' ') {
            imagettftext($this->image, $fontSize, $angle, $x, $y, current($colorArr), $fontFile->getPath(), $var);

            // #000 is 0, black will reset the array so we write it this way
            if (next($colorArr) === false) {
              reset($colorArr);
            }
          }
        }
      } else {
        imagettftext($this->image, $fontSize, $angle, $x, $y, $colorArr[0], $fontFile->getPath(), $text);
      }
    }

    return $this;
  }

  /**
   *  Same as imagettftext(), but allows for a stroke color and size
   *
   * @param resource &$image A GD image object
   * @param float $size The font size
   * @param float $angle The angle in degrees
   * @param int $x X-coordinate of the starting position
   * @param int $y Y-coordinate of the starting position
   * @param int &$textColor The color index of the text
   * @param int &$strokeColor The color index of the stroke
   * @param int $strokeSize The stroke size in pixels
   * @param string $fontFile The path to the font to use
   * @param string $text The text to output
   *
   * @return array This method has the same return values as imagettftext()
   *
   */
  protected function imageTTFStrokeText(&$image, $size, $angle, $x, $y, &$textColor, &$strokeColor, $strokeSize, $fontFile, $text) {
    for ($c1 = ($x - abs($strokeSize)); $c1 <= ($x + abs($strokeSize)); $c1++) {
      for ($c2 = ($y - abs($strokeSize)); $c2 <= ($y + abs($strokeSize)); $c2++) {
        imagettftext($image, $size, $angle, $c1, $c2, $strokeColor, $fontFile, $text);
      }
    }

    return imagettftext($image, $size, $angle, $x, $y, $textColor, $fontFile, $text);
  }

  /**
   * Outputs image without saving
   *
   * @param null|string $format If omitted or null - format of original file will be used, may be gif|jpg|png
   * @param int $quality Output image quality in percents 0-100
   *
   * @throws \Exception
   */
  public function output($format = null, $quality = 100) {
    // Determine mime type
    switch (strtolower($format)) {
      case 'gif':
        $mime = 'image/gif';
        break;
      case 'jpeg':
      case 'jpg':
        imageinterlace($this->image, true);
        $mime = 'image/jpeg';
        break;
      case 'png':
        $mime = 'image/png';
        break;
      default:
        $mime = $this->meta['mime'];
        break;
    }

    // Output the image
    header('Content-Type: ' . $mime);

    switch ($mime) {
      case 'image/gif':
        imagegif($this->image);
        break;
      case 'image/jpeg':
        imagejpeg($this->image, null, round($quality));
        break;
      case 'image/png':
        imagepng($this->image, null, round(9 * $quality / 100));
        break;
      default:
        throw new \Exception('Unsupported image format: ' . $this->file->extension());
        break;
    }
  }

  /**
   * Outputs image as data base64 to use as img src
   *
   * @param null|string $format If omitted or null - format of original file will be used, may be gif|jpg|png
   * @param int $quality Output image quality in percents 0-100
   *
   * @return string
   *
   * @throws \Exception
   */
  public function outputBase64($format = null, $quality = 100) {
    // Determine mime type
    switch (strtolower($format)) {
      case 'gif':
        $mime = 'image/gif';
        break;
      case 'jpeg':
      case 'jpg':
        imageinterlace($this->image, true);
        $mime = 'image/jpeg';
        break;
      case 'png':
        $mime = 'image/png';
        break;
      default:
        $mime = $this->meta['mime'];
        break;
    }

    // Output the image
    ob_start();

    switch ($mime) {
      case 'image/gif':
        imagegif($this->image);
        break;
      case 'image/jpeg':
        imagejpeg($this->image, null, round($quality));
        break;
      case 'image/png':
        imagepng($this->image, null, round(9 * $quality / 100));
        break;
      default:
        throw new \Exception('Unsupported image format: ' . $this->file->extension());
        break;
    }

    $data = ob_get_contents();
    ob_end_clean();

    // Returns formatted string for img src
    return 'data:' . $mime . ';base64,' . base64_encode($data);
  }

  /**
   * Save an image
   *
   * The resulting format will be determined by the file extension.
   *
   * @param null|File $file
   * @param null|int $quality Output image quality in percents 0-100
   *
   * @return File
   *
   * @throws \Exception
   */
  public function save(File $file = null, $quality = 100) {
    $format = $this->meta['format'];

    if ($file instanceof File) {
      $this->file = $file;
      $format = $file->extension();
    }

    // Create the image
    switch ($format) {
      case 'gif':
        $result = imagegif($this->image, $this->file->getPath());
        break;
      case 'jpg':
      case 'jpeg':
        imageinterlace($this->image, true);
        $result = imagejpeg($this->image, $this->file->getPath(), round($quality));
        break;
      case 'png':
        $result = imagepng($this->image, $this->file->getPath(), round(9 * $quality / 100));
        break;
      default:
        throw new \Exception('Unsupported format');
    }

    if (!$result) {
      throw new \Exception('Unable to save image: ' . $this->file->getPath());
    }

    return $this->file;
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
   * Get info about the original image
   *
   * @return array <pre> array(
   *  width        => 320,
   *  height       => 200,
   *  orientation  => ['portrait', 'landscape', 'square'],
   *  exif         => array(...),
   *  mime         => ['image/jpeg', 'image/gif', 'image/png'],
   *  format       => ['jpeg', 'gif', 'png']
   * )</pre>
   *
   */
  public function meta() {
    return $this->meta;
  }

  /**
   * Destroy image resource
   */
  public function __destruct() {
    if ($this->image !== null && get_resource_type($this->image) === 'gd') {
      imagedestroy($this->image);
    }
  }

}