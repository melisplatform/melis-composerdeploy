<?php

namespace MelisComposerDeploy;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\CompletePackage;

class MelisComposer
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @param Composer $composer
     *
     * @return $this
     */
    public function setComposer(Composer $composer)
    {
        $this->composer = $composer;

        return $this;
    }

    /**
     * @return \Composer\Composer
     */
    public function getComposer()
    {
        if (is_null($this->composer)) {
            // required by composer factory but not used to parse local repositories
            if (!isset($_ENV['COMPOSER_HOME'])) {
                putenv("COMPOSER_HOME=".$_SERVER['DOCUMENT_ROOT'] . '/../');
            }
            $factory = new Factory();
            $this->setComposer($factory->createComposer(new NullIO()));
        }

        return $this->composer;
    }

    /**
     * @param $moduleName
     * @param bool $returnFullPath
     *
     * @return string
     */
    public function getComposerModulePath($moduleName, $returnFullPath = true)
    {
        $repos = $this->getComposer()->getRepositoryManager()->getLocalRepository();
        $packages = $repos->getPackages();

        if (!empty($packages)) {
            foreach ($packages as $repo) {
                if ($repo->getType() == 'melisplatform-module') {
                    if (array_key_exists('module-name', $repo->getExtra())
                        && $moduleName == $repo->getExtra()['module-name']) {
                        foreach ($repo->getRequires() as $require) {
                            $source = $require->getSource();

                            if ($returnFullPath) {
                                return $_SERVER['DOCUMENT_ROOT'] . '/../vendor/' . $source;
                            } else {
                                return '/vendor/' . $source;
                            }
                        }
                    }
                }
            }
        }

        return '';
    }

}
