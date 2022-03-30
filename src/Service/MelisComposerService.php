<?php

/**
 * Melis Technology (http://www.melistechnology.com)
 *
 * @copyright Copyright (c) 2016 Melis Technology (http://www.melistechnology.com)
 *
 */

namespace MelisComposerDeploy\Service;

use Composer\Console\Application;
use Laminas\ServiceManager\ServiceManager;
use MelisCore\Service\MelisServiceManager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * This service handles the requests and commands that will be made into composer
 */
class MelisComposerService extends MelisServiceManager
{
    const COMPOSER = __DIR__ . '/../../bin/extracted-composer/composer';
    const INSTALL = 'install';
    const UPDATE = 'update';
    const REMOVE = 'remove';
    const DOWNLOAD = 'require';
    const DUMP_AUTOLOAD = 'dump-autoload';

    const DEFAULT_ARGS = '--no-interaction --profile ';
    const REMOVE_ARGS = '-vv --no-scripts ';
    const DRY_RUN_ARGS = '--dry-run';
    const ROOT_REQS = '--root-reqs ';
    const WITH_DEPENDENCIES = '--with-dependencies';
    const NO_UPDATE = ' --no-update ';
    const NO_PROGRESS = '--no-progress ';
    const IGNORE_REQ = '--ignore-platform-reqs';
    const PREFER_DIST = '--prefer-dist';
    const NO_SCRIPTS = '--no-scripts';

    /**
     * The path of the platform
     *
     * @var string
     */
    protected $documentRoot;

    /**
     * Sets whether composer commands should be applied or just for testing
     *
     * @var boolean
     */
    protected $isDryRun;

    /**
     * Executes a $ composer update command
     *
     * @param null $package
     * @param null $version
     * @param bool $dryRun
     *
     * @return string|\Symfony\Component\Console\Output\StreamOutput
     * @throws \Exception
     */
    public function update($package = null, $version = null, $dryRun = false)
    {
        if ($dryRun) {
            $this->setDryRun(true);
        }

        $package = !empty($version) ? $package . ':' . $version : $package;

        return $this->runCommand(self::UPDATE, $package, self::ROOT_REQS . self::DEFAULT_ARGS);
    }

    /**
     * Sets whether to enable the dry-run arg
     *
     * @param $status
     */
    public function setDryRun($status)
    {
        $this->isDryRun = (bool) $status;
    }

    /**
     * This calls the composer CLI to execute a command
     *
     * @param $cmd
     * @param null $package
     * @param $args
     * @param bool $noAddtlArguments
     *
     * @return string|\Symfony\Component\Console\Output\StreamOutput
     * @throws \Exception
     */
    private function runCommand($cmd, $package, $args, $noAddtlArguments = false)
    {
        $translator = $this->getServiceManager()->get('translator');
        $docPath = str_replace(['\\', 'public/../'], '', $this->getDocumentRoot());
        $docPath = trim(substr($docPath, 0, strlen($docPath) - 1)); // remove last "/" trail

        set_time_limit(0);
        ini_set('memory_limit', -1);       
        putenv('COMPOSER_HOME=' . self::COMPOSER);

        if (in_array($cmd, $this->availableCommands())) {

            $dryRunArgs = null;

            if ($this->getDryRun()) {
                $dryRunArgs = self::DRY_RUN_ARGS;
            }

            $noProgress = self::NO_PROGRESS;
            $ignoreReqs = self::IGNORE_REQ;
            $preferDist = self::PREFER_DIST;
            $noScript = self::NO_SCRIPTS;

            if ($cmd !== self::REMOVE){
                $commandString = "$cmd $package $dryRunArgs $args $ignoreReqs $noProgress $noScript $preferDist --working-dir=\"$docPath\"";

                // override commandstring if noAddtlArguments is set to "true"
                if ($noAddtlArguments) {
                    $commandString = "$cmd --working-dir=\"$docPath\"";
                }
            }else
                $commandString = "$cmd $package $ignoreReqs $noScript";

            $input = new StringInput($commandString);
            $output = new StreamOutput(fopen('php://output', 'w'));
            $composer = new Application();
            $formatter = $output->getFormatter();

            $formatter->setDecorated(true);
            $formatter->setStyle('error', new ComposerOutputFormatterStyle(ComposerOutputFormatterStyle::ERROR));
            $formatter->setStyle('info', new ComposerOutputFormatterStyle(ComposerOutputFormatterStyle::INFO));
            $formatter->setStyle('comment', new ComposerOutputFormatterStyle(ComposerOutputFormatterStyle::COMMENT));
            $formatter->setStyle('warning', new ComposerOutputFormatterStyle(ComposerOutputFormatterStyle::ERROR));
            $output->setFormatter($formatter);

            chdir($docPath);

            if (PHP_OS !== 'AIX' && DIRECTORY_SEPARATOR == '/') {
                /** proc_open(): fork failed - Cannot allocate memory [fix] | linux only */
                shell_exec('sudo /bin/dd if=/dev/zero of=/var/swap.1 bs=1M count=1024');
                shell_exec('sudo /sbin/mkswap /var/swap.1');
                shell_exec('sudo /sbin/swapon /var/swap.1');
            }

            $composer->run($input, $output);

            return $output;
        }

        return sprintf($translator->translate('tr_market_place_unknown_command'), $cmd);
    }

