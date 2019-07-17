<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Library;

use Webim\Database\Manager as DB;

class Validator {

  /**
   * The registered custom validators.
   *
   * @var array
   */
  protected static $validators = array();
  /**
   * The array being validated.
   *
   * @var array
   */
  public $attributes;
  /**
   * The post-validation error messages.
   *
   * @var array
   */
  protected $errors;
  /**
   * The validation rules.
   *
   * @var array
   */
  protected $rules = array();
  /**
   * The validation messages.
   *
   * @var array
   */
  protected $messages = array();
  /**
   * The database connection that should be used by the validator.
   *
   * @var DB
   */
  protected $db;
  /**
   * The numeric related validation rules.
   *
   * @var array
   */
  protected $numericRules = array(
    'numeric',
    'integer'
  );

  /**
   * Constructor
   *
   * @param array $attributes
   * @param array $rules
   * @param array $messages
   */
  public function __construct($attributes, $rules, $messages = array()) {
    foreach ($rules as $key => &$rule) {
      $rule = (is_string($rule)) ? explode('|', $rule) : $rule;
    }

    $this->rules = (array)$rules;
    $this->messages = (array)$messages;
    $this->attributes = (array)$attributes;
  }

  /**
   * Create a new validator instance.
   *
   * @param array $attributes
   * @param array $rules
   * @param array $messages
   *
   * @return Validator
   */
  public static function make($attributes, $rules, $messages = array()) {
    return new static($attributes, $rules, $messages);
  }

  /**
   * Register a custom validator.
   *
   * @param string $name
   * @param \Closure $validator
   *
   * @return void
   */
  public static function register($name, $validator) {
    static::$validators[$name] = $validator;
  }

  /**
   * Validate the target array using the specified validation rules.
   *
   * @return bool
   */
  public function passes() {
    return $this->valid();
  }

  /**
   * Validate the target array using the specified validation rules.
   *
   * @return bool
   */
  public function valid() {
    $this->errors = array();

    foreach ($this->rules as $attribute => $rules) {
      foreach ($rules as $rule) {
        $this->check($attribute, $rule);
      }
    }

    return (count($this->errors) == 0);
  }

  /**
   * Evaluate an attribute against a validation rule.
   *
   * @param string $attribute
   * @param string $rule
   *
   * @return void
   */
  protected function check($attribute, $rule) {
    list($rule, $parameters) = $this->parse($rule);

    $value = array_get($this->attributes, $attribute);

    // Before running the validator, we need to verify that the attribute and rule
    // combination is actually validatable. Only the 'accepted' rule implies that
    // the attribute is 'required', so if the attribute does not exist, the other
    // rules will not be run for the attribute.
    $validatable = $this->validatable($rule, $attribute, $value);

    if ($validatable && !$this->{'validate' . studly_case($rule)}($attribute, $value, $parameters, $this)) {
      $this->error($attribute, $rule, $parameters);
    }
  }

  /**
   * Extract the rule name and parameters from a rule.
   *
   * @param string $rule
   *
   * @return array
   */
  protected function parse($rule) {
    $parameters = array();

    // The format for specifying validation rules and parameters follows a
    // {rule}:{parameters} formatting convention. For instance, the rule
    // 'max:3' specifies that the value may only be 3 characters long.
    if (($colon = strpos($rule, ':')) !== false) {
      $parameters = str_getcsv(substr($rule, $colon + 1));
    }

    return array(
      (is_numeric($colon) ? substr($rule, 0, $colon) : $rule),
      $parameters
    );
  }

  /**
   * Determine if an attribute is validatable.
   *
   * To be considered validatable, the attribute must either exist, or the rule
   * being checked must implicitly validate 'required', such as the 'required'
   * rule or the 'accepted' rule.
   *
   * @param string $rule
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validatable($rule, $attribute, $value) {
    return ($this->validateRequired($attribute, $value) || $this->implicit($rule));
  }

  /**
   * Validate that a required attribute exists in the attributes array.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateRequired($attribute, $value) {
    if (is_null($value)) {
      return false;
    } elseif (is_string($value) && (trim($value) === '')) {
      return false;
    } elseif (isset($_FILES[$attribute]) && is_array($value) && ($value['tmp_name'] == '')) {
      return false;
    }

    return true;
  }

  /**
   * Determine if a given rule implies that the attribute is required.
   *
   * @param string $rule
   *
   * @return bool
   */
  protected function implicit($rule) {
    return (($rule == 'required') || ($rule == 'accepted'));
  }

