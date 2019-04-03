<?php

namespace Datashaman\PHPUnit;

use Exception;
use Faker\Factory;
use NunoMaduro\Collision\Provider;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\TextUI\ResultPrinter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use SebastianBergmann\Timer\Timer;

class FactoryTestCase extends TestCase
{
    /**
     * @var Factory
     */
    protected $faker;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $count;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    public function count(): int
    {
        $class = new ReflectionClass($this);

        $count = 0;

        foreach ($class->getMethods() as $method) {
            if (preg_match('/^test/', $method->name)) {
                $count += $this->getIterations($method);
            }
        }

        return $count;
    }

    public function getName(bool $withDataset = true): ?string
    {
        return $this->name ?: '';
    }

    public function getMethodAnnotations(ReflectionMethod $method): array
    {
        $annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(
            $method->getDeclaringClass()->name,
            $method->name
        );

        $result = [];

        if (isset($annotations['method']['param'])) {
            foreach ($annotations['method']['param'] as $param) {
                $array = false;
                $parts = explode(' ', preg_replace('/ +/', ' ', $param));
                $type = array_shift($parts);
                $name = array_shift($parts);
                $description = implode(' ', $parts);
                if (preg_match('/(.*)\[([0-9]*)\]$/', $type, $match)) {
                    $type = $match[1];
                    if ($match[2]) {
                        $size = (int) $match[2];
                    }
                    $array = true;
                }
                if ($type === 'string' && preg_match('/\[(.*)\]$/', $description, $match)) {
                    $subType = $match[1];
                }
                if ($name[0] === '$') {
                    $name = substr($name, 1);
                }
                $result[$name] = compact('array', 'size', 'subType', 'type');
            }
        }

        return $result;
    }

    protected function getParamAnnotation(ReflectionParameter $param): ?array
    {
        $method = $param->getDeclaringFunction();
        $annotations = $this->getMethodAnnotations($method);
        if (isset($annotations[$param->name])) {
            return $annotations[$param->name];
        }

        return null;
    }

    protected function generateArray(ReflectionParameter $param = null): array
    {
        $type = null;

        $allowsNull = $this->faker->boolean;
        $size = $this->faker->randomNumber(2);

        if ($param) {
            $annotation = $this->getParamAnnotation($param);

            if ($annotation) {
                $type = $annotation['type'];

                if (isset($annotation['size'])) {
                    $size = $annotation['size'];
                }

                if ($type[0] === '?') {
                    $allowsNull = true;
                    $type = substr($type, 1);
                } else {
                    $allowsNull = false;
                }
            }
        }

        if (!$type) {
            $type = $this->faker->randomElement(
                [
                    'bool',
                    'float',
                    'int',
                    'string',
                ]
            );
        }

        $result = [];
           
        for($i = 0; $i < $size; $i++) {
            $result[] = $this->generateArgument($type, $param, $allowsNull);
        }

        return $result;
    }

    protected function generateBool(ReflectionParameter $param = null): bool
    {
        return $this->faker->boolean;
    }

    protected function generateFloat(ReflectionParameter $param = null): float
    {
        return $this->faker->randomFloat();
    }

    protected function generateInt(ReflectionParameter $param = null): int
    {
        return $this->faker->numberBetween(PHP_INT_MIN, PHP_INT_MAX);
    }

    protected function generateMixed(ReflectionParameter $param = null)
    {
        $type = $this->faker->randomElement(
            [
                'array',
                'bool',
                'float',
                'int',
                'string',
            ]
        );

        return $this->generateArgument($type, $param);
    }

    protected function generateString(ReflectionParameter $param = null, string $subType = null): string
    {
        if (!is_null($subType)) {
            return $this->faker->{$subType};
        }

        if ($param) {
            $annotation = $this->getParamAnnotation($param);
            if (isset($annotation['subType'])) {
                return $this->faker->{$annotation['subType']};
            }
        }

        return $this->faker->text();
    }

    protected function generateObject(string $type)
    {
        if (empty($type)) {
            throw new Exception('Type cannot be empty');
        }

        $class = new ReflectionClass($type);
        $method = $class->getMethod('__construct');
        $arguments = $this->generateArguments($method);
        $object = $class->newInstance(...$arguments);

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $allowsNull = $this->faker->boolean;
            $type = 'mixed';

            $docComment = (string) $property->getDocComment();

            if ($docComment) {
                $annotations = \PHPUnit\Util\Test::parseAnnotations((string) $property->getDocComment());

                $subType = null;

                if (isset($annotations['var'])) {
                    $parts = explode(' ', preg_replace('/ +/', ' ', $annotations['var'][0]));
                    $type = array_shift($parts);
                    $description = implode(' ', $parts);
                    if ($type[0] === '?') {
                        $allowsNull = true;
                        $type = substr($type, 1);
                    } else {
                        $allowsNull = false;
                    }
                    if ($type === 'string' && preg_match('/\[(.*)\]$/', $description, $match)) {
                        $subType = $match[1];
                    }
                }
            }

            $object->{$property->name} = $this->generateArgument(
                $type,
                null,
                $allowsNull,
                $subType
            );
        }

        return $object;
    }

    protected function generateArgument(
        string $type,
        ReflectionParameter $param = null,
        bool $allowsNull = false,
        string $subType = null
    ) {
        if (empty($type)) {
            throw new Exception('Type cannot be empty');
        }

        if ($allowsNull) {
            if ($this->faker->boolean) {
                return null;
            }
        };

        $generateMethod = 'generate' . ucfirst($type);

        if (method_exists($this, $generateMethod)) {
            return $this->{$generateMethod}($param, $subType);
        }

        return $this->generateObject($type);
    }

    protected function generateArguments(ReflectionMethod $method)
    {
        $arguments = [];

        foreach ($method->getParameters() as $param) {
            if ($param->hasType()) {
                $type = $param->getType();
                $arguments[] = $this->generateArgument(
                    (string) $type,
                    $param,
                    $type->allowsNull()
                );
            } else {
                $arguments[] = $this->generateArgument(
                    'mixed',
                    $param,
                    $this->faker->boolean
                );
            }
        }

        return $arguments;
    }

    protected function getIterations(ReflectionMethod $method)
    {
        $annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(
            get_class($this),
            $method->name
        );

        $iterations = 100;
        if (isset($annotations['method']['iterations'])) {
            $iterations = (int) $annotations['method']['iterations'][0];
        }

        return $iterations;
    }

    public function run(TestResult $result = null): TestResult
    {
        if ($result === null) {
            $result = new TestResult();
        }

        $result->stop(false);

        $class = new ReflectionClass($this);

        foreach ($class->getMethods() as $method) {
            if (preg_match('/^test/', $method->name)) {
                $annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(
                    get_class($this),
                    $method->name
                );

                $iterations = 100;
                if (isset($annotations['method']['iterations'])) {
                    $iterations = (int) $annotations['method']['iterations'][0];
                }

                $this->name = $method->name;

                $result->startTest($this);
                Timer::start();

                $stopTime = null;

                for ($i = 0; $i < $iterations; $i++) {
                    try {
                        $testResult = $this->{$method->name}(...$this->generateArguments($method));
                    }

                    catch (AssertionFailedError $e) {
                        $stopTime = Timer::stop();
                        $result->addFailure($this, $e, $stopTime);
                    }

                    catch (Exception $e) {
                        $stopTime = Timer::stop();
                        $result->addError($this, $e, $stopTime);
                    }
                }

                $stopTime = Timer::stop();
                $result->endTest($this, $stopTime);
            }
        }

        return $result;
    }
}
