<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SyndicationController extends AbstractController
{
    #[Route('/rss.php', name: 'app_rss_redirect', priority: 100)]
    public function rssRedirect(Request $request): RedirectResponse
    {
        $qs = $request->server->get('QUERY_STRING', '');
        $url = $this->generateUrl('app_syndication') . ($qs !== '' ? '?' . $qs : '');

        return new RedirectResponse($url);
    }

    #[Route('/syndication.php', name: 'app_syndication', priority: 100)]
    public function __invoke(
        Request $request,
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
    ): Response {
        $fid = $request->query->getInt('fid') ?: null;
        if ($fid !== null && $fid <= 0) {
            $fid = null;
        }

        $threads = $threadRepository->findLatestForRss($fid, 20);

        $items = [];
        $firstPosts = [];
        if (count($threads) > 0) {
            $posts = $postRepository->createQueryBuilder('p')
                ->addSelect('t')
                ->join('p.thread', 't')
                ->where('p.thread IN (:threads)')
                ->setParameter('threads', $threads)
                ->orderBy('p.dateline', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($posts as $post) {
                $tid = $post->getThread()?->getTid();
                if ($tid !== null && !isset($firstPosts[$tid])) {
                    $firstPosts[$tid] = $post;
                }
            }
        }

        foreach ($threads as $thread) {
            $tid = $thread->getTid();
            $firstPost = $firstPosts[$tid] ?? null;
            $description = '';
            $author = $thread->getUsername();
            if ($firstPost !== null) {
                $raw = $firstPost->getMessage();
                $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $plain = strip_tags($decoded);
                $description = mb_substr($plain, 0, 200);
                if (mb_strlen($plain) > 200) {
                    $description .= '...';
                }
            }
            $items[] = [
                'title' => htmlspecialchars($thread->getSubject(), ENT_XML1, 'UTF-8'),
                'link' => $this->generateUrl('app_thread_show', ['tid' => $tid], true),
                'description' => htmlspecialchars($description, ENT_XML1, 'UTF-8'),
                'author' => htmlspecialchars($author, ENT_XML1, 'UTF-8'),
                'pubDate' => gmdate('r', $thread->getDateline()),
            ];
        }

        $channelTitle = 'SymBB';
        if ($fid !== null) {
            $channelTitle .= ' - Forum';
        } else {
            $channelTitle .= ' - Tüm Forumlar';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . htmlspecialchars($channelTitle, ENT_XML1, 'UTF-8') . '</title>' . "\n";
        $xml .= '    <link>' . htmlspecialchars($this->generateUrl('app_board_index', [], true), ENT_XML1, 'UTF-8') . '</link>' . "\n";
        $xml .= '    <description>SymBB Forum RSS</description>' . "\n";
        $xml .= '    <lastBuildDate>' . gmdate('r') . '</lastBuildDate>' . "\n";

        foreach ($items as $item) {
            $xml .= '    <item>' . "\n";
            $xml .= '      <title>' . $item['title'] . '</title>' . "\n";
            $xml .= '      <link>' . $item['link'] . '</link>' . "\n";
            $xml .= '      <description>' . $item['description'] . '</description>' . "\n";
            $xml .= '      <author>' . $item['author'] . '</author>' . "\n";
            $xml .= '      <pubDate>' . $item['pubDate'] . '</pubDate>' . "\n";
            $xml .= '    </item>' . "\n";
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>';

        return new Response($xml, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }
}
