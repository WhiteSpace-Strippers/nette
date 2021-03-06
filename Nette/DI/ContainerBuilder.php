<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 *
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Nette\DI;

use Nette,
	Nette\Utils\Validators,
	Nette\Utils\Strings,
	Nette\Reflection,
	Nette\PhpGenerator\Helpers as PhpHelpers,
	Nette\PhpGenerator\PhpLiteral;



/**
 * Basic container builder.
 *
 * @author     David Grudl
 * @property-read ServiceDefinition[] $definitions
 * @property-read array $dependencies
 */
class ContainerBuilder extends Nette\Object
{
	const THIS_SERVICE = 'self',
	THIS_CONTAINER = 'container';

	/** @var array  %param% will be expanded */
	public $parameters = array();

	/** @var ServiceDefinition[] */
	private $definitions = array();

	/** @var array for auto-wiring */
	private $classes;

	/** @var array of file names */
	private $dependencies = array();

	/** @var Nette\PhpGenerator\ClassType[] */
	private $generatedClasses = array();



	/**
	 * Adds new service definition. The expressions %param% and @service will be expanded.
	 * @param  string
	 * @return ServiceDefinition
	 */
	public function addDefinition($name)
	{
	if (!is_string($name) || !$name) { // builder is not ready for falsy names such as '0'
		throw new Nette\InvalidArgumentException("Service name must be a non-empty string, " . gettype($name) . " given.");

	} elseif (isset($this->definitions[$name])) {
		throw new Nette\InvalidStateException("Service '$name' has already been added.");
	}
	return $this->definitions[$name] = new ServiceDefinition;
	}



	/**
	 * Removes the specified service definition.
	 * @param  string
	 * @return void
	 */
	public function removeDefinition($name)
	{
	unset($this->definitions[$name]);
	}



	/**
	 * Gets the service definition.
	 * @param  string
	 * @return ServiceDefinition
	 */
	public function getDefinition($name)
	{
	if (!isset($this->definitions[$name])) {
		throw new MissingServiceException("Service '$name' not found.");
	}
	return $this->definitions[$name];
	}



	/**
	 * Gets all service definitions.
	 * @return array
	 */
	public function getDefinitions()
	{
	return $this->definitions;
	}



	/**
	 * Does the service definition exist?
	 * @param  string
	 * @return bool
	 */
	public function hasDefinition($name)
	{
	return isset($this->definitions[$name]);
	}



	/********************* class resolving ****************d*g**/



	/**
	 * Resolves service name by type.
	 * @param  string  class or interface
	 * @return string  service name or NULL
	 * @throws ServiceCreationException
	 */
	public function getByType($class)
	{
	$lower = ltrim(strtolower($class), '\\');
	if (!isset($this->classes[$lower])) {
		return;

	} elseif (count($this->classes[$lower]) === 1) {
		return $this->classes[$lower][0];

	} else {
		throw new ServiceCreationException("Multiple services of type $class found: " . implode(', ', $this->classes[$lower]));
	}
	}



	/**
	 * Gets the service objects of the specified tag.
	 * @param  string
	 * @return array of [service name => tag attributes]
	 */
	public function findByTag($tag)
	{
	$found = array();
	foreach ($this->definitions as $name => $def) {
		if (isset($def->tags[$tag]) && $def->shared) {
		$found[$name] = $def->tags[$tag];
		}
	}
	return $found;
	}



	/**
	 * Creates a list of arguments using autowiring.
	 * @return array
	 */
	public function autowireArguments($class, $method, array $arguments)
	{
	$rc = Reflection\ClassType::from($class);
	if (!$rc->hasMethod($method)) {
		if (!Nette\Utils\Arrays::isList($arguments)) {
		throw new ServiceCreationException("Unable to pass specified arguments to $class::$method().");
		}
		return $arguments;
	}

	$rm = $rc->getMethod($method);
	if (!$rm->isPublic()) {
		throw new ServiceCreationException("$rm is not callable.");
	}
	$this->addDependency($rm->getFileName());
	return Helpers::autowireArguments($rm, $arguments, $this);
	}



