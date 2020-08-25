<?php declare(strict_types=1);
namespace SimpleStaticMock;

/**
 * Gets the source for a reflected function or method from the disk.
 * Allows the function name to be modified and for lines of PHP code to be prepended.
 *
 * TODO: use closures in \SimpleStaticMock\SimpleStaticMock.
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
class MockFunction
{
    /**
     * @var int set of flags
     * e.g. public static (see ReflectionMethod for flag constants)
     */
    public $modifiers = 0;

    /**
     * @var string
     * e.g. 'function foo($p1, array $p2 = array()) '
     */
    private $_head;

    /**
     * @var string
     * e.g. function() use ($foo)
     * the use clause will be removed from the function and
     * $foo will be initialized at the start of the function
     */
    private $_use = '';

    /**
     * @var string entire function body between '{' and '}' exclusive
     */
    protected $_body;

    /**
     * @var string custom code. This will be executed **before** _use statements, since they may override function arguments.
     */
    protected $_prepended = '';

    /** @var bool whether or not this should create mockUse */
    private $_setsMockUse;

    /**
     * @param \Closure|\ReflectionFunctionAbstract $function (ReflectionFunction or ReflectionMethod)
     *        NOTE: Runkit7 was extremely slow if we keep references to objects around for long.
     *        Until php 7.0.15, there was a bug in the garbage collector that caused those references to stay around.
     *        As a result, this class and its subclasses were rewritten to avoid keeping a reference to $function in the properties.
     */
    public function __construct($function, string $rename = null)
    {
        if ($function instanceof \Closure) {
            $function = new \ReflectionFunction($function);
        }
        if (!($function instanceof \ReflectionFunction) && !($function instanceof \ReflectionMethod)) {
            throw new \InvalidArgumentException("Expected a ReflectionMethod or ReflectionFunction, got: " . print_r($function, true));
        }

        if ($function instanceof \ReflectionMethod) {
            $this->modifiers = $function->getModifiers();
        }

        $source = static::get_function_source($function);
        $bodyStart = strpos($source, '{');
        $this->_head = substr($source, 0, $bodyStart);  // Everything, up to the return type. Use clauses are stripped out by _parseUseClause.
        $this->_body = substr($source, $bodyStart + 1, -1);  // removing curly braces

        if ($rename) {
            $this->_setName($rename);
        }

        $this->_parseUseClause($this->_getStaticVariables($function));
    }

    /**
     * @return void
     */
    private function _setName(string $name)
    {
        $this->_head = preg_replace('/^function(\s+\w+|\s+(&)\s*\w+|\s*)/', 'function $2' . $name, $this->_head);
    }

    /**
     * Gets the name assigned to this function.
     *
     * @return string
     */
    public function getName() : string
    {
        if (preg_match('/^function\s+(&\s*)?(\w+)/', $this->_head, $matches)) {
            return $matches[2];
        } else {
            return '';
        }
    }

    /**
     * Gets this function's parameter list (all text between '(' and ')', exclusive)
     *
     * @return string
     */
    public function getParameters() : string
    {
        $start = strpos($this->_head, '(') + 1;
        $end   = strrpos($this->_head, ')');
        return substr($this->_head, $start, $end - $start);
    }

    /**
     * @return void
     * adds code to set up _mockUse to the body.
     */
    public function ensureSetsMockUse()
    {
        $this->_setsMockUse = true;
    }

    /**
     * Returns the function body (all text between '{' and '}', exclusive).
     */
    public function getBody() : string
    {
        $prefix = '';
        if ($this->_setsMockUse) {
            $prefix = '    $this->_setInstanceMockUse();' . "\n";
        }
        return $prefix . $this->_prepended . $this->_use . $this->_body;
    }

    public function isPublic() : bool
    {
        return (bool)($this->modifiers & \ReflectionMethod::IS_PUBLIC);
    }

    public function isProtected() : bool
    {
        return (bool)($this->modifiers & \ReflectionMethod::IS_PROTECTED);
    }

    public function isPrivate() : bool
    {
        return (bool)($this->modifiers & \ReflectionMethod::IS_PRIVATE);
    }

    public function isStatic() : bool
    {
        return (bool)($this->modifiers & \ReflectionMethod::IS_STATIC);
    }

    public function isFinal() : bool
    {
        return (bool)($this->modifiers & \ReflectionMethod::IS_FINAL);
    }

    /**
     * Prepend the given PHP code to the PHP code this class will generate.
     * @return void
     */
    public function prepend(string $prefix)
    {
        $this->_prepended = "    " . $prefix . "\n" . $this->_prepended;
    }

    /**
     * String substitution applied to the function body.
     *
     * @param string|array $search
     * @param string|array $replace
     */
    public function str_replace($search, $replace)
    {
        $this->_body = str_replace($search, $replace, $this->_body);
    }

    /**
     * Returns the entire function source.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->_head . "{\n" . $this->getBody() . "}\n";
    }

    /**
     * @return string[] the variables used in this function (E.g. ["&$a"])
     * This is overridden in subclasses.
     */
    protected function _extractUseVars() : array
    {
        // find the 'use' construct
        if (!preg_match('/\buse\s*\(([^)]*)\)\s*(:\s*[\w\\\\]+\s*)?$/', $this->_head, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        // trim the use clause from the head, keeping the return type declaration if it exists
        // Subclasses such as test_mock_method may add return type declarations if they don't already exist.
        $this->_head = substr($this->_head, 0, $matches[0][1]);
        if (isset($matches[2])) {
            $this->_head .= ' ' . $matches[2][0];
        }

        // the use parameter list
        $useClause = $matches[1][0];
        $vars = explode(',', $useClause);
        return $vars;
    }

    /**
     * @param \ReflectionMethod|\ReflectionFunction $function
     * @return array [string => mixed] mapping names of static variables to their values.
     * This is overridden in subclasses.
     */
    protected function _getStaticVariables(\ReflectionFunctionAbstract $function) : array
    {
        return $function->getStaticVariables();
    }

    /**
     * Get the variables used by the closure that this MockFunction represents.
     * Returns a string that represents declaring these variables, with their imported values, at
     * the beginning of this function.  For example, if this was the creation of the original closure:
     *
     *  $fakeData = array(
     *      1 => array('value' => 0),
     *      2 => array('value' => 2000, 'items' => array( array('id' => 1, 'count' => 1) )),
     *      3 => array('value' => 4000),
     *  );
     *  $this->_myMock = new \SimpleStaticMock\SimpleStaticMock('my_class', 'get_data', function($id) use ($fakeData) { return $fakeData[$id]; } );
     *
     * the return value of this function would be:
     *
     *  $fakeData = array(
     *      1 => array('value' => 0),
     *      2 => array('value' => 4000),
     *  );
     *
     * and the new runkit7-redefined method my_class::get_data (the X's represent some sequence of digits)
     * would be defined as the the following function:
     *
     *  public function getData($id) {
     *      $fakeData = array(
     *          1 => array('value' => 0),
     *          2 => array('value' => 4000),
     *      );
     *      return $fakeData[$data];
     *  }
     *  @param array $staticVars - The static variables of this function.
     *  @return void
     */
    private function _parseUseClause(array $staticVars)
    {
        $vars = $this->_extractUseVars();
        if (count($vars) == 0) {
            return;
        }

        // create the strings for each used variable
        $this->_use = '';
        foreach ($vars as $var) {
            $var = trim($var);
            $this->_use .= $this->_createUseStatement($var, $staticVars[trim($var, ' &$')]);
        }
    }

    /**
     * @unused - future releases of SimpleStaticMock may check the return types match up with the original by default.
     */
    public function makeReturnTypeCompatibleWithOriginal(\ReflectionFunctionAbstract $function)
    {
        if (preg_match('/:\s*\??\s*([\w\\\\])+\s*$/', $this->_head)) {
            return;  // Already has a return type (E.g. taken from Closure)
        }
        $originalReturnType = self::get_return_type($function);
        if ($originalReturnType) {
            $this->_head .= sprintf(' : %s ', (string)$originalReturnType);
        }
    }

    /**
     * @return string|null (e.g. 'SomeClass', 'string', (in php7.1) '?int', etc.)
     * @unused - future releases of SimpleStaticMock may check the return types match up with the original by default.
     */
    public static function get_return_type(\ReflectionFunctionAbstract $method)
    {
        $type = $method->getReturnType();
        if (!is_object($type)) {
            return null;
        }
        return (string)$type;
    }


    /**
     * Creates the PHP expression for assigning $use in the local scope.
     *
     * @param string $var the variable name of the closure, from $this->_function
     * @param mixed $value the value of $var, from _function->getStaticVariables().
     *                     If $value is an object, it must implement __set_state().
     * @return string a PHP expression that will evaluate to a copy of $var
     *                (In subclasses, may be $var or a reference to $var)
     */
    protected function _createUseStatement(string $var, $value) : string
    {
        if ($var[0] === '&') {
            $var = trim(substr($var, 1));
            trigger_error("Importing variable $var into overriding function by reference is not supported; $var will be imported by value instead.", E_USER_WARNING);
            // TODO: import this and anything with an object with a global value instead?
        }
        // Build the use statement
        $name = trim($var, ' $');
        $value_source = var_export($value, true);
        return "    $var = $value_source;\n";
    }

    /**
     * Goes back to the original source file to grab the source code.
     * Returns everything from parameters to final closing brace, but
     * the method name will be rewritten to reflect the passed-in name.
     *
     * This is overridden in some subclasses, call this with static::get_function_source
     *
     * @param \ReflectionFunctionAbstract $function
     * @return string the source code.
     */
    public static function get_function_source(\ReflectionFunctionAbstract $function)
    {
        static $fileCache = [];

        // NOTE: caching file calls reduces i/o greatly when multiple functions are being parsed from the same file
        // TODO(optional): finite size LRU cache.
        $filename = $function->getFileName();
        if (array_key_exists($filename, $fileCache)) {
            $file = $fileCache[$filename];
        } else {
            $file = file($filename);
            $fileCache[$filename] = $file;
        }
        // Note: the start and end lines may be incorrect with certain opcache settings. This may have been fixed since the last time I checked.
        $start    = $function->getStartLine() - 1;
        $end      = $function->getEndLine() - 1;

        // trim non-function source
        $source  = implode(array_slice($file, $start, $end - $start + 1));
        $matches = [];
        if (preg_match('/function(\s+\w+|\s+&\s*\w+)?\s*\(([^()]|\([^()]*\))*\)[^{]*{/', $source, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = $matches[0][1];
            $endPos = strrpos($source, '}');
            $source = substr($source, $startPos, $endPos - $startPos + 1);

            return $source;
        } else {
            throw new \Exception(sprintf('Could not find function start of %s between lines %d and %d of %s. Source: %s', $function->getName(), $function->getStartLine(), $function->getEndLine(), $filename, $source));
        }
    }
}
