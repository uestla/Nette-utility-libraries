<?php

namespace Model\Services;

use Nette;
use Nette\Caching;
use Nette\Database\Table;
use Nette\Database\Connection;

require_once __DIR__ . '/Selection.php';


/** @property-read Caching\Cache $cache */
abstract class BaseNdbService extends Nette\Object
{

	/** @var array */
	private static $transactionCounter = array();

	/** @var \Closure */
	protected $cacheFactory = NULL;

	/** @var Caching\Cache */
	private $cache = NULL;

	/** @var Connection */
	protected $connection;



	const TABLE = '';



	/**
	 * @param  Connection
	 * @param  Caching\IStorage
	 */
	function __construct(Connection $connection, Caching\IStorage $storage)
	{
		$this->connection = $connection;
		!isset( self::$transactionCounter[ $dsn = $connection->dsn ] ) && ( self::$transactionCounter[ $dsn ] = 0 );

		$this->cacheFactory = function () use ($storage) {
			return new Caching\Cache( $storage, __CLASS__ );
		};
	}



	/** @return Caching\Cache */
	function getCache()
	{
		if ($this->cache === NULL) {
			$this->cache = callback( $this->cacheFactory )->invoke();
		}

		return $this->cache;
	}



	// === CRUD ====================================================

	/**
	 * @param  mixed
	 * @return Database\Selection|Table\ActiveRow|FALSE
	 */
	function get($id = NULL)
	{
		$argn = func_num_args();
		$selection = $this->getSelection();

		if ($argn === 0) { // get all
			return $selection;

		} elseif ($argn === 1) { // primary or associative array
			if (is_array($id)) {
				$selection->where($id);
				return $this->isUnique( array_keys($id) ) ? $selection->fetch() : $selection;
			}

			return $selection->get($id);

		} elseif ($argn === 2) { // column, value
			$column = func_get_arg(0);
			$value = func_get_arg(1);

			$selection->where( $column, $value );
			return $this->isUnique($column) ? $selection->fetch() : $selection;

		} else {
			throw new Nette\InvalidArgumentException("Wrong argument count - none, 1 or 2 expected, " . func_num_args() . " given.");
		}
	}



	/**
	 * @param  mixed
	 * @param  string|NULL
	 * @return Table\ActiveRow
	 */
	function create($values, $table = NULL)
	{
		$this->begin();

			$this->processValues( $values );
			$record = $this->getSelection( $table )->insert($values);

		$this->commit();

		return $record;
	}



	/**
	 * @param  Table\ActiveRow
	 * @param  mixed
	 * @return int
	 */
	function update(Table\ActiveRow $record, $values)
	{
		$this->begin();

			$this->processValues( $values );

			foreach ($values as $key => $val) {
				$record->$key = $val;
			}

			$rows = $record->update();

		$this->commit();

		return $rows;
	}



	/**
	 * @param  Table\ActiveRow
	 * @return int
	 */
	function delete(Table\ActiveRow $record)
	{
		$this->begin();

			$rows = $record->delete();

		$this->commit();

		return $rows;
	}



	/**
	 * @param  mixed
	 * @return void
	 */
	protected function processValues(& $values)
	{}



	// === TRANSACTIONS ====================================================

	/** @return void */
	final protected function begin()
	{
		self::$transactionCounter[ $dsn = $this->connection->dsn ] === 0 && $this->connection->beginTransaction();
		self::$transactionCounter[ $dsn ]++;
	}



	/** @return void */
	final protected function commit()
	{
		if ( !isset(self::$transactionCounter[ $dsn = $this->connection->dsn ]) ) {
			throw new Nette\InvalidStateException("No transaction started.");
		}

		--self::$transactionCounter[ $dsn ] === 0 && $this->connection->commit();
	}



	/** @return void */
	final protected function rollback()
	{
		$this->connection->rollBack();
		self::$transactionCounter[ $this->connection->dsn ] = 0;
	}



	// === SHORTCUTS & HELPERS ====================================================

	/**
	 * @param  string|NULL
	 * @return Table\Selection
	 */
	protected function getSelection($table = NULL)
	{
		return new Database\Selection( $table ?: static::TABLE, $this->connection );
	}



	/**
	 * @param  string|array
	 * @return bool
	 */
	protected function isUnique($column)
	{
		$columns = array();
		foreach ((array) $column as $col) {
			$columns[ $col ] = TRUE;
		}

		ksort($columns);

		$key = 'columns';
		$uniques = $this->getCache()->load($key);

		if ($uniques === NULL) {
			$uniques = array();
			foreach ($this->connection->query('SHOW INDEXES FROM ' . static::TABLE)->fetchAll() as $index) {
				if ($index['Key_name'] === 'PRIMARY' || !$index['Non_unique']) {
					if (!isset($uniques[ $index['Key_name'] ])) {
						$uniques[ $index['Key_name'] ] = array();
					}

					$uniques[ $index['Key_name'] ][ $index['Column_name'] ] = TRUE;
				}
			}

			foreach ($uniques as & $foo) {
				ksort($foo);
			}

			$this->getCache()->save($key, $uniques);
		}

		foreach ($uniques as $cols) {
			if ($columns === $cols) {
				return TRUE;
			}
		}

		return FALSE;
	}

}
