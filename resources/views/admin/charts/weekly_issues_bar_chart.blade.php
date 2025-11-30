<div class="modern-box">
    {{-- Changed the title to be more inclusive --}}
    <div class="box-header">Weekly Attendance Summary (Last 30 Days)</div>
    <div class="box-content">
        <canvas id="weeklyIssuesBarChart" style="height: 290px;"></canvas>
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
            // --- ADD THIS NEW DATASET for "On Time" ---
            {
                label: 'On Time',
                backgroundColor: '#00BF63', // Your primary green color
                data: @json($on_time_data)
            },
            // --- Existing datasets ---
            {
                label: 'Late Arrivals',
                backgroundColor: '#f39c12',
                data: @json($late_data)
            }, {
                label: 'Absences',
                backgroundColor: '#e74c3c',
                data: @json($absent_data)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { fontColor: '#576574', boxWidth: 15, padding: 20 }},
            scales: {
                xAxes: [{ gridLines: { display: false }, ticks: { fontColor: '#576574' } }],
                yAxes: [{
                    gridLines: { color: '#e0e6ed', borderDash: [2, 5],},
                    ticks: {
                        beginAtZero: true,
                        fontColor: '#576574',
                        callback: function(value) { if (Number.isInteger(value)) { return value; } },
                    }
                }]
            }
        }
    });
});
</script>