<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

class Form {

  /**
   * Valid elements
   *
   * @var array
   */
  protected $els = array(
    'input' => array(
      'button', 'checkbox', 'color', 'date', 'datetime', 'datetime-local',
      'email', 'file', 'hidden', 'image', 'month', 'number', 'password',
      'radio', 'range', 'reset', 'search', 'submit', 'tel', 'text',
      'time', 'url', 'week'
    ),
    'radio' => array(),
    'checkbox' => array(),
    'select' => array(),
    'textarea' => array(),
    'button' => array(
      'button', 'submit', 'reset'
    )
  );

  /**
   * Tag name
   *
   * @var string
   */
  protected $el = 'input';

  /**
   * Element type
   *
   * @var NULL|string
   */
  protected $type = null;

  /**
   * Option list
   *
   * @var array
   */
  protected $opts = array();

  /**
   * Option attributes
   *
   * @var array
   */
  protected $optAttrs = array();

  /**
   * Element attributes
   *
   * @var array
   */
  protected $attrs = array();

  /**
   * Element styles
   *
   * @var array
   */
  protected $styles = array();

  /**
   * Element value
   *
   * @var string
   */
  protected $val = '';

  /**
   * Constructor
   *
   * @param string $el
   * @param string $type
   */
  public function __construct($el, $type = null) {
    if (isset($this->els['button'][$el])) {
      $type = $el;
      $el = 'button';
    } elseif (isset($this->els['input'][$el])) {
      $type = $el;
      $el = 'input';
    } elseif (!isset($this->els[$el])) {
      $el = 'input';
    }

    //Element
    $this->el = $el;

    //Element type
    $this->type = (in_array($type, $this->els[$this->el]) ? $type : current($this->els[$this->el]));

    //Set default attribute
    $this->attr('name', uniqid());
  }

  /**
   * Element attribute
   *
   * @param string $key
   * @param string $value
   * @param boolean $check
   *
   * @return Form
   */
  public function attr($key, $value = null, $check = false) {
    //Trim
    $key = trim($key);
    $value = trim($value);

    if (!$check || !isset($this->attrs[$key])) {
      $this->attrs[$key] = (is_null($value) ? $key : $value);
    }

    return $this;
  }

  /**
   * Call static element
   *
   * @param string $element
   * @param array $args
   *
   * @return Form
   */
  public static function __callstatic($element, $args = array()) {
    return static::element($element, array_get($args, 0));
  }

  /**
   * Element
   *
   * @param string $name
   * @param string $type
   *
   * @return Form
   */
  public static function element($name = 'input', $type = null) {
    return new static($name, $type);
  }

  /**
   * Element name
   *
   * @param string $value
   *
   * @return Form
   */
  public function name($value) {
    $this->attr('name', $value);

    if (!array_get($this->attrs, 'id')) {
      $this->attr('id', $value);
    }

    return $this;
  }

  /**
   * Element value
   *
   * @param string $value
   *
   * @return Form
   */
  public function val($value = '') {
    $this->val = $value;

    return $this;
  }

  /**
   * Option attributes
   *
   * @param array $options
   *
   * @return Form
   */
  public function optAttrs($options = array()) {
    foreach ($options as $optKey => $attributes) {
      foreach ($attributes as $key => $value) {
        $this->optAttr($optKey, $key, $value);
      }
    }

    return $this;
  }

  /**
   * Element attributes
   *
   * @param array $attributes
   *
   * @return Form
   */
  public function attrs($attributes = array()) {
    foreach ($attributes as $key => $value) {
      $this->attr($key, $value);
    }

    return $this;
  }

  /**
   * Element styles
   *
   * @param array $styles
   *
   * @return Form
   */
  public function styles($styles = array()) {
    foreach ($styles as $key => $value) {
      $this->style($key, $value);
    }

    return $this;
  }

  /**
   * Element style
   *
   * @param string $key
   * @param string $value
   * @param bool $check
   *
   * @return Form
   */
  public function style($key, $value, $check = false) {
    //Trim
    $key = trim($key);
    $value = trim($value);

    if (!$check || !isset($this->styles[$key])) {
      $this->styles[$key] = $value;
    }

    return $this;
  }

