<style>
    .card {
      border-radius: 5px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
      border: none;
    }
    .card-header {
      padding: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .card-header h3 {
      margin: 0;
      font-weight: 600;
    }
    .card-body {
      position: relative; 
      padding: 1rem;
    }
    </style>
    
    <div class="card mb-4 mb-md-5">
      <div class="card-header">
        <h3>
          <b>Requests Statistics - {{ count($labels) }} Months Overview</b>
        </h3>
        <a href="{{ admin_url('all-requests') }}" class="btn btn-sm btn-primary">
          View All Requests
        </a>
      </div>
      <div class="card-body">
        <canvas id="requestsFrequencyChart"></canvas>
      </div>
    </div>
    
    <script>
    $(function() {
      // 1) Get the data arrays
      var labels    = {!! json_encode($labels) !!};
      var vehicles  = {!! json_encode($vehicles) !!};
      var materials = {!! json_encode($materials) !!};
      var personels = {!! json_encode($personels) !!};
    
      // 2) Create the chart
      var ctx = document.getElementById('requestsFrequencyChart').getContext('2d');
      new Chart(ctx, {
        type: 'bar',               // root default
        data: {
          labels: labels,
          datasets: [
            {
              label: 'Vehicle Requests',
              data: vehicles,
              backgroundColor: 'rgba(39,124,97,0.8)',
              borderColor:   'rgba(39,124,97,1)',
              borderWidth: 1
            },
            {
              type: 'line',        // override to line
              label: 'Materials Requests',
              data: materials,
              backgroundColor: 'rgba(255,99,132,0.2)',
              borderColor:   'rgba(255,99,132,1)',
              fill: false
            },
            {
              type: 'line',
              label: 'Personnel Requests',
              data: personels,
              backgroundColor: 'rgba(54,162,235,0.2)',
              borderColor:   'rgba(54,162,235,1)',
              fill: false
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          title: {
            display: true,
            text: 'Requests Frequency Over Last 12 Months',
            fontSize: 18,
            fontStyle: 'bold',
            padding: 20
          },
          tooltips: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: function(item, data) {
                var ds = data.datasets[item.datasetIndex];
                return ds.label + ': ' + item.yLabel;
              }
            }
          },
          legend: {
            position: 'top',
            labels: {
              fontSize: 12
            }
          },
          scales: {
            xAxes: [{
              gridLines: { display: false },
              ticks: { fontSize: 12 }
            }],
            yAxes: [{
              ticks: {
                beginAtZero: true,
                fontSize: 12
              },
              gridLines: { color: 'rgba(200,200,200,0.2)' }
            }]
          }
        }
      });
    });
    </script>
    