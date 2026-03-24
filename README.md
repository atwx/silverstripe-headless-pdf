# Silverstripe Headless PDF
A Silverstripe module for printing a given html-template into a pdf file using [headless chromium](https://github.com/chrome-php/chrome) in php.

## Installation
This module can be installed via Composer with
```
composer require atwx/silverstripe-headless-pdf
```

## Configuration
This pdf module uses a hash created with `sha256` for protecting the html. For this to work you need to set the `HEADLESS_PDF_HASH_KEY` variable in your `.env` otherwise you will get an 403 HTTP-error.
Additionally if you are using ddev you can set the `IS_DDEV` variable in your `.env` to `"true"`. With this sandboxing is disabled and certificate errors are ignored allowing printing PDFs locally. To use headless chrome in ddev you need the [headless chrome addon](https://addons.ddev.com/addons/gebruederheitz/ddev-headless-chrome) by gebruederheitz.


## Usage
To print a pdf you need to create a new instance of `HeadlessPDFService` and call the `generatePdf` function. This function takes as arguments:
- a link for rendering the html-template,
- a string containing the filename,
- a bool for getting the pdf as a string (default) or as a path to a temporary file,
- optional xml for creating zugferd e-invoices,
- and an array for [php-chrome options](https://github.com/chrome-php/chrome?tab=readme-ov-file#print-as-pdf) different to the default defined in the `HeadlessPDFService`.

The link given as first argument needs to point to
```
/pdf/renderPdfTemplate/$ID
```
calling the `HeadlessPDFController` with get parameters:
- `template` containing the template name (called with renderWith()),
- `hash` generated with `HeadlessPDFController::generateHash`,
- optional `className` to use an object in the template corresponding to $ID in the link. This object is callable as `$TemplateObject` in the template,
- optional `variation` containing a string callable as `Variation` in the template to enable similar pdf versions without the need to create a new template.

For the correct rendering of a pdf received as a string you need to set the two HTTP-headers
```
'Content-Type' to 'application/pdf' and
'Content-Disposition' to 'inline; filename="' . <filename> . '.pdf"'
```
