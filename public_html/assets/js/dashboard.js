// ============================================
// Dashboard — charts
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    if (typeof initIcons === 'function') initIcons();

    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }

    const data = window.DASHBOARD_DATA || {};

    // Brand colors
    const COLORS = {
        green:     '#0F5E3D',
        greenSoft: '#1E8449',
        greenLight:'#F1FDF6',
        text:      '#2C3E50',
        textMuted: 'rgba(44, 62, 80, 0.5)',
        divider:   '#E0E0E0',
        slate:     '#64748b',
    };

    // ============================================
    // Common chart options
    // ============================================
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = COLORS.textMuted;

    // ============================================
    // 1. Activity Trend — Line Chart
    // ============================================
    const trendCanvas = document.getElementById('activityTrendChart');
    if (trendCanvas && data.activityTrend) {
        const labels = data.activityTrend.map(d => d.label);
        const values = data.activityTrend.map(d => d.count);

        const ctx = trendCanvas.getContext('2d');

        // Gradient fill under the line
        const gradient = ctx.createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(15, 94, 61, 0.25)');
        gradient.addColorStop(1, 'rgba(15, 94, 61, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Actions',
                    data: values,
                    borderColor: COLORS.green,
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: COLORS.green,
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: COLORS.green,
                    pointHoverBorderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: COLORS.text,
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => `${ctx.parsed.y} action${ctx.parsed.y === 1 ? '' : 's'}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: COLORS.textMuted }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: COLORS.divider, drawBorder: false },
                        border: { display: false },
                        ticks: {
                            color: COLORS.textMuted,
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    // ============================================
    // 2. User Distribution — Doughnut Chart
    // ============================================
    const distCanvas = document.getElementById('userDistributionChart');
    if (distCanvas && data.distribution) {
        const faculty = data.distribution.faculty || 0;
        const staff   = data.distribution.staff   || 0;
        const total   = faculty + staff;

        new Chart(distCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Faculty', 'Staff'],
                datasets: [{
                    data: [faculty, staff],
                    backgroundColor: [COLORS.green, COLORS.slate],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 12,
                            font: { size: 11 },
                            color: COLORS.text
                        }
                    },
                    tooltip: {
                        backgroundColor: COLORS.text,
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => {
                                const value = ctx.parsed;
                                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${value} (${pct}%)`;
                            }
                        }
                    }
                }
            },
            plugins: [{
                // Draw total in the center
                id: 'centerText',
                afterDraw(chart) {
                    const { ctx, chartArea } = chart;
                    const centerX = (chartArea.left + chartArea.right) / 2;
                    const centerY = (chartArea.top + chartArea.bottom) / 2;

                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    // Total number
                    ctx.fillStyle = COLORS.text;
                    ctx.font = 'bold 22px Inter, sans-serif';
                    ctx.fillText(String(total), centerX, centerY - 6);

                    // Label
                    ctx.fillStyle = COLORS.textMuted;
                    ctx.font = '11px Inter, sans-serif';
                    ctx.fillText('Total', centerX, centerY + 14);

                    ctx.restore();
                }
            }]
        });
    }
});