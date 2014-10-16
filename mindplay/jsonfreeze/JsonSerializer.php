<?php

namespace mindplay\jsonfreeze;

use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * @author Rasmus Schultz <rasmus@mindplay.dk>
 * @license LGPL v3 <http://www.gnu.org/licenses/gpl-3.0.txt>
 *
 * This class can serialize a complete PHP object-graph to a JSON string, and
 * can unserialize the string back to the equivalent PHP object-graph.
 *
 * See here for detailed notes on the precise requirements met by this class:
 *
 *   http://stackoverflow.com/questions/10489876
 *
 */

/**
 * This class can serialize/unserialize a complete PHP object graph to a
 * JSON string representation, using human-readable, VCS-friendly formatting.
 */
class JsonSerializer
{
    /**
     * @var (ReflectionProperty[])[] Internal cache for class-reflections
     */
    static $_reflections = array();

    /**
     * @type string hash token used to identify PHP classes
     */
    const TYPE = '#type';

    /**
     * @type string hash token previously used to identify PHP hashes (arrays with keys)
     */
    const HASH = '#hash';

    /**
     * @type string standard class name
     */
    const STD_CLASS = 'stdClass';

    /**
     * @var string one level of indentation
     */
    public $indentation = '';

    /**
     * @var string newline character(s)
     */
    public $newline = '';

    /**
     * @var string padding character(s) after ":" in JSON objects
     */
    public $padding = '';

    /**
     * @var bool true, if private properties should be skipped
     *
     * @see skipPrivateProperties()
     * @see _getClassProperties()
     */
    private $_skip_private = false;

    /**
     * @param bool $pretty true to enable "pretty" JSON formatting.
     */
    public function __construct($pretty = true)
    {
        if ($pretty) {
            $this->indentation = '  ';
            $this->newline = "\n";
            $this->padding = ' ';
        }
    }

    /**
     * Serialize a given PHP value/array/object-graph to a JSON representation.
     *
     * @param mixed $value The value, array, or object-graph to be serialized.
     *
     * @return string JSON serialized representation
     */
    public function serialize($value)
    {
        return $this->_serialize($value, 0);
    }

    /**
     * Unserialize a value/array/object-graph from a JSON string representation.
     *
     * @param string $string JSON serialized value/array/object representation
     *
     * @return mixed The unserialized value, array or object-graph.
     */
    public function unserialize($string)
    {
        $data = json_decode($string, true);

        return $this->_unserialize($data);
    }

    /**
     * Enable (or disable) serialization of private properties.
     *
     * By default, private properties are serialized - be aware that skipping private
     * properties may require some careful handling of those properties in your models;
     * in particular, a private property initialized during __construct() will not get
     * initialized when you unserialize() the object.
     */
    public function skipPrivateProperties($skip = true)
    {
        if ($this->_skip_private !== $skip) {
            $this->_skip_private = $skip;

            self::$_reflections = array();
        }
    }

    /**
     * Serializes an individual object/array/hash/value, returning a JSON string representation
     *
     * @param mixed $value the value to serialize
     * @param int $indent indentation level
     *
     * @return string JSON serialized value
     */
    protected function _serialize($value, $indent = 0)
    {
        if (is_object($value)) {
            if (get_class($value) === self::STD_CLASS) {
                return $this->_serializeStdClass($value, $indent);
            }

            return $this->_serializeObject($value, $indent);
        } else {
            if (is_array($value)) {
                if (array_keys($value) === array_keys(array_values($value))) {
                    return $this->_serializeArray($value, $indent);
                } else {
                    return $this->_serializeHash($value, $indent);
                }
            } else {
                if (is_scalar($value)) {
                    return json_encode($value);
                } else {
                    return 'null';
                }
            }
        }
    }

    /**
     * Serializes a complete object with aggregates, returning a JSON string representation.
     *
     * @param object $object object
     * @param int $indent indentation level
     *
     * @return string JSON object representation
     */
    protected function _serializeObject($object, $indent)
    {
        $type = get_class($object);

        $whitespace = $this->newline . str_repeat($this->indentation, $indent + 1);

        $string = '{' . $whitespace . '"' . self::TYPE . '":' . $this->padding . json_encode($type);

        foreach ($this->_getClassProperties($type) as $name => $prop) {
            $string .= ','
                . $whitespace
                . json_encode($name)
                . ':'
                . $this->padding
                . $this->_serialize($prop->getValue($object), $indent + 1);
        }

        $string .= $this->newline . str_repeat($this->indentation, $indent) . '}';

        return $string;
    }

