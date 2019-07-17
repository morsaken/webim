<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Image;

class Layer {

  /**
   * @var string
   */
  const UNIT_PIXEL = 'pixel';
  /**
   * @var string
   */
  const UNIT_PERCENT = 'percent';
  /**
   * @var integer
   */
  const ERROR_GD_NOT_INSTALLED = 1;
  /**
   * @var integer
   */
  const ERROR_PHP_IMAGE_VAR_NOT_USED = 2;
  /**
   * @var integer
   */
  const ERROR_FONT_NOT_FOUND = 3;
  /**
   * @var integer
   */
  const METHOD_DEPRECATED = 4;
  /**
   * @var integer
   */
  const ERROR_NEGATIVE_NUMBER_USED = 5;
  /**
   * @var $layers
   */
  public $layers;
  /**
   * @var $width
   */
  protected $width;
  /**
   * @var $height
   */
  protected $height;
  /**
   * @var $layerLevels
   */
  protected $layerLevels;
  /**
   * @var $layerPositions
   */
  protected $layerPositions;
  /**
   * @var $lastLayerId
   */
  protected $lastLayerId;
  /**
   * @var $highestLayerLevel
   */
  protected $highestLayerLevel;
  /**
   * @var resource
   */
  protected $image;

  /**
   * Constructor
   *
   * @param resource $image
   *
   * @throws \Exception
   */
  public function __construct($image) {
    if (!extension_loaded('gd')) {
      throw new \Exception('Imaging requires the GD extension to be loaded.', static::ERROR_GD_NOT_INSTALLED);
    }

    if ((gettype($image) != 'resource') && (gettype($image) != '\resource')) {
      throw new \Exception('You must give a php image var to initialize a layer.', static::ERROR_PHP_IMAGE_VAR_NOT_USED);
    }

    $this->width = imagesx($image);
    $this->height = imagesy($image);
    $this->image = $image;
    $this->layers = $this->layerLevels = $this->layerPositions = array();
    $this->clearStack();
  }

  /**
   * Reset the layer stack
   *
   * @param bool $deleteSubImgVar Delete sublayers image resource var
   */
  public function clearStack($deleteSubImgVar = true) {
    if ($deleteSubImgVar) {
      foreach ($this->layers as $layer) {
        $layer->delete();
      }
    }

    unset($this->layers);
    unset($this->layerLevels);
    unset($this->layerPositions);

    $this->lastLayerId = 0;
    $this->layers = array();
    $this->layerLevels = array();
    $this->layerPositions = array();
    $this->highestLayerLevel = 0;
  }

  /**
   * Clone method: use it if you want to reuse an existing ImageWorkshop object
   * in another variable
   * This is important because img resource var references all the same image in
   * PHP.
   * Example: $b = clone $a; (never do $b = $a;)
   */
  public function __clone() {
    $this->createNewVarFromBackgroundImage();
  }

  /**
   * Create a new background image var from the old background image var
   */
  public function createNewVarFromBackgroundImage() {
    $virginImage = Manager::generate($this->getWidth(), $this->getHeight());

    Manager::mergeTwoImages($virginImage, $this->image, 0, 0, 0, 0);
    unset($this->image);

    $this->image = $virginImage;
    unset($virginImage);

    $layers = $this->layers;

    foreach ($layers as $layerId => $layer) {
      $this->layers[$layerId] = clone $this->layers[$layerId];
    }
  }

  /**
   * Getter width
   *
   * @return int
   */
  public function getWidth() {
    return $this->width;
  }

  /**
   * Getter height
   *
   * @return int
   */
  public function getHeight() {
    return $this->height;
  }

  /**
   * Add an existing ImageWorkshop sublayer and set it in the stack at the
   * highest level
   * Return an array containing the generated sublayer id in the stack and the
   * highest level:
   * array("layerLevel" => integer, "id" => integer)
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param Webim\Image\Layer $layer
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   *
   * @return array
   */
  public function addLayerOnTop($layer, $positionX = 0, $positionY = 0, $position = "LT") {
    return $this->indexLayer($this->highestLayerLevel + 1, $layer, $positionX, $positionY, $position);
  }

  /**
   * Index a sublayer in the layer stack
   * Return an array containing the generated sublayer id and its final level:
   * array("layerLevel" => integer, "id" => integer)
   *
   * @param int $layerLevel
   * @param Webim\Image\Layer $layer
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   *
   * @return array
   */
  protected function indexLayer($layerLevel, $layer, $positionX = 0, $positionY = 0, $position) {
    // Choose an id for the added layer
    $layerId = $this->lastLayerId + 1;

    // Clone $layer to duplicate image resource var
    $layer = clone $layer;

    // Add the layer in the stack
    $this->layers[$layerId] = $layer;

    // Add the layer positions in the main layer
    $this->layerPositions[$layerId] = Manager::calculatePositions($this->getWidth(), $this->getHeight(), $layer->getWidth(), $layer->getHeight(), $positionX, $positionY, $position);

    // Update the lastLayerId of the workshop
    $this->lastLayerId = $layerId;

    // Add the layer level in the stack
    $layerLevel = $this->indexLevelInDocument($layerLevel, $layerId);

    return array(
      'layerLevel' => $layerLevel,
      'id' => $layerId
    );
  }

