
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');

    toast.innerHTML = `<span style="font-size:18px;">${icon}</span> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function toggleMonthInput() {
    const type = document.getElementById('payType').value;
    const rentGroup = document.getElementById('rentInfoGroup');
    const amountInput = document.getElementById('payAmount');
    const orGroup = document.getElementById('orFieldGroup');

    if (type === 'rent') {
        rentGroup.style.display = 'block';
        if (orGroup) orGroup.style.display = 'block';

        amountInput.value = window.currentStallRate || 0;
        amountInput.readOnly = true;
        amountInput.style.backgroundColor = "#f1f5f9";
        amountInput.style.cursor = "not-allowed";

        const mInput = document.getElementById('payMonth');
        if (mInput) {
            mInput.readOnly = true;
            mInput.style.backgroundColor = "#f1f5f9";
        }
    } else {
        rentGroup.style.display = 'none';
        if (orGroup) orGroup.style.display = 'none';

        amountInput.value = '';
        amountInput.readOnly = false;
        amountInput.style.backgroundColor = "white";
        amountInput.style.cursor = "text";
        amountInput.placeholder = "Enter Amount";
    }
}

function switchFloor(f) {
    document.querySelectorAll('.floor-view').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.toggle-btn[id^="btn-f"]').forEach(el => el.classList.remove('active'));

    document.getElementById('floor-' + f).classList.add('active');
    document.getElementById('btn-f' + f).classList.add('active');

    localStorage.setItem('activeFloor', f);
}

function toggleMode(btn, mode) {
    if (mode === 'payment') document.body.classList.add('mode-payment');
    else document.body.classList.remove('mode-payment');

    if (btn) {
        const parent = btn.closest('.toggle-group') || btn.parentElement;
        parent.querySelectorAll('.toggle-btn, .nav-item').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
}

let chartInstance = null;
let currentChartType = 'bar';

function openAnalytics() {
    document.getElementById('analyticsModal').style.display = 'block';
    updateAnalytics();
}

function updateAnalytics() {
    const period = document.getElementById('reportPeriod').value;

    fetch(`api/api_analytics.php?period=${period}`)
        .then(r => r.json())
        .then(data => {
            animateValue('kpiRevenue', 0, parseInt(data.revenue_month), 1000, '₱');
            animateValue('kpiOccupancy', 0, parseFloat(data.occupancy.rate), 1000, '%');
            document.getElementById('kpiOccDetail').innerText = `${data.occupancy.occupied}/${data.occupancy.total} Units`;
            animateValue('kpiVacant', 0, data.occupancy.vacant, 1000, '');
            animateValue('kpiCritical', 0, data.red_list.length, 1000, '');

            const redList = document.getElementById('redListBody');
            redList.innerHTML = '';

            if (data.red_list && data.red_list.length > 0) {
                data.red_list.forEach((item) => {
                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid #f1f5f9';
                    row.style.cursor = 'pointer';
                    row.onclick = () => {
                        document.getElementById('analyticsModal').style.display = 'none';
                        setTimeout(() => openModal(item.stall_id), 300);
                    };
                    row.innerHTML = `
                        <td style="padding:12px 8px;">
                            <div style="font-weight:700; color:#1e293b;">${item.renter_name}</div>
                            <div style="color:#64748b; font-size:11px;">${item.pasilyo} #${item.stall_number}</div>
                        </td>
                        <td style="color:#ef4444; font-weight:700; text-align:right; padding:12px 8px;">
                            ${item.months_due} Mos
                        </td>
                    `;
                    redList.appendChild(row);
                });
            } else {
                redList.innerHTML = '<tr><td colspan="2" style="padding:20px; text-align:center; color:#10b981;">🎉 All accounts are healthy!</td></tr>';
            }

            if (data.revenue_trend) updateChart(data.revenue_trend);
        })
        .catch(err => {
            console.error("Analytics Error:", err);
            showToast("Failed to load analytics data", "error");
        });
}

function updateChart(revenueData) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();

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
                label: 'Income',
                data: values,
                backgroundColor: currentChartType === 'bar' ? gradient : 'rgba(59, 130, 246, 0.8)',
                borderColor: '#3b82f6',
                borderWidth: 2,
                fill: currentChartType === 'line',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { display: false },
                    ticks: { callback: (val) => '₱' + (val / 1000) + 'k' }
                },
                x: { grid: { display: false } }
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

function animateValue(id, start, end, duration, prefix = '') {
    const obj = document.getElementById(id);
    if (!obj) return;
    const range = end - start;
    const minTimer = 50;
    const stepTime = Math.abs(Math.floor(duration / range));
    const timer = stepTime < minTimer ? minTimer : stepTime;
    let current = start;

    const step = () => {
        current += (end > start ? 1 : -1) * Math.ceil(range / (duration / minTimer));
        if ((end > start && current >= end) || (end < start && current <= end)) current = end;
        obj.innerText = prefix + current.toLocaleString();
        if (current !== end) setTimeout(step, timer);
    };
    step();
}


function exportReport() {
    window.open('print/report_print.php?type=dashboard', '_blank');
}

function viewAllDelinquents() {
    window.open('print/report_print.php?type=red_list', '_blank');
}

function generateSOAReport() {
    window.open('print/soa_print.php?bulk=true', '_blank');
}

function sendPaymentReminders() {
    confirmAction("Send Reminders", "Send email reminders to ALL delinquent tenants?", () => {
        fetch('api/api_admin.php?action=send_reminders', { method: 'POST' })
            .then(r => r.json())
            .then(d => showToast(d.message || 'Reminders queued!', 'success'));
    }, 'info');
}

function exportTenantData() {
    window.open('api/api_admin.php?action=export_tenants_csv', '_blank');
}

function openSettings() {
    document.getElementById('settingsModal').style.display = 'block';
}

function switchTab(tabId, btn) {
    document.querySelectorAll('.settings-content').forEach(c => {
        c.classList.remove('active');
        c.style.display = 'none';
    });
    document.querySelectorAll('.s-tab').forEach(t => {
        t.classList.remove('active');
        t.style.background = 'transparent';
        t.style.color = '#64748b';
    });

    const el = document.getElementById(tabId);
    if (el) {
        el.classList.add('active');
        el.style.display = 'block';
    }
    if (btn) {
        btn.classList.add('active');
        btn.style.background = '#1e293b';
        btn.style.color = 'white';
    }

    if (tabId === 'tabAddUser') {
        loadUserList();
    }
}

function changePass() {
    const formData = new FormData(document.getElementById('passForm'));
    fetch('api/api_admin.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast("Password Updated Successfully", "success");
                document.getElementById('passForm').reset();
            } else {
                showToast("Error: " + (d.message || "Unknown error"), "error");
            }
        })
        .catch(err => showToast("Server Error", "error"));
}

function addUser() {
    const form = document.getElementById('userForm');
    const formData = new FormData(form);

    const pass = formData.get('password');
    if (pass.length < 8) {
        showToast("Password too short! Min 8 chars.", "error");
        return;
    }

    fetch('api/api_admin.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(text => {
            try {
                const d = JSON.parse(text);
                if (d.success) {
                    showToast("User Created Successfully!", "success");
                    form.reset();
                } else {
                    showToast("Failed: " + (d.message || "DB Error"), "error");
                }
            } catch (e) {
                console.error(text);
                showToast("Server Error: Check Console", "error");
            }
        });
}

function openModal(id) {
    if (!id) return;
    document.getElementById('stallModal').style.display = 'block';

    document.getElementById('renterDetails').style.display = 'none';
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('paymentForm').style.display = 'none';

    document.getElementById('historyList').innerHTML = '';
    if (document.getElementById('btnHistory')) document.getElementById('btnHistory').style.display = 'none';

    document.getElementById('rDate').innerText = "";

    document.getElementById('newTenantForm').style.display = 'none';
    document.getElementById('reserveForm').style.display = 'none';

    if (document.getElementById('editTenantForm')) document.getElementById('editTenantForm').style.display = 'none';

    if (document.getElementById('goodwillInfo')) document.getElementById('goodwillInfo').remove();
    if (document.getElementById('paidUpBadge')) document.getElementById('paidUpBadge').remove();
    if (document.getElementById('reservationPanel')) document.getElementById('reservationPanel').remove();

    const canFinancials = ['admin', 'manager', 'staff_billing', 'staff_cashier'].includes(USER_ROLE);
    const canOperations = ['admin', 'manager'].includes(USER_ROLE);
    const isMonitor = USER_ROLE === 'staff_monitor';

    fetch(`api/api_stall_details.php?id=${id}`).then(r => r.json()).then(d => {
        window.currentStallId = id;
        window.currentRenterId = d.renter ? d.renter.id : null;
        window.currentStallRate = d.stall.rate;

        document.getElementById('modalTitle').innerText = "Stall " + d.stall.number;

        const btnPay = document.getElementById('btnRecordPay');
        const btnTerm = document.getElementById('btnTerminate');
        const btnContract = document.getElementById('btnViewContract');
        const btnSOA = document.getElementById('btnGenerateSOA');
        const btnAssign = document.getElementById('btnAssign');
        const btnReserve = document.getElementById('btnReserve');
        const btnHistory = document.getElementById('btnHistory');

        if (d.renter) {
            if (d.renter.is_reservation == 1) {
                document.getElementById('renterDetails').style.display = 'block';
                document.getElementById('rName').innerText = d.renter.name + " (Applicant)";
                if (d.renter.billing_account_number) {
                    document.getElementById('rBan').innerText = d.renter.billing_account_number;
                    document.getElementById('banDisplay').style.display = 'block';
                } else {
                    document.getElementById('banDisplay').style.display = 'none';
                }

                document.getElementById('rContact').innerText = d.renter.contact;

                btnPay.style.display = 'none';
                btnTerm.style.display = 'none';
                btnContract.style.display = 'none';
                btnSOA.style.display = 'none';
                btnHistory.style.display = 'none';

                const resPanel = document.createElement('div');
                resPanel.id = 'reservationPanel';
                resPanel.style.cssText = "background:#fffbeb; border:1px solid #fcd34d; padding:20px; border-radius:8px; text-align:center; margin-top:20px;";

                let actionBtns = '';
                if (canOperations) {
                    actionBtns = `
                        <div style="display:flex; gap:10px; justify-content:center;">
                            <button onclick="startApproval()" style="padding:12px 20px; background:#10b981; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">
                                ✅ Approve
                            </button>
                            <button onclick="processReservation('cancel')" style="padding:12px 20px; background:#ef4444; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">
                                ❌ Reject
                            </button>
                        </div>
                    `;
                } else {
                    actionBtns = `
                        <div style="padding:10px; background:rgba(255,255,255,0.5); border:1px dashed #b45309; color:#92400e; font-weight:600; font-size:13px; border-radius:6px;">
                            🔒 Awaiting Manager Approval
                        </div>
                    `;
                }

                resPanel.innerHTML = `
                    <h3 style="margin:0 0 10px 0; color:#b45309;">⚠️ RESERVATION PENDING</h3>
                    <p style="font-size:13px; color:#78350f; margin-bottom:15px;">
                        This unit is reserved.
                    </p>
                    ${actionBtns}
                `;
                document.getElementById('renterDetails').appendChild(resPanel);

                let img = (d.renter.image && d.renter.image !== 'null') ? d.renter.image : `https://ui-avatars.com/api/?name=${d.renter.name}&background=random`;
                document.getElementById('mImage').src = img;
                document.getElementById('modalProfilePic').style.display = 'block';

            } else {
                document.getElementById('renterDetails').style.display = 'block';
                const rNameBox = document.getElementById('rName');

                rNameBox.style.display = 'block';
                rNameBox.style.textAlign = 'center';

                let editIcon = '';
                if (canOperations) {
                    editIcon = `
                    <span onclick="toggleEditMode()" 
                        style="cursor:pointer; font-size:16px; opacity:0.6; vertical-align:middle; display:inline-block; margin-left:3px;" 
                        title="Edit Details">
                        ✏️
                    </span>`;
                }

                rNameBox.innerHTML = `
                    <span style="font-size:20px; font-weight:800; color:#1e293b; vertical-align:middle;">${d.renter.name}</span>
                    ${editIcon}
                `;

                if (d.renter.billing_account_number) {
                    document.getElementById('rBan').innerText = d.renter.billing_account_number;
                    document.getElementById('banDisplay').style.display = 'block';
                } else {
                    document.getElementById('banDisplay').style.display = 'none';
                }

                window.currentRenterData = d.renter;

                document.getElementById('rContact').innerHTML = d.renter.contact +
                    (d.renter.email ? `<br><span style="font-size:12px; color:#3b82f6;">${d.renter.email}</span>` : '');

                document.getElementById('rDate').innerText = "Start Date: " + (d.renter.since || "N/A");

                const nextDue = d.renter.next_due;
                const today = new Date().toISOString().slice(0, 7);
                window.isRentPaidUp = (nextDue > today);

                window.goodwillBalance = parseFloat(d.renter.goodwill.balance);
                window.isGoodwillPaidUp = (window.goodwillBalance <= 0);

                document.getElementById('payMonth').value = nextDue;
                document.getElementById('payAmount').value = d.stall.rate;

                if (window.isRentPaidUp && window.isGoodwillPaidUp) {
                    let badge = document.createElement('div');
                    badge.id = 'paidUpBadge';
                    badge.innerHTML = "🌟 FULLY PAID (RENT & GOODWILL)";
                    badge.style.cssText = "background:#dcfce7; color:#166534; padding:12px; border-radius:8px; font-weight:800; text-align:center; margin-bottom:15px; border:1px solid #bbf7d0; font-size:14px;";
                    document.getElementById('renterDetails').insertBefore(badge, btnPay);

                    btnPay.style.display = 'none';
                }
                else if (window.isRentPaidUp && !window.isGoodwillPaidUp) {
                    let badge = document.createElement('div');
                    badge.id = 'paidUpBadge';
                    badge.innerHTML = "✅ RENT UP TO DATE";
                    badge.style.cssText = "background:#dcfce7; color:#166534; padding:12px; border-radius:8px; font-weight:700; text-align:center; margin-bottom:15px; border:1px solid #bbf7d0; font-size:14px;";
                    document.getElementById('renterDetails').insertBefore(badge, btnPay);

                    btnPay.style.display = canFinancials ? 'block' : 'none';
                    btnPay.innerText = "Pay Goodwill";
                }
                else if (!window.isRentPaidUp && window.isGoodwillPaidUp) {
                    let badge = document.createElement('div');
                    badge.id = 'paidUpBadge';
                    badge.innerHTML = "✅ GOODWILL FULLY PAID";
                    badge.style.cssText = "background:#dcfce7; color:#166534; padding:12px; border-radius:8px; font-weight:700; text-align:center; margin-bottom:15px; border:1px solid #bbf7d0; font-size:14px;";
                    document.getElementById('renterDetails').insertBefore(badge, btnPay);

                    btnPay.style.display = canFinancials ? 'block' : 'none';
                    btnPay.innerText = "Pay Bill for: " + nextDue;
                }
                else {
                    btnPay.style.display = canFinancials ? 'block' : 'none';
                    btnPay.innerText = "Pay Bill for: " + nextDue;
                }

                const gw = d.renter.goodwill;
                if (gw && gw.balance > 0) {
                    const gwDiv = document.createElement('div');
                    gwDiv.id = 'goodwillInfo';
                    gwDiv.style.cssText = "background:#fff7ed; border:1px solid #fdba74; color:#9a3412; padding:10px; border-radius:6px; margin-bottom:15px; font-size:12px; text-align:center;";
                    gwDiv.innerHTML = `
                        <div style="font-weight:800; font-size:13px;">⚠️ GOODWILL BALANCE: ₱${gw.balance.toLocaleString()}</div>
                        <div style="font-size:11px; opacity:0.9;">
                            Agreement: ₱${gw.total.toLocaleString()} | Paid: ₱${gw.paid.toLocaleString()}
                        </div>
                    `;
                    document.getElementById('rName').insertAdjacentElement('afterend', gwDiv);
                }

                let img = (d.renter.image && d.renter.image !== 'null') ? d.renter.image : `https://ui-avatars.com/api/?name=${d.renter.name}&background=random`;
                document.getElementById('mImage').src = img;
                document.getElementById('modalProfilePic').style.display = 'block';

                if (d.renter.contract && d.renter.contract !== 'null' && canFinancials) {
                    btnContract.style.display = 'block';
                    btnContract.onclick = () => window.open(d.renter.contract, '_blank');
                } else btnContract.style.display = 'none';

                btnTerm.style.display = canOperations ? 'block' : 'none';

                if (canFinancials) {
                    btnSOA.style.display = 'block';
                    btnSOA.onclick = () => window.open(`print/soa_print.php?id=${d.renter.id}`, '_blank');
                } else btnSOA.style.display = 'none';

                if (canFinancials) {
                    btnHistory.style.display = 'block';
                    btnHistory.onclick = () => window.open(`print/print_history.php?id=${d.renter.id}`, '_blank');
                } else {
                    btnHistory.style.display = 'none';
                }

                let hHtml = '';
                d.history.forEach(h => {
                    let label = '';
                    let amtStyle = 'font-weight:bold;';
                    let dateStr = h.payment_date ? new Date(h.payment_date).toLocaleDateString() : 'N/A';

                    let amtDisplay = `₱${parseInt(h.amount).toLocaleString()}`;
                    if (parseFloat(h.amount) === 0) {
                        amtDisplay = `<span style="color:#10b981; font-style:italic; letter-spacing:1px;">FREE</span>`;
                    }

                    if (h.payment_type === 'goodwill') {
                        label = `<span style="background:#ffedd5; color:#c2410c; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:800;">GW</span>`;
                        amtStyle += 'color:#c2410c;';
                    } else {
                        let billDate = new Date(h.month_paid_for);
                        let monthStr = billDate.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                        label = `<span style="background:#dbeafe; color:#1e40af; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:800;">RENT</span> 
                                 <span style="font-size:11px; color:#334155; font-weight:600; margin-left:4px;">${monthStr}</span>`;
                    }

                    hHtml += `
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px 0;">
                                <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px;">
                                    ${label}
                                </div>
                                <div style="font-size:10px; color:#94a3b8; font-family:monospace;">
                                    Paid: ${dateStr} <span style="color:#cbd5e1;">|</span> OR# ${h.or_no || '--'}
                                </div>
                            </td>
                            <td style="text-align:right; ${amtStyle}">${amtDisplay}</td>
                        </tr>`;
                });
                document.getElementById('historyList').innerHTML = hHtml || '<tr><td colspan="2" style="text-align:center; padding:15px; color:#94a3b8;">No payment history</td></tr>';
            }
        } else {
            document.getElementById('renterDetails').style.display = 'none';
            document.getElementById('modalProfilePic').style.display = 'none';
            document.getElementById('emptyState').style.display = 'block';
            document.getElementById('historyList').innerHTML = '';

            if (canOperations) {
                btnAssign.style.display = 'block';
                btnReserve.style.display = 'block';
            } else {
                btnAssign.style.display = 'none';
                btnReserve.style.display = 'none';
            }
        }
    }).catch(e => { console.error(e); showToast("Error loading details", "error"); });
}

