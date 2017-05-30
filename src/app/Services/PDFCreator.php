<?php

namespace App\Services;

class PDFCreator
{
    protected $title;
    protected $content;
    protected $column_widths = [];

    public function __construct()
    {
        $this->pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        // set default font subsetting mode
        $this->pdf->setFontSubsetting(true);
        
        // Default font
        // $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        // $this->pdf->SetFontSize('10px');

        // Fallback font
        $this->pdf->SetFont('freeserif', '', 12);

        // set margins
        $this->pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $this->pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $this->pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        $this->column_widths = [200, 40, 80, 80, 80];

        return $this;
    }

    public function setColumnWidths(array $array) : self {
        $this->column_widths = $array;

        return $this;
    }

    public function setTitle(string $title) : self
    {
        $this->title = $title;
        $this->pdf->SetTitle($title);

        return $this;
    }

    public function setTable(array $headers, array $table) : self
    {
        $html = '<table width="100%" cellpadding="10" cellspacing="0" border="1">';

        $idx = 0;
        $widths = $this->column_widths;

        $html .= '<tr>' . implode('', array_map(function ($item) use (&$idx, $widths) {
                $style = ' style="background: #e8e8e8;width:' . $widths[$idx++] . 'px;" ';

                return '<th ' . $style . '>' . $item . '</th>';
            }, $headers)) . '</tr>';

        foreach ($table as $rows) {
            $html .= '<tr>' . implode('', array_map(function ($item) {
                return '<td>' . $item . '</td>';
            }, $rows)) . '</tr>';
        }

        $html .= '</table>';
        $this->content .= $html;

        return $this;
    }

    public function save(string $file)
    {
        // add a page
        $this->pdf->AddPage('L');
        $this->pdf->writeHTMLCell(0, 0, '', '', '<h2>' . $this->title . '</h2>');
        $this->pdf->writeHTML('<br/><br/>');
        $this->pdf->writeHTML($this->content);
        $this->pdf->Output($file, 'F');
    }
}