  /**
   * Add an error message to the validator's collection of messages.
   *
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return void
   */
  protected function error($attribute, $rule, $parameters) {
    $message = $this->replace($this->message($attribute, $rule), $attribute, $rule, $parameters);

    $this->errors[] = array(
      'attribute' => $attribute,
      'message' => $message
    );
  }

  /**
   * Replace all error message place-holders with actual values.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replace($message, $attribute, $rule, $parameters) {
    $message = str_replace(':attribute', $this->attribute($attribute), $message);

    if (method_exists($this, ($replacer = 'replace' . studly_case($rule)))) {
      $message = $this->$replacer($message, $attribute, $rule, $parameters);
    }

    return $message;
  }

  /**
   * Get the displayable name for a given attribute.
   *
   * @param string $attribute
   *
   * @return string
   */
  protected function attribute($attribute) {
    return str_replace('_', ' ', $attribute);
  }

  /**
   * Get the proper error message for an attribute and rule.
   *
   * @param string $attribute
   * @param string $rule
   *
   * @return string
   */
  protected function message($attribute, $rule) {
    // First we'll check for developer specified, attribute specific messages.
    // These messages take first priority. They allow the fine-grained tuning
    // of error messages for each rule.
    $custom = $attribute . '.' . $rule;

    if (array_key_exists($custom, $this->messages)) {
      return $this->messages[$custom];
    } elseif (array_key_exists($rule, $this->messages)) {
      return $this->messages[$rule];
    } else {
      return $attribute;
    }
  }

  /**
   * Validate the target array using the specified validation rules.
   *
   * @return bool
   */
  public function fails() {
    return $this->invalid();
  }

  /**
   * Validate the target array using the specified validation rules.
   *
   * @return bool
   */
  public function invalid() {
    return !$this->valid();
  }

  /**
   * Errors
   *
   * @param null|string $key
   *
   * @return array|mixed
   */
  public function errors($key = null) {
    if (!is_null($key)) return array_get($this->errors, $key);

    return $this->errors;
  }

  /**
   * Set the database connection that should be used by the validator.
   *
   * @param DB $connection
   *
   * @return Validator
   */
  public function connection(DB $connection) {
    $this->db = $connection;

    return $this;
  }

  /**
   * Dynamically handle calls to custom registered validators.
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed
   */
  public function __call($method, $args) {
    // First we will slice the 'validate_' prefix off of the validator since
    // custom validators aren't registered with such a prefix, then we can
    // just call the method with the given parameters.
    if (isset(static::$validators[$method = substr($method, 9)])) {
      return call_user_func_array(static::$validators[$method], $args);
    }

    return $this;
  }

  /**
   * Validate that an attribute has a matching confirmation attribute.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validate_confirmed($attribute, $value) {
    return $this->validateSame($attribute, $value, array(
      $attribute . '.confirmation'
    ));
  }

  /**
   * Validate that an attribute is the same as another attribute.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateSame($attribute, $value, $parameters) {
    $other = $parameters[0];

    return (isset($this->attributes[$other]) && ($value == $this->attributes[$other]));
  }

  /**
   * Validate that an attribute was 'accepted'.
   *
   * This validation rule implies the attribute is 'required'.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateAccepted($attribute, $value) {
    return ($this->validateRequired($attribute, $value) && (($value == 'yes') || ($value == '1')));
  }

  /**
   * Validate that an attribute is different from another attribute.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateDifferent($attribute, $value, $parameters) {
    $other = $parameters[0];

    return (isset($this->attributes[$other]) && ($value != $this->attributes[$other]));
  }

  /**
   * Validate that an attribute is numeric.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateNumeric($attribute, $value) {
    return is_numeric($value);
  }

  /**
   * Validate that an attribute is an integer.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateInteger($attribute, $value) {
    return (filter_var($value, FILTER_VALIDATE_INT) !== false);
  }

  /**
   * Validate the size of an attribute.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateSize($attribute, $value, $parameters) {
    return ($this->size($attribute, $value) == $parameters[0]);
  }

  /**
   * Get the size of an attribute.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return mixed
   */
  protected function size($attribute, $value) {
    // This method will determine if the attribute is a number, string, or file
    // and return the proper size accordingly. If it is a number, then number itself
    // is the size; if it is a file, the size is kilobytes in the size; if it is a
    // string, the length is the size.
    if (is_numeric($value) && $this->hasRule($attribute, $this->numericRules)) {
      return $this->attributes[$attribute];
    } elseif (array_key_exists($attribute, $_FILES)) {
      return $value['size'] / 1024;
    } else {
      return strlen(trim($value));
    }
  }

