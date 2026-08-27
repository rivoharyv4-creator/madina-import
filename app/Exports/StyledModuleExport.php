<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StyledModuleExport implements FromArray, WithCustomStartCell, WithDrawings, WithEvents, WithTitle
{
    private const HEADER_ROW = 4;

    public function __construct(
        private readonly string $module,
        private readonly string $title,
        private readonly array $columns,
        private readonly array $rows,
    ) {
    }

    public function array(): array
    {
        $data=[array_values($this->columns)];
        foreach($this->rows as $row){
            $data[]=array_map(fn($key)=>$this->value($key,$row[$key]??null),array_keys($this->columns));
        }
        return $data;
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function drawings(): array
    {
        $drawing=new Drawing();
        $drawing->setName('Madina Import');
        $drawing->setPath(public_path('brand/madina-import-logo-transparent.png'));
        $drawing->setHeight(53);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(7);
        $drawing->setOffsetY(3);
        $drawings=[$drawing];
        $photoColumn=array_search('photo_path',array_keys($this->columns),true);
        if($photoColumn===false) return $drawings;

        foreach($this->rows as $index=>$row){
            $path=$row['photo_path']??null;
            if(!$path||!Storage::disk('persistent')->exists($path)) continue;
            $absolutePath=Storage::disk('persistent')->path($path);
            if(@getimagesize($absolutePath)===false) continue;

            $photo=new Drawing();
            $photo->setName((string)($row['product_name']??$row['name']??'Photo produit'));
            $photo->setPath($absolutePath);
            $photo->setHeight(55);
            $photo->setCoordinates(Coordinate::stringFromColumnIndex($photoColumn+1).($index+self::HEADER_ROW+1));
            $photo->setOffsetX(5);
            $photo->setOffsetY(4);
            $drawings[]=$photo;
        }
        return $drawings;
    }

    public function title(): string
    {
        return mb_substr(str_replace(['\\','/','*','?',':','[',']'],'-',$this->title),0,31);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class=>function(AfterSheet $event): void {
            $sheet=$event->sheet->getDelegate();
            $columnCount=max(1,count($this->columns));
            $lastColumn=Coordinate::stringFromColumnIndex($columnCount);
            $lastRow=max(self::HEADER_ROW+1,self::HEADER_ROW+count($this->rows));

            $sheet->mergeCells("B1:{$lastColumn}1");
            $sheet->mergeCells("B2:{$lastColumn}2");
            $sheet->setCellValue('B1','MADINA IMPORT · '.mb_strtoupper($this->title));
            $sheet->setCellValue('B2','Export généré le '.now()->format('d/m/Y à H:i').' · '.count($this->rows).' enregistrement(s)');
            $sheet->getRowDimension(1)->setRowHeight(43);
            $sheet->getRowDimension(2)->setRowHeight(20);
            $sheet->getStyle("B1:{$lastColumn}1")->applyFromArray(['font'=>['bold'=>true,'size'=>18,'color'=>['rgb'=>'202124']],'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER]]);
            $sheet->getStyle("B2:{$lastColumn}2")->applyFromArray(['font'=>['size'=>9,'color'=>['rgb'=>'777777']]]);

            $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>10],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'2F2F2F']],
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER],
                'borders'=>['bottom'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'FCF108']]],
            ]);
            $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(26);

            for($row=self::HEADER_ROW+1;$row<=$lastRow;$row++){
                $sheet->getRowDimension($row)->setRowHeight(array_key_exists('photo_path',$this->columns)?48:23);
                if($row%2===0) $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F4');
            }
            $sheet->getStyle("A5:{$lastColumn}{$lastRow}")->applyFromArray([
                'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER],
                'borders'=>['bottom'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>'E4E4DF']]],
            ]);

            foreach(array_keys($this->columns) as $index=>$key){
                $letter=Coordinate::stringFromColumnIndex($index+1);
                $width=$this->columnWidth($key,$index);
                $sheet->getColumnDimension($letter)->setWidth($width);
                if($this->isMoney($key)) $sheet->getStyle("{$letter}5:{$letter}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0 "Ar"');
                if(in_array($key,['quantity','moq','quality_rating'],true)) $sheet->getStyle("{$letter}5:{$letter}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if($key==='photo_path') $sheet->getColumnDimension($letter)->setWidth(15);
            }

            $sheet->freezePane('A5');
            $sheet->setAutoFilter("A4:{$lastColumn}{$lastRow}");
            $sheet->setShowGridlines(false);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageMargins()->setTop(.45)->setRight(.3)->setBottom(.45)->setLeft(.3);
            $sheet->getHeaderFooter()->setOddFooter('&LMADINA IMPORT&CPage &P / &N&R'.$this->title);
        }];
    }

    private function value(string $key, mixed $value): mixed
    {
        if($value===null||$value==='') return '';
        if($key==='photo_path') return '';
        if($key==='active') return $value?'Actif':'Inactif';
        if($this->isMoney($key)||in_array($key,['quantity','moq','quality_rating'],true)) return (float)$value;
        if($key==='month'||str_ends_with($key,'_at')||str_ends_with($key,'_date')||in_array($key,['valid_until','due_at'],true)){
            try{return Carbon::parse($value)->format($key==='month'?'m/Y':'d/m/Y');}catch(\Throwable){return (string)$value;}
        }
        return ucfirst(str_replace('_',' ',(string)$value));
    }

    private function isMoney(string $key): bool
    {
        return collect(['total','amount','price','value','salary','margin','balance','deposit','cost','freight'])->contains(fn($part)=>str_contains($key,$part));
    }

    private function columnWidth(string $key, int $index): int
    {
        $length=mb_strlen((string)array_values($this->columns)[$index]);
        foreach($this->rows as $row) $length=max($length,mb_strlen((string)($row[$key]??'')));
        return min(42,max(14,$length+3));
    }
}
