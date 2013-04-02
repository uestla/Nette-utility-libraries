<?php

namespace Model\Services;

use Nette;
use Closure;
use Iterator;
use SplFileInfo;
use ArrayIterator;
use Nette\Utils\Finder as NFinder;


class Finder extends NFinder implements \Countable
{
	/** @var Closure[] */
	protected $orders = array();



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
		$this->orders[] = function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return strcasecmp( $a->getFilename(), $b->getFilename() ) * ($desc ? -1 : 1);
		};

		return $this;
	}



	/**
	 * @param  bool order in descending order?
	 * @return Finder provides fluent interface
	 */
	function orderBySize($desc = FALSE)
	{
		$this->orders[] = function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return ( $a->getSize() - $b->getSize() ) * ($desc ? -1 : 1);
		};

		return $this;
	}



	/**
	 * @param  bool order in descending order?
	 * @return Finder provides fluent interface
	 */
	function orderByType($desc = FALSE)
	{
		$this->orders[] = function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return strcasecmp( $a->getExtension(), $b->getExtension() ) * ($desc ? -1 : 1);
		};

		return $this;
	}



	/**
	 * @param  bool order in descending order?
	 * @return Finder provides fluent interface
	 */
	function orderByMTime($desc = FALSE)
	{
		$this->orders[] = function (SplFileInfo $a, SplFileInfo $b) use ($desc) {
			return ( $a->getMTime() - $b->getMTime() ) * ($desc ? -1 : 1);
		};

		return $this;
	}



	/** @return Finder provides fluent interface */
	function orderRandomly()
	{
		$this->orders[] = function (SplFileInfo $a, SplFileInfo $b) {
			return mt_rand(-512, 512);
		};

		return $this;
	}



	/** @return Closure */
	protected function getSortCallback()
	{
		$orders = $this->orders;
		return function (SplFileInfo $a, SplFileInfo $b) use ($orders) {
			reset($orders);

			foreach ($orders as $cb) {
				$result = callback($cb)->invokeArgs(array($a, $b));
				if ($result !== 0) {
					return $result;
				}
			}

			return 0;
		};
	}



	/** @return Iterator|ArrayIterator */
	function getIterator()
	{
		$iterator = parent::getIterator();
		if ($this->orders !== NULL) {
			$iterator = new ArrayIterator( iterator_to_array( $iterator ) );
			$iterator->uasort( $this->getSortCallback() );
		}

		return $iterator;
	}



	/** @return array */
	function toArray()
	{
		return @iterator_to_array( $this->getIterator() ); // intentionally @ due to PHP bug #50688
	}



	/** @return int */
	function count()
	{
		return count( $this->toArray() );
	}
}