  /**
   * Determine if an attribute has a rule assigned to it.
   *
   * @param string $attribute
   * @param array $rules
   *
   * @return bool
   */
  protected function hasRule($attribute, $rules) {
    foreach ($this->rules[$attribute] as $rule) {
      list($rule, $parameters) = $this->parse($rule);

      if (in_array($rule, $rules)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Validate the size of an attribute is between a set of values.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateBetween($attribute, $value, $parameters) {
    $size = $this->size($attribute, $value);

    return (($size >= $parameters[0]) && ($size <= $parameters[1]));
  }

  /**
   * Validate the size of an attribute is greater than a minimum value.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateMin($attribute, $value, $parameters) {
    return ($this->size($attribute, $value) >= $parameters[0]);
  }

  /**
   * Validate the size of an attribute is less than a maximum value.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateMax($attribute, $value, $parameters) {
    return ($this->size($attribute, $value) <= $parameters[0]);
  }

  /**
   * Validate an attribute is contained within a list of values.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateIn($attribute, $value, $parameters) {
    return in_array($value, $parameters);
  }

  /**
   * Validate an attribute is not contained within a list of values.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateNotIn($attribute, $value, $parameters) {
    return !in_array($value, $parameters);
  }

  /**
   * Validate the uniqueness of an attribute value on a given database table.
   *
   * If a database column is not specified, the attribute will be used.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateUnique($attribute, $value, $parameters) {
    // We allow the table column to be specified just in case the column does
    // not have the same name as the attribute. It must be within the second
    // parameter position, right after the database table name.
    if (isset($parameters[1])) {
      $attribute = $parameters[1];
    }

    $query = $this->db()->table($parameters[0])->where($attribute, '=', $value);

    // We also allow an ID to be specified that will not be included in the
    // uniqueness check. This makes updating columns easier since it is
    // fine for the given ID to exist in the table.
    if (isset($parameters[2])) {
      $id = (isset($parameters[3]) ? $parameters[3] : 'id');

      $query->where($id, '<>', $parameters[2]);
    }

    return ($query->count() == 0);
  }

  /**
   * Get the database connection for the Validator.
   *
   * @return DB
   */
  protected function db() {
    if (!is_null($this->db)) {
      return $this->db;
    }

    return $this->db = DB::connection();
  }

  /**
   * Validate the existence of an attribute value in a database table.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateExists($attribute, $value, $parameters) {
    if (isset($parameters[1])) {
      $attribute = $parameters[1];
    }

    // Grab the number of elements we are looking for. If the given value is
    // in array, we'll count all of the values in the array, otherwise we
    // can just make sure the count is greater or equal to one.
    $count = (is_array($value) ? count($value) : 1);

    $query = $this->db()->table($parameters[0]);

    // If the given value is an array, we will check for the existence of
    // all the values in the database, otherwise we'll check for the
    // presence of the single given value in the database.
    if (is_array($value)) {
      $query = $query->whereIn($attribute, $value);
    } else {
      $query = $query->where($attribute, '=', $value);
    }

    return ($query->count() >= $count);
  }

  /**
   * Validate that an attribute is a valid IP.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateIp($attribute, $value) {
    return (filter_var($value, FILTER_VALIDATE_IP) !== false);
  }

  /**
   * Validate that an attribute is a valid e-mail address.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateEmail($attribute, $value) {
    return (filter_var($value, FILTER_VALIDATE_EMAIL) !== false);
  }

  /**
   * Validate that an attribute is a valid URL.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateUrl($attribute, $value) {
    return (filter_var($value, FILTER_VALIDATE_URL) !== false);
  }

  /**
   * Validate that an attribute is a valid date.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateDate($attribute, $value) {
    return ($value instanceof Carbon);
  }

  /**
   * Validate that an attribute is an active URL.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateActiveUrl($attribute, $value) {
    $url = str_replace(array(
      'http://',
      'https://',
      'ftp://'
    ), '', Str::text($value)->lower()->get());

    return checkdnsrr($url);
  }

  /**
   * Validate the MIME type of a file is an image MIME type.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateImage($attribute, $value) {
    return $this->validateMimes($attribute, $value, array(
      'jpg',
      'png',
      'gif',
      'bmp'
    ));
  }

  /**
   * Validate the MIME type of a file upload attribute is in a set of MIME types.
   *
   * @param string $attribute
   * @param array $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateMimes($attribute, $value, $parameters) {
    if (!is_array($value) || (array_get($value, 'tmp_name', '') == '')) {
      return true;
    }

    foreach ($parameters as $extension) {
      if (File::path($value['tmp_name'])->extension() == $extension) {
        return true;
      }
    }

    return false;
  }

  /**
   * Validate that an attribute contains only alphabetic characters.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateAlpha($attribute, $value) {
    return preg_match('/^([a-z])+$/i', $value);
  }

  /**
   * Validate that an attribute contains only alpha-numeric characters.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateAlphaNum($attribute, $value) {
    return preg_match('/^([a-z0-9])+$/i', $value);
  }

  /**
   * Validate that an attribute contains only alpha-numeric characters, dashes,
   * and underscores.
   *
   * @param string $attribute
   * @param mixed $value
   *
   * @return bool
   */
  protected function validateAlphaDash($attribute, $value) {
    return preg_match('/^([-a-z0-9_-])+$/i', $value);
  }

  /**
   * Validate that an attribute passes a regular expression check.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateMatch($attribute, $value, $parameters) {
    return preg_match($parameters[0], $value);
  }

  /**
   * Validate the date is before a given date.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateBefore($attribute, $value, $parameters) {
    return (strtotime($value) < strtotime($parameters[0]));
  }

  /**
   * Validate the date is after a given date.
   *
   * @param string $attribute
   * @param mixed $value
   * @param array $parameters
   *
   * @return bool
   */
  protected function validateAfter($attribute, $value, $parameters) {
    return (strtotime($value) > strtotime($parameters[0]));
  }

  /**
   * Replace all place-holders for the between rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceBetween($message, $attribute, $rule, $parameters) {
    return str_replace(array(
      ':min',
      ':max'
    ), $parameters, $message);
  }

  /**
   * Replace all place-holders for the size rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceSize($message, $attribute, $rule, $parameters) {
    return str_replace(':size', $parameters[0], $message);
  }

  /**
   * Replace all place-holders for the min rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceMin($message, $attribute, $rule, $parameters) {
    return str_replace(':min', $parameters[0], $message);
  }

  /**
   * Replace all place-holders for the max rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceMax($message, $attribute, $rule, $parameters) {
    return str_replace(':max', $parameters[0], $message);
  }

  /**
   * Replace all place-holders for the in rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceIn($message, $attribute, $rule, $parameters) {
    return str_replace(':values', implode(', ', $parameters), $message);
  }

  /**
   * Replace all place-holders for the not_in rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceNotIn($message, $attribute, $rule, $parameters) {
    return str_replace(':values', implode(', ', $parameters), $message);
  }

  /**
   * Replace all place-holders for the not_in rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceMimes($message, $attribute, $rule, $parameters) {
    return str_replace(':values', implode(', ', $parameters), $message);
  }

  /**
   * Replace all place-holders for the same rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceSame($message, $attribute, $rule, $parameters) {
    return str_replace(':other', $parameters[0], $message);
  }

  /**
   * Replace all place-holders for the different rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceDifferent($message, $attribute, $rule, $parameters) {
    return str_replace(':other', $parameters[0], $message);
  }

  /**
   * Replace all place-holders for the before rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceBefore($message, $attribute, $rule, $parameters) {
    return str_replace(':date', $parameters[0], $message);
  }

  /**
   * Replace all place-holders for the after rule.
   *
   * @param string $message
   * @param string $attribute
   * @param string $rule
   * @param array $parameters
   *
   * @return string
   */
  protected function replaceAfter($message, $attribute, $rule, $parameters) {
    return str_replace(':date', $parameters[0], $message);
  }

}