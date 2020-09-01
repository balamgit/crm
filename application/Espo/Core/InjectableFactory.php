<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core;

use Espo\Core\{
    Exceptions\Error,
    Interfaces\Injectable,
};

use ReflectionClass;
use ReflectionParameter;
use Throwable;

/**
 * Creates an instance by a class name. Uses constructor param names to detect which
 * dependencies are needed. Only container services supported as dependencies.
 */
class InjectableFactory
{
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Creates an instance by a class name.
     */
    public function create(string $className) : object
    {
        return $this->createByClassName($className);
    }

    /**
     * Creates an instance by a class name. Allows passing specific constructor parameters.
     * Defined in an associative array. A key should match the parameter name.
     */
    public function createWith(string $className, array $with = []) : object
    {
        return $this->createByClassName($className, $with);
    }

    /**
     * @deprecated Use create or createWith methods instead. Left public for backward compatibility.
     * @todo Make protected.
     */
    public function createByClassName(string $className, ?array $with = null) : object
    {
        if (!class_exists($className)) {
            throw new Error("InjectableFactory: Class '{$className}' does not exist.");
        }

        $class = new ReflectionClass($className);

        $injectionList = $this->getConstructorInjectionList($class, $with);

        $obj = $class->newInstanceArgs($injectionList);

        // @todo Remove in 6.4.
        if ($class->implementsInterface(Injectable::class)) {
            $this->applyInjectable($class, $obj);

            return $obj;
        }

        $this->applyAwareInjections($class, $obj);

        return $obj;
    }

    /**
     * @deprecated
     * @todo Remove in 6.4.
     */
    protected function applyInjectable(ReflectionClass $class, object $obj)
    {
        $setList = [];

        $dependencyList = $obj->getDependencyList();

        foreach ($dependencyList as $name) {
            $injection = $this->container->get($name);

            if ($this->classHasDependencySetter($class, $name)) {
                $methodName = 'set' . ucfirst($name);
                $obj->$methodName($injection);
                $setList[] = $name;
            }

            $obj->inject($name, $injection);
        }

        $this->applyAwareInjections($class, $obj, $setList);

        return $obj;
    }

    protected function getConstructorInjectionList(ReflectionClass $class, ?array $with = null) : array
    {
        $injectionList = [];

        $constructor = $class->getConstructor();

        if (!$constructor) {
            return $injectionList;
        }

        $params = $constructor->getParameters();

        foreach ($params as $param) {
            $injectionList[] = $this->getConstructorParamInjection($class, $param, $with);
        }

        return $injectionList;
    }

    protected function getConstructorParamInjection(ReflectionClass $class, ReflectionParameter $param, ?array $with)
    {
        $name = $param->getName();

        if ($with && array_key_exists($name, $with)) {
            return $with[$name];
        }

        $dependencyClassName = null;

        if ($param->getType()) {
            try {
                $dependencyClassName = $param->getClass();
            }
            catch (Throwable $e) {
                $badClassName = $param->getType()->getName();

                // This trick allows to log syntax errors.
                class_exists($badClassName);

                throw new Error("InjectableFactory: " . $e->getMessage());
            }
        }

        if (!$dependencyClassName) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
        }

        if ($this->container->has($name)) {
            return $this->container->get($name);
        }

        if ($dependencyClassName && class_exists($dependencyClassName)) {
            return $this->create($dependencyClassName);
        }

        $className = $class->getName();

        throw new Error("InjectableFactory: Could not create {$className}, dependency '{$name}' not found.");
    }

    protected function applyAwareInjections(ReflectionClass $class, object $obj, array $ignoreList = [])
    {
        foreach ($class->getInterfaces() as $interface) {
            $interfaceName = $interface->getShortName();

            if (substr($interfaceName, -5) !== 'Aware' || strlen($interfaceName) <= 5) {
                continue;
            }

            $name = lcfirst(substr($interfaceName, 0, -5));

            if (in_array($name, $ignoreList)) {
                continue;
            }

            if (!$this->classHasDependencySetter($class, $name, true)) {
                continue;
            }

            $injection = $this->container->get($name);

            $methodName = 'set' . ucfirst($name);
            $obj->$methodName($injection);
        }
    }

    protected function classHasDependencySetter(ReflectionClass $class, string $name, bool $skipInstanceCheck = false) : bool
    {
        $methodName = 'set' . ucfirst($name);

        if (!$class->hasMethod($methodName) || !$class->getMethod($methodName)->isPublic()) {
            return false;
        }

        $params = $class->getMethod($methodName)->getParameters();
        if (!$params || !count($params)) {
            return false;
        }

        $injection = $this->container->get($name);

        $paramClass = $params[0]->getClass();

        if ($skipInstanceCheck || $paramClass && $paramClass->isInstance($injection)) {
            return true;
        }

        return false;
    }
}
