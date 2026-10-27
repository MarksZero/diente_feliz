<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Modules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionAdministrator extends Command
{
    protected $signature = 'clinic:provision-admin';
    protected $description = 'Crea la primera cuenta y sucursal sin cargar pacientes de demostración';

    public function handle(): int
    {
        if (User::exists()) {
            $this->error('Ya existen usuarios. Usa la administración de cuentas de la aplicación.');
            return self::FAILURE;
        }
        $data = [
            'name' => $this->ask('Nombre del administrador'),
            'email' => strtolower((string) $this->ask('Correo del administrador')),
            'password' => $this->secret('Contraseña (12 caracteres, mayúsculas, minúsculas, números y símbolos)'),
            'password_confirmation' => $this->secret('Repite la contraseña'),
            'branch' => $this->ask('Nombre de la primera sucursal'),
            'address' => $this->ask('Dirección de la sucursal'),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:200',
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'branch' => 'required|string|max:150',
            'address' => 'required|string|max:250',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            DB::statement('LOCK TABLE users IN EXCLUSIVE MODE');
            if (User::exists()) {
                throw new \RuntimeException('Otra operación ya creó el primer usuario.');
            }
            $role = Role::firstOrCreate(['name' => 'Administrador general'], ['permissions' => Modules::permissions()]);
            $branch = Branch::create(['name' => $data['branch'], 'address' => $data['address'], 'active' => true]);
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'active' => true]);
            $user->roles()->attach($role);
            $user->branches()->attach($branch);
            DB::table('audit_events')->insert(['actor_id' => $user->id, 'action' => 'initial_provision', 'entity' => 'users', 'entity_id' => $user->id, 'created_at' => now()]);
        });
        $this->info('Administrador y sucursal creados. No se cargaron pacientes ni contraseñas demo.');
        return self::SUCCESS;
    }
}
