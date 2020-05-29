# OXID PS Console

OXID PS Console is a Symfony console application for OXID eShop.
It is community and project driven with write and read access like in a public wiki (like Wikipedia).



The following commands are available:

* `cache:clear` - Clear OXID cache
* `views:update` - Regenerate database views
* `module:activate` - Activate module in shop
* `module:generate` - Generate new module scaffold
* `module:fix` - Fix the module chain based on the metadata contents
* `migration:generate` - Generate new migration file
* `migration:run` - Run migration scripts

For backwards compatibility the following commands are still available (*but are deprecated*):
* `db:update` - Updates database views
* `g:migration` - Generate new migration file
* `g:module` - Generate new module scaffold
* `list` - *(default)* List of all available commands
* `migrate` - Run migration scripts

## Which version to get?

| OXID Version      | OXID Console version | Source Code link | Download link |
|-------------------|----------------------|------------------|---------------|
| <4.9.0, <5.2.0    | 1.1.5                | [Source Code](https://github.com/OXIDprojects/oxid-console/tree/3e28bba67649c01156c6e97f1b99aa7538b1a32e) | [Download ZIP](https://github.com/OXIDprojects/oxid-console/archive/v1.1.5.zip) |
| \>=4.9.0, >=5.2.0 | 1.2.6                | [Source Code](https://github.com/OXIDprojects/oxid-console/tree/f7dedca4d831bf5cb52e1b17024f2b70cf789b2c) | [Download ZIP](https://github.com/OXIDprojects/oxid-console/archive/v1.2.6.zip) |
| =6.1.x            | 6.0                  | [Source Code](https://github.com/OXIDprojects/oxid-console/)|please use composer to install see next section| 
| =6.2.x            | -                    | use with care oxid console is not designed to be used with oxid 6.2 for now| 

## Installation
Use Composer to add the console to your project
```bash
composer require oxid-professional-services/oxid-console
```

## Getting started

```bash
vendor/bin/oxid list
```

## Defining your own command
* Class must extend `Symfony\Component\Console\Command\Command` class
* Add the following in the services.yaml json of your module (composer package) 
```yaml 
  services:
    oxid_community.moduleinternals.module.fix.command:
      class: OxidCommunity\ModuleInternals\Command\ModuleFixCommand
      tags:
      - { name: 'console.command' }
```

### Template for your command:

```php
<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * My own command
 *
 * Demo command for learning
 */
class MyOwnCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('my:own');
        $this->setDescription('Demo command for learning');
        $this->addOption('demo', 'd', InputOption::VALUE_NONE, 'run demo');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('demo')) {
            $output->writeln('You typed in --demo or -d also');
        }

        $output->writeln('My demo command finished');
    }
}
```

For more examples please see https://symfony.com/doc/current/components/console.html

## Migrations

*Warning* current implementation does not trigger the oxid core migration "oe-eshop-doctrine_migration"

OXID Console project includes migration handling. Lets generate sample migration by running `vendor/bin/oxid migration:generate "add amount field to demo module"`.

Console application generated `migration/20140312161434_addamountfieldtodemomodule.php` file with its contents:

```php
<?php

class AddAmountFieldToDemoModuleMigration extends oxMigrationQuery
{

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        // TODO: Implement up() method.
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        // TODO: Implement down() method.
    }
}
```

Migration handler can run migrations with your given timestamp *(if no timestamp provided than it assumes timestamp as current timestamp)*. Inside it saves which migration queries were executed and knows which migration queries go up or go down.

Once we generated this file we run `vendor/bin/oxid migration:run`

```
Running migration scripts
[DEBUG] Migrating up 20140312161434 addamountfieldtodemomodulemigration
Migration finished successfully
```

Now lets run the same command a second time

```
Running migration scripts
Migration finished successfully
```

*Note: No migration scripts were ran*

Ok, now lets run migrations with given timestamp of the past with `vendor/bin/oxid migration:run 2013-01-01` command

```
Running migration scripts
[DEBUG] Migrating down 20140312161434 addamountfieldtodemomodulemigration
Migration finished successfully
```

It ran our migration query down because on given timestamp we should not have had executed that migration query.

### Example

Here is a quick example of migration query which adds a column to oxuser table

```php
<?php
// FILE: 20140414085723_adddemoculumntooxuser.php

class AddDemoCulumnToOxUserMigration extends oxMigrationQuery
{

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if ($this->_columnExists('oxuser', 'OXDEMO')) {
            return;
        }

        $sSql = "ALTER TABLE  `oxuser`
                 ADD  `OXDEMO`
                    CHAR( 32 )
                    CHARACTER SET utf8
                    COLLATE utf8_general_ci
                    NULL
                    DEFAULT NULL
                    COMMENT  'Demo field for migration'";

        oxDb::getDb()->execute($sSql);
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        if (!$this->_columnExists('oxuser', 'OXDEMO')) {
            return;
        }

        oxDb::getDb()->execute('ALTER TABLE `oxuser` DROP `OXDEMO`');
    }
}
```

### Migration Query Law

* Filename must follow `YYYYMMDDHHiiss_description.php` format
* Must extend `oxMigrationQuery` abstract class
* Class name must be the same as description with *Migration* word appended to the end of the name

*Note: It is better to use generator for migration queries creation*


# Related Projects
* https://github.com/OXIDprojects/oxid-module-internals
* https://github.com/OXIDprojects/oxid_modules_config
