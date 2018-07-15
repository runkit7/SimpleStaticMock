<?php
namespace SimpleStaticMock\Tests;

use \PHPUnit\Framework\TestCase;
use \SimpleStaticMock\SimpleStaticMock;

/**
 * Simple examples for the usage of SimpleStaticMock, doubling as a PHPUnit test suite.
 * Tests for more complicated bug fixes, etc. can be added to another test file.
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
class SimpleStaticMockTest extends TestCase
{
    public function tearDown()
    {
        parent::tearDown();
        // All of the phpunit tests using SimpleStaticMock should call unmock_all after each test case, in tearDown()
        // Otherwise, this will cause unexpected results in other test cases/test suites.
        SimpleStaticMock::unmock_all();
    }

    /**
     * By providing a non-Closure value to SimpleStaticMock, you can make that static method return that value.
     */
    public function testMockStaticFunction()
    {
        $oldValue = \SimpleStaticMock\Tests\dummy::publicStaticFunction();
        $overriddenValue = 'overridden';
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', $overriddenValue);
        $this->assertSame($overriddenValue, \SimpleStaticMock\Tests\dummy::publicStaticFunction(), 'Did not override static method');
        $value = \SimpleStaticMock\Tests\dummy::publicStaticFunction();
        $this->assertSame($overriddenValue, $value, 'Did not override static method');
        $this->assertNotSame('overridden by closure', $oldValue);
        $staticMock->unmock();
        $this->assertSame($oldValue, \SimpleStaticMock\Tests\dummy::publicStaticFunction(), 'Did not restore static method');
    }

    /**
     * By providing a Closure value to SimpleStaticMock, you can make that static method return the value a Closure would return.
     */
    public function testMockStaticFunctionWithClosure()
    {
        $oldValue = \SimpleStaticMock\Tests\dummy::publicStaticFunction();
        $overriddenValue = 'overridden';
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', function () use ($overriddenValue) {
            return $overriddenValue . ' by closure';
        });
        $value = \SimpleStaticMock\Tests\dummy::publicStaticFunction();
        $this->assertSame('overridden by closure', $value, 'Did not override static method');
        $this->assertNotSame('overridden by closure', $oldValue);
        $staticMock->unmock();
        $this->assertSame($oldValue, \SimpleStaticMock\Tests\dummy::publicStaticFunction(), 'Did not restore static method');
    }

    /**
     * SimpleStaticMock tracks the total number of calls to a given function
     */
    public function testNumCalls()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'nate is awesome');

        $i = $calls = rand(1, 50);
        while ($i--) {
            \SimpleStaticMock\Tests\dummy::publicStaticFunction();
        }

        $this->assertSame($calls, $staticMock->numCalls());
        $staticMock->unmock();
    }

    /**
     * SimpleStaticMock has helper methods to check if a function was called certain numbers of times.
     */
    public function testCalledOnce()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'barrett is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction();

        $this->assertTrue($staticMock->calledOnce());
    }

    public function testCalledTwice()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'barrett is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction();
        \SimpleStaticMock\Tests\dummy::publicStaticFunction();

        $this->assertFalse($staticMock->calledOnce());
    }

    public function testCalledZeroTimes()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'barrett is awesome');
        $this->assertFalse($staticMock->calledOnce());
    }

    /**
     * SimpleStaticMock has helper methods calledOnceWithParams to check if a function was called certain numbers of times.
     */
    public function testCalledOnceWithParams()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'barrett is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param1', array('param2'));

        $this->assertTrue($staticMock->calledOnceWithParams('param1', array('param2')));
    }

    public function testCalledOnceWithBadParams()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'barrett is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param1', array('param2'));

        $this->assertFalse($staticMock->calledOnceWithParams('param1', 'param2'));
    }

    /**
     * SimpleStaticMock has a method argumentsCalledWith, which returns all of the (serialized then unserialized) arguments that it was called with.
     */
    public function testArgumentsCalledWith()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'testing your test framework is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param1', array('param2'));

        $this->assertSame(array(array('param1', array('param2'))), $staticMock->argumentsCalledWith());
    }

    public function testArgumentsCalledWithMultipleTimes()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'testing your test framework is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param1');
        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param2');
        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param3');

        $this->assertSame([['param1'], ['param2'], ['param3']], $staticMock->argumentsCalledWith());
    }

    public function testArgumentsCalledWithScalar()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'testing your test framework is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction(4);

        $this->assertSame([[4]], $staticMock->argumentsCalledWith());
    }

    public function testArgumentsCalledWithNotCalled()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'testing your test framework is awesome');

        // nothing called
        $this->assertSame([], $staticMock->argumentsCalledWith());
    }

    public function testFirstArgumentsCalledWith()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'testing your test framework is awesome');

        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param1');
        \SimpleStaticMock\Tests\dummy::publicStaticFunction('param2');

        $this->assertSame(['param1'], $staticMock->firstArgumentsCalledWith());
    }

    public function testFirstArgumentsCalledWithNotCalled()
    {
        $staticMock = new SimpleStaticMock('\SimpleStaticMock\Tests\dummy', 'publicStaticFunction', 'testing your test framework is awesome');

        // nothing called
        $this->assertNull($staticMock->firstArgumentsCalledWith());
    }
}
