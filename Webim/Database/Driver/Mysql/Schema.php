<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Mysql;

use Webim\Database\Driver\Connection;
use Webim\Database\Fluent;
use Webim\Database\Schema\Blueprint;
use Webim\Database\Schema\Grammar;

class Schema extends Grammar {

  /**
   * The possible column modifiers.
   *
   * @var array
   */
  protected $modifiers = array('Unsigned', 'Nullable', 'Default', 'Increment', 'After', 'Comment');

  /**
   * The possible column serials
   *
   * @var array
   */
  protected $serials = array('bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger');

  /**
   * Compile the query to determine the list of tables.
   *
   * @return string
   */
  public function compileTableExists() {
    return 'SELECT * FROM information_schema.tables WHERE table_schema = ? AND table_name = ?';
  }

  /**
   * Compile the query to determine the list of columns.
   *
   * @return string
   */
  public function compileColumnExists() {
    return 'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?';
  }

  /**
   * Compile a create table command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   * @param Webim\Database\Driver\Connection $connection
   *
   * @return string
   */
  public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection) {
    $columns = implode(', ', $this->getColumns($blueprint));

    $sql = 'CREATE TABLE ' . $this->wrapTable($blueprint) . " ($columns)";

    // Once we have the primary SQL, we can add the encoding option to the SQL for
    // the table.  Then, we can check if a storage engine has been supplied for
    // the table. If so, we will add the engine declaration to the SQL query.
    $sql = $this->compileCreateEncoding($sql, $connection);

    if (isset($blueprint->engine)) {
      $sql .= ' ENGINE = ' . $blueprint->engine;
    }

    return $sql;
  }

  /**
   * Append the character set specifications to a command.
   *
   * @param string $sql
   * @param Webim\Database\Driver\Connection $connection
   *
   * @return string
   */
  protected function compileCreateEncoding($sql, Connection $connection) {
    if (!is_null($charset = $connection->getConfig('charset'))) {
      $sql .= ' DEFAULT CHARACTER SET ' . $charset;
    }

    if (!is_null($collation = $connection->getConfig('collation'))) {
      $sql .= ' COLLATE ' . $collation;
    }

    return $sql;
  }

  /**
   * Compile an add column command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileAdd(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    $columns = $this->prefixArray('ADD', $this->getColumns($blueprint));

    return 'ALTER TABLE ' . $table . ' ' . implode(', ', $columns);
  }

  /**
   * Compile a primary key command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compilePrimary(Blueprint $blueprint, Fluent $command) {
    $command->name(null);

    return $this->compileKey($blueprint, $command, 'PRIMARY KEY');
  }

  /**
   * Compile an index creation command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   * @param string $type
   *
   * @return string
   */
  protected function compileKey(Blueprint $blueprint, Fluent $command, $type) {
    $columns = $this->columnize($command->columns);

    $table = $this->wrapTable($blueprint);

    return "ALTER TABLE {$table} ADD {$type} {$command->index}($columns)";
  }

  /**
   * Compile a unique key command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileUnique(Blueprint $blueprint, Fluent $command) {
    return $this->compileKey($blueprint, $command, 'UNIQUE');
  }

  /**
   * Compile a plain index key command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileIndex(Blueprint $blueprint, Fluent $command) {
    return $this->compileKey($blueprint, $command, 'INDEX');
  }

  /**
   * Compile a drop table command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDrop(Blueprint $blueprint, Fluent $command) {
    return 'DROP TABLE ' . $this->wrapTable($blueprint);
  }

  /**
   * Compile a drop table (if exists) command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropIfExists(Blueprint $blueprint, Fluent $command) {
    return 'DROP TABLE IF EXISTS ' . $this->wrapTable($blueprint);
  }

  /**
   * Compile a drop column command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropColumn(Blueprint $blueprint, Fluent $command) {
    $columns = $this->prefixArray('DROP', $this->wrapArray($command->columns));

    $table = $this->wrapTable($blueprint);

    return 'ALTER TABLE ' . $table . ' ' . implode(', ', $columns);
  }

  /**
   * Compile a drop primary key command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropPrimary(Blueprint $blueprint, Fluent $command) {
    return 'ALTER TABLE ' . $this->wrapTable($blueprint) . ' DROP PRIMARY KEY';
  }

  /**
   * Compile a drop unique key command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropUnique(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    return "ALTER TABLE {$table} DROP INDEX {$command->index}";
  }

  /**
   * Compile a drop index command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropIndex(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    return "ALTER TABLE {$table} DROP INDEX {$command->index}";
  }

  /**
   * Compile a drop foreign key command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropForeign(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    return "ALTER TABLE {$table} DROP FOREIGN KEY {$command->index}";
  }

  /**
   * Compile a rename table command.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileRename(Blueprint $blueprint, Fluent $command) {
    $from = $this->wrapTable($blueprint);

    return "RENAME TABLE {$from} TO " . $this->wrapTable($command->to);
  }

  /**
   * Create the column definition for a char type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeChar(Fluent $column) {
    return "char({$column->length})";
  }

  /**
   * Create the column definition for a string type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeString(Fluent $column) {
    return "varchar({$column->length})";
  }

  /**
   * Create the column definition for a text type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeText(Fluent $column) {
    return 'text';
  }

  /**
   * Create the column definition for a medium text type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeMediumText(Fluent $column) {
    return 'mediumtext';
  }

  /**
   * Create the column definition for a long text type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeLongText(Fluent $column) {
    return 'longtext';
  }

  /**
   * Create the column definition for a big integer type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeBigInteger(Fluent $column) {
    return 'bigint';
  }

  /**
   * Create the column definition for a integer type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeInteger(Fluent $column) {
    return 'int';
  }

  /**
   * Create the column definition for a medium integer type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeMediumInteger(Fluent $column) {
    return 'mediumint';
  }

  /**
   * Create the column definition for a tiny integer type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeTinyInteger(Fluent $column) {
    return 'tinyint';
  }

  /**
   * Create the column definition for a small integer type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeSmallInteger(Fluent $column) {
    return 'smallint';
  }

  /**
   * Create the column definition for a float type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeFloat(Fluent $column) {
    return "float({$column->total}, {$column->places})";
  }

  /**
   * Create the column definition for a double type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDouble(Fluent $column) {
    if ($column->total && $column->places) {
      return "double({$column->total}, {$column->places})";
    } else {
      return 'double';
    }
  }

  /**
   * Create the column definition for a decimal type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDecimal(Fluent $column) {
    return "decimal({$column->total}, {$column->places})";
  }

  /**
   * Create the column definition for a boolean type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeBoolean(Fluent $column) {
    return 'tinyint(1)';
  }

  /**
   * Create the column definition for an enum type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeEnum(Fluent $column) {
    return "enum('" . implode("', '", $column->allowed) . "')";
  }

  /**
   * Create the column definition for a date type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDate(Fluent $column) {
    return 'date';
  }

  /**
   * Create the column definition for a date-time type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDateTime(Fluent $column) {
    return 'datetime';
  }

  /**
   * Create the column definition for a time type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeTime(Fluent $column) {
    return 'time';
  }

  /**
   * Create the column definition for a timestamp type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeTimestamp(Fluent $column) {
    if (!$column->nullable) return 'timestamp DEFAULT 0';

    return 'timestamp';
  }

  /**
   * Create the column definition for a binary type.
   *
   * @param Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeBinary(Fluent $column) {
    return 'blob';
  }

  /**
   * Get the SQL for an unsigned column modifier.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyUnsigned(Blueprint $blueprint, Fluent $column) {
    if ($column->unsigned) return ' UNSIGNED';

    return '';
  }

  /**
   * Get the SQL for a nullable column modifier.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyNullable(Blueprint $blueprint, Fluent $column) {
    return $column->nullable ? ' NULL' : ' NOT NULL';
  }

  /**
   * Get the SQL for a default column modifier.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyDefault(Blueprint $blueprint, Fluent $column) {
    if (!is_null($column->default)) {
      return ' DEFAULT ' . $this->getDefaultValue($column->default);
    }

    return '';
  }

  /**
   * Get the SQL for an auto-increment column modifier.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyIncrement(Blueprint $blueprint, Fluent $column) {
    if (in_array($column->type, $this->serials) && $column->autoIncrement) {
      return ' AUTO_INCREMENT PRIMARY KEY';
    }

    return '';
  }

  /**
   * Get the SQL for an "after" column modifier.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyAfter(Blueprint $blueprint, Fluent $column) {
    if (!is_null($column->after)) {
      return ' AFTER ' . $this->wrap($column->after);
    }

    return '';
  }

  /**
   * Get the SQL for an "comment" column modifier.
   *
   * @param Webim\Database\Schema\Blueprint $blueprint
   * @param Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyComment(Blueprint $blueprint, Fluent $column) {
    if (!is_null($column->comment)) {
      return ' COMMENT "' . $column->comment . '"';
    }

    return '';
  }

  /**
   * Wrap a single string in keyword identifiers.
   *
   * @param string $value
   *
   * @return string
   */
  protected function wrapValue($value) {
    if ($value === '*') return $value;

    return '`' . str_replace('`', '``', $value) . '`';
  }

}