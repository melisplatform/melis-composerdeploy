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
use MelisComposerDeploy\MelisComposer;
use MelisCore\Service\MelisServiceManager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * This service handles the requests and commands that will be made into composer
 */
class MelisComposerService extends MelisServiceManager
{
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
     * Exit code of the last composer run (0 = success), null before any run
     *
     * @var int|null
     */
    protected $lastExitCode;

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

        // No package means "update everything" — `composer update --root-reqs`, which
        // is what MelisInstaller's downloadModules step calls (update() with no args).
        // Only validate when a package was actually requested.
        $package = ($package === null || $package === '')
            ? null
            : $this->buildPackageArg($package, $version);

        return $this->runCommand(self::UPDATE, $package, self::ROOT_REQS . self::DEFAULT_ARGS);
    }

    /**
     * Validates and assembles the "package[:version]" argument that is passed
     * to composer.
     *
     * Both $package and $version are interpolated into the command string that
     * is handed to Symfony's StringInput (which tokenizes on whitespace), so an
     * attacker-controlled value containing spaces could inject additional
     * composer arguments/options. We therefore reject anything that does not
     * strictly match a Composer package name (and, if given, a semver-ish
     * version constraint).
     *
     * @param string      $package
     * @param string|null $version
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    private function buildPackageArg($package, $version = null)
    {
        if (!is_string($package) || $package === '') {
            throw new \InvalidArgumentException('Invalid composer package name.');
        }

        // vendor/name, optionally followed by :constraint
        $packageRegex = '/^[a-z0-9]([a-z0-9._-]*)\/[a-z0-9]([a-z0-9._-]*)(:[\w.*<>=~^|-]+)?$/';
        // Callers may legitimately pass a whitespace-separated LIST of packages:
        // MelisInstaller does exactly that (implode(' ', $downloadableModules)) when the
        // setup wizard downloads the modules the user picked. Validate every token
        // individually — the injection guard still holds, because nothing but a
        // well-formed package name can survive the check below.
        $packages = preg_split('/\s+/', trim($package), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($packages)) {
            throw new \InvalidArgumentException('Invalid composer package name.');
        }

        foreach ($packages as $singlePackage) {
            if (!preg_match($packageRegex, $singlePackage)) {
                throw new \InvalidArgumentException('Invalid composer package name: ' . $singlePackage);
            }
        }

        if (count($packages) > 1) {
            if (!empty($version)) {
                throw new \InvalidArgumentException(
                    'A version constraint cannot be combined with several packages.'
                );
            }

            return implode(' ', $packages);
        }

        $package = $packages[0];

        if (!empty($version)) {
            // semver-ish constraint: digits, dots, wildcards and range operators
            $versionRegex = '/^[\w.*<>=~^|\- ]+$/';
            if (preg_match('/\s/', trim($version)) || !preg_match($versionRegex, $version)) {
                throw new \InvalidArgumentException('Invalid composer version constraint: ' . $version);
            }

            // if the package already embeds a constraint, do not append another
            if (strpos($package, ':') === false) {
                $package = $package . ':' . $version;
            }
        }

        return $package;
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
        $docPath = str_replace(['\\', 'public/../'], '', $this->getDocumentRoot());
        $docPath = trim(substr($docPath, 0, strlen($docPath) - 1)); // remove last "/" trail

        set_time_limit(0);
        ini_set('memory_limit', -1);
        // SECURITY (audit CD-M3): this used to force COMPOSER_HOME into the package's
        // own bin/ directory (a path that does not even exist in the shipped module):
        // the shared warm cache was bypassed and config.json/auth.json ended up inside
        // vendor/, wiped on every module update and sitting in a git-tracked tree.
        // Respect the environment instead, with the same private fallback as
        // MelisComposer (never the shared /tmp).
        MelisComposer::ensureComposerHome();

        if (in_array($cmd, $this->availableCommands())) {

            $dryRunArgs = null;

            if ($this->getDryRun()) {
                $dryRunArgs = self::DRY_RUN_ARGS;
            }
            // CD-M5: consume the flag — it used to stay on for the rest of the request,
            // silently turning every later real run into a dry run.
            $this->setDryRun(false);

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
                // CD-M5: align the remove branch with the others — honour $args
                // (REMOVE_ARGS was silently dropped), pin the working dir instead of
                // relying on the chdir() below, and never allow an interactive prompt.
                $commandString = "$cmd $package $dryRunArgs $args $ignoreReqs $noScript --no-interaction --working-dir=\"$docPath\"";

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

            // CD-M5: without this, Symfony's Application::run() ends with exit($code),
            // killing the PHP request as soon as composer finishes — every caller's
            // post-run code was dead and no failure was ever observable (gotcha 8).
            $composer->setAutoExit(false);
            $this->lastExitCode = $composer->run($input, $output);

            return $output;
        }

        $translator = $this->getServiceManager()->get('translator');

        return sprintf($translator->translate('tr_market_place_unknown_command'), $cmd);
    }

    /**
     * Exit code of the last composer run (0 = success), null before any run
     *
     * @return int|null
     */
    public function getLastExitCode()
    {
        return $this->lastExitCode;
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
        // Nothing to require is a no-op, not an invalid package name. MelisInstaller's
        // addModulesToComposer step calls download(implode(' ', $modules)) and that list
        // is legitimately EMPTY when the wizard's "Melis Core only" option is picked
        // (module_auto_install ships empty by design), so validating it would 500 the
        // Installation step on a perfectly valid choice. `composer require` with no
        // package is not the same thing as update()'s "everything" — it would prompt —
        // so return before running anything at all.
        if ($package === null || trim((string) $package) === '') {
            return '';
        }

        $package = $this->buildPackageArg($package, $version);

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
        $package = $this->buildPackageArg($package);

        $this->runCommand(self::REMOVE, $package, self::REMOVE_ARGS);

        // CD-M5: the old `!$output` test compared an always-truthy StreamOutput, so
        // this reported failure even when composer succeeded (and was unreachable
        // anyway while run() still exit()ed the request). Report the real outcome.
        return $this->getLastExitCode() === 0;
    }

}