function closeModal() {
    document.getElementById('stallModal').style.display = 'none';
}

window.onclick = e => {
    if (e.target == document.getElementById('stallModal')) closeModal();
    if (e.target == document.getElementById('settingsModal')) document.getElementById('settingsModal').style.display = 'none';
};


function togglePayForm() {
    const f = document.getElementById('paymentForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';

    if (f.style.display === 'block') {
        document.getElementById('payRenterId').value = window.currentRenterId;

        const rentOpt = document.querySelector('#payType option[value="rent"]');
        const gwOpt = document.querySelector('#payType option[value="goodwill"]');
        const payTypeSelect = document.getElementById('payType');

        rentOpt.disabled = window.isRentPaidUp;
        rentOpt.innerText = window.isRentPaidUp ? "Monthly Rent (Paid Up)" : "Monthly Rent";

        gwOpt.disabled = window.isGoodwillPaidUp;
        gwOpt.innerText = window.isGoodwillPaidUp ? "Goodwill (Fully Paid)" : "Goodwill";

        if (!window.isRentPaidUp) payTypeSelect.value = 'rent';
        else if (!window.isGoodwillPaidUp) payTypeSelect.value = 'goodwill';

        toggleMonthInput();
    }
}

function toggleTenantForm() {
    const r = document.getElementById('reserveForm');
    if (r) r.style.display = 'none';

    const f = document.getElementById('newTenantForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';

    if (f.style.display === 'block') {
        document.getElementById('wizardStallId').value = window.currentStallId;
        document.getElementById('wizReservationId').value = 0;
        document.getElementById('tenantForm').reset();
    }
}

function submitPayment() {
    const form = document.getElementById('payForm');
    const formData = new FormData(form);

    const type = formData.get('payment_type');
    const or_no = formData.get('or_no');

    if (type === 'rent' && (!or_no || or_no.trim() === '')) {
        showToast("⚠️ OR Number is required for Rent!", "error");
        return;
    }

    const amount = parseFloat(formData.get('amount') || 0);

    if (type === 'goodwill') {
        if (amount > window.goodwillBalance) {
            showToast(`⚠️ Cannot overpay! The remaining Goodwill balance is only ₱${window.goodwillBalance.toLocaleString()}.`, "error");
            return;
        }
    }

    fetch('api/api_add_payment.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(d => {
            if (d.success) {
                showToast("Payment Recorded!", "success");
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(d.message || "Error", "error");
            }
        })
        .catch(e => { console.error(e); showToast("Network Error", "error"); });
}

function submitNewTenant() {
    const form = document.getElementById('tenantForm');
    const fd = new FormData(form);

    const gwTotal = parseFloat(fd.get('goodwill_total') || 0);
    const initPay = parseFloat(fd.get('initial_payment') || 0);

    if (initPay > gwTotal) {
        showToast(`⚠️ Error: Payment (₱${initPay.toLocaleString()}) cannot exceed the total Goodwill of ₱${gwTotal.toLocaleString()}.`, "error");
        return;
    }

    fetch('api/api_assign_tenant.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast("Tenant Assigned Successfully!", "success");
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(d.message || "Error assigning tenant", "error");
            }
        });
}