  /**
   * Radio or checkbox labels
   *
   * @param array $labels
   *
   * @return Form
   */
  public function labels($labels = array()) {
    foreach ($labels as $key => $value) {
      $this->label($key, $value);
    }

    return $this;
  }

  /**
   * Radio or checkbox label
   *
   * @param string $key
   * @param string $value
   *
   * @return Form
   */
  public function label($key, $value = null) {
    $this->opt($key, $value);

    return $this;
  }

  /**
   * Element option
   *
   * @param string $key
   * @param string $value
   * @param bool $check
   *
   * @return Form
   */
  public function opt($key, $value = null, $check = false) {
    //Trim
    $key = trim($key);

    if (!strlen($key)) $key = 0;

    $value = trim($value);

    if (!$check || !isset($this->opts[$key])) {
      $this->opts[$key] = (is_null($value) ? '' : $value);
    }

    return $this;
  }

  /**
   * Option filling
   *
   * @param mixed $start
   * @param mixed $end
   * @param array $params
   *
   * @return Form
   */
  public function fill($start, $end, $params = array()) {
    //Fill elements
    $options = array();

    if ((int)$start > (int)$end) {
      $x = (int)$end;
      $y = (int)$start;
    } else {
      $x = (int)$start;
      $y = (int)$end;
    }

    for (; $x <= $y; $x++) {
      //Text is default x
      $text = str_pad(
        (string)$x,
        array_get($params, 'pad_length', 0),
        array_get($params, 'pad_string', ''),
        (array_get($params, 'pad_type', 'left') ? STR_PAD_LEFT : STR_PAD_RIGHT)
      );

      if (array_get($params, 'format')) {
        //Formatted
        $text = sprintf(array_get($params, 'format'), $text);
      }

      $options[$x] = $text;
    }

    //Change order
    if ($start > $end) {
      $options = array_reverse($options, true);
    }

    //Add to options
    $this->opts($options);

    return $this;
  }

  /**
   * Element options
   *
   * @param array $options
   *
   * @return Form
   */
  public function opts($options = array()) {
    foreach ((array)$options as $key => $value) {
      $this->opt($key, $value);
    }

    return $this;
  }

  /**
   * Echo built
   */
  public function write() {
    echo $this->get();
  }

  /**
   * Get built
   */
  public function get() {
    return $this->{$this->el}();
  }

  /**
   * Get built
   *
   * @return string
   */
  public function __toString() {
    return $this->get();
  }

  /**
   * Call attribute
   *
   * @param string $method
   * @param array $args
   *
   * @return Form
   */
  public function __call($method, $args = array()) {
    //Call function
    call_user_func_array(array(
      $this,
      'attr'
    ), array_merge(array($method), $args));

    return $this;
  }

  /**
   * Checkbox
   *
   * @return string
   */
  protected function checkbox() {
    //Set type
    $this->type = 'checkbox';

    return $this->input();
  }

  /**
   * Input
   *
   * @return string
   */
  protected function input() {
    //Return
    $html = array();

    if (count($this->opts) > 0) {
      foreach ($this->opts as $value => $text) {
        //More than one then change id
        if (count($this->opts) > 1) {
          //Set id
          $this->attr('id', preg_replace('/[^a-z0-9_\-]/i', '', array_get($this->attrs, 'name')) . '_' . $value);
        }

        if (strlen($text) > 0) {
          //Start label
          $html[] = '<label for="' . array_get($this->attrs, 'id') . '">';
        }

        //Element
        $html[] = '<input type="' . $this->type . '"'
          . $this->attributes()
          . ' value="' . $value . '"'
          . (in_array($this->type, array('checkbox', 'radio')) ? $this->checked($value) : '')
          . ' />';

        if (strlen($text) > 0) {
          //End label
          $html[] = '<span class="lbl">' . $text . '</span>';
          $html[] = '</label>';
        }
      }
    } else {
      //Value
      $value = (is_array($this->val) ? current($this->val) : $this->val);

      //Element
      $html[] = '<input type="' . $this->type . '"'
        . $this->attributes()
        . (strlen($value) > 0 ? ' value="' . $value . '"' : '')
        . ' />';
    }

    return implode("\n", $html);
  }

