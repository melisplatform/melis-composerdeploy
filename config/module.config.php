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
            'MelisComposerService' => \MelisComposerDeploy\Service\Factory\MelisComposerServiceFactory::class,
        ],
    ],
];