function terminateContract() {
    requestPassword((adminPass) => {
        const fd = new FormData();
        fd.append('renter_id', window.currentRenterId);
        fd.append('stall_id', window.currentStallId);
        fd.append('admin_password', adminPass);

        fetch('api/api_terminate.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    showToast("Contract Terminated", "success");

                    closeModal();

                    if (typeof refreshMapBlock === "function") {
                        refreshMapBlock(window.currentStallId);
                    } else {
                        setTimeout(() => location.reload(), 1000);
                    }

                } else {
                    showToast(d.message || "Error terminating", "error");
                }
            })
            .catch(e => {
                console.error(e);
                showToast("Network Error (Check Console)", "error");
            });
    });
}


let sTimer;
function liveSearch() {
    const q = document.getElementById('searchInput').value;
    const res = document.getElementById('searchResults');
    clearTimeout(sTimer);

    if (q.length < 2) { res.style.display = 'none'; return; }

    sTimer = setTimeout(() => {
        fetch(`api/api_search.php?q=${q}`)
            .then(r => r.json())
            .then(d => {
                res.innerHTML = '';
                if (d.length > 0) {
                    res.style.display = 'block';
                    d.forEach(i => {
                        const div = document.createElement('div');
                        div.style.cssText = "padding:12px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:12px; display:flex; gap:10px; align-items:center; transition:background 0.2s;";
                        div.onmouseover = () => div.style.background = "#f8fafc";
                        div.onmouseout = () => div.style.background = "white";

                        div.innerHTML = `
                            <img src="${i.img && i.img !== 'null' ? i.img : 'style/default_avatar.png'}" style="width:30px; height:30px; border-radius:50%; object-fit:cover;"> 
                            <div>
                                <div style="font-weight:700; color:#1e293b;">${i.label}</div>
                                <div style="color:#64748b; font-size:11px;">${i.sub}</div>
                            </div>
                        `;
                        div.onclick = () => {
                            switchFloor(i.floor);
                            setTimeout(() => openModal(i.id), 300);
                            res.style.display = 'none';
                            document.getElementById('searchInput').value = '';
                        };
                        res.appendChild(div);
                    });
                } else {
                    res.style.display = 'none';
                }
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
        const status = stall.getAttribute('data-status');

        if (status === 'occupied' || status === 'reserved') {
            hoverName.innerText = stall.getAttribute('data-renter');
            hoverContact.innerText = stall.getAttribute('data-contact') || 'No Info';

            let img = stall.getAttribute('data-image');
            hoverImg.src = (img && img !== 'null') ? img : `https://ui-avatars.com/api/?name=${hoverName.innerText}&background=random`;

            const hoverStatusBadge = document.getElementById('hoverStatus');
            const hoverHeader = hoverCard.querySelector('div');

            if (status === 'reserved') {
                hoverStatusBadge.innerText = "RESERVED";
                hoverStatusBadge.style.color = "#fcd34d";
                hoverStatusBadge.style.background = "rgba(252, 211, 77, 0.2)";
                hoverHeader.style.background = "#78350f";

                hoverDue.innerText = "Pending Move-in";
                hoverDue.style.color = "#d97706";
            } else {
                hoverStatusBadge.innerText = "OCCUPIED";
                hoverStatusBadge.style.color = "white";
                hoverStatusBadge.style.background = "rgba(255,255,255,0.2)";
                hoverHeader.style.background = "#1e293b";

                let due = parseInt(stall.getAttribute('data-due'));
                if (due > 0) {
                    hoverDue.innerText = `${due} Months Due`;
                    hoverDue.style.color = "#ef4444";
                } else {
                    hoverDue.innerText = "Good Standing";
                    hoverDue.style.color = "#10b981";
                }
            }

            hoverCard.style.display = 'block';
        }
    });

    stall.addEventListener('mousemove', (e) => {
        let x = e.clientX - 245;
        let y = e.clientY - 110;

        if (x < 0) x = e.clientX + 15;
        if (y < 0) y = e.clientY + 15;

        hoverCard.style.left = x + 'px';
        hoverCard.style.top = y + 'px';
    });

    stall.addEventListener('mouseleave', () => {
        hoverCard.style.display = 'none';
    });
});


function toggleReserveForm() {
    const r = document.getElementById('reserveForm');
    const f = document.getElementById('newTenantForm');

    if (f) f.style.display = 'none';

    r.style.display = r.style.display === 'none' ? 'block' : 'none';

    if (r.style.display === 'block') {
        document.getElementById('resStallId').value = window.currentStallId;
    }
}

function submitReservation() {
    const form = document.getElementById('resForm');
    const formData = new FormData(form);

    if (!formData.get('renter_name')) {
        showToast("Please enter an Applicant Name", "error");
        return;
    }

    fetch('api/api_reserve.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(d => {
            if (d.success) {
                showToast("Reserved Successfully!", "success");
                setTimeout(() => location.reload(), 1200);
            }
            else { showToast(d.message || "Error reserving unit", "error"); }
        })
        .catch(e => { console.error(e); showToast("Network Error", "error"); });
}

