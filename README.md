# melis-composerdeploy

MelisComposerDeploy lets the Melis Platform run Composer from inside the application itself, to install, update or remove modules programmatically. It is the engine behind the back-office Modules tool / marketplace and the platform installer, wrapping the `composer/composer` library so module management does not require a terminal.

## Getting Started

### Prerequisites

MelisComposerDeploy is a low-level, standalone tool - it depends only on `composer/composer`, not on `melis-core`. It is driven by MelisCore's Modules tool / marketplace and by the installer, and typically runs MelisDbDeploy afterwards so a newly installed module's database migrations are applied.

### Installing

Run the composer command:
```
composer require melisplatform/melis-composerdeploy
```

## Running the code

You do not use MelisComposerDeploy directly - it runs behind the back-office Modules tool / marketplace whenever you install, update, or remove a module.

## Authors

* **Melis Technology** - [www.melisplatform.com](https://www.melisplatform.com/)

See also the list of [contributors](https://github.com/melisplatform/melis-composerdeploy/contributors) who participated in this project.


## License

This project is licensed under the OSL-3.0 License - see the [LICENSE.md](LICENSE.md) file for details
