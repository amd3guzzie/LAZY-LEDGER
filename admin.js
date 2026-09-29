document.addEventListener('DOMContentLoaded', async () => {
    const res = await fetch('api/users.php');
    const data = await res.json();
    const tbody = document.getElementById('userTable');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3">No users found.</td></tr>';
        return;
    }

    data.forEach(user => {
        tbody.innerHTML += `<tr>
            <td style="color:#666;">${user.email}</td>
            <td style="font-weight:700;">${user.full_name}</td>
            <td style="text-transform:uppercase; font-size:12px; font-weight:800;">${user.role}</td>
        </tr>`;
    });
});