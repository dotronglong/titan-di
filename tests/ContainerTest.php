<?php namespace Titan\Tests\DI;

use Titan\Common\Bag;
use Titan\DI\Container;
use Titan\DI\Exception\BindingResolutionException;
use Titan\DI\Exception\ClassNotFoundException;
use Titan\DI\Exception\InvalidArgumentException;
use Titan\Tests\Common\TestCase;
use Titan\Tests\DI\Sample\SampleFour;
use Titan\Tests\DI\Sample\SampleOne;
use Titan\Tests\DI\Sample\SampleThree;
use Titan\Tests\DI\Sample\SampleTwo;
use Titan\Tests\DI\Sample\UninstantiableInterface;

class ContainerTest extends TestCase
{
    public function setUp()
    {
        Container::getInstance()->clean();
    }

    public function testBindSingleton()
    {
        Container::bind('foo', 'baz');
        $bag = Container::getInstance()->get('foo');
        $this->assertInstanceOf(Bag::class, $bag);
        $this->assertEquals('foo', $bag->get(Container::BAG_ABSTRACT));
        $this->assertEquals('baz', $bag->get(Container::BAG_CONCRETE));
        $this->assertFalse($bag->get(Container::BAG_IS_SHARED));
        $this->assertFalse($bag->get(Container::BAG_IS_RESOLVED));

        Container::singleton('foo', 'another_baz');
        $bag = Container::getInstance()->get('foo');
        $this->assertEquals('foo', $bag->get(Container::BAG_ABSTRACT));
        $this->assertEquals('another_baz', $bag->get(Container::BAG_CONCRETE));
        $this->assertTrue($bag->get(Container::BAG_IS_SHARED));
        $this->assertFalse($bag->get(Container::BAG_IS_RESOLVED));
    }

    public function testInstance()
    {
        $instance = new SampleTwo(1, 2);
        Container::singleton('foo', $instance);
        $this->assertEquals($instance, Container::instance('foo'));
        $this->assertEquals($instance, Container::instance('foo'));
    }

    public function testResolve()
    {
        // it should return sampleOne instance with all dependencies
        $sampleOne = Container::resolve(SampleOne::class, [0 => 11, 2 => 22]);
        $this->assertEquals(11, $sampleOne->getAttr1());
        $this->assertEquals(22, $sampleOne->getAttr2());
        $this->assertInstanceOf(SampleTwo::class, $sampleOne->getSampleTwo());

        $sampleThree = Container::resolve(SampleThree::class);
        $this->assertInstanceOf(SampleThree::class, $sampleThree);
    }

    public function testResolveExpectBindingException()
    {
        $this->expectException(BindingResolutionException::class);
        Container::resolve('awesome_class');
    }

    public function testResolveExpectInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        Container::getInstance()->set('foo', 'baz');
        Container::resolve('foo');
    }

    public function testResolveExpectInvalidArgumentExceptionForInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        Container::bind('foo', []);
        Container::resolve('foo');
    }

    public function testGetReflectorExpectClassNotFoundException()
    {
        $this->expectException(ClassNotFoundException::class);
        $this->invokeMethod(Container::getInstance(), 'getReflector', ['invalid_class_name']);
    }

    public function testGetReflectorExpectBindingResolutionException()
    {
        $this->expectException(BindingResolutionException::class);
        $this->invokeMethod(Container::getInstance(), 'getReflector', [UninstantiableInterface::class]);
    }

    public function testResolveWithArguments()
    {
        $sampleOne = Container::resolve(SampleOne::class, [11, new SampleTwo(1, 2)]);
        $sampleTwo = $sampleOne->getSampleTwo();
        $this->assertInstanceOf(SampleTwo::class, $sampleTwo);
        $this->assertEquals(1, $sampleTwo->getAttr1());
        $this->assertEquals(10, $sampleOne->getAttr2());

        $sampleOne = Container::resolve(SampleOne::class, [0 => 11, 'sampleTwo' => new SampleTwo(3, 4), 2 => 22]);
        $sampleTwo = $sampleOne->getSampleTwo();
        $this->assertInstanceOf(SampleTwo::class, $sampleTwo);
        $this->assertEquals(3, $sampleTwo->getAttr1());
    }

    public function testResolveExpectInvalidArgumentExceptionForInvalidArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $sampleOne = Container::resolve(SampleOne::class, [11, new SampleThree, 22]);
    }

    public function testResolveWithArgumentIsArray()
    {
        $sampleFour = Container::resolve(SampleFour::class);
        $this->assertEquals([], $sampleFour->getAttr1());
    }

    public function testResolveClosure()
    {
        Container::bind('foo', function () {
            return new SampleFour(['foo' => 'baz']);
        });
        $sampleFour = Container::resolve('foo');
        $this->assertInstanceOf(SampleFour::class, $sampleFour);
        $this->assertEquals(['foo' => 'baz'], $sampleFour->getAttr1());
    }

    public function testSetGetInstance()
    {
        $instance = new Container();
        $instance->bind('foo', 'baz');
        $this->assertNotEquals($instance, Container::getInstance());
        Container::setInstance($instance);
        $this->assertEquals($instance, Container::getInstance());
    }
}
