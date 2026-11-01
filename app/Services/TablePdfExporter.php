<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TablePdfExporter
{
    private const MAXIMUM_ROWS = 500;

    public function __construct(private PdfBranding $pdfBranding) {}

    public function assertQueryWithinLimit(Builder $query, int $additionalRows = 0): int
    {
        $rowCount = (clone $query)->count();

        $this->assertWithinLimit($rowCount + $additionalRows);

        return $rowCount;
    }

    /**
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string|int|float>>}>  $sections
     * @param  array<int, string>  $criteria
     */
    public function download(string $title, string $fileName, array $sections, array $criteria = []): Response
    {
        $rowCount = array_sum(array_map(
            fn (array $section): int => count($section['rows']),
            $sections,
        ));

        $this->assertWithinLimit($rowCount);

        return Pdf::loadView('pdf.table-export', [
            'title' => $title,
            'sections' => $sections,
            'criteria' => $criteria,
            'generatedAt' => now(),
            ...$this->pdfBranding->viewData(),
        ])
            ->setPaper('a4', 'landscape')
            ->download(Str::slug($fileName).'.pdf');
    }

    private function assertWithinLimit(int $rowCount): void
    {
        if ($rowCount > self::MAXIMUM_ROWS) {
            throw ValidationException::withMessages([
                'pdf' => 'Cet export dépasse 500 lignes. Réduisez la période ou ajoutez des filtres, puis exportez à nouveau.',
            ]);
        }
    }
}
