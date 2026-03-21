document.addEventListener('DOMContentLoaded',()=>{
    const form = document.getElementById('loginForm');
    const msg =  document.getElementById('msg');

    document.getElementById('loginBtn').addEventListener('click', async(e)=>{
        e.preventDefault();
        msg.textContent = '';
        const email = document.getElementById('email').value.trim();
        const contrasena = document.getElementById('pwd').value;

        //adaptar al api
        try{
            const res=await fetch('/api/login',{//adaptar esta línea
                method:'POST',
                headers:{'Accept':'application/json',
                         'Content-Type':'application/json'
                        },
                body: JSON.stringify({ email, password: contrasena })
            });

            const data = await res.json();

            if(!res.ok){
                msg.textContent=data?.message || 'Error al iniciar sesión.';
                return;
            }

            if(!data.token){
                msg.textContent='Login sin token. Revisa Sanctum.';
                return;
            }

            localStorage.setItem('token', data.token);
            window.location.href='/dashboard.html';
        }catch(err){
            msg.textContent='Error: ' + err.message;
        }
    });
});