document.addEventListener('DOMContentLoaded', () => {
    const loginModal = document.getElementById('loginModal');
    const signupModal = document.getElementById('signupModal');
    
    if (document.getElementById('openLoginBtn')) {
        document.getElementById('openLoginBtn').addEventListener('click', () => loginModal.classList.remove('hidden'));
        document.getElementById('openSignupBtn').addEventListener('click', () => signupModal.classList.remove('hidden'));
        
        window.addEventListener('click', (e) => {
            if (e.target === loginModal) loginModal.classList.add('hidden');
            if (e.target === signupModal) signupModal.classList.add('hidden');
        });

        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'login', email: document.getElementById('loginEmail').value, password: document.getElementById('loginPassword').value })
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = data.role === 'admin' ? 'admin.html' : (data.role === 'staff' ? 'staff.html' : 'dashboard.html');
            } else {
                alert(data.error);
            }
        });

        document.getElementById('signupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'signup', name: document.getElementById('regName').value, email: document.getElementById('regEmail').value, password: document.getElementById('regPassword').value })
            });
            const data = await res.json();
            if (data.success) {
                alert('Account created! Please log in.');
                signupModal.classList.add('hidden');
            } else {
                alert(data.error);
            }
        });
    }

    if (document.getElementById('transactionList')) {
        fetchTransactions();
        
        document.querySelectorAll('#sidebarNav .nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                document.querySelectorAll('#sidebarNav .nav-link').forEach(l => l.classList.remove('active'));
                document.querySelectorAll('.tab-section').forEach(sec => sec.style.display = 'none');
                
                e.currentTarget.classList.add('active');
                document.getElementById(e.currentTarget.dataset.target).style.display = 'block';
                
                const tabName = e.currentTarget.textContent.trim();
                document.getElementById('mainHeader').textContent = tabName === 'Accounts' ? 'NET WORTH' : 'LEFT TO SPEND';
                document.getElementById('mainValue').textContent = tabName === 'Accounts' ? '₱45,700.00' : '₱12,450.00';
                document.getElementById('mainValue').className = `card-value ${tabName === 'Accounts' ? 'text-green' : 'text-red'}`;
            });
        });

        document.getElementById('addEntryBtn').addEventListener('click', async () => {
            await fetch('api/transactions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ description: 'New Demo Expense', amount: 150.00, type: 'expense' })
            });
            fetchTransactions();
        });

        document.getElementById('currencyBtn').addEventListener('click', async (e) => {
            if (e.target.textContent === 'View in USD') {
                const res = await fetch('https://api.frankfurter.dev/v1/latest?base=USD&symbols=PHP');
                const data = await res.json();
                document.getElementById('mainValue').textContent = `$${(12450 / data.rates.PHP).toFixed(2)}`;
                e.target.textContent = 'View in PHP';
            } else {
                document.getElementById('mainValue').textContent = '₱12,450.00';
                e.target.textContent = 'View in USD';
            }
        });
    }
});

async function fetchTransactions() {
    const res = await fetch('api/transactions.php');
    const data = await res.json();
    const tbody = document.getElementById('transactionList');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3">No transactions found.</td></tr>';
        return;
    }

    data.forEach(tx => {
        const isInc = tx.type === 'income';
        tbody.innerHTML += `<tr>
            <td style="color:#666;">${new Date(tx.transaction_date).toLocaleDateString()}</td>
            <td style="font-weight:700;">${tx.description}</td>
            <td style="font-weight:800; color:var(--${isInc ? 'success' : 'danger'});">${isInc ? '+' : '-'}₱${parseFloat(tx.amount).toFixed(2)}</td>
        </tr>`;
    });
}