function processReservation(action) {
    let title = action === 'approve' ? "Approve Tenant" : "Reject Reservation";
    let msg = action === 'approve'
        ? "Are you sure you want to convert this applicant into an Official Tenant?"
        : "Are you sure you want to cancel this reservation and open the unit?";
    let type = action === 'approve' ? 'info' : 'danger';

    confirmAction(title, msg, () => {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('stall_id', window.currentStallId);
        fd.append('renter_id', window.currentRenterId);

        fetch('api/api_reservation_action.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                if (d.success) {
                    showToast(d.message, "success");
                    setTimeout(() => location.reload(), 1000);
                }
                else { showToast(d.message, "error"); }
            })
            .catch(e => { console.error(e); showToast("Network Error", "error"); });
    }, type);
}

function startApproval() {
    const panel = document.getElementById('reservationPanel');
    if (panel) panel.style.display = 'none';

    const form = document.getElementById('newTenantForm');
    form.style.display = 'block';

    try {
        let name = document.getElementById('rName').innerText;
        name = name.replace(" (Applicant)", "").trim();
        document.querySelector('input[name="renter_name"]').value = name;

        let contact = document.getElementById('rContact').innerText.trim();
        document.querySelector('input[name="contact_number"]').value = contact;
    } catch (e) { console.log(e); }

    document.getElementById('wizardStallId').value = window.currentStallId;
    document.getElementById('wizReservationId').value = window.currentRenterId;
}


