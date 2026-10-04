// ============================================
// Staff Dashboard — charts
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initIcons === 'function') initIcons();
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }

    const data = window.CHART_DATA || {};
    const COLORS = {
        green:     '#0F5E3D',
        text:      '#2C3E50',
        textMuted: 'rgba(44, 62, 80, 0.5)',
        divider:   '#E0E0E0',
        blue:      '#3b82f6',
        rose:      '#f43f5e',
        purple:    '#a855f7',
    };

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = COLORS.textMuted;

    // Donut: leave type breakdown
    const breakdownCanvas = document.getElementById('breakdownChart');
    if (breakdownCanvas && data.breakdown) {
        const { vacation, sick, other } = data.breakdown;
        const total = (vacation || 0) + (sick || 0) + (other || 0);

        new Chart(breakdownCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Vacation', 'Sick', 'Other'],
                datasets: [{
                    data: [vacation, sick, other],
                    backgroundColor: [COLORS.blue, COLORS.rose, COLORS.purple],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 4,
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
                        padding: 8,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => {
                                const v = ctx.parsed || 0;
                                const pct = total > 0 ? Math.round((v / total) * 100) : 0;
                                return `${v} day(s) (${pct}%)`;
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
                    ctx.font = 'bold 16px Inter, sans-serif';
                    ctx.fillText(String(total), cx, cy - 4);
                    ctx.fillStyle = COLORS.textMuted;
                    ctx.font = '9px Inter, sans-serif';
                    ctx.fillText('days used', cx, cy + 12);
                    ctx.restore();
                }
            }]
        });
    }

    // Line: usage trend
    const trendCanvas = document.getElementById('trendChart');
    if (trendCanvas && data.trend) {
        const ctx = trendCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 160);
        gradient.addColorStop(0, 'rgba(15, 94, 61, 0.22)');
        gradient.addColorStop(1, 'rgba(15, 94, 61, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.trend.labels,
                datasets: [{
                    label: 'Approved days',
                    data: data.trend.values,
                    borderColor: COLORS.green,
                    backgroundColor: gradient,
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: COLORS.green,
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
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
                        padding: 8,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => `${ctx.parsed.y} day(s)`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: COLORS.textMuted, font: { size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: COLORS.divider },
                        border: { display: false },
                        ticks: {
                            color: COLORS.textMuted,
                            font: { size: 10 },
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
    }
});