	/**
	 * Generates $dependencies, $classes and expands and normalize class names.
	 * @return array
	 */
	public function prepareClassList()
	{
	// prepare generated factories
	foreach ($this->definitions as $name => $def) {
		if (!$def->implement) {
		continue;
		}

		if (!interface_exists($def->implement)) {
		throw new Nette\InvalidStateException("Interface $def->implement has not been found.");
		}
		$rc = Reflection\ClassType::from($def->implement);
		if (count($rc->getMethods()) !== 1 || !$rc->hasMethod('create') || $rc->getMethod('create')->isStatic()) {
		throw new Nette\InvalidStateException("Interface $def->implement must have just one non-static method create().");
		}
		$def->implement = $rc->getName();

		if (!$def->class && empty($def->factory->entity)) {
		$method = $rc->getMethod('create');
		$returnType = $method->getAnnotation('return');
		if (!$returnType) {
			throw new Nette\InvalidStateException("Method $method has not @return annotation.");
		}
		if (!class_exists($returnType)) {
			if ($returnType[0] !== '\\') {
			$returnType = $rc->getNamespaceName() . '\\' . $returnType;
			}
			if (!class_exists($returnType)) {
			throw new Nette\InvalidStateException("Please use a fully qualified name of class in @return annotation at $method method. Class '$returnType' cannot be found.");
			}
		}
		$def->setClass($returnType);
		}

		if (!$def->parameters) {
		foreach ($rc->getMethod('create')->getParameters() as $param) {
			$paramDef = ($param->isArray() ? 'array' : $param->getClassName()) . ' ' . $param->getName();
			if ($param->isOptional()) {
			$def->parameters[$paramDef] = $param->getDefaultValue();
			} else {
			$def->parameters[] = $paramDef;
			}
		}
		}
	}

	// complete class-factory pairs; expand classes
	foreach ($this->definitions as $name => $def) {
		if ($def->class) {
		$def->class = $this->expand($def->class);
		if (!$def->factory) {
			$def->factory = new Statement($def->class);
		}
		} elseif (!$def->factory) {
		throw new ServiceCreationException("Class and factory are missing in service '$name' definition.");
		}
	}

	// check if services are instantiable
	foreach ($this->definitions as $name => $def) {
		$factory = $this->normalizeEntity($this->expand($def->factory->entity));
		if (is_string($factory) && preg_match('#^[\w\\\\]+\z#', $factory) && $factory !== self::THIS_SERVICE) {
		if (!class_exists($factory) || !Reflection\ClassType::from($factory)->isInstantiable()) {
			throw new Nette\InvalidStateException("Class $factory used in service '$name' has not been found or is not instantiable.");
		}
		}
	}

	// complete classes
	$this->classes = FALSE;
	foreach ($this->definitions as $name => $def) {
		$this->resolveClass($name);

		if (!$def->class) {
		continue;
		} elseif (!class_exists($def->class) && !interface_exists($def->class)) {
		throw new Nette\InvalidStateException("Class or interface $def->class used in service '$name' has not been found.");
		} else {
		$def->class = Reflection\ClassType::from($def->class)->getName();
		}
	}

	//  build auto-wiring list
	$this->classes = array();
	foreach ($this->definitions as $name => $def) {
		$class = $def->implement ?: $def->class;
		if ($def->autowired && $class) {
		foreach (class_parents($class) + class_implements($class) + array($class) as $parent) {
			$this->classes[strtolower($parent)][] = (string) $name;
		}
		}
	}

	foreach ($this->classes as $class => $foo) {
		$this->addDependency(Reflection\ClassType::from($class)->getFileName());
	}
	}



