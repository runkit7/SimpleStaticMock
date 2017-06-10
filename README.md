SimpleStaticMock
================

## Overview

A simple PHP framework for mocking static methods in unit tests (and recording those calls).
This requires the [runkit7/runkit7 fork of runkit](https://github.com/runkit7/runkit7).

[![Build Status](https://travis-ci.org/runkit7/SimpleStaticMock.svg?branch=master)](https://travis-ci.org/runkit7/SimpleStaticMock)

## Authors

Tyson Andre, various Ifwe employees.

## Requirements

- PHP version 7.0 or greater (also supports php 7.1 and 7.2-dev)
- [The runkit7/runkit7 fork of runkit](https://github.com/runkit7/runkit7) must be installed and enabled.
  Version 1.0.5a5 or greater is recommended.
- runkit must be enabled in your php.ini settings (`extension=runkit.so)

## License

The SimpleStaticMock framework is licensed under the <a href="http://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>

## Installation

TODO: publish this on packagist and add a release version.

Alternately, this project can be checked out to a directory and required manually.

```php
require_once $simpleStaticMockDir . '/src/SimpleStaticMock/MockUtils.php';
require_once $simpleStaticMockDir . '/src/SimpleStaticMock/MockFunction.php';
require_once $simpleStaticMockDir . '/src/SimpleStaticMock/SimpleStaticMock.php';
```

## How this works:

- If a value is passed in, we serialize and unserialize that value to add it to the source of a function created with `runkit\_method\_add`
- The function source is fetched from disk if a closure is passed in, using reflection to get the source of that closure using the file and start/end lines of the function.
  TODO: try out the runkit APIs for closures.

## Usage:

```php

use \SimpleStaticMock\SimpleStaticMock;

class TestCase extends PHPUnit\Framework\TestCase {

    public function myTest() {
        $this->_mock = new SimpleStaticMock('my_class', 'my_method', 42);
		$value = my_class::my_method('arg');  // real tests would call something else, which calls my_class::my_method()
        $this->assertSame(42, $value, 'SimpleStaticMock should mock this method');
        $this->assertSame([[42]], $this->_mock->argumentsCalledWith(), 'SimpleStaticMock should record calls to this method');
        $this->_mock->unmock();  // to unmock an individual mock
        // SimpleStaticMock::unmock_all();  // to unmock all mocks, e.g. in tearDown()
    }

    public function tearDown() {
        parent::tearDown();
        SimpleStaticMock::unmock_all();  // unmock all mocks created
    }
```

Alternately, a Closure can be used for user-defined logic to mock values.

```php
    public function myTest() {
		$tmp = 7;  // must be serializable with var_export
		$this->_mock = new SimpleStaticMock('my_class', 'my_method', function($arg) use($tmp) {
			return $tmp * 6;
		});
		$value = my_class::my_method('arg');  // real tests would call something else, which calls my_class::my_method()
		$this->assertSame(42, $value, 'SimpleStaticMock should mock this method');
	}
```

See [src/SimpleStaticMock/SimpleStaticMock.php](src/SimpleStaticMock/SimpleStaticMock.php) for all of the available methods.

## Examples:

See [tests/SimpleStaticMockTest.php](tests/SimpleStaticMockTest.php) for more examples

-----

README.md: Copyright 2017 Ifwe Inc.

README.md is licensed under a Creative Commons Attribution-ShareAlike 4.0 International License.

You should have received a copy of the license along with this work. If not, see <http://creativecommons.org/licenses/by-sa/4.0/>.
