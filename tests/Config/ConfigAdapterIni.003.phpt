<?php

/**
 * Test: Nette\Config\ConfigAdapterIni section.
 *
 * @author     David Grudl
 * @package    Nette\Config
 * @subpackage UnitTests
 */

use Nette\Config\Config;



require __DIR__ . '/../bootstrap.php';

define('TEMP_FILE', __DIR__ . '/tmp/cfg.ini');


// Load INI
$config = Config::fromFile('config2.ini');
Assert::same( array(
	'common' => array(
		'variable' => array(
			'tempDir' => '%appDir%/cache',
			'foo' => '%bar% world',
			'bar' => 'hello',
		),
		'set' => array(
			'date.timezone' => 'Europe/Prague',
			'iconv.internal_encoding' => '%encoding%',
			'mbstring.internal_encoding' => '%encoding%',
			'include_path' => '%appDir%/../_trunk;%appDir%/libs',
		),
	),
	'production' => array(
		'service' => array(
			'Nette-Application-IRouter' => 'Nette\Application\MultiRouter',
			'User' => 'Nette\Security\User',
			'Nette-Autoloader' => 'Nette\AutoLoader',
		),
		'webhost' => 'www.example.com',
		'database' => array(
			'params' => array(
				'host' => 'db.example.com',
				'username' => 'dbuser',
				'password' => 'secret',
				'dbname' => 'dbname',
			),
			'adapter' => 'pdo_mysql',
		),
		'variable' => array(
			'tempDir' => '%appDir%/cache',
			'foo' => '%bar% world',
			'bar' => 'hello',
		),
		'set' => array(
			'date.timezone' => 'Europe/Prague',
			'iconv.internal_encoding' => '%encoding%',
			'mbstring.internal_encoding' => '%encoding%',
			'include_path' => '%appDir%/../_trunk;%appDir%/libs',
		),
	),
	'development' => array(
		'database' => array(
			'params' => array(
				'host' => 'dev.example.com',
				'username' => 'devuser',
				'password' => 'devsecret',
				'dbname' => 'dbname',
			),
			'adapter' => 'pdo_mysql',
		),
		'service' => array(
			'Nette-Application-IRouter' => 'Nette\Application\MultiRouter',
			'User' => 'Nette\Security\User',
			'Nette-Autoloader' => 'Nette\AutoLoader',
		),
		'webhost' => 'www.example.com',
		'variable' => array(
			'tempDir' => '%appDir%/cache',
			'foo' => '%bar% world',
			'bar' => 'hello',
		),
		'set' => array(
			'date.timezone' => 'Europe/Prague',
			'iconv.internal_encoding' => '%encoding%',
			'mbstring.internal_encoding' => '%encoding%',
			'include_path' => '%appDir%/../_trunk;%appDir%/libs',
		),
		'test' => array(
			'host' => 'localhost',
			'params' => array(
				'host' => 'dev.example.com',
				'username' => 'devuser',
				'password' => 'devsecret',
				'dbname' => 'dbname',
			),
			'adapter' => 'pdo_mysql',
		),
	),
	'extra' => array(
		'set' => array(
			'date.timezone' => 'Europe/Paris',
			'iconv.internal_encoding' => '%encoding%',
			'mbstring.internal_encoding' => '%encoding%',
			'include_path' => '%appDir%/../_trunk;%appDir%/libs',
		),
	),
), $config->toArray() );



// Save INI
$config->save(TEMP_FILE);
Assert::match( <<<EOD
; generated by Nette

[common]
variable.tempDir = "%appDir%/cache"
variable.foo = "%bar% world"
variable.bar = "hello"
set.date.timezone = "Europe/Prague"
set.iconv.internal_encoding = "%encoding%"
set.mbstring.internal_encoding = "%encoding%"
set.include_path = "%appDir%/../_trunk;%appDir%/libs"

[production]
service.Nette-Application-IRouter = "Nette\Application\MultiRouter"
service.User = "Nette\Security\User"
service.Nette-Autoloader = "Nette\AutoLoader"
webhost = "www.example.com"
database.params.host = "db.example.com"
database.params.username = "dbuser"
database.params.password = "secret"
database.params.dbname = "dbname"
database.adapter = "pdo_mysql"
variable.tempDir = "%appDir%/cache"
variable.foo = "%bar% world"
variable.bar = "hello"
set.date.timezone = "Europe/Prague"
set.iconv.internal_encoding = "%encoding%"
set.mbstring.internal_encoding = "%encoding%"
set.include_path = "%appDir%/../_trunk;%appDir%/libs"

[development]
database.params.host = "dev.example.com"
database.params.username = "devuser"
database.params.password = "devsecret"
database.params.dbname = "dbname"
database.adapter = "pdo_mysql"
service.Nette-Application-IRouter = "Nette\Application\MultiRouter"
service.User = "Nette\Security\User"
service.Nette-Autoloader = "Nette\AutoLoader"
webhost = "www.example.com"
variable.tempDir = "%appDir%/cache"
variable.foo = "%bar% world"
variable.bar = "hello"
set.date.timezone = "Europe/Prague"
set.iconv.internal_encoding = "%encoding%"
set.mbstring.internal_encoding = "%encoding%"
set.include_path = "%appDir%/../_trunk;%appDir%/libs"
test.host = "localhost"
test.params.host = "dev.example.com"
test.params.username = "devuser"
test.params.password = "devsecret"
test.params.dbname = "dbname"
test.adapter = "pdo_mysql"

[extra]
set.date.timezone = "Europe/Paris"
set.iconv.internal_encoding = "%encoding%"
set.mbstring.internal_encoding = "%encoding%"
set.include_path = "%appDir%/../_trunk;%appDir%/libs"
EOD
, file_get_contents(TEMP_FILE) );



