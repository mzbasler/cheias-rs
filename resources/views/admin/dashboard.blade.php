@extends('admin.layout')

@section('title', 'Painel')

@section('content')
    <p class="admin-title">Painel</p>

    <p class="admin-eyebrow">Saúde da ingestão</p>

    <div class="admin-card">
        @foreach ($ingestion as $source)
            <div class="admin-row">
                <span class="admin-row-main">
                    <span class="admin-row-name">{{ $source['label'] }}</span>
                    <span class="admin-row-place">{{ $source['fresh'] }} de {{ $source['total'] }} estações com leitura fresca</span>
                </span>
                @if ($source['total'] > 0 && $source['fresh'] === $source['total'])
                    <span class="admin-badge" data-tone="good">Em dia</span>
                @else
                    <span class="admin-badge" data-tone="critical">Desatualizado</span>
                @endif
            </div>
        @endforeach

        <div class="admin-row">
            <span class="admin-row-main">
                <span class="admin-row-name">ANA/SNIRH</span>
                <span class="admin-row-place">Catálogo de estações — última importada {{ $snirhLastImportedAt?->locale('pt_BR')->diffForHumans() ?? 'nunca' }}</span>
            </span>
            @if ($snirhLastImportedAt && $snirhLastImportedAt->gt(now()->subDays(8)))
                <span class="admin-badge" data-tone="good">Em dia</span>
            @else
                <span class="admin-badge" data-tone="critical">Desatualizado</span>
            @endif
        </div>
    </div>
@endsection
