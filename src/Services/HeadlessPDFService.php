<?php

namespace Atwx\HeadlessPDF\Services;

use Exception;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

/**
 * Service for generating PDF files using headless Chrome.
 */
class HeadlessPDFService
{
    use Injectable;

    public function generatePdf(string $link, string $filename, bool $getPath = false, $zugferdXml = null, array|null $options = [])
    {
        if (!$link) {
            return null;
        }

        $chromePath = Environment::getEnv('CHROME_PATH');
        $browserFactory = new BrowserFactory($chromePath ?: null);

        $isDDev = Environment::getEnv('IS_DDEV') === 'true';

        $browser = $browserFactory->createBrowser([
            'startupTimeout' => 600,
            'noSandbox' => $isDDev,
            'ignoreCertificateErrors' => $isDDev,
        ]);

        try {
            $page = $browser->createPage();

            $waitingMode = Environment::getEnv('WAITING_MODE') ?: 'networkIdle';
            $page->navigate($link)->waitForNavigation($waitingMode);

            $defaultOptions = [
                'landscape' => false,
                'printBackground' => true,
                'paperWidth' => 8.27,
                'paperHeight' => 11.69,
                'marginTop' => 0.2,
                'marginBottom' => 0.2,
                'marginLeft' => 0.6,
                'marginRight' => 0.6,
                'scale' => 1.0,
            ];

            $pdfOptions = array_merge($defaultOptions, $options);
            $pdf = $page->pdf($pdfOptions);

            $tempPath = sys_get_temp_dir() . '/' . $filename . '.pdf';
            $pdf->saveToFile($tempPath);

            if ($zugferdXml) {
                if (!class_exists(\horstoeko\zugferd\ZugferdDocumentPdfMerger::class)) {
                    throw new Exception("ZugferdDocumentPdfMerger class not found. Please make sure the horstoeko/zugferd library is installed.");
                } else {
                    $pdfMerger = new \horstoeko\zugferd\ZugferdDocumentPdfMerger($zugferdXml, $tempPath);
                    $pdfMerger->generateDocument()->saveDocument($tempPath);
                }
            }

            if ($getPath) {
                return $tempPath;
            } else {
                $pdfContent = file_get_contents($tempPath);
                unlink($tempPath);
                return $pdfContent;
            }
        } finally {
            $browser->close();
        }
    }
}
