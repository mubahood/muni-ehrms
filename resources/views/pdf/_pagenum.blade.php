{{-- Page numbers. dompdf runs this where it appears, so it must be the last thing in <body>. --}}
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $size = 6.9;
        $width = $fontMetrics->getTextWidth('Page 99 of 99', $font, $size);
        $pdf->page_text($pdf->get_width() - 34 - $width, $pdf->get_height() - 36, $text, $font, $size, [0.46, 0.46, 0.46]);
    }
</script>