  /**
   * Index a layer level and update the layers levels in the document
   * Return the corrected level of the layer
   *
   * @param int $layerLevel
   * @param int $layerId
   *
   * @return int
   */
  protected function indexLevelInDocument($layerLevel, $layerId) {
    if (array_key_exists($layerLevel, $this->layerLevels)) { // Level already
      // exists
      ksort($this->layerLevels); // All layers after this level and the layer which
      // have this level are updated
      $layerLevelsTmp = $this->layerLevels;

      foreach ($layerLevelsTmp as $levelTmp => $layerIdTmp) {
        if ($levelTmp >= $layerLevel) {
          $this->layerLevels[$levelTmp + 1] = $layerIdTmp;
        }
      }

      unset($layerLevelsTmp);
    } else { // Level isn't taken
      if ($this->highestLayerLevel < $layerLevel) { // If given level is too high,
        // proceed adjustement
        $layerLevel = $this->highestLayerLevel + 1;
      }
    }

    $this->layerLevels[$layerLevel] = $layerId;
    $this->highestLayerLevel = max(array_flip($this->layerLevels)); // Update
    // $highestLayerLevel

    return $layerLevel;
  }

  /**
   * Add an existing ImageWorkshop sublayer and set it in the stack at level 1
   * Return an array containing the generated sublayer id in the stack and level
   * 1:
   * array("layerLevel" => integer, "id" => integer)
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param Webim\Image\Layer $layer
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   *
   * @return array
   */
  public function addLayerBelow($layer, $positionX = 0, $positionY = 0, $position = "LT") {
    return $this->indexLayer(1, $layer, $positionX, $positionY, $position);
  }

  /**
   * Move a sublayer on the top of a group stack
   * Return new sublayer level if success or false otherwise
   *
   * @param int $layerId
   *
   * @return mixed
   */
  public function moveTop($layerId) {
    return $this->moveTo($layerId, $this->highestLayerLevel, false);
  }

  /**
   * Move a sublayer to the level $level of a group stack
   * Return new sublayer level if success or false if layer isn't found
   *
   * Set $insertUnderTargetedLayer true if you want to move the sublayer under
   * the other sublayer at the targeted level,
   * or false to insert it on the top of the other sublayer at the targeted level
   *
   * @param int $layerId
   * @param int $level
   * @param bool $insertUnderTargetedLayer
   *
   * @return mixed
   */
  public function moveTo($layerId, $level, $insertUnderTargetedLayer = true) {
    // if the sublayer exists in stack
    if ($this->isLayerInIndex($layerId)) {

      $layerOldLevel = $this->getLayerLevel($layerId);

      if ($level < 1) {
        $level = 1;
        $insertUnderTargetedLayer = true;
      }

      if ($level > $this->highestLayerLevel) {
        $level = $this->highestLayerLevel;
        $insertUnderTargetedLayer = false;
      }

      // Not the same level than the current level
      if ($layerOldLevel != $level) {
        $isUnderAndNewLevelHigher = $isUnderAndNewLevelLower = $isOnTopAndNewLevelHigher = $isOnTopAndNewLevelLower = false;

        if ($insertUnderTargetedLayer) { // Under level
          if ($level > $layerOldLevel) { // new level higher
            $incrementorStartingValue = $layerOldLevel;
            $stopLoopWhenSmallerThan = $level;
            $isUnderAndNewLevelHigher = true;
          } else {
            $incrementorStartingValue = $level;
            $stopLoopWhenSmallerThan = $layerOldLevel;
            $isUnderAndNewLevelLower = true;
          }
        } else { // on the top
          if ($level > $layerOldLevel) { // new level higher
            $incrementorStartingValue = $layerOldLevel;
            $stopLoopWhenSmallerThan = $level;
            $isOnTopAndNewLevelHigher = true;
          } else { // new level lower
            $incrementorStartingValue = $level;
            $stopLoopWhenSmallerThan = $layerOldLevel;
            $isOnTopAndNewLevelLower = true;
          }
        }

        ksort($this->layerLevels);
        $layerLevelsTmp = $this->layerLevels;

        if ($isOnTopAndNewLevelLower) {
          $level++;
        }

        for ($i = $incrementorStartingValue; $i < $stopLoopWhenSmallerThan; $i++) {
          if ($isUnderAndNewLevelHigher || $isOnTopAndNewLevelHigher) {
            $this->layerLevels[$i] = $layerLevelsTmp[$i + 1];
          } else {
            $this->layerLevels[$i + 1] = $layerLevelsTmp[$i];
          }
        }

        unset($layerLevelsTmp);

        if ($isUnderAndNewLevelHigher) {
          $level--;
        }

        $this->layerLevels[$level] = $layerId;

        return $level;
      } else {
        return $level;
      }
    }

    return false;
  }

  /**
   * Check if a sublayer exists in the stack for a given id
   *
   * @param int $layerId
   *
   * @return bool
   */
  public function isLayerInIndex($layerId) {
    if (array_key_exists($layerId, $this->layers)) {
      return true;
    }

    return false;
  }

  /**
   * Get the level of a sublayer
   * Return sublayer level if success or false if layer isn't found
   *
   * @param int $layerId
   *
   * @return mixed (integer or boolean)
   */
  public function getLayerLevel($layerId) {
    if ($this->isLayerInIndex($layerId)) { // if the layer exists in document
      return array_search($layerId, $this->layerLevels);
    }

    return false;
  }

  /**
   * Move a sublayer to the level 1 of a group stack
   * Return new sublayer level if success or false otherwise
   *
   * @param int $layerId
   *
   * @return mixed
   */
  public function moveBottom($layerId) {
    return $this->moveTo($layerId, 1, true);
  }

  /**
   * Move up a sublayer in the stack (level +1)
   * Return new sublayer level if success, false otherwise
   *
   * @param int $layerId
   *
   * @return mixed
   */
  public function moveUp($layerId) {
    if ($this->isLayerInIndex($layerId)) { // if the sublayer exists in the stack
      $layerOldLevel = $this->getLayerLevel($layerId);

      return $this->moveTo($layerId, $layerOldLevel + 1, false);
    }

    return false;
  }

  /**
   * Move down a sublayer in the stack (level -1)
   * Return new sublayer level if success, false otherwise
   *
   * @param int $layerId
   *
   * @return mixed
   */
  public function moveDown($layerId) {
    if ($this->isLayerInIndex($layerId)) { // if the sublayer exists in the stack
      $layerOldLevel = $this->getLayerLevel($layerId);

      return $this->moveTo($layerId, $layerOldLevel - 1, true);
    }

    return false;
  }

