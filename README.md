# Composer Tools Installer aka Cotor

## How to use cotor

You can install any composer based dev tool with cotor. It will install each package in a `tools` folder in your current working directory.
For each tool a standalone folder named by the package without the vendor will be created.

### Commands

* **list**: Lists all available commands.
* **install**: Installs all tools and extensions listed in your composer.json at `extra.cotor`.
* **install $name**: Installs a new tool. `$name` must be a tool's composer or registered shortcut name.
* **update $name**: Updates an installed tool. `$name` must be a tool's name without vendor or registered shortcut name.
* **update-all**: Updates all installed tools.
* **outdated**: Lists all tools and checks if they are up-to-date.
* **extend $name $extension**: Installs a tool extension. `$name` must be a tool's composer or registered shortcut name. `$extension` must be the composer name of the extension.

## How to build cotor

1. Ensure you've got `box` installed in your `$PATH`.
2. Run `box compile` to create a new `cotor.phar`.

`box` takes the latest git tag or hash to propagate this as the PHARs version. So be sure to hava a proper git history before running the command.