let loadedUsers = [];

function loadUserList() {
    fetch('api/api_admin.php?action=get_users')
        .then(r => r.json())
        .then(users => {
            loadedUsers = users;
            const tbody = document.getElementById('userTableBody');
            tbody.innerHTML = '';

            users.forEach((u, index) => {
                const row = document.createElement('tr');
                row.style.borderBottom = '1px solid #f1f5f9';
                row.innerHTML = `
                <td style="padding:10px; color:#1e293b; font-weight:600;">${u.username}</td>
                <td style="padding:10px; color:#64748b;">${u.role}</td>
                <td style="padding:10px; text-align:right;">
                    <button onclick="openEditUser(${index})" 
                            style="padding:4px 10px; background:#e2e8f0; border:none; border-radius:4px; font-size:11px; cursor:pointer; color:#334155;">
                        Edit
                    </button>
                </td>
            `;
                tbody.appendChild(row);
            });
        });
}

function toggleUserForm(view) {
    const btnDelete = document.getElementById('btnDeleteUser');

    if (view === 'create') {
        document.getElementById('userListView').style.display = 'none';
        document.getElementById('userFormView').style.display = 'block';
        document.getElementById('userFormTitle').innerText = "Create New User";
        document.getElementById('userFormAction').value = "add_user";
        document.getElementById('targetUserId').value = "";

        if (btnDelete) btnDelete.style.display = 'none';

        document.getElementById('adminUserForm').reset();

        const passInput = document.getElementById('uPass');
        passInput.name = "password";
        passInput.placeholder = "Create Password (Min 8 chars)";
        passInput.required = true;
        document.getElementById('passHint').innerText = "Required for new users";

    } else if (view === 'edit') {
        document.getElementById('userListView').style.display = 'none';
        document.getElementById('userFormView').style.display = 'block';

        if (btnDelete) btnDelete.style.display = 'block';

        document.querySelector('input[name="admin_password"]').value = "";

        const passInput = document.getElementById('uPass');
        passInput.name = "new_password";
        passInput.required = false;

    } else {
        document.getElementById('userListView').style.display = 'block';
        document.getElementById('userFormView').style.display = 'none';
        loadUserList();
    }
}

