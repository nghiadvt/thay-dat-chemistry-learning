(function () {
  function initCharts() {
    const payload = window.__ADMIN_DASHBOARD__;
    if (!payload || typeof Chart === 'undefined') return;

    const commonOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: { font: { family: 'system-ui, sans-serif', size: 12 } },
        },
      },
    };

    const dailyCanvas = document.getElementById('chartSessionsDaily');
    if (dailyCanvas && payload.sessionsDaily?.values?.some((v) => v > 0)) {
      new Chart(dailyCanvas, {
        type: 'bar',
        data: {
          labels: payload.sessionsDaily.labels,
          datasets: [{
            label: 'Phòng tạo mới',
            data: payload.sessionsDaily.values,
            backgroundColor: 'rgba(45, 70, 214, 0.75)',
            borderRadius: 6,
            maxBarThickness: 36,
          }],
        },
        options: {
          ...commonOptions,
          plugins: { ...commonOptions.plugins, legend: { display: false } },
          scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } },
          },
        },
      });
    }

    const statusCanvas = document.getElementById('chartSessionStatus');
    if (statusCanvas && payload.status?.values?.some((v) => v > 0)) {
      new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
          labels: payload.status.labels,
          datasets: [{
            data: payload.status.values,
            backgroundColor: payload.status.colors,
            borderWidth: 0,
          }],
        },
        options: {
          ...commonOptions,
          cutout: '62%',
        },
      });
    }

    const gamesCanvas = document.getElementById('chartTopGames');
    if (gamesCanvas && payload.topGames?.length) {
      new Chart(gamesCanvas, {
        type: 'bar',
        data: {
          labels: payload.topGames.map((g) => g.name),
          datasets: [{
            label: 'Phiên kết thúc',
            data: payload.topGames.map((g) => g.total),
            backgroundColor: 'rgba(22, 163, 74, 0.75)',
            borderRadius: 6,
          }],
        },
        options: {
          indexAxis: 'y',
          ...commonOptions,
          plugins: { ...commonOptions.plugins, legend: { display: false } },
          scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } },
            y: { grid: { display: false } },
          },
        },
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts);
  } else {
    initCharts();
  }
})();
