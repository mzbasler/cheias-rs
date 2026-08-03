@extends('admin.layout')

@section('title', 'Relatos')

@section('content')
    <p class="admin-title">Relatos de moradores</p>
    <p class="admin-subtitle">{{ $reports->total() }} recebidos. Aprovado entra no mapa; nunca se mistura com telemetria oficial.</p>

    @if (session('status'))
        <p class="admin-flash" style="margin-top: var(--sp-section)">{{ session('status') }}</p>
    @endif

    <div class="flex flex-col gap-3" style="margin-top: var(--sp-section)">
        @foreach ($reports as $report)
            <div class="admin-card flex gap-3" style="padding: var(--sp-section)">
                <button type="button" class="admin-photo-trigger" data-photo-trigger="{{ $report->photoUrl() }}"
                        aria-label="Ver foto do relato ampliada">
                    <img src="{{ $report->photoUrl() }}" alt="" class="h-24 w-24">
                </button>

                <div class="admin-row-main">
                    <span class="admin-row-name">
                        {{ number_format($report->latitude, 4) }}, {{ number_format($report->longitude, 4) }}
                    </span>
                    <span class="admin-row-place">
                        {{ ['gps' => 'pelo GPS', 'address' => 'por endereço', 'manual' => 'marcado no mapa'][$report->position_source] }}
                        · {{ $report->created_at->locale('pt_BR')->diffForHumans() }}
                    </span>
                </div>

                @switch($report->status)
                    @case('approved')
                        <span class="admin-badge" data-tone="good" style="align-self: flex-start">Aprovado</span>
                        @break
                    @case('rejected')
                        <span class="admin-badge" data-tone="neutral" style="align-self: flex-start">Rejeitado</span>
                        @break
                    @default
                        <div class="flex flex-none flex-col gap-2">
                            <form method="POST" action="{{ route('admin.reports.approve', $report) }}">
                                @csrf
                                <button type="submit" class="admin-button admin-button--primary">Aprovar</button>
                            </form>
                            <form method="POST" action="{{ route('admin.reports.reject', $report) }}">
                                @csrf
                                <button type="submit" class="admin-button admin-button--secondary">Rejeitar</button>
                            </form>
                        </div>
                @endswitch
            </div>
        @endforeach
    </div>

    <div class="admin-pagination">
        {{ $reports->links() }}
    </div>

    <dialog id="photo-viewer" class="admin-photo-viewer" aria-label="Foto do relato ampliada">
        <img src="" alt="Foto do relato ampliada">
    </dialog>
@endsection
