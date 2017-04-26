<?php
namespace Untitled;

use FastRoute\Dispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Untitled\Event\NotFoundEvent;
use Untitled\Event\PreDispatchEvent;
use Zend\ServiceManager\ServiceManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use zpt\anno\Annotations;

/**
 * Class App
 */
class App
{
    /**
     * @param array $config
     */
    public static function init(array $config)
    {
        $request = Request::createFromGlobals();

        $serviceManager = new ServiceManager($config['service_manager']);

        // Prepare event dispatcher
        $eventDispatcher = $config['eventDispatcher'] ?? new EventDispatcher();
        if (is_callable($eventDispatcher)) {
            $eventDispatcher = $eventDispatcher($serviceManager);
        } elseif ($serviceManager->has($eventDispatcher)) {
            $eventDispatcher = $serviceManager->get($eventDispatcher);
        }

        // Handle routing
        $httpMethod = $request->getMethod();
        $uri = $request->getPathInfo();

        /** @var Dispatcher $dispatcher */
        $dispatcher = $config['routes'];

        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $response = new Response('Not found', Response::HTTP_NOT_FOUND);
                // Dispatch event
                // todo: add response to the event
                // todo: remove dispatcher and add route info
                $notFoundEvent = new NotFoundEvent($request, $dispatcher);
                $eventDispatcher->dispatch(NotFoundEvent::EVENT_NAME, $notFoundEvent);
                $response->send();
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                // todo: add event with: $request, $response, $routeInfo
                $methodNotAllowedResponse = new Response('Method not allowed', Response::HTTP_METHOD_NOT_ALLOWED);
                $methodNotAllowedResponse->send();
                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                $request->attributes->add($vars);
                $controller = $handler[0];
                $controllerInstance = $serviceManager->get($controller);
                $controllerReflection = new \ReflectionClass($controller);

                foreach ($controllerReflection->getProperties() as $property) {
                    $annotation = new Annotations($property);
                    if ($dependencyIdentifier = $annotation['inject'] ?? null) {
                        if (class_exists($dependencyIdentifier)) {
                            $dependencyIdentifier = $dependencyReflection = (new \ReflectionClass($annotation['inject']))->getName();
                        }

                        $dependency = $serviceManager->get($dependencyIdentifier);
                        $method = $controllerReflection->getMethod(sprintf('set%s', ucfirst($property->getName())))->getName();
                        $controllerInstance->{$method}($dependency);
                    }
                }

                $action = $handler[1];
                $actionMethod = sprintf('%sAction', $action);
                /** @var Response $response */
                if (method_exists($controllerInstance, $actionMethod)) {
                    $response = $controllerInstance->{$actionMethod}($request);
                } else {
                    $response = new Response('Method not found', Response::HTTP_NOT_FOUND);
                }

                // Fire predispatch event
                // todo: add response to the event
                $preDispatchEvent = new PreDispatchEvent($request);
                $eventDispatcher->dispatch(PreDispatchEvent::EVENT_NAME, $preDispatchEvent);

                $response->send();
                break;
        }
    }
}
