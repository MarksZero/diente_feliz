<?php
namespace App\Policies;
use App\Models\User;
use App\Models\Record;
use App\Services\Modules;
use Illuminate\Support\Facades\DB;
class RecordPolicy {
    public function view(User $user, Record $record): bool {
        $key = Modules::keyFor($record->getTable());
        if (!$user->permits(Modules::get($key)['permission'].'.view')) return false;
        if ($record->branch_id && !in_array($record->branch_id, $user->branchIds())) return false;
        if ($record->getTable() === 'branches' && !in_array($record->id, $user->branchIds())) return false;
        if ($record->getTable() === 'patients' && !DB::table('branch_patient')->where('patient_id',$record->id)->whereIn('branch_id',$user->branchIds())->exists()) return false;
        if ($record->getTable() === 'comments' && !$user->permits('comments.'.$record->visibility)) return false;
        return true;
    }
    public function update(User $user, Record $record): bool {
        $definition = Modules::get(Modules::keyFor($record->getTable()));
        return $this->view($user,$record) && empty($definition['immutable']) && $user->permits($definition['permission'].'.write');
    }
}
