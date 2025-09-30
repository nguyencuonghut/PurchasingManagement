<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class SupplierSelectionReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'file_path' => $this->file_path,
            'image_url' => $this->image_url,
            'status' => $this->status,
            'creator_id' => $this->creator_id,

            'manager_approved_result' => $this->manager_approved_result,
            'manager_approved_notes' => $this->manager_approved_notes,
            'auditor_audited_result' => $this->auditor_audited_result,
            'auditor_audited_notes' => $this->auditor_audited_notes,
            'director_approved_result' => $this->director_approved_result,
            'director_approved_notes' => $this->director_approved_notes,

            'creator_name' => $this->creator_name,
            'manager_name' => $this->manager_name,
            'auditor_name' => $this->auditor_name,
            'director_name' => $this->director_name,
            'admin_thu_mua_id' => $this->adm_id,
            'admin_thu_mua_name' => $this->admin_thu_mua_name,

            'manager_id' => $this->manager_id,
            'auditor_id' => $this->auditor_id,
            'director_id' => $this->director_id,

            'manager_approved_at' => $this->manager_approved_at,
            'auditor_audited_at' => $this->auditor_audited_at,
            'director_approved_at' => $this->director_approved_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'quotation_files_count' => $this->when(isset($this->quotation_files_count), $this->quotation_files_count, $this->quotationFiles()->count()),
            'formatted_created_at' => $this->created_at ? Carbon::parse($this->created_at)->format('d/m/Y H:i') : 'N/A',
            'formatted_manager_approved_at' => $this->manager_approved_at ? Carbon::parse($this->manager_approved_at)->format('d/m/Y H:i') : '',
            'formatted_auditor_audited_at' => $this->auditor_audited_at ? Carbon::parse($this->auditor_audited_at)->format('d/m/Y H:i') : '',
            'formatted_director_approved_at' => $this->director_approved_at ? Carbon::parse($this->director_approved_at)->format('d/m/Y H:i') : '',

            'quotation_files' => QuotationFileResource::collection($this->whenLoaded('quotationFiles')),
            'proposal_files' => ProposalFileResource::collection($this->whenLoaded('proposalFiles')),
        ];
    }
}
