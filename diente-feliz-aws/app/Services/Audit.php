<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
class Audit {
    public static function record(string $action, string $entity, ?int $id = null, array $changes = []): void {
        unset($changes['password'], $changes['remember_token'], $changes['path']);
        DB::table('audit_events')->insert(['actor_id'=>auth()->id(),'action'=>$action,'entity'=>$entity,'entity_id'=>$id,'changes'=>$changes ? Crypt::encryptString(json_encode($changes, JSON_UNESCAPED_UNICODE)) : null,'ip'=>app()->runningInConsole() ? null : request()->ip(),'created_at'=>now()]);
    }
}
