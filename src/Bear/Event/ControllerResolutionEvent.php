<?php
namespace Bear\Event;

use Symfony\Component\HttpFoundation\Request;

/**
 * Class ControllerResolutionEvent
 *
 * @package Bear\Event
 */
class ControllerResolutionEvent extends RequestAwareEvent
{
    const EVENT_NAME = 'bear.controller_resolution';

    /**
     * @var object
     */
    private $controller;

    /**
     * ControllerResolutionEvent constructor.
     *
     * @param Request $request
     * @param         $controller
     */
    public function __construct(Request $request, $controller)
    {
        parent::__construct($request);

        $this->controller = $controller;
    }

    /**
     * @return object
     */
    public function getController(): object
    {
        return $this->controller;
    }
}
