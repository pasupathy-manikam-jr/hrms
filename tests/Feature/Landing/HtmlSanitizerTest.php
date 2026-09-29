<?php

namespace Tests\Feature\Landing;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function cases(): array
    {
        return [
            'demo formatting is kept' => ['About <b>smart</b>.<br>x &bull; y', 'About <b>smart</b>.<br>x • y'],
            'allowed tags survive' => ['<h2>T</h2><p><strong>s</strong> <em>e</em> <i>i</i></p><ul><li>1</li></ul><ol><li>2</li></ol><h3>U</h3>', '<h2>T</h2><p><strong>s</strong> <em>e</em> <i>i</i></p><ul><li>1</li></ul><ol><li>2</li></ol><h3>U</h3>'],
            'script and style are removed with contents' => ['a<script>alert(1)</script><style>p{}</style>b', 'ab'],
            'event handlers and styles are stripped' => ['<p onclick="x()" style="color:red" class="c">Hi</p>', '<p>Hi</p>'],
            'unknown tags are unwrapped' => ['<div><span>keep <img src=x onerror=alert(1)> text</span></div>', 'keep  text'],
            'javascript links lose href' => ['<a href="javascript:alert(1)">j</a>', '<a>j</a>'],
            'data links lose href' => ['<a href=" data:text/html,x">d</a>', '<a>d</a>'],
            'protocol-relative links lose href' => ['<a href="//evil.test">p</a>', '<a>p</a>'],
            'http, https and relative links keep href only' => ['<a href="https://x.test" target="_blank">h</a><a href="/page/faq" onclick="x">r</a><a href="HTTP://y.test">u</a>', '<a href="https://x.test">h</a><a href="/page/faq">r</a><a href="HTTP://y.test">u</a>'],
            'comments are dropped' => ['a<!-- secret -->b', 'ab'],
            'nested dropped tags inside allowed ones' => ['<p>x<iframe src="https://evil"></iframe><svg><script>1</script></svg>y</p>', '<p>xy</p>'],
            'text is escaped' => ['a < b & c', 'a &lt; b &amp; c'],
            'utf-8 is preserved' => ['ünï — ✓', 'ünï — ✓'],
            'empty' => ['  ', ''],
        ];
    }

    #[DataProvider('cases')]
    public function test_clean(string $html, string $expected): void
    {
        $this->assertSame($expected, HtmlSanitizer::clean($html));
    }
}
