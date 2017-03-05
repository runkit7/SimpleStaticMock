<?php declare(strict_types=1);
namespace SimpleStaticMock;

/**
 * Generic utilities for mocking methods and functions.
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
class MockUtils {
    /**
     * Converts an array of ReflectionParameter objects to a valid PHP string for a method/function declaration.
     * Note: this aims to be generic enough be used outside of SimpleStaticMock.
     *
     * @param \ReflectionParameter[] $params
     * @param bool $useName - Whether to use the actual names or just $arg1, $arg2, etc.
     * @return string - comma separated representation of parameter list.
     */
    public static function params_to_string(array $params, bool $useName = false) : string {
        $defparams = [];
        $i = 0;
        foreach ($params as $parameter) {
            $i++;
            // Get the type hint of the parameter
            $type = self::reflection_type_to_declaration($parameter->getType());
            if ($type !== '') {
                $type .= ' ';
            }

            // Check if the parameter is variadic - it is possible there is a type hint before this token
            $hasDefault = $parameter->isDefaultValueAvailable() || ($parameter->isOptional() && !$parameter->isVariadic());

            // Turn the method arguments into a php fragment the defines a function with them, including possibly the by-reference "&" and any default
            $defparams[] =
                $type .
                ($parameter->isVariadic() ? '...' : '') .
                ($parameter->isPassedByReference() ? '&' : '') .
                '$' . ($useName ? $parameter->getName() : ('arg' . $i)) .
                ($hasDefault ? '=' . '<default>' : '')
            ;
        }
        return implode(', ', $defparams);
    }


	/**
     * @param null|\ReflectionType $type
     * @return string - The type, converted to a string that can be used in php code for params or return type declarations.
     */
    public static function reflection_type_to_declaration($type) : string {
        if (!$type) {
            return '';
        }
        return
            ($type->allowsNull() ? '?' : '') .
            ($type->isBuiltin() ? '' : '\\') .  // include absolute namespace for class names, so that classes using this will be able to mock namespaced classes
            (string)$type;
    }
}
