<?php

require dirname(__DIR__) . '/vendor/autoload.php';

require __DIR__ . '/fixtures.php';

use mindplay\jsonfreeze\JsonSerializer;

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

/**
 * @param DateTime|DateTimeImmutable $value
 * @param DateTime|DateTimeImmutable $expected
 */
function eq_dates($value, $expected) {
    eq($value->getTimestamp(), $expected->getTimestamp());
    eq($value->getTimezone()->getName(), $expected->getTimezone()->getName());
}

/**
 * @param DateTime|DateTimeImmutable $date
 */
function check_date($date)
{
    $serializer = new JsonSerializer(false);

    $serialized = $serializer->serialize($date);

    eq_dates($date, $serializer->unserialize($serialized));
}

test(
    'Can serialize/unserialize DateTime types',
    function () {
        $serializer = new JsonSerializer(false);

        $date = new DateTime("1975-07-07 00:00:00", timezone_open("UTC"));

        eq($serializer->serialize($date), '{"#type":"DateTime","datetime":"1975-07-07T00:00:00Z","timezone":"UTC"}');

        check_date($date);

        $date = new DateTime("1975-07-07 00:00:00", timezone_open("America/New_York"));

        eq($serializer->serialize($date), '{"#type":"DateTime","datetime":"1975-07-07T04:00:00Z","timezone":"America\/New_York"}');

        check_date($date);

        if (class_exists("DateTimeImmutable")) {
            $date = new DateTimeImmutable("1975-07-07 00:00:00", timezone_open("UTC"));

            eq($serializer->serialize($date), '{"#type":"DateTimeImmutable","datetime":"1975-07-07T00:00:00Z","timezone":"UTC"}');

            check_date($date);

            $date = new DateTimeImmutable("1975-07-07 00:00:00", timezone_open("America/New_York"));

            eq($serializer->serialize($date), '{"#type":"DateTimeImmutable","datetime":"1975-07-07T04:00:00Z","timezone":"America\/New_York"}');

            check_date($date);
        }
    }
);

exit(run());
