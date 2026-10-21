<x-layouts.admin title="Live" subtitle="Alle lopende veilingen op één scherm, met wat er de laatste minuten gebeurde. Ververst vanzelf.">
    <div class="mt-6" x-data="adminLive(@js(route('admin.live.panel')))">
        @include('admin.partials.live')
    </div>
</x-layouts.admin>
