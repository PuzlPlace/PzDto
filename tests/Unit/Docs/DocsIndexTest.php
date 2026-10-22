<?php

declare(strict_types=1);

namespace Puzl\PzDto\Tests\Unit\Docs;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * Testes estruturais do tutorial `docs/index.html`.
 *
 * Garante a integridade da navegação do tutorial publicado no GitHub Pages
 * : toda âncora interna (`href="#..."`) deve resolver para um
 * elemento com o `id` correspondente, e todas as seções obrigatórias devem estar
 * presentes e navegáveis pela sidebar.
 */
final class DocsIndexTest extends TestCase
{
    private const REQUIRED_SECTIONS = [
        'instalacao',
        'heranca',
        'conversao',
        'aninhamento',
        'validacoes',
        'migracao',
        'diferencas',
        'faq',
    ];

    private static function docsPath(): string
    {
        return dirname(__DIR__, 3).'/docs/index.html';
    }

    private static function loadDom(): DOMDocument
    {
        $path = self::docsPath();

        self::assertFileExists($path, 'O tutorial docs/index.html deve existir.');

        $html = file_get_contents($path);
        self::assertNotFalse($html, 'Não foi possível ler docs/index.html.');
        self::assertNotSame('', trim($html), 'docs/index.html não pode estar vazio.');

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // Prefixo de encoding garante a leitura correta do UTF-8 pelo libxml.
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @return array<string>
     */
    private static function collectIds(DOMXPath $xpath): array
    {
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string>
     */
    private static function collectInternalAnchors(DOMXPath $xpath): array
    {
        $targets = [];
        foreach ($xpath->query('//a[@href]') as $node) {
            $href = $node->getAttribute('href');
            if (str_starts_with($href, '#') && $href !== '#') {
                $targets[] = substr($href, 1);
            }
        }

        return $targets;
    }

    public function test_o_tutorial_existe_e_nao_esta_vazio(): void
    {
        $dom = self::loadDom();

        self::assertNotEmpty(
            $dom->getElementsByTagName('section'),
            'O tutorial deve conter ao menos uma <section>.'
        );
    }

    public function test_toda_ancora_interna_resolve_para_um_id(): void
    {
        $dom = self::loadDom();
        $xpath = new DOMXPath($dom);

        $ids = self::collectIds($xpath);
        $anchors = self::collectInternalAnchors($xpath);

        self::assertNotEmpty($anchors, 'A sidebar deve conter âncoras internas (#...).');

        foreach ($anchors as $target) {
            self::assertContains(
                $target,
                $ids,
                "A âncora interna '#{$target}' não possui um elemento com id correspondente (link quebrado)."
            );
        }
    }

    public function test_todas_as_secoes_obrigatorias_estao_presentes(): void
    {
        $dom = self::loadDom();
        $xpath = new DOMXPath($dom);

        $ids = self::collectIds($xpath);

        foreach (self::REQUIRED_SECTIONS as $section) {
            self::assertContains(
                $section,
                $ids,
                "A seção obrigatória '#{$section}' deve existir no tutorial."
            );

            $sectionNodes = $xpath->query("//section[@id='{$section}']");
            self::assertSame(
                1,
                $sectionNodes->length,
                "Deve existir exatamente uma <section id=\"{$section}\">."
            );
        }
    }

    public function test_cada_secao_obrigatoria_e_navegavel_pela_sidebar(): void
    {
        $dom = self::loadDom();
        $xpath = new DOMXPath($dom);

        $anchors = self::collectInternalAnchors($xpath);

        foreach (self::REQUIRED_SECTIONS as $section) {
            self::assertContains(
                $section,
                $anchors,
                "A seção '#{$section}' deve ter um link de navegação na sidebar."
            );
        }
    }

    public function test_documenta_os_nomes_reais_do_pacote(): void
    {
        $html = file_get_contents(self::docsPath());
        self::assertNotFalse($html);

        self::assertStringContainsString('composer require puzl/pzdto:^1.0', $html);
        self::assertStringContainsString('https://github.com/PuzlPlace/PzDto.git', $html);
        self::assertStringContainsString('Puzl\\PzDto\\Attributes\\Validation', $html);
    }
}
