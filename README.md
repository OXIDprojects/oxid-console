# OXID Console

OXID Console is a symfony console application for OXID Shop.

The following commands are available:

* `cache:clear` - Clear OXID cache
* `views:update` - Regenerate database views
* `module:generate` - Generate new module scaffold
* `module:fix` - Fixes modules metadata states
* `migration:generate` - Generate new migration file
* `migration:run` - Run migration scripts

For backwords compatibility the following commands are still available (*but are deprecated*):
* `cache:clear` - Clear OXID cache from tmp folder
* `db:update` - Updates database views
* `fix:states` - Fixes modules metadata states
* `g:migration` - Generate new migration file
* `g:module` - Generate new module scaffold
* `list` - *(default)* List of all available commands
* `migrate` - Run migration scripts

## Which version to get?

| OXID Version      | OXID Console version | Source Code link | Download link |
|-------------------|----------------------|------------------|---------------|
| <4.9.0, <5.2.0    | 1.1.5                | [Source Code](https://github.com/OXIDprojects/oxid-console/tree/3e28bba67649c01156c6e97f1b99aa7538b1a32e) | [Download ZIP](https://github.com/OXIDprojects/oxid-console/archive/v1.1.5.zip) |
| \>=4.9.0, >=5.2.0 | 1.2.6                | [Source Code](https://github.com/OXIDprojects/oxid-console/tree/f7dedca4d831bf5cb52e1b17024f2b70cf789b2c) | [Download ZIP](https://github.com/OXIDprojects/oxid-console/archive/v1.2.6.zip) |
| \>=6.0.0          | 5.x.y                | use composer ;-) 

## Installation

add this repository to your composer.json (unless the console is released on packagist.org):
```json
    "oxid-professional-services/oxid-console": {
      "type": "vcs",
      "url": "https://github.com/OXIDprojects/oxid-console"
    },
```

```bash
composer require oxid-professional-services/oxid-console
```

## Getting started

```bash
vendor/bin/oxid list
```

## Defining your own command

* Commands get autoloaded from `[module_path]/Commands/` directory
* Command filename must follow `[your_command]Command.php` format
* Class name must be the same as filename, e.g. `CacheClearCommand`
* Class must extend `Symfony\Component\Console\Command\Command` class

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
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('demo')) {
            $output->writeLn('You typed in --demo or -d also');
        }

        $output->writeLn('My demo command finished');
    }
}
```

For more examples please see https://symfony.com/doc/current/components/console.html

## Migrations

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

## Module state fixer

### Current problem

When you change information of module in metadata you need to reactivate the module for changes to take effect. It is a bad idea for live systems because you might loose data and it bugs out developers to do this all the time by hand.

### Solution

`oxModuleStateFixer` which is an extension of oxModuleInstaller has method `fix()` which will fix all the states.

We have provided you with `module:states` command to work with `oxModuleStateFixer`. Type in `vendor/bin/oxid module:states --help` for more information.
