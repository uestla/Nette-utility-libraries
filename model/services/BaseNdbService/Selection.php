<?php

namespace Model\Services\Database;

use Nette\Database\Table\Selection as NSelection;


class Selection extends NSelection
{

	/**
	 * @param  string|array
	 * @return Selection
	 */
	function order($columns)
	{
		if (is_array($columns)) {
			$order = array();
			foreach ($columns as $name => $desc) {
				$order[] = $name . ' ' . ($desc ? 'DESC' : 'ASC');
			}

			$columns = implode(', ', $order);
		}

		return parent::order($columns);
	}



	/**
	 * @param  NSelection
	 * @return array
	 */
	static function toArray( NSelection $selection )
	{
		$primary = $selection->getPrimary();
		return is_array($primary) || $primary === NULL ? iterator_to_array( $selection ) : $selection->fetchPairs( $primary );
	}



	/** @return array */
	function asArray()
	{
		return static::toArray( $this );
	}

}