  /**
   * Merge a sublayer with another sublayer below it in the stack
   * Note: the result layer will conserve the given id
   * Return true if success or false if layer isn't found or doesn't have a layer
   * under it in the stack
   *
   * @param int $layerId
   *
   * @return bool
   */
  public function mergeDown($layerId) {
    // if the layer exists in document
    if ($this->isLayerInIndex($layerId)) {
      $layerLevel = $this->getLayerLevel($layerId);
      $layerPositions = $this->getLayerPositions($layerId);
      $layer = $this->getLayer($layerId);
      $layerWidth = $layer->getWidth();
      $layerHeight = $layer->getHeight();
      $layerPositionX = $this->layerPositions[$layerId]['x'];
      $layerPositionY = $this->layerPositions[$layerId]['y'];

      if ($layerLevel > 1) {
        $underLayerId = $this->layerLevels[$layerLevel - 1];
        $underLayer = $this->getLayer($underLayerId);
        $underLayerWidth = $underLayer->getWidth();
        $underLayerHeight = $underLayer->getHeight();
        $underLayerPositionX = $this->layerPositions[$underLayerId]['x'];
        $underLayerPositionY = $this->layerPositions[$underLayerId]['y'];

        $totalWidthLayer = $layerWidth + $layerPositionX;
        $totalHeightLayer = $layerHeight + $layerPositionY;

        $totalWidthUnderLayer = $underLayerWidth + $underLayerPositionX;
        $totalHeightUnderLayer = $underLayerHeight + $underLayerPositionY;

        $minLayerPositionX = $layerPositionX;

        if ($layerPositionX > $underLayerPositionX) {
          $minLayerPositionX = $underLayerPositionX;
        }

        $minLayerPositionY = $layerPositionY;

        if ($layerPositionY > $underLayerPositionY) {
          $minLayerPositionY = $underLayerPositionY;
        }

        if ($totalWidthLayer > $totalWidthUnderLayer) {
          $layerTmpWidth = $totalWidthLayer - $minLayerPositionX;
        } else {
          $layerTmpWidth = $totalWidthUnderLayer - $minLayerPositionX;
        }

        if ($totalHeightLayer > $totalHeightUnderLayer) {
          $layerTmpHeight = $totalHeightLayer - $minLayerPositionY;
        } else {
          $layerTmpHeight = $totalHeightUnderLayer - $minLayerPositionY;
        }

        $layerTmp = Manager::virginLayer($layerTmpWidth, $layerTmpHeight);
        $layerTmp->addLayer(1, $underLayer, $underLayerPositionX - $minLayerPositionX, $underLayerPositionY - $minLayerPositionY);
        $layerTmp->addLayer(2, $layer, $layerPositionX - $minLayerPositionX, $layerPositionY - $minLayerPositionY);

        // Update layers
        $layerTmp->mergeAll();
        $this->layers[$underLayerId] = clone $layerTmp;
        $this->changePosition($underLayerId, $minLayerPositionX, $minLayerPositionX);
      } else {
        $layerTmp = Manager::fromResourceVar($this->image);
        $layerTmp->addLayer(1, $layer, $layerPositionX, $layerPositionY);

        $this->image = $layerTmp->getResult(); // Update background image
      }

      unset($layerTmp);
      $this->remove($layerId); // Remove the merged layer from the stack

      return true;
    }

    return false;
  }

  /**
   * Getter layerPositions
   *
   * Get all the positions of the sublayers,
   * or when specifying $layerId, get the position of this sublayer
   *
   * @param int $layerId
   *
   * @return mixed (array or boolean)
   */
  public function getLayerPositions($layerId = null) {
    if (!$layerId) {
      return $this->layerPositions;
    } elseif ($this->isLayerInIndex($layerId)) { // if the sublayer exists in the
      return $this->layerPositions[$layerId];
    }

    return false;
  }

  /**
   * Get a sublayer in the stack
   * Don't forget to use clone method: $b = clone $a->getLayer(3);
   *
   * @param int $layerId
   *
   * @return Webim\Image\Layer
   */
  public function getLayer($layerId) {
    return $this->layers[$layerId];
  }

  /**
   * Add an existing ImageWorkshop sublayer and set it in the stack at a given
   * level
   * Return an array containing the generated sublayer id in the stack and its
   * corrected level:
   * array("layerLevel" => integer, "id" => integer)
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param int $layerLevel
   * @param Webim\Image\Layer $layer
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   *
   * @return array
   */
  public function addLayer($layerLevel, $layer, $positionX = 0, $positionY = 0, $position = "LT") {
    return $this->indexLayer($layerLevel, $layer, $positionX, $positionY, $position);
  }

  /**
   * Merge sublayers in the stack on the layer background
   */
  public function mergeAll() {
    $this->image = $this->getResult();
    $this->clearStack();
  }

