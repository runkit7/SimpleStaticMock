<?php
namespace SimpleStaticMock\Tests;
/**
 * Dummy class for testing SimpleStaticMock
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
class dummy {
    const MY_CONST = 'DUMMY';
    public $publicParam;
    private $_userId;
    private $_param2;

    public $some;

    public $getcalled;

    function __construct($userId = 'none', $param2 = 'none2') {
        $this->_userId = 'dummy' . $userId;
        $this->_param2 = 'dummy' . $param2;

        $this->publicParam = 'dummy' . 'publicParam';
        $this->getcalled = 'dummy' . 'no';

        $this->some = 'defaultsome';
    }

    public function __get($key) {
        switch($key) {
            case 'testget1':
                $this->getcalled = 'dummy' . 'yes';
                return 'dummy' . 'yestestget1';
            case 'testget2':
                $this->some = $this->testget3;
                return 'testget2';
            case 'testget3':
                return 'testget3';
        }
        return 'unknownget';
    }

    public function __set($key, $value) {
        switch($key) {
            case 'testset1':
                $this->$key = $value;
        }
    }

    public function __call($method, $args) {
        switch($method) {
            case 'testcall1':
                return 'testcall1';
            case 'testcall2':
                return 'testcall2' . $args[0];
            case 'testcall3':
                return $this->testcall1();
            case 'testcall4':
                return $this->testcall2($args[0]);
            case 'testcall5':
                return $this->privateFunction();
            case 'testcall6':
                return $this->privateFunctionCall();
        }
        return 'unknowncall';
    }

    public static function _X_create_object($userId, $param2 = null) {
        return new dummy($userId, $param2);
    }

    public function publicGetPublicParam() {
        return $this->publicParam;
    }

    public function publicGetPrivateParam() {
        return $this->_userId;
    }

    public function publicGetPrivateParam2() {
        return $this->_param2;
    }

    public function publicFunction() {
        return 'dummy' . 'publicFunction';
    }

    public function publicFunctionCallPrivateFunction() {
        return $this->privateFunction();
    }

    private function privateFunction() {
        return 'dummy' . 'privateFunction';
    }

    public function publicFunctionCrossCall() {
        return 'dummy' . 'publicFunctionCrossCall';
    }

    public function privateFunctionCall() {
        return $this->testcall1();
    }

    public function publicFunctionPassArgByReference(&$array) {
        array_unshift($array, 'a');
    }

    public function publicFunctionCallPrivateFunctionPassArgByReference() {
        $array = array('a');
        $this->privateFunctionPassArgByReference($array);
        return $array;
    }

    private function privateFunctionPassArgByReference(&$array) {
        array_unshift($array, 'b');
    }

    public function getMyConst() {
        return self::MY_CONST;
    }

    public function getPublicStaticFunction() {
        return self::publicStaticFunction();
    }

    public static function publicStaticFunction() {
        return 'dummy' . 'publicStaticFunction';
    }

    public function typeHintArg(dummy $dummy) {
        return $dummy->publicFunction();
    }

    public function varargsFunction(...$args) {
        return $args;
    }

    public function returnTypeFunction($a, $b) : array {
        return [$a, $b];
    }

    public function varargsScalarFunction(int ...$args) : int {
        return array_sum($args);
    }
}
