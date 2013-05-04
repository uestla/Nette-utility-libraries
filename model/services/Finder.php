<?php

namespace Model\Services;

use Nette;
use Closure;
use Iterator;
use SplFileInfo;
use ArrayIterator;
use Nette\Utils\Finder as NFinder;


/**
 * @method Finder in(string|array $path)
 * @method Finder from(string|array $path)
 * @method Finder childFirst()
 * @method Finder exclude(mixed $mask)
 * @method Finder filter(callable $callback)
 * @method Finder limitDepth(int $depth)
 * @method Finder size(string $operator, int $size = NULL)
 * @method Finder date(string $operator, mixed $date = NULL)
 */
class Finder extends Nette\Object implements \Countable
{

	/** @var NFinder */
	protected $finder;

	/** @var Closure[] */
	protected $orders = array();

	/** @var SplFileInfo[] */
	protected $files = NULL;



	/** @param  NFinder */
	function __construct(NFinder $finder)
	{
		$this->finder = $finder;
	}



	/**
	 * @param  Closure|Nette\Callback
	 * @return Finder provides fluent interface
	 */
	function order($callback)
	{
		$this->orders[] = callback($callback);

		return $this;
	}



	/**
	 * @param  bool order in descending order?
	 * @return Finder provides fluent interface
	 */
	function orderByName($desc = FALSE)
	{
		$this->order(function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return strcasecmp($a->getFilename(), $b->getFilename()) * ($desc ? -1 : 1);
		});

		return $this;
	}



	/**
	 * @param  bool order in descending order?
	 * @return Finder provides fluent interface
	 */
	function orderBySize($desc = FALSE)
	{
		$this->order(function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return ($a->getSize() - $b->getSize()) * ($desc ? -1 : 1);
		});

		return $this;
	}



	/**
	 * @param  bool order in descending order?
	 * @return Finder provides fluent interface
	 */
	function orderByType($desc = FALSE)
	{
		$this->order(function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return strcasecmp($a->getExtension(), $b->getExtension()) * ($desc ? -1 : 1);
		});

		return $this;
	}



	/**
	 * @param  bool order in descending order?
	 * @return Finder provides fluent interface
	 */
	function orderByMTime($desc = FALSE)
	{
		$this->order(function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return ($a->getMTime() - $b->getMTime()) * ($desc ? -1 : 1);
		});

		return $this;
	}



	/** @return Finder provides fluent interface */
	function orderRandomly()
	{
		$this->order(function (SplFileInfo $a, SplFileInfo $b) {
			return mt_rand(-1, 1);
		});

		return $this;
	}



	/** @return Closure */
	protected function getSortCallback()
	{
		$orders = $this->orders;
		return function (SplFileInfo $a, SplFileInfo $b) use ($orders) {
			reset($orders);

			foreach ($orders as $cb) {
				if (($result = $cb($a, $b)) !== 0) {
					return $result;
				}
			}

			return $result;
		};
	}



	/** @return Iterator */
	function getIterator()
	{
		if ($this->orders !== NULL) {
			$this->loadFiles();
			$iterator = new ArrayIterator($this->files);
			$iterator->uasort($this->getSortCallback());
			return $iterator;
		}

		return $this->finder->getIterator();
	}



	/** @return array */
	function toArray()
	{
		return @iterator_to_array( $this->getIterator() ); // intentionally @ due to PHP bug #50688
	}



	/** @return int */
	function count()
	{
		$this->loadFiles();
		return count($this->files);
	}



	/** @return void */
	protected function loadFiles()
	{
		if ($this->files === NULL) {
			$this->files = iterator_to_array($this->finder->getIterator());
		}
	}



	/** @return void */
	protected function invalidate()
	{
		$this->files = NULL;
	}



	/**
	 * @param  string
	 * @param  array
	 * @return mixed
	 */
	function __call($name, $args)
	{
		try {
			$this->invalidate();
			callback($this->finder, $name)->invokeArgs($args);
			return $this;

		} catch (Nette\InvalidArgumentException $e) {
			return parent::__call($name, $args);
		}
	}



	/**
	 * @param  string
	 * @param  array
	 * @return mixed
	 */
	static function __callStatic($name, $args)
	{
		try {
			return new static(callback('Nette\Utils\Finder::' . $name)->invokeArgs($args));

		} catch (Nette\InvalidArgumentException $e) {
			return parent::__callStatic($name, $args);
		}
	}

}
