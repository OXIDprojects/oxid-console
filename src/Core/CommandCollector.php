<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core;

use OxidEsales\Eshop\Core\Registry;
use Symfony\Component\Console\Command\Command;

use OxidProfessionalServices\OxidConsole\Command\CacheClearCommand;
use OxidProfessionalServices\OxidConsole\Command\DatabaseUpdateCommand;
use OxidProfessionalServices\OxidConsole\Command\FixStatesCommand;
use OxidProfessionalServices\OxidConsole\Command\GenerateMigrationCommand;
use OxidProfessionalServices\OxidConsole\Command\GenerateModuleCommand;
use OxidProfessionalServices\OxidConsole\Command\MigrateCommand;
use OxidProfessionalServices\OxidConsole\Command\ActivateModuleCommand;

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
        if (!class_exists('oxConsoleCommand'))
            class_alias(CommandCollector::class, 'oxConsoleCommand');

        $commands = $this->getCommandsFromCore();
        $commandsFromModules = $this->getCommandsFromModules();
        return array_merge(
            $commands,
            $commandsFromModules
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
            new FixStatesCommand(),
            new GenerateMigrationCommand(),
            new GenerateModuleCommand(),
            new MigrateCommand(),
            new ActivateModuleCommand()
        ];
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

        if (!is_dir($modulesRootPath))
            return [];

        if (!is_array($modulePaths))
            return [];

        $fullModulePaths = array_map(function ($modulePath) use ($modulesRootPath) {
            return $modulesRootPath . $modulePath;
        }, array_values($modulePaths));

        return array_filter($fullModulePaths, function ($fullModulePath) {
            return is_dir($fullModulePath);
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
        $classesBefore = get_declared_classes();
        try {
            require_once $pathToPhpFile;
        } catch (\Exception $exception) {

        }
        $classesAfter = get_declared_classes();
        $newClasses = array_diff($classesAfter, $classesBefore);

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
            try {
                $testObject = new $class();
            } catch (\Exception $exception) {
                return false;
            }

            return get_class($testObject) !== Command::class && is_subclass_of($testObject, Command::class);
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
        return array_map(function ($class) {
            return new $class;
        }, $classes);
    }
}