  /**
   * Build attributes
   *
   * @return string
   */
  protected function attributes() {
    //Attributes
    $attributes = array();

    foreach ($this->attrs as $key => $values) {
      if (!is_null($values)) {
        $attributes[] = $key . '="' . (is_array($values) ? implode(' ', $values) : (string)$values) . '"';
      }
    }

    //Attributes
    $html = ((count($attributes) > 0) ? ' ' . implode(' ', $attributes) : '');

    //Styles
    $styles = array();

    foreach ($this->styles as $key => $value) {
      $styles[] = $key . ': ' . $value . '';
    }

    //Styles
    $html .= ((count($styles) > 0) ? ' style="' . implode('; ', $styles) . '"' : '');

    return $html;
  }

  /**
   * Get radio or checkbox checked status
   *
   * @param string $value
   * @param bool $html
   *
   * @return string|bool
   */
  protected function checked($value, $html = true) {
    //Check variable
    $checked = false;

    if (is_array($this->val)) {
      foreach ($this->val as $val) {
        if ((string)$val === (string)$value) {
          $checked = true;
          break;
        }
      }
    } else {
      $checked = ((string)$this->val === (string)$value);
    }

    if ($html) {
      return ($checked ? ' checked="checked"' : '');
    }

    return $checked;
  }

  /**
   * Radio
   *
   * @return string
   */
  protected function radio() {
    //Set type
    $this->type = 'radio';

    return $this->input();
  }

  /**
   * Select
   *
   * @return string
   */
  protected function select() {
    return '<select'
      . $this->attributes()
      . '>' . "\n" . $this->options() . "\n" . '</select>';
  }

  /**
   * Build options
   *
   * @return string
   */
  protected function options() {
    //Return
    $options = array();

    if (is_array($this->val)) {
      foreach ($this->val as $value) {
        $this->optAttr($value, 'selected', 'selected');
      }
    } else {
      $this->optAttr($this->val, 'selected', 'selected');
    }

    foreach ($this->opts as $value => $text) {
      if (is_array($text)) {
        //Option group
        $options[] = '<optgroup label="' . $value . '">';

        foreach ($text as $groupValue => $groupText) {
          //Set value
          $this->optAttr($groupValue, 'value', $groupValue);

          //Add to options
          $options[] = '<option' . $this->optionAttributes($groupValue) . '>'
            . ((strlen($groupText) > 0) ? $groupText : '&nbsp;')
            . '</option>';
        }

        $options[] = '</optgroup>';
      } else {
        //Set value
        $this->optAttr($value, 'value', $value);

        //Set option
        $options[] = '<option' . $this->optionAttributes($value) . '>'
          . ((strlen($text) > 0) ? $text : '&nbsp;')
          . '</option>';
      }
    }

    return implode("\n", $options);
  }

  /**
   * Option attribute
   *
   * @param string $optKey
   * @param string $key
   * @param string $value
   * @param bool $check
   *
   * @return Form
   */
  public function optAttr($optKey, $key, $value = null, $check = false) {
    //Trim
    $key = trim($key);
    $value = trim($value);

    if (!$check || !isset($this->optAttrs[$key])) {
      $this->optAttrs[$optKey][$key] = (is_null($value) ? $key : $value);
    }

    return $this;
  }

  /**
   * Build option attributes
   *
   * @param string $optKey
   *
   * @return string
   */
  protected function optionAttributes($optKey) {
    //Attributes
    $attributes = array();

    foreach (array_get($this->optAttrs, $optKey, array()) as $key => $value) {
      $attributes[] = $key . '="' . $value . '"';
    }

    return ((count($attributes) > 0) ? ' ' . implode(' ', $attributes) : '');
  }

  /**
   * Textarea
   *
   * @return string
   */
  protected function textarea() {
    //Add strict attributes
    $this->attr('rows', 5, true);
    $this->attr('cols', 5, true);

    return '<textarea'
      . $this->attributes()
      . '>' . htmlspecialchars($this->val) . '</textarea>';
  }

  /**
   * Button
   *
   * @return string
   */
  protected function button() {
    return '<button type="' . $this->type . '"'
      . $this->attributes()
      . '>'
      . (count($this->opts) > 0 ? current($this->opts) : $this->val)
      . '</button>';
  }

}