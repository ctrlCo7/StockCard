<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/** Renders A4 PDFs with "Page X of Y" stamped in the footer. */
class PdfRenderer
{
    public static function make(string $view, array $data, bool $landscape = false): DomPdf
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', $landscape ? 'landscape' : 'portrait');
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 7.5, [0.35, 0.35, 0.35]);

        return $pdf;
    }
}
