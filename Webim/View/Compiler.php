<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\View;

class Compiler {

  /**
   * All of the registered extensions.
   *
   * @var array
   */
  protected $extensions = array();

  /**
   * All of the available compiler functions.
   *
   * @var array
   */
  protected $compilers = array(
    'Extensions',
    'Statements',
    'Comments',
    'Echos'
  );

  /**
   * Array of opening and closing tags for escaped echos.
   *
   * @var array
   */
  protected $contentTags = array('{{', '}}');

  /**
   * Array of opening and closing tags for escaped echos.
   *
   * @var array
   */
  protected $escapedTags = array('{{{', '}}}');

  /**
   * Counter to keep track of nested forelse statements.
   *
   * @var int
   */
  protected $forElseCounter = 0;

  /**
   * Array of footer lines to be added to template.
   *
   * @var array
   */
  protected $footer = array();

  /**
   * Placeholder to temporary mark the position of verbatim blocks.
   *
   * @var string
   */
  protected $verbatimPlaceholder = '@__verbatim__@';

  /**
   * Array to temporary store the verbatim blocks found in the template.
   *
   * @var array
   */
  protected $verbatimBlocks = [];

  /**
   * Compile
   *
   * @param string $content
   *
   * @return string
   */
  public static function compile($content) {
    return with(new static)->compileString($content);
  }

  /**
   * Compile the given Blade template contents.
   *
   * @param string $value
   *
   * @return string
   */
  public function compileString($value) {
    $result = '';

    if (strpos($value, '@verbatim') !== false) {
      $value = $this->storeVerbatimBlocks($value);
    }

    $this->footer = [];

    // Here we will loop through all of the tokens returned by the Zend lexer and
    // parse each one into the corresponding valid PHP. We will then have this
    // template as the correctly rendered PHP that can be rendered natively.
    foreach (token_get_all($value) as $token) {
      $result .= is_array($token) ? $this->parseToken($token) : $token;
    }

    if (!empty($this->verbatimBlocks)) {
      $result = $this->restoreVerbatimBlocks($result);
    }

    // If there are any footer lines that need to get added to a template we will
    // add them here at the end of the template. This gets used mainly for the
    // template inheritance via the extends keyword that should be appended.
    if (count($this->footer)) {
      $result = ltrim($result, PHP_EOL)
        . PHP_EOL . implode(PHP_EOL, array_reverse($this->footer));
    }

    return $result;
  }

  /**
   * Store the verbatim blocks and replace them with a temporary placeholder.
   *
   * @param string $value
   *
   * @return string
   */
  protected function storeVerbatimBlocks($value) {
    return preg_replace_callback('/(?<!@)@verbatim(.*?)@endverbatim/s', function ($matches) {
      $this->verbatimBlocks[] = $matches[1];

      return $this->verbatimPlaceholder;
    }, $value);
  }

  /**
   * Parse the tokens from the template.
   *
   * @param array $token
   *
   * @return string
   */
  protected function parseToken($token) {
    list($id, $content) = $token;

    if ($id == T_INLINE_HTML) {
      foreach ($this->compilers as $type) {
        $content = $this->{"compile{$type}"}($content);
      }
    }

    return $content;
  }

  /**
   * Replace the raw placeholders with the original code stored in the raw blocks.
   *
   * @param string $result
   *
   * @return string
   */
  protected function restoreVerbatimBlocks($result) {
    $result = preg_replace_callback('/' . preg_quote($this->verbatimPlaceholder) . '/', function () {
      return array_shift($this->verbatimBlocks);
    }, $result);

    $this->verbatimBlocks = [];

    return $result;
  }

  /**
   * Register a custom Blade compiler.
   *
   * @param \Closure $compiler
   *
   * @return void
   */
  public function extend(\Closure $compiler) {
    $this->extensions[] = $compiler;
  }

