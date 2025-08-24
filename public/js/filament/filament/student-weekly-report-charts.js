/* public/js/filament/student-weekly-report-charts.js */
(function () {
  if (!window.__termChartData) return;

  const { subjects = [], columns = [], ids = [], table = {} } = window.__termChartData;

  // Paleta Delmar
  const brandBlue = '#0b2a4a';
  const brandBlueMid = '#143e6e';

  // Para cada trimestre construir dataset (una barra por materia)
  ids.forEach((canvasId, idx) => {
    const el = document.getElementById(canvasId);
    if (!el) return;

    // termId real está en el mismo orden que ids/columns
    const termId = Number(el.dataset.termId);

    const dataForTerm = subjects.map((s) => {
      const row = table[s] || {};
      const val = row[termId];
      return typeof val === 'number' ? val : null; // null para huecos
    });

    const everyNull = dataForTerm.every(v => v === null);
    if (everyNull) {
      // Si no hay datos, pinta sólo un texto
      const ctx = el.getContext('2d');
      ctx.save();
      ctx.font = '14px system-ui, -apple-system, Segoe UI, Roboto, Helvetica';
      ctx.fillStyle = '#6b7280';
      ctx.fillText('Sin datos para este trimestre', 10, 24);
      ctx.restore();
      return;
    }

    // eslint-disable-next-line no-undef
    new Chart(el, {
      type: 'bar',
      data: {
        labels: subjects,
        datasets: [{
          label: columns[idx] || 'Trimestre',
          data: dataForTerm,
          backgroundColor: brandBlueMid,
          borderColor: brandBlue,
          borderWidth: 1,
          borderRadius: 6,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { suggestedMin: 0, suggestedMax: 10, ticks: { stepSize: 2 } },
          x: { ticks: { autoSkip: false, maxRotation: 35, minRotation: 0 } }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed?.y;
                return (v === null || v === undefined) ? '—' : ` ${v.toFixed(2)}`;
              },
            },
          },
        },
      },
    });
  });
})();
