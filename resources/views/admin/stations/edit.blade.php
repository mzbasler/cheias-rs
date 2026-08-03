@extends('admin.layout')

@section('title', 'Editar estação')

@section('content')
    <p class="admin-title">{{ $station->name }}</p>
    <p class="admin-subtitle">{{ $station->source }} · {{ $station->external_id }}</p>

    @if ($errors->any())
        <ul class="admin-errors" style="margin-top: var(--sp-section)">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.stations.update', $station) }}"
          class="admin-card admin-form flex flex-col gap-3" style="margin-top: var(--sp-section); padding: var(--sp-section)">
        @csrf
        @method('PUT')

        <label class="admin-field">
            <span>Nome</span>
            <input type="text" name="name" value="{{ old('name', $station->name) }}" required class="admin-input">
        </label>

        <label class="admin-field">
            <span>Rio</span>
            <input type="text" name="river" value="{{ old('river', $station->river) }}" class="admin-input">
        </label>

        <label class="admin-field">
            <span>Município</span>
            <input type="text" name="municipality" value="{{ old('municipality', $station->municipality) }}" class="admin-input">
        </label>

        <div class="grid grid-cols-2 gap-3">
            <label class="admin-field">
                <span>Latitude</span>
                <input type="text" name="latitude" value="{{ old('latitude', $station->latitude) }}" required class="admin-input">
            </label>

            <label class="admin-field">
                <span>Longitude</span>
                <input type="text" name="longitude" value="{{ old('longitude', $station->longitude) }}" required class="admin-input">
            </label>
        </div>

        <label class="admin-field">
            <span>Unidade</span>
            <input type="text" name="unit" value="{{ old('unit', $station->unit) }}" class="admin-input">
        </label>

        <div class="grid grid-cols-3 gap-3">
            <label class="admin-field">
                <span>Cota de atenção</span>
                <input type="text" name="attention_level" value="{{ old('attention_level', $station->attention_level) }}" class="admin-input">
            </label>

            <label class="admin-field">
                <span>Cota de alerta</span>
                <input type="text" name="alert_level" value="{{ old('alert_level', $station->alert_level) }}" class="admin-input">
            </label>

            <label class="admin-field">
                <span>Cota de inundação</span>
                <input type="text" name="critical_level" value="{{ old('critical_level', $station->critical_level) }}" class="admin-input">
            </label>
        </div>

        <div class="flex gap-3" style="margin-top: var(--sp-tight)">
            <button type="submit" class="admin-button admin-button--primary">Salvar</button>
            <a href="{{ route('admin.stations.index') }}" class="admin-button admin-button--secondary">Cancelar</a>
        </div>
    </form>
@endsection
