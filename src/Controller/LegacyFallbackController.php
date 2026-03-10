<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegacyFallbackController extends AbstractController
{
    private const string LEGACY_DIR = 'legacy_core';

    #[Route('/{path}', name: 'legacy_fallback', requirements: ['path' => '.*'], defaults: ['path' => ''], priority: -1000)]
    public function __invoke(Request $request, string $path = ''): Response
    {
        $targetFile = $this->resolveTargetFile($path);
        $legacyDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::LEGACY_DIR;
        $absolutePath = $legacyDir . DIRECTORY_SEPARATOR . $targetFile;

        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException(
                sprintf('Legacy file not found: %s', $targetFile)
            );
        }

        $this->populateSuperglobals($request);

        $originalDir = getcwd();
        chdir($legacyDir);

        $statusCode = Response::HTTP_OK;
        $headersBefore = headers_list();

        ob_start();

        try {
            $this->executeLegacyScript($absolutePath);
        } catch (\Throwable $e) {
            ob_end_clean();
            chdir($originalDir);
            restore_error_handler();
            restore_exception_handler();

            throw $e;
        }

        restore_error_handler();
        restore_exception_handler();

        $content = ob_get_clean();
        $headersAfter = headers_list();

        chdir($originalDir);

        $response = new Response((string) $content, $statusCode);
        $this->applyLegacyHeaders($response, $headersBefore, $headersAfter);

        return $response;
    }

    private function resolveTargetFile(string $path): string
    {
        if ($path === '' || $path === '/') {
            return 'index.php';
        }

        $path = ltrim($path, '/');

        if (str_ends_with($path, '.php')) {
            return $path;
        }

        if (is_dir(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::LEGACY_DIR . DIRECTORY_SEPARATOR . $path)) {
            return $path . DIRECTORY_SEPARATOR . 'index.php';
        }

        return $path . '.php';
    }

    /**
     * MyBB dosyasını global scope'ta çalıştırır.
     * MyBB'nin $db, $mybb, $cache vb. nesneleri global olarak oluşturulur
     * ve diğer include edilen dosyalara aktarılabilir.
     */
    private function executeLegacyScript(string $absolutePath): void
    {
        // @phpcs:disable
        global $mybb, $db, $charset, $maintimer, $templates, $theme,
               $plugins, $lang, $forum_cache, $cache, $session,
               $header, $headerinclude, $footer, $groupscache,
               $usertitles, $templatecache, $templatelist,
               $usergroup, $displaygroup, $fcache, $moderatorcache,
               $forumpermissions, $mybbgroups, $parser, $pids,
               $postcounter, $altbg, $thread, $ismod, $attachcache,
               $navbits;
        // @phpcs:enable

        $navbits = $navbits ?? [];
        $templatelist = $templatelist ?? '';

        include $absolutePath;
    }

    private function populateSuperglobals(Request $request): void
    {
        $_GET = $request->query->all();
        $_POST = $request->request->all();
        $_COOKIE = $request->cookies->all();
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
        $_FILES = $request->files->all();

        $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        $_SERVER['QUERY_STRING'] = $request->getQueryString() ?? '';
        $_SERVER['SCRIPT_NAME'] = '/' . ltrim($request->getPathInfo(), '/');
        $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . self::LEGACY_DIR
            . DIRECTORY_SEPARATOR . $this->resolveTargetFile(
                $request->getPathInfo()
            );
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
        $_SERVER['HTTP_HOST'] = $request->getHost();
        $_SERVER['SERVER_NAME'] = $request->getHost();
        $_SERVER['SERVER_PORT'] = $request->getPort();
        $_SERVER['HTTPS'] = $request->isSecure() ? 'on' : 'off';
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::LEGACY_DIR;
    }

    private function applyLegacyHeaders(Response $response, array $before, array $after): void
    {
        $newHeaders = array_diff($after, $before);

        foreach ($newHeaders as $header) {
            $parts = explode(':', $header, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            if (strtolower($name) === 'location') {
                $response->setStatusCode(Response::HTTP_FOUND);
            }

            $response->headers->set($name, $value);
        }
    }
}
