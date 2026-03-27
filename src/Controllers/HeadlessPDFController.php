<?php

namespace Atwx\HeadlessPDF\Controllers;

use DateTime;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Manifest\ModuleResourceLoader;

/**
 * Controller that exposes an endpoint for generating PDFs from URLs.
 */
class HeadlessPDFController extends Controller
{
    private static string $url_segment = 'pdf';

    private static array $allowed_actions = [
        'renderPdfTemplate',
    ];

    /**
     * Generate a PDF for the given URL query parameter and stream it to the browser.
     */
    public function renderPdfTemplate()
    {
        $request = $this->getRequest();

        $hash = $request->getVar('hash');
        $template = $request->getVar('template');
        $className = $request->getVar('className');
        $controller = $request->getVar('controller');
        $variation = $request->getVar('variation');

        if (Environment::getEnv('HEADLESS_PDF_HASH_KEY') && (!$hash || !$template || !self::validateHash($hash, $template))) {
            return $this->httpError(403, 'Invalid hash or no template given');
        }

        if (!$template || !$this->getTemplateEngine()->hasTemplate($template)) {
            return $this->httpError(400, 'No template given or found');
        }

        if ($className && class_exists($className)) {
            $templateObject = $className::get()->byID($request->param('ID'));
        }

        if ($controller && class_exists($controller)) {
            $templateDatalist = $this->getPdfDatalist();
        }

        if ($className || $controller || $variation) {
            return $this->customise([
                "TemplateObject" => $templateObject ?? null,
                "TemplateDatalist" => $templateDatalist ?? null,
                "Variation" => $variation,
            ])->renderWith($template);
        } else {
            return $this->renderWith($template);
        }
    }

    /**
     * Helper method to get datalist through function in given controller
     */
    public function getPdfDatalist()
    {
        $request = $this->getRequest();
        $controller = $request->getVar("controller");
        $controller = $controller::create();
        $controller->setRequest($request);
        if ($controller->hasMethod("getPdfDatalist")) {
            return $controller->getPdfDatalist();
        } else {
            return null;
        }
    }

    /**
     * Secure PDF generation with hashes
     */
    public static function generateHash(string $template): string
    {
        $hashKey = Environment::getEnv('HEADLESS_PDF_HASH_KEY');
        if (!$hashKey) {
            return '';
        }
        return hash_hmac('sha256', $template, $hashKey);
    }

    public static function validateHash(string $hash, string $template): bool
    {
        if (!$hash || !$template) {
            return false;
        }

        $expectedHash = self::generateHash($template);
        return hash_equals($expectedHash, $hash);
    }

    /**
     * Helper method to get image Urls from the given path
     */
    public function getImage($fullPath) {
        if ($fullPath) {
            return ModuleResourceLoader::resourceURL($fullPath);
        }

        return null;
    }

    /**
     * Helper method to render the current date
     */
    public function renderToday()
    {
        return (new DateTime($timezone = "Europe/Berlin"))->format("d.m.Y");
    }
}
