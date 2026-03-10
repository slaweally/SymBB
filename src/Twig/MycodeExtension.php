<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

final class MycodeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('mycode_parse', $this->parse(...), ['is_safe' => ['html']]),
            new TwigFilter('html_decode', $this->decode(...), ['is_safe' => ['html']]),
        ];
    }

    public function decode(string $text): Markup
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // İkinci kez decode ederek (double-escape durumunu çozmek için)
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return new Markup($decoded, 'UTF-8');
    }

    public function parse(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $patterns = [
            // [b]bold[/b]
            '/\[b\](.*?)\[\/b\]/si' => '<strong>$1</strong>',
            // [i]italic[/i]
            '/\[i\](.*?)\[\/i\]/si' => '<em>$1</em>',
            // [u]underline[/u]
            '/\[u\](.*?)\[\/u\]/si' => '<u>$1</u>',
            // [s]strikethrough[/s]
            '/\[s\](.*?)\[\/s\]/si' => '<del>$1</del>',
            // [url=http://...]text[/url]
            '/\[url=(&quot;|")?(.+?)(&quot;|")?\](.*?)\[\/url\]/si' => '<a href="$2" rel="noopener" target="_blank">$4</a>',
            // [url]http://...[/url]
            '/\[url\](.*?)\[\/url\]/si' => '<a href="$1" rel="noopener" target="_blank">$1</a>',
            // [img]...[/img]
            '/\[img\](.*?)\[\/img\]/si' => '<img src="$1" alt="image" style="max-width:100%;" loading="lazy">',
            // [color=X]...[/color]
            '/\[color=(&quot;|")?(.+?)(&quot;|")?\](.*?)\[\/color\]/si' => '<span style="color:$2;">$4</span>',
            // [size=X]...[/size]
            '/\[size=(&quot;|")?(.+?)(&quot;|")?\](.*?)\[\/size\]/si' => '<span style="font-size:$2;">$4</span>',
            // [quote]...[/quote]
            '/\[quote\](.*?)\[\/quote\]/si' => '<blockquote class="mybb-quote">$1</blockquote>',
            // [quote='User']...[/quote]
            '/\[quote=(&quot;|\'|")?(.+?)(&quot;|\'|")?\](.*?)\[\/quote\]/si' => '<blockquote class="mybb-quote"><cite>$2 yazdı:</cite>$4</blockquote>',
            // [code]...[/code]
            '/\[code\](.*?)\[\/code\]/si' => '<pre class="mybb-code"><code>$1</code></pre>',
            // [list]...[/list] with [*]
            '/\[list\](.*?)\[\/list\]/si' => '<ul>$1</ul>',
            '/\[\*\](.*?)(?=\[\*\]|\[\/list\]|$)/si' => '<li>$1</li>',
        ];

        $text = preg_replace(array_keys($patterns), array_values($patterns), $text) ?? $text;

        // Newlines
        $text = nl2br($text);

        return $text;
    }
}
