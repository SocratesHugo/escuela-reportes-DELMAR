{{-- resources/views/filament/hooks/collapse-sidebar.blade.php --}}

<style>
  /* Colapsar el sidebar por defecto (si no está "open"). */
  body:not(.fi-sidebar-open) .fi-sidebar {
    display: none;
  }

  /* Cuando el sidebar está cerrado, deja que el contenido ocupe todo el ancho. */
  @media (min-width: 1024px) {
    body:not(.fi-sidebar-open) .fi-main {
      margin-left: 0 !important;
    }
  }
</style>

<script>
(function () {
  const KEY = 'filament.sidebar.open';

  // Si no existe preferencia, colapsamos por defecto.
  const stored = localStorage.getItem(KEY);
  if (stored === null) {
    document.body.classList.remove('fi-sidebar-open');
    localStorage.setItem(KEY, 'false');
  } else {
    document.body.classList.toggle('fi-sidebar-open', stored === 'true');
  }

  // Observamos cambios en la clase para persistir la preferencia al vuelo.
  new MutationObserver(() => {
    const open = document.body.classList.contains('fi-sidebar-open');
    localStorage.setItem(KEY, open ? 'true' : 'false');
  }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
})();
</script>
