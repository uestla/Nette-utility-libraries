<?php

use Model\Services\Finder;

require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../model/services/Finder.php';


class FinderTest extends PHPUnit_Framework_TestCase
{
	function testOrderings()
	{
		$expected = array(
			($first = __DIR__ . '/files\style.css') => new SplFileInfo( $first ),
			($second = __DIR__ . '/files\logo.png') => new SplFileInfo( $second ),
		);

		$actual = Finder::findFiles('*')
			->in( __DIR__ . '/files' )
			->orderByName(TRUE)
			->toArray();

		$this->assertEquals( $expected, $actual );
	}



	function testCount()
	{
		$this->assertEquals( 2, count( Finder::findFiles('*')->in( __DIR__ . '/files' ) ) );
	}
}
