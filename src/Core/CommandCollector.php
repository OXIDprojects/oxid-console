<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\Repository\ComposerRepository;
use Composer\Repository\InstalledFilesystemRepository;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Module\ModuleList;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;
use OxidProfessionalServices\OxidConsole\Command\CacheClearCommand;
use OxidProfessionalServices\OxidConsole\Command\DatabaseUpdateCommand;
use OxidProfessionalServices\OxidConsole\Command\GenerateMigrationCommand;
use OxidProfessionalServices\OxidConsole\Command\GenerateModuleCommand;
use OxidProfessionalServices\OxidConsole\Command\MigrateCommand;
use OxidProfessionalServices\OxidConsole\Command\ActivateModuleCommand;
use OxidProfessionalServices\OxidConsole\Command\DeactivateModuleCommand;
use Throwable;

/**
 * Class responsible for collecting instances of available `Commands`
 *
 * @package OxidProfessionalServices\OxidConsole
 */
class CommandCollector
{
    /**
     * Get all available command objects.
     *
     * Command objects are collected from the following sources:
     *   1) All core commands from `OxidProfessionalServices\OxidConsole\Command` namespace;
     *   2) All commands from modules matching the following criteria:
     *     a) Are placed under the directory `Command` within a module;
     *     b) PHP Class file ends with `Command` as suffix of the class;
     *     c) PHP Class extends `Symfony\Component\Console\Command\Command`.
     *
     * @return Command[]
     */
    public function getAllCommands()
    {
        // Avoid fatal errors for "Class 'oxConsoleCommand' not found" in case of old Commands present
        if (!class_exists('oxConsoleCommand')) {
            class_alias(Command::class, 'oxConsoleCommand');
        }
        $commands = $this->getCommandsFromCore();
        $commandsFromComposer = $this->getCommandsFromComposer();
        $commandsFromModules = $this->getCommandsFromModules();
        return array_merge(
            $commands,
            $commandsFromModules,
            $commandsFromComposer
        );
    }

    /**
     * Get all predefined commands from this package.
     *
     * Statically collect all command objects from `OxidProfessionalServices\OxidConsole\Command` namespace.
     *
     * @return Command[]
     */
    private function getCommandsFromCore()
    {
        return [
            new CacheClearCommand(),
            new DatabaseUpdateCommand(),
            new GenerateMigrationCommand(),
            new GenerateModuleCommand(),
            new MigrateCommand(),
            new ActivateModuleCommand(),
            new DeactivateModuleCommand()
        ];
    }

    private function getCommandsFromComposer()
    {


        $localRepository = new InstalledFilesystemRepository(new JsonFile(VENDOR_PATH . '/composer/installed.json'));

        $commandsClasses = [];

        $packages = $localRepository->getPackages();

        $symfonyContainer = new ContainerBuilder();
        $loader = new YamlFileLoader($symfonyContainer, new FileLocator());

        foreach ($packages as $package) {
            //deprecated syntax to be removed
            $extra = $package->getExtra();
            $oxideshop = isset($extra['oxideshop']) ? $extra['oxideshop'] : [];
            $consoleCommands = isset($oxideshop['console-commands']) && is_array($oxideshop['console-commands']) ?
                $oxideshop['console-commands'] : [];
            foreach ($consoleCommands as $commandClass) {
                print "$commandClass is defined in composer.json of module this is deprecated\n";
                $commandsClasses[] = new $commandClass();
            }
            //end of deprecated code

            $serviceFile =  VENDOR_PATH  . $package->getName() . '/services.yaml';
            if (file_exists($serviceFile)) {
                $loader->load($serviceFile);
            }
        }
        foreach ($symfonyContainer->findTaggedServiceIds('console.command') as $id => $tags) {
            $definition = $symfonyContainer->getDefinition($id);
            $class = $definition->getClass();
            try {
                //TODO maybe get the command with DI container
                $commandsClasses[] = new $class();
            } catch (Throwable $ex) {
                print "WARNING: can not create command $id " . $ex->getMessage();
            }
        }

        return $commandsClasses;
    }

    /**
     * Collect all available commands from modules.
     *
     * Dynamically scan all modules and include available command objects.
     *
     * @return array
     */
    private function getCommandsFromModules()
    {
        $oConfig = Registry::getConfig();

        if (! class_exists(ModuleList::class)) {
            print "ERROR: Oxid ModuleList class can not be loaded,
                please try to run vendor/bin/oe-eshop-unified_namespace_generator";
        } else {
            try {
                $moduleList = oxNew(ModuleList::class);
                $modulesDir = $oConfig->getModulesDir();
                $moduleList->getModulesFromDir($modulesDir);
            } catch (Throwable $exception) {
                print "Shop is not able to list modules\n";
                print $exception->getMessage();
                return [];
            }
        }

        $paths = $this->getPathsOfAvailableModules();
        $pathToPhpFiles = $this->getPhpFilesMatchingPatternForCommandFromGivenPaths(
            $paths
        );
        $classes = $this->getAllClassesFromPhpFiles($pathToPhpFiles);
        $comanndClasses = $this->getCommandCompatibleClasses($classes);
        return $this->getObjectsFromClasses($comanndClasses);
    }

