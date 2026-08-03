@extends('admin.layout')

@section('title', 'Configurações')

@section('content')
    <p class="admin-title">Configurações</p>

    @if (session('status'))
        <p class="admin-flash" style="margin-top: var(--sp-section)">{{ session('status') }}</p>
    @endif

    <p class="admin-eyebrow">Doação por Pix</p>
    <p class="admin-hint" style="margin-top: var(--sp-tight)">Chave vazia desliga o botão de doação no mapa — nunca gera QR Code para lugar nenhum.</p>

    @if ($errors->any())
        <ul class="admin-errors" style="margin-top: var(--sp-section)">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}"
          class="admin-card flex max-w-sm flex-col gap-3" style="margin-top: var(--sp-section); padding: var(--sp-section)">
        @csrf
        @method('PUT')

        <label class="admin-field">
            <span>Chave Pix</span>
            <input type="text" name="pix_key" value="{{ old('pix_key', $setting->pix_key) }}" class="admin-input">
        </label>

        <label class="admin-field">
            <span>Nome do recebedor</span>
            <input type="text" name="pix_receiver_name" value="{{ old('pix_receiver_name', $setting->pix_receiver_name) }}" class="admin-input">
        </label>

        <label class="admin-field">
            <span>Cidade do recebedor</span>
            <input type="text" name="pix_receiver_city" value="{{ old('pix_receiver_city', $setting->pix_receiver_city) }}" class="admin-input">
        </label>

        <button type="submit" class="admin-button admin-button--primary" style="margin-top: var(--sp-tight)">Salvar</button>
    </form>
@endsection
