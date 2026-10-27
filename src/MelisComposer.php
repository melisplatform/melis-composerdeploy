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
            // SECURITY (audit CD-M1): this used to default COMPOSER_HOME to the shared,
            // world-writable /tmp (and tested $_ENV, which is empty under the default
            // variables_order=GPCS, so a COMPOSER_HOME actually present in the
            // environment was ignored too). Any local user could pre-plant
            // /tmp/config.json, /tmp/auth.json and globally-installed plugins and have
            // the factory load them as the web user. Honour the real environment and
            // fall back to a per-user 0700 directory instead; plugins are disabled —
            // this consumer only reads the local repository and needs none.
            static::ensureComposerHome();
            $factory = new Factory();
            $this->setComposer($factory->createComposer(new NullIO(), null, true));
        }

        return $this->composer;
    }

    /**
     * Makes sure COMPOSER_HOME is set without ever pointing it at a directory
     * another local user may control. The home only has to exist for Composer's
     * bootstrap; when the environment provides none, a private per-user
     * directory under the system temp dir is created (0700) and used.
     *
     * @return string the effective COMPOSER_HOME
     * @throws \RuntimeException when the fallback directory cannot be trusted
     */
    public static function ensureComposerHome()
    {
        $home = getenv('COMPOSER_HOME');
        if ($home !== false && $home !== '') {
            return $home;
        }

        $uid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
        $home = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'melis-composer-home-' . $uid;

        if (!is_dir($home)) {
            @mkdir($home, 0700, true);
        }

        // mkdir above fails silently when the path already exists: refuse a
        // directory pre-planted by another local user instead of reading its
        // config.json/auth.json.
        if (!is_dir($home)
            || (function_exists('posix_geteuid') && false !== ($owner = @fileowner($home)) && $owner !== $uid)) {
            throw new \RuntimeException(
                'Refusing to use ' . $home . ' as COMPOSER_HOME: directory is missing or owned by another user.'
            );
        }

        putenv('COMPOSER_HOME=' . $home);

        return $home;
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
        // PERF/SECURITY (audit AM-M6): cache the parsed installed.json across ALL instances.
        // MelisModulesService creates a fresh MelisComposer on nearly every call, so the former
        // per-instance ($this->packages) cache never helped — installed.json (~300 KB) was
        // re-read and re-decoded several times PER asset request (dozens per page). A process-wide
        // static makes it read-and-decode once per request.
        static $cache = null;

        if ($cache === null) {
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ? $_SERVER['DOCUMENT_ROOT'].'/../' : './';

            $installedPackagesJson = $docRoot.'vendor/composer/installed.json';
            $cache = (array) \Laminas\Json\Json::decode(file_get_contents($installedPackagesJson));

            // forward compatibility for composer v2 installed.json
            if (isset($cache['packages'])) {
                $cache = $cache['packages'];
            }
        }

        $this->packages = $cache;

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
