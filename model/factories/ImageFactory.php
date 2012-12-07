<?php

namespace Model\Factories;

use Nette;


class ImageFactory extends Nette\Object
{
	/** @return Nette\Image */
	function create($file)
	{
		return Nette\Image::fromFile($file);
	}
}
