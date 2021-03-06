<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2016 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisComposerDeploy\Service\Factory;

use Psr\Container\ContainerInterface;

class MelisComposerServiceFactory
{
    public function __invoke(ContainerInterface $container, $requestedName)
    {
        $instance = new $requestedName();
        $instance->setServiceManager($container);
        return $instance;
    }
}