<?php
/**
 * @author Orhan POLAT
 */

namespace Webim\Database\Driver\Sqlserver;

use Webim\Database\Fluent;
use Webim\Database\Schema\Blueprint;
use Webim\Database\Schema\Grammar;

class Schema extends Grammar {

  /**
   * The possible column modifiers.
   *
   * @var array
   */
  protected $modifiers = array('Increment', 'Nullable', 'Default');

  /**
   * The columns available as serials.
   *
   * @var array
   */
  protected $serials = array('bigInteger', 'integer');

  /**
   * Compile the query to determine if a table exists.
   *
   * @return string
   */
  public function compileTableExists() {
    return "SELECT * FROM sysobjects WHERE type = 'U' AND name = ?";
  }

  /**
   * Compile the query to determine the list of columns.
   *
   * @param  string $table
   *
   * @return string
   */
  public function compileColumnExists($table) {
    return "SELECT col.name FROM sys.columns AS col
                JOIN sys.objects AS obj ON col.object_id = obj.object_id
                WHERE obj.type = 'U' AND obj.name = '$table'";
  }

  /**
   * Compile a create table command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileCreate(Blueprint $blueprint, Fluent $command) {
    $columns = implode(', ', $this->getColumns($blueprint));

    return 'CREATE TABLE ' . $this->wrapTable($blueprint) . " ($columns)";
  }

  /**
   * Compile a create table command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileAdd(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    $columns = $this->getColumns($blueprint);

    return 'ALTER TABLE ' . $table . ' ADD ' . implode(', ', $columns);
  }

  /**
   * Compile a primary key command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compilePrimary(Blueprint $blueprint, Fluent $command) {
    $columns = $this->columnize($command->columns);

    $table = $this->wrapTable($blueprint);

    return "ALTER TABLE {$table} ADD CONSTRAINT {$command->index} PRIMARY KEY ({$columns})";
  }

  /**
   * Compile a unique key command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileUnique(Blueprint $blueprint, Fluent $command) {
    $columns = $this->columnize($command->columns);

    $table = $this->wrapTable($blueprint);

    return "CREATE UNIQUE INDEX {$command->index} ON {$table} ({$columns})";
  }

  /**
   * Compile a plain index key command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileIndex(Blueprint $blueprint, Fluent $command) {
    $columns = $this->columnize($command->columns);

    $table = $this->wrapTable($blueprint);

    return "CREATE INDEX {$command->index} ON {$table} ({$columns})";
  }

  /**
   * Compile a drop table command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDrop(Blueprint $blueprint, Fluent $command) {
    return 'DROP TABLE ' . $this->wrapTable($blueprint);
  }

  /**
   * Compile a drop column command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropColumn(Blueprint $blueprint, Fluent $command) {
    $columns = $this->wrapArray($command->columns);

    $table = $this->wrapTable($blueprint);

    return 'ALTER TABLE ' . $table . ' DROP COLUMN ' . implode(', ', $columns);
  }

  /**
   * Compile a drop primary key command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropPrimary(Blueprint $blueprint, Fluent $command) {
    $table = $blueprint->getTable();

    $table = $this->wrapTable($blueprint);

    return "ALTER TABLE {$table} DROP CONSTRAINT {$command->index}";
  }

  /**
   * Compile a drop unique key command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropUnique(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    return "DROP INDEX {$command->index} ON {$table}";
  }

  /**
   * Compile a drop index command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropIndex(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    return "DROP INDEX {$command->index} ON {$table}";
  }

  /**
   * Compile a drop foreign key command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileDropForeign(Blueprint $blueprint, Fluent $command) {
    $table = $this->wrapTable($blueprint);

    return "ALTER TABLE {$table} DROP CONSTRAINT {$command->index}";
  }

  /**
   * Compile a rename table command.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $command
   *
   * @return string
   */
  public function compileRename(Blueprint $blueprint, Fluent $command) {
    $from = $this->wrapTable($blueprint);

    return "SP_RENAME {$from}, " . $this->wrapTable($command->to);
  }

  /**
   * Create the column definition for a char type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeChar(Fluent $column) {
    return "nchar({$column->length})";
  }

  /**
   * Create the column definition for a string type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeString(Fluent $column) {
    return "nvarchar({$column->length})";
  }

  /**
   * Create the column definition for a text type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeText(Fluent $column) {
    return 'nvarchar(max)';
  }

  /**
   * Create the column definition for a medium text type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeMediumText(Fluent $column) {
    return 'nvarchar(max)';
  }

  /**
   * Create the column definition for a long text type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeLongText(Fluent $column) {
    return 'nvarchar(max)';
  }

  /**
   * Create the column definition for a integer type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeInteger(Fluent $column) {
    return 'int';
  }

  /**
   * Create the column definition for a big integer type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeBigInteger(Fluent $column) {
    return 'bigint';
  }

  /**
   * Create the column definition for a medium integer type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeMediumInteger(Fluent $column) {
    return 'int';
  }

  /**
   * Create the column definition for a tiny integer type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeTinyInteger(Fluent $column) {
    return 'tinyint';
  }

  /**
   * Create the column definition for a small integer type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeSmallInteger(Fluent $column) {
    return 'smallint';
  }

  /**
   * Create the column definition for a float type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeFloat(Fluent $column) {
    return 'float';
  }

  /**
   * Create the column definition for a double type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDouble(Fluent $column) {
    return 'float';
  }

  /**
   * Create the column definition for a decimal type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDecimal(Fluent $column) {
    return "decimal({$column->total}, {$column->places})";
  }

  /**
   * Create the column definition for a boolean type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeBoolean(Fluent $column) {
    return 'bit';
  }

  /**
   * Create the column definition for an enum type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeEnum(Fluent $column) {
    return 'nvarchar(255)';
  }

  /**
   * Create the column definition for a date type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDate(Fluent $column) {
    return 'date';
  }

  /**
   * Create the column definition for a date-time type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeDateTime(Fluent $column) {
    return 'datetime';
  }

  /**
   * Create the column definition for a time type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeTime(Fluent $column) {
    return 'time';
  }

  /**
   * Create the column definition for a timestamp type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeTimestamp(Fluent $column) {
    return 'datetime';
  }

  /**
   * Create the column definition for a binary type.
   *
   * @param  Webim\Database\Fluent $column
   *
   * @return string
   */
  protected function typeBinary(Fluent $column) {
    return 'varbinary(max)';
  }

  /**
   * Get the SQL for a nullable column modifier.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyNullable(Blueprint $blueprint, Fluent $column) {
    return $column->nullable ? ' NULL' : ' NOT NULL';
  }

  /**
   * Get the SQL for a default column modifier.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyDefault(Blueprint $blueprint, Fluent $column) {
    if (!is_null($column->default)) {
      return ' DEFAULT ' . $this->getDefaultValue($column->default);
    }

    return null;
  }

  /**
   * Get the SQL for an auto-increment column modifier.
   *
   * @param  Webim\Database\Schema\Blueprint $blueprint
   * @param  Webim\Database\Fluent $column
   *
   * @return string|null
   */
  protected function modifyIncrement(Blueprint $blueprint, Fluent $column) {
    if (in_array($column->type, $this->serials) && $column->autoIncrement) {
      return ' IDENTITY PRIMARY KEY';
    }

    return null;
  }

}