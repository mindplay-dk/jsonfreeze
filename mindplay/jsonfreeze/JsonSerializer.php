<?php

namespace mindplay\jsonfreeze;

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
  const TYPE = '#type';
  const HASH = '#hash';
  
  /**
   * @var string One level of indentation - defaults to two spaces.
   */
  public $indentation = '  ';
  
  /**
   * @var string Newline character(s) - defaults to newline.
   */
  public $newline = "\n";
  
  /**
   * @var string Padding character(s) after ":" in JSON objects - defaults to one space.
   */
  public $padding = ' ';
  
  /**
   * @param bool $pretty Whether or not to enable "pretty" JSON formatting.
   */
  public function __construct($pretty = true)
  {
    if (!$pretty) {
      $this->indentation = '';
      $this->newline = '';
      $this->padding = '';
    }
  }
  
  /**
   * Serialize a given object-graph to a JSON representation.
   *
   * @param object $object The root of the object-graph to be serialized.
   */
  public function serialize($object)
  {
    if (!is_object($object)) {
      throw new Exception("argument is not an object");
    }
    
    return $this->_serializeObject($object, 0);
  }
  
  /**
   * Unserialize an object-graph from a JSON string representation.
   *
   * @return object The root of the unserialized object-graph.
   */
  public function unserialize($string)
  {
    $data = json_decode($string, true);
    
    return $this->_unserialize($data);
  }
  
  /**
   * Serializes an individual object/array/hash/value, returning a JSON string representation
   */
  protected function _serialize($value, $indent=0)
  {  
    if (is_object($value)) {
      return $this->_serializeObject($value, $indent);
    } else if (is_array($value)) {
      if (array_keys($value) === array_keys(array_values($value))) {
        return $this->_serializeArray($value, $indent);
      } else {
        return $this->_serializeHash($value, $indent);
      }
    } else if (is_scalar($value)) {
      return json_encode($value);
    } else {
      return 'null';
    }
  }
  
  /**
   * Serializes a complete object with aggregates, returning a JSON string representation.
   */
  protected function _serializeObject($object, $indent)
  {
    $type = get_class($object);
    
    $whitespace = $this->newline . str_repeat($this->indentation, $indent+1);
    
    $string = '{' . $whitespace . '"' . self::TYPE . '":' . $this->padding . json_encode($type);
    
    foreach ($this->_getClassProperties($type) as $name => $prop) {
      $string .= ',' . $whitespace . json_encode($name) . ':' . $this->padding . $this->_serialize($prop->getValue($object), $indent+1);
    }
    
    $string .= $this->newline . str_repeat($this->indentation, $indent) . '}';
    
    return $string;
  }
  
  /**
   * Serializes a "strict" array (base-0 integer keys) returning a JSON string representation.
   */
  protected function _serializeArray($array, $indent)
  {
    $string = '[';
    
    $last_key = count($array)-1;
    
    foreach ($array as $key => $item) {
      $string .= $this->_serialize($item, $indent) . ($key === $last_key ? '' : ',');
    }
    
    $string .= ']';
    
    return $string;
  }
  
  /**
   * Serializes a "wild" array (e.g. a "hash" array with mixed keys) returning a JSON string representation.
   */
  protected function _serializeHash($hash, $indent)
  {
    $whitespace = $this->newline . str_repeat($this->indentation, $indent+1);
    
    $string = '{'. $whitespace . '"' . self::TYPE . '":' . $this->padding . '"' . self::HASH . '"';
    
    foreach ($hash as $key => $item) {
      $string .= ',' . $whitespace . json_encode($key) . ':' . $this->padding . $this->_serialize($item, $indent+1);
    }
    
    $string .= $this->newline . str_repeat($this->indentation, $indent) . '}';
    
    return $string;
  }
  
  /**
   * Unserialize an individual object/array/hash/value from a hash of properties.
   */
  protected function _unserialize($data) 
  {
    if (!is_array($data))
      return $data; // scalar value is fully unserialized
    
    if (array_key_exists(self::TYPE, $data)) {
      if ($data[self::TYPE] === self::HASH) {
        return $this->_unserializeHash($data);
      } else {
        return $this->_unserializeObject($data);
      }
    } else {
      return $this->_unserializeArray($data);
    }
  }
  
  /**
   * Unserialize an individual object from a hash of properties.
   */
  protected function _unserializeObject($data)
  {
    $type = $data[self::TYPE];
    
    $object = unserialize('O:'.strlen($type).':"'.$type.'":0:{}');

    // TODO support ReflectionClass::newInstanceWithoutConstructor() in PHP 5.4
    
    foreach ($this->_getClassProperties($type) as $name => $prop) {
      if (array_key_exists($name, $data)) {
        $value = $this->_unserialize($data[$name]);
        $prop->setValue($object, $value);
      }
    }
    
    // TODO invoke the magic __wakeup() method
    
    return $object;
  }
  
  /**
   * Unserialize a "strict" array (base-0 integer keys) from a hash.
   */
  protected function _unserializeArray($data)
  {
    $array = array();
    
    for ($i=0; $i<count($data); $i++) {
      $array[] = $this->_unserialize($data[$i]);
    }
    
    return $array;
  }
  
  /**
   * Unserialize a "wild" array. (e.g. a "hash" array with mixed keys)
   */
  protected function _unserializeHash($data)
  {
    $hash = array();
    
    foreach ($data as $name => $value) {
      if ($name === self::TYPE)
        continue;
      
      $hash[$name] = $this->_unserialize($value);
    }
    
    return $hash;
  }
  
  /**
   * Internal cache for class-reflections
   */
  static $_reflections = array();
  
  /**
   * Obtain a (cached) array of property-reflections, with all properties made accessible.
   */
  protected function _getClassProperties($type)
  {
    if (!isset(self::$_reflections[$type])) {
      $class = new ReflectionClass($type);
      
      $props = array();
      
      // TODO add support for the __sleep() magic method
      
      foreach ($class->getProperties() as $prop) {
        $prop->setAccessible(true);
        $props[ $prop->getName() ] = $prop;
      }
      
      self::$_reflections[$type] = $props;
    }
    
    return self::$_reflections[$type];
  }
}