    /**
     * Convert array of arrays to flat list array.
     *
     * @param array[] $nonFlatArray
     *
     * @return array
     */
    private function getFlatArray($nonFlatArray)
    {
        return array_reduce($nonFlatArray, 'array_merge', []);
    }

    /**
     * Return list of paths to all available modules.
     *
     * @return string[]
     */
    private function getPathsOfAvailableModules()
    {
        $config = Registry::getConfig();
        $modulesRootPath = $config->getModulesDir();
        $modulePaths = $config->getConfigParam('aModulePaths');

        if (!is_dir($modulesRootPath)) {
            return [];
        }

        if (!is_array($modulePaths)) {
            return [];
        }

        $fullModulePaths = array_map(function ($modulePath) use ($modulesRootPath) {
            return $modulesRootPath . $modulePath;
        }, array_values($modulePaths));

        return array_filter($fullModulePaths, function ($fullModulePath) {
            if (! is_dir($fullModulePath)) {
                return false;
            }
            if (file_exists($fullModulePath . '/services.yaml')) {
                return false;
            }
            return true;
        });
    }

    /**
     * Return list of PHP files matching `Command` specific pattern.
     *
     * @param string $path Path to collect files from
     *
     * @return string[]
     */
    private function getPhpFilesMatchingPatternForCommandFromGivenPath($path)
    {
        $folders = ['Commands','commands','Command'];
        foreach ($folders as $f) {
            $cPath = $path . DIRECTORY_SEPARATOR . $f . DIRECTORY_SEPARATOR;

            if (!is_dir($cPath)) {
                continue;
            }
            $files = glob("$cPath*[cC]ommand\.php");

            return $files;
        }
        return [];
    }

    /**
     * Helper method for `getPhpFilesMatchingPatternForCommandFromGivenPath`
     *
     * @param string[] $paths
     *
     * @return string[]
     */
    private function getPhpFilesMatchingPatternForCommandFromGivenPaths($paths)
    {
        return $this->getFlatArray(array_map(function ($path) {
            return $this->getPhpFilesMatchingPatternForCommandFromGivenPath($path);
        }, $paths));
    }

    /**
     * Get list of defined classes from given PHP file.
     *
     * @param string $pathToPhpFile
     *
     * @return string[]
     */
    private function getAllClassesFromPhpFile($pathToPhpFile)
    {
        $name = basename($pathToPhpFile, '.php');
        echo "deprecated command: command $name should be registered in services.yaml";

        $classesBefore = get_declared_classes();
        try {
            echo "scanning $pathToPhpFile...";
            require_once $pathToPhpFile;
            echo ", file loaded.\n";
        } catch (Throwable $exception) {
            print "Can not add Command $pathToPhpFile:\n";
            print $exception->getMessage() . "\n";
            return [];
        }
        $classesAfter = get_declared_classes();
        $newClasses = array_diff($classesAfter, $classesBefore);
        if (count($newClasses) > 1) {
            //try to find the correct class name to use
            //this avoids warnings when module developer use there own command base class, that is not instantiable
            $name = basename($pathToPhpFile, '.php');
            foreach ($newClasses as $newClass) {
                // The filename does not contain the namespace of a class, so use the short-name of the class for
                // comparison
                try {
                    $newClassWithoutNamespace = (new ReflectionClass($newClass))->getShortName();
                } catch (ReflectionException $exception) {
                    $newClassWithoutNamespace = $newClass;
                }

                if ($newClassWithoutNamespace == $name) {
                    return [$newClass];
                }
            }
        }

        return $newClasses;
    }

    /**
     * Helper method for `getAllClassesFromPhpFile`
     *
     * @param string[] $pathToPhpFiles
     *
     * @return string[]
     */
    private function getAllClassesFromPhpFiles($pathToPhpFiles)
    {
        return $this->getFlatArray(array_map(function ($pathToPhpFile) {
            return $this->getAllClassesFromPhpFile($pathToPhpFile);
        }, $pathToPhpFiles));
    }

    /**
     * Filter out classes with predefined criteria to be accepted as valid `Command` classes.
     *
     * A given class should match the following criteria:
     *   a) Extends `Symfony\Component\Console\Command\Command`;
     *   b) Is not `Symfony\Component\Console\Command\Command` itself.
     *
     * @param string[] $classes
     *
     * @return string[]
     */
    private function getCommandCompatibleClasses($classes)
    {
        return array_filter($classes, function ($class) {
            return is_subclass_of($class, Command::class) && $class !== Command::class;
        });
    }

    /**
     * Convert given list of classes to objects.
     *
     * @param string[] $classes
     *
     * @return mixed[]
     */
    private function getObjectsFromClasses($classes)
    {
        $objects = array_map(function ($class) {
            try {
                return new $class();
            } catch (Throwable $ex) {
                print "Can not add command from class $class:\n";
                print $ex->getMessage() . "\n";
            }
            return null;
        }, $classes);
        $objects = array_filter($objects, function ($o) {
            return !is_null($o);
        });
        return $objects;
    }
}
