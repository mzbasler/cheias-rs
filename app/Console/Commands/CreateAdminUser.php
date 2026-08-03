<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Argumentos, não prompts (`ask()`/`secret()`): em alguns terminais Windows,
 * qualquer aviso do PHP levantado durante uma pergunta interativa do Symfony
 * Console trava o processo — o ClassLoader falha ao carregar o renderizador
 * de erro nesse contexto específico. Argumento nunca passa por esse caminho.
 */
#[Signature('admin:create-user {name : Nome do admin} {email : E-mail de login} {password : Senha}')]
#[Description('Cria um usuário com acesso ao painel de admin')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $this->argument('password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->info("Usuário {$data['email']} criado.");

        return self::SUCCESS;
    }
}
