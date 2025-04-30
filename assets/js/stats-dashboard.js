// Woo Cron Cleaner – Dashboard Charts
document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('wccc_chart_total')) return;           // nur auf Stats-Seite

  const p  = new URLSearchParams(window.location.search);
  const from = p.get('from') || '';
  const to   = p.get('to')   || '';

  const res  = await fetch(`/wp-json/wccc/v1/stats?from=${from}&to=${to}`);
  const d    = await res.json();

  // KPI-Cards
  document.getElementById('wccc_kpi_total').textContent   = d.totals.complete + d.totals.failed;
  document.getElementById('wccc_kpi_success').textContent = d.successRate + '%';
  document.getElementById('wccc_kpi_saved').textContent   = d.savedSec + ' s';

  // Line-Chart (total vs failed)
  new Chart('#wccc_chart_total', {
    type:'line',
    data:{ labels:d.labels,
      datasets:[
        { data:d.total,  label:'total',  tension:.3, fill:true, backgroundColor:'rgba(59,130,246,.15)', borderColor:'#3b82f6' },
        { data:d.failed, label:'failed', tension:.3, fill:true, backgroundColor:'rgba(220,38,38,.15)', borderColor:'#dc2626' }
      ]},
    options:{ plugins:{legend:{display:false}}, scales:{x:{display:false}, y:{display:false}} }
  });

  // Doughnut Success-Rate
  new Chart('#wccc_chart_donut', {
    type:'doughnut',
    data:{ labels:['complete','failed'],
      datasets:[{ data:[d.totals.complete,d.totals.failed],
        backgroundColor:['#16a34a','#dc2626'] }]},
    options:{ plugins:{legend:{position:'bottom'}} }
  });

  // Fehler nach Plugin – Horizontal Bar
  const plLabels = Object.keys(d.plugins);
  const plVals   = Object.values(d.plugins);
  new Chart('#wccc_chart_plugins', {
    type:'bar',
    data:{ labels:plLabels, datasets:[{ data:plVals, backgroundColor:'#f97316' }] },
    options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true}} }
  });

  // Heatmap Fehler
  const matrixData = [];
  Object.entries(d.heat).forEach(([w,row])=>{
    Object.entries(row).forEach(([h,val])=>{
      matrixData.push({x:+h,y:+w,v:val});
    });
  });
  new Chart('#wccc_chart_heat',{
    type:'matrix',
    data:{ datasets:[{
      label:'failed',
      data:matrixData,
      width:20,
      height:20,
      backgroundColor(c){ const v=c.raw.v||0; return `rgba(220,38,38,${Math.min(1,v/5)})`; }
    }]},
    options:{ scales:{
      x:{type:'linear',min:0,max:23,ticks:{stepSize:4}},
      y:{type:'linear',min:0,max:6,
         ticks:{callback:v=>['So','Mo','Di','Mi','Do','Fr','Sa'][v]}}
    }}
  });
});