  /**
   * Return a merged resource image
   *
   * $backgroundColor is really usefull if you want to save a JPG or GIF, because
   * the transparency of the background
   * would be remove for a colored background, so you should choose a color like
   * "ffffff" (white)
   *
   * @param string $backgroundColor
   *
   * @return resource
   */
  public function getResult($backgroundColor = null) {
    $imagesToMerge = array();
    ksort($this->layerLevels);

    foreach ($this->layerLevels as $layerLevel => $layerId) {
      $imagesToMerge[$layerLevel] = $this->layers[$layerId]->getResult();

      // Layer positions
      if (($this->layerPositions[$layerId]['x'] != 0) || ($this->layerPositions[$layerId]['y'] != 0)) {
        $virginLayoutImageTmp = Manager::generate($this->width, $this->height);
        Manager::mergeTwoImages($virginLayoutImageTmp, $imagesToMerge[$layerLevel], $this->layerPositions[$layerId]['x'], $this->layerPositions[$layerId]['y'], 0, 0);
        $imagesToMerge[$layerLevel] = $virginLayoutImageTmp;
        unset($virginLayoutImageTmp);
      }
    }

    $iterator = 1;
    $mergedImage = $this->image;
    ksort($imagesToMerge);

    foreach ($imagesToMerge as $imageLevel => $image) {
      Manager::mergeTwoImages($mergedImage, $image);
      $iterator++;
    }

    $opacity = 127;

    if ($backgroundColor && $backgroundColor != "transparent") {
      $opacity = 0;
    }

    $backgroundImage = Manager::generate($this->width, $this->height, $backgroundColor, $opacity);
    Manager::mergeTwoImages($backgroundImage, $mergedImage);
    $mergedImage = $backgroundImage;
    unset($backgroundImage);

    return $mergedImage;
  }

  /**
   * Change the position of a sublayer for new positions
   *
   * @param int $layerId
   * @param int $newPosX
   * @param int $newPosY
   *
   * @return bool
   */
  public function changePosition($layerId, $newPosX = null, $newPosY = null) {
    // if the sublayer exists in the stack
    if ($this->isLayerInIndex($layerId)) {

      if ($newPosX !== null) {
        $this->layerPositions[$layerId]['x'] = $newPosX;
      }

      if ($newPosY !== null) {
        $this->layerPositions[$layerId]['y'] = $newPosY;
      }

      return true;
    }

    return false;
  }

  /**
   * Delete a layer (return true if success, false if no sublayer is found)
   *
   * @param int $layerId
   *
   * @return bool
   */
  public function remove($layerId) {
    // if the layer exists in document
    if ($this->isLayerInIndex($layerId)) {

      $layerToDeleteLevel = $this->getLayerLevel($layerId);

      // delete
      $this->layers[$layerId]->delete();
      unset($this->layers[$layerId]);
      unset($this->layerLevels[$layerToDeleteLevel]);
      unset($this->layerPositions[$layerId]);

      // One or plural layers are sub of the deleted layer
      if (array_key_exists(($layerToDeleteLevel + 1), $this->layerLevels)) {
        ksort($this->layerLevels);

        $layerLevelsTmp = $this->layerLevels;

        $maxOldestLevel = 1;

        foreach ($layerLevelsTmp as $levelTmp => $layerIdTmp) {
          if ($levelTmp > $layerToDeleteLevel) {
            $this->layerLevels[($levelTmp - 1)] = $layerIdTmp;
          }

          $maxOldestLevel++;
        }
        unset($layerLevelsTmp);
        unset($this->layerLevels[$maxOldestLevel]);
      }

      $this->highestLayerLevel--;

      return true;
    }

    return false;
  }

  /**
   * Paste an image on the layer
   * You can specify the position left (in pixels) and the position top (in
   * pixels) of the added image relatives to the layer
   * Otherwise, it will be set at 0 and 0
   *
   * @param string $unit Use one of `UNIT_*` constants, "UNIT_PIXEL" by default
   * @param resource $image
   * @param int $positionX
   * @param int $positionY
   */
  public function pasteImage($unit = self::UNIT_PIXEL, $image, $positionX = 0, $positionY = 0) {
    if ($unit == self::UNIT_PERCENT) {

      $positionX = round(($positionX / 100) * $this->width);
      $positionY = round(($positionY / 100) * $this->height);
    }

    imagecopy($this->image, $image, $positionX, $positionY, 0, 0, $image->getWidth(), $image->getHeight());
  }

  /**
   * Resize the layer by specifying a percent
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param float $percentWidth
   * @param float $percentHeight
   * @param bool $converseProportion
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   */
  public function resizeInPercent($percentWidth = null, $percentHeight = null, $converseProportion = false, $positionX = 0, $positionY = 0, $position = 'MM') {
    $this->resize(self::UNIT_PERCENT, $percentWidth, $percentHeight, $converseProportion, $positionX, $positionY, $position);
  }

  /**
   * Resize the layer by its largest side by specifying pixel
   *
   * @param int $newLargestSideWidth
   * @param bool $converseProportion
   */
  public function resizeByLargestSideInPixel($newLargestSideWidth, $converseProportion = false) {
    $this->resizeByLargestSide(self::UNIT_PIXEL, $newLargestSideWidth, $converseProportion);
  }

  /**
   * Resize the layer by its largest side
   *
   * @param string $unit
   * @param int $newLargestSideWidth
   * @param bool $converseProportion
   */
  public function resizeByLargestSide($unit = self::UNIT_PIXEL, $newLargestSideWidth, $converseProportion = false) {
    if ($unit == self::UNIT_PERCENT) {
      $newLargestSideWidth = round(($newLargestSideWidth / 100) * $this->getLargestSideWidth());
    }

    if ($this->getWidth() > $this->getHeight()) {
      $this->resizeInPixel($newLargestSideWidth, null, $converseProportion);
    } else {
      $this->resizeInPixel(null, $newLargestSideWidth, $converseProportion);
    }
  }

  /**
   * Return the largest side width of the layer
   *
   * @return int
   */
  public function getLargestSideWidth() {
    $largestSideWidth = $this->getWidth();

    if ($this->getHeight() > $largestSideWidth) {
      $largestSideWidth = $this->getHeight();
    }

    return $largestSideWidth;
  }

  /**
   * Resize the layer by specifying pixel
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param int $newWidth
   * @param int $newHeight
   * @param bool $converseProportion
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   */
  public function resizeInPixel($newWidth = null, $newHeight = null, $converseProportion = false, $positionX = 0, $positionY = 0, $position = "MM") {
    $this->resize(self::UNIT_PIXEL, $newWidth, $newHeight, $converseProportion, $positionX, $positionY, $position);
  }