function openEditUser(index) {
    const u = loadedUsers[index];

    toggleUserForm('edit');
    document.getElementById('userFormTitle').innerText = "Edit " + u.username;
    document.getElementById('userFormAction').value = "admin_update_user";
    document.getElementById('targetUserId').value = u.user_id;

    document.getElementById('uUsername').value = u.username;
    document.getElementById('uRole').value = u.role;

    const passInput = document.getElementById('uPass');
    passInput.name = "new_password";
    passInput.placeholder = "Leave blank to keep current";
    passInput.required = false;
    document.getElementById('passHint').innerText = "Only fill to reset password";
}

function submitAdminUserForm() {
    const form = document.getElementById('adminUserForm');
    const fd = new FormData(form);
    const action = fd.get('action');

    if (!fd.get('admin_password')) {
        showToast("⚠️ Please enter YOUR admin password to confirm.", "error");
        return;
    }

    let userPass = "";
    if (action === 'add_user') userPass = fd.get('password');
    else if (action === 'admin_update_user') userPass = fd.get('new_password');

    if (userPass && userPass.length > 0 && userPass.length < 8) {
        showToast("⚠️ User password must be at least 8 characters.", "error");
        return;
    }

    fetch('api/api_admin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(d.message || "Success!", "success");

                form.reset();

                toggleUserForm('list');
                loadUserList();
            } else {
                showToast(d.message || "Error", "error");
            }
        })
        .catch(e => { console.error(e); showToast("Server Error", "error"); });
}

