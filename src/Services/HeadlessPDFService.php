<?php

namespace ATWX\HeadlessPDF\Services;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Service for generating PDF files using headless Chrome.
 */
class HeadlessPDFService
{
    use Configurable;
    use Injectable;

    /**
     * Path to the Chrome/Chromium binary.
     *
     * @config
     * @var string
     */
    private static string $chrome_binary = '/usr/bin/chromium-browser';

    /**
     * Command-line arguments passed to Chrome.
     *
     * @config
     * @var array
     */
    private static array $chrome_args = [
        '--headless',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-dev-shm-usage',
    ];

    /**
     * Timeout in seconds for the Chrome process.
     *
     * @config
     * @var int
     */
    private static int $timeout = 30;

    /**
     * Generate a PDF from a given URL and return the raw PDF bytes.
     *
     * @param string $url The URL to render as a PDF. Must use http or https scheme.
     * @return string Raw PDF binary content.
     * @throws \InvalidArgumentException If the URL scheme is not allowed.
     * @throws \RuntimeException If PDF generation fails or times out.
     */
    public function generateFromUrl(string $url): string
    {
        $this->validateUrl($url);

        $outputFile = tempnam(sys_get_temp_dir(), 'headless_pdf_') . '.pdf';

        try {
            $binary = $this->config()->get('chrome_binary');
            $args = $this->config()->get('chrome_args');
            $timeout = (int) $this->config()->get('timeout');

            $command = array_merge(
                [$binary],
                $args,
                ['--print-to-pdf=' . $outputFile, $url]
            );

            $escapedCommand = implode(' ', array_map('escapeshellarg', $command));
            $process = proc_open(
                $escapedCommand,
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );

            if (!is_resource($process)) {
                throw new \RuntimeException('Failed to start Chrome process.');
            }

            // Use non-blocking reads to avoid deadlock when output buffers fill up.
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $start = time();
            $stderr = '';

            while (true) {
                $status = proc_get_status($process);

                $stderr .= (string) stream_get_contents($pipes[2]);

                if (!$status['running']) {
                    break;
                }

                if ((time() - $start) >= $timeout) {
                    proc_terminate($process, 9);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    throw new \RuntimeException(
                        sprintf('Chrome process timed out after %d seconds.', $timeout)
                    );
                }

                usleep(100000);
            }

            $exitCode = $status['exitcode'];
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    sprintf('Chrome exited with code %d: %s', $exitCode, $stderr)
                );
            }

            if (!file_exists($outputFile) || filesize($outputFile) === 0) {
                throw new \RuntimeException('Chrome did not produce a PDF output file.');
            }

            return file_get_contents($outputFile);
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    /**
     * Validate that the URL uses an allowed scheme (http or https).
     *
     * @param string $url
     * @throws \InvalidArgumentException
     */
    protected function validateUrl(string $url): void
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'])) {
            throw new \InvalidArgumentException('Invalid URL provided.');
        }

        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException(
                sprintf('URL scheme "%s" is not allowed. Only http and https are permitted.', $parsed['scheme'])
            );
        }
    }
}