	private function resolveClass($name, $recursive = array())
	{
	if (isset($recursive[$name])) {
		throw new Nette\InvalidArgumentException('Circular reference detected for services: ' . implode(', ', array_keys($recursive)) . '.');
	}
	$recursive[$name] = TRUE;

	$def = $this->definitions[$name];
	$factory = $this->normalizeEntity($this->expand($def->factory->entity));

	if ($def->class) {
		return $def->class;

	} elseif (is_array($factory)) { // method calling
		if ($service = $this->getServiceName($factory[0])) {
		if (Strings::contains($service, '\\')) { // @\Class
			throw new ServiceCreationException("Unable resolve class name for service '$name'.");
		}
		$factory[0] = $this->resolveClass($service, $recursive);
		if (!$factory[0]) {
			return;
		}
		if ($this->definitions[$service]->implement && $factory[1] === 'create') {
			return $def->class = $factory[0];
		}
		}
		$factory = new Nette\Callback($factory);
		if (!$factory->isCallable()) {
		throw new Nette\InvalidStateException("Factory '$factory' is not callable.");
		}
		try {
		$reflection = $factory->toReflection();
		$def->class = preg_replace('#[|\s].*#', '', $reflection->getAnnotation('return'));
		if ($def->class && !class_exists($def->class) && $def->class[0] !== '\\' && $reflection instanceof \ReflectionMethod) {
			/**/$def->class = $reflection->getDeclaringClass()->getNamespaceName() . '\\' . $def->class;/**/
		}
		} catch (\ReflectionException $e) {
		}

	} elseif ($service = $this->getServiceName($factory)) { // alias or factory
		if (Strings::contains($service, '\\')) { // @\Class
		/*5.2* $service = ltrim($service, '\\');*/
		$def->autowired = FALSE;
		return $def->class = $service;
		}
		if ($this->definitions[$service]->shared) {
		$def->autowired = FALSE;
		}
		return $def->class = $this->definitions[$service]->implement ?: $this->resolveClass($service, $recursive);

	} else {
		return $def->class = $factory; // class name
	}
	}



	/**
	 * Adds a file to the list of dependencies.
	 * @return ContainerBuilder  provides a fluent interface
	 */
	public function addDependency($file)
	{
	$this->dependencies[$file] = TRUE;
	return $this;
	}



	/**
	 * Returns the list of dependent files.
	 * @return array
	 */
	public function getDependencies()
	{
	unset($this->dependencies[FALSE]);
	return array_keys($this->dependencies);
	}



	/********************* code generator ****************d*g**/



	/**
	 * Generates PHP classes. First class is the container.
	 * @return Nette\PhpGenerator\ClassType[]
	 */
	public function generateClasses()
	{
	unset($this->definitions[self::THIS_CONTAINER]);
	$this->addDefinition(self::THIS_CONTAINER)->setClass('Nette\DI\Container');

	$this->generatedClasses = array();
	$this->prepareClassList();

	$containerClass = $this->generatedClasses[] = new Nette\PhpGenerator\ClassType('Container');
	$containerClass->addExtend('Nette\DI\Container');
	$containerClass->addMethod('__construct')
		->addBody('parent::__construct(?);', array($this->expand($this->parameters)));

	$prop = $containerClass->addProperty('classes', array());
	foreach ($this->classes as $name => $foo) {
		try {
		$prop->value[$name] = $this->getByType($name);
		} catch (ServiceCreationException $e) {
		$prop->value[$name] = new PhpLiteral('FALSE, //' . strstr($e->getMessage(), ':'));
		}
	}

	$definitions = $this->definitions;
	ksort($definitions);

	$meta = $containerClass->addProperty('meta', array());
	foreach ($definitions as $name => $def) {
		if ($def->shared) {
		foreach ($this->expand($def->tags) as $tag => $value) {
			$meta->value[$name][Container::TAGS][$tag] = $value;
		}
		}
	}

	foreach ($definitions as $name => $def) {
		try {
		$name = (string) $name;
		$methodName = Container::getMethodName($name, $def->shared);
		if (!PhpHelpers::isIdentifier($methodName)) {
			throw new ServiceCreationException('Name contains invalid characters.');
		}
		$method = $containerClass->addMethod($methodName)
			->addDocument("@return " . ($def->implement ?: $def->class))
			->setVisibility($def->shared ? 'protected' : 'public')
			->setBody($name === self::THIS_CONTAINER ? 'return $this;' : $this->generateService($name))
			->setParameters($def->implement ? array() : $this->convertParameters($def->parameters));
		} catch (\Exception $e) {
		throw new ServiceCreationException("Service '$name': " . $e->getMessage(), NULL, $e);
		}
	}

	return $this->generatedClasses;
	}



