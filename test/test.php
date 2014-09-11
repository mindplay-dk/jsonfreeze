<?php

require '../mindplay/jsonfreeze/JsonSerializer.php';

use mindplay\jsonfreeze\JsonSerializer;

// TEST FIXTURES:

class Order
{
    /**
     * @var int
     */
    public $orderNo;

    /**
     * @var OrderLine[]
     */
    public $lines = array();

    /**
     * @var bool
     */
    public $paid = false;

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
    /**
     * @param string $item
     * @param int $amount
     */
    public function __construct($item, $amount)
    {
        $this->item = $item;
        $this->amount = $amount;
    }

    public $item;
    public $amount;
    public $options = array();
}

// UNIT TEST:

header('Content-type: text/plain');

/**
 * @return Order
 */
function get_order_fixture() {
    $order = new Order;
    $order->orderNo = 123;
    $order->setPaid();
    $order->addLine(new OrderLine('milk "fuzz"', 3));

    $cookies = new OrderLine('cookies', 7);
    $cookies->options = array('flavor' => 'chocolate', 'weight' => '1/2 lb');

    $order->addLine($cookies);

    return $order;
}

// Create a simple object graph:

test(
    'Can serialize and unserialize object graph',
    function () {
        $input = get_order_fixture();

        $json = new JsonSerializer();

        $data = $json->serialize($input);

        /** @var Order $output */
        $output = $json->unserialize($data);

        eq($output->orderNo, $input->orderNo, 'object property value');
        eq(array_keys($output->lines), array_keys($input->lines), 'array sequence');
        eq($output->paid, $input->paid, 'scalar value');

        eq($output->lines[0]->amount, $input->lines[0]->amount, 'nested value');
        eq($output->lines[1]->options, $input->lines[1]->options, 'nested array');
    }
);

exit(status());

// https://gist.github.com/mindplay-dk/4260582

/**
 * @param string   $name     test description
 * @param callable $function test implementation
 */
function test($name, $function)
{
    echo "\n=== $name ===\n\n";

    try {
        call_user_func($function);
    } catch (Exception $e) {
        ok(false, "UNEXPECTED EXCEPTION", $e);
    }
}

/**
 * @param bool   $result result of assertion
 * @param string $why    description of assertion
 * @param mixed  $value  optional value (displays on failure)
 */
function ok($result, $why = null, $value = null)
{
    if ($result === true) {
        echo "- PASS: " . ($why === null ? 'OK' : $why) . ($value === null ? '' : ' (' . format($value) . ')') . "\n";
    } else {
        echo "# FAIL: " . ($why === null ? 'ERROR' : $why) . ($value === null ? '' : ' - ' . format($value, true)) . "\n";
        status(false);
    }
}

/**
 * @param mixed  $value    value
 * @param mixed  $expected expected value
 * @param string $why      description of assertion
 */
function eq($value, $expected, $why = null)
{
    $result = $value === $expected;

    $info = $result
        ? format($value)
        : "expected: " . format($expected, true) . ", got: " . format($value, true);

    ok($result, ($why === null ? $info : "$why ($info)"));
}

/**
 * @param string   $exception_type Exception type name
 * @param string   $why            description of assertion
 * @param callable $function       function expected to throw
 */
function expect($exception_type, $why, $function)
{
    try {
        call_user_func($function);
    } catch (Exception $e) {
        if ($e instanceof $exception_type) {
            ok(true, $why, $e);
            return;
        } else {
            $actual_type = get_class($e);
            ok(false, "$why (expected $exception_type but $actual_type was thrown)");
            return;
        }
    }

    ok(false, "$why (expected exception $exception_type was NOT thrown)");
}

/**
 * @param mixed $value
 * @param bool  $verbose
 *
 * @return string
 */
function format($value, $verbose = false)
{
    if ($value instanceof Exception) {
        return get_class($value)
        . ($verbose ? ": \"" . $value->getMessage() . "\"" : '');
    }

    if (! $verbose && is_array($value)) {
        return 'array[' . count($value) . ']';
    }

    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }

    if (is_object($value) && !$verbose) {
        return get_class($value);
    }

    return print_r($value, true);
}

/**
 * @param bool|null $status test status
 *
 * @return int number of failures
 */
function status($status = null)
{
    static $failures = 0;

    if ($status === false) {
        $failures += 1;
    }

    return $failures;
}
