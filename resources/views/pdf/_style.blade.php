{{-- Shared PDF styles. dompdf supports CSS 2.1: layout is done with tables. --}}
<style>
    @page { margin: 122px 34px 62px 34px; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { color: #1a1a1a; font-size: 8.4pt; line-height: 1.38; }
    .band { position: fixed; top: -122px; left: -34px; right: -34px; height: 5px; background: #800000; }
    .lh { position: fixed; top: -106px; left: 0; right: 0; height: 86px; }
    .lh table { width: 100%; border-collapse: collapse; }
    .lh td { vertical-align: top; padding: 0; }
    .lh .crest { width: 60px; }
    .lh .crest img { width: 52px; height: 56px; }
    .lh .inst { font-family: 'DejaVu Serif', serif; font-size: 15.5pt; color: #800000; letter-spacing: .5pt; line-height: 1.1; }
    .lh .office { font-size: 7.6pt; color: #4a4a4a; margin-top: 3px; }
    .lh .motto { font-size: 7.4pt; color: #800000; font-style: italic; margin-top: 1px; }
    .lh .sys { text-align: right; font-size: 7.4pt; color: #800000; letter-spacing: .3pt; }
    .lh .contact { text-align: right; font-size: 6.9pt; color: #767676; margin-top: 3px; }
    .lh .rule { border-bottom: 1.2px solid #800000; margin-top: 8px; }
    .foot { position: fixed; bottom: -44px; left: 0; right: 0; height: 30px; border-top: .6px solid #d8d8d8; padding-top: 5px; font-size: 6.9pt; color: #767676; }
    h1 { font-size: 15pt; font-weight: normal; margin: 0 0 2px; letter-spacing: -.1pt; }
    .sub { color: #4a4a4a; font-size: 8.4pt; margin-bottom: 12px; }
    h2 { font-size: 10pt; margin: 16px 0 6px; font-weight: bold; }
    .muted { color: #767676; }
    .small { font-size: 7.2pt; }
    .kpis { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
    .kpis td { border: .6px solid #d8d8d8; border-top-width: 2.4px; padding: 7px 8px 6px; text-align: center; background: #fcfafa; }
    .kpis .v { font-size: 13.5pt; font-weight: bold; }
    .kpis .l { font-size: 6.9pt; color: #4a4a4a; margin-top: 1px; }
    .k-present { border-top-color: #17703f !important; } .k-late { border-top-color: #b97b10 !important; }
    .k-absent { border-top-color: #b42318 !important; } .k-leave { border-top-color: #1a5aa0 !important; }
    .k-brand { border-top-color: #800000 !important; }
    .bar { width: 100%; border-collapse: collapse; height: 8px; margin-top: 2px; }
    .bar td { height: 8px; padding: 0; }
    .legend { font-size: 7pt; color: #4a4a4a; margin: 4px 0 10px; }
    .legend span { margin-right: 12px; }
    .sw { display: inline-block; width: 7px; height: 7px; margin-right: 3px; }
    table.t { width: 100%; border-collapse: collapse; }
    table.t thead { display: table-header-group; }
    table.t th { background: #800000; color: #fff; font-weight: bold; font-size: 7.2pt; text-align: left; padding: 5px 6px; }
    table.t td { padding: 4px 6px; border-bottom: .5px solid #e4e4e4; font-size: 7.9pt; vertical-align: top; }
    table.t tr { page-break-inside: avoid; }
    table.t .r { text-align: right; }
    table.t .c { text-align: center; }
    table.t tr.group { page-break-after: avoid; }
    table.t tr.group td { background: #f7eeee; border-bottom: .6px solid #e6caca; font-size: 8pt; padding: 5px 6px; }
    table.t tr.group td b { color: #800000; }
    table.t tr.total td { border-top: 1px solid #800000; font-weight: bold; background: #fafafa; }
    table.t tr.off td { color: #9a9a9a; background: #fcfcfc; }
    .st { padding: 1px 5px; font-size: 7pt; font-weight: bold; }
    .st-present { background: #e8f4ed; color: #17703f; } .st-late { background: #fdf3e2; color: #9a5b00; }
    .st-absent { background: #fdeceb; color: #b42318; } .st-leave { background: #e8f0fa; color: #1a5aa0; }
    .st-muted { background: #f0f0f0; color: #767676; }
    .note { border-left: 2px solid #800000; background: #fafafa; padding: 6px 9px; font-size: 7.4pt; color: #4a4a4a; margin: 8px 0; }
    .dl { width: 100%; border-collapse: collapse; }
    .dl td { padding: 3px 0; border-bottom: .5px solid #ebebeb; font-size: 8pt; }
    .dl td.k { color: #767676; width: 38%; }
    .dl.two td.k { width: 15%; }
    .dl.two td { width: 35%; }
    .h3 { font-size: 8.2pt; font-weight: bold; color: #800000; margin: 0 0 5px; letter-spacing: .2pt; }
    table.split { width: 100%; border-collapse: collapse; }
    table.split > tr > td, table.split td.half { vertical-align: top; padding: 0; }
    table.bars { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.bars td { padding: 2.5px 0; font-size: 7.6pt; vertical-align: middle; }
    table.bars td.bl { width: 38%; padding-right: 6px; }
    table.bars td.bv { width: 44px; text-align: right; font-weight: bold; }
    table.bars .track { height: 7px; background: #eeeeee; }
    table.bars .track div { height: 7px; }
    table.mini { width: 100%; border-collapse: collapse; }
    table.mini td { padding: 3px 0; border-bottom: .5px solid #ebebeb; font-size: 7.7pt; }
    table.mini td.r { text-align: right; padding-left: 6px; white-space: nowrap; }
    table.cols { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.cols td { vertical-align: bottom; padding: 0 1.5px; text-align: center; }
    table.cols .stack div { width: 100%; }
    table.cols .cl { font-size: 6.2pt; color: #767676; line-height: 1.15; margin-top: 2px; }
</style>