	/**
	 * Generates body of service method.
	 * @return string
	 */
	private function generateService($name)
	{
	$def = $this->definitions[$name];
	$parameters = $this->parameters;
	foreach ($this->expand($def->parameters) as $k => $v) {
		$v = explode(' ', is_int($k) ? $v : $k);
		$parameters[end($v)] = new PhpLiteral('$' . end($v));
	}

	$code = '$service = ' . $this->formatStatement(Helpers::expand($def->factory, $parameters, TRUE)) . ";\n";

	$entity = $this->normalizeEntity($def->factory->entity);
	if ($def->class && $def->class !== $entity && !$this->getServiceName($entity)) {
		$code .= PhpHelpers::formatArgs("if (!\$service instanceof $def->class) {\n"
		. "\tthrow new Nette\\UnexpectedValueException(?);\n}\n",
		array("Unable to create service '$name', value returned by factory is not $def->class type.")
		);
	}

	if ($def->inject) {
		$code .= "\$this->callInjects(\$service);\n";
	}

	foreach ((array) $def->setup as $setup) {
		$setup = Helpers::expand($setup, $parameters, TRUE);
		if (is_string($setup->entity) && strpbrk($setup->entity, ':@?') === FALSE) { // auto-prepend @self
		$setup->entity = array("@$name", $setup->entity);
		}
		$code .= $this->formatStatement($setup, $name) . ";\n";
	}

	$code .= 'return $service;';

	if (!$def->implement) {
		return $code;
	}

	$factoryClass = $this->generatedClasses[] = new Nette\PhpGenerator\ClassType;
	$factoryClass->setName(str_replace(array('\\', '.'), '_', "{$def->implement}Impl_{$name}"))
		->addImplement($def->implement)
		->setFinal(TRUE);

	$factoryClass->addProperty('container')
		->setVisibility('private');

	$factoryClass->addMethod('__construct')
		->addBody('$this->container = $container;')
		->addParameter('container')
		->setTypeHint('Nette\DI\Container');

	$factoryClass->addMethod('create')
		->setParameters($this->convertParameters($def->parameters))
		->setBody(str_replace('$this', '$this->container', $code));

	return "return new {$factoryClass->name}(\$this);";
	}



	/**
	 * Converts parameters from ServiceDefinition to PhpGenerator.
	 * @return Nette\PhpGenerator\Parameter[]
	 */
	private function convertParameters(array $parameters)
	{
	$res = array();
	foreach ($this->expand($parameters) as $k => $v) {
		$tmp = explode(' ', is_int($k) ? $v : $k);
		$param = $res[] = new Nette\PhpGenerator\Parameter;
		$param->setName(end($tmp));
		if (!is_int($k)) {
		$param = $param->setOptional(TRUE)->setDefaultValue($v);
		}
		if (isset($tmp[1])) {
		$param->setTypeHint($tmp[0]);
		}
	}
	return $res;
	}



	/**
	 * Formats PHP code for class instantiating, function calling or property setting in PHP.
	 * @return string
	 * @internal
	 */
	public function formatStatement(Statement $statement, $self = NULL)
	{
	$entity = $this->normalizeEntity($statement->entity);
	$arguments = $statement->arguments;

	if (is_string($entity) && Strings::contains($entity, '?')) { // PHP literal
		return $this->formatPhp($entity, $arguments, $self);

	} elseif ($service = $this->getServiceName($entity)) { // factory calling or service retrieving
		if ($this->definitions[$service]->shared) {
		if ($arguments) {
			throw new ServiceCreationException("Unable to call service '$entity'.");
		}
		return $this->formatPhp('$this->getService(?)', array($service));
		}
		$params = array();
		foreach ($this->definitions[$service]->parameters as $k => $v) {
		$params[] = preg_replace('#\w+\z#', '\$$0', (is_int($k) ? $v : $k)) . (is_int($k) ? '' : ' = ' . PhpHelpers::dump($v));
		}
		$rm = new Reflection\GlobalFunction(create_function(implode(', ', $params), ''));
		$arguments = Helpers::autowireArguments($rm, $arguments, $this);
		return $this->formatPhp('$this->?(?*)', array(Container::getMethodName($service, FALSE), $arguments), $self);

	} elseif ($entity === 'not') { // operator
		return $this->formatPhp('!?', array($arguments[0]));

	} elseif (is_string($entity)) { // class name
		if ($constructor = Reflection\ClassType::from($entity)->getConstructor()) {
		$this->addDependency($constructor->getFileName());
		$arguments = Helpers::autowireArguments($constructor, $arguments, $this);
		} elseif ($arguments) {
		throw new ServiceCreationException("Unable to pass arguments, class $entity has no constructor.");
		}
		return $this->formatPhp("new $entity" . ($arguments ? '(?*)' : ''), array($arguments), $self);

	} elseif (!Nette\Utils\Arrays::isList($entity) || count($entity) !== 2) {
		throw new Nette\InvalidStateException("Expected class, method or property, " . PhpHelpers::dump($entity) . " given.");

	} elseif ($entity[0] === '') { // globalFunc
		return $this->formatPhp("$entity[1](?*)", array($arguments), $self);

	} elseif (Strings::contains($entity[1], '$')) { // property setter
		Validators::assert($arguments, 'list:1', "setup arguments for '" . Nette\Callback::create($entity) . "'");
		if ($this->getServiceName($entity[0], $self)) {
		return $this->formatPhp('?->? = ?', array($entity[0], substr($entity[1], 1), $arguments[0]), $self);
		} else {
		return $this->formatPhp($entity[0] . '::$? = ?', array(substr($entity[1], 1), $arguments[0]), $self);
		}

	} elseif ($service = $this->getServiceName($entity[0], $self)) { // service method
		$class = $this->definitions[$service]->implement ?: $this->definitions[$service]->class;
		if ($class) {
		$arguments = $this->autowireArguments($class, $entity[1], $arguments);
		}
		return $this->formatPhp('?->?(?*)', array($entity[0], $entity[1], $arguments), $self);

	} else { // static method
		$arguments = $this->autowireArguments($entity[0], $entity[1], $arguments);
		return $this->formatPhp("$entity[0]::$entity[1](?*)", array($arguments), $self);
	}
	}



