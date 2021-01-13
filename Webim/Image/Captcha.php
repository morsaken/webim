<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Image;

class Captcha {

  /**
   * Errors
   *
   * @var array
   */
  public $errors = array();
  /**
   * Length of captcha string
   *
   * @var int
   */
  protected $length;
  /**
   * Picture width
   *
   * @var int
   */
  protected $width;
  /**
   * Picture height
   *
   * @var int
   */
  protected $height;
  /**
   * Font size
   *
   * @var int
   */
  protected $fontSize = 25;
  /**
   * String color
   *
   * @var array
   */
  protected $strColor = array(0, 0, 0);
  /**
   * Background color
   *
   * @var array
   */
  protected $bgColor = array(255, 255, 255);
  /**
   * Transparent background color
   *
   * @var bool
   */
  protected $transparent = false;
  /**
   * Captcha complicity
   *
   * @var bool
   */
  protected $simple = false;
  /**
   * Noise value
   *
   * @var int
   */
  protected $noise;
  /**
   * Blur value
   *
   * @var int
   */
  protected $blur;
  /**
   * Captcha string
   *
   * @var string
   */
  protected $str;
  /**
   * Fonts array
   *
   * @var array
   */
  protected $fonts;

  /**
   * Constructor
   *
   * @param array $fonts
   * @param int $length
   * @param null|int $noise
   * @param null|int $blur
   */
  public function __construct($fonts = array(), $length = 6, $noise = null, $blur = null) {
    $this->length = $length;
    $this->noise = $noise;
    $this->blur = $blur;

    foreach ($fonts as $font) {
      $this->fonts[] = $font;
    }

    if (count($this->fonts) == 0) {
      $this->addError('No font!');
    }

    if (!function_exists('imagettftext')) {
      $this->addError('imagettftext not found!');
    }

    if ($this->hasError()) {
      $this->displayError();
      die();
    }

    $this->generateStr();
  }

  /**
   * Add error string
   *
   * @param string $message
   */
  protected function addError($message) {
    $this->errors[] = $message;
  }

  /**
   * Has error
   *
   * @return bool
   */
  public function hasError() {
    return (count($this->errors) > 0);
  }

  /**
   * Displays error image
   */
  protected function displayError() {
    //Header
    header('Content-type: image/png');

    $iHeight = count($this->errors) * 20 + 10;
    $iHeight = ($iHeight < 100) ? 100 : $iHeight;

    $image = imagecreate(400, $iHeight);
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $strColor = imagecolorallocate($image, 0, 0, 0);

    imagefill($image, 0, 0, $bgColor);

    for ($i = 0; $i < count($this->errors); $i++) {
      $msg = 'Error[' . $i . ']: ' . $this->errors[$i];

      imagestring($image, 5, 10, ($i * 20 + 5), $msg, $strColor);
    }

    imagepng($image);
    imagedestroy($image);

    exit();
  }

  /**
   * Generate string
   */
  protected function generateStr() {
    //Chars
    $lowercase = range('a', 'z');
    $numeric = range(2, 9);

    //Pool
    $pool = array_merge($lowercase, $numeric);
    $length = (count($pool) - 1);

    //String
    $str = '';

    for ($i = 0; $i < $this->length; $i++) {
      $str .= $pool[mt_rand(0, $length)];
    }

    $this->str = $str;
  }

  /**
   * Create class
   *
   * @param array $fonts
   * @param int $length
   * @param null|int $noise
   * @param null|int $blur
   *
   * @return $this
   */
  public static function create($fonts = array(), $length = 6, $noise = null, $blur = null) {
    return new static($fonts, $length, $noise, $blur);
  }

  /**
   * Set size
   *
   * @param int $width
   * @param int $height
   *
   * @return $this
   */
  public function size($width, $height) {
    $this->width = $width;
    $this->height = $height;

    return $this;
  }

  /**
   * Font size
   *
   * @param int $size
   *
   * @return $this
   */
  public function fontSize($size = 25) {
    $this->fontSize = $size;

    return $this;
  }

  /**
   * Set complicity
   *
   * @return $this
   */
  public function simple() {
    $this->simple = true;
    $this->height = 40;

    return $this;
  }

  /**
   * String color
   *
   * @param int $red
   * @param int $green
   * @param int $blue
   *
   * @return $this
   */
  public function strColor($red = 0, $green = 0, $blue = 0) {
    $this->strColor = array($red, $green, $blue);

    return $this;
  }

  /**
   * Background color
   *
   * @param int $red
   * @param int $green
   * @param int $blue
   *
   * @return $this
   */
  public function bgColor($red = 255, $green = 255, $blue = 255) {
    $this->bgColor = array($red, $green, $blue);

    return $this;
  }

  /**
   * Set background transparent status
   *
   * @param bool $status
   *
   * @return $this
   */
  public function transparent($status = true) {
    $this->transparent = $status;

    return $this;
  }

