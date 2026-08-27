<?php

namespace App\Exports;

use App\Support\Format;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AnapathSuiviExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    private $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function headings(): array
    {
        return [
            'N° demande', 'Date de la demande', 'Patient', 'N° dossier',
            'Acte', 'Date de l\'acte', 'Médecin prescripteur',
            'Laboratoire', 'Saisi par', 'Date de saisie', 'Heure de saisie', 'Statut',
            'Résultat reçu', 'Date réception', 'Comptes rendus (PJ)', 'Paiement', 'Date paiement',
        ];
    }

    public function collection()
    {
        return $this->rows->map(function ($r) {
            $d = fn ($x) => $x ? Carbon::parse($x)->format('d/m/Y') : '';

            return [
                $r->numero_demande ?? '',
                $d($r->created_at),
                trim(($r->patient_nom ?? '').' '.($r->patient_prenom ?? '')),
                $r->num_doss ?? '',
                $r->LibelleActe ?? ($r->code_examen_erp ?? ''),
                $d($r->DateActe),
                $r->medecin_prescripteur ?? '',
                $r->laboratoire_nom ?? '',
                $r->cree_par_username ?? '',
                $d($r->created_at),
                $r->created_at ? Format::time($r->created_at) : '',
                $r->statut_label ?? ($r->resultat_recu ? 'Résultat reçu' : 'En attente'),
                $r->resultat_recu ? 'Oui' : 'Non',
                $d($r->resultat_recu_le),
                $r->pj_list ? $r->pj_list->pluck('nom')->join(', ') : '',
                $r->paiement_type === 'laboratoire' ? 'Laboratoire'
                    : ($r->paiement_type === 'facture' ? 'Inclus dans la facture patient' : 'À renseigner'),
                $d($r->paiement_date),
            ];
        });
    }
}
