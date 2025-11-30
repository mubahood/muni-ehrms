<div class="modern-box">
    <div class="box-header">Monthly Attendance Overview</div>
    <div class="box-content">
        <canvas id="pieChart" style="height:290px"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('pieChart').getContext('2d'), {
        type: 'doughnut', /* Doughnut looks more modern */
        data: {
            labels: @json($labels),
            datasets: [{
                data: @json($data),
                backgroundColor: ['#00BF63', '#e74c3c', '#f39c12'],
                borderColor: '#ffffff',
                borderWidth: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutoutPercentage: 65, /* Makes the doughnut hole bigger */
            legend: {
                position: 'bottom',
                labels: {
                    fontColor: '#576574',
                    boxWidth: 15,
                    padding: 20
                }
            },
        }
    });
});
</script>