  /**
   * Resize the layer
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param string $unit
   * @param mixed $newWidth
   * @param mixed $newHeight
   * @param bool $converseProportion
   * @param mixed $positionX
   * @param mixed $positionY
   * @param string $position
   */
  public function resize($unit = self::UNIT_PIXEL, $newWidth = null, $newHeight = null, $converseProportion = false, $positionX = 0, $positionY = 0, $position = 'MM') {
    if (is_numeric($newWidth) || is_numeric($newHeight)) {
      if ($unit == self::UNIT_PERCENT) {
        if ($newWidth) {
          $newWidth = round(($newWidth / 100) * $this->width);
        }

        if ($newHeight) {
          $newHeight = round(($newHeight / 100) * $this->height);
        }
      }

      if (is_numeric($newWidth) && $newWidth <= 0) {
        $newWidth = 1;
      }

      if (is_numeric($newHeight) && $newHeight <= 0) {
        $newHeight = 1;
      }

      if ($converseProportion) { // Proportion are conserved
        if ($newWidth && $newHeight) { // Proportions + $newWidth + $newHeight
          if ($this->getWidth() > $this->getHeight()) {
            $this->resizeInPixel($newWidth, null, true);

            if ($this->getHeight() > $newHeight) {
              $this->resizeInPixel(null, $newHeight, true);
            }
          } else {
            $this->resizeInPixel(null, $newHeight, true);

            if ($this->getWidth() > $newWidth) {
              $this->resizeInPixel($newWidth, null, true);
            }
          }

          if ($this->getWidth() != $newWidth || $this->getHeight() != $newHeight) {
            $layerTmp = Manager::virginLayer($newWidth, $newHeight);
            $layerTmp->addLayer(1, $this, $positionX, $positionY, $position);

            // Reset part of stack
            unset($this->image);
            unset($this->layerLevels);
            unset($this->layerPositions);
            unset($this->layers);

            // Update current object
            $this->width = $layerTmp->getWidth();
            $this->height = $layerTmp->getHeight();
            $this->layerLevels = $layerTmp->layers[1]->getLayerLevels();
            $this->layerPositions = $layerTmp->layers[1]->getLayerPositions();
            $this->layers = $layerTmp->layers[1]->getLayers();
            $this->lastLayerId = $layerTmp->layers[1]->getLastLayerId();
            $this->highestLayerLevel = $layerTmp->layers[1]->getHighestLayerLevel();

            $translations = $layerTmp->getLayerPositions(1);

            foreach ($this->layers as $id => $layer) {
              $this->applyTranslation($id, $translations['x'], $translations['y']);
            }

            $layerTmp->layers[1]->clearStack(false);
            $this->image = $layerTmp->getResult();
            unset($layerTmp);
          }

          return;
        } elseif ($newWidth) {
          $widthResizePercent = $newWidth / ($this->width / 100);
          $newHeight = round(($widthResizePercent / 100) * $this->height);
          $heightResizePercent = $widthResizePercent;
        } elseif ($newHeight) {
          $heightResizePercent = $newHeight / ($this->height / 100);
          $newWidth = round(($heightResizePercent / 100) * $this->width);
          $widthResizePercent = $heightResizePercent;
        }
      } elseif (($newWidth && !$newHeight) || (!$newWidth && $newHeight)) {
        if ($newWidth) {
          $widthResizePercent = $newWidth / ($this->width / 100);
          $heightResizePercent = 100;
          $newHeight = $this->height;
        } else {
          $heightResizePercent = $newHeight / ($this->height / 100);
          $widthResizePercent = 100;
          $newWidth = $this->width;
        }
      } else { // New width AND new height are given
        $widthResizePercent = $newWidth / ($this->width / 100);
        $heightResizePercent = $newHeight / ($this->height / 100);
      }

      // Update the layer positions in the stack
      foreach ($this->layerPositions as $layerId => $layerPosition) {
        $newPosX = round(($widthResizePercent / 100) * $layerPosition['x']);
        $newPosY = round(($heightResizePercent / 100) * $layerPosition['y']);

        $this->changePosition($layerId, $newPosX, $newPosY);
      }

      // Resize layers in the stack
      $layers = $this->layers;

      foreach ($layers as $key => $layer) {
        $layer->resizeInPercent($widthResizePercent, $heightResizePercent);
        $this->layers[$key] = $layer;
      }

      $this->resizeBackground($newWidth, $newHeight); // Resize the layer
    }
  }

  /**
   * Apply a translation on a sublayer that change its positions
   *
   * @param int $layerId
   * @param int $addedPosX
   * @param int $addedPosY
   *
   * @return mixed (array of new positions or false if fail)
   */
  public function applyTranslation($layerId, $addedPosX = null, $addedPosY = null) {
    // if the sublayer exists in the stack
    if ($this->isLayerInIndex($layerId)) {
      if ($addedPosX !== null) {
        $this->layerPositions[$layerId]['x'] += $addedPosX;
      }

      if ($addedPosY !== null) {
        $this->layerPositions[$layerId]['y'] += $addedPosY;
      }

      return $this->layerPositions[$layerId];
    }

    return false;
  }

  /**
   * Resize the background of a layer
   *
   * @param int $newWidth
   * @param int $newHeight
   */
  public function resizeBackground($newWidth, $newHeight) {
    $oldWidth = $this->width;
    $oldHeight = $this->height;

    $this->width = $newWidth;
    $this->height = $newHeight;

    $virginLayoutImage = Manager::generate($this->width, $this->height);

    imagecopyresampled($virginLayoutImage, $this->image, 0, 0, 0, 0, $this->width, $this->height, $oldWidth, $oldHeight);

    unset($this->image);
    $this->image = $virginLayoutImage;
  }

