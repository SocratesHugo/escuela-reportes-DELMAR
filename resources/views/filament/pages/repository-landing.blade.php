{{-- resources/views/filament/pages/repository-landing.blade.php --}}
<x-filament::page>
    <style>
        .repo-title{
            font-weight: 800;
            color:#0b2a4a;
            font-size: clamp(24px, 3vw, 36px);
            margin-bottom: .75rem;
        }
        .repo-sub{
            color:#64748b;
            margin-bottom: 1.25rem;
        }
        .repo-grid{
            display:grid;
            gap: .85rem;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        @media (min-width: 720px){
            .repo-grid{ grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        .repo-card{
            display:flex; align-items:center; gap:.75rem;
            border:1px solid #e5e7eb; border-radius:.9rem;
            padding: .9rem 1rem; background: #fff;
            transition: box-shadow .18s ease, transform .18s ease;
        }
        .repo-card:hover{
            box-shadow: 0 6px 18px rgba(2, 6, 23, .06);
            transform: translateY(-1px);
        }
        .repo-card a{
            text-decoration:none; color:#0b2a4a; font-weight:700;
        }
        .repo-icon{
            width: 34px; height: 34px;
            display:grid; place-items:center;
            border-radius:.75rem; background:#f1f5f9;
        }
    </style>

    <div class="repo-title">Repositorio Middle School 2025-2026</div>
    <div class="repo-sub">Accesos rápidos</div>

    <div class="repo-grid">
        @foreach($links as $link)
            <div class="repo-card">
                <div class="repo-icon">
                    <x-filament::icon :icon="$link['icon'] ?? 'heroicon-m-link'" class="w-5 h-5 text-gray-700" />
                </div>

                <div style="display:flex;flex-direction:column;gap:.2rem">
                    <a href="{{ $link['href'] }}" target="_blank" rel="noopener">
                        {{ $link['label'] }}
                    </a>
                    <div style="color:#94a3b8; font-size:.85rem">{{ $link['href'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament::page>
