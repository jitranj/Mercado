<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mall Kiosk - React Version</title>

<script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>

<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Inter', sans-serif;
        background: #0f172a;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        color: #f8fafc;
    }

    .kiosk-container {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.4), 0 8px 16px -8px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        transition: all 0.3s ease;
    }

    .kiosk-header {
        background: linear-gradient(135deg, rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.1));
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 24px;
        text-align: center;
        position: relative;
    }

    .kiosk-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #3b82f6, transparent);
    }

    .kiosk-title {
        font-size: 24px;
        font-weight: 800;
        background: linear-gradient(to right, #60a5fa, #a78bfa);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 0;
        letter-spacing: -0.5px;
    }

    .kiosk-subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin: 8px 0 0 0;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .kiosk-body {
        padding: 32px;
    }

    .input-field {
        background: #0f172a;
        border: 1px solid #334155;
        color: #f8fafc;
        transition: all 0.3s;
        font-size: 16px;
    }

    .input-field:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
        color: white;
        font-weight: 700;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    }
    
    .btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #94a3b8;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #f8fafc;
    }

    .card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 20px;
        backdrop-filter: blur(10px);
    }

    .toggle-group {
        background: #0f172a;
        border: 1px solid #334155;
        padding: 4px;
        border-radius: 8px;
        display: flex;
    }

    .toggle-btn {
        flex: 1;
        padding: 10px 16px;
        border: none;
        background: transparent;
        color: #94a3b8;
        font-weight: 600;
        font-size: 13px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .toggle-btn.active {
        background: #3b82f6;
        color: white;
    }
    
    .toggle-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        text-decoration: line-through;
    }

    .qr-container {
        background: white;
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        display: inline-block;
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
        margin: 0 auto;
        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
    }

    .animate-fade {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #3b82f6;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>

<body class="flex items-center justify-center min-h-screen">

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function KioskApp() {
        const [step, setStep] = useState(1); 
        const [accNum, setAccNum] = useState("");
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState("");
        
        const [renter, setRenter] = useState(null);
        
        const [payType, setPayType] = useState('rent'); 
        const [amount, setAmount] = useState(0);
        const [refNo, setRefNo] = useState(""); 
        const [userRef, setUserRef] = useState(""); 

        const checkAccount = async (num) => {
            if(num.length < 10) return;
            setLoading(true);
            setError("");

            const fd = new FormData();
            fd.append('action', 'check_account');
            fd.append('account_number', num);

            try {
                const res = await fetch('api/api_kiosk.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    setRenter(data);
                    setStep(2);
                    
                    if (!data.is_rent_paid_up) {
                        setPayType('rent');
                        setAmount(data.monthly_rate);
                    } else if (!data.is_goodwill_paid_up) {
                        setPayType('goodwill');
                        setAmount(data.goodwill_balance);
                    } else {
                        setPayType('none'); 
                    }

                } else {
                    setError(data.message);
                    setAccNum("");
                }
            } catch (err) {
                setError("Network Error");
            }
            setLoading(false);
        };

        const handleTypeChange = (type) => {
            if (type === 'rent' && renter.is_rent_paid_up) return;
            if (type === 'goodwill' && renter.is_goodwill_paid_up) return;

            setPayType(type);
            if (type === 'rent') {
                setAmount(renter.monthly_rate); 
            } else {
                setAmount(renter.goodwill_balance); 
            }
        };

        const processPayment = async () => {
            if (amount <= 0) { alert("Invalid Amount"); return; }
            if (userRef.length < 5) { alert("Please enter the GCash Reference Number"); return; }
            
            if (payType === 'goodwill' && amount > renter.goodwill_balance) {
                alert(`Overpayment! Max amount is ₱${renter.goodwill_balance}`);
                return;
            }

            setLoading(true);
            const fd = new FormData();
            fd.append('action', 'pay');
            fd.append('renter_id', renter.renter_id);
            fd.append('amount', amount);
            fd.append('payment_type', payType);
            fd.append('reference_number', userRef); 
            
            if(payType === 'rent') {
                fd.append('month_for', renter.next_due_date);
            }

            try {
                const res = await fetch('api/api_kiosk.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    setRefNo(data.ref); 
                    setStep(3);
                } else {
                    alert("Payment Failed: " + data.message);
                }
            } catch (err) {
                alert("System Error");
            }
            setLoading(false);
        };

        const resetKiosk = () => {
            setStep(1);
            setAccNum("");
            setRenter(null);
            setRefNo("");
            setUserRef("");
        };


        return (
            <div className="w-full max-w-md kiosk-container animate-fade">
                
                <div className="kiosk-header">
                    <h1 className="kiosk-title">DIGITAL PAY KIOSK</h1>
                    <p className="kiosk-subtitle">Service Payment Terminal</p>
                </div>

                <div className="kiosk-body">
                    
                    {step === 1 && (
                        <div className="space-y-6">
                            <div className="text-center">
                                <label className="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">
                                    Enter Account Number
                                </label>
                                <input 
                                    type="number" 
                                    className="w-full p-4 text-3xl font-bold text-center input-field border-2 rounded-xl focus:outline-none tracking-widest"
                                    placeholder="0000 0000 00"
                                    value={accNum}
                                    onChange={(e) => {
                                        setAccNum(e.target.value);
                                        if(e.target.value.length === 10) checkAccount(e.target.value);
                                    }}
                                    autoFocus
                                />
                            </div>
                            
                            {loading && <div className="text-center text-blue-500 font-bold animate-pulse flex items-center justify-center gap-2">
                                <div className="loading-spinner"></div>
                                Searching Database...
                            </div>}
                            {error && <div className="text-center text-red-500 font-bold bg-red-50 p-3 rounded-lg border border-red-200">{error}</div>}
                        </div>
                    )}

                    {step === 2 && renter && (
                        <div className="space-y-6 animate-fade">
                            <div className="card">
                                <h2 className="text-lg font-bold text-white">{renter.name}</h2>
                                <p className="text-sm text-slate-400">{renter.stall}</p>
                            </div>

                            <div className="toggle-group">
                                <button 
                                    onClick={() => handleTypeChange('rent')}
                                    className={`toggle-btn ${payType === 'rent' ? 'active' : ''} ${renter.is_rent_paid_up ? 'disabled' : ''}`}
                                >
                                    {renter.is_rent_paid_up ? 'RENT (PAID)' : 'MONTHLY RENT'}
                                </button>
                                <button 
                                    onClick={() => handleTypeChange('goodwill')}
                                    className={`toggle-btn ${payType === 'goodwill' ? 'active' : ''} ${renter.is_goodwill_paid_up ? 'disabled' : ''}`}
                                >
                                    {renter.is_goodwill_paid_up ? 'GW (PAID)' : 'GOODWILL'}
                                </button>
                            </div>

                            {payType === 'none' ? (
                                <div className="text-center py-8">
                                    <div className="text-4xl mb-2">🎉</div>
                                    <h3 className="text-xl font-bold text-green-400">All Paid Up!</h3>
                                    <p className="text-sm text-slate-400 mt-1">You have no pending balances.</p>
                                    <button onClick={resetKiosk} className="mt-6 w-full py-3 btn-secondary rounded-xl">Go Back</button>
                                </div>
                            ) : (
                                <>
                                    <div className="text-center">
                                        {payType === 'rent' ? (
                                            <div className="mb-4">
                                                <p className="text-xs font-bold text-slate-400 uppercase">PAYING FOR</p>
                                                <p className="text-xl font-bold text-blue-400">{renter.next_due_display}</p>
                                            </div>
                                        ) : (
                                            <div className="mb-4">
                                                <p className="text-xs font-bold text-slate-400 uppercase">REMAINING BALANCE</p>
                                                <p className="text-xl font-bold text-orange-400">₱{renter.goodwill_balance.toLocaleString()}</p>
                                            </div>
                                        )}

                                        <label className="block text-xs font-bold text-slate-400 uppercase mb-2">AMOUNT TO PAY</label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-4 text-xl font-bold text-slate-400">₱</span>
                                            <input 
                                                type="number" 
                                                value={amount}
                                                onChange={(e) => {
                                                    let val = parseFloat(e.target.value);
                                                    if (payType === 'goodwill' && val > renter.goodwill_balance) val = renter.goodwill_balance;
                                                    setAmount(val);
                                                }}
                                                readOnly={payType === 'rent'}
                                                className={`w-full p-4 pl-10 text-3xl font-bold text-center border-2 rounded-xl focus:outline-none ${payType === 'rent' ? 'bg-slate-800 border-slate-600 text-slate-300' : 'input-field text-white'}`}
                                            />
                                        </div>
                                        {payType === 'rent' && <p className="text-xs text-slate-400 mt-2">Rent amount is fixed per contract.</p>}
                                    </div>

                                    <div className="flex justify-center my-4">
                                        <div className="qr-container">
                                            <img 
                                                src="style/qr.jpg" 
                                                alt="Scan to Pay" 
                                                className="w-72 h-72 object-cover rounded-md" 
                                            />
                                            <p className="text-center text-xs font-bold text-slate-500 mt-2">ELLI DE GUZMAN</p>
                                        </div>
                                    </div>

                                    <div className="mb-4">
                                        <label className="block text-xs font-bold text-slate-400 uppercase mb-2">
                                            Enter GCash Reference No.
                                        </label>
                                        <input 
                                            type="text" 
                                            value={userRef}
                                            onChange={(e) => setUserRef(e.target.value)}
                                            placeholder="REF NUMBER HERE"
                                            className="w-full p-3 text-center input-field rounded-lg font-mono tracking-widest uppercase border border-slate-600 focus:border-blue-500"
                                        />
                                    </div>

                                    <div className="space-y-3">
                                        <button 
                                            onClick={processPayment}
                                            disabled={loading}
                                            className="w-full py-4 px-6 btn-primary rounded-xl shadow-lg transform active:scale-95 transition-all disabled:opacity-50 font-bold text-lg"
                                        >
                                            {loading ? (
                                                <span className="flex items-center justify-center gap-2">
                                                    <div className="loading-spinner"></div>
                                                    PROCESSING...
                                                </span>
                                            ) : (
                                                'CONFIRM PAYMENT'
                                            )}
                                        </button>
                                        <button 
                                            onClick={resetKiosk}
                                            className="w-full py-3 text-slate-400 font-bold hover:text-slate-300 btn-secondary rounded-xl"
                                        >
                                            CANCEL
                                        </button>
                                    </div>
                                </>
                            )}
                        </div>
                    )}

                    {step === 3 && (
                        <div className="text-center space-y-6 animate-fade">
                            <div className="success-icon">
                                ✓
                            </div>
                            <div>
                                <h2 className="text-2xl font-bold text-white">Payment Successful!</h2>
                                <p className="text-slate-400">Your account has been updated.</p>
                            </div>
                            <div className="card font-mono text-sm">
                                <span className="block text-xs text-slate-400 uppercase">REFERENCE NUMBER</span>
                                <span className="text-lg font-bold text-yellow-400">{refNo}</span>
                            </div>
                            <button 
                                onClick={resetKiosk}
                                className="w-full py-4 px-6 btn-primary rounded-xl shadow-lg font-bold text-lg"
                            >
                                DONE
                            </button>
                        </div>
                    )}

                </div>
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<KioskApp />);
</script>
</body>
</html>