mindplay/jsonfreeze
===================

Serialize and unserialize a PHP object-graph to/from a JSON string-representation.

**STATUS**: complete and working; needs proper unit-testing.


Overview
--------

This library can serialize and unserialize a complete PHP object-graph to/from a
JSON string-representation.

This library differs in a number of ways from e.g. `json_encode()`, `serialize()`,
`var_export()` and other existing serialization libraries, in a number of important
ways. Please see here for detailed technical background information:

    http://stackoverflow.com/questions/10489876

The most important thing to understand, is that this library is designed to store
self-contained object-graphs - it does not support shared or circular object-references.
This is by design, and in-tune with good DDD design practices. An object-graph with
shared or circular references cannot be stored directly as JSON, in a predictable format,
primarily because the JSON data-format is a tree, not a graph.


Usage
-----

...