  /**
   * Resize the layer by its largest side by specifying percent
   *
   * @param int $newLargestSideWidth
   * @param bool $converseProportion
   */
  public function resizeByLargestSideInPercent($newLargestSideWidth, $converseProportion = false) {
    $this->resizeByLargestSide(self::UNIT_PERCENT, $newLargestSideWidth, $converseProportion);
  }

  /**
   * Resize the layer by its narrow side by specifying pixel
   *
   * @param int $newNarrowSideWidth
   * @param bool $converseProportion
   */
  public function resizeByNarrowSideInPixel($newNarrowSideWidth, $converseProportion = false) {
    $this->resizeByNarrowSide(self::UNIT_PIXEL, $newNarrowSideWidth, $converseProportion);
  }

  /**
   * Resize the layer by its narrow side
   *
   * @param string $unit
   * @param int $newNarrowSideWidth
   * @param bool $converseProportion
   */
  public function resizeByNarrowSide($unit = self::UNIT_PIXEL, $newNarrowSideWidth, $converseProportion = false) {
    if ($unit == self::UNIT_PERCENT) {
      $newNarrowSideWidth = round(($newNarrowSideWidth / 100) * $this->getNarrowSideWidth());
    }

    if ($this->getWidth() < $this->getHeight()) {
      $this->resizeInPixel($newNarrowSideWidth, null, $converseProportion);
    } else {
      $this->resizeInPixel(null, $newNarrowSideWidth, $converseProportion);
    }
  }

  /**
   * Return the narrow side width of the layer
   *
   * @return int
   */
  public function getNarrowSideWidth() {
    $narrowSideWidth = $this->getWidth();

    if ($this->getHeight() < $narrowSideWidth) {
      $narrowSideWidth = $this->getHeight();
    }

    return $narrowSideWidth;
  }

  /**
   * Resize the layer by its narrow side by specifying percent
   *
   * @param int $newNarrowSideWidth
   * @param bool $converseProportion
   */
  public function resizeByNarrowSideInPercent($newNarrowSideWidth, $converseProportion = false) {
    $this->resizeByNarrowSide(self::UNIT_PERCENT, $newNarrowSideWidth, $converseProportion);
  }

  /**
   * Crop the document by specifying percent
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param int $percentWidth
   * @param int $percentHeight
   * @param int $positionXPercent
   * @param int $positionYPercent
   * @param string $position
   */
  public function cropInPercent($percentWidth = 0, $percentHeight = 0, $positionXPercent = 0, $positionYPercent = 0, $position = 'LT') {
    $this->crop(self::UNIT_PERCENT, $percentWidth, $percentHeight, $positionXPercent, $positionYPercent, $position);
  }

  /**
   * Crop the maximum possible from left top ("LT"), "RT"...
   * by specifying a shift in pixel
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   */
  public function cropMaximumInPixel($positionX = 0, $positionY = 0, $position = 'LT') {
    $this->cropMaximum(self::UNIT_PIXEL, $positionX, $positionY, $position);
  }

  /**
   * Crop the maximum possible from left top
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param string $unit
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   */
  public function cropMaximum($unit = self::UNIT_PIXEL, $positionX = 0, $positionY = 0, $position = 'LT') {
    $narrowSide = $this->getNarrowSideWidth();

    if ($unit == self::UNIT_PERCENT) {
      $positionX = round(($positionX / 100) * $this->width);
      $positionY = round(($positionY / 100) * $this->height);
    }

    $this->cropInPixel($narrowSide, $narrowSide, $positionX, $positionY, $position);
  }

  /**
   * Crop the document by specifying pixels
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param int $width
   * @param int $height
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   */
  public function cropInPixel($width = 0, $height = 0, $positionX = 0, $positionY = 0, $position = 'LT') {
    $this->crop(self::UNIT_PIXEL, $width, $height, $positionX, $positionY, $position);
  }

  /**
   * Crop the document
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param string $unit
   * @param int $width
   * @param int $height
   * @param int $positionX
   * @param int $positionY
   * @param string $position
   *
   * @throws \Exception
   */
  public function crop($unit = self::UNIT_PIXEL, $width = 0, $height = 0, $positionX = 0, $positionY = 0, $position = 'LT') {
    if ($width < 0 || $height < 0) {
      throw new \Exception('You can\'t use negative $width or $height for "' . __METHOD__ . '" method.', static::ERROR_NEGATIVE_NUMBER_USED);
    }

    if ($unit == self::UNIT_PERCENT) {
      $width = round(($width / 100) * $this->width);
      $height = round(($height / 100) * $this->height);

      $positionX = round(($positionX / 100) * $this->width);
      $positionY = round(($positionY / 100) * $this->height);
    }

    if (($width != $this->width || $positionX == 0) || ($height != $this->height || $positionY == 0)) {
      if ($width == 0) {
        $width = 1;
      }

      if ($height == 0) {
        $height = 1;
      }

      $layerTmp = Manager::virginLayer($width, $height);
      $layerClone = Manager::virginLayer($this->width, $this->height);

      imagedestroy($layerClone->image);
      $layerClone->image = $this->image;

      $layerTmp->addLayer(1, $layerClone, -$positionX, -$positionY, $position);

      $newPos = $layerTmp->getLayerPositions();
      $layerNewPosX = $newPos[1]['x'];
      $layerNewPosY = $newPos[1]['y'];

      // update the layer
      $this->width = $layerTmp->getWidth();
      $this->height = $layerTmp->getHeight();
      $this->image = $layerTmp->getResult();
      unset($layerTmp);
      unset($layerClone);

      $this->updateLayerPositionsAfterCropping($layerNewPosX, $layerNewPosY);
    }
  }