  /**
   * Get the regular expression for a generic Blade function.
   *
   * @param string $function
   *
   * @return string
   */
  public function createMatcher($function) {
    return '/(?<!\w)(\s*)@' . $function . '(\s*\(.*\))/';
  }

  /**
   * Get the regular expression for a generic Blade function.
   *
   * @param string $function
   *
   * @return string
   */
  public function createOpenMatcher($function) {
    return '/(?<!\w)(\s*)@' . $function . '(\s*\(.*)\)/';
  }

  /**
   * Create a plain Blade matcher.
   *
   * @param string $function
   *
   * @return string
   */
  public function createPlainMatcher($function) {
    return '/(?<!\w)(\s*)@' . $function . '(\s*)/';
  }

  /**
   * Sets the escaped content tags used for the compiler.
   *
   * @param string $openTag
   * @param string $closeTag
   *
   * @return void
   */
  public function setEscapedContentTags($openTag, $closeTag) {
    $this->setContentTags($openTag, $closeTag, true);
  }

  /**
   * Gets the content tags used for the compiler.
   *
   * @return array
   */
  public function getContentTags() {
    return $this->contentTags;
  }

  /**
   * Sets the content tags used for the compiler.
   *
   * @param string $openTag
   * @param string $closeTag
   * @param bool $escaped
   *
   * @return void
   */
  public function setContentTags($openTag, $closeTag, $escaped = false) {
    $property = ($escaped === true) ? 'escapedTags' : 'contentTags';

    $this->{$property} = array(preg_quote($openTag), preg_quote($closeTag));
  }

  /**
   * Gets the escaped content tags used for the compiler.
   *
   * @return array
   */
  public function getEscapedContentTags() {
    return $this->escapedTags;
  }

  /**
   * Execute the user defined extensions.
   *
   * @param string $value
   *
   * @return string
   */
  protected function compileExtensions($value) {
    foreach ($this->extensions as $compiler) {
      $value = call_user_func($compiler, $value, $this);
    }

    return $value;
  }

  /**
   * Compile Blade Statements that start with "@"
   *
   * @param string $value
   *
   * @return mixed
   */
  protected function compileStatements($value) {
    $callback = function ($match) {
      if (method_exists($this, $method = 'compile' . ucfirst($match[1]))) {
        $match[0] = $this->$method(array_get($match, 3));
      }

      return isset($match[3]) ? $match[0] : $match[0] . $match[2];
    };

    return preg_replace_callback('/\B@(\w+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $callback, $value);
  }

  /**
   * Compile Blade comments into valid PHP.
   *
   * @param string $value
   *
   * @return string
   */
  protected function compileComments($value) {
    $pattern = sprintf('/%s--((.|\s)*?)--%s/', $this->contentTags[0], $this->contentTags[1]);

    return preg_replace($pattern, '<?php /*$1*/ ?>', $value);
  }

  /**
   * Compile Blade echos into valid PHP.
   *
   * @param string $value
   *
   * @return string
   */
  protected function compileEchos($value) {
    $difference = strlen($this->contentTags[0]) - strlen($this->escapedTags[0]);

    if ($difference > 0) {
      return $this->compileEscapedEchos($this->compileRegularEchos($value));
    }

    return $this->compileRegularEchos($this->compileEscapedEchos($value));
  }

  /**
   * Compile the escaped echo statements.
   *
   * @param string $value
   *
   * @return string
   */
  protected function compileEscapedEchos($value) {
    $pattern = sprintf('/%s\s*(.+?)\s*%s(\r?\n)?/s', $this->escapedTags[0], $this->escapedTags[1]);

    $callback = function ($matches) {
      $whitespace = empty($matches[2]) ? '' : $matches[2] . $matches[2];

      return '<?php echo e(' . $this->compileEchoDefaults($matches[1]) . '); ?>' . $whitespace;
    };

    return preg_replace_callback($pattern, $callback, $value);
  }

  /**
   * Compile the default values for the echo statement.
   *
   * @param string $value
   *
   * @return string
   */
  public function compileEchoDefaults($value) {
    return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $value);
  }

