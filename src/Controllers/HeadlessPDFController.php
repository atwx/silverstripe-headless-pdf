<?php

namespace Atwx\HeadlessPDF\Controllers;

use DateTime;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\ORM\DataObject;

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
     * Whether the legacy `hash` parameter (signs only the template) is accepted.
     * It allows rendering any record through a template once a hash for that
     * template is known; new code should use signedUrl() and set this to false.
     */
    private static bool $allow_template_only_hash = true;

    /**
     * Default lifetime of URLs created by signedUrl(), in seconds.
     */
    private static int $signed_url_ttl = 300;

    /**
     * Parameters covered by the signature of signedUrl().
     */
    private const SIGNED_PARAMS = ['template', 'className', 'controller', 'variation', 'expires'];

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

        if (!$template || !$this->getTemplateEngine()->hasTemplate($template)) {
            return $this->httpError(400, 'No template given or found');
        }

        if (!$this->isAuthorised()) {
            return $this->httpError(403, 'Invalid hash');
        }

        if ($className && is_subclass_of($className, DataObject::class)) {
            $templateObject = $className::get()->byID($request->param('ID'));
        }

        if ($controller && is_subclass_of($controller, Controller::class)) {
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
     * Valid full signature (signedUrl()) or, if allowed, a legacy template hash.
     */
    protected function isAuthorised(): bool
    {
        $request = $this->getRequest();
        $signature = $request->getVar('signature');
        if (is_string($signature)) {
            $params = [];
            foreach (self::SIGNED_PARAMS as $name) {
                $params[$name] = (string) $request->getVar($name);
            }
            $params['ID'] = (string) $request->param('ID');
            return (int) $params['expires'] >= time()
                && hash_equals(self::sign($params), $signature);
        }
        $hash = $request->getVar('hash');
        return static::config()->get('allow_template_only_hash')
            && is_string($hash)
            && self::validateHash($hash, (string) $request->getVar('template'));
    }

    /**
     * Absolute, expiring URL for renderPdfTemplate whose signature covers the
     * template, record ID, class, controller and variation.
     *
     * @param string|null $baseURL Base to render against, e.g. an internal host
     *                             reachable by Chrome; defaults to the site URL.
     */
    public static function signedUrl(
        string $template,
        ?int $id = null,
        ?string $className = null,
        ?string $controller = null,
        ?string $variation = null,
        ?int $ttl = null,
        ?string $baseURL = null
    ): string {
        $params = [
            'template' => $template,
            'className' => (string) $className,
            'controller' => (string) $controller,
            'variation' => (string) $variation,
            'expires' => (string) (time() + ($ttl ?? static::config()->get('signed_url_ttl'))),
            'ID' => (string) $id,
        ];
        $query = array_filter(
            array_diff_key($params, ['ID' => true]),
            fn ($value) => $value !== ''
        );
        $query['signature'] = self::sign($params);
        return Controller::join_links(
            $baseURL ?? Director::absoluteBaseURL(),
            'pdf/renderPdfTemplate',
            $id ? (string) $id : null
        ) . '?' . http_build_query($query);
    }

    private static function sign(array $params): string
    {
        $hashKey = Environment::getEnv('HEADLESS_PDF_HASH_KEY');
        if (!$hashKey) {
            throw new \LogicException('HEADLESS_PDF_HASH_KEY is not configured');
        }
        ksort($params);
        return hash_hmac('sha256', http_build_query($params), $hashKey);
    }

    /**
     * Secure PDF generation with hashes (legacy: signs the template only)
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

    /**
     * Helper method to get image Urls from the given path
     */
    public function getImage($fullPath)
    {
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

    /**
     * Helper method to render a given date
     */
    public function renderDate($date = null)
    {
        if ($date) {
            return date("d.m.Y", strtotime($date));
        }

        return null;
    }
}
