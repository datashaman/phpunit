<?php

namespace Tests;

use Faker\Factory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertFailedError;
use PHPUnit\Framework\TestResult;
use PHPUnit\TextUI\Command;
use ReflectionClass;

function dd() {
    var_dump(func_get_args());
    exit;
}

class DataDrivenTest extends TestCase
{
    /**
     * @param string $name
     * @param string $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        $data = [
            0,
            1,
        ];

        parent::__construct($name, $data, $dataName);
    }

    public function count(): int
    {
        return 3;
    }

    public function blah(TestResult $result = null): TestResult
    {
        $faker = Factory::create();

        if ($result === null) {
            $result = new TestResult();
        }

        $reflector = new ReflectionClass(self::class);
        
        foreach ($reflector->getMethods() as $method) {
            if (preg_match('/^test/', $method->name)) {
                $parameters = [];
                
                foreach ($method->getParameters() as $param) {
                    $type = (string) $param->getType();

                    switch ($type) {
                    case 'int':
                        $parameters[] = $faker->randomNumber();
                        break;
                    default:
                        throw new Exception('Unhandled parameter type: ' . $type);
                    }
                }
            }
        }

        return $result;

        foreach ($this->lines as $line) {
            $result->startTest($this);
            PHP_Timer::start();
            $stopTime = null;

            list($expected, $actual) = explode(';', $line);

            try {
                Assert::assertEquals(
                  trim($expected), trim($actual)
                );
            }

            catch (AssertionFailedError $e) {
                $result->addFailure($this, $e, $stopTime);
            }

            catch (Exception $e) {
                $result->addError($this, $e, $stopTime);
            }

            finally {
                $stopTime = PHP_Timer::stop();
            }

            $result->endTest($this, $stopTime);
        }

        return $result;
    }

    public function testEqual()
    {
        $this->assertEquals($a, $b);
    }
}
