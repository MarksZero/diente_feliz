<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Services\Modules;
class Record extends Model {
    protected $guarded = ['id'];
    public static function for(string $module): static {
        $definition = Modules::get($module);
        $model = new static;
        $model->setTable($definition['table']);
        // Explicit per-module columns avoid Eloquent's class-wide column cache for dynamic tables.
        $extra = match ($module) {
            'clinical','comments','odontogram' => ['author_id'],
            'documents' => ['author_id','path','mime','size','sha256'],
            'consents' => ['author_id','signed_at','document_hash'],
            'budgets' => ['discount_percent','total'],
            'payments','refunds' => ['author_id','idempotency_key'],
            'templates' => ['version'],
            'responses' => ['question_snapshot'],
            'messages' => ['status','sent_at'],
            default => [],
        };
        $model->fillable(array_merge(array_diff(array_keys($definition['fields']),['file','accepted']),$extra));
        $casts = [];
        foreach ($definition['encrypted'] ?? [] as $field) $casts[$field] = 'encrypted';
        $model->mergeCasts($casts);
        return $model;
    }
}