  /**
   * Set colors
   *
   * @param array $str
   * @param array $bg
   *
   * @return $this
   */
  public function colors($str = array(0, 0, 0), $bg = array(255, 255, 255)) {
    $this->strColor = $str;
    $this->bgColor = $bg;

    return $this;
  }

  /**
   * Generated captcha string
   *
   * @return string
   */
  public function getStr() {
    return $this->str;
  }

  /**
   * Make and show captcha image
   */
  public function display() {
    //Header
    header('Content-type: image/png');

    //Size
    $width = $this->width ? $this->width : ($this->length * $this->fontSize + 20);
    $height = $this->height ? $this->height : ($this->fontSize + 10);

    //Image
    $image = imagecreate($width, $height);
    $bgColor = imagecolorallocate($image, array_get($this->bgColor, 0, 255), array_get($this->bgColor, 1, 255), array_get($this->bgColor, 2, 255));
    $strColor = imagecolorallocate($image, array_get($this->strColor, 0, 255), array_get($this->strColor, 0, 255), array_get($this->strColor, 0, 255));

    if ($this->transparent) {
      imagecolortransparent($image, $bgColor);
    }

    imagefill($image, 0, 0, $bgColor);

    if ($this->simple) {
      imagettftext(
        $image,
        $this->fontSize,
        0,
        10,
        $this->fontSize + (($this->height - $this->fontSize) / 2),
        $strColor,
        $this->font(),
        $this->str
      );
    } else {
      $this->signs($image, $this->font());

      for ($i = 0; $i < strlen($this->str); $i++) {
        imagettftext(
          $image,
          $this->fontSize,
          mt_rand(-15, 15),
          ($i * $this->fontSize + 10),
          mt_rand(30, 70),
          $strColor,
          $this->font(),
          $this->str[$i]
        );
      }
    }

    if ($this->noise) {
      $this->noise($image, $this->noise);
    }

    if ($this->blur) {
      $this->blur($image, $this->blur);
    }

    imagepng($image);
    imagedestroy($image);

    exit();
  }

  /**
   * Get random font
   */
  protected function font() {
    return $this->fonts[mt_rand(0, (count($this->fonts) - 1))];
  }

  /**
   * Signs
   *
   * @param Resource $image
   * @param string $font
   * @param int $cells
   */
  protected function signs(&$image, $font, $cells = 3) {
    $w = imagesx($image);
    $h = imagesy($image);

    for ($i = 0; $i < $cells; $i++) {
      $centerX = mt_rand(1, $w);
      $centerY = mt_rand(1, $h);
      $amount = mt_rand(1, 15);
      $strColor = imagecolorallocate($image, 185, 185, 185);

      for ($n = 0; $n < $amount; $n++) {
        $signs = range('A', 'Z');
        $sign = $signs[mt_rand(0, count($signs) - 1)];

        imagettftext($image, $this->fontSize,
          mt_rand(-15, 15),
          ($centerX + mt_rand(-50, 50)),
          ($centerY + mt_rand(-50, 50)),
          $strColor, $font, $sign
        );
      }
    }
  }

  /**
   * Noise effect
   *
   * @param Resource $image
   * @param int $runs
   */
  protected function noise(&$image, $runs = 30) {
    $w = imagesx($image);
    $h = imagesy($image);

    for ($n = 0; $n < $runs; $n++) {
      for ($i = 1; $i <= $h; $i++) {
        $randColor = imagecolorallocate($image,
          mt_rand(0, 255),
          mt_rand(0, 255),
          mt_rand(0, 255)
        );

        imagesetpixel($image,
          mt_rand(1, $w),
          mt_rand(1, $h),
          $randColor
        );
      }
    }
  }

  /**
   * Blur effect
   *
   * @param Resource $image
   * @param int $radius
   */
  protected function blur(&$image, $radius = 3) {
    $radius = round(max(0, min($radius, 50)) * 2);

    $w = imagesx($image);
    $h = imagesy($image);

    $blur = imagecreate($w, $h);

    for ($i = 0; $i < $radius; $i++) {
      imagecopy($blur, $image, 0, 0, 1, 1, ($w - 1), ($h - 1));
      imagecopymerge($blur, $image, 1, 1, 0, 0, $w, $h, 50.0000);
      imagecopymerge($blur, $image, 0, 1, 1, 0, ($w - 1), $h, 33.3333);
      imagecopymerge($blur, $image, 1, 0, 0, 1, $w, $h - 1, 25.0000);
      imagecopymerge($blur, $image, 0, 0, 1, 0, ($w - 1), $h, 33.3333);
      imagecopymerge($blur, $image, 1, 0, 0, 0, $w, $h, 25.0000);
      imagecopymerge($blur, $image, 0, 0, 0, 1, $w, $h - 1, 20.0000);
      imagecopymerge($blur, $image, 0, 1, 0, 0, $w, $h, 16.6667);
      imagecopymerge($blur, $image, 0, 0, 0, 0, $w, $h, 50.0000);
      imagecopy($image, $blur, 0, 0, 0, 0, $w, $h);
    }

    imagedestroy($blur);
  }

}