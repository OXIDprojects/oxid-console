<?php

/**
 * This file is part of OXID Console.
 *
 * OXID Console is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID Console is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID Console.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2018
 */

namespace OxidProfessionalServices\OxidConsole\Command;

use OxidEsales\Eshop\Core\Exception\StandardException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use OxidProfessionalServices\OxidConsole\Core\ShopConfig;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Module\Module;

/**
 * Class ActivateModuleCommand
 */
class ActivateModuleCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('module:activate')
            ->setDescription('Activate module in shop')
            ->addArgument(
                'moduleid',
                InputArgument::REQUIRED,
                "Module name which should be activated"
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleId = $input->getArgument('moduleid');
        $this->activateModule($moduleId, $output);
    }

    /**
     * @param string $moduleId
     * @param OutputInterface $output
     *
     * @throws StandardException
     */
    public function activateModule($moduleId, $output)
    {
        /** @var ModuleInstaller $moduleInstaller */
        $moduleInstaller = Registry::get(ModuleInstaller::class);

        $moduleList = oxNew(ModuleList::class);
        $moduleList->getModulesFromDir(Registry::getConfig()->getModulesDir());
        $modules = $moduleList->getList();

        /** @var Module $module */
        $module = $modules[$moduleId];
        if ($module == null) {
            $output->writeln("$moduleId not found. choose from:");
            $output->writeln(join("\n", array_keys($modules)));

            exit(1);
        }

        if ($module->isActive()) {
            $output->writeln("$moduleId already active");
        } else {
            $moduleInstaller->activate($module);
        }
    }
}