function deleteUser() {
    const form = document.getElementById('adminUserForm');
    const fd = new FormData(form);

    if (!fd.get('admin_password')) {
        showToast("⚠️ Enter YOUR Admin Password to confirm deletion.", "error");
        return;
    }

    confirmAction("Delete User", "Are you sure you want to PERMANENTLY DELETE this staff account? This cannot be undone.", () => {

        fd.set('action', 'delete_user');

        fetch('api/api_admin.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    showToast("User Deleted Successfully", "success");
                    toggleUserForm('list');
                } else {
                    showToast(d.message || "Error deleting user", "error");
                }
            })
            .catch(e => { console.error(e); showToast("Server Error", "error"); });

    });
}

function toggleEditMode() {
    document.getElementById('renterDetails').style.display = 'none';

    document.getElementById('editTenantForm').style.display = 'block';

    const d = window.currentRenterData;
    document.getElementById('editRenterId').value = d.id;
    document.getElementById('editName').value = d.name;
    document.getElementById('editContact').value = d.contact;
    document.getElementById('editEmail').value = d.email || '';

    document.getElementById('editStartDate').value = d.start_date || d.since;

    const passwordBox = document.querySelector('#formEditRenter input[name="admin_password"]');
    if (passwordBox) passwordBox.value = "";
}

function cancelEdit() {
    document.getElementById('editTenantForm').style.display = 'none';
    document.getElementById('renterDetails').style.display = 'block';
}

function submitEditRenter() {
    const form = document.getElementById('formEditRenter');
    const fd = new FormData(form);

    fetch('api/api_edit_renter.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast("Details Updated Successfully!", "success");

                const passwordBox = document.querySelector('#formEditRenter input[name="admin_password"]');
                if (passwordBox) passwordBox.value = "";

                openModal(window.currentStallId);
            } else {
                showToast(d.message || "Update Failed", "error");

                const passwordBox = document.querySelector('#formEditRenter input[name="admin_password"]');
                if (passwordBox) passwordBox.value = "";
            }
        })
        .catch(e => {
            console.error(e);
            showToast("Server Error", "error");
            const passwordBox = document.querySelector('#formEditRenter input[name="admin_password"]');
            if (passwordBox) passwordBox.value = "";
        });
}


