<?php namespace Titan\DI;

use Closure;
use Exception;
use ReflectionClass;
use Titan\Common\Bag;
use Titan\Common\BagInterface;
use Titan\Common\BagTrait;
use Titan\DI\Exception\BindingResolutionException;
use Titan\DI\Exception\ClassNotFoundException;
use Titan\DI\Exception\InvalidArgumentException;

class Container
{
    use BagTrait;

    const BAG_ABSTRACT    = 'abstract';
    const BAG_CONCRETE    = 'concrete';
    const BAG_INSTANCE    = 'instance';
    const BAG_IS_SHARED   = 'is_shared';
    const BAG_IS_RESOLVED = 'is_resolved';

    const METHOD_CONSTRUCT = '__construct';

    /**
     * @var static
     */
    private static $instance;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * @param self $instance
     */
    public static function setInstance(Container $instance)
    {
        static::$instance = $instance;
    }

    /**
     * Bind an abstract with a specific concrete, concrete might be Closure, string
     *
     * @param string $abstract
     * @param mixed  $concrete
     * @param bool   $shared Share this concrete for any calls to abstract instead of make new one
     */
    public static function bind($abstract, $concrete, $is_shared = false)
    {
        $bag = new Bag();
        $bag->set(static::BAG_ABSTRACT, $abstract);
        $bag->set(static::BAG_CONCRETE, $concrete);
        $bag->set(static::BAG_IS_SHARED, (bool) $is_shared);
        $bag->set(static::BAG_IS_RESOLVED, false);

        static::getInstance()->set($abstract, $bag);
    }

    /**
     * Bind abstract and enable sharing
     *
     * @param string $abstract
     * @param mixed  $concrete
     */
    public static function singleton($abstract, $concrete)
    {
        static::getInstance()->bind($abstract, $concrete, true);
    }

    /**
     * Get shared instance
     *
     * @param string $abstract
     * @return mixed|object
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     */
    public static function instance($abstract)
    {
        return static::getInstance()->resolve($abstract);
    }

    /**
     * Resolve an instance
     *
     * @param string $abstract
     * @param array  $arguments
     * @return mixed|object
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     */
    public static function resolve($abstract, $arguments = [])
    {
        $container = static::getInstance();
        if (!$container->has($abstract)) {
            if (class_exists($abstract)) {
                // Bind this abstract to itself
                $container->bind($abstract, $abstract);
            } else {
                throw new BindingResolutionException("$abstract could not be found.");
            }
        }

        $bag = $container->get($abstract);
        if (!$bag instanceof BagInterface) {
            throw new InvalidArgumentException('Object must be implemented BagInterface.');
        }

        $concrete   = $bag->get(static::BAG_CONCRETE);
        $isShared   = (bool) $bag->get(static::BAG_IS_SHARED, false);
        $isResolved = (bool) $bag->get(static::BAG_IS_RESOLVED, false);
        if ($isShared && $isResolved) {
            return $bag->get(static::BAG_INSTANCE);
        } elseif ($concrete instanceof Closure) {
            $instance = $container->resolveClosure($concrete, $arguments);
        } elseif (is_object($concrete)) {
            $instance = $concrete;
        } elseif (is_string($concrete)) {
            $instance = $container->resolveInstance($concrete, $arguments);
        } else {
            throw new InvalidArgumentException("Concrete of $abstract has invalid type.");
        }

        if ($instance) {
            $bag->set(static::BAG_IS_RESOLVED, true);

            if ($isShared) {
                $bag->set(static::BAG_INSTANCE, $instance);
            }
        }
        $container->set($abstract, $bag);

        return $instance;
    }

    /**
     * Resolve a closure
     *
     * @param Closure $closure
     * @param array   $arguments
     * @return mixed
     */
    private function resolveClosure(Closure $closure, $arguments = [])
    {
        return call_user_func_array($closure, $arguments);
    }

    /**
     * Resolve an instance using ReflectionClass
     *
     * @param string $concrete
     * @param array $arguments
     * @return object
     * @throws BindingResolutionException
     * @throws ClassNotFoundException
     * @throws InvalidArgumentException
     */
    private function resolveInstance($concrete, $arguments = [])
    {
        $reflector  = $this->getReflector($concrete);
        $parameters = $this->build($reflector, $arguments);

        if (count($parameters)) {
            $instance = $reflector->newInstanceArgs(array_values($parameters));
        } else {
            $instance = $reflector->newInstance();
        }

        return $instance;
    }

    /**
     * Get Reflection Class
     *
     * @return ReflectionClass
     * @throws ClassNotFoundException
     * @throws BindingResolutionException
     */
    protected function getReflector($name)
    {
        try {
            $reflector = new ReflectionClass($name);
        } catch (Exception $e) {
            throw new ClassNotFoundException("Class $name could not be found");
        }

        // If the type is not instantiable, the developer is attempting to resolve
        // an abstract type such as an Interface of Abstract Class and there is
        // no binding registered for the abstractions so we need to bail out.
        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException("Target [$name] is not instantiable.");
        }

        return $reflector;
    }

    /**
     * Build dependencies based on ReflectionClass and proposed arguments
     *
     * @param ReflectionClass $reflector
     * @param array           $arguments
     * @param string          $method_name
     * @return array
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     */
    protected function build(ReflectionClass $reflector, $arguments = [], $method_name = self::METHOD_CONSTRUCT)
    {
        $dependencies = [];
        if ($reflector->hasMethod($method_name)) {
            $method = $reflector->getMethod($method_name);
            foreach ($method->getParameters() as $i => $parameter) {
                $name       = $parameter->getName();
                $dependency = null;
                if (is_array($arguments) && array_key_exists($name, $arguments)) {
                    // Use the predefined parameter if it is set already
                    $dependency = $arguments[$name];
                } elseif (is_array($arguments) && isset($arguments[$i])) {
                    if ($parameter->getClass() !== null) {
                        // Use the provided parameter if it is set and instanceof the parameter abstract
                        $typeHint = $parameter->getClass()->getName();
                        if ($arguments[$i] instanceof $typeHint) {
                            $dependency = $arguments[$i];
                        } else {
                            $message = get_class($arguments[$i]) . " must be an instance of $typeHint";
                            throw new InvalidArgumentException($message);
                        }
                    } else {
                        $dependency = $arguments[$i];
                    }
                } else {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependency = $parameter->getDefaultValue();
                    } elseif ($abstract = $parameter->getClass()) {
                        // Build dependency for this parameter
                        $dependency = $this->resolve($abstract->getName());
                    } elseif ($parameter->isArray()) {
                        $dependency = [];
                    }
                }

                $dependencies[$name] = $dependency;
            }
        }

        return $dependencies;
    }
}
