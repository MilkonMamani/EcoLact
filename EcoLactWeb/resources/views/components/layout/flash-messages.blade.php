<div class="space-y-3">
    @if (auth()->user()?->estaEnSoloConsulta())
        <x-ui.alert variant="warning">{{ \App\Http\Support\SesionWeb::MENSAJE_SOLO_CONSULTA }}</x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="error">{{ session('error') }}</x-ui.alert>
    @endif

    @if (session('warning'))
        <x-ui.alert variant="warning">{{ session('warning') }}</x-ui.alert>
    @endif

    @if (session('info'))
        <x-ui.alert variant="info">{{ session('info') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="error">
            <p class="font-medium">Revisa los siguientes campos:</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif
</div>