function closeSysModal() {
    document.getElementById('systemModal').style.display = 'none';
}

function confirmAction(title, message, yesCallback, type = 'danger') {
    const m = document.getElementById('systemModal');
    const btn = document.getElementById('sysBtnConfirm');
    const icon = document.getElementById('sysIcon');

    document.getElementById('sysTitle').innerText = title;
    document.getElementById('sysMessage').innerText = message;

    if (type === 'danger') {
        icon.innerText = "⚠️";
        btn.style.background = "#ef4444";
        btn.innerText = "Yes, Do It";
    } else if (type === 'logout') {
        icon.innerText = "👋";
        btn.style.background = "#ef4444";
        btn.innerText = "Sign Out";
    } else {
        icon.innerText = "ℹ️";
        btn.style.background = "#10b981";
        btn.innerText = "Okay";
    }

    btn.onclick = function () {
        closeSysModal();
        if (yesCallback) yesCallback();
    };

    m.style.display = 'flex';
}

let pendingSecurityCallback = null;

function requestPassword(callback) {
    pendingSecurityCallback = callback;
    document.getElementById('secPassInput').value = '';
    document.getElementById('securityModal').style.display = 'flex';
    document.getElementById('secPassInput').focus();
}

function submitSecurityCheck() {
    const pass = document.getElementById('secPassInput').value;
    if (!pass) {
        showToast("⚠️ Password required", "error");
        return;
    }

    document.getElementById('securityModal').style.display = 'none';
    if (pendingSecurityCallback) {
        pendingSecurityCallback(pass);
        pendingSecurityCallback = null;
    }
}

(function () {
    const IDLE_LIMIT = 2 * 60 * 60 * 1000;
    const GRACE_PERIOD = 15 * 60 * 1000;

    let idleTimer;
    let logoutTimer;
    let isWarningActive = false;

    function startIdleTimer() {
        if (isWarningActive) return;

        clearTimeout(idleTimer);
        idleTimer = setTimeout(showInactivityWarning, IDLE_LIMIT);
    }

    function showInactivityWarning() {
        isWarningActive = true;

        const modal = document.getElementById('systemModal');
        const title = document.getElementById('sysTitle');
        const msg = document.getElementById('sysMessage');
        const btnYes = document.getElementById('sysBtnConfirm');
        const btnNo = document.getElementById('sysBtnCancel');
        const icon = document.getElementById('sysIcon');

        title.innerText = "Session Timeout";
        msg.innerText = "No activity detected for 2 hours. You will be signed out in 15 minutes.";
        icon.innerText = "⏰";

        btnYes.innerText = "I'm Here!";
        btnYes.style.background = "#10b981";
        btnNo.innerText = "Dismiss";

        const userIsAlive = () => {
            clearTimeout(logoutTimer);
            isWarningActive = false;
            modal.style.display = 'none';
            startIdleTimer();
        };

        btnYes.onclick = userIsAlive;
        btnNo.onclick = userIsAlive;

        modal.style.display = 'flex';

        logoutTimer = setTimeout(() => {
            window.location.href = 'logout.php';
        }, GRACE_PERIOD);
    }

    ['mousemove', 'mousedown', 'keypress', 'touchmove', 'scroll'].forEach(evt => {
        document.addEventListener(evt, startIdleTimer);
    });

    startIdleTimer();
})();


document.addEventListener('DOMContentLoaded', () => {
    const savedFloor = localStorage.getItem('activeFloor');
    if (savedFloor) {
        switchFloor(savedFloor);
    }
});

function copyBan() {
    const banText = document.getElementById('rBan').innerText.trim();
    
    if (!banText || banText === '--') return;

    const fallbackCopy = (text) => {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showToast("Account Number Copied! 📋", "success");
        } catch (err) {
            console.error('Fallback copy failed', err);
            prompt("Copy failed. Press Ctrl+C to copy:", text);
        }
        
        document.body.removeChild(textArea);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(banText)
            .then(() => {
                showToast("Account Number Copied! 📋", "success");
            })
            .catch(err => {
                console.warn("Modern copy failed, switching to fallback...");
                fallbackCopy(banText);
            });
    } else {
        fallbackCopy(banText);
    }
}

function toggleTheme() {
    const body = document.body;
    body.classList.toggle('light-mode');
    
    const isLight = body.classList.contains('light-mode');
    localStorage.setItem('mercado_theme', isLight ? 'light' : 'dark');
    
    document.getElementById('themeIcon').innerText = isLight ? '🌙' : '☀️';
}

document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('mercado_theme');
    if (savedTheme === 'light') {
        document.body.classList.add('light-mode');
        const icon = document.getElementById('themeIcon');
        if(icon) icon.innerText = '🌙';
    }
});