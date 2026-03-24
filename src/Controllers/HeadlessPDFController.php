<?php

namespace Atwx\HeadlessPDF\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Environment;

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
        $variation = $request->getVar('variation');

        if (!$hash || !$template || !self::validateHash($hash, $template)) {
            return $this->httpError(403, 'Invalid hash');
        }

        if ($className || $variation) {
            return $this->customise([
                "TemplateObject" => $className::get()->byID($request->param('ID')),
                "Variation" => $variation,
            ])->renderWith($template);
        } else {
            return $this->renderWith($template);
        }
    }

    /**
     * Secure PDF generation with hashes
     */
    public static function generateHash(string $template): string
    {
        $hashKey = Environment::getEnv('HEADLESS_PDF_HASH_KEY');
        if (!$hashKey) {
            return Controller::curr()->httpError(403, 'Hash key not configured');
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

}