	/**
	 * Formats PHP statement.
	 * @return string
	 */
	public function formatPhp($statement, $args, $self = NULL)
	{
	$that = $this;
	array_walk_recursive($args, function(&$val) use ($self, $that) {
		list($val) = $that->normalizeEntity(array($val));

		if ($val instanceof Statement) {
		$val = new PhpLiteral($that->formatStatement($val, $self));

		} elseif ($val === '@' . ContainerBuilder::THIS_CONTAINER) {
		$val = new PhpLiteral('$this');

		} elseif ($service = $that->getServiceName($val, $self)) {
		$val = $service === $self ? '$service' : $that->formatStatement(new Statement($val));
		$val = new PhpLiteral($val);
		}
	});
	return PhpHelpers::formatArgs($statement, $args);
	}



	/**
	 * Expands %placeholders% in strings (recursive).
	 * @param  mixed
	 * @return mixed
	 */
	public function expand($value)
	{
	return Helpers::expand($value, $this->parameters, TRUE);
	}



	/** @internal */
	public function normalizeEntity($entity)
	{
	if (is_string($entity) && Strings::contains($entity, '::') && !Strings::contains($entity, '?')) { // Class::method -> [Class, method]
		$entity = explode('::', $entity);
	}

	if (is_array($entity) && $entity[0] instanceof ServiceDefinition) { // [ServiceDefinition, ...] -> [@serviceName, ...]
		$tmp = array_keys($this->definitions, $entity[0], TRUE);
		$entity[0] = "@$tmp[0]";

	} elseif ($entity instanceof ServiceDefinition) { // ServiceDefinition -> @serviceName
		$tmp = array_keys($this->definitions, $entity, TRUE);
		$entity = "@$tmp[0]";

	} elseif (is_array($entity) && $entity[0] === $this) { // [$this, ...] -> [@container, ...]
		$entity[0] = '@' . ContainerBuilder::THIS_CONTAINER;
	}
	return $entity; // Class, @service, [Class, member], [@service, member], [, globalFunc]
	}



	/**
	 * Converts @service or @\Class -> service name and checks its existence.
	 * @param  mixed
	 * @return string  of FALSE, if argument is not service name
	 */
	public function getServiceName($arg, $self = NULL)
	{
	if (!is_string($arg) || !preg_match('#^@[\w\\\\.].*\z#', $arg)) {
		return FALSE;
	}
	$service = substr($arg, 1);
	if ($service === self::THIS_SERVICE) {
		$service = $self;
	}
	if (Strings::contains($service, '\\')) {
		if ($this->classes === FALSE) { // may be disabled by prepareClassList
		return $service;
		}
		$res = $this->getByType($service);
		if (!$res) {
		throw new ServiceCreationException("Reference to missing service of type $service.");
		}
		return $res;
	}
	if (!isset($this->definitions[$service])) {
		throw new ServiceCreationException("Reference to missing service '$service'.");
	}
	return $service;
	}



	/** @deprecated */
	function generateClass()
	{
	throw new Nette\DeprecatedException(__METHOD__ . '() is deprecated; use generateClasses()[0] instead.');
	}

}
