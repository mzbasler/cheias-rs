@extends('admin.layout')

@section('title', 'Entrar')

@section('content')
    <div class="mx-auto mt-16 w-full max-w-sm admin-card" style="padding: 1.5rem">
        <p class="admin-title">Entrar</p>
        <p class="admin-subtitle">Painel de administração do Cheias RS.</p>

        @error('email')
            <p class="admin-errors" style="margin-top: var(--sp-section)">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-3" style="margin-top: var(--sp-section)">
            @csrf

            <label class="admin-field">
                <span>E-mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="admin-input">
            </label>

            <label class="admin-field">
                <span>Senha</span>
                <input type="password" name="password" required class="admin-input">
            </label>

            <button type="submit" class="admin-button admin-button--primary" style="margin-top: var(--sp-tight)">Entrar</button>
        </form>
    </div>
@endsection