  /**
   * Compile the "regular" echo statements.
   *
   * @param string $value
   *
   * @return string
   */
  protected function compileRegularEchos($value) {
    $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->contentTags[0], $this->contentTags[1]);

    $callback = function ($matches) {
      $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

      return $matches[1] ? substr($matches[0], 1) : '<?php echo ' . $this->compileEchoDefaults($matches[2]) . '; ?>' . $whitespace;
    };

    return preg_replace_callback($pattern, $callback, $value);
  }

  /**
   * Compile the yield statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileYield($expression) {
    return "<?php echo \$__render->yieldContent{$expression}; ?>";
  }

  /**
   * Compile the show statements into valid PHP.
   *
   * @return string
   */
  protected function compileShow() {
    return '<?php echo $__render->yieldSection(); ?>';
  }

  /**
   * Compile the section statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileSection($expression) {
    return "<?php \$__render->startSection{$expression}; ?>";
  }

  /**
   * Compile the append statements into valid PHP.
   *
   * @return string
   */
  protected function compileAppend() {
    return '<?php $__render->appendSection(); ?>';
  }

  /**
   * Compile the end-section statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndsection() {
    return '<?php $__render->stopSection(); ?>';
  }

  /**
   * Compile the stop statements into valid PHP.
   *
   * @return string
   */
  protected function compileStop() {
    return '<?php $__render->stopSection(); ?>';
  }

  /**
   * Compile the overwrite statements into valid PHP.
   *
   * @return string
   */
  protected function compileOverwrite() {
    return '<?php $__render->stopSection(true); ?>';
  }

  /**
   * Compile the unless statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileUnless($expression) {
    return "<?php if ( ! {$expression}): ?>";
  }

  /**
   * Compile the end unless statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndunless() {
    return '<?php endif; ?>';
  }

  /**
   * Compile the url statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileUrl($expression) {
    return "<?php echo url{$expression}; ?>";
  }

  /**
   * Compile the lang statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileLang($expression) {
    return "<?php echo lang{$expression}; ?>";
  }

  /**
   * Compile the conf statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileConf($expression) {
    return "<?php echo conf{$expression}; ?>";
  }

  /**
   * Compile the choice statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileChoice($expression) {
    return "<?php echo choice{$expression}; ?>";
  }

  /**
   * Compile the else statements into valid PHP.
   *
   * @return string
   */
  protected function compileElse() {
    return '<?php else: ?>';
  }

  /**
   * Compile the for statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileFor($expression) {
    return "<?php for {$expression}: ?>";
  }

  /**
   * Compile the raw PHP statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compilePhp($expression) {
    return $expression ? "<?php {$expression}; ?>" : '<?php ';
  }

  /**
   * Compile the foreach statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileForeach($expression) {
    preg_match('/\( *(.*) +as *(.*)\)$/is', $expression, $matches);

    $iteratee = trim($matches[1]);
    $iteration = trim($matches[2]);

    $initLoop = "\$__currentLoopData = {$iteratee}; \$__render->addLoop(\$__currentLoopData);";
    $iterateLoop = '$__render->incrementLoopIndices(); $loop = $__render->getLastLoop();';

    return "<?php {$initLoop} foreach(\$__currentLoopData as {$iteration}): {$iterateLoop} ?>";
  }

  /**
   * Compile the forelse statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileForelse($expression) {
    $empty = '$__empty_' . ++$this->forElseCounter;

    preg_match('/\( *(.*) +as *(.*)\)$/is', $expression, $matches);

    $iteratee = trim($matches[1]);
    $iteration = trim($matches[2]);

    $initLoop = "\$__currentLoopData = {$iteratee}; \$__render->addLoop(\$__currentLoopData);";
    $iterateLoop = '$__render->incrementLoopIndices(); $loop = $__render->getLastLoop();';

    return "<?php {$empty} = true; {$initLoop} foreach(\$__currentLoopData as {$iteration}): {$iterateLoop} {$empty} = false; ?>";
  }

  /**
   * Compile the break statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileBreak($expression) {
    if ($expression) {
      preg_match('/\(\s*(-?\d+)\s*\)$/', $expression, $matches);

      return $matches ? '<?php break ' . max(1, $matches[1]) . '; ?>' : "<?php if {$expression} break; ?>";
    }

    return '<?php break; ?>';
  }

  /**
   * Compile the continue statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileContinue($expression) {
    if ($expression) {
      preg_match('/\(\s*(-?\d+)\s*\)$/', $expression, $matches);

      return $matches ? '<?php continue ' . max(1, $matches[1]) . '; ?>' : "<?php if {$expression} continue; ?>";
    }

    return '<?php continue; ?>';
  }

  /**
   * Compile the if statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileIf($expression) {
    return "<?php if {$expression}: ?>";
  }

  /**
   * Compile the else-if statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileElseif($expression) {
    return "<?php elseif {$expression}: ?>";
  }

  /**
   * Compile the forelse statements into valid PHP.
   *
   * @return string
   */
  protected function compileEmpty() {
    $empty = '$__empty_' . $this->forElseCounter--;

    return "<?php endforeach; if ({$empty}): ?>";
  }

  /**
   * Compile the while statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileWhile($expression) {
    return "<?php while {$expression}: ?>";
  }

  /**
   * Compile the end-while statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndwhile() {
    return '<?php endwhile; ?>';
  }

  /**
   * Compile the end-for statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndfor() {
    return '<?php endfor; ?>';
  }

  /**
   * Compile the end-for-each statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndforeach() {
    return '<?php endforeach; $__render->popLoop(); $loop = $__render->getLastLoop(); ?>';
  }

  /**
   * Compile the end-if statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileEndif($expression) {
    return '<?php endif; ?>';
  }

  /**
   * Compile the end-for-else statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndforelse() {
    return '<?php endif; ?>';
  }

  /**
   * Compile the end php statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndphp() {
    return '?>';
  }

  /**
   * Compile the extends statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileExtends($expression) {
    $expression = $this->stripParentheses($expression);

    $data = "<?php echo \$__view->make('layout', {$expression})->render(); ?>";

    $this->footer[] = $data;

    return '';
  }

  /**
   * Strip the parentheses from the given expression.
   *
   * @param string $expression
   *
   * @return string
   */
  public function stripParentheses($expression) {
    if (starts_with($expression, '(')) {
      $expression = substr($expression, 1, -1);
    }

    return $expression;
  }

  /**
   * Compile the include statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileInclude($expression) {
    $expression = $this->stripParentheses($expression);

    return "<?php echo \$__view->make('include', {$expression})->render(); ?>";
  }

  /**
   * Compile the include-if statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileIncludeIf($expression) {
    $expression = $this->stripParentheses($expression);

    return "<?php if (\$__view->exists('include', {$expression})) echo \$__view->make('include', {$expression}); ?>";
  }

  /**
   * Compile the include-when statements into valid PHP.
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileIncludeWhen($expression) {
    $expression = $this->stripParentheses($expression);

    return "<?php echo \$__view->makeWhen('include', {$expression}); ?>";
  }

  /**
   * Compile the stack statements into the content
   *
   * @param string $expression
   *
   * @return string
   */
  protected function compileStack($expression) {
    return "<?php echo \$__render->yieldContent{$expression}; ?>";
  }

  /**
   * Compile the push statements into valid PHP.
   *
   * @param $expression
   *
   * @return string
   */
  protected function compilePush($expression) {
    return "<?php \$__render->startSection{$expression}; ?>";
  }

  /**
   * Compile the endpush statements into valid PHP.
   *
   * @return string
   */
  protected function compileEndpush() {
    return '<?php $__render->appendSection(); ?>';
  }

}