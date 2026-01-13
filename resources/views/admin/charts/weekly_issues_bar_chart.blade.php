<div class="modern-box fade-in">
    <div class="box-header">
        <i class="fa fa-bar-chart" style="margin-right: 8px; color: var(--muni-maroon, #800000);"></i>
        Weekly Attendance Summary (Last 30 Days)
    </div>
    <div class="box-content">
        <canvas id="weeklyIssuesBarChart" style="height: 260px; max-height: 260px;"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var ctx = document.getElementById('weeklyIssuesBarChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($labels),
            datasets: [
                {
                    label: 'On Time',
                    backgroundColor: '#800000',
                    borderRadius: 4,
                    data: @json($on_time_data)
                },
                {
                    label: 'Late Arrivals',
                    backgroundColor: '#d69e2e',
                    borderRadius: 4,
                    data: @json($late_data)
                },
                {
                    label: 'Absences',
                    backgroundColor: '#e53e3e',
                    borderRadius: 4,
                    data: @json($absent_data)
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'bottom',
                labels: {
                    fontColor: '#4a5568',
                    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                    fontSize: 12,
                    boxWidth: 12,
                    padding: 16
                }
            },
            tooltips: {
                backgroundColor: '#1a202c',
                titleFontSize: 13,
                bodyFontSize: 12,
                cornerRadius: 6,
                xPadding: 12,
                yPadding: 10
            },
            scales: {
                xAxes: [{
                    gridLines: { display: false },
                    ticks: {
                        fontColor: '#4a5568',
                        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                        fontSize: 11
                    }
                }],
                yAxes: [{
                    gridLines: {
                        color: '#e2e8f0',
                        borderDash: [3, 3],
                        zeroLineColor: '#e2e8f0'
                    },
                    ticks: {
                        beginAtZero: true,
                        fontColor: '#4a5568',
                        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                        fontSize: 11,
                        callback: function(value) {
                            if (Number.isInteger(value)) { return value; }
                        }
                    }
                }]
            }
        }
    });
});
</script>