<?php

namespace Model\Helpers;

use Nette;
use DateTime;
use Nette\Utils\Strings;


/**
 * Birth certificate number helper class
 * Operates with the BCN according to Czech & Slovakian law
 *
 * Based on http://latrine.dgx.cz/jak-overit-platne-ic-a-rodne-cislo
 */
class BCN extends Nette\Object
{
	/** @var string */
	protected $bcn;

	/** @var DateTime */
	protected $birthDate;

	/** @var string */
	protected $sex;



	const PATTERN = '\s*(\d\d)(\d\d)(\d\d)[ /]*(\d\d\d)(\d?)\s*';
	const SEX_MALE = 'male';
	const SEX_FEMALE = 'female';



	/** @param  string */
	function __construct($bcn)
	{
		do {
			if (!($matches = Strings::match($bcn, '#^' . static::PATTERN . '$#'))) {
				break;
			}

			list(, $year, $month, $day, $ext, $c) = $matches;
			$this->bcn = $year . $month . $day . '/' . $ext . $c;

			if ($c === '') {
				if ($year >= 54) break;

			} else {
				$mod = ($year . $month . $day . $ext) % 11;
				if ($mod === 10) $mod = 0;
				if ($mod !== (int) $c) {
					break;
				}
			}

			$year += $year < 54 ? 2000 : 1900;

			if ($month > 70 && $year > 2003) {
				$month -= 70;
				$this->sex = static::SEX_FEMALE;

			} elseif ($month > 50) {
				$month -= 50;
				$this->sex = static::SEX_FEMALE;

			} elseif ($month > 20 && $year > 2003) {
				$month -= 20;
				$this->sex = static::SEX_MALE;

			} else {
				$this->sex = static::SEX_MALE;
			}

			if (!checkdate($month, $day, $year)) {
				break;
			}

			$this->birthDate = new DateTime("$year-$month-$day");
			return ;

		} while (FALSE);

		throw new InvalidBCNException("Invalid BCN '$bcn'.");
	}



	/**
	 * @param  string
	 * @return bool
	 */
	static function isValid($bcn)
	{
		try {
			new static($bcn);
			return TRUE;

		} catch (InvalidBCNException $e) {
			return FALSE;
		}
	}



	/** @return string */
	function getBCN()
	{
		return $this->bcn;
	}



	/** @return DateTime */
	function getBirthDate()
	{
		return $this->birthDate;
	}



	/** @return bool */
	function isMale()
	{
		return $this->sex === static::SEX_MALE;
	}



	/** @return bool */
	function isFemale()
	{
		return $this->sex === static::SEX_FEMALE;
	}
}



class InvalidBCNException extends Nette\InvalidArgumentException {}
