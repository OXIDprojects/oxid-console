# Changelog for OXID Console

## [v6.0.1]

### Fixed
- bin/oxid can be called from within the vendor directory

## [v6.0.0]
improve support with new oxid versions 6.2 and force commands to be written in compatible way to support other consoles
This console may become deprecated soon. You may want to update your oxid console to this release as intermediate step as it will warn you about compatibillity issues while still supporting your legacy comands. 

### Added
deprecation warnings for modules defined in way that is incompatible with oe console

### Fixed
- (6.0.0-beta10) Uncaught Error: Undefined class constant 'REGEXP_TIMESTAMP' AbstractQuery.php https://github.com/OXIDprojects/oxid-console/pull/30
- (6.0.0-beta9) some fixes (commands with namespaces but legacy definition)

### Changed
- (6.0.0-beta2) compatibillity with oxid 6.2 
- (6.0.0-beta4) deprecated warnings for modules using composer extra settings for command registration
- (6.0.0-beta4) deprecated warning for modules using no registration via services.yaml
- (6.0.0-beta4) deprecated warning for using shop config (for console compatibility)

### Removed
- cache clear command does not support -s for clearing smarty cache because -s is used for the shop id
- remove fix: states command - install module internals 2.x to install the command
- (6.0.0-beta4) no filesystem based command loading for modules with services.yaml

## [v5.3.0]
### Changed
- Console runs even if shop has a broken configuration
- Version is read by composers installed.json

## [v5.2.0]
### Added
- Support for commands registered via services.yaml of other composer packages  

## [v5.1.0]
### Added
- Support for commands registered via composer.json of other composer packages  

## [v5.0.29]
### Changed
- Fix error during migrations which where using _columnExists

## [v5.0.28]
### Changed
-  fix:states command: modules without meta data version can be fixed. 

## [v5.0.27]
### Changed
- fix:states command: preparation for being able to handle modules without metadataversion

## [v5.0.26]
### Changed
 -update modules lists before get commands from modules
 
## [v5.0.25]
### Changed
- removed php warnings (refactored code)

## [v5.0.24]
### Changed
- use normal Error stacktrace because debug output is usually to long in case of error 

## [v5.0.23]
### Added
- finding special kind of global garbage that can be caused by modules using namespaces 

## [v5.0.22]
### Fixed
- output when fixing module extensions
- finding special kind of garbage if module extension was renamed

## [v5.0.21]
### Fixed
- fixed moduel:fix command in case module events where removed

## [v5.0.20]
### Fixed
- fixed moduel:fix command in case module garbage was found

## [v5.0.19]
### Fixed
- fixed moduel:fix command

## [v5.0.18]
** WARNING known crash during fix:states **
### Changed
- minor performance improvement (when saving bool values)

## [v5.0.17]
### Changed
- better performance of shop config by avoiding unnecessary writes for modules config (if it stays the same)

## [v5.0.16]
### Changed
- more verbose when changing things,
- better performance by smart cache clear 

## [v5.0.15]
### Fixed
- fix fixing modules with camecase files definition

## [v5.0.14]
### Fixed
- fix fixing of modules with same prefix in the directory name
- fix debug output

## [v5.0.13]
### Changed
- added exception handling for broken commands, if a command can not be added to console there will be some output
- searching for command will (try to) avoid to instantiate command's intermediate parent class   

## [v5.0.12]
### Fixed
- fix warning during migrate command caused by missing http host variable in CLI
- refactored getting version from composer

## [v5.0.11]
### Fixed
- fix error during module:fix command (by setting debug output)

## [v5.0.10]
### Fixed
- fix exception during fix states caused when there is a new module

## [v5.0.10]
### Changed
- improve installation section in README


## [v5.0.8]
### Changed
- remove version from composer file (reading composer.lock)
## [v5.0.7]
### Fixed
- fix setting module version during fix states
### Changed
- better error output for duplicate controllers exception

## [v5.0.6]
### Changed
- better performance for fixing modules

## [v5.0.5]
### Fixed
- fixed alias for oxConsoleCommand to be alias of Command::class

## [v5.0.4]
### Changed
- use version number from composer instead of hardcoded value 

## [v5.0.3]
### Changed
** This would be a BC break, but as v5 was not yet finally released I stay with v5.0.x
- new command names views:update and module:fix 

## [v5.0.2]

## [v5.0.1]

## [v5.0.0]
- Support for OXID eShop V6
- commands inside of oxid modules must be placed in commands sub directory of modules
(BC break there is recursive full scan anymore because of performance reasons)
- module tpl files get stored with a unique id and will not be deleted during fix state
- module versions number will not get deleted during fix states
- avoid duplicate controllers errors
- prepare refactoring of version reading

