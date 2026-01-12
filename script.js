/* script.js */

function switchFloor(f) {
    document.querySelectorAll('.floor-view').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.toggle-btn[id^="btn-f"]').forEach(el => el.classList.remove('active'));
    document.getElementById('floor-'+f).classList.add('active');
    document.getElementById('btn-f'+f).classList.add('active');
}
function toggleMode(btn, mode) {
    if(mode === 'payment') document.body.classList.add('mode-payment');
    else document.body.classList.remove('mode-payment');
    btn.parentElement.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

let chartInstance = null;
let currentChartType = 'bar';

function openAnalytics() {
    document.getElementById('analyticsModal').style.display = 'block';
    updateAnalytics();
}

function updateAnalytics() {
    const period = document.getElementById('reportPeriod').value;
    fetch(`api_analytics.php?period=${period}`)
        .then(r => r.json())
        .then(data => {
            animateValue('kpiRevenue', 0, parseInt(data.revenue_month), 1000, '₱');
            animateValue('kpiOccupancy', 0, parseFloat(data.occupancy.rate), 1000, '%');
            document.getElementById('kpiOccDetail').innerText = `${data.occupancy.occupied}/${data.occupancy.total} Units`;
            animateValue('kpiVacant', 0, data.occupancy.vacant, 1000, '');
            animateValue('kpiCritical', 0, data.red_list.length, 1000, '');

            const redList = document.getElementById('redListBody');
            redList.innerHTML = '';
            if(data.red_list.length > 0) {
                data.red_list.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid #f1f5f9';
                    row.style.transition = 'all 0.2s';
                    row.style.cursor = 'pointer';
                    row.onmouseover = () => row.style.background = '#f8fafc';
                    row.onmouseout = () => row.style.background = 'transparent';
                    row.onclick = () => openModal(item.stall_id);
                    row.innerHTML = `
                        <td style="padding:12px 8px;">
                            <div style="font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
                                <span style="width:8px; height:8px; background:#ef4444; border-radius:50%;"></span>
                                ${item.renter_name}
                            </div>
                            <div style="color:#64748b; font-size:11px; margin-top:2px;">${item.pasilyo} #${item.stall_number}</div>
                        </td>
                        <td style="color:#ef4444; font-weight:700; text-align:right; padding:12px 8px;">
                            ${item.months_due} Mos
                        </td>
                    `;
                    redList.appendChild(row);
                });
            } else {
                redList.innerHTML = '<tr><td colspan="2" style="padding:40px; text-align:center; color:#10b981; font-style:italic; font-size:14px;">🎉 All tenants are in good standing!</td></tr>';
            }

            updateChart(data.revenue_trend);
        })
        .catch(err => console.error("Analytics Error:", err));
}

