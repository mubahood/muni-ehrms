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
      height: 300px;
      padding: 1rem;
    }
  </style>
  
  <div class="card">
    <div class="card-header">
      <h3>Requests per Department</h3>
      <a href="{{ admin_url('vehicles') }}" class="btn btn-sm btn-primary">
        View All Vehicles
      </a>
    </div>
    <div class="card-body">
      <canvas id="vehiclesAvailability"></canvas>
    </div>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const labels = {!! json_encode($labels) !!};
      const data   = {!! json_encode($values) !!};
  
      // Generate a color palette
      const colors = [
        '#277C61','#FF6384','#36A2EB','#FFCE56',
        '#4BC0C0','#9966FF','#FF9F40','#8A9B0F'
      ];
  
      const ctx = document.getElementById('vehiclesAvailability').getContext('2d');
      new Chart(ctx, {
        type: 'pie',
        data: {
          labels: labels,
          datasets: [{
            label: 'Requests',
            data: data,
            backgroundColor: colors.slice(0, labels.length),
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            title: {
              display: true,
              text: 'Requests per Department',
              font: { size: 18, weight: 'bold' },
              padding: { top: 10, bottom: 20 }
            },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  return `${ctx.label}: ${ctx.parsed}`;
                }
              }
            },
            legend: {
              position: 'top',
              labels: { font: { size: 12 } }
            }
          }
        }
      });
    });
  </script>
  