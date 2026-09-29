document.addEventListener('DOMContentLoaded', fetchRequests);

async function fetchRequests() {
    const res = await fetch('api/requests.php');
    const data = await res.json();
    const tbody = document.getElementById('requestQueue');
    tbody.innerHTML = data.length ? '' : '<tr><td colspan="3">No pending requests.</td></tr>';
    
    data.forEach(req => {
        tbody.innerHTML += `<tr>
            <td><strong>${req.requested_name}</strong></td><td>${req.requested_type}</td>
            <td><button onclick="updateRequest('${req.id}', 'approved')" style="background:var(--success); color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">Approve</button></td>
        </tr>`;
    });
}

async function updateRequest(id, status) {
    await fetch('api/requests.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, status }) });
    fetchRequests();
}