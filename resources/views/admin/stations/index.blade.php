@extends('admin.layout')

@section('title', 'Estações')

@section('content')
    <p class="admin-title">Estações</p>
    <p class="admin-subtitle">{{ $stations->total() }} no catálogo, inclusive as sem leitura.</p>

    @if (session('status'))
        <p class="admin-flash" style="margin-top: var(--sp-section)">{{ session('status') }}</p>
    @endif

    <div class="admin-card" style="margin-top: var(--sp-section)">
        @foreach ($stations as $station)
            <div class="admin-row">
                <span class="dot" style="--dot: var(--status-{{ $station->status() }})" aria-hidden="true"></span>

                <span class="admin-row-main">
                    <span class="admin-row-name">{{ $station->name }}</span>
                    <span class="admin-row-place">{{ collect([$station->river, $station->municipality])->filter()->join(' · ') ?: $station->source }}</span>
                </span>

                <a href="{{ route('admin.stations.edit', $station) }}" class="admin-row-action">Editar</a>
            </div>
        @endforeach
    </div>

    <div class="admin-pagination">
        {{ $stations->links() }}
    </div>
@endsection
