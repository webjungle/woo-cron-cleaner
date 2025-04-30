document.addEventListener('DOMContentLoaded',async()=>{
    if(!document.getElementById('wccc_kpi_avg_day'))return;
    const params=new URLSearchParams(location.search);
    const from=params.get('from')||'';
    const to=params.get('to')||'';
    const res=await fetch(`/wp-json/wccc/v1/stats?from=${from}&to=${to}`);
    const d=await res.json();
    document.getElementById('wccc_kpi_avg_day').textContent=d.avg.day;
    document.getElementById('wccc_kpi_avg_week').textContent=d.avg.week;
    document.getElementById('wccc_kpi_avg_month').textContent=d.avg.month;
    document.getElementById('wccc_kpi_saved').textContent=d.savedSec+' s';
    new Chart(document.getElementById('wccc_chart_donut'),{
        type:'doughnut',
        data:{labels:['Complete','Failed'],datasets:[{data:[d.donut.complete,d.donut.failed],backgroundColor:['#16a34a','#dc2626']}]},
        options:{plugins:{legend:{position:'bottom'}}}
    });
    document.getElementById('wccc_peak_info').textContent=d.peak.count+' Jobs am '+d.peak.date;
});