#v2 - v4 (skipped, unrelased or project specific implementations)  

## [v1.2.6] - 2017-09-21
* (9a94f32) Support camel cased command file names
* (80500c3) Set SMARTY_PHP_PASSTHRU for generate commands

## [v1.2.5] - 2016-11-25
* (7b25ce7) Fix for table exists method of migration query for not accounting for a database

## [v1.2.4] - 2016-07-21
* (a80093e) Disable view usage when updating views

## [v1.2.3] - 2015-12-14
* (9fe1282) Bugfix for oxid executable not being able to be called from other directories
* (1272eee) Read prompt from STDIN
* (b7073f0) Output generated migration filename
* (42d0c09) Bugfix for fix:states not recognising newly created modules
* (b6ddb5b) Remove redundant type protection within fix:states
* (c3a0e3a) Clear oxCache if available via cache:clear command

## [v1.2.2] - 2015-06-07
* (c49ecb0) Bugfix for fix:states not working for all shops

## [v1.2.1] - 2015-02-10
* Changed LICENSE to MIT and modified file headers
* (ca40799) Better input description for g:module command

## [v1.1.5] - 2015-02-10
* Change LICENSE to MIT and modify file headers

## [v1.2.0] - 2014-11-17
* Migration status are stored in database right now
* (aab4e32) Dropping module_enabled_count() feature
* (bce0279) Use events class for generated modules
* (fc02b9f) Modify the behavior of fixing module states
* (96cb16f) Remove console:update command in favor of deprecation
* (456706f) Remove module:list command

## [v1.1.4] - 2014-10-27
* (af63d2c) Deprecate console:update command

## [v1.1.3] - 2014-10-22
* (be06157) Modify source code to new OXID standards
* (cff5a53) Bugfix for migration filename pattern not working with numbers

## [v1.1.2] - 2014-06-19
* (de92637) Add recursive flag on mkdir in createMissingFolders() method of generate module command
* feature #7 (1b7fa80) Generate translation files with module scaffold
* (6b984f2) Update version number to 0.0.1-DEV on module scaffold
* (ed9a9ac) Removed unnecessary check for output interface on migration command
* bug fix #5 and feature #9 (9306e0e) Implement oxNullOutput for debug ignoring

## [v1.1.1] - 2014-05-06
* feature #4 (7aab7b6) _tableExists() method in oxMigrationQuery
* bug fix #3 (b224139) No more PHP Warning if no modules are active

## [v1.1.0] - 2014-04-15
* (9b923fe) Initial Update Manager
* (fe5d867) Catch exceptions on getLatestVersion() in Update Manager
* (f3f46d6) Clearing cache command able to delete directories too

## [v1.0.1] - 2014-04-14
* feature #2 (77d001f) Add oxMigrationQuery::_columnExists()
* (1f5d892) Modify PHP file headers licence info
* documentation #1 (e8b20b0) Add an example of migration query

[Unreleased]: https://github.com/OXIDprojects/oxid-console/compare/v1.2.6...quick-port-6.0-wip
[v1.2.6]: https://github.com/OXIDprojects/oxid-console/compare/v1.2.5...v1.2.6
[v1.2.5]: https://github.com/OXIDprojects/oxid-console/compare/v1.2.4...v1.2.5
[v1.2.4]: https://github.com/OXIDprojects/oxid-console/compare/v1.2.3...v1.2.4
[v1.2.3]: https://github.com/OXIDprojects/oxid-console/compare/v1.2.2...v1.2.3
[v1.2.2]: https://github.com/OXIDprojects/oxid-console/compare/v1.2.1...v1.2.2
[v1.2.1]: https://github.com/OXIDprojects/oxid-console/compare/v1.2.0...v1.2.1
[v1.2.0]: https://github.com/OXIDprojects/oxid-console/compare/v1.1.4...v1.2.0
[v1.1.5]: https://github.com/OXIDprojects/oxid-console/compare/v1.1.4...v1.1.5
[v1.1.4]: https://github.com/OXIDprojects/oxid-console/compare/v1.1.3...v1.1.4
[v1.1.3]: https://github.com/OXIDprojects/oxid-console/compare/v1.1.2...v1.1.3
[v1.1.2]: https://github.com/OXIDprojects/oxid-console/compare/v1.1.1...v1.1.2
[v1.1.1]: https://github.com/OXIDprojects/oxid-console/compare/v1.1.0...v1.1.1
[v1.1.0]: https://github.com/OXIDprojects/oxid-console/compare/v1.0.1...v1.1.0
[v1.0.1]: https://github.com/OXIDprojects/oxid-console/compare/v1.0.0...v1.0.1
