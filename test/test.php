<?php

/**
 * This will eventually be a unit-test...
 */

class Order
{
  public $orderNo;
  protected $lines = array();
  protected $paid = false;
  
  public function addLine(OrderLine $line)
  {
    $this->lines[] = $line;
  }
  
  public function setPaid($paid = true)
  {
    $this->paid = true;
  }
}

class OrderLine
{
  public function __construct($item, $amount)
  {
    $this->item = $item;
    $this->amount = $amount;
  }
  
  public $item;
  public $amount;
  public $options;
}

header('Content-type: text/plain');

require '../mindplay/jsonfreeze/JsonSerializer.php';

use mindplay\jsonfreeze\JsonSerializer;

// Create a simple object graph:

$order = new Order;
$order->orderNo = 123;
$order->setPaid();
$order->addLine( new OrderLine('milk "fuzz"', 3) );

$cookies = new OrderLine('cookies', 7);
$cookies->options = array('flavor' => 'chocolate', 'weight' => '1/2 lb');

$order->addLine($cookies);

// Serialize and unserialize:

$json = new JsonSerializer;

$data = $json->serialize($order);

echo "\n\n".$data;

$unserialized = $json->unserialize($data);

echo "\n\nUnserialized:\n";

var_dump($unserialized);

// Validate serialize/unserialize accuracy:

$input = print_r($order, true);
$output = print_r($unserialized, true);

if ($output !== $input) {
  echo "\nERROR: input and output do not match.\n\n";
} else {
  echo "\nVERIFIED: input object graph is identical to unserialized object graph.\n\n";
}