  /**
   * Update the positions of layers in the stack after cropping
   *
   * @param int $positionX
   * @param int $positionY
   */
  public function updateLayerPositionsAfterCropping($positionX, $positionY) {
    foreach ($this->layers as $layerId => $layer) {
      $oldLayerPosX = $this->layerPositions[$layerId]['x'];
      $oldLayerPosY = $this->layerPositions[$layerId]['y'];

      $newLayerPosX = $oldLayerPosX + $positionX;
      $newLayerPosY = $oldLayerPosY + $positionY;

      $this->changePosition($layerId, $newLayerPosX, $newLayerPosY);
    }
  }

  /**
   * Crop the maximum possible from left top ("LT"), "RT"...
   * by specifying a shift in percent
   *
   * @see http://phpimageworkshop.com/doc/22/corners-positions-schema-of-an-image.html
   *
   * @param int $positionXPercent
   * @param int $positionYPercent
   * @param string $position
   */
  public function cropMaximumInPercent($positionXPercent = 0, $positionYPercent = 0, $position = 'LT') {
    $this->cropMaximum(self::UNIT_PERCENT, $positionXPercent, $positionYPercent, $position);
  }

  /**
   * Rotate the layer (in degree)
   *
   * @param float $degrees
   */
  public function rotate($degrees) {
    if ($degrees != 0) {
      if ($degrees < -360 || $degrees > 360) {
        $degrees = $degrees % 360;
      }

      if ($degrees < 0 && $degrees >= -360) {
        $degrees = 360 + $degrees;
      }

      // Rotate the layer background image
      $imageRotated = imagerotate($this->image, -$degrees, -1);
      imagealphablending($imageRotated, true);
      imagesavealpha($imageRotated, true);

      unset($this->image);

      $this->image = $imageRotated;

      $oldWidth = $this->width;
      $oldHeight = $this->height;

      $this->width = imagesx($this->image);
      $this->height = imagesy($this->image);

      foreach ($this->layers as $layerId => $layer) {
        $layerSelfOldCenterPosition = array(
          'x' => $layer->width / 2,
          'y' => $layer->height / 2
        );

        $smallImageCenter = array(
          'x' => $layerSelfOldCenterPosition['x'] + $this->layerPositions[$layerId]['x'],
          'y' => $layerSelfOldCenterPosition['y'] + $this->layerPositions[$layerId]['y']
        );

        $this->layers[$layerId]->rotate($degrees);

        $ro = sqrt(pow($smallImageCenter['x'], 2) + pow($smallImageCenter['y'], 2));

        $teta = (acos($smallImageCenter['x'] / $ro)) * 180 / pi();

        $a = $ro * cos(($teta + $degrees) * pi() / 180);
        $b = $ro * sin(($teta + $degrees) * pi() / 180);

        if ($degrees > 0 && $degrees <= 90) {
          $newPositionX = $a - ($this->layers[$layerId]->width / 2) + $oldHeight * sin(($degrees * pi()) / 180);
          $newPositionY = $b - ($this->layers[$layerId]->height / 2);
        } elseif ($degrees > 90 && $degrees <= 180) {
          $newPositionX = $a - ($this->layers[$layerId]->width / 2) + $this->width;
          $newPositionY = $b - ($this->layers[$layerId]->height / 2) + $oldHeight * (-cos(($degrees) * pi() / 180));
        } elseif ($degrees > 180 && $degrees <= 270) {
          $newPositionX = $a - ($this->layers[$layerId]->width / 2) + $oldWidth * (-cos(($degrees) * pi() / 180));
          $newPositionY = $b - ($this->layers[$layerId]->height / 2) + $this->height;
        } else {
          $newPositionX = $a - ($this->layers[$layerId]->width / 2);
          $newPositionY = $b - ($this->layers[$layerId]->height / 2) + $oldWidth * (-sin(($degrees) * pi() / 180));
        }

        $this->layerPositions[$layerId] = array(
          'x' => $newPositionX,
          'y' => $newPositionY
        );
      }
    }
  }

  /**
   * Change the opacity of the layer
   *
   * @param int $opacity
   * @param bool $recursive
   */
  public function opacity($opacity, $recursive = true) {
    if ($recursive) {
      $layers = $this->layers;

      foreach ($layers as $key => $layer) {
        $layer->opacity($opacity, true);
        $this->layers[$key] = $layer;
      }
    }

    $transparentImage = Manager::generate($this->getWidth(), $this->getHeight());

    Manager::copyMergeAlpha($transparentImage, $this->image, 0, 0, 0, 0, $this->getWidth(), $this->getHeight(), $opacity);

    unset($this->image);
    $this->image = $transparentImage;
    unset($transparentImage);
  }

  /**
   * Apply a filter on the layer
   * Be careful: some filters can damage transparent img, use it sparingly !
   * (A good pratice is to use mergeAll on your layer before applying a filter)
   *
   * @param int $filterType
   * @param int $arg1
   * @param int $arg2
   * @param int $arg3
   * @param int $arg4
   * @param bool $recursive
   */
  public function applyFilter($filterType, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null, $recursive = false) {
    if ($filterType == IMG_FILTER_COLORIZE) {
      imagefilter($this->image, $filterType, $arg1, $arg2, $arg3, $arg4);
    } elseif ($filterType == IMG_FILTER_BRIGHTNESS || $filterType == IMG_FILTER_CONTRAST || $filterType == IMG_FILTER_SMOOTH) {
      imagefilter($this->image, $filterType, $arg1);
    } elseif ($filterType == IMG_FILTER_PIXELATE) {
      imagefilter($this->image, $filterType, $arg1, $arg2);
    } else {
      imagefilter($this->image, $filterType);
    }

    if ($recursive) {
      $layers = $this->layers;

      foreach ($layers as $layerId => $layer) {
        $this->layers[$layerId]->applyFilter($filterType, $arg1, $arg2, $arg3, $arg4, true);
      }
    }
  }

