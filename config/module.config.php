<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2015 Melis Technology (http://www.melistechnology.com)
 *
 */

return [
    'service_manager' => [
        'factories' => [
            MelisComposerDeploy\Service\MelisComposerService::class => \MelisComposerDeploy\Service\Factory\MelisComposerServiceFactory::class,
        ],
        'aliases' => [
            'MelisComposerService' => MelisComposerDeploy\Service\MelisComposerService::class
        ]
    ],
];