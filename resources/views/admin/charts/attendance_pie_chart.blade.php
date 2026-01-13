<div class="modern-box fade-in">
    <div class="box-header">
        <i class="fa fa-pie-chart" style="margin-right: 8px; color: var(--muni-maroon, #800000);"></i>
        Monthly Attendance Overview
    </div>
    <div class="box-content">
        <canvas id="pieChart" style="height: 260px; max-height: 260px;"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var ctx = document.getElementById('pieChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: @json($labels),
            datasets: [{
                data: @json($data),
                backgroundColor: ['#800000', '#e53e3e', '#d69e2e'],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverBorderWidth: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutoutPercentage: 60,
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
            }
        }
    });
});
</script>