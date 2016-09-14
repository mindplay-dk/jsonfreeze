<?php

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
     * @var int this is here to assert omission/inclusion/inheritance of private properties
     */
    private $data = 123;

    public $item;
    public $amount;
    public $options = array();

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}

class OrderLineEx extends OrderLine
{
    public $color;

    private $data;

    public function setDataEx($data)
    {
        $this->data = $data;
    }

    public function getDataEx()
    {
        return $this->data;
    }
}
