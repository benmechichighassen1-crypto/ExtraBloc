<?php

namespace App\Exports;

use App\Http\Controllers\DirectionController;
use App\Support\Format;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel (.xlsx) de l'écran "Contrôle direction".
 * Reçoit les lignes déjà filtrées (dates + statuts) par DirectionController::buildQuery().
 */
class DeclarationsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            'Dossier', 'Patient', 'Intervenant', 'Type intervenant', 'Acte', 'Salle',
            'Chirurgien', 'Réanimateur',
            'Heure planification début', 'Heure planification fin',
            'Heure anesthésie début', 'Heure anesthésie fin',
            'Heure emploi', 'Heure pointage entrée', 'Heure pointage sortie',
            'Saisi par', 'Saisi le', 'Statut',
            'Décidé par', 'Décidé le', 'Motif décision', 'Observation',
        ];
    }

    public function map($item): array
    {
        $heureEmploi = $item->HeureEmploiDebut1
            ? Format::time($item->HeureEmploiDebut1) . ' - ' . Format::time($item->HeureEmploiFin1)
            : '';

        return [
            $item->num_doss,
            trim(($item->NomPatient ?? '') . ' ' . ($item->PrenomPatient ?? '')),
            $item->DesInterv ?? $item->cod_interv,
            $item->DesTypInterv,
            $item->LibelleActe,
            $item->DesignationSalle,
            $item->Chirurgien,
            $item->Reanimateur,
            Format::dateTime($item->HDAnest),
            Format::dateTime($item->HFAnest),
            Format::dateTime($item->Debut_Anesthesie),
            Format::dateTime($item->Fin_Anesthesie),
            $heureEmploi,
            Format::time($item->HeurePointageEntree),
            Format::time($item->HeurePointageSortie),
            $item->declared_by_username,
            Format::dateTime($item->declared_at),
            DirectionController::STATUS_LABELS[$item->statut] ?? $item->statut,
            $item->valide_par_username,
            Format::dateTime($item->valide_le),
            $item->motif_decision,
            $item->observation,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
