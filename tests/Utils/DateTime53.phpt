<?php

/**
 * Test: DateTime53 test.
 *
 * @author     David Grudl
 * @package    Nette
 * @subpackage UnitTests
 */

use Nette\Annotations;



require __DIR__ . '/../bootstrap.php';



date_default_timezone_set('Europe/Prague');

$obj = new DateTime53('Mon, 23 Jan 1978 10:00:00', new DateTimeZone('Europe/London'));

Assert::same( '1978-01-23 10:00:00', $obj->format('Y-m-d H:i:s') );
Assert::same( 'Europe/London', $obj->getTimezone()->getName() );
Assert::same( 254397600, $obj->getTimestamp() );


$obj = unserialize(serialize($obj));

Assert::same( '1978-01-23 10:00:00', $obj->format('Y-m-d H:i:s') );
Assert::same( 'Europe/London', $obj->getTimezone()->getName() );
Assert::same( 254397600, $obj->getTimestamp() );




$obj = new DateTime53(NULL, new DateTimeZone('Europe/London'));
$obj->setTimestamp(254400000);

Assert::same( '1978-01-23 10:40:00', $obj->format('Y-m-d H:i:s') );
Assert::same( 'Europe/London', $obj->getTimezone()->getName() );
Assert::same( 254400000, $obj->getTimestamp() );


$obj = unserialize(serialize($obj));

Assert::same( '1978-01-23 10:40:00', $obj->format('Y-m-d H:i:s') );
Assert::same( 'Europe/London', $obj->getTimezone()->getName() );
Assert::same( 254400000, $obj->getTimestamp() );