function updateChart(revenueData) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    if(chartInstance) chartInstance.destroy();

    const labels = revenueData.map(d => d.month);
    const values = revenueData.map(d => parseInt(d.amount) || 0);

    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.8)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.1)');

    chartInstance = new Chart(ctx, {
        type: currentChartType,
        data: {
            labels: labels,
            datasets: [{
                label: 'Monthly Income',
                data: values,
                backgroundColor: currentChartType === 'bar' ? gradient : 'rgba(59, 130, 246, 0.8)',
                borderColor: '#3b82f6',
                borderWidth: 2,
                fill: currentChartType === 'line',
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    callbacks: {
                        label: function(context) {
                            return ' ₱ ' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return '₱' + (value/1000).toFixed(0) + 'k';
                        },
                        font: { size: 11 }
                    },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
}

function toggleChartType(type) {
    currentChartType = type;
    document.getElementById('chartTypeBar').style.background = type === 'bar' ? '#3b82f6' : '#e2e8f0';
    document.getElementById('chartTypeBar').style.color = type === 'bar' ? 'white' : '#64748b';
    document.getElementById('chartTypeLine').style.background = type === 'line' ? '#3b82f6' : '#e2e8f0';
    document.getElementById('chartTypeLine').style.color = type === 'line' ? 'white' : '#64748b';
    updateAnalytics();
}

function animateValue(id, start, end, duration, prefix = '', suffix = '') {
    const obj = document.getElementById(id);
    const range = end - start;
    const minTimer = 50;
    const stepTime = Math.abs(Math.floor(duration / range));
    const timer = stepTime < minTimer ? minTimer : stepTime;

    const startTime = new Date().getTime();
    const endTime = startTime + duration;

    function run() {
        const now = new Date().getTime();
        const remaining = Math.max((endTime - now) / duration, 0);
        const value = Math.round(end - (remaining * range));
        obj.innerText = prefix + value.toLocaleString() + suffix;
        if (value == end) {
            clearInterval(timerId);
        }
    }

    const timerId = setInterval(run, timer);
    run();
}

function exportReport() {
    const period = document.getElementById('reportPeriod').value;
    window.open(`api_analytics.php?action=export&period=${period}`, '_blank');
}

function viewAllDelinquents() {
    alert('Opening detailed delinquents report...');
}

function generateSOAReport() {
    window.open('soa_print.php?bulk=true', '_blank');
}

function sendPaymentReminders() {
    if(confirm('Send payment reminders to all delinquent tenants?')) {
        fetch('api_admin.php?action=send_reminders', { method: 'POST' })
            .then(r => r.json())
            .then(d => alert(d.message || 'Reminders sent successfully!'));
    }
}

function exportTenantData() {
    window.open('api_admin.php?action=export_tenants_csv', '_blank');
}

function openSettings() {
    document.getElementById('settingsModal').style.display = 'block';
}

function switchTab(tabId, btn) {
    document.querySelectorAll('.settings-content').forEach(c => { c.classList.remove('active'); c.style.display = 'none'; });
    document.querySelectorAll('.s-tab').forEach(t => { t.classList.remove('active'); t.style.background = 'transparent'; t.style.color = '#334155'; });
    const el = document.getElementById(tabId);
    if (el) { el.classList.add('active'); el.style.display = 'block'; }
    if (btn) { btn.classList.add('active'); btn.style.background = '#1e293b'; btn.style.color = 'white'; }
}

function changePass() {
    const formData = new FormData(document.getElementById('passForm'));
    fetch('api_admin.php', { method: 'POST', body: formData }).then(r=>r.json()).then(d => {
        if(d.success) { alert("Password Updated"); document.getElementById('settingsModal').style.display='none'; }
        else alert("Error");
    });
}

function addUser() {
    const formData = new FormData(document.getElementById('userForm'));
    fetch('api_admin.php', { method: 'POST', body: formData }).then(r=>r.json()).then(d => {
        if(d.success) { alert("User Created"); document.getElementById('userForm').reset(); }
        else alert("Error: " + d.message);
    });
}

function openModal(id) {
    if(!id) return;
    document.getElementById('stallModal').style.display = 'block';
    fetch(`api_stall_details.php?id=${id}`).then(r=>r.json()).then(d => {
        window.currentStallId = id;
        window.currentRenterId = d.renter ? d.renter.id : null;
        document.getElementById('modalTitle').innerText = "Stall " + d.stall.number;

        const btnAssign = document.getElementById('btnAssign');
        const btnPay = document.getElementById('btnRecordPay');
        const btnTerm = document.getElementById('btnTerminate');
        const btnContract = document.getElementById('btnViewContract');
        const btnSOA = document.getElementById('btnGenerateSOA');

        if(d.stall.status === 'occupied' && d.renter) {
            document.getElementById('renterDetails').style.display = 'block';
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('rName').innerText = d.renter.name;
            document.getElementById('rContact').innerText = d.renter.contact;
            
            let img = (d.renter.image && d.renter.image !== 'null') ? d.renter.image : `https://ui-avatars.com/api/?name=${d.renter.name}&background=random`;
            document.getElementById('mImage').src = img;
            document.getElementById('modalProfilePic').style.display = 'block';

            if(d.renter.contract && d.renter.contract !== 'null') {
                btnContract.style.display = 'block';
                btnContract.onclick = () => window.open(d.renter.contract, '_blank');
            } else {
                btnContract.style.display = 'none';
            }

            // WE USE THE GLOBAL USER_ROLE HERE
            btnPay.style.display = (USER_ROLE === 'admin' || USER_ROLE === 'staff_cashier') ? 'block' : 'none';
            btnTerm.style.display = (USER_ROLE === 'admin') ? 'block' : 'none';
            if(USER_ROLE === 'admin' || USER_ROLE === 'staff_billing') {
                btnSOA.style.display = 'block';
                btnSOA.onclick = () => window.open(`soa_print.php?id=${d.renter.id}`, '_blank');
            } else {
                btnSOA.style.display = 'none';
            }

            let hHtml = '';
            d.history.forEach(h => { hHtml += `<tr><td style="padding:5px 0;">${h.month_paid_for}</td><td>₱${h.amount}</td></tr>`; });
            document.getElementById('historyList').innerHTML = hHtml || '<tr><td colspan="2">No records</td></tr>';
        } else {
            document.getElementById('renterDetails').style.display = 'none';
            document.getElementById('modalProfilePic').style.display = 'none';
            document.getElementById('emptyState').style.display = 'block';
            document.getElementById('historyList').innerHTML = '';
            btnAssign.style.display = (USER_ROLE === 'admin' || USER_ROLE === 'staff_cashier') ? 'block' : 'none';
        }
    }).catch(e => console.error(e));
}

function closeModal() { document.getElementById('stallModal').style.display = 'none'; }
window.onclick = e => { 
    if(e.target == document.getElementById('stallModal')) closeModal(); 
    if(e.target == document.getElementById('settingsModal')) document.getElementById('settingsModal').style.display='none';
};

function togglePayForm() { 
    const f = document.getElementById('paymentForm'); 
    f.style.display = f.style.display==='none' ? 'block' : 'none'; 
    if(f.style.display==='block') document.getElementById('payRenterId').value = window.currentRenterId;
}
function toggleTenantForm() { 
    const f = document.getElementById('newTenantForm'); 
    f.style.display = f.style.display==='none' ? 'block' : 'none'; 
    if(f.style.display==='block') document.getElementById('wizardStallId').value = window.currentStallId;
}

function submitPayment() { fetch('api_add_payment.php', { method:'POST', body:new FormData(document.getElementById('payForm')) }).then(r=>r.json()).then(d=> { if(d.success) { alert("Saved"); location.reload(); } }); }
function submitNewTenant() { fetch('api_assign_tenant.php', { method:'POST', body:new FormData(document.getElementById('tenantForm')) }).then(r=>r.json()).then(d=> { if(d.success) { location.reload(); } else { alert(d.message); } }); }
function terminateContract() { 
    if(confirm("Terminate?")) {
        const fd = new FormData();
        fd.append('renter_id', window.currentRenterId);
        fd.append('stall_id', window.currentStallId);
        fetch('api_terminate.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=> { location.reload(); }); 
    }
}

let sTimer;
function liveSearch() {
    const q = document.getElementById('searchInput').value;
    const res = document.getElementById('searchResults');
    clearTimeout(sTimer);
    if(q.length < 2) { res.style.display='none'; return; }
    sTimer = setTimeout(() => {
        fetch(`api_search.php?q=${q}`).then(r=>r.json()).then(d => {
            res.innerHTML = '';
            if(d.length > 0) {
                res.style.display = 'block';
                d.forEach(i => {
                    const div = document.createElement('div');
                    div.style.cssText = "padding:10px; cursor:pointer; border-bottom:1px solid #0b0a0a; font-size:12px; display:flex; gap:10px; align-items:center;";
                    div.innerHTML = `<img src="${i.img||'default_avatar.png'}" style="width:24px; height:24px; border-radius:50%;"> <b>${i.label}</b>`;
                    div.onclick = () => { switchFloor(i.floor); setTimeout(() => openModal(i.id), 300); res.style.display='none'; };
                    res.appendChild(div);
                });
            } else res.style.display = 'none';
        });
    }, 300);
}

const hoverCard = document.getElementById('hoverCard');
const hoverName = document.getElementById('hoverName');
const hoverContact = document.getElementById('hoverContact');
const hoverImg = document.getElementById('hoverImg');
const hoverDue = document.getElementById('hoverDue');

document.querySelectorAll('.stall').forEach(stall => {
    stall.addEventListener('mouseenter', (e) => {
        if (stall.getAttribute('data-status') === 'occupied') {
            hoverName.innerText = stall.getAttribute('data-renter');
            hoverContact.innerText = stall.getAttribute('data-contact') || 'No Info';
            let img = stall.getAttribute('data-image');
            hoverImg.src = (img && img !== 'null') ? img : `https://ui-avatars.com/api/?name=${hoverName.innerText}&background=random`;
            let due = parseInt(stall.getAttribute('data-due'));
            hoverDue.innerText = due > 0 ? `${due} Mos Due` : "Paid";
            hoverDue.style.color = due > 0 ? "#ef4444" : "#10b981";
            hoverCard.style.display = 'block';
        }
    });
    stall.addEventListener('mousemove', (e) => {
        let x = e.clientX + 15; let y = e.clientY + 15;
        if(x + 230 > window.innerWidth) x -= 245;
        hoverCard.style.left = x + 'px'; hoverCard.style.top = y + 'px';
    });
    stall.addEventListener('mouseleave', () => { hoverCard.style.display = 'none'; });
});