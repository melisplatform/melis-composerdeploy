<?php

namespace MelisComposerDeploy;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;

class MelisComposer
{
    /**
     * @var Composer
     */
    protected $composer;

    public $packages;

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
                putenv("COMPOSER_HOME=/tmp");
            }
            $factory = new Factory();
            $this->setComposer($factory->createComposer(new NullIO()));
        }

        return $this->composer;
    }

    /**
     * Return Melis modules path
     *
     * @param $moduleName
     * @param bool $returnFullPath
     *
     * @return string
     */
    public function getComposerModulePath($moduleName, $returnFullPath = true)
    {
        foreach ($this->getInstalledPackages() As $package) {

            if ($package->type == 'melisplatform-module' && !empty($package->extra)) {

                $extra = (array) $package->extra ?? [];
                if (array_key_exists('module-name', $extra)) {

                    if ($moduleName == $extra['module-name']) {

                        // Package name as Vendor package path
                        $packageName = $package->name;

                        if ($returnFullPath && php_sapi_name() != "cli") {
                            return $_SERVER['DOCUMENT_ROOT'] . '/../vendor/' . $packageName;
                        } else {
                            return '/vendor/' . $packageName;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Composer packages installed
     * This will get the /vendor/composer/installed.json
     * where the list of packages stored
     *
     * @return array
     */
    public function getInstalledPackages()
    {
        if (!$this->packages) {

            $docRoot = $_SERVER['DOCUMENT_ROOT'] ? $_SERVER['DOCUMENT_ROOT'].'/../' : './';

            $installedPackagesJson = $docRoot.'vendor/composer/installed.json';
            $this->packages = (array) \Laminas\Json\Json::decode(file_get_contents($installedPackagesJson));

            // forward compatibility for composer v2 installed.json
            if (isset($this->packages['packages'])) {
                $this->packages = $this->packages['packages'];
            }

        }

        return $this->packages;
    }

    /**
     * Melis Packages installed
     * Only packages installed in vendor/melisplatform
     * with type of melisplatform-module and
     * extra module-name
     */
    public function getMelisPackages()
    {
        return array_filter($this->getInstalledPackages(), function($package) {
            $type = $package->type;
            $extra = $package->extra ?? (object)[];

            /** @var CompletePackage $package */
            return $type === 'melisplatform-module' &&
                property_exists($extra, 'module-name');
        });
    }
}
