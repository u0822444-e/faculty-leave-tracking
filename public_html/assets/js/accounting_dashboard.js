// ============================================
// Accounting Dashboard — charts
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  if (typeof Chart === 'undefined') return;

  const data = window.CHART_DATA || {};
  const COLORS = {
    green: '#0F5E3D',
    blue: '#3b82f6',
    purple: '#a855f7',
    amber: '#f59e0b',
    red: '#ef4444',
    slate: '#64748b',
    text: '#2C3E50',
    textMuted: 'rgba(44, 62, 80, 0.5)',
    divider: '#E0E0E0',
  };

  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 12;
  Chart.defaults.color = COLORS.textMuted;

  // Payroll liability bar
  const payrollCanvas = document.getElementById('payrollChart');
  if (payrollCanvas) {
    new Chart(payrollCanvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: ['Settled', 'Pending', 'Liability'],
        datasets: [{
          data: [
            data.payroll?.settled || 0,
            data.payroll?.pending || 0,
            data.payroll?.liability || 0,
          ],
          backgroundColor: [COLORS.green, COLORS.amber, COLORS.blue],
          borderRadius: 6,
          borderSkipped: false,
          barThickness: 40,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: COLORS.text,
            titleColor: '#fff',
            bodyColor: '#fff',
            padding: 10,
            cornerRadius: 8,
            displayColors: false,
            callbacks: {
              label: (ctx) => `₱${Number(ctx.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`
            }
          }
        },
        scales: {
          x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 } } },
          y: {
            beginAtZero: true,
            grid: { color: COLORS.divider },
            border: { display: false },
            ticks: {
              font: { size: 11 },
              callback: (v) => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v),
            }
          }
        }
      }
    });
  }

  // Settlement doughnut
  const settleCanvas = document.getElementById('settlementChart');
  if (settleCanvas) {
    const settled = data.payroll?.settled || 0;
    const pending = data.payroll?.pending || 0;
    const total = settled + pending;

    new Chart(settleCanvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Settled', 'Pending'],
        datasets: [{
          data: [settled, pending],
          backgroundColor: [COLORS.green, COLORS.amber],
          borderColor: '#ffffff',
          borderWidth: 3,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: COLORS.text,
            titleColor: '#fff',
            bodyColor: '#fff',
            padding: 10,
            cornerRadius: 8,
            displayColors: false,
            callbacks: {
              label: (ctx) => {
                const v = ctx.parsed || 0;
                const pct = total > 0 ? Math.round((v / total) * 100) : 0;
                return `₱${v.toLocaleString('en-PH', { minimumFractionDigits: 2 })} (${pct}%)`;
              }
            }
          }
        }
      },
      plugins: [{
        id: 'centerText',
        afterDraw(chart) {
          const { ctx, chartArea } = chart;
          const cx = (chartArea.left + chartArea.right) / 2;
          const cy = (chartArea.top + chartArea.bottom) / 2;
          ctx.save();
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillStyle = COLORS.text;
          ctx.font = 'bold 15px Inter, sans-serif';
          ctx.fillText('₱' + (total >= 1000 ? (total / 1000).toFixed(1) + 'k' : total.toFixed(0)), cx, cy - 6);
          ctx.fillStyle = COLORS.textMuted;
          ctx.font = '10px Inter, sans-serif';
          ctx.fillText('total settled + pending', cx, cy + 12);
          ctx.restore();
        }
      }]
    });
  }

  // Leave breakdown doughnut
  const breakCanvas = document.getElementById('breakdownChart');
  if (breakCanvas && data.breakdown) {
    new Chart(breakCanvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Vacation', 'Sick', 'Other'],
        datasets: [{
          data: [data.breakdown.vacation, data.breakdown.sick, data.breakdown.other],
          backgroundColor: [COLORS.blue, COLORS.red, COLORS.purple],
          borderColor: '#ffffff',
          borderWidth: 3,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: { legend: { display: false } }
      }
    });
  }

  // Usage trend line
  const trendCanvas = document.getElementById('trendChart');
  if (trendCanvas && data.trend) {
    new Chart(trendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: data.trend.labels,
        datasets: [{
          label: 'Approved days',
          data: data.trend.values,
          borderColor: COLORS.green,
          backgroundColor: 'rgba(15, 94, 61, 0.10)',
          borderWidth: 2,
          tension: 0.35,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: COLORS.green,
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, border: { display: false } },
          y: {
            beginAtZero: true,
            grid: { color: COLORS.divider },
            border: { display: false },
            ticks: { stepSize: 1, precision: 0 }
          }
        }
      }
    });
  }
});