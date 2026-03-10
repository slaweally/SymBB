<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\UserGroupRepository;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly UserGroupRepository $userGroupRepository,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_name', $this->formatName(...), ['is_safe' => ['html']]),
            new TwigFilter('parse_message', $this->parseMessage(...), ['is_safe' => ['html']]),
        ];
    }

    public function parseMessage(?string $text): Markup
    {
        if ($text === null || $text === '') {
            return new Markup('', 'UTF-8');
        }
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $bbPatterns = [
            '/\[b\](.*?)\[\/b\]/si' => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/si' => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/si' => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/si' => '<del>$1</del>',
            '/\[url=(&quot;|")?(.+?)(&quot;|")?\](.*?)\[\/url\]/si' => '<a href="$2" rel="noopener" target="_blank">$4</a>',
            '/\[url\](.*?)\[\/url\]/si' => '<a href="$1" rel="noopener" target="_blank">$1</a>',
            '/\[img\](.*?)\[\/img\]/si' => '<img src="$1" alt="image" style="max-width:100%;" loading="lazy">',
            '/\[color=(&quot;|")?(.+?)(&quot;|")?\](.*?)\[\/color\]/si' => '<span style="color:$2;">$4</span>',
            '/\[size=(&quot;|")?(.+?)(&quot;|")?\](.*?)\[\/size\]/si' => '<span style="font-size:$2;">$4</span>',
            '/\[quote=(&quot;|\'|")?(.+?)(&quot;|\'|")?\](.*?)\[\/quote\]/si' => '<blockquote class="mybb-quote"><cite>$2 yazdı:</cite>$4</blockquote>',
            '/\[quote\](.*?)\[\/quote\]/si' => '<blockquote class="mybb-quote">$1</blockquote>',
            '/\[code\](.*?)\[\/code\]/si' => '<pre class="mybb-code"><code>$1</code></pre>',
            '/\[list\](.*?)\[\/list\]/si' => '<ul>$1</ul>',
            '/\[\*\](.*?)(?=\[\*\]|\[\/list\]|$)/si' => '<li>$1</li>',
        ];
        $text = preg_replace(array_keys($bbPatterns), array_values($bbPatterns), $text ?? '') ?? $text;

        $parsedown = new \Parsedown();
        $html = $parsedown->text($text);

        return new Markup($html, 'UTF-8');
    }

    public function formatName(?User $user): string
    {
        if ($user === null) {
            return '';
        }

        $gid = $user->getDisplaygroup() > 0 ? $user->getDisplaygroup() : $user->getUsergroup();
        $username = htmlspecialchars($user->getUsername(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $userGroup = $this->userGroupRepository->findByGid($gid);
        $format = '{username}';

        if ($userGroup !== null && str_contains($userGroup->getNamestyle(), '{username}')) {
            $format = $userGroup->getNamestyle();
        }

        return str_replace('{username}', $username, $format);
    }
}
