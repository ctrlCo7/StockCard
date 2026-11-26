{{-- Shared styles for printable official forms and reports (browser print + dompdf). --}}
<style>
    @page { size: A4; margin: 14mm 12mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: 10.5px; color: #111; margin: 0; }
    .doc { width: 100%; }
    table { border-collapse: collapse; width: 100%; }
    .hdr-table td { padding: 3px 4px; vertical-align: bottom; }
    .hdr-table .entity { text-align: center; font-weight: bold; font-size: 11px; padding-bottom: 2px; }
    .hdr-table .entity .sub { font-weight: normal; font-size: 10px; }
    .hdr-table .doc-title { text-align: center; font-weight: bold; font-size: 15px; letter-spacing: 3px; padding: 6px 0 10px; }
    .hdr-table .lbl { width: 14%; white-space: nowrap; font-weight: bold; }
    .hdr-table .line { border-bottom: 1px solid #333; width: 36%; }
    .grid { margin-top: 10px; }
    .grid th, .grid td { border: 1px solid #333; padding: 4px 5px; }
    .grid th { background: #e9edf3; font-weight: bold; text-align: center; font-size: 10px; }
    .grid thead { display: table-header-group; }
    .grid tr { page-break-inside: avoid; }
    .num { text-align: right; }
    .center { text-align: center; }
    .right { text-align: right; }
    .muted { color: #555; }
    .foot { margin-top: 8px; font-size: 9.5px; }
    .foot td { padding: 2px 0; }
    .box-table td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
    .box-table .lbl { font-weight: bold; white-space: nowrap; }
    .sig td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; height: 22px; }
    .sig .head { font-weight: bold; text-align: center; background: #e9edf3; }
    .sig .rowlbl { font-weight: bold; width: 16%; white-space: nowrap; }
    .report-meta { font-size: 9.5px; color: #333; margin: 2px 0 8px; }
    .summary td { padding: 2px 10px 2px 0; font-size: 10px; }
    .watermark { font-size: 9px; color: #777; margin-top: 4px; }
</style>
