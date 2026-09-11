<x-layouts.admin title="Overzicht" subtitle="Wat er nu gebeurt op het platform, elke paar seconden ververst.">
    <div class="mt-6" x-data="adminLive(@js(route('admin.activity')))">
        @include('admin.partials.activity')
    </div>
</x-layouts.admin>
