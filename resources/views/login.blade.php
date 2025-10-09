<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="{{ asset('public/css/login.css') }}">
</head>
<body>
<div class="login-container">
    <h1>FORM LOGIN</h1>
    <form id="loginForm" method="POST">
        @csrf
        <label for="email">Email :</label>
        <input type="email" id="email" name="email" placeholder="Masukkan email Anda" required>
        
        <label for="password">Password :</label>
        <input type="password" id="password" name="password" placeholder="Masukkan password" required>
        
        <button type="submit">Login</button>
        <div id="msg" style="margin-top:10px;"></div>
    </form>
</div>

<script>
const form = document.getElementById('loginForm');
const msg = document.getElementById('msg');

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    msg.textContent = 'Memproses...';

    const fd = new FormData(form);

    try {
        const res = await fetch("{{ route('login') }}", {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await res.json();

        if (data.success) {
            window.location.href = data.redirect;
        } else {
            msg.textContent = data.message || 'Gagal login';
            msg.style.color = '#c00';
        }
    } catch (err) {
        console.error(err);
        msg.textContent = 'Terjadi kesalahan server atau jaringan';
        msg.style.color = '#c00';
    }
});
</script>
</body>
</html>
