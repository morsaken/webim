<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\View;

class Renderer {

  /**
   * All of the finished, captured sections.
   *
   * @var array
   */
  protected $sections = array();

  /**
   * The stack of in-progress sections.
   *
   * @var array
   */
  protected $sectionStack = array();

  /**
   * The stack of in-progress loops.
   *
   * @var array
   */
  protected $loopsStack = [];

  /**
   * The number of active rendering operations.
   *
   * @var int
   */
  protected $renderCount = 0;

  /**
   * Start injecting content into a section.
   *
   * @param string $section
   * @param string $content
   *
   * @return void
   */
  public function startSection($section, $content = '') {
    if ($content === '') {
      ob_start() && ($this->sectionStack[] = $section);
    } else {
      $this->extendSection($section, $content);
    }
  }

  /**
   * Append content to a given section.
   *
   * @param string $section
   * @param string $content
   *
   * @return void
   */
  protected function extendSection($section, $content) {
    if (isset($this->sections[$section])) {
      $content = str_replace('@parent', trim($content), $this->sections[$section]);
    }

    $this->sections[$section] = trim($content, ' ');
  }

  /**
   * Stop injecting content into a section and return its contents.
   *
   * @return string
   */
  public function yieldSection() {
    return $this->yieldContent($this->stopSection());
  }

  /**
   * Get the string contents of a section.
   *
   * @param string $section
   * @param string $default
   *
   * @return string
   */
  public function yieldContent($section, $default = '') {
    $sectionContent = $default;

    if (isset($this->sections[$section])) {
      $sectionContent = $this->sections[$section];
    }

    return str_replace('@parent', '', $sectionContent);
  }

  /**
   * Stop injecting content into a section.
   *
   * @param bool $overwrite
   *
   * @return string
   */
  public function stopSection($overwrite = false) {
    $last = array_pop($this->sectionStack);

    if ($overwrite) {
      $this->sections[$last] = ob_get_clean();
    } else {
      $this->extendSection($last, ob_get_clean());
    }

    return $last;
  }

  /**
   * Stop injecting content into a section and append it.
   *
   * @return string
   */
  public function appendSection() {
    $last = array_pop($this->sectionStack);

    if (isset($this->sections[$last])) {
      $this->sections[$last] .= ob_get_clean();
    } else {
      $this->sections[$last] = ob_get_clean();
    }

    return $last;
  }

  /**
   * Flush all of the section contents if done rendering.
   *
   * @return void
   */
  public function flushSectionsIfDoneRendering() {
    if ($this->doneRendering()) $this->flushSections();
  }

  /**
   * Check if there are no active render operations.
   *
   * @return bool
   */
  public function doneRendering() {
    return $this->renderCount == 0;
  }

  /**
   * Flush all of the section contents.
   *
   * @return void
   */
  public function flushSections() {
    $this->sections = array();

    $this->sectionStack = array();
  }

  /**
   * Increment the rendering counter.
   *
   * @return void
   */
  public function incrementRender() {
    $this->renderCount++;
  }

  /**
   * Decrement the rendering counter.
   *
   * @return void
   */
  public function decrementRender() {
    $this->renderCount--;
  }

  /**
   * Get the entire array of sections.
   *
   * @return array
   */
  public function getSections() {
    return $this->sections;
  }

  /**
   * Add new loop to the stack.
   *
   * @param \Countable|array $data
   *
   * @return void
   */
  public function addLoop($data) {
    $length = is_array($data) || $data instanceof \Countable ? count($data) : null;
    $parent = array_last($this->loopsStack);
    $this->loopsStack[] = [
      'iteration' => 0,
      'index' => 0,
      'remaining' => isset($length) ? $length : null,
      'count' => $length,
      'first' => true,
      'last' => isset($length) ? $length == 1 : null,
      'depth' => count($this->loopsStack) + 1,
      'parent' => $parent ? (object)$parent : null,
    ];
  }

  /**
   * Increment the top loop's indices.
   *
   * @return void
   */
  public function incrementLoopIndices() {
    $loop = $this->loopsStack[$index = count($this->loopsStack) - 1];
    $this->loopsStack[$index] = array_merge($this->loopsStack[$index], [
      'iteration' => $loop['iteration'] + 1,
      'index' => $loop['iteration'],
      'first' => $loop['iteration'] == 0,
      'remaining' => isset($loop['count']) ? $loop['remaining'] - 1 : null,
      'last' => isset($loop['count']) ? $loop['iteration'] == $loop['count'] - 1 : null,
    ]);
  }

  /**
   * Pop a loop from the top of the loop stack.
   *
   * @return void
   */
  public function popLoop() {
    array_pop($this->loopsStack);
  }

  /**
   * Get an instance of the last loop in the stack.
   *
   * @return \stdClass|null
   */
  public function getLastLoop() {
    if ($last = array_last($this->loopsStack)) {
      return (object)$last;
    }

    return null;
  }

  /**
   * Get the entire loop stack.
   *
   * @return array
   */
  public function getLoopStack() {
    return $this->loopsStack;
  }

}