<?php

use Nette\Diagnostics\Debugger;

require_once 'Nette/loader.php';
require_once 'PHPUnit/Autoload.php';


Debugger::$strictMode = TRUE;
Debugger::enable( Debugger::DEVELOPMENT );
