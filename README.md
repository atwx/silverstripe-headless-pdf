# Silverstripe Headless PDF
A Silverstripe module for printing a given html-template into a pdf file using [headless chromium](https://github.com/chrome-php/chrome) in php. This module enables the creation of zugferd compatible e-invoices. For this the additional module [horstoeko/zugferd](https://github.com/horstoeko/zugferd) is required.

## Installation
This module can be installed via Composer with
```
composer require atwx/silverstripe-headless-pdf
```

## Requirements
This module requires the following modules
```
php: "^8.3",
silverstripe/framework: "^6.0",
chrome-php/chrome: "^1.15"
```

## Configuration
The browser-factory will search for the environment variable `CHROME_PATH` when starting. To prevent it from guessing, this variable should be set in your `.env` ([configuration instruction from chrome-php/chrome](https://github.com/chrome-php/chrome?tab=readme-ov-file#using-different-chrome-executable)).

If the browser has trouble rendering the PDF (mostly timeouts), you can try different navigation wait events. This can be set in the `WAITING_MODE` variable in the `.env`. All available events can be found [here](https://github.com/chrome-php/chrome#navigate-to-an-url). For each constant the string version has to be used. In this module the default value is `'networkIdle'`.

This pdf module uses hashes created with the `sha256` algorithm for protecting the html. Therefore you need to set the `HEADLESS_PDF_HASH_KEY` variable in your `.env` otherwise the pdf creation return an 403 http error. Any other authentication method has to be disabled for the `HeadlessPDFController->renderPdfTemplate` function.

Additionally if you are using ddev you can set the `IS_DDEV` variable in your `.env` to `"true"`. By doing this sandboxing is disabled and certificate errors are ignored allowing printing PDFs locally. To use headless chrome in ddev you need the [headless chrome add-on](https://addons.ddev.com/addons/gebruederheitz/ddev-headless-chrome) by gebruederheitz.


## Usage
To print a pdf you need to create a new instance of `HeadlessPDFService` and call its `generatePdf` function. This function takes up to 5 arguments:
- a link for rendering the html-template which will then be printed as a pdf,
- a string containing the filename,
- a bool `getPath` for getting the pdf as a string (default) or as a path to a temporary file,
- optional xml-data for creating zugferd compatible e-invoices (this requires additionally the [horstoeko/zugferd](https://github.com/horstoeko/zugferd) module),
- and an optional array for [php-chrome options](https://github.com/chrome-php/chrome?tab=readme-ov-file#print-as-pdf) changing the default pdf-options defined in the `HeadlessPDFService`.

The link given as first argument needs to point to
```
/pdf/renderPdfTemplate/<optional ID>
```
calling the `HeadlessPDFController` with the following get-parameters:
- `template` containing the full template name/path (called with renderWith($template)),
- `hash` generated with `HeadlessPDFController::generateHash`,
- optional `className` to use an object in the template corresponding to $ID in the link. This object is callable as `$TemplateObject` in the template,
- optional `controller` to use an iterable list of dataobjects in the template callable as `$TemplateDatalist`. This list can be defined in a getPdfDatalist function in the given controller,
- optional `variation` containing a string callable as `Variation` in the template to enable similar pdf versions without the need to create a new template.

For the correct rendering of the pdf received as a string you need to set the two HTTP-headers
```
$this->getResponse()->addHeader('Content-Type', 'application/pdf')
$this->getResponse()->addHeader('Content-Disposition', 'inline; filename="' . <filename> . '.pdf"')
```
