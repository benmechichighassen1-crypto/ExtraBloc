<?php

namespace App\Exports;

use App\Support\Format;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RegistreExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Dossier', 'Patient', 'Acte', 'Salle', 'Date acte', 'Heure planification',
            'Chirurgien', 'Réanimateur', 'Statut compte rendu', 'Compte rendu validé le',
            'Demande anapath',
        ];
    }

    public function map($item): array
    {
        return [
            $item->NumDoss,
            $item->Patient,
            $item->LibelleActe,
            $item->DesignationSalle,
            Format::date($item->DateActe),
            Format::timeRange($item->HDAnest, $item->HFAnest),
            $item->Chirurgien,
            $item->Reanimateur,
            $item->StatutCompteRendu,
            Format::dateTime($item->CompteRenduValideLe),
            $item->NumeroDemande,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