  /**
   * Apply horizontal or vertical flip (Transformation)
   *
   * @param string $type
   */
  public function flip($type = 'horizontal') {
    $layers = $this->layers;

    foreach ($layers as $key => $layer) {
      $layer->flip($type);
      $this->layers[$key] = $layer;
    }

    $temp = Manager::generate($this->width, $this->height);

    if ($type == 'horizontal') {
      imagecopyresampled($temp, $this->image, 0, 0, $this->width - 1, 0, $this->width, $this->height, -$this->width, $this->height);
      $this->image = $temp;

      foreach ($this->layerPositions as $layerId => $layerPositions) {
        $this->changePosition($layerId, $this->width - $this->layers[$layerId]->getWidth() - $layerPositions['x'], $layerPositions['y']);
      }
    } elseif ($type == 'vertical') {
      imagecopyresampled($temp, $this->image, 0, 0, 0, $this->height - 1, $this->width, $this->height, $this->width, -$this->height);
      $this->image = $temp;

      foreach ($this->layerPositions as $layerId => $layerPositions) {
        $this->changePosition($layerId, $layerPositions['x'], $this->height - $this->layers[$layerId]->getHeight() - $layerPositions['y']);
      }
    }

    unset($temp);
  }

  /**
   * Add a text on the background image of the layer using a default font
   * registered in GD
   *
   * @param string $text
   * @param int $font
   * @param string $color
   * @param int $positionX
   * @param int $positionY
   * @param string $align
   */
  public function writeText($text, $font = 1, $color = 'ffffff', $positionX = 0, $positionY = 0, $align = 'horizontal') {
    $RGBTextColor = Manager::hexToRgb($color);
    $textColor = imagecolorallocate($this->image, $RGBTextColor['R'], $RGBTextColor['G'], $RGBTextColor['B']);

    if ($align == 'horizontal') {
      imagestring($this->image, $font, $positionX, $positionY, $text, $textColor);
    } else {
      imagestringup($this->image, $font, $positionX, $positionY, $text, $textColor);
    }
  }

  /**
   * Add a text on the background image of the layer using a font localized at
   * $fontPath
   *
   * @param string $text
   * @param int $fontPath
   * @param int $fontSize
   * @param string $color
   * @param int $positionX
   * @param int $positionY
   * @param int $fontRotation
   *
   * @return array
   *
   * @throws \Exception
   */
  public function write($text, $fontPath, $fontSize = 13, $color = 'ffffff', $positionX = 0, $positionY = 0, $fontRotation = 0) {
    if (!file_exists($fontPath)) {
      throw new \Exception('Can\'t find a font file at this path : "' . $fontPath . '".', static::ERROR_FONT_NOT_FOUND);
    }

    $RGBTextColor = Manager::hexToRgb($color);
    $textColor = imagecolorallocate($this->image, $RGBTextColor['R'], $RGBTextColor['G'], $RGBTextColor['B']);

    return imagettftext($this->image, $fontSize, $fontRotation, $positionX, $positionY, $textColor, $fontPath, $text);
  }

  /**
   * Save the resulting image at the specified path
   *
   * $backgroundColor is really usefull if you want to save a JPG or GIF, because
   * the transparency of the background
   * would be remove for a colored background, so you should choose a color like
   * "ffffff" (white)
   *
   * If the file already exists, it will be override !
   *
   * $imageQuality is useless for GIF
   *
   * Ex: $folder = __DIR__."/../web/img/2012"
   * $imageName = "butterfly.jpg"
   * $createFolders = true
   * $imageQuality = 95
   * $backgroundColor = "ffffff"
   *
   * @param string $folder
   * @param string $imageName
   * @param bool $createFolders
   * @param string $backgroundColor
   * @param int $imageQuality
   * @param bool $interlace
   */
  public function save($folder, $imageName, $createFolders = true, $backgroundColor = null, $imageQuality = 75, $interlace = false) {
    if (!is_file($folder)) {
      if (is_dir($folder) || $createFolders) {
        // Creating the folders if they don't exist
        if (!is_dir($folder) && $createFolders) {
          $oldUmask = umask(0);
          mkdir($folder, 0777, true);
          umask($oldUmask);
          chmod($folder, 0777);
        }

        $extension = explode('.', $imageName);
        $extension = strtolower($extension[count($extension) - 1]);

        $filename = $folder . '/' . $imageName;

        if (($extension == 'jpg' || $extension == 'jpeg' || $extension == 'gif') && (!$backgroundColor || $backgroundColor == 'transparent')) {
          $backgroundColor = 'ffffff';
        }

        $image = $this->getResult($backgroundColor);

        imageinterlace($image, (int)$interlace);

        if ($extension == 'jpg' || $extension == 'jpeg') {
          imagejpeg($image, $filename, $imageQuality);
          unset($image);
        } elseif ($extension == 'gif') {
          imagegif($image, $filename);
          unset($image);
        } elseif ($extension == 'png') {
          $imageQuality = $imageQuality / 10;
          $imageQuality -= 1;

          imagepng($image, $filename, $imageQuality);
          unset($image);
        }
      }
    }
  }

  /**
   * Getter image
   *
   * @return resource
   */
  public function getImage() {
    return $this->image;
  }

  /**
   * Getter layers
   *
   * @return array
   */
  public function getLayers() {
    return $this->layers;
  }

  /**
   * Getter layerLevels
   *
   * @return array
   */
  public function getLayerLevels() {
    return $this->layerLevels;
  }

  /**
   * Getter highestLayerLevel
   *
   * @return array
   */
  public function getHighestLayerLevel() {
    return $this->highestLayerLevel;
  }

  /**
   * Getter lastLayerId
   *
   * @return array
   */
  public function getLastLayerId() {
    return $this->lastLayerId;
  }

  /**
   * Delete the current object
   */
  public function delete() {
    imagedestroy($this->image);
    $this->clearStack();
  }

}