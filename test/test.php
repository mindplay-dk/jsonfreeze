<?php

require dirname(__DIR__) . '/mindplay/jsonfreeze/JsonSerializer.php';

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
     * @param int    $amount
     */
    public function __construct($item, $amount)
    {
        $this->item = $item;
        $this->amount = $amount;
        $this->data = 456;
    }

    /**
     * @var array this is here to assert omission of static properties
     */
    public static $cache = array();

    /**
     * @var int this is here to assert omission or inclusion of private properties
     */
    private $data = 123;

    public $item;
    public $amount;
    public $options = array();
}

// UNIT TEST:

header('Content-type: text/plain');

/**
 * @return Order
 */
function get_order_fixture()
{
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

        $serial = json_decode($data, true);

        ok(! isset($serial['lines'][0]['cache']), 'static properties should always be omitted');

        eq($serial['lines'][0]['data'], 456, 'private properties should be included by default');

        $prop = new ReflectionProperty($output->lines[0], 'data');
        $prop->setAccessible(true);

        $json->skipPrivateProperties();

        $data = $json->serialize($input);

        $output = $json->unserialize($data);

        $serial = json_decode($data, true);

        ok(! isset($serial['lines'][0]['data']), 'private properties should be omitted after skipPrivateProperties()');

        eq($prop->getValue($output->lines[0]), 123, 'private properties should initialize to their default value');
    }
);

test(
    'Can serialize/unserialize standard objects',
    function () {
        $serializer = new JsonSerializer();

        $input = (object) array('foo' => 'bar');

        $json = $serializer->serialize($input);

        $data = json_decode($json, true);

        eq($data[JsonSerializer::TYPE], JsonSerializer::STD_CLASS, 'stdClass object gets tagged');

        ok(isset($data['foo']), 'undefined property is preserved');

        eq($data['foo'], 'bar', 'undefined property value is preserved');

        $output = $serializer->unserialize($json);

        ok(isset($output->foo), 'object property is restored');

        eq($output->foo, $input->foo, 'property value is restored');
    }
);

test(
    'Can unserialize legacy array/hash values',
    function () {
        $array = array('foo', 'bar', 'baz');

        $input = array(
            'array' => $array,
            'hash'  => array(
                JsonSerializer::TYPE => JsonSerializer::HASH,
                'foo'                => 1,
                'bar'                => 2,
                'baz'                => 3,
            ),
        );

        $serializer = new JsonSerializer();

        $output = $serializer->unserialize(json_encode($input));

        eq($output['array'], $array, 'correctly unserializes a straight array');

        ok(! isset($output['hash'][JsonSerializer::TYPE]), 'legacy hash tag detected and removed');

        eq(
            $output['hash'],
            array(
                'foo' => 1,
                'bar' => 2,
                'baz' => 3,
            ),
            'original hash correctly restored after hash tag removal'
        );
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
        echo "# FAIL: " . ($why === null ? 'ERROR' : $why) . ($value === null ? '' : ' - ' . format($value,
                    true)) . "\n";
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

    if (is_object($value) && ! $verbose) {
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
