<?php namespace AmoriumCms\Shop\Classes\Import;

use Symfony\Component\Process\Process;

/**
 * RemoteGet downloads a public http address. Some storefronts reject PHP's HTTP client,
 * so a blocked response is tried again with the curl program.
 */
class RemoteGet
{
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    /**
     * fetch
     */
    public function fetch(string $url, string $accept = '*/*'): RemoteResponse
    {
        $this->assertUrl($url);

        $response = $this->viaExtension($url, $accept);

        if ($response->status === 0 || $response->status === 403) {
            $response = $this->viaProgram($url, $accept);
        }

        return $response;
    }

    /**
     * viaExtension
     */
    protected function viaExtension(string $url, string $accept): RemoteResponse
    {
        if (!function_exists('curl_init')) {
            return new RemoteResponse(0, '', '');
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: ' . $accept,
                'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8',
            ],
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        return new RemoteResponse($status, $body === false ? '' : $body, $this->mediaType($type));
    }

    /**
     * viaProgram shells out to curl when the storefront blocks PHP
     */
    protected function viaProgram(string $url, string $accept): RemoteResponse
    {
        $binary = $this->program();
        $bodyFile = tempnam(sys_get_temp_dir(), 'shopb');
        $headerFile = tempnam(sys_get_temp_dir(), 'shoph');

        if ($bodyFile === false || $headerFile === false) {
            throw new ImportException('A temporary file for the download could not be created.');
        }

        try {
            $process = new Process([
                $binary,
                '-sS',
                '-L',
                '--max-time', '30',
                '-A', self::USER_AGENT,
                '-H', 'Accept: ' . $accept,
                '-H', 'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8',
                '-D', $headerFile,
                '-o', $bodyFile,
                $url,
            ]);
            $process->setTimeout(40);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ImportException('The download failed: ' . trim($process->getErrorOutput()));
            }

            $headers = (string) file_get_contents($headerFile);

            return new RemoteResponse(
                $this->status($headers),
                (string) file_get_contents($bodyFile),
                $this->mediaType($this->header($headers, 'Content-Type'))
            );
        } finally {
            @unlink($bodyFile);
            @unlink($headerFile);
        }
    }

    /**
     * program
     */
    protected function program(): string
    {
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['C:\\Windows\\System32\\curl.exe', 'curl.exe', 'curl']
            : ['curl'];

        foreach ($candidates as $candidate) {
            if ($candidate === 'curl' || $candidate === 'curl.exe' || is_file($candidate)) {
                return $candidate;
            }
        }

        throw new ImportException('The product page was blocked and the curl program was not found.');
    }

    /**
     * assertUrl
     */
    protected function assertUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ImportException('Only http links can be downloaded.');
        }
    }

    /**
     * status reads the last status line, which is the one after redirects
     */
    protected function status(string $headers): int
    {
        preg_match_all('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/m', $headers, $matches);

        return (int) (end($matches[1]) ?: 0);
    }

    /**
     * header
     */
    protected function header(string $headers, string $name): string
    {
        if (!preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $headers, $match)) {
            return '';
        }

        return trim($match[1]);
    }

    /**
     * mediaType drops parameters such as charset
     */
    protected function mediaType(string $header): string
    {
        return strtolower(trim(strtok($header, ';')));
    }
}
