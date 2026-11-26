{{-- Raw CSS for the paper-style Stock Card (QF form layout). Include inside a <style> element.
     Sizes are in em so the same rules serve the screen (.sc-screen) and A4 print/PDF. --}}
.sc-doc { font-size: 10.5px; color: #000; }
.sc-doc.sc-screen { font-size: 14px; background: #fff; }
.sc-head, .sc-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
.sc-head td { border: 1.2px solid #000; padding: .3em .6em; vertical-align: top; }
.sc-head td.sc-title-cell { text-align: center; padding: .9em .6em .5em; border-bottom: none; }
.sc-head td.sc-last { border-bottom: none; }
.sc-head tr.sc-sizer td { border: none; padding: 0; height: 0; line-height: 0; font-size: 0; }
.sc-title { font-family: "Times New Roman", Times, serif; font-weight: bold; font-size: 2.5em; letter-spacing: .25em; line-height: 1; }
.sc-code { font-family: "Times New Roman", Times, serif; font-weight: bold; font-size: .85em; margin-top: .25em; }
.sc-lbl { font-family: "Times New Roman", Times, serif; font-size: 1.05em; }
.sc-val { font-weight: bold; padding-left: .5em; }
.sc-office div { padding: .1em 0; }
.sc-office .sc-lbl { display: inline-block; width: 5.4em; }
.sc-block .sc-val { display: block; padding: .3em 0 .1em .5em; }
.sc-head td.sc-stockcell { padding: 0; }
.sc-stockcell div { padding: .35em .6em; }
.sc-stockcell div.sc-rule { border-top: 1.2px solid #000; }

.sc-grid th, .sc-grid td { border: 1.2px solid #000; padding: .2em .45em; }
.sc-grid thead { display: table-header-group; }
.sc-grid thead th { font-family: "Times New Roman", Times, serif; font-weight: bold; text-align: center; vertical-align: middle; }
.sc-grid thead th.sc-big { font-size: 1.15em; letter-spacing: .04em; }
.sc-grid tbody td { height: 1.75em; vertical-align: middle; }
.sc-grid tr { page-break-inside: avoid; }
.sc-grid td.q { text-align: center; }
.sc-grid tr.sc-in td { color: #b3261e; }
.sc-grid td.sc-asper { font-style: italic; }
.sc-grid .sc-sub { font-size: .8em; color: #444; }
.sc-grid tr.sc-reversed td { color: #777; }
.sc-foot { width: 100%; margin-top: .6em; font-size: .85em; color: #333; border-collapse: collapse; }
.sc-foot td { padding: 0; }
