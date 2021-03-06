<?php
/**
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file ControllerMatchedEvent.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Core\Events;

use RZ\Roadiz\Core\Kernel;
use RZ\Roadiz\CMS\Controllers\AppController;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Event dispatched after a route has been matched.
 */
class ControllerMatchedEvent extends Event
{
    private $kernel;

    /**
     * @param RZ\Roadiz\Core\Kernel $kernel
     */
    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }
    /**
     * After a controller has been matched. We need to inject current
     * Kernel instance and securityContext.
     *
     * @param \Symfony\Component\HttpKernel\Event\FilterControllerEvent $event
     */
    public function onControllerMatched(FilterControllerEvent $event)
    {
        $matchedCtrl = $event->getController()[0];

        /*
         * Do not inject current theme when
         * Install mode is active.
         */
        if (true !== $this->kernel->container['config']['install']) {
            // No node controller matching in install mode
            $this->kernel->getRequest()->setTheme($matchedCtrl::getTheme());
        }

        /*
         * Set request locale if _locale param
         * is present in Route.
         */
        $routeParams = $this->kernel->getRequest()->get('_route_params');
        if (!empty($routeParams["_locale"])) {
            $this->kernel->getRequest()->setLocale($routeParams["_locale"]);
        }

        /*
         * Inject current Kernel to the matched Controller
         */
        if ($matchedCtrl instanceof AppController) {
            $matchedCtrl->setKernel($this->kernel);
            $matchedCtrl->__init();
        }
    }
}
