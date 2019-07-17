<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Image;

class Manager {

  /**
   * @var int
   */
  const ERROR_NOT_AN_IMAGE_FILE = 1;

  /**
   * @var int
   */
  const ERROR_IMAGE_NOT_FOUND = 2;

  /**
   * @var integer
   */
  const ERROR_NOT_WRITABLE_FILE = 3;

  /**
   * @var int
   */
  const ERROR_CREATE_IMAGE_FROM_STRING = 4;

  /**
   * @var int
   */
  const ERROR_FONT_NOT_FOUND = 5;

  /**
   * Initialize a layer from a given image path
   *
   * From an upload form, you can give the "tmp_name" path
   *
   * @param string $path
   *
   * @return Webim\Image\Layer
   *
   * @throws \Exception
   */
  public static function fromPath($path) {
    if (file_exists($path) && !is_dir($path)) {
      if (!is_readable($path)) {
        throw new \Exception("Can't open the file at '" . $path . "': file is not writable, did you check permissions (755 / 777) ?", static::ERROR_NOT_WRITABLE_FILE);
      }

      $imageSizeInfos = @getimagesize($path);
      $mimeContentType = explode('/', $imageSizeInfos['mime']);

      if (!$mimeContentType || !array_key_exists(1, $mimeContentType)) {
        throw new \Exception("Not an image file (jpeg/png/gif) at '" . $path . "'", static::ERROR_NOT_AN_IMAGE_FILE);
      }

      $mimeContentType = $mimeContentType[1];

      switch ($mimeContentType) {
        case 'jpeg':
          $image = imagecreatefromjpeg($path);
          break;
        case 'gif':
          $image = imagecreatefromgif($path);
          break;
        case 'png':
          $image = imagecreatefrompng($path);
          break;
        default:
          throw new \Exception('Not an image file (jpeg/png/gif) at "' . $path . '"', static::ERROR_NOT_AN_IMAGE_FILE);
          break;
      }

      return new Layer($image);
    }

    throw new \Exception('No such file found at "' . $path . '"', static::ERROR_IMAGE_NOT_FOUND);
  }

  /**
   * Initialize a text layer
   *
   * @param string $text
   * @param string $fontPath
   * @param int $fontSize
   * @param string $fontColor
   * @param int $textRotation
   * @param int $backgroundColor
   *
   * @return Webim\Image\Layer
   */
  public static function textLayer($text, $fontPath, $fontSize = 13, $fontColor = 'ffffff', $textRotation = 0, $backgroundColor = null) {
    $textDimensions = static::getTextBoxDimensions($fontSize, $textRotation, $fontPath, $text);

    $layer = static::virginLayer($textDimensions['width'], $textDimensions['height'], $backgroundColor);
    $layer->write($text, $fontPath, $fontSize, $fontColor, $textDimensions['left'], $textDimensions['top'], $textRotation);

    return $layer;
  }

  /**
   * Return dimension of a text
   *
   * @param int $fontSize
   * @param int $fontAngle
   * @param string $fontFile
   * @param string $text
   *
   * @return array|bool
   *
   * @throws \Exception
   */
  public static function getTextBoxDimensions($fontSize, $fontAngle, $fontFile, $text) {
    if (!file_exists($fontFile)) {
      throw new \Exception('Can\'t find a font file at this path : "' . $fontFile . '".', static::ERROR_FONT_NOT_FOUND);
    }

    $box = imagettfbbox($fontSize, $fontAngle, $fontFile, $text);

    if (!$box) {
      return false;
    }

    $minX = min(array(
      $box[0],
      $box[2],
      $box[4],
      $box[6]
    ));
    $maxX = max(array(
      $box[0],
      $box[2],
      $box[4],
      $box[6]
    ));
    $minY = min(array(
      $box[1],
      $box[3],
      $box[5],
      $box[7]
    ));
    $maxY = max(array(
      $box[1],
      $box[3],
      $box[5],
      $box[7]
    ));
    $width = ($maxX - $minX);
    $height = ($maxY - $minY);
    $left = abs($minX) + $width;
    $top = abs($minY) + $height;

    // to calculate the exact bounding box, we write the text in a large image
    $img = @imagecreatetruecolor($width << 2, $height << 2);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $black);

    // for ensure that the text is completely in the image
    imagettftext($img, $fontSize, $fontAngle, $left, $top, $white, $fontFile, $text);

