<?php
declare(strict_types=1);

namespace App\Artifacts;

use Dompdf\Dompdf;
use Dompdf\Options;

final class ArtifactPdfService
{
    public function __construct(private readonly ?string $autoloadPath = null)
    {
    }

    public function htmlToPdf(string $html): string
    {
        $this->loadComposerAutoload();
        if (!class_exists(Dompdf::class)) {
            throw new \RuntimeException('Dompdf não está disponível no projeto.');
        }
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        if ($pdf === '') {
            throw new \RuntimeException('Falha ao gerar PDF.');
        }
        return $pdf;
    }

    public function fromPayload(string $contentType, string $payload, ArtifactRenderService $renderer, string $title, array $meta = []): string
    {
        if ($contentType === 'pdf_base64') {
            $pdf = base64_decode($payload, true);
            if ($pdf === false || $pdf === '') {
                throw new \RuntimeException('Falha ao decodificar pdf_base64.');
            }
            return $pdf;
        }
        $html = $renderer->toHtml($contentType, $payload, $title, $meta);
        return $this->htmlToPdf($html);
    }

    private function loadComposerAutoload(): void
    {
        $candidates = array_filter([
            $this->autoloadPath,
            dirname(__DIR__, 3) . '/vendor/autoload.php',
            dirname(__DIR__, 4) . '/vendor/autoload.php',
        ]);
        foreach ($candidates as $file) {
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
}
