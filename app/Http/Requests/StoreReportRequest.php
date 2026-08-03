<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // jpeg/png/webp só — nunca svg: aberto direto pela URL pública, um
            // svg malicioso executa script no navegador de quem clicar.
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            // Bounds do RS, com folga — não é validação de fronteira exata, só
            // descarta coordenada absurda (dedo errado, bug de geocoding).
            'latitude' => ['required', 'numeric', 'between:-34,-27'],
            'longitude' => ['required', 'numeric', 'between:-58,-49'],
            'position_source' => ['required', 'in:gps,address,manual'],
            // Desabilitar o botão no cliente é UX, não é controle — o servidor
            // exige a mesma coisa de novo.
            'consent' => ['required', 'accepted'],
        ];
    }
}