    /**
     * Serializes a "strict" array (base-0 integer keys) returning a JSON string representation.
     *
     * @param array $array array
     * @param int $indent indentation level
     *
     * @return string JSON array representation
     */
    protected function _serializeArray($array, $indent)
    {
        $string = '[';

        $last_key = count($array) - 1;

        foreach ($array as $key => $item) {
            $string .= $this->_serialize($item, $indent) . ($key === $last_key ? '' : ',');
        }

        $string .= ']';

        return $string;
    }

    /**
     * Serializes a "wild" array (e.g. a "hash" array with mixed keys) returning a JSON string representation.
     *
     * @param array $hash hash array
     * @param int $indent indentation level
     *
     * @return string JSON hash representation
     */
    protected function _serializeHash($hash, $indent)
    {
        $whitespace = $this->newline . str_repeat($this->indentation, $indent + 1);

        $string = '{';

        $comma = '';

        foreach ($hash as $key => $item) {
            $string .= $comma
                . $whitespace
                . json_encode($key)
                . ':'
                . $this->padding
                . $this->_serialize($item, $indent + 1);

            $comma = ',';
        }

        $string .= $this->newline . str_repeat($this->indentation, $indent) . '}';

        return $string;
    }

    /**
     * Serializes a stdClass object returning a JSON string representation.
     *
     * @param stdClass $value stdClass object
     * @param int $indent indentation level
     *
     * @return string JSON object representation
     */
    protected function _serializeStdClass($value, $indent)
    {
        $array = (array) $value;

        $array[self::TYPE] = self::STD_CLASS;

        return $this->_serializeHash($array, $indent);
    }

    /**
     * Unserialize an individual object/array/hash/value from a hash of properties.
     *
     * @param array $data hashed value representation
     *
     * @return mixed unserialized value
     */
    protected function _unserialize($data)
    {
        if (!is_array($data)) {
            return $data; // scalar value is fully unserialized
        }

        if (array_key_exists(self::TYPE, $data)) {
            if ($data[self::TYPE] === self::HASH) {
                // remove legacy hash tag from JSON serialized with version 1.x
                unset($data[self::TYPE]);
                return $this->_unserializeArray($data);
            }

            return $this->_unserializeObject($data);
        }

        return $this->_unserializeArray($data);
    }

    /**
     * Unserialize an individual object from a hash of properties.
     *
     * @param array $data hash of object properties
     *
     * @return object unserialized object
     */
    protected function _unserializeObject($data)
    {
        $type = $data[self::TYPE];

        if ($type === self::STD_CLASS) {
            unset($data[self::TYPE]);
            return (object) $this->_unserializeArray($data);
        }

        $object = unserialize('O:' . strlen($type) . ':"' . $type . '":0:{}');

        // TODO support ReflectionClass::newInstanceWithoutConstructor() in PHP 5.4

        foreach ($this->_getClassProperties($type) as $name => $prop) {
            if (array_key_exists($name, $data)) {
                $value = $this->_unserialize($data[$name]);
                $prop->setValue($object, $value);
            }
        }

        return $object;
    }

    /**
     * Unserialize a hash/array.
     *
     * @param array $data hash/array
     *
     * @return array unserialized hash/array
     */
    protected function _unserializeArray($data)
    {
        $array = array();

        foreach ($data as $key => $value) {
            $array[$key] = $this->_unserialize($value);
        }

        return $array;
    }

    /**
     * Obtain a (cached) array of property-reflections, with all properties made accessible.
     *
     * @param string $type fully-qualified class name
     *
     * @return ReflectionProperty[] map where property-name => accessible ReflectionProperty instance
     */
    protected function _getClassProperties($type)
    {
        if (!isset(self::$_reflections[$type])) {
            $class = new ReflectionClass($type);

            $props = array();

            foreach ($class->getProperties() as $prop) {
                if ($prop->isStatic()) {
                    continue; // omit static property
                }

                if ($this->_skip_private && $prop->isPrivate()) {
                    continue; // skip private property
                }

                $prop->setAccessible(true);

                $props[$prop->getName()] = $prop;
            }

            self::$_reflections[$type] = $props;
        }

        return self::$_reflections[$type];
    }
}
