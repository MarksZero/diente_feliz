<?php

namespace Tests\Audit;

use App\Models\{User, Role, Record};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail};
use Tests\TestCase;

/** Characterization tests: a pass confirms the documented defect, not a fix. */
class AuditProbeTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if (config('database.connections.pgsql.database') !== 'diente_feliz_test') {
            throw new \RuntimeException('Audit probes require diente_feliz_test.');
        }
        return $app;
    }

    public function test_documented_defects(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@dientefeliz.test')->firstOrFail();
        $this->actingAs($admin)->withSession(['session_version' => $admin->session_version]);

        // The same human has a separate professional row in each branch.
        $dentist = User::where('email', 'dentista@dientefeliz.test')->firstOrFail();
        $dentist->branches()->attach(2);
        $second = Record::for('professionals');
        $second->forceFill(['branch_id'=>2,'user_id'=>$dentist->id,'name'=>'Misma persona, sede Norte','specialty'=>'General','active'=>true])->save();
        $availability = Record::for('availability');
        $availability->forceFill(['branch_id'=>2,'professional_id'=>$second->id,'room_id'=>3,'starts_at'=>today()->setTime(8,0),'ends_at'=>today()->setTime(18,0)])->save();
        $appointment = ['branch_id'=>2,'patient_id'=>8,'professional_id'=>$second->id,'room_id'=>3,'starts_at'=>today()->setTime(9,0)->format('Y-m-d\TH:i'),'ends_at'=>today()->setTime(9,30)->format('Y-m-d\TH:i'),'reason'=>'Audit concurrent branch','status'=>'Pendiente'];
        // Test the scheduling rule independently; the HTTP write is separately
        // blocked by the Record/messages mass-assignment defect.
        $schedule = new \ReflectionMethod(\App\Services\Clinic::class, 'schedule');
        $arguments = [&$appointment, null, 'appointments'];
        $schedule->invokeArgs(null, $arguments);
        DB::table('appointments')->insert($appointment);
        $this->assertSame(2, DB::table('appointments')->whereIn('professional_id',[1,$second->id])->where('starts_at',today()->setTime(9,0))->count(), 'Cross-branch double booking accepted');

        // Account suspension is tested against now, not the requested appointment.
        $dentist->update(['disabled_from'=>today()->addDay(),'disabled_until'=>today()->addDays(3)]);
        $appointment = array_merge($appointment,['branch_id'=>1,'patient_id'=>6,'professional_id'=>1,'room_id'=>1,'starts_at'=>today()->addDay()->setTime(15,0)->format('Y-m-d\TH:i'),'ends_at'=>today()->addDay()->setTime(15,30)->format('Y-m-d\TH:i')]);
        $arguments = [&$appointment, null, 'appointments'];
        $schedule->invokeArgs(null, $arguments);
        $this->assertTrue(true, 'Future suspension does not block scheduling');

        // Scope metadata of resources can move without moving linked records.
        $this->put('/modules/rooms/1',['branch_id'=>2,'name'=>'Box trasladado','equipment'=>'Demo','active'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2,(int) DB::table('rooms')->where('id',1)->value('branch_id'));
        $this->assertTrue(DB::table('appointments')->where('room_id',1)->where('branch_id',1)->exists());

        // Optional fields omitted from a valid request cause an undefined-key error.
        $this->post('/users',['name'=>'Audit User','email'=>'audit@example.test','password'=>'Audit-Password!2026','active'=>1,'roles'=>[3],'branches'=>[1]])->assertStatus(500);
        $this->assertDatabaseMissing('users',['email'=>'audit@example.test']);

        // users.write alone can grant a role whose permissions exceed the actor's.
        $limitedRole = Role::create(['name'=>'Audit delegated account manager','permissions'=>['users.write']]);
        $limited = User::create(['name'=>'Delegated','email'=>'delegated@example.test','password'=>'Audit-Password!2026','active'=>true,'session_version'=>1]);
        $limited->roles()->attach($limitedRole); $limited->branches()->attach(1);
        $this->actingAs($limited)->withSession(['session_version'=>1]);
        $this->post('/users',['name'=>'Promoted','email'=>'promoted@example.test','password'=>'Audit-Password!2026','active'=>1,'disabled_from'=>null,'disabled_until'=>null,'roles'=>[1],'branches'=>[1,2]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email','promoted@example.test')->firstOrFail()->permits('roles.write'));

        // Control: a fresh login is valid; distinguish a stale actingAs test
        // object from a real session loaded on a new request.
        $this->actingAs($admin)->withSession(['session_version'=>$admin->session_version]);
        $this->post('/logout')->assertRedirect();
        $this->post('/login',['email'=>'admin@dientefeliz.test','password'=>env('DEMO_PASSWORD')])->assertRedirect('/');
        $this->get('/')->assertOk();
    }
}

