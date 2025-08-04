# RIBAEXPORTER FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## Features

RibaExporter is a module for Dolibarr that allows you to export invoices to RiBa (Ricevuta Bancaria) format, which is an Italian electronic payment system. This module makes it easy to generate RiBa files directly from your Dolibarr invoices for submission to your bank.

Key features include:
- Export one or multiple invoices to RiBa format
- Support for multiple bank accounts
- Mass action for exporting multiple invoices at once
- Direct export button on invoice cards (TODO)
- Automatic generation of properly formatted RiBa files

<!--
![Screenshot ribaexporter](img/screenshot_ribaexporter.png?raw=true "RibaExporter"){imgmd}
-->

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files in the module directories under `langs`.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more information, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->


## Installation

Prerequisites: You must have Dolibarr ERP & CRM software installed. You can download it from [Dolistore.org](https://www.dolibarr.org).
You can also get a ready-to-use instance in the cloud from https://saas.dolibarr.org


### From the ZIP file and GUI interface

If the module is a ready-to-deploy zip file, so with a name `module_xxx-version.zip` (e.g., when downloading it from a marketplace like [Dolistore](https://www.dolistore.com)),
go to menu `Home> Setup> Modules> Deploy external module` and upload the zip file.

<!--

Note: If this screen tells you that there is no "custom" directory, check that your setup is correct:

- In your Dolibarr installation directory, edit the `htdocs/conf/conf.php` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading `//`) and assign the proper value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```
-->

<!--

### From a GIT repository

Clone the repository in `$dolibarr_main_document_root_alt/ribaexporter`

```shell
cd ....../custom
git clone git@github.com:gitlogin/ribaexporter.git ribaexporter
```

-->

### Final steps

Using your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup"> "Modules"
  - You should now be able to find and enable the module

## Configuration

Before using the RibaExporter module, you must configure your bank accounts:

1. Go to Bank/Cash > Bank accounts
2. Edit the bank account you want to use for RiBa exports
3. Go to the "Extra attributes" tab
4. Set the "SIA Code" provided by your bank
5. Save the changes

## Usage

### From an invoice card:
1. Open an invoice that's in "Validated" status
2. Click the "Export RiBa" button in the action buttons area
3. The RiBa file will be generated and downloaded automatically

### From the invoice list:
1. Go to Invoices > Customer invoices
2. Select one or more invoices using the checkboxes
3. From the "Selected records actions" dropdown, choose "Export RiBa"
4. Click "Apply"
5. The RiBa file will be generated and downloaded automatically

Note: If you export multiple invoices linked to different bank accounts, a ZIP file containing multiple RiBa files will be generated.



## Credits

This module uses the following third-party libraries:

### CBI Library
- **Package**: [devcode-it/cbi](https://packagist.org/packages/devcode-it/cbi)
- **Description**: Italian CBI (Corporate Banking Interbancario) format library for RiBa payments
- **Author**: DevCode s.n.c. (info@devcode.it)
- **License**: GPL-3.0
- **Repository**: [github.com/devcode-it/cbi](https://github.com/devcode-it/cbi)

The CBI library provides the core functionality for generating and reading the Italian RiBa (Ricevuta Bancaria) format according to the official CBI standard specifications.

## Licenses

### Main code

GPLv3 or any later version. See file COPYING for more information.

### Documentation

All texts and readme's are licensed under [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).
