<?php

namespace ATWX\HeadlessPDF\Controllers;

use ATWX\HeadlessPDF\Services\HeadlessPDFService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Controller that exposes an endpoint for generating PDFs from URLs.
 */
class HeadlessPDFController extends Controller
{
    private static string $url_segment = 'headless-pdf';

    private static array $allowed_actions = [
        'generate',
    ];

    /**
     * Generate a PDF for the given URL query parameter and stream it to the browser.
     */
    public function generate(HTTPRequest $request): HTTPResponse
    {
        $url = $request->getVar('url');

        if (!$url) {
            return $this->httpError(400, 'Missing required "url" parameter.');
        }

        try {
            $pdfContent = HeadlessPDFService::create()->generateFromUrl($url);
        } catch (\InvalidArgumentException $e) {
            return $this->httpError(400, $e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->httpError(500, 'PDF generation failed.');
        }

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/pdf');
        $response->addHeader('Content-Disposition', 'inline; filename="document.pdf"');
        $response->setBody($pdfContent);

        return $response;
    }
}
