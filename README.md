mindplay/jsonfreeze
===================

Serialize and unserialize a PHP object-graph to/from a JSON string-representation.

[![Build Status](https://travis-ci.org/mindplay-dk/jsonfreeze.svg)](https://travis-ci.org/mindplay-dk/jsonfreeze)


## Overview

This library can serialize and unserialize a complete PHP object-graph to/from a
JSON string-representation.

This library differs in a number of ways from e.g. `json_encode()`, `serialize()`,
`var_export()` and other existing serialization libraries, in a number of important
ways.

[Please see here for detailed technical background information](http://stackoverflow.com/questions/10489876).

The most important thing to understand, is that this library is designed to store
self-contained object-graphs - it does not support shared or circular object-references.
This is by design, and in-tune with good DDD design practices. An object-graph with
shared or circular references cannot be stored directly as JSON, in a predictable format,
primarily because the JSON data-format is a tree, not a graph.


## Usage

Nothing to it.

```php
use mindplay\jsonfreeze\JsonSerializer;

$serializer = new JsonSerializer();

// serialize to JSON:

$string = $serializer->serialize($my_object);

// rebuild your object from JSON:

$object = $serializer->unserialize($string);
```

### Custom Serialization

You can define your own un/serialization functions for a specified class:

```php
$serializer = new JsonSerializer();

$serializer->defineSerialization(
    MyType::class,
    function (MyType $object) {
        return ["foo" => $object->foo, "bar" => $object->bar];
    },
    function (array $data) {
        return new MyType($data["foo"], $data["bar"]);
    }
);
```

Note that this works only for concrete classes, and not for abstract classes or
interfaces - serialization functions apply to precisely one class, although you
can of course register the same functions to multiple classes.

#### Date and Time Serialization

The `DateTime` and `DateTimeImmutable` classes have pre-registered un/serialization
functions supporting a custom format, in which the date/time is stored in the common
ISO-8601 date/time format in the UTC timezone, along with the timezone ID - for example:

    {
        "#type": "DateTime",
        "datetime": "1975-07-07T00:00:00Z",
        "timezone": "America\/New_York"
    }
