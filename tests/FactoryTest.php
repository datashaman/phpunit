<?php

namespace Datashaman\PHPUnit\Tests;

use Datashaman\PHPUnit\FactoryTestCase;

class User {
    protected $name;

    /**
     * @var string [email]
     */
    public $email;

    /**
     * @var string [url]
     */
    public $url;

    /**
     * @param string $name [name]
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}

class FactoryTest extends FactoryTestCase
{
    public function testInt(int $a)
    {
        $this->assertIsInt($a);
    }

    public function testNullableInt(?int $a)
    {
        $this->assertTrue(is_null($a) || is_int($a));
    }

    public function testFloat(float $a)
    {
        $this->assertIsFloat($a);
    }

    public function testNullableFloat(?float $a)
    {
        $this->assertTrue(is_null($a) || is_float($a));
    }

    public function testString(string $a)
    {
        $this->assertIsString($a);
    }

    public function testNullableString(?string $a)
    {
        $this->assertTrue(is_null($a) || is_string($a));
    }

    public function testArray(array $a)
    {
        $this->assertIsArray($a);
    }

    public function testNullableArray(?array $a)
    {
        $this->assertTrue(is_null($a) || is_array($a));
    }

    /**
     * @param string[] $a
     */
    public function testTypeArray(array $a)
    {
        $this->assertIsArray($a);
        foreach ($a as $item) {
            $this->assertIsString($item);
        }
    }

    /**
     * @param string[5] $a
     */
    public function testTypeArrayLength(array $a)
    {
        $this->assertIsArray($a);
        $this->assertCount(5, $a);
        foreach ($a as $item) {
            $this->assertIsString($item);
        }
    }

    public function testObject(User $a)
    {
        $this->assertInstanceOf(User::class, $a);
        $this->assertNotFalse(filter_var($a->email, FILTER_VALIDATE_EMAIL));
        $this->assertNotFalse(filter_var($a->url, FILTER_VALIDATE_URL));
        $this->assertNotNull($a->getName());
    }
}
