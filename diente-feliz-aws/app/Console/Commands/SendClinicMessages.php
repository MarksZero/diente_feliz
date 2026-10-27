<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Mail};
use App\Models\Record;
use App\Services\Audit;
class SendClinicMessages extends Command {
    protected $signature='clinic:send-messages';
    protected $description='Procesa mensajes autorizados usando el correo local configurado';
    public function handle(): int {
        $sent=0;
        foreach(Record::for('messages')->where('status','Pendiente')->limit(100)->pluck('id') as $id) {
            DB::transaction(function() use($id,&$sent) {
                $message=Record::for('messages')->lockForUpdate()->find($id); if($message->status!=='Pendiente') return;
                $patient=Record::for('patients')->findOrFail($message->patient_id);
                if(!$patient->contact_consent || !$patient->email) { $message->status='Sin autorización'; $message->save(); return; }
                if (config('mail.default')==='log') {
                    $message->status='Simulado'; $message->save();
                    Audit::record('message_simulated','messages',$id);
                    return;
                }
                Mail::raw($message->body,fn($mail)=>$mail->to($patient->email)->subject($message->subject));
                $message->status='Enviado'; $message->sent_at=now(); $message->save(); Audit::record('message_sent','messages',$id); $sent++;
            });
        }
        $this->info("Mensajes enviados al transporte: {$sent}. En modo log se marcan Simulado y se consultan en Notificaciones; no se envían correos."); return self::SUCCESS;
    }
}
