<?php // declare(strict_types=1);

namespace SimpleStaticMock;

/**
 * Allow static methods to be mocked to return controlled dummy values for testing.
 *
 * Compatible with php 7.0 and php 7.1.
 *
 *
 * Copyright 2017 Ifwe Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class SimpleStaticMock {
    /** @var string */
    private $_className;

    /** @var string */
    private $_methodName;

    /** @var string */
    private $_overrideName;

    /** @var \Closure */
    private $_closure;

    /** @var bool */
    private $_usesStubClosure = false;

    /** @var bool */
    private $_isMocked = false;

    /**
     * A registry of all mock objects.
	 * @var SimpleStaticMock[]
     */
    private static $_registry = [];

	/**
	 * @var int[][]
	 * This is a two-dimensional array [class::method][serialized args] - contains the counts for that class, method name, and the serialized arguments.
	 */
    private static $_counts = [];

    public function __construct(string $className, string $methodName, $value = null, bool $mock = true) {
        $this->_className = $className;
        $this->_methodName = $methodName;
        $this->_overrideName = $this->_methodName . 'override' . rand();
        $this->setReturnValue($value);

        if ($mock) $this->mock();
    }

	/**
	 * We automatically unmock this when it goes out of scope.
	 */
    public function __destruct() {
        $this->unmock();
    }

    public function setReturnValue($value) {
        if ($value instanceof \Closure) {
            $this->_closure = $value;
            $this->_usesStubClosure = false;
        } else {
            $this->_closure = function() use ($value) { return $value; };
            $this->_usesStubClosure = true;
        }
    }

    public function getOverrideName() {
        return $this->_overrideName;
    }

    private function _classAndMethod() : string {
        return sprintf(
            '%s::%s',
            strtolower($this->_className),
            strtolower($this->_methodName)
        );
    }

    /**
     * Replaces the implementation of the static method, in the class and its subclasses
     * (This works best if the subclass was loaded before the StaticMock was created)
     * @return void
     */
    public function mock() {
        $method = $this->_classAndMethod();
        if (isset(self::$_registry[$method])) {
            self::$_registry[$method]->unmock();
            unset(self::$_registry[$method]);
        }

        $function = new MockFunction($this->_closure);
        // Prepend the count **BEFORE** any of the method arguments can be modified by the function.
        // Modifying arguments changes func_get_args(). See http://php.net/manual/en/function.func-get-args.php#refsect1-function.func-get-args-notes
        $function->prepend(sprintf('\SimpleStaticMock\SimpleStaticMock::add_count(%s, func_get_args());', json_encode($method)));

        $reflectionMethod = new \ReflectionMethod($this->_className, $this->_methodName);
        if ($reflectionMethod->isPrivate()) {
            $flags = RUNKIT_ACC_PRIVATE;
        } elseif ($reflectionMethod->isProtected()) {
            $flags = RUNKIT_ACC_PROTECTED;
        } else {
            $flags = RUNKIT_ACC_PUBLIC;
        }
        if ($reflectionMethod->isStatic()) {
            $flags |= RUNKIT_ACC_STATIC;
        }

        $reflectionClosure = new \ReflectionFunction($this->_closure);
        if ($reflectionClosure->getNumberOfRequiredParameters() > $reflectionMethod->getNumberOfRequiredParameters()) {
            throw new \RuntimeException(sprintf('%s(%s) has a mock with more required parameters (%d > %d) than the method itself. This may lead to a confusing Error being thrown. Original declaration in %s:%d',
                $method,
                MockUtils::params_to_string($reflectionMethod->getParameters(), true),
                $reflectionClosure->getNumberOfRequiredParameters(),
                $reflectionMethod->getNumberOfRequiredParameters(),
                $reflectionMethod->getFileName(),
                $reflectionMethod->getStartLine()
            ));
        }

        // Build and validate the body before moving around methods
        $body = $function->getBody();

        // Used to have problems mocking methods with static variables in them.
        // Seems to work now (new version of runkit) so the code checking for that has been removed.

        if (!\runkit_method_copy($this->_className, $this->_overrideName, $this->_className, $this->_methodName)) {
            throw new \RuntimeException($method . ' runkit_method_copy mock');
		}
        if (!\runkit_method_remove($this->_className, $this->_methodName)) {
            throw new \RuntimeException($method . ' runkit_method_remove mock');
		}
        if (!\runkit_method_add($this->_className, $this->_methodName, $function->getParameters(), $body, $flags)) {
            throw new \RuntimeException($method . ' runkit_method_add mock');
		}

        // Register object for later destruction.
        $this->_isMocked = true;
        self::$_registry[$method] = $this;
    }

    /**
     * Restores the original implementation of the static method, in the method and its subclasses inheriting the method.
     *
     * @return void
     */
    public function unmock() {
        $method = $this->_classAndMethod();
        if ($this->_isMocked) {
            // echo "Unmock $method\n";
            if (!\runkit_method_remove($this->_className, $this->_methodName))
                trigger_error($method . ' runkit_method_remove1 unmock');
            if (!\runkit_method_copy($this->_className, $this->_methodName, $this->_className, $this->_overrideName))
                trigger_error($method . ' runkit_method_copy unmock');
            if (!\runkit_method_remove($this->_className, $this->_overrideName))
                trigger_error($method . ' runkit_method_remove2 unmock');
            $this->_isMocked = false;
            self::$_counts[$method] = [];
        }
    }

    /**
     * Unmock all registered objects.
     * @return void
     */
    public static function unmock_all() {
        foreach (self::$_registry as $mock) {
            $mock->unmock();
        }
        self::$_registry = [];
        // reset counts
        self::$_counts = [];
    }

	/**
     * Accepts additional arguments to verify it was called with the
     * correct parameters
	 *
     * if numCalls() was requested without args, get the total calls regardless of args
	 *
	 * @param mixed ...$args
	 */
    public function numCalls(...$args) : int {
        $method = $this->_classAndMethod();

        if (!isset(self::$_counts[$method]))
            return 0;

        // if numCalls() was requested without args, get the total calls regardless of args
        if (count($args) === 0) {
            return array_sum(self::$_counts[$method]);
        }

        $sargs = $this->_serialize($args);
        if (array_key_exists($sargs, self::$_counts[$method])) {
            return self::$_counts[$method][$sargs];
        } else {
            return 0;
        }
    }

    /**
     * Get the arguments that this mocked static function was called with
     * @return array[] an array of arrays
     *             * each array is the set of arguments the mocked function was called with
     *             * one item in the array for each time the mocked function was called
     */
    public function argumentsCalledWith() : array {
        $method = $this->_classAndMethod();

        $result = [];
        if (!isset(self::$_counts[$method])) {
            return $result;
        }

        foreach (self::$_counts[$method] as $sargs => $count) {
            if ($count > 0) { // should always be true, but doesn't hurt to check
                $result[] = self::_unserialize($sargs);
            }
        }
        return $result;
    }

    /**
     * @return array|null - the array of arguments passed the first time this mocked function was called
     */
    public function firstArgumentsCalledWith() {
        $arguments = $this->argumentsCalledWith();
        return $arguments[0] ?? null;
    }

    /**
     * @return array|null - the array of arguments passed the last time this mocked function was called
     */
    public function lastArgumentsCalledWith() {
        $arguments = $this->argumentsCalledWith();
        return count($arguments) > 0 ? end($arguments) : null;
    }

    public function calledOnce() : bool {
        return $this->numCalls() === 1;
    }

    public function calledOnceWithParams(...$args) : bool {
        return $this->numCalls(...$args) === 1;
    }

    public static function add_count($method, $args = null) {
        $sargs = self::_serialize($args);
        if (!array_key_exists($method, self::$_counts)) {
            self::$_counts[$method] = [];
        }
        if (!array_key_exists($sargs, self::$_counts[$method])) {
            self::$_counts[$method][$sargs] = 0;
        }
        self::$_counts[$method][$sargs]++;
    }

    /**
     * @param array|null $args
     */
    private static function _serialize($args) {
        if ($args == null) {  // check for null or empty array
            return null;
        }
        if (is_array($args) && count($args) == 1 && is_int($args[0])) {
            return $args[0];
        }
        try {
            return serialize($args);
        } catch (\Exception $e) {
            // closures may not be serialized, in this case, we will not track calls on a per-argument basis.
            return null;
        }
    }

    private static function _unserialize($sargs) {
        if (!is_string($sargs)) {
            return [$sargs];  // we store some values as a shorter representation to save memory.
        }
        try {
            return unserialize($sargs);
        } catch (\Exception $e) {
            // looks like this wasn't a serialized argument
            return [$sargs];
        }
    }
}