    // start scanning (0=> black => empty)
    $rleft = $w4 = $width << 2;
    $rright = 0;
    $rbottom = 0;
    $rtop = $h4 = $height << 2;

    for ($x = 0; $x < $w4; $x++) {
      for ($y = 0; $y < $h4; $y++) {
        if (imagecolorat($img, $x, $y)) {
          $rleft = min($rleft, $x);
          $rright = max($rright, $x);
          $rtop = min($rtop, $y);
          $rbottom = max($rbottom, $y);
        }
      }
    }

    imagedestroy($img);

    return array(
      'left' => $left - $rleft,
      'top' => $top - $rtop,
      'width' => $rright - $rleft + 1,
      'height' => $rbottom - $rtop + 1
    );
  }

  /**
   * Initialize a new virgin layer
   *
   * @param int $width
   * @param int $height
   * @param string $backgroundColor
   *
   * @return Webim\Image\Layer
   */
  public static function virginLayer($width = 100, $height = 100, $backgroundColor = null) {
    $opacity = 0;

    if (!$backgroundColor || ($backgroundColor == 'transparent')) {
      $opacity = 127;
      $backgroundColor = 'ffffff';
    }

    return new Layer(static::generate($width, $height, $backgroundColor, $opacity));
  }

  /**
   * Generate a new image resource var
   *
   * @param int $width
   * @param int $height
   * @param string $color
   * @param int $opacity
   *
   * @return resource
   */
  public static function generate($width = 100, $height = 100, $color = "ffffff", $opacity = 127) {
    $RGBColors = static::hexToRgb($color);

    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    $color = imagecolorallocatealpha($image, $RGBColors["R"], $RGBColors["G"], $RGBColors["B"], $opacity);
    imagefill($image, 0, 0, $color);

    return $image;
  }

  /**
   * Convert Hex color to RGB color format
   *
   * @param string $hex
   *
   * @return array
   */
  public static function hexToRgb($hex) {
    return array(
      'R' => (int)base_convert(substr($hex, 0, 2), 16, 10),
      'G' => (int)base_convert(substr($hex, 2, 2), 16, 10),
      'B' => (int)base_convert(substr($hex, 4, 2), 16, 10)
    );
  }

  /**
   * Initialize a layer from a resource image var
   *
   * @param resource $image
   *
   * @return Webim\Image\Layer
   */
  public static function fromResourceVar($image) {
    return new Layer($image);
  }

  /**
   * Initialize a layer from a string (obtains with file_get_contents, cURL...)
   *
   * This not recommended to initialize JPEG string with this method, GD displays
   * bugs !
   *
   * @param string $string
   *
   * @return Webim\Image\Layer
   *
   * @throws \Exception
   */
  public static function fromString($string) {
    if (!$image = @imageCreateFromString($string)) {
      throw new \Exception('Can\'t generate an image from the given string.', static::ERROR_CREATE_IMAGE_FROM_STRING);
    }

    return new Layer($image);
  }

  /**
   * Calculate the left top positions of a layer inside a parent layer container
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param int $containerWidth
   * @param int $containerHeight
   * @param int $layerWidth
   * @param int $layerHeight
   * @param int $layerPositionX
   * @param int $layerPositionY
   * @param string $position
   *
   * @return array
   */
  public static function calculatePositions($containerWidth, $containerHeight, $layerWidth, $layerHeight, $layerPositionX, $layerPositionY, $position = "LT") {
    $position = strtolower($position);

    if ($position == 'rt') {
      $layerPositionX = $containerWidth - $layerWidth - $layerPositionX;
    } elseif ($position == 'lb') {
      $layerPositionY = $containerHeight - $layerHeight - $layerPositionY;
    } elseif ($position == 'rb') {
      $layerPositionX = $containerWidth - $layerWidth - $layerPositionX;
      $layerPositionY = $containerHeight - $layerHeight - $layerPositionY;
    } elseif ($position == 'mm') {
      $layerPositionX = (($containerWidth - $layerWidth) / 2) + $layerPositionX;
      $layerPositionY = (($containerHeight - $layerHeight) / 2) + $layerPositionY;
    } elseif ($position == 'mt') {
      $layerPositionX = (($containerWidth - $layerWidth) / 2) + $layerPositionX;
    } elseif ($position == 'mb') {
      $layerPositionX = (($containerWidth - $layerWidth) / 2) + $layerPositionX;
      $layerPositionY = $containerHeight - $layerHeight - $layerPositionY;
    } elseif ($position == 'lm') {
      $layerPositionY = (($containerHeight - $layerHeight) / 2) + $layerPositionY;
    } elseif ($position == 'rm') {
      $layerPositionX = $containerWidth - $layerWidth - $layerPositionX;
      $layerPositionY = (($containerHeight - $layerHeight) / 2) + $layerPositionY;
    }

    return array(
      'x' => $layerPositionX,
      'y' => $layerPositionY
    );
  }

  /**
   * Copy an image on another one and converse transparency
   *
   * @param resource $destImg
   * @param resource $srcImg
   * @param int $destX
   * @param int $destY
   * @param int $srcX
   * @param int $srcY
   * @param int $srcW
   * @param int $srcH
   * @param int $pct
   */
  public static function copyMergeAlpha(&$destImg, &$srcImg, $destX, $destY, $srcX, $srcY, $srcW, $srcH, $pct = 0) {
    $destX = (int)$destX;
    $destY = (int)$destY;
    $srcX = (int)$srcX;
    $srcY = (int)$srcY;
    $srcW = (int)$srcW;
    $srcH = (int)$srcH;
    $pct = (int)$pct;
    $destW = imageSX($destImg);
    $destH = imageSY($destImg);

    for ($y = 0; $y < $srcH + $srcY; $y++) {
      for ($x = 0; $x < $srcW + $srcX; $x++) {
        if ($x + $destX >= 0 && $x + $destX < $destW && $x + $srcX >= 0 && $x + $srcX < $srcW && $y + $destY >= 0 && $y + $destY < $destH && $y + $srcY >= 0 && $y + $srcY < $srcH) {
          $destPixel = imagecolorsforindex($destImg, imageColorat($destImg, $x + $destX, $y + $destY));
          $srcImgColorat = imageColorat($srcImg, $x + $srcX, $y + $srcY);

          if ($srcImgColorat >= 0) {

            $srcPixel = imagecolorsforindex($srcImg, $srcImgColorat);

            $alpha = 0;
            $srcAlpha = 1 - ($srcPixel['alpha'] / 127);
            $destAlpha = 1 - ($destPixel['alpha'] / 127);
            $opacity = $srcAlpha * $pct / 100;

            if ($destAlpha >= $opacity) {
              $alpha = $destAlpha;
            }

            if ($destAlpha < $opacity) {
              $alpha = $opacity;
            }

            if ($alpha > 1) {
              $alpha = 1;
            }

            if ($opacity > 0) {
              $destRed = round((($destPixel['red'] * $destAlpha * (1 - $opacity))));
              $destGreen = round((($destPixel['green'] * $destAlpha * (1 - $opacity))));
              $destBlue = round((($destPixel['blue'] * $destAlpha * (1 - $opacity))));
              $srcRed = round((($srcPixel['red'] * $opacity)));
              $srcGreen = round((($srcPixel['green'] * $opacity)));
              $srcBlue = round((($srcPixel['blue'] * $opacity)));
              $red = round(($destRed + $srcRed) / ($destAlpha * (1 - $opacity) + $opacity));
              $green = round(($destGreen + $srcGreen) / ($destAlpha * (1 - $opacity) + $opacity));
              $blue = round(($destBlue + $srcBlue) / ($destAlpha * (1 - $opacity) + $opacity));

              if ($red > 255) {
                $red = 255;
              }

              if ($green > 255) {
                $green = 255;
              }

              if ($blue > 255) {
                $blue = 255;
              }

              $alpha = round((1 - $alpha) * 127);
              $color = imageColorAllocateAlpha($destImg, $red, $green, $blue, $alpha);
              imageSetPixel($destImg, $x + $destX, $y + $destY, $color);
            }
          }
        }
      }
    }
  }

  /**
   * Merge two image var
   *
   * @param resource $destinationImage
   * @param resource $sourceImage
   * @param int $destinationPosX
   * @param int $destinationPosY
   * @param int $sourcePosX
   * @param int $sourcePosY
   */
  public static function mergeTwoImages(&$destinationImage, $sourceImage, $destinationPosX = 0, $destinationPosY = 0, $sourcePosX = 0, $sourcePosY = 0) {
    imageCopy($destinationImage, $sourceImage, $destinationPosX, $destinationPosY, $sourcePosX, $sourcePosY, imageSX($sourceImage), imageSY($sourceImage));
  }

}