// Save section to INI
$config->save(TEMP_FILE, 'mysection');
Assert::match( <<<EOD
; generated by Nette

[mysection]
common.variable.tempDir = "%appDir%/cache"
common.variable.foo = "%bar% world"
common.variable.bar = "hello"
common.set.date.timezone = "Europe/Prague"
common.set.iconv.internal_encoding = "%encoding%"
common.set.mbstring.internal_encoding = "%encoding%"
common.set.include_path = "%appDir%/../_trunk;%appDir%/libs"
production.service.Nette-Application-IRouter = "Nette\Application\MultiRouter"
production.service.User = "Nette\Security\User"
production.service.Nette-Autoloader = "Nette\AutoLoader"
production.webhost = "www.example.com"
production.database.params.host = "db.example.com"
production.database.params.username = "dbuser"
production.database.params.password = "secret"
production.database.params.dbname = "dbname"
production.database.adapter = "pdo_mysql"
production.variable.tempDir = "%appDir%/cache"
production.variable.foo = "%bar% world"
production.variable.bar = "hello"
production.set.date.timezone = "Europe/Prague"
production.set.iconv.internal_encoding = "%encoding%"
production.set.mbstring.internal_encoding = "%encoding%"
production.set.include_path = "%appDir%/../_trunk;%appDir%/libs"
development.database.params.host = "dev.example.com"
development.database.params.username = "devuser"
development.database.params.password = "devsecret"
development.database.params.dbname = "dbname"
development.database.adapter = "pdo_mysql"
development.service.Nette-Application-IRouter = "Nette\Application\MultiRouter"
development.service.User = "Nette\Security\User"
development.service.Nette-Autoloader = "Nette\AutoLoader"
development.webhost = "www.example.com"
development.variable.tempDir = "%appDir%/cache"
development.variable.foo = "%bar% world"
development.variable.bar = "hello"
development.set.date.timezone = "Europe/Prague"
development.set.iconv.internal_encoding = "%encoding%"
development.set.mbstring.internal_encoding = "%encoding%"
development.set.include_path = "%appDir%/../_trunk;%appDir%/libs"
development.test.host = "localhost"
development.test.params.host = "dev.example.com"
development.test.params.username = "devuser"
development.test.params.password = "devsecret"
development.test.params.dbname = "dbname"
development.test.adapter = "pdo_mysql"
extra.set.date.timezone = "Europe/Paris"
extra.set.iconv.internal_encoding = "%encoding%"
extra.set.mbstring.internal_encoding = "%encoding%"
extra.set.include_path = "%appDir%/../_trunk;%appDir%/libs"
EOD
, file_get_contents(TEMP_FILE) );



// Load section from INI
$config = Config::fromFile('config2.ini', 'development', NULL);
Assert::same( array(
	'database' => array(
		'params' => array(
			'host' => 'dev.example.com',
			'username' => 'devuser',
			'password' => 'devsecret',
			'dbname' => 'dbname',
		),
		'adapter' => 'pdo_mysql',
	),
	'service' => array(
		'Nette-Application-IRouter' => 'Nette\Application\MultiRouter',
		'User' => 'Nette\Security\User',
		'Nette-Autoloader' => 'Nette\AutoLoader',
	),
	'webhost' => 'www.example.com',
	'variable' => array(
		'tempDir' => '%appDir%/cache',
		'foo' => '%bar% world',
		'bar' => 'hello',
	),
	'set' => array(
		'date.timezone' => 'Europe/Prague',
		'iconv.internal_encoding' => '%encoding%',
		'mbstring.internal_encoding' => '%encoding%',
		'include_path' => '%appDir%/../_trunk;%appDir%/libs',
	),
	'test' => array(
		'host' => 'localhost',
		'params' => array(
			'host' => 'dev.example.com',
			'username' => 'devuser',
			'password' => 'devsecret',
			'dbname' => 'dbname',
		),
		'adapter' => 'pdo_mysql',
	),
), $config->toArray() );



// Save INI
$config->display_errors = true;
$config->html_errors = false;
$config->save(TEMP_FILE, 'mysection');
Assert::match( <<<EOD
; generated by Nette

[mysection]
database.params.host = "dev.example.com"
database.params.username = "devuser"
database.params.password = "devsecret"
database.params.dbname = "dbname"
database.adapter = "pdo_mysql"
service.Nette-Application-IRouter = "Nette\Application\MultiRouter"
service.User = "Nette\Security\User"
service.Nette-Autoloader = "Nette\AutoLoader"
webhost = "www.example.com"
variable.tempDir = "%appDir%/cache"
variable.foo = "%bar% world"
variable.bar = "hello"
set.date.timezone = "Europe/Prague"
set.iconv.internal_encoding = "%encoding%"
set.mbstring.internal_encoding = "%encoding%"
set.include_path = "%appDir%/../_trunk;%appDir%/libs"
test.host = "localhost"
test.params.host = "dev.example.com"
test.params.username = "devuser"
test.params.password = "devsecret"
test.params.dbname = "dbname"
test.adapter = "pdo_mysql"
display_errors = true
html_errors = false
EOD
, file_get_contents(TEMP_FILE) );