    /**
     * Returns the path of the platform, if nothing is set, then it will use the default path of this platform
     *
     * @return string
     */
    public function getDocumentRoot()
    {
        if (!$this->documentRoot) {
            $this->documentRoot = $this->getDefaultDocRoot();
        }

        return $this->documentRoot;
    }

    /**
     * Sets the path of the platform, if nothing is set, then it will use the default path of this platform
     *
     * @param $documentRoot
     */
    public function setDocumentRoot($documentRoot)
    {
        if ($documentRoot) {
            $this->documentRoot = $documentRoot;
        } else {
            $this->documentRoot = $this->getDefaultDocRoot();
        }
    }

    /**
     * Returns the document root of this platform
     *
     * @return string
     */
    private function getDefaultDocRoot()
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/../';
    }

    /**
     * Sets the limitation to what commands that can be executed
     *
     * @return array
     */
    private function availableCommands()
    {
        return [
            self::INSTALL,
            self::UPDATE,
            self::DUMP_AUTOLOAD,
            self::DOWNLOAD,
            self::REMOVE,
        ];
    }

    /**
     * Returns if dry-run arg is enabled or not
     *
     * @return string
     */
    public function getDryRun()
    {
        return $this->isDryRun;
    }

    /**
     * Executes $ composer require command
     *
     * @param $package
     * @param null $version
     * @param bool $noInstall
     *
     * @return string|\Symfony\Component\Console\Output\StreamOutput
     * @throws \Exception
     */
    public function download($package, $version = null, $noInstall = false)
    {
        $package = !empty($version) ? $package . ':' . $version : $package;

        $args = $noInstall === true ? self::NO_PROGRESS . self::NO_UPDATE . self::DEFAULT_ARGS : self::DEFAULT_ARGS;

        return $this->runCommand(self::DOWNLOAD, $package, $args);
    }

    /**
     * Executes $ composer dump-autoload comand
     *
     * @return string|\Symfony\Component\Console\Output\StreamOutput
     * @throws \Exception
     */
    public function dumpAutoload()
    {
        return $this->runCommand(self::DUMP_AUTOLOAD, null, null, true);
    }

    /**
     * Executes a $ composer remove package/package command
     *
     * @param $package
     *
     * @return bool
     */
    public function remove($package)
    {
        $output = $this->runCommand(self::REMOVE, $package, self::REMOVE_ARGS);

        if (!$output) {
            return true;
        }

        return false;
    }

}
