<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2016 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisComposerDeployTest\Controller;

use MelisCore\ServiceManagerGrabber;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
class MelisComposerDeployControllerTest extends AbstractHttpControllerTestCase
{
    protected $traceError = false;
    protected $sm;
    protected $composer;

    public function setUp()
    {
        $this->sm  = new ServiceManagerGrabber();

        $this->composer = $this->sm->getServiceManager()->get('MelisComposerService');
    }

    

    public function getPayload($method)
    {
        return $this->sm->getPhpUnitTool()->getPayload('MelisComposerDeploy', $method);
    }



    /**
     * START ADDING YOUR TESTS HERE
     */

    public function testComposerIsInstalled()
    {
        $output = shell_exec("composer --version");

        $this->assertStringStartsWith("Composer", $output);
    }


}

